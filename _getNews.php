<?

function _getNews($page_id=0){
    
    global $fx, $LG, $COUNTRY, $INFO_SUBMENU, $INFO_NAV_PAG;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }


    $sql = cms_query("SELECT * FROM noticias
                        WHERE nome$LG<>'' AND
                            dodia<=CURDATE() AND
                            aodia>=CURDATE() AND
                            cat='1' AND
                            (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                        ORDER BY data DESC, ordem, id DESC ");
    $group  = array();
    $not    = array();
    while($v = cms_fetch_assoc($sql)){
        $temp         = call_api_func('OBJ_article', $v);
        $temp['url']  = "index.php?id=$page_id&idn=".$v['id'];
        $not[]        = $temp;
        $temp2        = call_api_func('OBJ_articleGroup',$v);
        if(!in_array($temp2,$group)){
            $group[] = $temp2;
        }        
    }
    
    
    $resp = array();

    if($INFO_SUBMENU==1){
        $resp['page'] = call_api_func('pageOBJ', 46, 46);
    }else{
        $row = call_api_func('get_pagina', 46, "_trubricas");
        $resp['page'] = call_api_func('OBJ_page', $row, 46, 0);
    } 

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $resp['page']['breadcrumb'] = $caminho;
    
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $resp['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  

    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $resp['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $resp['articles'] = $not;
    $resp['article_group'] = $group;
    $resp['shop'] = call_api_func('OBJ_shop_mini');
    $resp['expressions'] = call_api_func('get_expressions', 46);
    

    return serialize($resp);
}


?>
