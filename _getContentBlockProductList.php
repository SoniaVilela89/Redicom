<?    
function _getContentBlockProductList($page_id=0, $content_block_id=null, $include_hf=0, $show_all=0, $device='')
{
    global $SETTINGS_LOJA, $fx, $LG, $INFO_SUBMENU, $INFO_NAV_PAG, $CACHE_HOME, $COUNTRY, $userID, $CACHE_KEY, $detect, $B2B, $B2B_LAYOUT;
    
    if($page_id==0){
        $page_id            = (int)params('page_id');
        $content_block_id   = (int)params('content_block_id');
        $include_hf         = (int)params('include_hf');
        $show_all           = (int)params('show_all');
        $device             = params('device');
    }

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $_SERVER['DOCUMENT_ROOT'], false, _ROOT.'/temp_twig/');

    include_once("_getContentBlock.php");
    
    global $C_content_block_id;

    $C_content_block_id = $content_block_id;
    
    $resp = _getContentBlock($page_id, $content_block_id, $show_all, $device, 1);
    $x['response'] = unserialize($resp);   
    
    foreach($x['response']['content_blocks'] as $k => $v){
        foreach($v["banners"] as $kk => $vv){
            $x['response']['content_blocks'][$k]['banners'][$kk]['html'] = base64_decode($vv['html']);
            $x['response']['content_blocks'][$k]['banners'][$kk]['description'] = base64_decode($vv['description']);

            if( strpos($vv['link'], "index.php?") === 0 ) $x['response']['content_blocks'][$k]['banners'][$kk]['link'] = "/".$vv['link'];
            if( strpos($vv['button_link'], "index.php?") === 0 ) $x['response']['content_blocks'][$k]['banners'][$kk]['button_link'] = "/".$vv['button_link'];
            if( strpos($vv['linkURL'], "index.php?") === 0 ) $x['response']['content_blocks'][$k]['banners'][$kk]['linkURL'] = "/".$vv['linkURL'];
            if( strpos($vv['linkURL2'], "index.php?") === 0 ) $x['response']['content_blocks'][$k]['banners'][$kk]['linkURL2'] = "/".$vv['linkURL2'];
        }
        
        foreach($v["tabs"] as $kk => $vv){
            foreach($vv['products'] as $kkk => $vvv){              
                $x['response']['content_blocks'][$k]['tabs'][$kk]['products'][$kkk]['info'] = base64_decode($vvv['info']);
                $x['response']['content_blocks'][$k]['tabs'][$kk]['products'][$kkk]['info_mob'] = base64_decode($vvv['info_mob']);
            }
        }
    }
    
    $IS_SALES = 0; 
    if(strpos($_SERVER['SERVER_NAME'], 'sales') !== false) {
        $IS_SALES = 1; 
    }
    
   
    
    if($IS_SALES==1) {
        $x['response']['layout']['product_item'] = "templates/";
    }elseif(file_exists("../templates/product_item.htm")){
        $x['response']['layout']['product_item'] = "templates/";
    }elseif($B2B==1){     
        if((int)$B2B_LAYOUT['b2b_style_version'] == 1){
            $x['response']['layout']['product_item'] = "plugins/templates_base_b2b/product_item/2/";
        }else{
            $x['response']['layout']['product_item'] = "plugins/templates_base_b2b/";
        }
    }elseif($SETTINGS_LOJA['base_3']['campo_12']!=''){
        $x['response']['layout']['product_item'] = "plugins/templates_base/product_item/".$SETTINGS_LOJA['base_3']['campo_12']."/";
    }else{    
        $x['response']['layout']['product_item'] = "templates_system/"; 
    }

    
    render_page($x, 'content_blocks');
    
    # Opções TEMPLATES_PARAMS api_config e opções do BO - prioridade ao api_config
    get_options_template($x, 'product_item');
    
    $x['hideHeading']           = 1;
    $x['include_header_footer'] = $include_hf;
        
    if(file_exists("templates/content_blocks.htm")){
        $html = $fx->printTwigTemplate("templates/content_blocks.htm",$x, true, $exp);
    }elseif($SETTINGS_LOJA['base_3']['campo_2']!=''){
        $html = $fx->printTwigTemplate("plugins/templates_base/content_blocks/".$SETTINGS_LOJA['base_3']['campo_2']."/content_blocks.htm",$x, true, $exp);
    }else{
        $html = $fx->printTwigTemplate("templates_system/content_blocks.htm",$x, true, $exp);
    }
    
    $arr                = array();
    $arr['block_html']  = base64_encode($html);  
    $arr['block_id']    = $content_block_id;  

    return serialize($arr);
}

?>
