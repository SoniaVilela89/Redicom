<?

function _getCreators($page_id=0){
    
    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
          
    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 54, 54);
    }else{
        $row = call_api_func('get_pagina_modulos', 54, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 54, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;

    $arr['creators'] = getCreators($page_id);
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',54);
    return serialize($arr);
}


?>
