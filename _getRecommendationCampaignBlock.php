<?

function _getRecommendationCampaignBlock()
{

    global $userID, $COUNTRY, $CACHE_KEY, $fx, $SETTINGS_LOJA, $LG;
    
    
    $num_prods = $_POST['num_prods'];
    
    if($num_prods<4) $num_prods = 4;
    

    $camp_r = verifyRecommendationCampaign(80);
    if((int)$_POST['preview-ma']==9280) {
        $camp_r = call_api_func('get_line_table', 'ec_campanhas', "id='9280'");
    }

    if((int)$camp_r['automation']!=80 ) return serialize(array("active" => 0, "prods" => ""));
    
                                  
    $PROMOS = getAvailablePromoForUser();    
    
    $logado_user = $userID;  
    if( !is_numeric($userID) ){
        $logado_user = -99;  
    } 


    $_cacheid   = $CACHE_KEY."MA_PROD_REC_80_".$logado_user."_".$LG."_".$_SESSION['_MARKET']['lista_preco']."_".$_SESSION['_COUNTRY']['id']."_".implode(',', $PROMOS["promos"])."_".$_SESSION["segmentos"];
     
    $dados = $fx->_GetCache($_cacheid, 1440); #2 horas 

    
    $produtos = array();
    
    if ($dados!=false && $_GET['nocache']!=1 ){
        
        $resp = unserialize($dados);
        $produtos = $resp['produtos'];
               
    }else{
                           
        require_once($_SERVER['DOCUMENT_ROOT'].'/plugins/ma/functions/functions_ma4.php');
        
        $cliente = array();
        $cliente['id'] = $userID;
        $cliente['ultima_encomenda_offline_skus'] = '';
        $cliente['ultima_encomenda_offline']      = '0000-00-00';
        
        if(is_numeric($cliente['id'])){
            $user = call_api_func('get_line_table', '_tusers', "id='".$cliente['id']."'");
            
            $cliente['ultima_encomenda_offline_skus'] = $user['ultima_encomenda_offline_skus'];
            $cliente['ultima_encomenda_offline']      = $user['ultima_encomenda_offline'];
        }
        
        
        $camp_r['automation_prods'] = $num_prods;
        
        
        $produtos = _ma_get_produtos_recomendados($camp_r, $cliente, 0, 1);
        
        foreach($produtos as $k => $v){
            $produtos[$k]['url'] .= '&cpos=9280'; 
        }  

        
        $resp = array();
        $resp['produtos'] = $produtos;
    
        $fx->_SetCache($_cacheid, serialize($resp), 1440);
    }
    
    
    # Reorganiza de forma random as posições do array
    shuffle($produtos);

    $total_prods = count($produtos);

    if($total_prods>$num_prods) {
        $produtos_t = array_slice($produtos, 0, $num_prods);
        $produtos = $produtos_t;
        $total_prods = $num_prods;
    }  
    
        
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', '../', false, _ROOT.'/temp_twig/');
    
                     
    $x = array(
      	'title'	=> $camp_r['titulo'.$LG],
      	'arr' 	=> $produtos,
      	'response' => array(
      		'shop' => call_api_func('OBJ_shop_mini'),
          'expressions' => call_api_func('get_expressions',5)
      	)
    );
    
    
    // PRODUCT ITEM SOURCE =============================================================
    $x['response']['layout']['product_item'] = "templates_system/"; 
    
    $x['response']['shop']['TEMPLATES_PARAMS']['relacionados_qtd'] = $total_prods; 

             
             
    if(file_exists(_ROOT."/templates/product_item.htm")) {
        $x['response']['layout']['product_item'] = "templates/";
    } elseif($SETTINGS_LOJA['base_3']['campo_12']!='') {
        $x['response']['layout']['product_item'] = "plugins/templates_base/product_item/".$SETTINGS_LOJA['base_3']['campo_12']."/";
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
    
    
                  
    return serialize(array("prods" => base64_encode($produtos_html), "prods_qtd" => $total_prods) );
    
}



?>
