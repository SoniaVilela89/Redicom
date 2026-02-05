<?

function _searchByFilterB2B($offset=null, $pages=null, $page_id=null, $search_name='', $search_value=0){

    global $userID, $CONFIG_TEMPLATES_PARAMS, $B2B;
    
    if( $search_name == '' || (int)$search_value <= 0 ){
        return [];
    }
    

    
    $search_name_bd = getSearchPageParamToProductDetail($search_name);
    
    if( (int)$offset == 0 ){
        $resp['terms'] = getSearchTermByPageParam($search_name_bd, $search_value);
    }

    $_query_regras = " AND registos.".$search_name_bd." =".$search_value;
    $temp_products_info = array();
    $temp_products_info = call_api_func('get_products', $page_id, $offset, $_query_regras, $pages);
    
    $resp['result_count']       = $temp_products_info['COUNT'];
    $resp['filters']            = $temp_products_info['FILTROS'];

    $resp['filters_encode']     = encodeFilters(36);
    

    $page = call_api_func('get_pagina', $page_id, "_trubricas");
    if( $B2B == 1 && !empty($page['catalogos']) && count(explode(",", $page['catalogos'])) == 1 ){
        $catalogo_id = (int)$page["catalogos"];
    }elseif($page["catalogo"]>0){
        $catalogo_id = $page["catalogo"];
        #$mercado=0;
    }else{
        $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        #$mercado  = 1;
    }
    
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
                  
   
    foreach($temp_products_info['PRODUCTS'] as $k => $v){
                    
        $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);

        if ((int)$_SESSION['_MARKET']['depositos_condicionados_ativo'] == 1 && trim($_SESSION['_MARKET']['depositos_condicionados']) != '' && has_only_conditioned_stock($v, $ids_depositos, $_SESSION['_MARKET']['depositos_condicionados'])) {

            if (is_null($market_extra_info)) {
                $market_extra_info = get_market_extra_info($_SESSION['_MARKET']["id"]);
            }

            $temp_products_info['PRODUCTS'][$k]['tags'][] = [
                'title'      => $market_extra_info['dc_etiqueta_nome'],
                'color'      => '#' . $market_extra_info['dc_etiqueta_cor_fundo'],
                'color_text' => '#' . $market_extra_info['dc_etiqueta_cor_texto']
            ];
        }
        
        $temp_products_info['PRODUCTS'][$k]['matriz'] = $matriz;
        
        $temp_products_info['PRODUCTS'][$k]['warehouse_availability'] = []; #by default no warehouse will be shown
        if( count($matriz['colunas']) == 1 && count($matriz['linhas']) == 1 && (int)$v['uncataloged_stock'] == 0 ){
            $temp_products_info['PRODUCTS'][$k]['warehouse_availability'] = check_warehouses_stock($ids_depositos, $temp_products_info['PRODUCTS'][$k]['sku'], $_SESSION['_MARKET']);
        }
        
    }
    
    $resp['results']['section'] = "Produtos";
    $resp['results']['items']   = $temp_products_info['PRODUCTS'];

    $tot_filtros = 0;
    foreach($_SESSION['filter_active'][36] as $k => $v){
        $tot_filtros += count($v);
    }
    
    $resp['order_by']             = call_api_func('get_order_by', "36");
    $resp['active_filters']       = ( isset($_SESSION['filter_active'][36]) ) ? 1 : 0;
    $resp['total_active_filters'] = $tot_filtros;
    $resp['active_order_by']      = ( isset($_SESSION['order_active'][36]) ) ? 1 : 0;
    $resp['grid_view']            =  $_SESSION['GridView'];
    $resp['grid_view_mobile']     =  $_SESSION['GridViewMobile'];

    $resp['total_products_comparator'] = 0;
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0) {  

        $resp['total_products_comparator']  = call_api_func('get_total_comparator');

        foreach($resp['results']['items'] as $k => $v){
            
            if($resp['total_products_comparator']>0){
                $resp['results']['items'][$k]['add_comparator']  = call_api_func('verify_product_add_comparator',$v['sku_family']);
            }
            
            if(count($resp['results']['items'][$k]['composition'])>0){
                $resp['results']['items'][$k]['comparator'] = 1;
            }
        }

    }
    
    $resp['shop'] = call_api_func('OBJ_shop_mini'); 
    
    if($resp['shop']['wishlist']>0){
        foreach($resp['results']['items'] as $k => $v){
            $resp['results']['items'][$k]["wishlist"] = call_api_func('verify_product_wishlist', $v['sku_family'], $userID);
        }
    }
    
    $resp['expressions']      = call_api_func('get_expressions',36);
    
    if(is_callable('custom_controller_search')) {
        call_user_func_array('custom_controller_search', array(&$resp));
    }
    
    return serialize($resp);

}



function getSearchPageParamToProductDetail($search_type){
    
    switch( $search_type ){
        
        case 'fm':  return 'familia';
        case 'sfm': return 'subfamilia';
        case 'cg':  return 'categoria';
        case 'scg': return 'subcategoria';
        case 'yr':  return 'ano';
        case 'ss':  return 'semestre';
        case 'mc':  return 'marca';
        case 'gm':  return 'gama';
        default:    return '';
        
    }
    
}

function getSearchTermByPageParam($search_name_bd, $search_value){

    global $LG;
    
    return get_line_table("`registos_".$search_name_bd."s`", "`id`='$search_value'", "`id`, `nome$LG` AS `name`")['name'];

}

?>
