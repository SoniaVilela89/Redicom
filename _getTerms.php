<?

function _getTerms($page_id=0){

    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;

    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    $faqs = array();
    $res_faqs = cms_query("SELECT *
                            FROM _tfaqs
                            WHERE  nome$LG!='' AND
                                cat in(3) AND
                                (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                            ORDER BY ordem, id");

    $faqs['section']      = "Terms";
    $faqs['request_form'] = "";
    $faqs['questions']    = array(); 
    
    while($row = cms_fetch_assoc($res_faqs) ){
        $faqs['questions'][] = array(
            "id"       => $row['id'],
            "question" => $row['nome'.$LG],
            "response" => base64_encode($row['desc'.$LG])
        );
    }


    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 51, 51);
    }else{
        $row = call_api_func('get_pagina_modulos', 51, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 51, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
    $arr['faqs'] = $faqs;
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',51);

    $Mercado = call_api_func("get_line_table", "ec_mercado", "id='".$_SESSION['_MARKET']['id']."'");                                               
    if( $Mercado['bloco'.$LG]!="" ){
        $arr['page']['content'] = base64_encode($Mercado['bloco'.$LG]);
    }

    return serialize($arr);
}

?>
