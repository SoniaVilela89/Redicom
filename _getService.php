<?

function _getService($page_id=0, $service_id=0){

    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    

    if ($page_id==0){
       $page_id = (int)params('page_id');
       $service_id = (int)params('service_id');
    }
    

    $arr = array();

    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 44, 44);
    }else{
        $row = call_api_func('get_pagina_modulos', 44, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 44, 0);
    } 
        
    $arr['selected_service'] = call_api_func('getService',$service_id, $page_id);

    $caminho = call_api_func('get_breadcrumb', $page_id);

    $caminho[] = array(
      "name" => $arr["selected_service"]["title"],
      "link" => $arr["selected_service"]["url"],
      "without_click" => 1
    );
    $arr['page']['breadcrumb'] = $caminho;

    $arr['services'] = call_api_func('getServices', $page_id);
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',44);
    return serialize($arr);
}


?>
