<?

function _getFaqsDetail($page_id=0, $new_id=0){
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $new_id = (int)params('new_id');
    }

    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;    

    
    $sql = cms_query("SELECT * 
                        FROM _tfaqs 
                        WHERE nome$LG!='' 
                            AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) 
                            AND id = $new_id 
                        ORDER BY ordem, nome$LG,id DESC");  

    $faq = cms_fetch_assoc($sql);
       
    $faq_resp = call_api_func('OBJ_faq',$faq,$page_id);         
    
    $resp = array();
    
    if($INFO_SUBMENU==1){
        $resp['page']  = call_api_func('pageOBJ', 45, 45);
    }else{
        $row          = call_api_func('get_pagina', 45, "_trubricas");
        $resp['page']  = call_api_func('OBJ_page', $row, 45, 0);
    } 

    $caminho    = call_api_func('get_breadcrumb', $page_id);
    $caminho[]  = array(
        "name" => $faq_resp["title"],
        "link" => "index.php?id=".$page_id."&idf=".$new_id,
        "without_click" => 1
    );
    
    $resp['page']['breadcrumb']   = $caminho;
    

    if($LG=='gb'){
        $data = strftime("%d %B, %Y",strtotime($faq['last_update']));
    }else{
        setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
        $data = strftime("%d de %B de %Y",strtotime($faq['last_update']));
    }  
    
    $resp['page']['page_title']   = $faq_resp['title'];
    $resp['page']['shot_content'] = estr(410).' '.$data;
    

    $resp['faq']                  = $faq_resp;     
    $resp['shop']                 = call_api_func('OBJ_shop_mini');
    $resp['expressions']          = call_api_func('get_expressions',45);
    
    return serialize($resp);
}


?>
