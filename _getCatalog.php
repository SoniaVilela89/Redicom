<?php
function _getCatalog($page_id=0)
{

    global $fx, $INFO_NAV_PAG, $B2B;
    
    if ($page_id==0){
        $page_id = (int)params('page_id');
    }   
    
    $arr = array();
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
    
    
    $pai = $caminho[1]['id_pag'];

    if($B2B>0){
        $temp = $_GET['id'];
        $row_temp = call_api_func('get_pagina', $temp, "_trubricas");                    
        if($row_temp['subpagina']>0) $pai = $page_id;
    } 
   
    
    $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);

    
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = getExpressions(); 
    
    
    return serialize($arr);
    
}
?> 
