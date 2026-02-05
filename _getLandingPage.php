<?
function _getLandingPage($page_id=0, $landing_group=0, $landing_id=0){
     
    global $LG;
    global $INFO_SUBMENU;
    global $SETTINGS;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $landing_group = (int)params('landing_group');
       $landing_id = (int)params('landing_id');
    }
            
            
  
    $arr = array();
        
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 90, 90);
    }else{
        $row = call_api_func('get_pagina', 90, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 90, 0);
    }
        
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;   
    
    $arr['landingpage'] = call_api_func('get_landing_page', $landing_group, $landing_id);
    
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',90);
    
    $arr['allow_telf'] = (int)$SETTINGS['telf_fixo'];

    return serialize($arr);
}

?>
