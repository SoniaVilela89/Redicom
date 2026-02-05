<?

function _getCareers($page_id=0){

    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU, $INFO_NAV_PAG;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    $faqs = array();

    $res_faqs = cms_query("SELECT *
                            FROM _tfaqs
                            WHERE nome$LG!='' AND
                                cat in(4) AND
                                (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                            ORDER BY ordem,nome$LG,id DESC ");

    $faqs['section']      = "Careers";
    $faqs['request_form'] = "";
    $faqs['questions']    = array();

    while($row = cms_fetch_assoc($res_faqs) ){

        $pdf_info_careers = array();
        $cam = "downloads/careers/file".$row['id']."_".$LG.".pdf";
        
        if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
            $rowFile          = array('nome' => 'pdf', 'nome'.$LG => $row['nome'.$LG]);
            $pdf_info_careers = call_api_func('fileOBJ',$rowFile, $cam);
        }

        $cam  = "images/oportu_".$row['id'].".jpg";
        $temp = array();
        
        if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
            $temp = OBJ_image($row['nome'.$LG],1,$cam);
        }

        $faqs['questions'][] = array(
            "question"    => $row['nome'.$LG],
            "subtitulo"   => $row['subtitulo'.$LG],
            "data"        => $row['data'],
            "image"       => $temp,
            "response"    => base64_encode($row['desc'.$LG]),
            "file"        => $pdf_info_careers
        );
    }

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 77, 77);
    }else{
        $row          = call_api_func('get_pagina_modulos', 77, "_trubricas");
        $arr['page']  = call_api_func('OBJ_page', $row, 77, 0);
    } 
    
    
    $caminho                    = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb']  = $caminho;
    
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
  
    
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $arr['faqs']        = $faqs;
    $arr['shop']        = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions',77);

    return serialize($arr);
}


?>
