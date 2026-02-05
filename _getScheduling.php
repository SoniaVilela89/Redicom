<?                                  
function _getScheduling($page_id=0){

    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU, $db_name_cms;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }


    $sql = cms_query("SELECT * FROM agendamentos
                        WHERE nome$LG<>'' AND
                            dodia<=CURDATE() AND
                            aodia>=CURDATE() AND                             
                            (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                        ORDER BY agendamento_dodia DESC, ordem, id DESC ");    


    $agend    = array();
    while($v = cms_fetch_assoc($sql)){
        $temp         = call_api_func('OBJ_scheduling', $v);
        $temp['url']  = "index.php?id=$page_id&ida=".$v['id'];
        $agend[]        = $temp;            
    }
    
    
    $resp = array();

    if($INFO_SUBMENU==1){
        $resp['page'] = call_api_func('pageOBJ', 94, 94);
    }else{
        $row = call_api_func('get_pagina', 94, "_trubricas");
        $resp['page'] = call_api_func('OBJ_page', $row, 94, 0);
    } 

    $caminho = call_api_func('get_breadcrumb', $page_id);
    
    $resp['page']['breadcrumb'] = $caminho;  
    $resp['articles'] = $agend;
    
    $resp['content_blocks'] = get_content_blocks($row["ContentBlock"], 0);

    $table_style = "`$db_name_cms`.ContentBlocksStyles";                                                                        
    $style = get_line_table_api_obj($table_style, "Id='1'");
      
    $resp['content_blocks_style'] = $style;
    
    $resp['shop'] = call_api_func('OBJ_shop_mini');
    $resp['expressions'] = call_api_func('get_expressions', 94);
    

    return serialize($resp);
}


?>
