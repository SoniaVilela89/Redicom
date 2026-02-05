<?

function _getAboutPage($page_id=0){

    global $fx, $LG, $INFO_SUBMENU;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();

    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 39, 39);
    }else{
        $row = call_api_func('get_pagina_modulos', 39, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 39, 0);
    }
            
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['collections']    = call_api_func('getCollections',39);
    $arr['expressions']    = call_api_func('get_expressions',39);

    return serialize($arr);
}


?>
