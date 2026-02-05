<?

function _getSearch($page_id=0)
{
    global $developer;
    global $LG;
    global $INFO_SUBMENU;
    global $row; # 08-12-2022 para substituir o row do getHeader
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
            
    $resp = array();
        
    if($INFO_SUBMENU==1){
        $resp['page'] = call_api_func('pageOBJ', 36, 36);
    }else{
        $row = call_api_func('get_pagina_modulos', 36, "_trubricas");
        $resp['page'] = call_api_func('OBJ_page', $row, 36, 0);
        $resp['page']['id'] = 36;
    }
    
    
    $caminho = call_api_func('get_breadcrumb',$page_id);
    $resp['page']['breadcrumb'] = $caminho;
    
    
    $resp['expressions']      = call_api_func('get_expressions',36);
    $resp['grid_view']        =  $_SESSION['GridView'];
    $resp['grid_view_mobile'] =  $_SESSION['GridViewMobile'];
    $resp['order_by']         = call_api_func('get_order_by', 36);
    
    $resp['active_filters']   = ( isset($_SESSION['filter_active'][$page_id]) ) ? 1 : 0;
    $resp['active_order_by']  = ( isset($_SESSION['order_active'][$page_id]) ) ? 1 : 0;
    
    
    #Inutilizado 13-04-2019
    /*$q = "select *, SUM(total) as tot from searched where total>0 AND lg='".$LG."' AND id_mercado='".$_SESSION['_MARKET']['id']."' GROUP BY termo order by tot desc LIMIT 0,8";
    $sql = cms_query($q);
    while($v = cms_fetch_assoc($sql)){
        $most_searched[] = array(
          "term"  => $v['termo'],
          "url"   => "index.php?id=36&term=".$v['termo']
        );
    }
    $resp['most_searched']  = $most_searched; */
    
    
    
    $resp['shop']           = call_api_func('OBJ_shop_mini');
    
    
    return serialize($resp);

}

?>
