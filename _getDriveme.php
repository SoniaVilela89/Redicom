<?

function _getDriveme($page_id=0){
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    global $MARKET;
    global $MOEDA;
    global $COUNTRY;
    global $LG;
    global $userID;
    global $CONFIG_LISTAGEM_QTD;
    global $CONFIG_ORDEM;
    global $CONFIG_ORDEM_PRECO;
    global $CONFIG_ORDEM_NOME;
    global $LISTAGEM_DESAGRUPAR_CORES;
    global $LISTAGEM_RAPIDA;
    global $LISTAGEM_PRODS_ADS, $LONG_CACHE, $CACHE_QUERY_PRODUTOS, $CONFIG_OPTIONS, $fx, $prods_final_variants, $CACHE_LISTA_PRODUTOS;

    $arr = array();
    
    $temp_filters = array_merge_recursive((array)$_SESSION['filter_active'][$page_id], (array)$_SESSION['filter_active']['DRIVEME']);
    
    $scope = array();
    $scope['ID']          = $page_id;
    $scope['PAIS']        = $_SESSION['_COUNTRY']['id'];
    $scope['LG']          = $_SESSION['LG'];
    $scope['FILTERS']     = serialize((array)$_SESSION['filter_active'][$page_id]);
    $scope['PRICE_LIST']  = $_SESSION['_MARKET']['lista_preco'];
    
    $_cacheid             = $CACHE_KEY."DM".md5(serialize($scope));
    
    if($CACHE_LISTA_PRODUTOS>0) {
        $dados_driveme = $fx->_GetCache($_cacheid, $CACHE_LISTA_PRODUTOS);
    }

    if (!$dados_driveme || $_GET["nocache"]==1){
     
        $row = call_api_func('get_pagina', $page_id, "_trubricas");
        $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
        $caminho = call_api_func('get_breadcrumb', $page_id);
        $arr['selected_page']['breadcrumb'] = $caminho;
        
        $arr['filters'] = call_api_func('getFiltersDriveme', $page_id);

        $fx->_SetCache($_cacheid, serialize($arr), 5);
    }else{
        
        $arr = unserialize($dados_driveme);    
    }
    
    $arr['expressions'] = call_api_func('get_expressions', $page_id);
    $arr['shop'] = call_api_func('OBJ_shop_mini');

    return serialize($arr);
}


function getFiltersDriveme($page_id){
    
    global $MARKET;
    global $MOEDA;
    global $COUNTRY;
    global $LG;
    global $userID;
    global $CONFIG_LISTAGEM_QTD;
    global $CONFIG_ORDEM;
    global $CONFIG_ORDEM_PRECO;
    global $CONFIG_ORDEM_NOME;
    global $LISTAGEM_DESAGRUPAR_CORES;
    global $LISTAGEM_RAPIDA;
    global $LISTAGEM_PRODS_ADS, $LONG_CACHE, $CACHE_QUERY_PRODUTOS, $CONFIG_OPTIONS, $fx, $prods_final_variants;
    
    $MARKET       = $_SESSION['_MARKET'];
    $priceList    = $_SESSION['_MARKET']['lista_preco'];    
    
    
    $arr_query_prod = prepare_query_products($page_id, $pagina_mae, $_query_regras_param);
 
    if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
        $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
    }

    $page                       = $arr_query_prod["page"];
    $JOIN                       = $arr_query_prod["JOIN"];
    $_query_regras_final        = $arr_query_prod["_query_regras_final"];
    $order_by                   = $arr_query_prod["order_by"];
    $LISTAGEM_DESAGRUPAR_CORES  = $arr_query_prod["LISTAGEM_DESAGRUPAR_CORES"];
          
    $temp = return_products_list($page_id, $JOIN, $_query_regras_final, $priceList, $order_by);
      
    $prods_final_original = $prods_final = $temp['prods'];
    $array_pids           = $temp['pids'];
    $array_pids_ordem     = $temp['ordem'];
    $max_conv             = $temp["max_conv"];
    
    if(isset($_SESSION['filter_active'][$page_id])){
        apply_filters_to_products($prods_final, $page_id);
    }

    $FILTROS_PRODS = get_filters($page_id, $prods_final_original, $prods_final);

    return $FILTROS_PRODS;
}
