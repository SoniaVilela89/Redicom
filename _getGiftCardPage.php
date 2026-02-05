<?

function _getGiftCardPage($page_id=0)
{

    global $fx;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();

    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 35, 35);
    }else{
        $row = call_api_func('get_pagina_modulos', 35, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 35, 0);
    }
    
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);          
    
    $arr['cards'] = call_api_func('get_egift_desenho');    
    $arr['values'] = call_api_func('get_egift_valores');    
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions', 35);

    return serialize($arr);
}

?>
