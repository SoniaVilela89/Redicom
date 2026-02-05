<?

#Recomendações no passo 1 do checkout

function _getRecommendationCampaignCart($preview_ma=null, $step=0){

    global $eComm, $userID, $LG, $fx, $COUNTRY;

    if( is_null($preview_ma) ){
        $preview_ma     = params('preview_ma');
        $step           = params('step');
    }
    
    $response = array( "campaign" => null );
                                
    $campaign = verifyRecommendationCampaign(60);
    
    if( (int)$preview_ma == 9210 ){
        $campaign = call_api_func('get_line_table', 'ec_campanhas', "id='9210'");
    }
    
                        
    if((int)$campaign['automation']!=60 || $campaign['ofer_perc']<1 ) return serialize($response);
    
    
    
    # 2025-07-14
    if (!empty(trim($campaign['crit_paises']))) {
        $paises = array_map('trim', explode(',', $campaign['crit_paises']));
    
        if (!in_array($COUNTRY['id'], $paises)) {
            return serialize($response);
        }
    }
    
    
    
    # 2025-12-02
    # Para não mostar quando o cliente já tem um produto da campanha no carrinho              
    $linha_camp_q = cms_query("SELECT id FROM `ec_encomendas_lines` WHERE `id_cliente`='".$userID."' AND `tipo_linha`='10' AND `status`='0' AND id_desconto='9210'");
    $linha_camp_n = cms_num_rows($linha_camp_q);
    if($linha_camp_n>0){
        return serialize($response);    
    }
    
          
  
        
    # Carrinho deve ter determinados produtos            
    if($campaign['crit_catalogo']>0){            
        $vals = $eComm->getCatalogoRef($campaign['crit_catalogo']);
            
        $sql = cms_query("SELECT ec.id
                            FROM registos
                                $vals[JOIN]
                                INNER JOIN ec_encomendas_lines ec ON SUBSTRING_INDEX(ec.pid, '|||', -1)=registos.id AND egift=0
                            WHERE registos.activo='1' $vals[query_regras]
                              AND ec.id_cliente='".$userID."'
                              AND ec.status='0'
                              AND ec.oferta='0'
                              AND ec.id_linha_orig<1");
                                                
         if( cms_num_rows($sql)<1 ){
            return serialize($response);   
         }
     }
    
       
    if( (int)$preview_ma == 0 ) cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9210, 1, '1') ON DUPLICATE KEY UPDATE impressoes=impressoes+1");
        
         
         
    $_cacheid   = $CACHE_KEY."MA_PROD_REC_60";
    
    $dados = $fx->_GetCache($_cacheid, 30); #30 min 
    
    $pids = array();
    
    if ($dados!=false && $_GET['nocache']!=1 ){
        $pids = $dados['produtos'];
    }else{
              
              
        $produtos_comprados_s = "SELECT registos.id, registos.sku_family
                                  FROM ec_campanhas_produtos 
                                      INNER JOIN registos ON ec_campanhas_produtos.ref=registos.sku_group
                                      INNER JOIN registos_stocks st ON registos.sku=st.sku AND (st.stock-st.margem_seguranca)>0             
                                  WHERE ec_campanhas_id='".$campaign['id']."'
                                      AND registos.activo=1                                                                          
                                  GROUP BY registos.sku_group
                                  LIMIT 0,20";
                  
        $produtos_comprados_q = cms_query($produtos_comprados_s);       
         
        while($prod_linha = cms_fetch_assoc($produtos_comprados_q)){
            $pids[] = $prod_linha;   
        }  
              
         
        $resp = array();
        $resp['produtos'] = $pids;
          
        $fx->_SetCache($_cacheid, $resp, 30);
        
    }
    
              
       
             
    # Reorganiza de forma random as posições do array
    shuffle($pids);
    
    
    require_once($_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductSimple.php');

     
      
    $products = array();  
    $produtos_family = array();
    
    
    $qtd_produtos = 3;
    if($step==1) $qtd_produtos = 1; 
               
    foreach($pids as $k => $prod_linha){
    

        # Verificar se cliente já comprou alguma vez o produto a recomendar
        /*if(is_numeric($userID)){
            $compra_q = cms_query("SELECT COUNT(id) as total FROM ec_encomendas_lines WHERE sku_family='".$prod_linha['sku_family']."' AND id_cliente='".$userID."' LIMIT 0,1"); 
            $compra_r = cms_fetch_assoc($compra_q);
            if((int)$compra_r['total']>0) continue;
        }*/

        $y = _getProductSimple(5,0,$prod_linha['id'],0);

        $x = unserialize($y);
        
 
        
        $prod = $x['product'];

        if((int)$prod['id']<1) continue;

        if($prod['selected_variant']['inventory_quantity']<1) continue;
        
        if(strpos($prod['selected_variant']['image']['source'], 'no-image') !== false) continue;
        
        if(trim($prod['price']['value'])=='') continue;
        
        $prod['previous_price']['value']            = $prod['price_min']['value'];         
        $prod['previous_price']['value_original']   = $prod['price_min']['value_original'];            

        $prod['price_discount_value']['value']            = number_format(($prod['previous_price']['value']*$campaign['ofer_perc'])/100, 2);
        $prod['price_discount_value']['value_original']   = number_format(($prod['previous_price']['value']*$campaign['ofer_perc'])/100, 2);
                                                            
        
        $prod['price']['value']                     = $prod['previous_price']['value_original']-$prod['price_discount_value']['value_original']; 
        $prod['price']['value_original']            = $prod['previous_price']['value_original']-$prod['price_discount_value']['value_original'];  
        
        
        $prod['price_min']['value']                 = $prod['price']['value']; 
        $prod['price_min']['value_original']        = $prod['price']['value_original'];  
        
        $prod['price_max']['value']                 = $prod['price']['value']; 
        $prod['price_max']['value_original']        = $prod['price']['value_original'];  
              
        $prod['price_discount']                     = number_format($campaign['ofer_perc']).'%';
        
        
        foreach ($prod['variants'] as $key => $value) {        

            $prod['variants'][$key]['previous_price']['value']            = $value['price']['value'];         
            $prod['variants'][$key]['previous_price']['value_original']   = $value['price']['value_original'];            
    
            $prod['variants'][$key]['price_discount_value']['value']            = number_format(($prod['variants'][$key]['previous_price']['value']*$campaign['ofer_perc'])/100, 2);
            $prod['variants'][$key]['price_discount_value']['value_original']   = number_format(($prod['variants'][$key]['previous_price']['value']*$campaign['ofer_perc'])/100, 2);
                                                                
            
            $prod['variants'][$key]['price']['value']                     = $prod['variants'][$key]['previous_price']['value_original']-$prod['variants'][$key]['price_discount_value']['value_original']; 
            $prod['variants'][$key]['price']['value_original']            = $prod['variants'][$key]['previous_price']['value_original']-$prod['variants'][$key]['price_discount_value']['value_original'];  
            
            $prod['variants'][$key]['price_discount']                     = number_format($campaign['ofer_perc']).'%';  
                       	
        }   
        
        

        $products[] = $prod; 
        
        $produtos_family[$prod['sku_group']] = $prod['sku_group']; 

        if(count($products)==$qtd_produtos) break;
    }
    
      
    
    if( count($products)<$qtd_produtos) return serialize($response); 
            
    

    if( (int)$preview_ma == 0 ){
    
        cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9210, 1, '2') ON DUPLICATE KEY UPDATE impressoes=impressoes+1 ");
 
        trackPageVisit(109210);
         
        foreach($produtos_family as $k => $v){
            $_SESSION['MA_CARTSUG'][$v] = $v;
        }
    }
    
    
    
    $s_tams = "SELECT GROUP_CONCAT(DISTINCT tamanho SEPARATOR '||') as tams FROM ec_encomendas_lines WHERE id_cliente='".$userID."' AND status='0' AND id_linha_orig<1";
    $q_tams = cms_query($s_tams);
    $r_tams = cms_fetch_assoc($q_tams);  
    
    
    
    
    $response['campaign'] = [
        "id"            => $campaign['id'],
        "steps"         => $campaign['ofer_tipo'],
        "title"         => $campaign['email_tit'.$LG],
        "products"      => $products,
        "font_color"    => $campaign['automation_color'],
        "font_bg"       => $campaign['automation_bg'],
        "tams"          => explode('||', $r_tams['tams'])
    ];

    
    
    return serialize($response);

}
