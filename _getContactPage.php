<?

function _getContactPage($page_id=0){

    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 40, 40);
    }else{
        $row = call_api_func('get_pagina_modulos', 40, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 40, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
    $arr['social_pages'] = call_api_func('get_redes_sociais');

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',40);

    return serialize($arr);
}


?>
