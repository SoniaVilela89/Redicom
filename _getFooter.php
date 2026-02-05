<?
function _getFooter($page_id=0){  

    global $fx, $LG, $COUNTRY;
    global $CACHE_HEADER_FOOTER, $CACHE_KEY;
    global $xmyaccount;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
 
    $resp = array();    
    
    $scope            = array();
    $scope['PAIS']    = $_SESSION['_COUNTRY']['id'];
    $scope['LG']      = $_SESSION['LG'];
    $scope['ACCOUNT'] = $xmyaccount;
    
    $_FTcacheid = $CACHE_KEY."FT_".implode('_', $scope);

    $dados = $fx->_iGetCache($_FTcacheid);
    
    if ($dados!=false && $_GET['nocache']!=1)
    {
        $resp = unserialize($dados);
        
    }else{

        $marcas = array();
        
        # INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.preco>0 
        # AND registos_precos.idListaPreco='".$_SESSION['_MARKET']['lista_preco']."' 
        
        
        $q = "SELECT registos_marcas.id, registos_marcas.ordem, registos_marcas.nome$LG, registos_marcas.desc$LG, registos_marcas.link, registos_marcas.target, registos_marcas.hidden
                                FROM registos_marcas 
                                WHERE registos_marcas.hidden = 0
                                    AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) 
                                GROUP BY registos_marcas.id
                                ORDER BY registos_marcas.ordem, registos_marcas.nome$LG, registos_marcas.id DESC";
                                
        $sql    = cms_query($q);

        while($v = cms_fetch_assoc($sql)){
            if(count($marcas)>=12) continue;

            $img = array();
            if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/marca_".$v['id'].".jpg") ){
                $img = call_api_func('imageOBJ',$v['nome'.$LG],1,"images/marca_".$v['id'].".jpg");
                $marcas[] = array(
                  "id"              => $v['id'],
                  "title"           => $v['nome'.$LG],
                  "image"           => $img,
                  "short_content"   => $v['desc'.$LG],
                  "url"             => $v['link'],
                  "target"          => $v['target']
                );
            }
        }

        $resp["icons_payments"] = get_icons_payment();
        $resp["icons_shipping"] = get_icons_shipping();
        $resp['brands'] = $marcas;
        $resp['footer_pages'] = call_api_func('get_menu', 3);
        $resp['social_pages'] = call_api_func('get_redes_sociais');
        $resp['expressions'] = call_api_func('get_expressions');
        
        
        if(count($resp['footer_pages'])>0) {
            $fx->_iSetCache($_FTcacheid, serialize($resp), $CACHE_HEADER_FOOTER);
        }
    }
    
    $resp['footer_pages'] = remove_strict_menu_pages( $resp['footer_pages'] );
    
    $resp['shop'] = call_api_func('OBJ_shop_mini'); 
    $resp['tag_manager_body'] = call_api_func('getTrackingTagManager',"Body");
    
    
    # 2020-05-26 - Campanha MA de recomendação de produtos em site - 9200
    $camp_r = verifyRecommendationCampaign(50);
    
    $resp['recommendation_campaign_active'] = 0; 
    if((int)$camp_r['automation']==50 || (int)$_GET['preview-ma']==9200) {
        $resp['recommendation_campaign_active'] = 1;
    }
    
    
    # 2020-05-26 - Campanha MA de recomendação de produtos em site - 9250
    $camp_r = verifyRecommendationCampaign(55);
    
    $resp['detail_recommendation_campaign_active'] = 0; 
    if((int)$camp_r['automation']==55 || (int)$_GET['preview-ma']==9250) {
        $resp['detail_recommendation_campaign_active'] = 1;
    }
    


    $resp['popup_with_login'] = 0;
    if($_SESSION['PAGE_WITH_LOGIN']==1){
        $resp['popup_with_login'] = 1;
    }
    
    
        
    return serialize($resp);
    
}
?>
