<?
function _getSearchAuto(){
		
    global $LG, $fx;
    global $MARKET, $COUNTRY;
    global $CONFIG_TEMPLATES_PARAMS;
    global $CONFIG_LISTAGEM_QTD, $PESQUISA_MIN_LENGTH;
    global $SOLR;
    global $solr_options;
    global $filters_convert;
    global $userID, $PESQUISA_SKUS_DIRECTOS;
    global $CONFIG_ORDEM;
    global $SETTINGS_LOJA;
    global $CACHE_KEY, $IP_WHITELIST, $SOLR_WITH_RANK, $CACHE_HEADER_FOOTER;
    global $SOLR_ORDEM_FILTROS, $slocation, $SOLR_VERSION;
    global $connection, $COM_PROD_CONFIG_PESQUISA, $db_name_cms;    
    
    
    $CONFIG_ORDEM_INIT = $CONFIG_ORDEM;
    
    # 2023-04-27
    # Como é um post efetuado em site tem sempre de ter o HTTP_REFERER do site
    # Esta validação ajuda a detetar posts diretos ao ficheiro nos ataques
    if(strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME']) === false) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    
   
		    
    # 2022-05-18
    $ARR_IP_WHITELIST = explode(";", $IP_WHITELIST);
    if( $offset==0 && !is_numeric($userID) && !in_array($_SERVER['REMOTE_ADDR'], $ARR_IP_WHITELIST)){ 
        $key = 'SEARCH_SOLR_'.$_SERVER[REMOTE_ADDR];            
        $x = 0 + @apc_fetch($key);
            
        if ($x>100)
        {
            $x++;
            apc_store($key, $x, 10);  #10min
            ob_end_clean();
            
            mysqli_close($connection);
            
            header('HTTP/1.1 403 Forbidden');
            exit;
        } else {
            $x++;
            apc_store($key, $x, 5);
        }
    }
    
    
    
    if( $offset==0 && is_numeric($userID) && !in_array($_SERVER['REMOTE_ADDR'], $ARR_IP_WHITELIST)){ 
        $key = 'SEARCH_SOLR_US_'.$_SERVER[REMOTE_ADDR];            
        $x = 0 + @apc_fetch($key);
              
        if ($x>200)
        {
            $x++;
            apc_store($key, $x, 10);  #10min
            ob_end_clean();
            
            mysqli_close($connection);
            
            header('HTTP/1.1 403 Forbidden');
            exit;
        } else {
            $x++;
            apc_store($key, $x, 5);
        }
    }
    
    
    
    
    if(isset($_GET) || (int)$_POST["total_prods"] > 6 || $_SERVER["HTTP_ORIGIN"] != $slocation ){
        ob_end_clean();
        
        mysqli_close($connection);
        
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    
    
    
    $CONFIG_TEMPLATES_PARAMS = array_merge($CONFIG_TEMPLATES_PARAMS, @get_options_bd('header'));
	
    
    
    # Para template da bazar onde sem nenhum termo mostra logo sugestões     
    if( ($SETTINGS_LOJA['base_3']['campo_5']==3 || $SETTINGS_LOJA['base_3']['campo_18']==12) && trim($_POST['terms'])==""){


        $scope            = array();
        $scope['PAIS']    = $_SESSION['_MARKET']['id'];
        $scope['LG']      = $_SESSION['LG'];
        
        $CACHEID = $CACHE_KEY."TEND_".implode('_', $scope);
  		
  	    $dados = $fx->_GetCache($CACHEID, $CACHE_HEADER_FOOTER);
  	    
  	    if ($dados!=false && $_GET['nocache']!=1)
  	    {
  	          $resp = $dados;  
              
  	    }else{

              $sql_search = "SELECT termo, MAX(data) as data_max, SUM(total) as total FROM searched WHERE id_mercado='".$MARKET["id"]."' AND tipo=0 AND lg='".$LG."' GROUP BY termo ORDER BY data DESC LIMIT 0,100";
              $res_search = cms_query($sql_search);
              $arr_search_most = array();
              while($row_search = cms_fetch_assoc($res_search)){
                 $arr_search_most[] = array(
                      "termo" => $row_search["termo"],
                      "total" => $row_search["total"]
                 );
              }
              
              usort($arr_search_most, function($a, $b) {
                  return $b['total'] <=> $a['total'];
              });
              
              $arr_search_most = array_slice($arr_search_most, 0, 10);
              
              $arr_terms_searched = array();
              foreach($arr_search_most as $k => $v){
                  $arr_terms_searched[] = $v["termo"];
              }
              
              $arr_resp = array(
                  "terms_searched" =>  $arr_terms_searched
              );
          
          
          		$resp = serialize($arr_resp);
              
           		$fx->_SetCache($CACHEID, $resp, $CACHE_HEADER_FOOTER);
       
       	}
     

        mysqli_close($connection);
        
        return $resp;

    }
    
    
    
    if(trim($_POST['terms'])==""){
        $resp = array();
        $resp['error'] = 1;   
        
        mysqli_close($connection);     
        return serialize($resp); 
    }
    
    
    


    #Limpar termo pesquisa
    if(isset($_SESSION['term_pesq']) && $_SESSION['term_pesq']!=$_POST['terms']){
        unset($_SESSION['filter_active'][36]);
    }
    $_SESSION['term_pesq'] = $_POST['terms'];

    $offset = (int)$_POST['offset'];

    $term  = strtolower($_POST['terms']);        
    $term  = html_entity_decode($term, ENT_QUOTES);  
    
    
    $term  = utf8_decode($term);
    
    # 2021-05-18 - quando se escrever directamtne p.e. calça na url é necessário um segundo decode  
    $term2 = utf8_decode($term);     
                
    $validUTF8 =! (false === mb_detect_encoding($term2, 'UTF-8', true));
                         
    if(!$validUTF8) {
        $term = $term2;                 
    }
    
                        



    $MAX_LENGTH = 3;
    if((int)$PESQUISA_MIN_LENGTH>0) $MAX_LENGTH = $PESQUISA_MIN_LENGTH;
    
    $palavras = explode (" ", $term);
    foreach ($palavras as $kk=>$_word) {
        if ( strlen($_word) < $MAX_LENGTH ){
            unset( $palavras[$kk] );
        }else{
            # 02/03/2021 - comentado url encode para permitir pesquisas com /
            #$palavras[$kk] = urlencode(clearVariable($_word)); 
            
            # 2025-04-30
            # clearVariable removido porqe com o utf8encode e phpsecurity já bem protegido enão queremos remover plicas ex: l'essence 
            #$palavras[$kk] = clearVariable($_word);
            if((int)$SOLR > 0) $palavras[$kk] = clearVariable($_word);
            else $palavras[$kk] = $_word;
        }
    }
    
    
    if(count($palavras)==0){
        $resp = array();
        $resp['error'] = 1;     
        
        mysqli_close($connection);   
        return serialize($resp); 
    }
    
  



    # Collect API  ***************************************************************************************************************
    $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
    global $collect_api;
    if( isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1 ){
        
        $event_info = [ 'term' => $term ];
        
        $collect_api->setEvent(CollectAPI::SEARCH, $_SESSION['EC_USER'], $event_info);
        
    } 
        
    
    
    
    $sinonimos_pesquisa = getSinonimosPesquisa($term);

    # Sinónimos
    foreach($sinonimos_pesquisa as $kk => $_sinonimo){

        if ( strlen($_sinonimo) < $MAX_LENGTH ){
            unset( $sinonimos_pesquisa[$kk] );
        }

        if((int)$SOLR>0){
            $sinonimos_pesquisa[$kk] = urlencode(clearVariable($_sinonimo));
        }

    }



    $index_last_term      = count($palavras) - 1;
    $last_term            = $palavras[$index_last_term];


    //Cria as sugestões de pesquisa
    $menu = array();
    $autocompleter        = array();
    $autocompleter_log    = array();
    $count_autocompleter  = 0;
    $resp_ = array();
    
    
    
    
    
    $_HEADERS = getallheaders();
    
    if($_HEADERS['Rdc-Origin']=='APP'){
        #Quando estamos em APP este parametro não é lido, herda por defeito o numero de podutos de uma listagem
        $CONFIG_TEMPLATES_PARAMS["header_quick_search_prod"] = $CONFIG_LISTAGEM_QTD;    
    }
    
    
    
    if((int)$_POST["total_prods"]>0){
    
       
        $scope            = array();
        $scope['PAIS']    = $_SESSION['_MARKET']['id'];
        $scope['LG']      = $_SESSION['LG'];
        $scope['TERMS']   = serialize($palavras);
        
        
        $CACHEID = $CACHE_KEY."TEND_TERM_".implode('_', $scope);
  		
  	    $dados = $fx->_GetCache($CACHEID, $CACHE_HEADER_FOOTER);
  	    
        
        $arr_terms_searched = array();
        
  	    if ($dados!=false && $_GET['nocache']!=1)
  	    {
  	          $arr_terms_searched = unserialize($dados);
              
  	    }else{
        
            $_query_search = array();
            foreach($palavras as $kk => $vv){
                $_query_order_arr[]  ="termo like '".cms_escape($vv)."%' ";
                $_query_order_arr[]  ="termo like '% ".cms_escape($vv)."%' ";
            }
            
            
            $arr_search_term = array();
            
            $sql_search = "SELECT termo, MAX(data) as data_max, SUM(total) as total FROM searched WHERE id_mercado='".$MARKET["id"]."' AND tipo=0 AND lg='".$LG."' AND (".implode(" OR ",$_query_order_arr).") GROUP BY termo ORDER BY data DESC LIMIT 0,100";
            $res_search = cms_query($sql_search);

            while($row_search = cms_fetch_assoc($res_search)){
               $arr_search_term[] = array(
                    "termo" => $row_search["termo"],
                    "data"  => $row_search["data"],
                    "total" => $row_search["total"]
               );
            }   
            
            usort($arr_search_term, function($a, $b) {
                return $b['total'] <=> $a['total'];
            });
            
            $arr_search_term = array_slice($arr_search_term, 0, 5);
                
           
            foreach($arr_search_term as $k => $v){
                $arr_terms_searched[] = $v["termo"];
            }
            
            
         		$fx->_SetCache($CACHEID, serialize($arr_terms_searched), $CACHE_HEADER_FOOTER);
        
       } 
       
       
        $CONFIG_LISTAGEM_QTD = $_POST["total_prods"];
          
    }
    
    
    
    if((int)$SOLR>0){
    
    
        $scope            = array();
        $scope['LG']      = $_SESSION['LG'];
        $scope['TERM']    = $term;
        
        $CACHEID = $CACHE_KEY."SUGES_".implode('_', $scope);
  		
  	    $dados_s = $fx->_GetCache($CACHEID, $CACHE_HEADER_FOOTER);
  	    

  	    if ($dados_s!=false && $_GET['nocache']!=1)
  	    {
              
  	         $autocompleter = $dados_s;
              
  	    }else{

      				$_endPoint = "http://".$solr_options["hostname"].":".$solr_options["port"]."/".$solr_options["path"]."/suggest_".$LG."?q=".urlencode($term);
              
              $ch = curl_init();
              curl_setopt($ch, CURLOPT_URL, $_endPoint);
              curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
              curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
              curl_setopt($ch, CURLOPT_TIMEOUT, 5);
              curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); 
              curl_setopt($ch, CURLOPT_SSLVERSION, 6);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
              curl_setopt($ch, CURLOPT_POST, 1);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
              $response = curl_exec($ch);
      
      
      
              $response = json_decode($response);
              
              
              
              if($response->responseHeader->status=="0"){
                  
                  foreach($response->spellcheck->suggestions[1]->suggestion as $key => $value){
      
                      if($count_autocompleter>10) break;
      
                      $value = strip_tags($value);
                      $res = explode($term, $value);
                      if(count($res)==1) continue;
      
                      $res = explode(' ', $res[1]);
      
                      $value = $term.$res[0];

                      $autocompleter[strip_tags($value)] = array("value" => $count_autocompleter, "label" => utf8_decode(strip_tags($value)));
                      
                      $count_autocompleter++;
                  }
                  
              }
      
              curl_close($ch);
              
               
              $autocompleter = array_values($autocompleter);                                     
              
              $fx->_SetCache($CACHEID, $autocompleter, $CACHE_HEADER_FOOTER);
        
        }
        
        
        $PROMO = getAvailablePromoForUser();
  

        $scope                  = array();        
        $scope['LG']            = $_SESSION['LG'];
        $scope['MARKET']        = $MARKET['id']; 
        $scope['PAIS']          = $_SESSION['_COUNTRY']['id'];       
        $scope['TERM']          = $term;
        $scope['TERMS']         = serialize($palavras);
        $scope['SYS_EXP_ZIP']   = $_COOKIE['SYS_EXP_ZIP'];
        $scope['QTD']           = $CONFIG_LISTAGEM_QTD;
        $scope['OFFSET']        = $offset;
        $scope['PROMO']         = implode(',', $PROMO["promos"]);
        $scope['SEG']           = $_SESSION["segmentos"];         
        $scope['lista_preco']   = $_SESSION['_MARKET']['lista_preco'];
                
        
        
        $CACHEID = $CACHE_KEY."PESQ_".implode('_', $scope);
  		
  	    $dados_p = $fx->_GetCache($CACHEID, $CACHE_HEADER_FOOTER);
  	    
  	    if (count($_POST["filters"])==0 && $dados_p!=false && $_GET['nocache']!=1)
  	    {

               $resp = unserialize($dados_p);
                
               $temp_products_info = $resp['TEMP_P'];
               $menu = $resp['MENU'];
               
               $resp_['result_count'] = $temp_products_info['COUNT'];
                
  	    }else{
        
        
            if($CONFIG_TEMPLATES_PARAMS["header_quick_search_prod"]>0 && (int)$_POST["suggestion"]==0){
               
                $temp = array();
                $temp_express = array();
                
                
                # 2025-01-13
                # Para ser possivel perquisar por um produto do configurador avançado
                if((int)$COM_PROD_CONFIG_PESQUISA > 0){
                
                    if((int)$PESQUISA_SKUS_DIRECTOS > 0){
                        $sql_configurador_av = "SELECT DISTINCT(sku) FROM registos_configurador_avancado WHERE sku_final LIKE '".cms_escape(implode('%',$palavras))."%' AND (idioma = '$LG' || idioma = '*') AND ativo = 1";
                    }else{
                        $sql_configurador_av = "SELECT DISTINCT(sku) FROM registos_configurador_avancado WHERE sku_final LIKE '%".cms_escape(implode('%',$palavras))."%' AND (idioma = '$LG' || idioma = '*') AND ativo = 1";
                    }
                    $res_configurador_av = cms_query($sql_configurador_av);
        
                    $temp_palavras = array();
                    while($row_configurador_av = cms_fetch_assoc($res_configurador_av)){
                        $temp_palavras[] = $row_configurador_av["sku"];
                    }
                     
                    $active_prod = cms_num_rows(cms_query("SELECT id FROM registos WHERE sku = '".$temp_palavras[0]."' AND activo = 1")); #melhoria - não procura nos artigos inativos
                        
                    if(count($temp_palavras) > 0 && $active_prod>0){
                        $palavras = $temp_palavras;
                        $term = implode(" ", $temp_palavras);
                    }
        
                } 
                
                if(count($palavras)>0){
                    
                    $client = new SolrClient($solr_options);
    
                    $catalogo = get_line_table_cache_api('registos_catalogo', "id='".$MARKET['catalogo']."' AND deleted='0'");
                    $q_stock = "";
                    if((int)$catalogo['only_with_stock']>0){
                        $q_stock = " AND inventory:[1 TO *]";
                    }
    
                    $arr_query = array();  
                    if(count($palavras)>1){
                        
                        $terms_string = clearVariable($term);
                        $terms_string = str_replace(" ", "\ ", $terms_string);
                        
                        $terms_string = str_replace("-", "\-", $terms_string);                
                        $palavras = str_replace("-", "\-", $palavras);
                        $sinonimos_pesquisa = str_replace("-", "\-", $sinonimos_pesquisa);
                        
                        
                        (count($sinonimos_pesquisa)>0) ? $t_sinonimo1 = "OR (".implode('\ ',$sinonimos_pesquisa).")" : $t_sinonimo1 = "";
                        (count($sinonimos_pesquisa)>0) ? $t_sinonimo2 = "OR (".implode('\ ',$sinonimos_pesquisa)."*)" : $t_sinonimo2 = "";
                        (count($sinonimos_pesquisa)>0) ? $t_sinonimo3 = "OR (*\ ".implode('\ ',$sinonimos_pesquisa)."*)" : $t_sinonimo3 = "";
                        
                        $arr_query[] = "name_".$LG.":((".$terms_string.") $t_sinonimo1)^8200";
                        $arr_query[] = "name_".$LG.":((".$terms_string."*) $t_sinonimo2)^8100";
                        $arr_query[] = "name_".$LG.":((*\ ".$terms_string."*) $t_sinonimo3)^8000";   
                        
                        
                        (count($sinonimos_pesquisa)>0) ? $sinonimo1 = "OR (".implode('\ ',$sinonimos_pesquisa).")" : $sinonimo1 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo2 = "OR (".implode('\ ',$sinonimos_pesquisa)."*)" : $sinonimo2 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo3 = "OR (*".implode('\ ',$sinonimos_pesquisa)."*)" : $sinonimo3 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo4 = "OR ((".implode('*) AND (*',$sinonimos_pesquisa)."*))" : $sinonimo4 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo5 = "OR ((*\ ".implode('*) AND (*\ ',$sinonimos_pesquisa)."*))" : $sinonimo5 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo6 = "OR ((*\ ".implode('*) AND (*\ ',$sinonimos_pesquisa)."*))" : $sinonimo6 = "";
                        
                        $arr_query[] = "name_".$LG.":((".implode('\ ',$palavras).") $sinonimo1)^5400";
                        $arr_query[] = "name_".$LG.":((".implode('\ ',$palavras)."*) $sinonimo2)^5300";
                        $arr_query[] = "name_".$LG.":((*\ ".implode('\ ',$palavras)."*) $sinonimo3)^5200";
                        
                        $arr_query[] = "name_".$LG.":((".implode('*) AND (*',$palavras)."*) $sinonimo4)^5100";
                        $arr_query[] = "name_".$LG.":((*\ ".implode('*) AND (*\ ',$palavras)."*) $sinonimo5)^5000";
                        
           
                        foreach ($palavras as $palavra1) {
                            foreach ($palavras as $palavra2) {
                                if ($palavra1 !== $palavra2) {
                                    $arr_query[] = "name_".$LG.":($palavra1 AND color:$palavra2)^4800";                              
                                    $arr_query[] = "name_".$LG.":($palavra1* AND color:$palavra2*)^4600";
                                }
                            }
                        }
                        
                        foreach ($palavras as $palavra1) {
                            foreach ($palavras as $palavra2) {
                                if ($palavra1 !== $palavra2) {
                                    $arr_query[] = "name_".$LG.":($palavra1 AND keywords_".$LG.":$palavra2)^4400";                              
                                    $arr_query[] = "name_".$LG.":($palavra1* AND keywords_".$LG.":$palavra2*)^4200";
                                }
                            }
                        }
                         
                        $arr_query[] = "family_".$LG.":((".implode('\ ',$palavras).") $sinonimo1)^540";
                        $arr_query[] = "family_".$LG.":((".implode('\ ',$palavras)."*) $sinonimo2)^530";
                        $arr_query[] = "family_".$LG.":((*\ ".implode('\ ',$palavras)."*) $sinonimo3)^520";                        
                        $arr_query[] = "family_".$LG.":((".implode('*) AND (*\ ',$palavras)."*) $sinonimo4)^510";
                        $arr_query[] = "family_".$LG.":((*\ ".implode('*) AND (*\ ',$palavras)."*) $sinonimo5)^500";
                                        
                        $arr_query[] = "subfamily_".$LG.":((".implode('\ ',$palavras).") $sinonimo1)^540";
                        $arr_query[] = "subfamily_".$LG.":((".implode('\ ',$palavras)."*) $sinonimo2)^530";
                        $arr_query[] = "subfamily_".$LG.":((*\ ".implode('\ ',$palavras)."*) $sinonimo3)^520";                        
                        $arr_query[] = "subfamily_".$LG.":((".implode('*) AND (*\ ',$palavras)."*) $sinonimo4)^510";
                        $arr_query[] = "subfamily_".$LG.":((*\ ".implode('*) AND (*\ ',$palavras)."*) $sinonimo5)^500";
                        
                        $arr_query[] = "keywords_".$LG.":((".implode('\ ',$palavras).") $sinonimo1)^540";
                        $arr_query[] = "keywords_".$LG.":((".implode('\ ',$palavras)."*) $sinonimo2)^530";
                        $arr_query[] = "keywords_".$LG.":((*\ ".implode('\ ',$palavras)."*) $sinonimo3)^520";
                        $arr_query[] = "keywords_".$LG.":((".implode('*) AND (',$palavras)."*) $sinonimo4)^62";
                        $arr_query[] = "keywords_".$LG.":((*\ ".implode('*) AND (*\ ',$palavras)."*) $sinonimo5)^500";
    
                        $arr_query[] = "promotion_".$LG.":((*\ ".implode('\ ',$palavras)."*) $sinonimo3)^300";                
                        
                                                
                        if((int)$PESQUISA_SKUS_DIRECTOS>0){
                            $arr_query[] = "sku:($terms_string*)^100";
                            $arr_query[] = "style:($terms_string*)^100";
                            $arr_query[] = "refcolor:($terms_string*)^100";                        
                        }else{
                            $arr_query[] = "sku:(*$terms_string*)^100";
                            $arr_query[] = "style:(*$terms_string*)^100";
                            $arr_query[] = "refcolor:(*$terms_string*)^100";
                        } 
            
                    }else{
                        
                        $terms_string = clearVariable($term);
                        $terms_string = str_replace(" ", "\ ", $terms_string);
                        
                        
                        $terms_string = str_replace("-", "\-", $terms_string);               
                        $sinonimos_pesquisa = str_replace("-", "\-", $sinonimos_pesquisa);
                        
                        (count($sinonimos_pesquisa)>0) ? $sinonimo1 = "OR (".implode(" OR ", $sinonimos_pesquisa).")" : $sinonimo1 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo2 = "OR (".implode("* OR ", $sinonimos_pesquisa)."*)" : $sinonimo2 = "";     
                        (count($sinonimos_pesquisa)>0) ? $sinonimo3 = "OR (*\ ".implode(" OR *\ ", $sinonimos_pesquisa).")" : $sinonimo3 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo4 = "OR (*\ ".implode("\ * OR *\ ", $sinonimos_pesquisa)."\ *)" : $sinonimo4 = "";
                        (count($sinonimos_pesquisa)>0) ? $sinonimo4 = "OR (*\ ".implode("* OR *\ ", $sinonimos_pesquisa)."*)" : $sinonimo5 = "";
                        
                        $arr_query[] = "name_".$LG.":(($terms_string) $sinonimo1)^5400";      
                        $arr_query[] = "name_".$LG.":(($terms_string*) $sinonimo2)^5300";      
                        $arr_query[] = "name_".$LG.":((*\ $terms_string) $sinonimo3)^5200";    
                        $arr_query[] = "name_".$LG.":((*\ $terms_string\ *) $sinonimo4)^5100";
                        $arr_query[] = "name_".$LG.":((*\ $terms_string*) $sinonimo5)^5000";
    
                        $arr_query[] = "color:(($terms_string) $sinonimo1)^820";
                        $arr_query[] = "color:(($terms_string*) $sinonimo2)^810";
                        $arr_query[] = "color:((*\ $terms_string*) $sinonimo5)^800";                    
                        
                        $arr_query[] = "keywords_".$LG.":(($terms_string) $sinonimo1)^620";
                        $arr_query[] = "keywords_".$LG.":(($terms_string*) $sinonimo2)^610";
                        $arr_query[] = "keywords_".$LG.":((*\ $terms_string*) $sinonimo5)^600";
                                            
                        $arr_query[] = "promotion_".$LG.":((*\ $terms_string*) $sinonimo5)^300";   
                        
                        if((int)$PESQUISA_SKUS_DIRECTOS>0){
                            $arr_query[] = "sku:($terms_string*)^100";
                            $arr_query[] = "style:($terms_string*)^100";
                            $arr_query[] = "refcolor:($terms_string*)^100";  
                        }else{ 
                            $arr_query[] = "sku:(*$terms_string*)^100";
                            $arr_query[] = "style:(*$terms_string*)^100";
                            $arr_query[] = "refcolor:(*$terms_string*)^100";        
                        }    
            
                    }
    
                                   
                    $filters_query = "";          
                    foreach($_POST["filters"] as $k => $v){
                        $arr_filters = explode("|||", $v);
                        $filters_query .= "(".$k."_".$LG.":(".implode(') OR '.$k."_".$LG.':(',$arr_filters).")) AND ";
                    }
                    
                    
                    #$dismaxQuery = new SolrDisMaxQuery("(    ( (".implode(' OR ',$arr_query).") AND new:0)^1 OR ( (".implode(' OR ',$arr_query).") AND new:1)^10   ) AND $filters_query market:$MARKET[id] AND active:1 $q_stock");
    
                    $dismaxQuery = new SolrDisMaxQuery("(".implode(' OR ',$arr_query).") AND $filters_query market:$MARKET[id] AND active:1 $q_stock ");

                    $dismaxQuery->setParam("defType", "edismax");
                   
                    $dismaxQuery->setParam('bf', "if(termfreq(new,'1'),10000,0)"); 
                      
                                                      
                    if((int)$SOLR_WITH_RANK>0) $dismaxQuery->addSortField("order", 1);   # 0 -> ASC || 1 -> DESC
                    else $dismaxQuery->setParam("sort", "new desc, score desc, name_$LG asc");   
            
            
                    $dismaxQuery->set("spellcheck", "off");
                    $dismaxQuery->set("q.op", "OR");
                    

    
                    $dismaxQuery->setStart($offset);
                    
                    $prods_quantity = $CONFIG_LISTAGEM_QTD;
                   
                    # Shipping Express
                    if( trim($_COOKIE['SYS_EXP_ZIP']) != '' && $offset == 0 ){
                        $prods_quantity = $CONFIG_LISTAGEM_QTD*2;    
                    }
                    # Shipping Express
    
                    $dismaxQuery->setRows($prods_quantity);
    
    
                    $dismaxQuery->setFacet(true);
                    $dismaxQuery->addFacetField('new')->addFacetField('promotion_'.$LG)->addFacetField('gender_'.$LG)->addFacetField('family_'.$LG)->addFacetField('subfamily_'.$LG)->addFacetField('category_'.$LG)->addFacetField('subcategory_'.$LG)->addFacetField('brand_'.$LG)->addFacetField('year_'.$LG)->addFacetField('season_'.$LG);
                    
                    if((int)$SOLR_VERSION>1){
                        $dismaxQuery->addFacetField('base_color_'.$LG);                    
                    }
                    
                    
                    $query_response = $client->query($dismaxQuery);
                    $response = $query_response->getResponse();
                    
                    #d($response);exit;
                    
                    if($response->response->numFound>0){
                    
                        $shipping_express_page = 0;
                        if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){
                            $shipping_express_page_row = cms_fetch_assoc( cms_query("SELECT `id` FROM `$db_name_cms`.`_trubricas` WHERE `sublevel`=74 AND `hidemenu`=0 AND `hidden`=0 AND `subpagina`=2") );
                            if( (int)$shipping_express_page_row['id'] > 0 ){
                                $shipping_express_page = $shipping_express_page_row['id'];
                            }                          
                        }
                        
                        foreach($response->response->docs as $key=>$value){
                            $prod = array();
                            $prod = call_api_func('get_product',$value["pid"], '', 36);
                            if($prod["id"]>0){
                               
                                # Shipping Express
                                if( trim($_COOKIE['SYS_EXP_ZIP']) != '' && $offset == 0 ){
                                    
                                    if( (int)$shipping_express_page > 0 ){
    
                                        $arr_skus = array();
                                        foreach( $prod["variants"] as $prod_variant ){
                                            $arr_skus[] = $prod_variant['barcode'];    
                                        }
    
                                        $express_zip_code = preg_replace( "/[^0-9]/", "", base64_decode($_COOKIE['SYS_EXP_ZIP']) );
                
                                        $sql_express = "SELECT GROUP_CONCAT( DISTINCT `registos_stocks`.`iddeposito` ) AS depositos
                                                        FROM `registos_stocks`
                                                        INNER JOIN `ec_depositos_codigos_postais` ON `ec_depositos_codigos_postais`.`deposito_id` = `registos_stocks`.`iddeposito` 
                                                            AND `ec_depositos_codigos_postais`.`cod_postal_inicio` <= '".$express_zip_code."'
                                                            AND `ec_depositos_codigos_postais`.`cod_postal_fim` >= '".$express_zip_code."' 
                                                            AND `ec_depositos_codigos_postais`.`pais_id`='".$COUNTRY['id']."'
                                                        WHERE `registos_stocks`.`sku` IN ('".implode("','", $arr_skus)."') 
                                                            AND ((registos_stocks.stock-registos_stocks.margem_seguranca)>0 OR `registos_stocks`.`venda_negativo`=1 OR `registos_stocks`.`produto_digital`=1)
                                                            AND `registos_stocks`.`iddeposito` IN (".$MARKET['deposito'].")
                                                        LIMIT 1"; 
                                                        
                                        $row_express = cms_fetch_assoc( cms_query($sql_express) );
    
                                    }
    
                                    if( trim($row_express['depositos']) != '' && count($temp_express) < 4 ){
                                        $prod['url'] = str_replace("id=36", "id=".$shipping_express_page, $prod['url']);
                                        $temp_express[] = $prod;  
                                    }elseif( trim($row_express['depositos']) == '' && ( ( count($temp) < 4 && (int)$shipping_express_page > 0 ) || count($temp) < $CONFIG_LISTAGEM_QTD ) ){
                                        $temp[] = $prod;    
                                    }
    
                                    if( ( count($temp_express) == 4 && count($temp) == 4) || ( (int)$shipping_express_page <= 0 && count($temp) == $CONFIG_LISTAGEM_QTD ) ) break;
    
                                # Shipping Express
                                }else{
                                    $temp[] = $prod;
                                }
    
                            }
                            
                        }
                    }
                    
                }
    
                if(count($temp)==0){
                    $response->response->numFound = 0;
                }
                
                
                # Shipping Express
                if( count($temp_express) > 0 ){
                    $temp_products_info["PRODUCTS_EXPRESS"] = $temp_express;
                }
                
                
                $temp_products_info["PRODUCTS"] = $temp;
                $temp_products_info["COUNT"] = $response->response->numFound;
                $temp_products_info["header_quick_search_prod"] = $CONFIG_TEMPLATES_PARAMS["header_quick_search_prod"];
                
                
                
                
                
                
                
    
                $resp_['result_count']       = $temp_products_info['COUNT'];
    
                $menu = array();
                $filters = array();
                $etiqueta = array();
    
                if(count($response->facet_counts->facet_fields)>0){
                    $filters = call_api_func('get_filters_page', 36);
                    $etiqueta = call_api_func('get_etiquetas', "2");
                }
                
                foreach($response->facet_counts->facet_fields as $k => $v){
                
                    $k = str_replace('_'.$LG, '', $k);
                    
                    if($k=="new"){
                        $arr_values = array();
                        $sel = 0;
                        if(isset($_POST["filters"][$k])) $sel = 1;
                        foreach($v as $vv => $kk){
                            $total = $total + $kk;
                            if($kk[1]>0){
                                $arr_values[] = array(
                                    "name" => utf8_decode($etiqueta[2]["title"]),
                                    "total" => $kk,
                                    "sel" => $sel
                                );
                            }
                        }
                        $sel = 0;
                        if(isset($_POST["filters"]["new"])) $sel = 1;
                        if(count($arr_values)>1){
                            $menu[] = array(
                                "name" => $etiqueta[2]["title"],
                                "field" => $k,
                                "sel" => $sel,
                                "total_products" => $total,
                                "values" => $arr_values
                            );
                        }
                    }
    
                    if($k=="promotion" && isset($filters["directos"]['promo'])){
                        $arr_values = array();
                        $total = 0;
                        $sel = 0;
                        if(isset($_POST["filters"][$k])) $sel = 1;
                        foreach($v as $vv => $kk){
                            $total = $total + $kk;
                            if($kk>0){
                                $arr_values[] = array(
                                    "name" => utf8_decode($vv),
                                    "total" => $kk,
                                    "sel" => $sel
                                );
                            }
                        }
                        $sel = 0;
                        if(isset($_POST["filters"][$k])) $sel = 1;
    
                        if(count($arr_values)>0){
                            $menu[] = array(
                                    "name" => $filters["directos"]['promo']['nome'],
                                    "field" => $k,
                                    "total_products" => $total,
                                    "values" => $arr_values);
                        }
                    }
    
    
                    foreach($filters["directos"] as $key=>$value){
                    
                        if($key=='promo') continue; #este é inlcuido em cima
                        
                        if($filters_convert[$key]==$k){
                            $total = 0;
                            $arr_values = array();
                            foreach($v as $vv => $kk){
                                $sel = 0;
                                $arr_filters = explode("|||", $_POST["filters"][$k]);
                                if(in_array($vv, $arr_filters)) $sel = 1;
                                $total = $total + $kk;
                                if($kk>0){
                                    
                                    $key_filtro = $vv;                                
                                    if( count($SOLR_ORDEM_FILTROS) > 0 && trim($SOLR_ORDEM_FILTROS[$key]) != ""){
                                        $fil = call_api_func('get_line_table', $SOLR_ORDEM_FILTROS[$key], "nome$LG='".$vv."'");
                                        if((int)$fil["id"]>0 && (int)$fil["ordem"]>0){
                                            $key_filtro = $fil["ordem"]."_".$fil["id"];
                                        }
                                    }
                                    
                                    $arr_values[$key_filtro] = array(
                                        "name" => utf8_decode($vv),
                                        "total" => $kk,
                                        "sel" => $sel
                                    );
                                }
                            }
                            $sel = 0;
                            if(isset($_POST["filters"][$k])) $sel = 1;
                            
                            if(count($arr_values)>0){
                            
                                ksort($arr_values);
                                
                                $arr_values = array_values($arr_values);
                                $menu[] = array(
                                    "name" => $value["nome"],
                                    "field" => $k,
                                    "sel" => $sel,
                                    "total_products" => $total,
                                    "values" => $arr_values
                                );
                            }
                       
                        }
                    }
    
    
                }
                
                
                if(is_callable('custom_controller_search_product_list')) {
                    call_user_func_array('custom_controller_search_product_list', array(&$temp_products_info));
                }
                
                
                
                $resp = array();
                $resp['TEMP_P'] = $temp_products_info;
                $resp['MENU'] = $menu;

           		if(count($_POST["filters"])==0) $fx->_SetCache($CACHEID, serialize($resp), $CACHE_HEADER_FOOTER);
                
            }
        
        }
        

    }else{

        $temp_products_info   = array();
        if($CONFIG_TEMPLATES_PARAMS["header_quick_search_prod"]>0){
        
            $CONFIG_LISTAGEM_QTD  = $CONFIG_TEMPLATES_PARAMS["header_quick_search_prod"];
            
            
            $_query_regras_arr = array();
            $_query_regras_arr_sk = array();
            $_query_regras_arr_sin = array(); 
            
            $_query_order_arr = array(); 
            
            
            
            # 2025-01-13
            # Para ser possivel perquisar por um produto do configurador avançado
            if((int)$COM_PROD_CONFIG_PESQUISA > 0){
            
                if((int)$PESQUISA_SKUS_DIRECTOS > 0){
                    $sql_configurador_av = "SELECT DISTINCT(sku) FROM registos_configurador_avancado WHERE sku_final LIKE '".cms_escape(implode('%',$palavras))."%' AND (idioma = '$LG' || idioma = '*') AND ativo = 1";
                }else{
                    $sql_configurador_av = "SELECT DISTINCT(sku) FROM registos_configurador_avancado WHERE sku_final LIKE '%".cms_escape(implode('%',$palavras))."%' AND (idioma = '$LG' || idioma = '*') AND ativo = 1";
                }
                $res_configurador_av = cms_query($sql_configurador_av);
    
                $temp_palavras = array();
                while($row_configurador_av = cms_fetch_assoc($res_configurador_av)){
                    $temp_palavras[] = $row_configurador_av["sku"];
                }
                
                $active_prod = cms_num_rows(cms_query("SELECT id FROM registos WHERE sku = '".$temp_palavras[0]."' AND activo = 1")); #melhoria - não procura nos artigos inativos
                
                if(count($temp_palavras) > 0 && $active_prod>0){
                    $palavras = $temp_palavras;
                    $term = implode(" ", $temp_palavras);
                }          
    
            }
            
            
          
                    
            # 2020-03-10 Usado pelo jom para CAMA não encontrar escamador no sku_family
            # Aprovado pelo Serafim
            if(count($palavras)>1){
                
                
                $car_implode = '%';
                if($B2B==1) $car_implode = ' ';
                
                
    						$_blocos_por_campo = [];
    						$campos = ["registos.desc$LG", "registos.descritores$LG", "registos.nome$LG"];
    						
    						foreach ($campos as $campo) {
    						    $_condicoes = [];
    						
    						    foreach ($palavras as $palavra) {
                        #$palavra = preg_replace('/[^a-zA-Z0-9 ]/u', ' ', $palavra);
    						        #$regex = '[[:<:]]' . preg_quote($palavra, '/') . '[[:>:]]';
                        #$_condicoes[] = "$campo REGEXP '$regex'";
                        
                        $palavra = preg_replace('/[^a-zA-Z0-9]/u', '_', $palavra);                                        
    						        $_condicoes[] = " ($campo LIKE '$palavra' OR $campo LIKE '$palavra %' OR $campo LIKE '% $palavra ' OR $campo LIKE '% $palavra %' OR $campo LIKE '% $palavra' ) ";
    						    }
    						
    						    $_blocos_por_campo[] = '(' . implode(' AND ', $_condicoes) . ')';
    						}
    						
    						$_query_regras = 'AND (((' . implode(' OR ', $_blocos_por_campo) . ')';
                
                
                /*$_query_regras = "AND (((registos.desc$LG LIKE '%".cms_escape(implode('%',$palavras))."%' OR registos.descritores$LG  LIKE '%".cms_escape(implode('%',$palavras))."%' OR registos.nome$LG  LIKE '%".cms_escape(implode('%',$palavras))."%')  ";
                
                $_query_order_arr[15]  ="when registos.nome{LG} like '".cms_escape($term)."' then '15'";
                $_query_order_arr[16]  ="when registos.nome{LG} like '".cms_escape($term)."%' then '16'";
                $_query_order_arr[17]  ="when registos.nome{LG} like '%".cms_escape($term)."%' then '17'";
                    
                $_query_order_arr[20]  ="when registos.nome{LG} like '".cms_escape(implode(' ',$palavras))."%' then '20'";    
                $_query_order_arr[40]  ="when registos.desc{LG} like '".cms_escape(implode(' ',$palavras))."%' then '40'";           
        
                $_query_order_arr[21]  ="when registos.nome{LG} like '%".cms_escape(implode('%',$palavras))."%' then '21'";    
                $_query_order_arr[41]  ="when registos.desc{LG} like '%".cms_escape(implode('%',$palavras))."%' then '41'";  
                         
                $_query_order_arr[60]  ="when registos.descritores{LG} like '%".cms_escape(implode('%',$palavras))."%' then '60'";*/
                
                foreach ($palavras as $kk => $vv){
                    $_query_regras_arr[] = " registos.descritores$LG LIKE '".cms_escape($vv)."%'  OR registos.descritores$LG LIKE '% ".cms_escape($vv)."%'  OR registos.desc$LG LIKE '".cms_escape($vv)."%' OR registos.desc$LG LIKE '% ".cms_escape($vv)."%' OR registos.nome$LG  LIKE '".cms_escape($vv)."%' OR registos.nome$LG  LIKE '% ".cms_escape($vv)."%'  ";
                    
                    /*$_query_order_arr[21+$kk]  ="when registos.nome{LG} like '".cms_escape($vv)."%' then '22'";
                    $_query_order_arr[25+$kk]  ="when registos.nome{LG} like '% ".cms_escape($vv)."%' then '24'";
                    
                    $_query_order_arr[41+$kk]  ="when registos.desc{LG} like '".cms_escape($vv)."%' then '42'";
                    $_query_order_arr[45+$kk]  ="when registos.desc{LG} like '% ".cms_escape($vv)."%' then '44'";
                    
                    $_query_order_arr[61+$kk]  ="when registos.descritores{LG} like '".cms_escape($vv)."%' then '62'";
                    $_query_order_arr[65+$kk]  ="when registos.descritores{LG} like '% ".cms_escape($vv)."%' then '64'";*/
                    
                    
                    # 2020-03-10 Usado pelo jom para CAMA não encontrar escamador no sku_family
                    # Aprovado pelo Serafim
                    if((int)$PESQUISA_SKUS_DIRECTOS>0){
                        $_query_regras_arr_sk[] = " registos.sku LIKE '".cms_escape($vv)."%' OR registos.sku_family LIKE '".cms_escape($vv)."%' OR registos.sku_group LIKE '".cms_escape($vv)."%' ";            
                        
                        $_query_order_arr[71+$kk]  ="when registos.sku like '".cms_escape($vv)."%' then '70'";
                        $_query_order_arr[72+$kk]  ="when registos.sku_family like '".cms_escape($vv)."%' then '70'";
                        $_query_order_arr[73+$kk]  ="when registos.sku_group like '".cms_escape($vv)."%' then '70'";
                        
                    }else{
                        $_query_regras_arr_sk[] = " registos.sku LIKE '%".cms_escape($vv)."%' OR registos.sku_family LIKE '%".cms_escape($vv)."%' OR registos.sku_group LIKE '%".cms_escape($vv)."%' ";
                        
                        $_query_order_arr[77+$kk]  ="when registos.sku like '%".cms_escape($vv)."%' then '75'";
                        $_query_order_arr[78+$kk]  ="when registos.sku_family like '%".cms_escape($vv)."%' then '75'";
                        $_query_order_arr[79+$kk]  ="when registos.sku_group like '%".cms_escape($vv)."%' then '75'";
        
                    }
        
                }             
            }else{
            
                $term = implode('%',$palavras);  
                
                $_query_regras = "AND (((registos.desc$LG LIKE '".cms_escape($term)."' OR registos.descritores$LG  LIKE '".cms_escape($term)."' OR registos.nome$LG  LIKE '".cms_escape($term)."')  ";  
                
                $_query_order_arr[10]  ="when registos.nome{LG} like '".cms_escape($term)."' then '10' ";
                $_query_order_arr[30]  ="when registos.desc{LG} like '".cms_escape($term)."' then '30'  ";            
                $_query_order_arr[50]  ="when registos.descritores{LG} like '%".cms_escape($term)."%' then '50' ";
    
                $_query_regras_arr[] = " registos.descritores$LG LIKE '".cms_escape($term)."%'  OR registos.descritores$LG LIKE '% ".cms_escape($term)."%'  OR registos.desc$LG LIKE '".cms_escape($term)."%' OR registos.desc$LG LIKE '% ".cms_escape($term)."%' OR registos.nome$LG  LIKE '".cms_escape($term)."%' OR registos.nome$LG  LIKE '% ".cms_escape($term)."%'  ";
                
                $_query_order_arr[21]  ="when registos.nome{LG} like '".cms_escape($term)." %' then '21'";
                $_query_order_arr[23]  ="when registos.nome{LG} like '".cms_escape($term)."%' then '23'";
                $_query_order_arr[25]  ="when registos.nome{LG} like '% ".cms_escape($term)."%' then '24'";
                
                $_query_order_arr[41]  ="when registos.desc{LG} like '".cms_escape($term)." %' then '41'";
                $_query_order_arr[43]  ="when registos.desc{LG} like '".cms_escape($term)."%' then '43'";
                $_query_order_arr[45]  ="when registos.desc{LG} like '% ".cms_escape($term)."%' then '44'";
                
                $_query_order_arr[61]  ="when registos.descritores{LG} like '".cms_escape($term)." %' then '61'";
                $_query_order_arr[63]  ="when registos.descritores{LG} like '".cms_escape($term)."%' then '63'";
                $_query_order_arr[65]  ="when registos.descritores{LG} like '% ".cms_escape($term)."%' then '64'";
                
                
                # 2020-03-10 Usado pelo jom para CAMA não encontrar escamador no sku_family
                # Aprovado pelo Serafim
                if((int)$PESQUISA_SKUS_DIRECTOS>0){
                    $_query_regras_arr_sk[] = " registos.sku LIKE '".cms_escape($term)."%' OR registos.sku_family LIKE '".cms_escape($term)."%' OR registos.sku_group LIKE '".cms_escape($term)."%' ";            
                    
                    $_query_order_arr[71]  ="when registos.sku like '".cms_escape($term)."%' then '70'";
                    $_query_order_arr[72]  ="when registos.sku_family like '".cms_escape($term)."%' then '70'";
                    $_query_order_arr[73]  ="when registos.sku_group like '".cms_escape($term)."%' then '70'";
                    
                }else{
                    $_query_regras_arr_sk[] = " registos.sku LIKE '%".cms_escape($term)."%' OR registos.sku_family LIKE '%".cms_escape($term)."%' OR registos.sku_group LIKE '%".cms_escape($term)."%' ";
                    
                    $_query_order_arr[71]  ="when registos.sku like '%".cms_escape($term)."%' then '70'";
                    $_query_order_arr[72]  ="when registos.sku_family like '%".cms_escape($term)."%' then '70'";
                    $_query_order_arr[73]  ="when registos.sku_group like '%".cms_escape($term)."%' then '70'";
    
                }
                            
            }             
                
            
            # Sinónimos    
            if(count($sinonimos_pesquisa) > 0){
                $_query_regras_sin .= "OR ((registos.desc$LG LIKE '%".cms_escape(implode('%',$sinonimos_pesquisa))."%' OR registos.descritores$LG  LIKE '%".cms_escape(implode('%',$sinonimos_pesquisa))."%' OR registos.nome$LG LIKE '%".cms_escape(implode('%',$sinonimos_pesquisa))."%') ";       
              
                $_query_order_arr[10]  ="when registos.nome{LG} like '".cms_escape(implode('%',$palavras))."' then '10' ";
                $_query_order_arr[15]  ="when registos.nome{LG} like '".cms_escape(implode('%',$palavras))."%' then '15' ";
                $_query_order_arr[20]  ="when registos.nome{LG} like '%".cms_escape(implode('%',$palavras))."%' then '20'"; 
                   
                $_query_order_arr[30]  ="when registos.desc{LG} like '".cms_escape(implode('%',$palavras))."' then '30'  ";            
                $_query_order_arr[35]  ="when registos.desc{LG} like '".cms_escape(implode('%',$palavras))."%' then '35'  ";            
                $_query_order_arr[40]  ="when registos.desc{LG} like '%".cms_escape(implode('%',$palavras))."%' then '40'";           
     
                $_query_order_arr[50]  ="when registos.descritores{LG} like '%".cms_escape(implode('%',$palavras))."%' then '50' ";
                 
               
                foreach ($sinonimos_pesquisa as $kk => $vv){
                    $_query_regras_arr_sin[] = " registos.descritores$LG LIKE '".cms_escape($vv)."%'  OR registos.descritores$LG LIKE '% ".cms_escape($vv)."%'  OR registos.desc$LG LIKE '".cms_escape($vv)."%' OR registos.desc$LG LIKE '% ".cms_escape($vv)."%' OR registos.nome$LG  LIKE '".cms_escape($vv)."%' OR registos.nome$LG  LIKE '% ".cms_escape($vv)."%'  ";
                    
                    $_query_order_arr[21+$kk]  ="when registos.nome{LG} like '".cms_escape($vv)."%' then '22'";
                    $_query_order_arr[25+$kk]  ="when registos.nome{LG} like '% ".cms_escape($vv)."%' then '24'";
                    
                    $_query_order_arr[41+$kk]  ="when registos.desc{LG} like '".cms_escape($vv)."%' then '42'";
                    $_query_order_arr[45+$kk]  ="when registos.desc{LG} like '% ".cms_escape($vv)."%' then '44'";
                    
                    $_query_order_arr[61+$kk]  ="when registos.descritores{LG} like '".cms_escape($vv)."%' then '62'";
                    $_query_order_arr[65+$kk]  ="when registos.descritores{LG} like '% ".cms_escape($vv)."%' then '64'";
                    
                }       
            }                       

            ksort($_query_order_arr);
        
            #$CONFIG_ORDEM = "CASE ".implode(" ",$_query_order_arr)." ELSE '9999' END ASC,".$CONFIG_ORDEM;
            $CONFIG_ORDEM_TEMP = $CONFIG_ORDEM;
            $CONFIG_ORDEM  = " score_relevancia DESC, CASE ".implode(" ",$_query_order_arr)." ELSE '9999' END ASC, ".$CONFIG_ORDEM_INIT;
            
            

            $temp_products_info = array();
            if( count($palavras)>0 ) {

                if( count($_query_regras_arr)>0 )  $_query_regras .= " OR (( ".implode(") AND (",$_query_regras_arr)." ) ) ";
                if( count($_query_regras_arr_sk)>0 )  $_query_regras .= " OR (( ".implode(") OR (",$_query_regras_arr_sk)." ) ) )";

                if(count($sinonimos_pesquisa) > 0){
                    $_query_regras .= $_query_regras_sin;
                    $_query_regras .= " OR (( ".implode(") AND (",$_query_regras_arr_sin)." ) ) )";
                }

                $_query_regras .= ")";       
                
                
                
                $campos = [
    						    "registos.nome$LG",
    						    "registos.descritores$LG",
    						    "registos.desc$LG",
    						    "registos.sku",
    						    "registos.sku_family",
                    "registos.sku_group"
    						];
    						
    						$partes = [];
    						
    						foreach ($campos as $campo) {
    						    foreach ($palavras as $palavra) {
    						        $palavra_esc = addslashes($palavra);
    						        $partes[] = "($campo LIKE '%$palavra_esc%')";
    						    }
    						}
    						
    						$add_score_sql = ",(\n  " . implode(" +\n  ", $partes) . "\n) AS score_relevancia";
                                                   

                $temp_products_info = call_api_func('get_products',36, $offset, $_query_regras, 1,0,0,0,0,0,0,$add_score_sql);
            }
            
            

        }

        $index_last_term      = count($palavras) - 1;
        $last_term            = $palavras[$index_last_term];
        $match_str            = "MATCH(`keyword`) AGAINST('$last_term*' IN BOOLEAN MODE)";
        $sql                  = "SELECT `keyword` FROM `search_engine` WHERE `mercado`='".$MARKET["id"]."' AND $match_str GROUP BY `keyword` ORDER BY `keyword` LIMIT 0,10";
        $res                  = cms_query($sql);
        
        while($row = cms_fetch_assoc($res)){
            $explode_keyword = explode(" ", $row['keyword']);
            foreach($explode_keyword as $k => $v){
                $v = trim($v);
                $v = str_replace(",","",$v);
                $v = str_replace("&","",$v);
                $v = strtolower($v);
                if( strlen($v) > 1 ){
                    if( stripos($v, $last_term) !== false && !array_key_exists($v, $autocompleter_log) ){
                        $tmp_aux                    = array();
                        $tmp_aux                    = $palavras;
                        $tmp_aux[$index_last_term]  = $v;
                        $tmp                        = array();
                        $tmp['value']               = $count_autocompleter;
                        $tmp['label']               = implode(" ", $tmp_aux);
                        array_push($autocompleter, $tmp);
                        $autocompleter_log[$v]      = $v;
                        $count_autocompleter++;
                    }
                }
            }
        }

        $resp_['result_count']       = $temp_products_info['COUNT'];
        $resp_['filters']            = $temp_products_info['FILTROS'];

        $resp_['filters_encode']     = encodeFilters(36);

        $tot_filtros = 0;
        foreach($_SESSION['filter_active'][36] as $k => $v){
            $tot_filtros += count($v);
        }

        $resp_['active_filters']       = ( isset($_SESSION['filter_active'][36]) ) ? 1 : 0;
        $resp_['total_active_filters'] = $tot_filtros;


    }

    if( count($temp_products_info['PRODUCTS'])>0 && $offset==0){
        if(is_numeric($userID) ){
            
            $menos_2min = date("Y-m-d H:i:s", strtotime("-10 seconds"));    
            
            $s = "select id from searched WHERE `data`>='$menos_2min' AND id_cliente='".$userID."' ORDER BY id DESC LIMIT 0,1 ";
            $found = cms_fetch_assoc(cms_query($s));

            if($found['id']>0){
                $sql = "UPDATE searched SET termo='$term', total='1', id_cliente='".$userID."', data=NOW() WHERE id='".$found['id']."' ";
            }else{
                $sql = "insert into searched set termo='$term', total='1', id_cliente='".$userID."', lg='".$LG."', id_mercado='".$MARKET["id"]."' on duplicate key update total=total+1, data=NOW() ";
            }
            cms_query($sql);
            
        }else{
            $_SESSION["termos_pesquisa"][$term] = array(
                                                 "termo"      => $term,
                                                 "lg"         => $LG,
                                                 "id_mercado" => $MARKET["id"]
                                             );
        }
    }

    $resp['shop'] = call_api_func('OBJ_shop_mini');

    if($resp['shop']['wishlist']>0){
        foreach($temp_products_info["PRODUCTS"] as $k => $v){
            $temp_products_info["PRODUCTS"][$k]["wishlist"] = call_api_func('verify_product_wishlist', $v['sku_family'], $userID);
        }
    }
    
    # Entrega Geograficamente Limitada
    $has_geo_limited_delivery = hasGeoLimitedDelivery();
    if( !empty($has_geo_limited_delivery) ){
        $shipping_express = cms_fetch_assoc( cms_query("SELECT GROUP_CONCAT(id) AS shipping_ids FROM `ec_shipping` WHERE `geo_limited`=1 AND `id` IN(".$_SESSION['_MARKET']['metodos_envio'].")") );
    }
    # Entrega Geograficamente Limitada
        
    foreach($temp_products_info['PRODUCTS'] as $k => $v){
            
        if( count($_SESSION['EC_USER']['deposito_express']['depositos']) > 0 || !empty($has_geo_limited_delivery) ){
            
            get_tags_express($temp_products_info['PRODUCTS'][$k]);
            
            # Entrega Geograficamente Limitada
            if( $shipping_express['shipping_ids'] != "" ){
            
                $prod = call_api_func("get_line_table", "registos", "id='".$v['id']."'");
                if( trim($prod['generico30']) != "" && count( array_intersect( explode(",", $prod['generico30']), explode(",", $shipping_express['shipping_ids']) ) ) > 0 ){
                    $temp_products_info['PRODUCTS'][$k]['allow_add_cart'] = 0;
                }
                
            }
            # Entrega Geograficamente Limitada
                        
        }    

    }
    
    # Reviews
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){
        $arr_sku_family = array();
        foreach($temp_products_info['PRODUCTS'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];
        }    
        $arr_review_product            = call_api_func('get_reviews_product_by_sku_familys', $arr_sku_family);
        foreach($temp_products_info['PRODUCTS'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $temp_products_info['PRODUCTS'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
    }
    
    
    if(is_callable('custom_controller_search_product_list')) {
        call_user_func_array('custom_controller_search_product_list', array(&$temp_products_info));
    }
     
     
    $arr_resp = array(
        "autocompleter"         =>  $autocompleter,
        "prods"                 =>  $temp_products_info,
        "menu"                  =>  $menu,
        "result_count"          =>  $resp_['result_count'],
        "filters"               =>  $resp_['filters'],
        "filters_encode"        =>  $resp_['filters_encode'],
        "active_filters"        =>  $resp_['active_filters'],
        "total_active_filters"  =>  $resp_['total_active_filters'],
        "search_terms"          =>  $arr_terms_searched,
        "expressions"           =>  call_api_func('get_expressions',36)
    );
    
    $CONFIG_ORDEM = $CONFIG_ORDEM_TEMP;        

    return serialize($arr_resp);
}

function escapeSolrValue($string){
    $match = array('\\', '+', '-', '&', '|', '!', '(', ')', '{', '}', '[', ']', '^', '~', '*', '?', ':', '"', ';', ' ');
    $replace = array('\\\\', '\\+', '\\-', '\\&', '\\|', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\~', '\\*', '\\?', '\\:', '\\"', '\\;', '\\ ');
    $string = str_replace($match, $replace, $string);

    return $string;
}

function getSinonimosPesquisa($term){
    global $LG;

    $arr              = array();
    $arr_query_regras = array();

    if($term == '')
        return $arr;


    # Procura pela input que o cliente introduziu
    $sql = @cms_query("SELECT DISTINCT(sinonimo) FROM ec_dicionario_sinonimos WHERE lingua = '".$LG."' AND origem = '".cms_escape($term)."' ");

    if(@cms_num_rows($sql) > 0){
        while($v = @cms_fetch_assoc($sql)){
            $arr[] = $v['sinonimo'];
        }

        return $arr;
    }


    # Caso não encontre, procura pelas palavras que fazem parte da input
    $search_words = explode(" ", $term);

    if(count($search_words) < 2)                                                      
        return $arr;


    foreach ($search_words as $k => $word) {
        if ( strlen($word) < 2 ){
            unset($search_words[$k]);
        }else{
            $arr_query_regras[] = "(origem = '".cms_escape($word)."')";
        }
    }


    if(count($arr_query_regras) < 1)
        return $arr;


    $sql = @cms_query("SELECT origem, sinonimo FROM ec_dicionario_sinonimos WHERE lingua = '".$LG."' AND ((( ".implode(") OR (",$arr_query_regras)." ))) GROUP BY origem");

    if(@cms_num_rows($sql) < 1){
        return $arr;
    }

     while($v = @cms_fetch_assoc($sql)){

        foreach($search_words as $kk => $word){
            
            if(strtolower($v['origem']) == strtolower($word)){
                $search_words[$kk] = $v['sinonimo'];
                
            }

        }

    }

    $arr = $search_words;

    return $arr;
}


?>
