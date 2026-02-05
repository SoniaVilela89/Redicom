<?
function _quickSearchB2B(){

    global $LG, $fx;
    global $MARKET;
    global $CONFIG_TEMPLATES_PARAMS;
    global $CONFIG_B2B_SOLR_QUICK_QTD, $PESQUISA_MIN_LENGTH;
    global $SOLR;
    global $solr_options;
    global $filters_convert;
    global $userID, $PESQUISA_SKUS_DIRECTOS;
    global $CONFIG_ORDEM;
    global $SETTINGS_LOJA;
    global $CACHE_KEY, $IP_WHITELIST, $SOLR_WITH_RANK;
    global $SOLR_ORDEM_FILTROS;
    global $CONFIG_IMAGE_SIZE;
    
    foreach($_POST as $k => $v){
        if(is_array($v)){
            $_POST[$k] = $v;
        }else{
            $_POST[$k] = strip_tags(trim(utf8_decode($v)));
        }
    }
    

    $search_page_id = 36;
    $search_row = call_api_func("get_line_table", "_trubricas", "id='".$search_page_id."'");


    #Limpar termo pesquisa
    if(isset($_SESSION['term_pesq']) && $_SESSION['term_pesq']!=$_POST['terms']){
        unset($_SESSION['filter_active'][$search_page_id]);
    }
    $_SESSION['term_pesq'] = $_POST['terms'];

    $offset = (int)$_POST['offset'];

    $term  = strtolower($_POST['terms']);        
    $term  = html_entity_decode($term, ENT_QUOTES);
    $term  = utf8_decode($term);
    
    # 2021-05-18 - quando se escrever directamtne p.e. calça na url é necessário um segundo decode  
    $term2 = utf8_decode($term);

    $validUTF8 =! (false === mb_detect_encoding($term2, 'UTF-8', true));
                         
    if( !$validUTF8 ){
        $term = $term2;
    }

    $sinonimos_pesquisa = getSinonimosPesquisa($term);
    
    $MAX_LENGTH = 3;
    
    if( (int)$PESQUISA_MIN_LENGTH > 0 ){
        $MAX_LENGTH = $PESQUISA_MIN_LENGTH;
    }

    $palavras = explode (" ", $term);
    foreach ($palavras as $kk => $_word) {
        if( strlen($_word) < $MAX_LENGTH ){
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

    # Sinónimos
    foreach($sinonimos_pesquisa as $kk => $_sinonimo){

        if ( strlen($_sinonimo) < $MAX_LENGTH ){
            unset( $sinonimos_pesquisa[$kk] );
        }

        $sinonimos_pesquisa[$kk] = urlencode(clearVariable($_sinonimo));

    }
    
    $index_last_term      = count($palavras) - 1;
    $last_term            = $palavras[$index_last_term];

    //Cria as sugestões de pesquisa
    $menu = array();
    $autocompleter        = array();
    $autocompleter_log    = array();
    $count_autocompleter  = 0;
    $resp_ = array();

    $_endPoint = "http://".$solr_options["hostname"].":".$solr_options["port"]."/".$solr_options["path"]."/suggest_".$LG."?q=".urlencode($term);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_endPoint);
    #curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    $response = curl_exec($ch);

    $response = json_decode($response);
    if( $response->responseHeader->status == "0" ){
        foreach($response->spellcheck->suggestions[1]->suggestion as $key => $value){

            $value = strip_tags($value);
            $res = explode($term, $value);
            if(count($res)==1) continue;

            $res = explode(' ', $res[1]);

            $value = $term.$res[0];

            $tmp            = array();
            $tmp['value']   = $count_autocompleter;
            $tmp['label']   = utf8_decode(strip_tags($value));
            array_push($autocompleter, $tmp);
            $count_autocompleter++;
        }
    }

    curl_close($ch);
   
    $sections_info = array();
    
    if(count($palavras) > 0){
        
        $sections_to_search = [['type' => 'registos', 'exp' => 34 ]];
        $MAX_AREAS_NUM = 3;
        for( $i=1; $i<=$MAX_AREAS_NUM; $i++ ){
            if( isset( $search_row['quick_search_area_'.$i] ) && trim( $search_row['quick_search_area_'.$i] ) != '' ){
                $sections_to_search[] = ['type' => trim( $search_row['quick_search_area_'.$i] ), 'exp' => (int)$search_row['quick_search_exp_'.$i] ];
            }
        }
        
        foreach( $sections_to_search as $section ){
            
            $extra_opt = ['filters' => $_POST['filters'], 'synonyms' => $sinonimos_pesquisa, 'offset' => $offset, 'market' => $MARKET ];
            $temp = [];
            
            if( $section['type'] == 'registos' ){
                if( !isset($CONFIG_B2B_SOLR_QUICK_QTD) || is_null($CONFIG_B2B_SOLR_QUICK_QTD) ) $CONFIG_B2B_SOLR_QUICK_QTD = 8;
                $extra_opt['qtd'] = $CONFIG_B2B_SOLR_QUICK_QTD * 2;
            }else{
                $extra_opt['qtd'] = 0;
            }
            
            $response = searchSolr($term, $palavras, $section['type'], $extra_opt);
            
            if( $response === NULL || $response->response->numFound == 0 ){
                continue;
            }
                
            if( $section['type'] == 'registos' ){
                
                $count_aux = 0;
                $section_type = 1;
                foreach($response->response->docs as $key=>$value){
                    
                    if( empty($value['sku']) || empty($value['pid']) ){
                        continue;
                    }
                    
                    if( count( $value['sku'] ) == 1 ){
                        $sku_price_clause = " AND `sku`='".reset($value['sku'])."'";
                    }else{
                        $sku_price_clause = " AND `sku` IN('".implode('\',\'', $value['sku'])."')";
                    }
                    
                    $product_eq = cms_query("SELECT `id` FROM `registos_precos` WHERE `idListaPreco`=".$MARKET['lista_preco']." AND `preco`>0 AND registos_precos.`data`<=CURRENT_DATE() ".$sku_price_clause." LIMIT 1");

                    if( cms_num_rows( $product_eq ) == 0 ){
                        continue;
                    }
                    
                    $configs_temp = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['thumb']);

                    $product = call_api_func("get_line_table", "registos", "id='".$value['pid']."'");
                                                    
                    $temp[] = ['id' => $value['pid'], 'nome' => $value['name_'.$LG], 'image' => $value['product_image'], 'image_size' => $configs_temp[0].'/'.$configs_temp[1], 'ref' => $product['sku_group'], 'url' => '/index.php?id='.$search_page_id.'&idpd='.$value['pid'].'&term='.urlencode($product['sku_group']) ];
                    $count_aux++;
                    
                    if( $count_aux == $CONFIG_B2B_SOLR_QUICK_QTD ){
                        break;
                    }
                    
                }
            
            }else{
                
                $section_type = 0;
                $facet_name = getProductCharacteristicToSolr($section['type'], $LG);

                $facet_values = (array)$response->facet_counts->facet_fields->$facet_name;
                $facet_values = array_filter($facet_values, function($facet_count){ return $facet_count > 0; });
                
                $temp = getFacetsListInfo($section['type'], $facet_values, $LG);
                    
            }
            
            $temp_section_info = ['entities' => $temp, 'title_exp' => $section['exp'], 'type' => $section_type];
            
            if( $section['type'] == 'registos' ){
                $temp_section_info['entities_limit'] = $CONFIG_B2B_SOLR_QUICK_QTD;
            }
            
            $sections_info[] = $temp_section_info;
            
        }
    
    }

    if(is_callable('custom_controller_search_product_list')) {
        call_user_func_array('custom_controller_search_product_list', array(&$temp_products_info));
    }

    $arr_resp = array(
        "autocompleter" => $autocompleter,
        "sections"      => $sections_info,
        "expressions"   => call_api_func('get_expressions',$search_page_id)
    );

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

function getSolrSearchQuery($extend_terms, $splitted_terms, $search_type='registos', $extra_options=[]){
    
    switch( strtolower($search_type) ){
        
        case 'registos':
            return getProductSolrQuery($extend_terms, $splitted_terms, $extra_options['market'], $extra_options['filters'], $extra_options['synonyms']);
        
        default:
            return getCharacteristicsSolrQuery($extend_terms, $splitted_terms, $search_type);
        
    }
    
    return '';
    
}


function getCharacteristicsSolrQuery($extend_terms, $splitted_terms, $search_type){
    
    global $LG;
    
    $solr_search_field = getProductCharacteristicToSolr($search_type, $LG);
        
    if( $solr_search_field == '' ){
        return '';
    }

    $terms_string = $extend_terms;
    $terms_string = str_replace(" ", "\ ", $terms_string);
    
    $arr_query = array();  
    if(count($splitted_terms)>1){
        
        $arr_query[] = $solr_search_field.":(*".implode('\ ', $splitted_terms)."*)";
        
    }else{
        
        $arr_query[] = $solr_search_field.":(*$terms_string*)";
        
    }
    
    return implode(' OR ', $arr_query);
    
}


function getProductSolrQuery($extend_terms, $palavras, $market_info, $filters=[], $synonyms_arr=[]){
    
    global $LG, $PESQUISA_SKUS_DIRECTOS;
    
    $catalogo = call_api_func('get_line_table', 'registos_catalogo', "id='".$market_info['catalogo']."' AND deleted='0'");
    $q_stock = "";
    if((int)$catalogo['only_with_stock']>0){
        $q_stock = " AND inventory:[1 TO *]";
    }

    $terms_string = $extend_terms;
    $terms_string = str_replace(" ", "\ ", $terms_string);

    $arr_query = array();  
    if(count($splitted_terms)>1){
        
        
        (count($synonyms_arr)>0) ? $t_sinonimo1 = "OR (".implode('\ ',$synonyms_arr).")" : $t_sinonimo1 = "";
        (count($synonyms_arr)>0) ? $t_sinonimo2 = "OR (".implode('\ ',$synonyms_arr)."*)" : $t_sinonimo2 = "";
        (count($synonyms_arr)>0) ? $t_sinonimo3 = "OR (*\ ".implode('\ ',$synonyms_arr)."*)" : $t_sinonimo3 = "";
        
        $arr_query[] = "name_".$LG.":((".$terms_string.") $t_sinonimo1)^8200";
        $arr_query[] = "name_".$LG.":((".$terms_string."*) $t_sinonimo2)^8100";
        $arr_query[] = "name_".$LG.":((*\ ".$terms_string."*) $t_sinonimo3)^8000";   
        
        
        (count($synonyms_arr)>0) ? $sinonimo1 = "OR (".implode('\ ',$synonyms_arr).")" : $sinonimo1 = "";
        (count($synonyms_arr)>0) ? $sinonimo2 = "OR (".implode('\ ',$synonyms_arr)."*)" : $sinonimo2 = "";
        (count($synonyms_arr)>0) ? $sinonimo3 = "OR (*".implode('\ ',$synonyms_arr)."*)" : $sinonimo3 = "";
        (count($synonyms_arr)>0) ? $sinonimo4 = "OR ((".implode('*) AND (*',$synonyms_arr)."*))" : $sinonimo4 = "";
        (count($synonyms_arr)>0) ? $sinonimo5 = "OR ((*\ ".implode('*) AND (*\ ',$synonyms_arr)."*))" : $sinonimo5 = "";
        (count($synonyms_arr)>0) ? $sinonimo6 = "OR ((*\ ".implode('*) AND (*\ ',$synonyms_arr)."*))" : $sinonimo6 = "";
        
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
            foreach($palavras as $k => $v){
                $arr_query[] = "sku:($v*)^=100";
                $arr_query[] = "style:($v*)^=100";
                $arr_query[] = "refcolor:($v*)^=100";
            }                         
            
        }else{
            foreach($palavras as $k => $v){
                $arr_query[] = "sku:(*$v*)^=100";
                $arr_query[] = "style:(*$v*)^=100";
                $arr_query[] = "refcolor:(*$v*)^=100";
            }
        } 

    }else{
        

        (count($synonyms_arr)>0) ? $sinonimo1 = "OR (".implode(" OR ", $synonyms_arr).")" : $sinonimo1 = "";
        (count($synonyms_arr)>0) ? $sinonimo2 = "OR (".implode("* OR ", $synonyms_arr)."*)" : $sinonimo2 = "";     
        (count($synonyms_arr)>0) ? $sinonimo3 = "OR (*\ ".implode(" OR *\ ", $synonyms_arr).")" : $sinonimo3 = "";
        (count($synonyms_arr)>0) ? $sinonimo4 = "OR (*\ ".implode("\ * OR *\ ", $synonyms_arr)."\ *)" : $sinonimo4 = "";
        (count($synonyms_arr)>0) ? $sinonimo4 = "OR (*\ ".implode("* OR *\ ", $synonyms_arr)."*)" : $sinonimo5 = "";
        
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
    if( !empty( $filters ) ){
        foreach($filters as $k => $v){
            $arr_filters = explode("|||", $v);
            $filters_query .= "(".$k."_".$LG.":(".implode(') OR '.$k."_".$LG.':(',$arr_filters).")) AND ";
        }
    }
    
    return "( ( (".implode(' OR ', $arr_query).") AND new:0)^1 OR ( (".implode(' OR ', $arr_query).") AND new:1)^10 ) AND ".$filters_query." market:".$market_info['id']." AND active:1 ".$q_stock;
    
}


function searchSolr($extend_terms, $splitted_terms, $search_type='registos', $extra_options=[]){
    
    global $SOLR_WITH_RANK, $LG;
    global $solr_options, $client;
    
    if( empty($client) ){
        $client = new SolrClient($solr_options);
    }

    $solr_query = getSolrSearchQuery($extend_terms, $splitted_terms, $search_type, $extra_options);
    
    if( $solr_query == '' ){
        return NULL;
    }
    
    $dismaxQuery = new SolrDisMaxQuery($solr_query);  
    
    $dismaxQuery->setParam("defType", "edismax");
                   
    $dismaxQuery->setParam('bf', "if(termfreq(new,'1'),10000,0)"); 
                                      
    $dismaxQuery->setParam("sort", "new desc, score desc, name_$LG asc"); 
    

    $dismaxQuery->set("spellcheck", "on");
    $dismaxQuery->set("q.op", "AND");
    
    if( (int)$SOLR_WITH_RANK > 0 ){
        $dismaxQuery->addSortField("order", 1); # 0 -> ASC || 1 -> DESC
    }

    $dismaxQuery->setStart($extra_options['offset']);
    $dismaxQuery->setRows($extra_options['qtd']);

    if( $search_type == 'registos' ){
        $dismaxQuery->setFacet(false);
    }else{
        $solr_search_field = getProductCharacteristicToSolr($search_type, $LG);
        
        if( $solr_search_field == '' ){
            return NULL;
        }
        
        $dismaxQuery->setFacet(true);
        $dismaxQuery->addFacetField($solr_search_field);
    }
    
    $query_response = $client->query($dismaxQuery);
    $response = $query_response->getResponse();
    
    return $response;
    
}


function getProductCharacteristicToSolr($search_type, $lang){
    
    switch( $search_type ){
        
        case 'familia':      return 'family_'.$lang;
        case 'subfamilia':   return 'subfamily_'.$lang;
        case 'categoria':    return 'category_'.$lang;
        case 'subcategoria': return 'subcategory_'.$lang;
        case 'ano':          return 'year_'.$lang;
        case 'semestre':     return 'season_'.$lang;
        case 'marca':        return 'brand_'.$lang;
        case 'gama':         return 'gama_'.$lang;
        default:             return '';
        
    }
    
}


function getProductCharacteristicToBD($search_type, $lang){
    
    switch( $search_type ){
        
        case 'familia':      return 'registos_familias';
        case 'subfamilia':   return 'registos_subfamilias';
        case 'categoria':    return 'registos_categorias';
        case 'subcategoria': return 'registos_subcategorias';
        case 'ano':          return 'registos_anos';
        case 'semestre':     return 'registos_semestres';
        case 'marca':        return 'registos_marcas';
        case 'gama':         return 'registos_gamas';
        default:             return '';
        
    }
    
}


function getProductCharacteristicToSearchPageParam($search_type){
    
    switch( $search_type ){
        
        case 'familia':      return 'fm';
        case 'subfamilia':   return 'sfm';
        case 'categoria':    return 'cg';
        case 'subcategoria': return 'scg';
        case 'ano':          return 'yr';
        case 'semestre':     return 'ss';
        case 'marca':        return 'mc';
        case 'gama':         return 'gm';
        default:             return '';
        
    }
    
}

function getFacetsListInfo($section, $facet_values, $lang){
    
    global $search_page_id;
    
    $facet_name_values = array_keys( $facet_values );
    
    $bd_table_name = getProductCharacteristicToBD($section, $lang);
    $ulr_param_name = getProductCharacteristicToSearchPageParam($section);
    $return_details = [];
    
    if( empty($search_page_id) ){
        $search_page_id = 36;
    }
    
    if( count( $facet_name_values ) == 1 ){
        $where_clause = "`nome".$lang."`='".reset($facet_name_values)."'";
    }else{
        $where_clause = "`nome".$lang."` IN('".implode('\',\'', $facet_name_values)."')";
    }
    
    $res = cms_query( "SELECT `id`, `nome".$lang."` as `nome` FROM `".$bd_table_name."` WHERE ".$where_clause );
    while( $row = cms_fetch_assoc($res) ){
        
        $temp = $row;
        $temp['count'] = $facet_values[ clearVariable($row['nome']) ];
        
        #$temp['url'] = '/index.php?id='.$search_page_id.'&search_nm='.$ulr_param_name.'&search_vl='.$row['id'];
        $temp['url'] = '/index.php?id='.$search_page_id.'&term='.$row['nome'];
        
        if( file_exists($_SERVER['DOCUMENT_ROOT']."/images/icon_".$bd_table_name."_".$row['id'].".jpg") ){
            $image_obj = call_api_func('OBJ_image', $row['nome'], 1, "/images/icon_".$bd_table_name."_".$row['id'].".jpg", "productOBJ");
            $temp['image'] = $image_obj['resource_url']['thumb'];
            $temp['image_size'] = $image_obj['resource_url_sizes']['thumb'];
        }else{
            global $CONFIG_IMAGE_SIZE;
            
            $configs_temp = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['thumb']);
            $temp['image_size'] = $configs_temp[0].'/'.$configs_temp[1];
        }
        
        $return_details[] = $temp;
        
    }
    
    return $return_details;
    
}


?>
