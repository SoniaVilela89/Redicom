<?

function _getEventsDetail($page_id=0, $event_id=0){
    
    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;   
    
                
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $event_id = (int)params('event_id');       
    }
 

    $not_actual = array();


    $sql = cms_query("SELECT * FROM noticias
                        WHERE nome$LG<>'' AND
                            dodia<=CURDATE() AND
                            aodia>=CURDATE() AND
                            cat='2' AND
                            (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                        ORDER BY data DESC, ordem, id DESC ");

    $group  = array();
    $nots   = array();
    while($v = cms_fetch_assoc($sql)){
        $temp = call_api_func('articleOBJ',$v);
        $temp['url'] = "index.php?id=$page_id&ide=".$v['id'];
        $nots[] = $temp;
        $temp2 = call_api_func('articleGroupOBJ',$v);
        if(!in_array($group,$temp2)){
          $group[] = $temp2;
        }
        
        if($v['id']==$event_id){
            $not_actual = $temp;
        }
        
    }

    $resp = array();
    
    if($INFO_SUBMENU==1){
        $resp['page'] = call_api_func('pageOBJ', 43, 43);
    }else{
        $row = call_api_func('get_pagina', 43, "_trubricas");
        $resp['page'] = call_api_func('OBJ_page', $row, 43, 0);
    }
    
    $resp['selected_new'] = $not_actual;
    
    $caminho = call_api_func('get_breadcrumb', $page_id);

    $caminho[] = array(
        "name" => $resp["selected_new"]["title"],
        "link" => $resp["selected_new"]["url"],
        "without_click" => 1
    );
    
    $resp['page']['breadcrumb'] = $caminho;
    $resp['articles'] = $nots;
    $resp['article_group'] = $group;
    $resp['shop'] = call_api_func('OBJ_shop_mini');
    $resp['expressions'] = call_api_func('get_expressions',46);
    return serialize($resp);
}


?>
