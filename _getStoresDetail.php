<?

function _getStoresDetail($page_id=0, $store_id=0){
          
    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $store_id = (int)params('store_id');
    }
    
    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 52, 52);
    }else{
        $row = call_api_func('get_pagina_modulos', 52, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 52, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
  
    $store = get_loja($store_id);
    if( (int)$store['id'] > 0 && (int)$store['hidden'] == 0 ){
        $arr['store'] = $store;
    }
    
    $arr['shop'] = call_api_func('shopOBJ');

    $arr['expressions'] = call_api_func('get_expressions',52);

    return serialize($arr);

}

?>
