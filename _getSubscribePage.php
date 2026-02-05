<?

function _getSubscribePage($page_id=0){

    global $fx;
    global $LG;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $resp = array();
    
    if($INFO_SUBMENU==1){
        $resp['page'] = call_api_func('pageOBJ', 42, 42);
    }else{
        $row = call_api_func('get_pagina_modulos', 42, "_trubricas");
        $resp['page'] = call_api_func('OBJ_page', $row, 42, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $resp['page']['breadcrumb'] = $caminho;

    $resp['shop'] = call_api_func('OBJ_shop_mini');

    $resp['expressions'] = call_api_func('get_expressions',42);

    return serialize($resp);
}


?>
