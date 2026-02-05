<?

function _getPage($page_id=0)
{

    global $fx, $LG, $INFO_SUBMENU, $INFO_NAV_PAG;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
             
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }
        
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    
    $arr['expressions'] = call_api_func('get_expressions', $page_id);

    return serialize($arr);
    
}

?>
