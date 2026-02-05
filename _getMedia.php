<?

function _getMedia($page_id=0){

    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;
  

    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    

    $sql    = cms_query("SELECT * FROM noticias WHERE
                                nome$LG<>'' AND
                                dodia<=CURDATE() AND
                                aodia>=CURDATE() AND
                                cat='2' AND
                                (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                            ORDER BY ordem, data DESC, id DESC");

    $not = array();
    while($v = cms_fetch_assoc($sql)){
        $not[] = call_api_func('articleOBJ',$v);
    }
          

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page']  = call_api_func('pageOBJ', 86, 86);
    }else{
        $row          = call_api_func('get_pagina_modulos', 86, "_trubricas");
        $arr['page']  = call_api_func('OBJ_page', $row, 86, 0);
    } 

    $caminho                    = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb']  = $caminho;

    $row_child                  = call_api_func('get_pagina_modulos', 43, "_trubricas");
    $arr['page']['childs'][]    = call_api_func('OBJ_page', $row_child, 43, 0);



    $arr['collections']         = call_api_func('getCollections',86);
    $arr['articles']            = $not;
    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['expressions']         = call_api_func('get_expressions',86);

    return serialize($arr);
}


?>
