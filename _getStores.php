<?

function _getStores($page_id=0){

    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    global $id;
    global $B2B;
    global $COUNTRY;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 52, 52);
    }else{
        $row = call_api_func('get_pagina_modulos', 52, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 52, 0);
    } 

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  

    $arr['shop'] = call_api_func('shopOBJ');
    
    if((int)$B2B == 1){
        $brands = array();
        
        $sql  = cms_query("SELECT m.id, m.nome$LG as nome 
                            FROM ec_lojas l 
                                INNER JOIN registos_marcas m ON FIND_IN_SET(m.id, l.marca) > 0 
                            WHERE l.hidden=0 AND l.marca!=''
                            GROUP BY m.id
                            ORDER BY m.nome$LG");
                            
        while($row_brands = cms_fetch_assoc($sql)){
            $brands[] = array(
                "id"    => $row_brands['id'],
                "name"  => $row_brands['nome']
            );
        }
        $arr['brands'] = $brands;

    }
    
    $arr['expressions'] = call_api_func('get_expressions',52);

    return serialize($arr);
}


?>
