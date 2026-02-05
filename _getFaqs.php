<?

function _getFaqs($page_id=0){

    global $LG, $COUNTRY, $INFO_SUBMENU, $INFO_NAV_PAG;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
   
    $faqs                 = array();    
    $faqs['section']      = "Faqs";
    $faqs['request_form'] = "";
    $faqs['group']        = array(); 
   
    $arr_grp_faqs = array(); 
    
    $grp_faqs             = cms_query("SELECT * FROM _tfaqs_cat WHERE  nome$LG!='' ORDER BY ordem, id");
    while($row_grp = cms_fetch_assoc($grp_faqs) ){
    
        $q_faqs   = array(); 
        
        $res_faqs = cms_query("SELECT * FROM _tfaqs WHERE nome$LG!='' AND cat in(1) AND categoria='".$row_grp['id']."'  AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) ORDER BY ordem, nome$LG,id DESC");      
        while($row = cms_fetch_assoc($res_faqs) ){
            $q_faqs[] = array(  "id"          => $row['id'],  
                                "question"    => $row['nome'.$LG],
                                "response"    => base64_encode($row['desc'.$LG]));
        }
        
        if(count($q_faqs)>0){
            $img = '';
            if(file_exists($_SERVER['DOCUMENT_ROOT']."/images/faqs_cat_".$row_grp['id'].".jpg")){
                $img = call_api_func('OBJ_image',$row_grp['nome'.$LG], 1, "images/faqs_cat_".$row_grp['id'].".jpg");
            }

            $faqs['group'][] = array("id"          => $row_grp['id'],
                                    "title"        => $row_grp['nome'.$LG],
                                    "description"  => $row_grp['desc'.$LG],
                                    "image"        => $img,
                                    "questions"    => $q_faqs);
        }
    }

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 45, 45);
    }else{
        $row = call_api_func('get_pagina_modulos', 45, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 45, 0);
    }

    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row ,$page_id);

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }
    
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['faqs'] = $faqs;
    $arr['expressions'] = call_api_func('get_expressions',45);

    return serialize($arr);
}


?>
