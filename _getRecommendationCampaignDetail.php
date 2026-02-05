<?

# Recomendaçoes no detalhe do produto

function _getRecommendationCampaignDetail()
{

    global $userID, $COUNTRY, $CACHE_KEY, $fx, $SETTINGS_LOJA;
    
    $pid = $_POST['pid'];
    $info_class = $_POST['info_class'];
    $return_type = (int)$_POST['return_type'];


    $camp_r = verifyRecommendationCampaign(55);
    if((int)$_POST['preview-ma']==9250) {
        $camp_r = call_api_func('get_line_table', 'ec_campanhas', "id='9250'");
    }

    if((int)$camp_r['automation']!=55 ) return serialize(array("active" => 0, "prods" => ""));
    
    
    #2022-12-26 
    if( (int)$preview_ma == 0 ) cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9250, 1, '1') ON DUPLICATE KEY UPDATE impressoes=impressoes+1");
    
    
         
    
    
    
    $prod = get_line_table_cache_api('registos', "id='".$pid."'"); 
   
       
             
    $_cacheid   = $CACHE_KEY."MA_PROD_REC_55_".$prod['genero'].'_'.$prod['marca'];
    
    
    $dados = $fx->_GetCache($_cacheid, 120); #2 horas 
    
         
          
    
    $pids = array();
    
    if ($dados!=false && $_GET['nocache']!=1 ){
        $pids = $dados['produtos'];
    }else{
                 
  
        $menos_90dias = date("Y-m-d", strtotime("-90 days"));
        
        
        $more_where = "";
        if((int)$camp_r['crit_prods_brand']==1) $more_where .= " AND r.marca='".$prod['marca']."' ";
        if((int)$camp_r['crit_prods_gender']==1) $more_where .= " AND r.genero='".$prod['genero']."' ";
        #$more_where .= " AND r.genero='".$prod['genero']."' AND r.marca='".$prod['marca']."' ";
        
    
        # Obtem os últimos produtos comprados + que 2 vez por qualquer pessoa 
        $produtos_comprados_s = "SELECT r.id as pid, e.sku_family
                                  FROM ec_encomendas_lines e
                                      INNER JOIN registos r ON SUBSTRING_INDEX(e.pid, '|||', -1)=r.id AND r.activo=1
                                  WHERE e.data>'$menos_90dias'
                                      AND e.status>0
                                      AND e.order_id>0
                                      AND e.ref!='PORTES'
                                      AND e.id_linha_orig<1
                                      $more_where
                                  GROUP BY r.sku_family
                                  HAVING COUNT(e.id)>1 
                                  ORDER BY r.id DESC
                                  LIMIT 0,20";
            
                    
        $produtos_comprados_q = cms_query($produtos_comprados_s);
    
        while($prod_linha = cms_fetch_assoc($produtos_comprados_q)){
        
            if( !array_key_exists($prod_linha['sku_family'], $pids) ){
                $pids[$prod_linha['sku_family']]= $prod_linha; 
            }
            
        }        
        
        $resp = array();
        $resp['produtos'] = $pids;
            
        $fx->_SetCache($_cacheid, $resp, 120);
        
    }
    
    
    # Reorganiza de forma random as posições do array
    shuffle($pids);



    require_once($_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductSimple.php');

    $cont_prods = 0;
    
    $produtos         = array();
    $produtos_family  = array();
    
    foreach($pids as $k => $prod_linha){
    
        if($cont_prods>=6) break;
        
        # Verificar se cliente já comprou alguma vez o produto a recomendar
        if(is_numeric($userID)){
            $compra_q = cms_query("SELECT COUNT(id) as total FROM ec_encomendas_lines WHERE sku_family='".$prod_linha['sku_family']."' AND id_cliente='".$userID."' LIMIT 0,1"); 
            $compra_r = cms_fetch_assoc($compra_q);
            if((int)$compra_r['total']>0) continue;
        }
        

        $y = _getProductSimple(5,0,$prod_linha['pid'],0,0,$camp_r['crit_catalogo'],0,1);

        $x = unserialize($y);
        
        $prod = $x['product'];

        if((int)$prod['id']<1) continue;

        if($prod['selected_variant']['inventory_quantity']<1) continue;
        
        if(strpos($prod['selected_variant']['image']['source'], 'no-image') !== false) continue;
        
        if(trim($prod['price']['value'])=='') continue;


        if( !array_key_exists($prod['sku_family'], $produtos) ){
            $produtos[$prod['sku_family']]        = $prod; 
            $produtos_family[$prod['sku_family']] = $prod['sku_family']; 
            $cont_prods++;
        }
    }
         
             
              
    if(count($produtos)<4) return serialize(array("active" => 0, "prods" => ""));
    
            
    if( (int)$preview_ma == 0 ){
    
        cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9250, 1, '2') ON DUPLICATE KEY UPDATE impressoes=impressoes+1 ");
               
               
        trackPageVisit(109250);
        
        foreach($produtos as $k => $v){
            $produtos[$k]['url'] .= '&cpos=9250'; 
        }
    }
              
              
    
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', '../', false, _ROOT.'/temp_twig/');
    
                     
    $x = array(
      	'title'	=> estr(476),
      	'class_title' => $info_class,
      	'arr' 	=> $produtos,
      	'response' => array(
      		'shop' => call_api_func('OBJ_shop_mini'),
          'expressions' => call_api_func('get_expressions',5)
      	)
    );
    
    
    // PRODUCT ITEM SOURCE =============================================================
    $x['response']['layout']['product_item'] = "templates_system/"; 
    
    
    # Opções TEMPLATES_PARAMS api_config e opções do BO - prioridade ao api_config
    get_options_template($x, 'product_item');
             
             
    if(file_exists(_ROOT."/templates/product_item.htm")) {
        $x['response']['layout']['product_item'] = "templates/";
    } elseif($SETTINGS_LOJA['base_3']['campo_12']!='') {
        $x['response']['layout']['product_item'] = "plugins/templates_base/product_item/".$SETTINGS_LOJA['base_3']['campo_12']."/";
    }

    
    if( $return_type == 1 ){
        return serialize(array("active" => 1, "prods" => __encodeArrayToUTF8($x), "prods_qtd" => count($produtos)) );
    }

    
    // RELATED PRODUCTS HTML FILE =======================================================
    $sourceRelatedProducts = "plugins/system/";
    if(file_exists(_ROOT."/templates/system/tpl_related_products.htm")) {
        $sourceRelatedProducts = "templates/system/";
    } elseif(file_exists(_ROOT."/templates_system/system/tpl_related_products.htm")) {
        $sourceRelatedProducts = "templates_system/system/";
    }
    
  
      
    global $exp;
             
    $produtos_html = $fx->printTwigTemplate($sourceRelatedProducts . "tpl_related_products.htm", $x, true, $exp);
           
       
              
    return serialize(array("active" => 1, "prods" => base64_encode($produtos_html), "prods_qtd" => count($produtos)) );
    
}



?>
