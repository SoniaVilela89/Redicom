<?

#Recomendações no mini-carrinho

function _getRecommendationCampaign($pid=null, $preview_ma=null, $only_portes=0)
{

    global $eComm, $MARKET, $userID, $COUNTRY, $CACHE_KEY, $fx, $MA_IGNORAR_TAMANHOS, $MA_IGNORAR_SUGESTOES_SUBFAMILIA, $MA_MINICARRINHO_C_PRODS_RELACIONADOS, $MOEDA, $DISPLAY_ADVANCED_CONFIGURATOR;
    
    if(is_null($pid)){
        $pid = params('pid');
        $preview_ma = params('preview_ma');
        $only_portes = params('only_portes');
    }

    $arr_resp = array(
        "active" => 0,
        "prods"  => array(),
        "active_prods_shipping" => 0,
        "prods_shipping" => array(),
    );
                           
    $camp_r = verifyRecommendationCampaign(50);
    if((int)$preview_ma==9200) {
        $camp_r = call_api_func('get_line_table', 'ec_campanhas', "id='9200'");
    }
                    
    if((int)$camp_r['automation']!=50) return serialize($arr_resp);
    
    
    # 2025-07-14
    if (!empty(trim($camp_r['crit_paises']))) {
        $paises = array_map('trim', explode(',', $camp_r['crit_paises']));
    
        if (!in_array($COUNTRY['id'], $paises)) {
            return serialize($arr_resp);
        }
    }
    
    
    if($only_portes == 0){
        
        #2022-12-26 
        if( (int)$preview_ma == 0 ) cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9200, 1, '1') ON DUPLICATE KEY UPDATE impressoes=impressoes+1");
        

        
            
        $base_prod = call_api_func('get_line_table', 'registos', "id='".$pid."'"); 
        
        $base_size = call_api_func('get_line_table', 'registos_tamanhos', "id='".$base_prod['tamanho']."'"); 
        
        $subfamilia = call_api_func('get_line_table', 'registos_subfamilias', "id='".$base_prod['subfamilia']."'"); 
            
        
        $subfamilia['ma_campanha_9200'] = ltrim($subfamilia['ma_campanha_9200'], ',');
    
    
        $pids = array();
        
        # 2023-03
        # FerramentasPro
        # Obter os produtos do mini carrinho através dos produtos relacioandos da ficha do produto
        if($MA_MINICARRINHO_C_PRODS_RELACIONADOS==1){

            $see_also = explode(',', ltrim($base_prod['see_also'], ','));    
        
            $outros_produtos_s = "SELECT sku_family, id as pid FROM registos WHERE sku  IN ('".implode("', '", $see_also)."') ";
                                                                                                                                                                                                                        
            $outros_produtos_q = cms_query($outros_produtos_s);       
            
            while($prod_linha = cms_fetch_assoc($outros_produtos_q)){
                    
                if( !array_key_exists($prod_linha['sku_family'], $pids) ){
                    $prod_linha['ignorar_compra'] = 1;
                    $pids[$prod_linha['sku_family']]= $prod_linha; 
                }
                
            }       
        
        }elseif((int)$MA_IGNORAR_SUGESTOES_SUBFAMILIA==0 && trim($subfamilia['ma_campanha_9200'])!=''){            

            # 2021-
            # Prof
            # Se para a subfamilia do produto adicionado ao carrinho, existir no BO produtos configurados na subfmailia sugerimos esses mesmos produtos
        
            $pids_ma = explode(',', $subfamilia['ma_campanha_9200']);
        
            $outros_produtos_s = "SELECT sku_family, id as pid FROM registos WHERE sku_group IN ('".implode("', '", $pids_ma)."') ";
                                                                                                                                                                                                                                            
            $outros_produtos_q = cms_query($outros_produtos_s);       

            while($prod_linha = cms_fetch_assoc($outros_produtos_q)){
                    
                if( !array_key_exists($prod_linha['sku_family'], $pids) ){
                
                    $prod_linha['ignorar_compra'] = 1;
                    $pids[$prod_linha['sku_family']]= $prod_linha; 
                }
                
            }    
        }
            
        
        if(count($pids)<3){
        
            $_cacheid   = $CACHE_KEY."MA_PROD_REC_50_".$base_prod['sku_family'];
                                            
            $dados = $fx->_GetCache($_cacheid, 120); #2 horas 
                                                
                            
            if ($dados!=false && $_GET['nocache']!=1 ){
                $pids = $dados['produtos'];
            }else{
            
                $menos_3mes = date("Y-m-d", strtotime("-3 months"));
                
                #Obter clientes que tenham comprado o mesmo produto nos últimos 3 meses
                $outros_clientes_s = "SELECT GROUP_CONCAT(DISTINCT(id_cliente)) as id_cliente
                                        FROM ec_encomendas_lines e
                                        WHERE e.id_cliente>0   
                                            AND e.data>'$menos_3mes'
                                            AND e.sku_family='".$base_prod['sku_family']."' ";
                                    
                        
                $outros_clientes_q = cms_query($outros_clientes_s);
                $outros_clientes_r = cms_fetch_assoc($outros_clientes_q);    
                
            
                if(trim($outros_clientes_r['id_cliente'])=='') return serialize($arr_resp);
        
                
                $more_where = "";
                if((int)$camp_r['crit_prods_brand']==1) $more_where .= " AND r.marca='".$base_prod['marca']."' ";
                if((int)$camp_r['crit_prods_gender']==1) $more_where .= " AND r.genero='".$base_prod['genero']."' ";
                
                
                $clientes = explode(',', $outros_clientes_r['id_cliente']);
                
                # Obtem os últimos produtos que os clientes secundários tenham comprado + que 1 vez no total
                $outros_produtos_s = "SELECT COUNT(e.sku_family) as total, e.sku_family, r.id as pid, EXTRACT( YEAR_MONTH FROM e.data ) as data_f  
                                        FROM ec_encomendas_lines e 
                                            INNER JOIN registos r ON SUBSTRING_INDEX(e.pid, '|||', -1)=r.id AND r.activo=1 
                                        WHERE e.id_cliente IN ('".implode("', '", $clientes)."')
                                        AND e.sku_family!='".$base_prod['sku_family']."'
                                        AND e.ref!='PORTES' 
                                        AND e.data>'$menos_3mes'
                                        AND e.id_linha_orig<1
                                        $more_where
                                        GROUP BY e.sku_family, EXTRACT( YEAR_MONTH  FROM e.data ) 
                                        HAVING COUNT(e.sku_family)>0 
                                        ORDER BY data_f desc, total desc
                                        LIMIT 0,50";
                                                        
                                                                                                                                                                                                                    
                $outros_produtos_q = cms_query($outros_produtos_s);       
        
                while($prod_linha = cms_fetch_assoc($outros_produtos_q)){
                        
                    if( !array_key_exists($prod_linha['sku_family'], $pids) ){
                        
                        $prod_linha['ignorar_compra'] = 0;
                        $pids[$prod_linha['sku_family']]= $prod_linha; 
                    }
                    
                }
                
                    
                $resp = array();
                $resp['produtos'] = $pids;
                    
                $fx->_SetCache($_cacheid, $resp, 120);
                
            }        
        }   
        
        # Reorganiza de forma random as posições do array
        shuffle($pids);
                        
        
                
        require_once($_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductSimple.php');
        
        $cont_prods       = 0;
        
        $produtos         = array();
        $produtos_family  = array();        


        foreach($pids as $k => $prod_linha){
        
            if($cont_prods>=3) break;
            
            # Verificar se cliente já comprou alguma vez o produto a recomendar
            if(is_numeric($userID) && (int)$prod_linha['ignorar_compra']==0){
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

            #Valida se existe o tamanho que foi adicionado ao carrinho
            if(count($prod['variants'])>0){
                
                $flag = false;
                
                foreach ($prod['variants'] as $key=>$value) {
                
                    if((int)$MA_IGNORAR_TAMANHOS==0 && (int)$prod_linha['ignorar_compra']==0 && $value['size_code'] != $base_size['codigo']) continue;
                
                    if(empty(trim($value['price']['value']))) continue;
                    
                    if($value['inventory_quantity']<1) continue;
                    
                    $flag = true;
                    break;
                    
                }
                
                if(!$flag) continue;
            }

            if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
                $prod['product_configurator'] = check_product_advanced_configurator($prod['sku_group']);
            }

            if( !array_key_exists($prod['sku_group'], $produtos) ){
                $produtos[$prod['sku_group']]        = $prod; 
                $produtos_family[$prod['sku_family']] = $prod['sku_family']; 
                $cont_prods++;
            }
        
        }
    }
        
    if(count($produtos) == 0 && $only_portes == 0) return serialize($arr_resp);
        
    $active_prods_shipping = 0;
    $perc_shipping = 100;
    $prods_shipping = array();
    $exp_shipping = "";

    if((int)$camp_r['inc_prods_portes_gratis'] == 1){

        $BASKET = $eComm->buildBasketInformation($userID, $MARKET, 0);
        $shipping = get_shipping_info( $BASKET['SUBTOTAL'], (int)$BASKET['QTDS'], $more_value_free_portes, $valor_portes);

        if($more_value_free_portes > 0){
            
            require_once($_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductSimple.php');

            $more_where = "";
            if((int)$camp_r['crit_prods_brand'] == 1) $more_where .= " AND r.marca='".$base_prod['marca']."' ";
            if((int)$camp_r['crit_prods_gender'] == 1) $more_where .= " AND r.genero='".$base_prod['genero']."' ";
            
            $sql_prod = "SELECT r.id, r.sku_family FROM registos r
                            INNER JOIN registos_precos rp ON r.sku=rp.sku 
                                AND rp.idListaPreco='".$_SESSION['_MARKET']['lista_preco']."'
                                AND rp.preco>='".$more_value_free_portes."' AND rp.preco<='".($more_value_free_portes+10)."'
                        WHERE r.activo=1 $more_where
                        GROUP BY r.sku_family";
            $res_prod = cms_query($sql_prod);       

            $cont_prods       = 0;

            while($row_prod = cms_fetch_assoc($res_prod)){

                if($cont_prods >= 3) break;

                # Verificar se cliente já comprou alguma vez o produto a recomendar
                if(is_numeric($userID) ){
                    $compra_q = cms_query("SELECT COUNT(id) as total FROM ec_encomendas_lines WHERE sku_family='".$row_prod['sku_family']."' AND id_cliente='".$userID."' LIMIT 0,1"); 
                    $compra_r = cms_fetch_assoc($compra_q);
                    if((int)$compra_r['total'] > 0) continue;
                }

    
                $y = _getProductSimple(5,0,$row_prod['id'],0,0,$camp_r['crit_catalogo'],0,1);
                $x = unserialize($y);

                $prod = $x['product'];

                if((int)$prod['id'] < 1) continue;

                if($prod['selected_variant']['inventory_quantity'] < 1) continue;
                
                if(strpos($prod['selected_variant']['image']['source'], 'no-image') !== false) continue;
                
                if(trim($prod['price']['value'])=='') continue;
                    
                #Valida se existe o tamanho que foi adicionado ao carrinho
                if(count($prod['variants'])>0){
                    
                    $flag = false;
                    
                    foreach ($prod['variants'] as $key=>$value) {
                    
                        if(empty(trim($value['price']['value']))) continue;
                        
                        if($value['inventory_quantity']<1) continue;
                        
                        $flag = true;
                        break;
                        
                    }
                    
                    if(!$flag) continue;
                }

                if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
                    $prod['product_configurator'] = check_product_advanced_configurator($prod['sku_group']);
                }

                if( !array_key_exists($prod['sku_group'], $prods_shipping) ){
                    $prods_shipping[$prod['sku_group']]   = $prod; 
                    $produtos_family[$prod['sku_family']] = $prod['sku_family']; 
                    $cont_prods++;
                }

            }

        }

        if(count($prods_shipping) > 0){
            $perc_shipping = (($valor_portes - $more_value_free_portes) *100)/$valor_portes;
            $exps = call_api_func("get_expressions");
            $more_value_free_portes_final = $MOEDA['prefixo'].number_format($more_value_free_portes, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares'] ).$MOEDA['sufixo'];
            $exp_shipping = str_replace("{VALUE}", $more_value_free_portes_final, $exps["823"]);

            $active_prods_shipping = 1;
        }

    }
    
            
    if((count($produtos) == 0 && $only_portes == 0) && count($prods_shipping) == 0) return serialize($arr_resp);
     
         
    if( (int)$preview_ma == 0 ){
    
        cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9200, 1, '2') ON DUPLICATE KEY UPDATE impressoes=impressoes+1 ");
            
        trackPageVisit(109200); 
        
        foreach($produtos_family as $k => $v){
            $_SESSION['MA_CARTREC'][$v] = $v;
        }
    
    }
       
    $resp = array(
        "active"                    => 1, 
        "prods"                     => $produtos,
        "active_prods_shipping"     => $active_prods_shipping,
        "prods_shipping"            => $prods_shipping,
        "title_shipping"            => $exp_shipping,
        "perc_shipping"             => $perc_shipping
    );
    
    return serialize($resp);

}


?>
