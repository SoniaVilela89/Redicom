<?

function _getClientCard($page_id=0){

    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;

    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    $faqs = array();
    $res_faqs = cms_query("SELECT * FROM _tfaqs WHERE  nome$LG!='' AND cat in(6) AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) ORDER BY ordem,nome$LG,id DESC");
    $faqs['section'] = "ClientCard";
    $faqs['request_form'] = "";
    $faqs['questions'] = array();

    while($row = cms_fetch_assoc($res_faqs) ){
        $faqs['questions'][] = array(
            "question" => $row['nome'.$LG],
            "response" => base64_encode($row['desc'.$LG])
        );
    }

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 26, 26);
    }else{
        $row = call_api_func('get_pagina_modulos', 26, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 26, 0);
    }
    
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['faqs'] = $faqs;
    $arr['expressions'] = call_api_func('get_expressions',26);


    return serialize($arr);
}


?>
