<?

function _getProductSimple($page_id=0, $category_id=0, $product_id=0, $account=1, $is_detail=0, $catalog_id=0, $configurator_id=0, $mini=0){

    global $userID, $eComm, $LG, $fx, $B2B, $COUNTRY, $MARKET, $MOEDA, $CONFIG_RELACIONADOS_QTD, $B2B, 
        $B2B_LAYOUT_VERTICAL, $CONFIG_TEMPLATES_PARAMS, $CACHE_KEY, $DISPLAY_ADVANCED_CONFIGURATOR, $CONFIG_OPTIONS, $detect, $EXIBIR_TODOS_AS_CORES;
       
    if ($product_id > 0){         
        $page_id      = (int)$page_id;
        $cat          = (int)$category_id;
        $product_id   = (int)$product_id;         
        $is_detail    = (int)$is_detail;
        $catalog_id   = (int)$catalog_id;           
        $mini         = (int)$mini;           
    }else{            
        $page_id      = (int)params('page_id');
        $cat          = (int)params('category_id');
        $product_id   = (int)params('product_id');         
        $is_detail    = (int)params('is_detail');     
        $catalog_id   = (int)params('catalog_id');     
        $mini         = (int)params('mini');     
    }



    # 2025-05-19    
    # Comentado porque com esta regra dentro get_product entra na primeria condição do if e não usa o catalogo com regras
    # quero que entre na 3' regra para respeitar o catalogo - MA; recomemendações  
    #if( (int)$catalog_id > 0 ) $_GET['ctg'] = (int)$catalog_id;
    
    

    $resp = array();
    
    if( (int)$product_id < 1 ) return serialize($resp);
    
    $acc = 1;
    if(isset($account)) $acc = $account;
    
    
             
    if((int)$configurator_id > 0) $_GET["cg"] = $configurator_id;
    
              

    #2024-01-09
    # o 1 para o without_stock foi forçado porque um produto sem stock em que o catalogo do mercado nao permite produtos sem stock não é bem selecionado na seect_variant edepois o preço vai de um irmão
    $resp['product'] = call_api_func('get_product',$product_id,'',$page_id, $is_detail, $acc, $catalog_id, 1);
 
    $resp['product']['wishlist'] = 0;
 
 
 
    if($mini==0){
        
        $resp['shop'] = call_api_func('OBJ_shop_mini');

        if((int)$B2B>0){
            
            $resp['product']['size_guide_all'] = array();
            if((int)$resp['product']['size_guide_id']>0){
    
                include_once(_ROOT."/api/controllers/_getSizeGuide.php");
    
                $size_guide_all = _getSizeGuide($resp['product']['size_guide_id'], 0, 1);
                $resp['product']['size_guide_all'] = unserialize($size_guide_all);
    
            }
    
            $ids_depositos = $_SESSION['_MARKET']['deposito'];                        
            if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!=''){
                $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
            } 
            
            $priceList = $_SESSION['_MARKET']['lista_preco'];  
            if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
                $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
            } 
            
            global $page;
                        
            # 2025-02-06
            # Por causa das excepçoes definidas no BO    
            if($page_id==36){
                $page = call_api_func('get_pagina_modulos', $page_id, "_trubricas");     
            }else{
                $page = call_api_func('get_pagina', $page_id, "_trubricas");    
            }
        
            
            $mercado  = 1;
                        
            if($page["catalogo"]>0){
                $catalogo_id = $page["catalogo"];
                $mercado=0;
            }else{
                $catalogo_id = $_SESSION['_MARKET']['catalogo'];
            }  
            
            if( $B2B == 1 && isset($_GET['ctg']) && (int)$_GET['ctg'] > 0 ){
                $catalogo_id = (int)$_GET['ctg'];
            }


             preparar_regras_carrinho($catalogo_id);

            if($catalogo_id != (int)$GLOBALS["REGRAS_CATALOGO"]) $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        
                
            $resp['id_catalog'] = $catalogo_id;
            
            # Regras para obter as cores     
            $JOIN = '';
            $JOIN_ARRAY = array();
            # 10-09-2019 - no detalhe exibimos todas as cores com base na regra do mercado, as regras da página da listagem são excluídas (PhoneHouse - pag exclusivos)
            #$_query_regras = build_regras($page_id, $JOIN_ARRAY, 0);
            $_query_regras = '';         
            
            
            if((int)$EXIBIR_TODOS_AS_CORES==0){
                $_query_regras .= build_regras(0, $JOIN_ARRAY, $catalogo_id);
            }else{
                $_query_regras .= build_regras(0, $JOIN_ARRAY, $catalogo_id, 1);
            }
        
                                                            
            if($mercado==1) {
                if((int)$EXIBIR_TODOS_AS_CORES==0){
                    $_query_regras .= build_regras_mercado($JOIN_ARRAY);
                }else{
                    $_query_regras .= build_regras_mercado($JOIN_ARRAY, 1);
                }
            }
                
            $so_cores_com_stock = 0;                            
            if(isset($JOIN_ARRAY['STOCK'])){                
                unset($JOIN_ARRAY['STOCK']);
                $so_cores_com_stock = 1;
            }
                
            if(count($JOIN_ARRAY)>0){
                $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
            }    
               
               
            if( (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 && $page['sublevel'] == 60 ){
    
                # Remove os tamanhos diferentes do SKU pesquisado
                foreach( $resp['product']['variants'] as $key => $variant ){
                    if( $variant['id'] != $resp['product']['id'] ){
                        unset($resp['product']['variants'][$key]);
                    }
                }
    
                # Remove as cores diferentes do SKU pesquisado
                foreach( $resp['product']['available_colors'] as $key => $color ){
                    if( $color['color_id'] != $resp['product']['selected_variant']['color']['color_id'] && $color['color_id'] ){
                        unset($resp['product']['available_colors'][$key]);
                    }
                }
    
            }
            
            $matriz = get_product_matriz($resp['product'], $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock, $is_detail);
                
            $resp['product']["matriz"] = $matriz;  
            
            if( (int)$GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 0 ){
            
                foreach($resp['product']['variants'] as $key=>$value) {
                    
                    if( $value['price']['value'] == 0 ){
                        $resp['product']['variants'][$key]['inventory_quantity'] = 0;
                        $resp['product']['variants'][$key]['inventory_rule']     = 0;
                        $resp['product']['variants'][$key]['inventory_class']    = "inputInventoryGray";
                    }
                        
                }
                
            }     
           
            $resp['product']['layout_grid'] = 2; # horizontal - grelha
            if(count($resp['product']["matriz"]['packs'])>0 || $resp['product']['price_min']!=$resp['product']['price_max'] || count($resp['product']["matriz"]['colunas'])>10){
                $resp['product']['layout_grid'] = 1;    # vertical
            }  
            
            if( $CONFIG_OPTIONS['grelha_layout_horizontal'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1 ){
                $resp['product']['layout_grid'] = 2;    # horizontal - grelha
            }
            
            $resp['product']['warehouse_availability'] = []; #by default no warehouse will be shown
            if( count($matriz['colunas']) == 1 && count($matriz['linhas']) == 1 && (int)$resp['product']['uncataloged_stock'] == 0 ){
                $resp['product']['warehouse_availability'] = check_warehouses_stock($ids_depositos, $resp['product']['sku'], $_SESSION['_MARKET']);
            }
            
            if((int)$B2B_LAYOUT_VERTICAL==1){
                $resp['product']['layout_grid'] = 1;    # vertical    
            }
            
            # Se produto unico (uma cor e um tamanho) ou pelos menos um produto é de cotação, então é o layout simples
            if($resp['shop']['b2b_style_version'] == 1 && ( (count($resp['product']["matriz"]['linhas']) == 1 && count($resp['product']["matriz"]['colunas']) == 1) || ($GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 1) ) ){        
                $resp['product']['layout_grid'] = 0;       
            }

            if(count($resp["product"]['advanced_configurator'])>0 || count($resp['product']['dimension'])>0){
                $resp['product']['layout_grid'] = 3; 
            }
            
            
            $mobile = false;
            if(file_exists('api/lib/class.mobile_detect.php') && !is_null($detect)){
                    
                #Sonia - 11/10 - definido por Serafim que em tablet os espaçamentos são os de mobile
                if($detect->isMobile() || $detect->isTablet()){    
                    $mobile = true;
                }
    
            }else{
                if( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'],'Android') ){
                    $mobile = true;
                }
            }
                                
            
            $resp['product']['added_to_cart'] = 0;
            $resp['product']['selected_variant']['discount_levels'] = 0;
            $unit_stores_active_for_user = stores_units_active_for_user();
            $user_default_unit_store_id  = 0;
    
            if($unit_stores_active_for_user){
                $user_default_unit_store_info = get_user_default_unit_store_info();
                if(!empty($user_default_unit_store_info)){
                    $user_default_unit_store_id = $user_default_unit_store_info['id'];
                    unset($user_default_unit_store_info);
                }
            }
            
            if(!isset($prod)) $prod = call_api_func("get_line_table","registos", "id='".$product_id."'");
            
            $last_used_store = end($_SESSION['EC_USER']['last_used_stores_units']);
            $user_store_temp = ($last_used_store > 0) ? $last_used_store : $user_default_unit_store_id;
            
            # Obter quantidades adicionadas
            if($mobile || $resp['product']['layout_grid'] == 0 || $resp['product']['layout_grid'] == 1 || $resp['product']['layout_grid'] == 3){
            
                $resp['user_units_stores'] = get_user_stores_units_from_session($_SESSION['EC_USER']['id'], $catalogo_id, '', $resp['product']['sku_group']);
                  
                foreach($resp['product']['variants'] as &$vva){
                    loop_variants_b2b($resp, $vva);  
                }
                      
                foreach($resp['product']['dimentions'] as &$vva){                
                    loop_variants_b2b($resp, $vva);  
                }
                      
                foreach($resp['product']['dimension_secondary'] as &$vva){                
                    loop_variants_b2b($resp, $vva);  
                }
    
            }else{
               
                $resp['user_units_stores'] = get_user_stores_units_from_session($_SESSION['EC_USER']['id'], $catalogo_id, $resp['product']['sku_family'], '');
               
                foreach($resp['product']["matriz"]["artigos_info"] as $kcores => $vcores){
                
                    foreach($vcores as $ktam => $vtam){
                        $qtd = 0;
                        $resp['product']["matriz"]["artigos_info"][$kcores][$ktam]['info']['added_to_cart'] = 0;                  
                      
                        if((int)$vtam['info']['package_price_auto']==1 && (int)$vtam['info']['units_in_package']>1 && $vtam['info']['inventory_quantity']>0 && $vtam['info']['inventory_quantity']!=99999){
                            $vtam['info']['inventory_quantity'] /= (int)$vtam['info']['units_in_package'];
                        }
                      
                        if($vtam['info']['inventory_quantity']>0 || $vtam['info']['inventory_rule']>0){
                            $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, $catalogo_id);
                        }
                        
                        if($qtd>0){
                            $resp['product']['added_to_cart'] = 1;
                            $resp['product']["matriz"]["artigos_info"][$kcores][$ktam]['info']['added_to_cart'] = 1;                    
                        }
                        
                        if($unit_stores_active_for_user && $qtd>0){
                            #Replace total quantity by the quantity added for the default unit store in case then last used store is not defined - $user_store_temp
                            $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, $matrix_catalog_id, $user_store_temp);
                        }
                        
                        if($vtam['info']['package_price_auto']==1 && (int)$vtam['info']['units_in_package']>1) $qtd /= (int)$vtam['info']['units_in_package'];
                        
                        $resp['product']["matriz"]["artigos_info"][$kcores][$ktam]['info']['quantity_in_cart'] = (int)$qtd;
                        
                        if( (int)$CONFIG_OPTIONS['exibir_referencias_substitutas'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1 ){
                            
                          ############ PRODUTOS SUBSTITUTOS ############
                           $product_eq = cms_query("SELECT `registos`.`nome".$LG."` as `nome`, `registos`.`id`, `registos`.`sku` FROM `registos_referencias`
                                                        JOIN `registos` ON `registos`.`sku`=`registos_referencias`.`sku` AND `registos`.`nome".$LG."`!=''
                                                        WHERE `registos_referencias`.`sku_orig`='".$vtam['info']['sku']."' AND `registos_referencias`.`sku`!='' AND `registos_referencias`.`tipo`='S'
                                                        LIMIT 0,5");
                                                        
                            while( $product_row = cms_fetch_assoc( $product_eq ) ){
                                
                                $resp['produtos_substitutos'][ $vtam['info']['sku'] ][] = [ 'id' => $product_row['id'],
                                                                                            'sku' => $product_row['sku'],
                                                                                            'nome' => $product_row['nome'],
                                                                                            'url' => "/index.php?pid=".$product_row['id']."&id=5" ];
                                
                            }
                            ############ PRODUTOS SUBSTITUTOS ############
                     
                        }
                                                    
                    }   
                
                }
    
            }

            # B2B - Produtos equivalentes
            $products_to_search[$resp['product']['sku']] = $resp['product']['sku'];
            if( (int)$CONFIG_OPTIONS['exibir_referencias_equivalentes'] == 1 && !empty( $products_to_search ) ){
                $resp['equivalent_products'] = get_equivalent_products($products_to_search, $page["catalogo"]);
            }
                  
        }
                                       
    }
    
    
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){
        $resp['product']['review_product']  = call_api_func('get_reviews_product', $resp['product']['sku_family'], $resp['product']['selected_variant']['color']['color_id']);
    }
    
    if(count($_SESSION['EC_USER']['deposito_express']['depositos'])>0){
        get_tags_express($resp['product']);              
    }
    
    if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
        $resp['product']['advanced_configurator'] = get_product_advanced_configurator($resp['product']['sku']);
    }
    
    if( (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 && $page['sublevel'] == 60 ){
        # Forçado para que a remoção das linhas seja só feita ao SKU
        $resp['product']['sku_family'] = $resp['product']['sku'];
    }

    
    
    $refs_wishlist = call_api_func('get_refs_wishlist');

    if(trim($refs_wishlist["sku_family"])!="" || trim($refs_wishlist["refs"])!=""){
    
        $arr_skus_wishlist = explode(",",$refs_wishlist["refs"]);
        $arr_sku_family_wishlist = explode(",",$refs_wishlist["sku_family"]);        
        
        $resp['product']['wishlist'] = in_array($resp['product']['sku_family'], $arr_sku_family_wishlist)? 1:0;    
        
        foreach($resp['product']['variants'] as $k => $v){
            $resp['product']['variants'][$k]["wishlist"] = in_array($v['sku'], $arr_skus_wishlist)? 1:0;
        }         

        $resp['product']['selected_variant']["wishlist"] = in_array($resp['product']['selected_variant']['sku'], $arr_skus_wishlist)? 1:0;
        
        foreach($resp['product']['dimension'] as $k => $v){
            $resp['product']['dimension'][$k]["wishlist"] = in_array($v['sku'], $arr_skus_wishlist)? 1:0;
        }       
    
    }
 
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0) {
        $resp['product']['add_comparator']  = call_api_func('verify_product_add_comparator',$resp['product']['sku_family']);
        if(count($resp['product']['composition'])>0){
            $resp['product']['comparator'] = 1;
        }
    }
        
        
    
    if(is_callable('custom_controller_product_simple')) {
        call_user_func_array('custom_controller_product_simple', array(&$resp));
    }
    
    return serialize($resp);

}

?>
