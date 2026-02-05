<?
function _searchMultiCatalog($term=null, $offset=null, $pages=null, $page_id=null){

    global $LG, $eComm, $SETTINGSB2B;
    global $userID;
    global $MARKET;
    global $CONFIG_TEMPLATES_PARAMS, $CONFIG_OPTIONS;
    global $CONFIG_LISTAGEM_QTD, $PESQUISA_MIN_LENGTH;
    global $SOLR;
    global $solr_options;
    global $filters_convert; 
    global $slocation, $fx, $PESQUISA_SKUS_DIRECTOS;
    global $CONFIG_ORDEM;
    global $CACHE_KEY;
    
    global $CONFIG_B2B_SEARCH_QTD;
    
    if((int)$CONFIG_B2B_SEARCH_QTD>0) $CONFIG_LISTAGEM_QTD = $CONFIG_B2B_SEARCH_QTD; 

    if ($term!=''){
        $offset = (int)$offset;
        $pages  = (int)$pages;
        $page_id  = (int)$page_id;
    }else{
        $term   = params('term');
        $offset = (int)params('offset');
        $pages  = (int)params('pages');
        $page_id  = (int)params('page_id');
    }
    
    
    if($page_id==0) $page_id = 36;
    
    $term = trim(html_entity_decode($term, ENT_QUOTES));
    $term = utf8_decode($term);

    # 2021-05-18 - quando se escrever directamtne p.e. calça na url é necessário um segundo decode
    $term2 = utf8_decode($term);

    $validUTF8 =! (false === mb_detect_encoding($term2, 'UTF-8', true));
    if(!$validUTF8) {
        $term = $term2;
    }
    

    # Ficheiro custom - usado na anasousa
    $file_custom = $_SERVER['DOCUMENT_ROOT'].'/custom/controllers/search.php';
    if(file_exists($file_custom)){
        include_once($file_custom);      
        if( is_callable('setTerm') ) {
            $term = call_user_func('setTerm', $term);                       
        }     
    }
    
    
    $sinonimos_pesquisa = getSinonimosPesquisa($term);

    if ((int)$pages<1) $pages=1;


    #Limpar termo pesquisa           
    if(isset($_SESSION['term_pesq']) && $_SESSION['term_pesq']!=$term){
        unset($_SESSION['filter_active'][36]);    
    }
    $_SESSION['term_pesq'] = $term;


    $resp               = array();
    $resp['terms']      = $term;
    $resp['search_url'] = "index.php?id=36&term=$term";
    
    
    $MAX_LENGTH = 3;
    
    if((int)$PESQUISA_MIN_LENGTH>0) $MAX_LENGTH = $PESQUISA_MIN_LENGTH;
    
    
    $palavras = explode (" ", $term);
    foreach ($palavras as $kk=>$_word) {
        if ( strlen($_word) < $MAX_LENGTH ){
            unset( $palavras[$kk] );
        }else{
            # 02/03/2021 - comentado url encode para permitir pesquisas com /
            #$palavras[$kk] = urlencode(clearVariable($_word)); 
            $palavras[$kk] = clearVariable($_word);
        }
    }

    # Sinónimos  
    foreach($sinonimos_pesquisa as $kk => $_sinonimo){
        
        if ( strlen($_sinonimo) < $MAX_LENGTH ){
            unset( $sinonimos_pesquisa[$kk] );
        }   
            
        if((int)$SOLR>0){
            $sinonimos_pesquisa[$kk] = urlencode(clearVariable($_sinonimo));
        }
        
    }
    
    
    

    if(trim($term)=='' ){
        header('HTTP/1.0 400 Bad Request');
        echo "<script>location='$slocation'</script>";
        exit;            
    }
    
    $_query_regras_arr = array();
    $_query_regras_arr_sk = array();     
    $_query_regras_arr_sin = array();
    
    $_query_order_arr = array();
    
    if(count($palavras)>1){
            
        $_query_regras = "AND (((registos.desc$LG LIKE '%".cms_escape(implode('%',$palavras))."%' OR registos.descritores$LG  LIKE '%".cms_escape(implode('%',$palavras))."%' OR registos.nome$LG  LIKE '%".cms_escape(implode('%',$palavras))."%')  ";
        
        $_query_order_arr[15]  ="when registos.nome{LG} like '".cms_escape($term)."' then '15'";
        $_query_order_arr[16]  ="when registos.nome{LG} like '".cms_escape($term)."%' then '16'";
        $_query_order_arr[17]  ="when registos.nome{LG} like '%".cms_escape($term)."%' then '17'";
            
        $_query_order_arr[20]  ="when registos.nome{LG} like '".cms_escape(implode(' ',$palavras))."%' then '20'";    
        $_query_order_arr[40]  ="when registos.desc{LG} like '".cms_escape(implode(' ',$palavras))."%' then '40'";           

        $_query_order_arr[21]  ="when registos.nome{LG} like '%".cms_escape(implode('%',$palavras))."%' then '21'";    
        $_query_order_arr[41]  ="when registos.desc{LG} like '%".cms_escape(implode('%',$palavras))."%' then '41'";  
                 
        $_query_order_arr[60]  ="when registos.descritores{LG} like '%".cms_escape(implode('%',$palavras))."%' then '60'";
        
        foreach ($palavras as $kk => $vv){
            $_query_regras_arr[] = " registos.descritores$LG LIKE '".cms_escape($vv)."%'  OR registos.descritores$LG LIKE '% ".cms_escape($vv)."%'  OR registos.desc$LG LIKE '".cms_escape($vv)."%' OR registos.desc$LG LIKE '% ".cms_escape($vv)."%' OR registos.nome$LG  LIKE '".cms_escape($vv)."%' OR registos.nome$LG  LIKE '% ".cms_escape($vv)."%'  ";
            
            $_query_order_arr[21+$kk]  ="when registos.nome{LG} like '".cms_escape($vv)."%' then '22'";
            $_query_order_arr[25+$kk]  ="when registos.nome{LG} like '% ".cms_escape($vv)."%' then '24'";
            
            $_query_order_arr[41+$kk]  ="when registos.desc{LG} like '".cms_escape($vv)."%' then '42'";
            $_query_order_arr[45+$kk]  ="when registos.desc{LG} like '% ".cms_escape($vv)."%' then '44'";
            
            $_query_order_arr[61+$kk]  ="when registos.descritores{LG} like '".cms_escape($vv)."%' then '62'";
            $_query_order_arr[65+$kk]  ="when registos.descritores{LG} like '% ".cms_escape($vv)."%' then '64'";
            
            
            # 2020-03-10 Usado pelo jom para CAMA não encontrar escamador no sku_family
            # Aprovado pelo Serafim
            if((int)$PESQUISA_SKUS_DIRECTOS>0){
                $_query_regras_arr_sk[] = " registos.sku LIKE '".cms_escape($vv)."%' OR registos.sku_family LIKE '".cms_escape($vv)."%' OR registos.sku_group LIKE '".cms_escape($vv)."%' OR registos.ean LIKE '".cms_escape($vv)."%' OR CONCAT(',', registos.ean, ',') LIKE '%,".cms_escape($vv)."%,%' ";            
                
                $_query_order_arr[71+$kk]  ="when registos.sku like '".cms_escape($vv)."%' then '70'";
                $_query_order_arr[72+$kk]  ="when registos.sku_family like '".cms_escape($vv)."%' then '70'";
                $_query_order_arr[73+$kk]  ="when registos.sku_group like '".cms_escape($vv)."%' then '70'";
                
            }else{
                $_query_regras_arr_sk[] = " registos.sku LIKE '%".cms_escape($vv)."%' OR registos.sku_family LIKE '%".cms_escape($vv)."%' OR registos.sku_group LIKE '%".cms_escape($vv)."%' OR registos.ean LIKE '%".cms_escape($vv)."%' OR CONCAT(',', registos.ean, ',') LIKE '%,%".cms_escape($vv)."%,%' ";
                
                $_query_order_arr[71+$kk]  ="when registos.sku like '%".cms_escape($vv)."%' then '70'";
                $_query_order_arr[72+$kk]  ="when registos.sku_family like '%".cms_escape($vv)."%' then '70'";
                $_query_order_arr[73+$kk]  ="when registos.sku_group like '%".cms_escape($vv)."%' then '70'";

                $_query_order_arr[74+$kk]  ="when registos.sku like '".cms_escape($vv)."%' then '73'";
                $_query_order_arr[75+$kk]  ="when registos.sku_family like '".cms_escape($vv)."%' then '73'";
                $_query_order_arr[76+$kk]  ="when registos.sku_group like '".cms_escape($vv)."%' then '73'";

                $_query_order_arr[77+$kk]  ="when registos.sku like '%".cms_escape($vv)."%' then '75'";
                $_query_order_arr[78+$kk]  ="when registos.sku_family like '%".cms_escape($vv)."%' then '75'";
                $_query_order_arr[79+$kk]  ="when registos.sku_group like '%".cms_escape($vv)."%' then '75'";

            }

        }             
    }else{
        $_query_regras = "AND (((registos.desc$LG LIKE '".cms_escape($term)."' OR registos.descritores$LG  LIKE '".cms_escape($term)."' OR registos.nome$LG  LIKE '".cms_escape($term)."')  ";  
        
        $_query_order_arr[10]  ="when registos.nome{LG} like '".cms_escape($term)."' then '10' ";
        $_query_order_arr[30]  ="when registos.desc{LG} like '".cms_escape($term)."' then '30'  ";            
        $_query_order_arr[50]  ="when registos.descritores{LG} like '%".cms_escape($term)."%' then '50' ";

        $_query_regras_arr[] = " registos.descritores$LG LIKE '".cms_escape($term)."%'  OR registos.descritores$LG LIKE '% ".cms_escape($term)."%'  OR registos.desc$LG LIKE '".cms_escape($term)."%' OR registos.desc$LG LIKE '% ".cms_escape($term)."%' OR registos.nome$LG  LIKE '".cms_escape($term)."%' OR registos.nome$LG  LIKE '% ".cms_escape($term)."%'  ";
        
        $_query_order_arr[21+$kk]  ="when registos.nome{LG} like '".cms_escape($term)." %' then '21'";
        $_query_order_arr[23+$kk]  ="when registos.nome{LG} like '".cms_escape($term)."%' then '23'";
        $_query_order_arr[25+$kk]  ="when registos.nome{LG} like '% ".cms_escape($term)."%' then '24'";
        
        $_query_order_arr[41+$kk]  ="when registos.desc{LG} like '".cms_escape($term)." %' then '41'";
        $_query_order_arr[43+$kk]  ="when registos.desc{LG} like '".cms_escape($term)."%' then '43'";
        $_query_order_arr[45+$kk]  ="when registos.desc{LG} like '% ".cms_escape($term)."%' then '44'";
        
        $_query_order_arr[61+$kk]  ="when registos.descritores{LG} like '".cms_escape($term)." %' then '61'";
        $_query_order_arr[63+$kk]  ="when registos.descritores{LG} like '".cms_escape($term)."%' then '63'";
        $_query_order_arr[65+$kk]  ="when registos.descritores{LG} like '% ".cms_escape($term)."%' then '64'";
        
        
        # 2020-03-10 Usado pelo jom para CAMA não encontrar escamador no sku_family
        # Aprovado pelo Serafim
        if((int)$PESQUISA_SKUS_DIRECTOS>0){
            $_query_regras_arr_sk[] = " registos.sku LIKE '".cms_escape($term)."%' OR registos.sku_family LIKE '".cms_escape($term)."%' OR registos.sku_group LIKE '".cms_escape($term)."%' OR registos.ean LIKE '".cms_escape($term)."%' OR CONCAT(',', registos.ean, ',') LIKE '%,".cms_escape($term)."%,%' ";            
            
            $_query_order_arr[71+$kk]  ="when registos.sku like '".cms_escape($term)."%' then '70'";
            $_query_order_arr[72+$kk]  ="when registos.sku_family like '".cms_escape($term)."%' then '70'";
            $_query_order_arr[73+$kk]  ="when registos.sku_group like '".cms_escape($term)."%' then '70'";
            
        }else{
            $_query_regras_arr_sk[] = " registos.sku LIKE '%".cms_escape($term)."%' OR registos.sku_family LIKE '%".cms_escape($term)."%' OR registos.sku_group LIKE '%".cms_escape($term)."%' OR registos.ean LIKE '%".cms_escape($term)."%' OR CONCAT(',', registos.ean, ',') LIKE '%,%".cms_escape($term)."%,%' ";
            
            $_query_order_arr[71+$kk]  ="when registos.sku like '".cms_escape($term)."' then '70'";
            $_query_order_arr[72+$kk]  ="when registos.sku_family like '".cms_escape($term)."' then '70'";
            $_query_order_arr[73+$kk]  ="when registos.sku_group like '".cms_escape($term)."' then '70'";

            $_query_order_arr[74+$kk]  ="when registos.sku like '".cms_escape($term)."%' then '73'";
            $_query_order_arr[75+$kk]  ="when registos.sku_family like '".cms_escape($term)."%' then '73'";
            $_query_order_arr[76+$kk]  ="when registos.sku_group like '".cms_escape($term)."%' then '73'";

            $_query_order_arr[77+$kk]  ="when registos.sku like '%".cms_escape($term)."%' then '75'";
            $_query_order_arr[78+$kk]  ="when registos.sku_family like '%".cms_escape($term)."%' then '75'";
            $_query_order_arr[79+$kk]  ="when registos.sku_group like '%".cms_escape($term)."%' then '75'";

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
    $CONFIG_ORDEM_TEMP = $CONFIG_ORDEM;
    $CONFIG_ORDEM = "CASE ".implode(" ",$_query_order_arr)." ELSE '9999' END ASC,".$CONFIG_ORDEM;

    
    $temp_products_info = array();
    $temp_catalogs_products_info = array();
    if( count($palavras)>0 ) {
        if( count($_query_regras_arr)>0 ) $_query_regras .= " OR (( ".implode(") AND (",$_query_regras_arr)." ) ) ";  
        if( count($_query_regras_arr_sk)>0 ) $_query_regras .= " OR (( ".implode(") OR (",$_query_regras_arr_sk)." ) ) )";              

        if(count($sinonimos_pesquisa) > 0){
            $_query_regras .= $_query_regras_sin;
            $_query_regras .= " OR (( ".implode(") AND (",$_query_regras_arr_sin)." ) ) )";   
        }   
        
        $_query_regras .= ")";     
        
        $page = call_api_func('get_pagina', $page_id, "_trubricas");
        
        $catalogs = explode(",", $page['catalogos']);
        $add_catalog_market = 0;
        
        foreach ($catalogs as $k => $cat) {

            $catalog = get_line_table_cache_api("registos_catalogo", "id='".$cat."' AND deleted=0");
            # If the catalog doesn't exist is ignored and go to the next
            if( (int)$catalog['id'] == 0 ){
                unset($catalogs[$k]);
                continue;   
            }
            # If the catalog doesn't exist is ignored and go to the next

            # If the cart rule doesn't exist the catalog is not displayed in checkout and it's ignored
            $cart_rule = preparar_regras_carrinho($cat);
            if( (int)$cart_rule['id'] == 0 ){

                unset($catalogs[$k]);

                # If doesn't exist active cart rules the market catalog is forced in search
                $has_active_cart_rule = get_line_table_cache_api("registos_catalogo_regras", "id_catalogo='$cat' AND data_start<=CURDATE() AND data_fim>=CURDATE() AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%' )");
                if( $has_active_cart_rule['id'] == 0 ){
                    $add_catalog_market = 1;
                }
                # If doesn't exist active cart rules the market catalog is forced in search

            }
            # If the cart rule doesn't exist the catalog is not displayed in checkout and it's ignored    

        }
        
        # Add the market catalog to the catalog list
        if($add_catalog_market == 1 && array_search($MARKET["catalogo"], $catalogs)===false ) $catalogs = array_merge([$MARKET["catalogo"]], $catalogs);       
        # Add the market catalog to the catalog list
        
        foreach ($catalogs as $k => $cat) {
            
            # This is necessary in order to not mix rules in the catalog
            $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"] = "";
            $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"] = "";
            $GLOBALS["STOCK_REGRAS_CATALOGO"] = "";
            # This is necessary in order to not mix rules in the catalog

            $temp_products_info = call_api_func('get_products', $page_id, $offset, $_query_regras, $pages, 0, 0, 0, 0, $cat);
            
            $catalog = get_line_table_cache_api("registos_catalogo", "id='".$cat."' AND deleted=0");
            
            $temp_catalogs_products_info[$cat] = array_merge(array( "id" => $cat, "name" => $catalog['nome'.$LG]), $temp_products_info);
            
            $resp['result_count'][$cat] = $temp_products_info['COUNT'];
            $resp['filters'][$cat]      = $temp_products_info['FILTROS'];

        }
        
    }

    $resp['filters_encode']     = encodeFilters(36);
           
    global $B2B,$TecDoc;
    if((int)$B2B>0){
    
        $products_to_search = [];
        
        foreach ($temp_catalogs_products_info as $catalogo_id=>$value) {
          
            preparar_regras_carrinho($catalogo_id);
   
            $ids_depositos = $_SESSION['_MARKET']['deposito'];                        
            if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!=''){
                $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
            }

            $priceList = $_SESSION['_MARKET']['lista_preco'];  
            if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
                $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
            }

            # Regras para obter as cores     
            $JOIN = '';
            $JOIN_ARRAY = array();
            # 10-09-2019 - no detalhe exibimos todas as cores com base na regra do mercado, as regras da página da listagem são excluídas (PhoneHouse - pag exclusivos)
            #$_query_regras = build_regras($page_id, $JOIN_ARRAY, 0);
            $_query_regras = '';
            
            $_query_regras = build_regras(0, $JOIN_ARRAY, $catalogo_id);
                            
            
            if($mercado==1) $_query_regras .= build_regras_mercado($JOIN_ARRAY);
            
            $so_cores_com_stock = 0;                            
            if(isset($JOIN_ARRAY['STOCK'])){                
                unset($JOIN_ARRAY['STOCK']);
                $so_cores_com_stock = 1;
            }
                
            if(count($JOIN_ARRAY)>0){
                $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
            }    
                        
            foreach($value['PRODUCTS'] as $k => $v){
            
                $products_to_search[ $v['selected_variant']['sku'] ] = $v['selected_variant']['sku'];
                            
                $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);

                if ((int)$_SESSION['_MARKET']['depositos_condicionados_ativo'] == 1 && trim($_SESSION['_MARKET']['depositos_condicionados']) != '' && has_only_conditioned_stock($v, $ids_depositos, $_SESSION['_MARKET']['depositos_condicionados'])) {

                    if (is_null($market_extra_info)) {
                        $market_extra_info = get_market_extra_info($_SESSION['_MARKET']["id"]);
                    }

                    $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['tags'][] = [
                        'title'      => $market_extra_info['dc_etiqueta_nome'],
                        'color'      => '#' . $market_extra_info['dc_etiqueta_cor_fundo'],
                        'color_text' => '#' . $market_extra_info['dc_etiqueta_cor_texto']
                    ];
                }
                
                $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['matriz'] = $matriz;
        
                $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['warehouse_availability'] = []; #by default no warehouse will be shown
                if( count($matriz['colunas']) == 1 && count($matriz['linhas']) == 1 && (int)$v['uncataloged_stock'] == 0 ){            
                    $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['warehouse_availability'] = check_warehouses_stock($ids_depositos, $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['sku'], $_SESSION['_MARKET']);
                }
                
                #Layout vertical  
                if(count($v['variants'])==1 && count($v['available_colors'])==1 ){
                    
                    $qtd = $eComm->getProductQtds($userID, $v['selected_variant']['id'], 0, 0, $catalogo_id);
                    
                    $temp_catalogs_products_info[$catalogo_id]['PRODUCTS'][$k]['variants'][0]['quantity_in_cart'] = (int)$qtd;
                }
                        
            }
          
        }
        

        # B2B - Produtos equivalentes
        if( (int)$CONFIG_OPTIONS['exibir_referencias_equivalentes'] == 1 && !empty( $products_to_search ) ){
            $resp['results']['other_products'] = get_equivalent_products($products_to_search, $page["catalogo"]);
            

            
                                                                                             
            if(trim($SETTINGSB2B['tecdoc_provider'])!='' && (int)$_SESSION['EC_USER']['custom_tecdoc_acesso']>0){
                                                                                             
                $prov       = trim($SETTINGSB2B['tecdoc_provider']);
                $prod       = $SETTINGSB2B['tecdoc_produtivo']; 

                require_once $_SERVER["DOCUMENT_ROOT"].'/dev/TecDoc/TecDoc.php';          
                require_once $_SERVER["DOCUMENT_ROOT"].'/dev/TecDoc/tecdoc_funcs.php';   
                                             
                $TecDoc = new TecDoc_Layout();
                $TecDoc->LG = $LG;
                $TecDoc->production = $prod;
                $TecDoc->TECDOC_MANDATOR = $prov;

        
                $_GET['searchTecdoc'] = $_GET['term'];
                $_aux_get = $_GET;
                     
                # Pesquisa no TecDoc
                $return_data = getTecDocArray($_aux_get); 
                                                                       
                limpa_lixo_inicial($return_data);     
        
                find_aftermarket($return_data, $_SESSION['EC_USER']['lista_preco']); 
                
                              
                limpa_lixo_final($return_data, 0);        
                # Pesquisa no TecDoc
                 
                $result = array(); 
                           
                if(count($return_data)>0){   
                    foreach($return_data as $k => $v){
                        foreach($v['ERP'] as $k1 => $v1){      
                             $prod_temp = call_api_func('get_product',0, $v1['NUMINTERNO'], 36);
                             if( $prod_temp["id"] <= 0 || in_array($prod_temp['sku'], $products_to_search)){
                                continue;
                             }     
                             $result[]  = $prod_temp;
                        }
                    }
                }
                
                if (!empty($result)) { 
                    $resp['results']['other_products'] = array_merge($resp['results']['other_products'], $result);
                }  
                
            }
            
            foreach($resp['results']['other_products'] as $k => $v){
                if( count($matriz['colunas']) == 1 && count($matriz['linhas']) == 1 && (int)$v['uncataloged_stock'] == 0 ){            
                    $resp['results']['other_products'][$k]['warehouse_availability'] = check_warehouses_stock($ids_depositos, $resp['results']['other_products'][$k]['sku'], $_SESSION['_MARKET']);
                }
            }
            
        }

    }
    

    $resp['results']['section']       = "Produtos";
    $resp['results']['multi_catalog'] = 1;
    $resp['results']['catalogs']      = $temp_catalogs_products_info;

    $tot_filtros = 0;
    foreach($_SESSION['filter_active'][36] as $k => $v){
        $tot_filtros += count($v);
    }
    
    $resp['order_by']             = call_api_func('get_order_by', "36");
    $resp['active_filters']       = ( isset($_SESSION['filter_active'][36]) ) ? 1 : 0;
    $resp['total_active_filters'] = $tot_filtros;
    $resp['active_order_by']      = ( isset($_SESSION['order_active'][36]) ) ? 1 : 0;
    $resp['grid_view']            =  $_SESSION['GridView'];
    $resp['grid_view_mobile'] =  $_SESSION['GridViewMobile'];
    

    if( array_sum($resp['result_count'])>0 && $offset==0){
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
        foreach($resp['results']['items'] as $k => $v){
            $resp['results']['items'][$k]["wishlist"] = call_api_func('verify_product_wishlist', $v['sku_family'], $userID);
        }
    } 
    
    $resp['expressions']      = call_api_func('get_expressions',36); 
    
    
    if(is_callable('custom_controller_searchMultiCatalog')) {
        call_user_func_array('custom_controller_searchMultiCatalog', array(&$resp));
    }
    $CONFIG_ORDEM = $CONFIG_ORDEM_TEMP;
    return serialize($resp);
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
