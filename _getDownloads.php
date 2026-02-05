<?

function _getDownloads($page_id=0, $year=0)
{

    global $fx;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $year = (int)params('year');
    }

    $arr = array();
    
    
    $row = call_api_func('get_pagina_modulos', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);
    
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
                           
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 53, 53);
    }else{
        $row = call_api_func('get_pagina_modulos', 53, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 53, 0);
    }

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',53);

    return serialize($arr);
    
}

?>
