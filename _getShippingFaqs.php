<?

function _getShippingFaqs($page_id=0){

    global $LG, $INFO_SUBMENU, $INFO_NAV_PAG, $COUNTRY;
    
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $faqs     = array();
    $res_faqs = cms_query("SELECT *
                            FROM _tfaqs
                            WHERE nome$LG!='' AND
                                cat in(5) AND
                                (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                            ORDER BY ordem,nome$LG,id DESC");

    $faqs['section']      = "ShippingFaqs";
    $faqs['request_form'] = "";
    $faqs['questions']    = array();

    while($row = cms_fetch_assoc($res_faqs) ){
        $faqs['questions'][] = array("question" => $row['nome'.$LG],
                                    "response" => base64_encode($row['desc'.$LG]));
    }

    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 25, 25);
    }else{
        $row = call_api_func('get_pagina_modulos', 25, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 25, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $arr['faqs'] = $faqs;
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',25);

   return serialize($arr);
}


?>
