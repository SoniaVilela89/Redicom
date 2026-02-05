<?

function _getProductMatrixQtyForUserUnitStore($unit_store_id=null, $int_page_id=null, $product_id=null, $catalog_id=0){

    global $userID, $eComm, $B2B, $B2B_LAYOUT_VERTICAL, $CONFIG_OPTIONS, $LG, $detect;

    if(!stores_units_active_for_user()){
        return serialize(['error_code' => 1, 'error_msg' => 'User without units/stores']);
    }

    if ($int_page_id > 0){
        $page_id       = (int)$int_page_id;
        $product_id    = (int)$product_id;
        $catalog_id    = (int)$catalog_id;
        $unit_store_id = (int)$unit_store_id;
    }else{
        $page_id       = (int)params('page_id');
        $product_id    = (int)params('product_id');
        $catalog_id    = (int)params('catalog_id');
        $unit_store_id = (int)params('unit_store_id');
    }

    if($unit_store_id <= 0){
        return serialize(['error_code' => 1, 'error_msg' => 'User without units/stores']);
    }

    
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if( (int)$catalog_id > 0 ) $_GET['ctg'] = (int)$catalog_id;

    $ids_depositos = $_SESSION['_MARKET']['deposito'];
    if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!=''){
        $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
    } 
    
    $priceList = $_SESSION['_MARKET']['lista_preco'];
    if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
        $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];
    }
    
    $page = call_api_func('get_pagina', $page_id, "_trubricas");
    
    $mercado  = 1;
    if($page["catalogo"]>0){
        $catalogo_id = $page["catalogo"];
        $mercado     = 0;
    }else{
        $catalogo_id = $_SESSION['_MARKET']['catalogo'];
    }
    
    if( $B2B == 1 && isset($_GET['ctg']) && (int)$_GET['ctg'] > 0 ){
        $catalogo_id = (int)$_GET['ctg'];
    }

    # Regras para obter as cores
    $JOIN = '';
    $JOIN_ARRAY = array();
    # 10-09-2019 - no detalhe exibimos todas as cores com base na regra do mercado, as regras da página da listagem são excluídas (PhoneHouse - pag exclusivos)
    #$_query_regras = build_regras($page_id, $JOIN_ARRAY, 0);
    $_query_regras = '';
    if($mercado==1) $_query_regras .= build_regras_mercado($JOIN_ARRAY);
        
    $so_cores_com_stock = 0;
    if(isset($JOIN_ARRAY['STOCK'])){
        unset($JOIN_ARRAY['STOCK']);
        $so_cores_com_stock = 1;
    }

    if(count($JOIN_ARRAY)>0){
        $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
    }

    $resp['shop'] = call_api_func('OBJ_shop_mini');

    $product_info = call_api_func('get_product', $product_id, '', $page_id, 0, 0, 0, 1);
    
    $matriz       = get_product_matriz($product_info, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock, 1);
    
    
    if( (int)$GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 0 ){
            
        foreach($resp['product']['variants'] as $key=>$value) {
            
            if( $value['price']['value'] == 0 ){
                $resp['product']['variants'][$key]['inventory_quantity'] = 0;
                $resp['product']['variants'][$key]['inventory_rule']     = 0;
                $resp['product']['variants'][$key]['inventory_class']    = "inputInventoryGray";
            }
                
        }
        
    } 
    
    
    $product_info['layout_grid'] = 2;  # horizontal - grelha

    if((int)$B2B_LAYOUT_VERTICAL==1 || count($matriz['packs'])>0 || $product_info['price_min']!=$product_info['price_max'] || count($matriz['colunas'])>10){
        $product_info['layout_grid'] = 1;    # vertical
    }
    
    if($CONFIG_OPTIONS['grelha_layout_horizontal'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1){
        $product_info['layout_grid'] = 2;    # horizontal - grelha
    }

     # Se produto unico (uma cor e um tamanho) ou pelos menos um produto é de cotação, então é o layout simples
            if($resp['shop']['b2b_style_version'] == 1 && ( (count($resp['product']["matriz"]['linhas']) == 1 && count($resp['product']["matriz"]['colunas']) == 1) || ($GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 1) ) ){        
                $resp['product']['layout_grid'] = 0;       
            }
        
    if(count($product_info['advanced_configurator'])>0 || count($product_info['dimension'])>0){
        $product_info['layout_grid'] = 3;
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

    
    $product_info['added_to_cart'] = 0;
    $product_info['selected_variant']['discount_levels'] = 0;

    $response = ['product' => &$product_info];
    
    # Obter quantidades adicionadas
    if($mobile || $product_info['layout_grid'] == 0 || $product_info['layout_grid']==1){
        foreach($product_info['variants'] as &$vva){
            $qtd = 0;
            $variants_catalog_id = 0;
            
            if($vva['inventory_quantity']>0 || $vva['inventory_rule']>0){
                if( !isset( $GLOBALS["REGRAS_CATALOGO"] ) ){
                    $qtd = $eComm->getProductQtds($userID, $vva['id'], 0, 0, 0, $unit_store_id);
                }else{
                    $qtd = $eComm->getProductQtds($userID, $vva['id'], 0, 0, $catalogo_id, $unit_store_id);
                    $variants_catalog_id = $catalogo_id;
                }
            }
            
            $vva['quantity_in_cart'] = (int)$qtd;
            $vva['discount_levels']  = product_has_discount_levels($userOriginalID, $vva['sku']);
            if((int)$vva['discount_levels'] == 1){

                if((int)$qtd>0){
                    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductDiscountLevelPrice.php';
                    $price_variant = _getProductDiscountLevelPrice((int)$qtd, $vva['sku'], $variants_catalog_id);
                    $price_variant = unserialize($price_variant);
                    $vva = $price_variant + $vva;
                }

                $response['product']['selected_variant']['discount_levels'] = 1;
            
            }
                    
            if( (int)$CONFIG_OPTIONS['exibir_referencias_substitutas'] == 1 ){  
            
                # PRODUTOS SUBSTITUTOS
                $product_eq = cms_query("SELECT `registos`.`nome".$LG."` as `nome`, `registos`.`id`, `registos`.`sku` FROM `registos_referencias`
                                            JOIN `registos` ON `registos`.`sku`=`registos_referencias`.`sku` AND `registos`.`nome".$LG."`!=''
                                            WHERE `registos_referencias`.`sku_orig`='".$vva['sku']."' AND `registos_referencias`.`sku`!='' AND `registos_referencias`.`tipo`='S'");
                
                while( $product_row = cms_fetch_assoc( $product_eq ) ){
                    
                    $vva['inventory_class'] = 'inputInventoryOrange';
                    
                    $response['produtos_substitutos'][ $vva['sku'] ][] = [
                        'id'   => $product_row['id'],
                        'sku'  => $product_row['sku'],
                        'nome' => $product_row['nome'],
                        'url'  => "/index.php?pid=".$product_row['id']."&id=5"
                    ];
                    
                }
         
            }
            
            if(stores_units_active_for_user() && $qtd>0){
                $resp['product']['added_to_cart'] = 1;
            }
            
        }
        
    }else{
        
        foreach($matriz["artigos_info"] as &$vcores){
        
            foreach($vcores as &$vtam){
                $qtd = 0;
                $matrix_catalog_id = 0;
                
                if($vtam['info']['inventory_quantity']>0 || $vtam['info']['inventory_rule']>0){
                    if( !isset( $GLOBALS["REGRAS_CATALOGO"] ) ){
                        $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, 0, $unit_store_id);
                    }else{
                        $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, $catalogo_id, $unit_store_id);
                        $matrix_catalog_id = $catalogo_id;
                    }
                }
                
                $vtam['info']['quantity_in_cart'] = (int)$qtd;
                $vtam['info']['discount_levels'] = product_has_discount_levels($userOriginalID, $vtam['info']['sku']);
                if((int)$vtam['info']['discount_levels'] == 1){

                    if((int)$qtd>0){
                        require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductDiscountLevelPrice.php';
                        $price_matrix = _getProductDiscountLevelPrice((int)$qtd, $vtam['info']['sku'], $matrix_catalog_id);
                        $price_matrix = unserialize($price_matrix);
                        
                        $vtam['info'] = $price_matrix + $vtam['info'];
                        if(count($matriz['colunas']) == 1){ #If product "Simple" (with Qty box next to the add to cart button) then we need to update the initial product price according to the corresponding level
                            $response['product'] = $price_matrix + $response['product'];
                        }
                    }

                    $response['product']['selected_variant']['discount_levels'] = 1;
                }

                    
                if( (int)$CONFIG_OPTIONS['exibir_referencias_substitutas'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1 ){
                    
                    # PRODUTOS SUBSTITUTOS
                    $product_eq = cms_query("SELECT `registos`.`nome".$LG."` as `nome`, `registos`.`id`, `registos`.`sku` FROM `registos_referencias`
                                            JOIN `registos` ON `registos`.`sku`=`registos_referencias`.`sku` AND `registos`.`nome".$LG."`!=''
                                            WHERE `registos_referencias`.`sku_orig`='".$vva['sku']."' AND `registos_referencias`.`sku`!='' AND `registos_referencias`.`tipo`='S'
                                            LIMIT 0,5");
                                            
                    while( $product_row = cms_fetch_assoc( $product_eq ) ){
                        
                        $resp['produtos_substitutos'][ $vtam['info']['sku'] ][] = [ 
                            'id'   => $product_row['id'],
                            'sku'  => $product_row['sku'],
                            'nome' => $product_row['nome'],
                            'url'  => "/index.php?pid=".$product_row['id']."&id=5" 
                        ];
                        
                    }
             
                }
                
                if(stores_units_active_for_user() && $qtd>0){
                    $resp['product']['added_to_cart'] = 1;
                }
                
            }   
        
        }

        $response['product']['matriz'] = $matriz;

    }
    
    return serialize($response);

}

?>
