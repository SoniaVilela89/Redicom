<?

function _getAccordion($page_id=0){

    global $LG, $COUNTRY;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
   
   
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
   
    $faqs                 = array();    
    $faqs['section']      = "Faqs";
    $faqs['group']        = array(); 
   
    $arr_grp_faqs = array(); 
    
    $grp_faqs  = cms_query("SELECT * FROM _tacordiao_cats WHERE  nome$LG!='' and id in (".$row['opcoes_acordiao'].") ORDER BY ordem, id");
    while($row_grp = cms_fetch_assoc($grp_faqs) ){
    
        $q_faqs   = array(); 
        
        $res_faqs = cms_query("SELECT * FROM _tacordiao WHERE nome$LG!='' AND cat='".$row_grp['id']."'   AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) ORDER BY ordem, nome$LG,id DESC");   
        while($row = cms_fetch_assoc($res_faqs) ){
            $q_faqs[] = array(  "question"    => $row['nome'.$LG],
                                "response"    => base64_encode($row['desc'.$LG]));
        }
        
        if(count($q_faqs)>0){
            $faqs['group'][] = array("id"          => $row_grp['id'],
                                    "title"        => $row_grp['nome'.$LG],
                                    "description"  => $row_grp['desc'.$LG],
                                    "questions"    => $q_faqs);       
        }
    }
    
    
    $arr = array();
    $arr['faqs'] = $faqs;

    return serialize($arr);
}


?>
