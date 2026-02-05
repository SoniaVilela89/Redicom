<?

function _getEvents($page_id=0){

    global $fx, $LG, $COUNTRY, $INFO_NAV_PAG;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $sql = cms_query("SELECT * FROM noticias WHERE
                                nome$LG<>'' AND
                                dodia<=CURDATE() AND
                                aodia>=CURDATE() AND
                                cat='2' AND
                                (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                            ORDER BY data DESC, ordem, id DESC ");
    $group  = array();
    $not = array();
    while($v = cms_fetch_assoc($sql)){
        $temp         = call_api_func('articleOBJ', $v);
        $temp['url']  = "index.php?id=$page_id&ide=".$v['id'];
        $not[]        = $temp;
        $temp2 = call_api_func('articleGroupOBJ',$v);
        if(!in_array($temp2, $group)){
          $group[] = $temp2;
        }
    }

    $arr                        = array();
    $row                        = call_api_func('get_pagina_modulos', 43, "_trubricas");
    $arr['page']                = call_api_func('OBJ_page', $row, 43, 0);
        
    $caminho                    = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb']  = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    

    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $arr['articles']            = $not;
    $arr['article_group']       = $group;
    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['expressions']         = call_api_func('get_expressions',43);

    return serialize($arr);
}


?>
