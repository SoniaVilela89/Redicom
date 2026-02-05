<?

function _getNewsDetail($page_id=0, $new_id=0){

    if ($page_id==0){
       $page_id = (int)params('page_id');
       $new_id = (int)params('new_id');
    }

    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;    
    

    $not_actual = array();


    $sql   = cms_query("SELECT * FROM noticias
                        WHERE nome$LG<>'' AND
                            dodia<=CURDATE() AND
                            aodia>=CURDATE() AND
                            cat='1' AND
                            (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                        ORDER BY data DESC, ordem, id DESC ");

    $group  = array();
    $nots   = array();
    
    while($v = cms_fetch_assoc($sql)){
        $temp         = call_api_func('OBJ_article',$v);
        $temp['url']  = "index.php?id=$page_id&idn=".$v['id'];
        $nots[]       = $temp;
        $temp2        = call_api_func('OBJ_articleGroup',$v);
        if(!in_array($group,$temp2)){
          $group[] = $temp2;
        }
        
        if($v['id']==$new_id){
            $not_actual = $temp;
        }
        
    }

    $resp = array();
    
    $resp['selected_new'] = $not_actual;
    
    
    if($INFO_SUBMENU==1){
        $resp['page']  = call_api_func('pageOBJ', 46, 46);
    }else{
        $row           = call_api_func('get_pagina', 46, "_trubricas");
        $resp['page']  = call_api_func('OBJ_page', $row, 46, 0);
    } 

    
    $caminho    = call_api_func('get_breadcrumb', $page_id);
    $caminho[]  = array(
        "name" => $resp["selected_new"]["title"],
        "link" => $resp["selected_new"]["url"],
        "without_click" => 1
    );
    $resp['page']['breadcrumb'] = $caminho;

    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $resp['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);    
    
    
    $resp['articles']           = $nots;
    $resp['article_group']      = $group;
    $resp['shop']               = call_api_func('OBJ_shop_mini');
    $resp['expressions']        = call_api_func('get_expressions',46);
    return serialize($resp);
}


?>
