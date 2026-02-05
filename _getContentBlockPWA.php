<?    
function _getContentBlockPWA($content_block_id=0, $show_all=0)
{
    global $fx, $LG, $CACHE_HOME, $COUNTRY, $userID, $CACHE_KEY, $cdn_location, $CONFIG_TEMPLATES_PARAMS;
        
        
    if($content_block_id == 0){
        $content_block_id = (int)params('content_block_id');
        $show_all = (int)params('show_all');
    }  
    
    $arr_html = array("content_blocks" => "");
    
    $more_sql = "";
    if($content_block_id > 0) $more_sql = " id = '".$content_block_id."' AND ";
    $more_date = " AND (StartDate<=CURDATE() AND EndDate>=CURDATE()) ";
    if($show_all) $more_date = "";

    $sql_block  = "SELECT * 
                    FROM ContentBlocksPWA
                    WHERE $more_sql (Countries='' OR CONCAT(',',Countries,',') LIKE '%,".$COUNTRY["id"].",%' )
                    $more_date    
                    ORDER BY id DESC LIMIT 0,1";
                    
    $res_block  = cms_query($sql_block);
    $row_block  = cms_fetch_assoc($res_block);
    
    if((int)$row_block["id"] > 0) $content_block_id = (int)$row_block["id"];
    else{
        $sql_block  = "SELECT * 
                    FROM ContentBlocksPWA
                    WHERE $more_sql (Countries='' OR CONCAT(',',Countries,',') LIKE '%,".$COUNTRY["id"].",%' )  
                    ORDER BY id DESC LIMIT 0,1";
                    
        $res_block  = cms_query($sql_block);
        $row_block  = cms_fetch_assoc($res_block);

        $content_block_id = (int)$row_block["id"];
    }
    
    $mobile = "MOBILE";
    if($device!='') $mobile = $device;
    
    
    
    $PROMOS = getAvailablePromoForUser();    
   

    $scope                      = array();   
    $scope['BLOCK']             = 'CIP_'.$content_block_id;  #Ao mudar esta chave ter em atencao ao ficheiro data_triggers + modules/content_blocks_pwa/actions do BO
    $scope['LG']                = $_SESSION['LG'];
    $scope['DEVICE']            = $mobile;       
    $scope['PRICE_LIST']        = $_SESSION['_MARKET']['lista_preco'];
    $scope['PAIS']              = $_SESSION['_COUNTRY']['id'];
    $scope['PROMO']             = implode(',', $PROMOS["promos"]);            
    
    $_HPcacheid = $CACHE_KEY."CBL_".implode('_', $scope);
    
    $dados = $fx->_GetCache($_HPcacheid, $CACHE_HOME);

    if ($dados!=false && $_GET['nocache']!=1 && $show_all==0){         

        $arr = unserialize($dados); 
             
    } else { 
                    
        $arr = array();

        $arr['content_blocks'] = get_content_blocks_pwa($content_block_id, $show_all);
        
        $arr['shop']['CDN'] = $cdn_location;
        $arr['shop']['TEMPLATES_PARAMS'] = $CONFIG_TEMPLATES_PARAMS;

                
        $style = get_line_table_api_obj("ContentBlocksStylesPWA", "Id='1'");  
    
        $arr['content_blocks_style'] = $style;

        $arr['expressions'] = call_api_func('get_expressions', $page_id);
        
        $fx->_SetCache($_HPcacheid, serialize($arr), $CACHE_HOME);                          
    }   
    
    
    render_page($arr, 'content_block_pwa');

    $html_block_pwa = "";
    if(count($arr['content_blocks']) > 0){
        $fx->LoadTwig(_ROOT."/lib/Twig/Autoloader.php", _ROOT, false, _ROOT.'/temp_twig/');
        $html_block_pwa = $fx->printTwigTemplate("plugins/templates_base/content_blocks_pwa/1/content_blocks.htm", array("response" => $arr), true, $exp);
    } 

    $arr_html["content_blocks"] = base64_encode($html_block_pwa);

    
    return serialize($arr_html);
   
}

?>
