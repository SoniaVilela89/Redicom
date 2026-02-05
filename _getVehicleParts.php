<?

function _getVehicleParts($vehicle_id=0, $level1_value=0, $level2_value=0, $quantity=0, $offset=0, $image_id=0, $page_id=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id   = params('vehicle_id');
        $level1_value = params('level1_value');
        $level2_value = params('level2_value');
        $quantity     = params('quantity');
        $offset       = params('offset');
        $image_id     = (int)params('image_id');
    }
    
    if( $vehicle_id <= 0 || $quantity <= 0 || $offset < 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
        return serialize(['success' => false]);
    }

    $more_where = "";
    $more_where_join = "";
    if( $level1_value > 0 && $level2_value > 0 ){
        $more_where = "AND `nivel1_valor`=".$level1_value." AND `nivel2_valor`=".$level2_value;
        $more_where_join = "AND `pecas_imagens_veiculos`.`nivel1_valor`=".$level1_value." AND `pecas_imagens_veiculos`.`nivel2_valor`=".$level2_value;
    }elseif( $image_id > 0 ){
        $more_where_join = "AND `pecas_imagens_veiculos`.`imagem_id`=".$image_id;
    }
    
    global $LG, $CONFIG_OPTIONS, $row;
    
    if( $CONFIG_OPTIONS['DESENHO_EXPL_MODULE_ACTIVE'] > 0 ){
        $sql = "SELECT `pecas_imagens_hotspots`.`registo_id`, `pecas_imagens_hotspots`.`nome".$LG."` as `label`
                  FROM `pecas_imagens_hotspots`
                    JOIN `pecas_imagens_veiculos` ON `pecas_imagens_veiculos`.`imagem_id`=`pecas_imagens_hotspots`.`imagem_id` AND `pecas_imagens_veiculos`.`veiculo_id`=".$vehicle_id." $more_where_join
                  GROUP BY `pecas_imagens_hotspots`.`registo_id`
                UNION
                  SELECT `registo_id`, '' as `label`
                  FROM `pecas_veiculos_registos`
                  WHERE `veiculo_id`=".$vehicle_id." $more_where
                ORDER BY CASE `label` WHEN '' THEN 1 ELSE 0 END, `label`
                LIMIT ".$offset.", ".$quantity;
    }else{
        $sql = "SELECT `registo_id`, '' as `label`
                FROM `pecas_veiculos_registos`
                WHERE `veiculo_id`=".$vehicle_id." $more_where
                LIMIT ".$offset.", ".$quantity;
    }

    $products_res = cms_query($sql);
    $products = [];
            
  
    if($page_id>0){
        $row = call_api_func('get_pagina', $page_id, '_trubricas');  
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductSimple.php';
    
    while( $product = cms_fetch_assoc($products_res) ){
        
        if( (int)$product['registo_id'] <= 0 ){
            continue;
        }
        
        $product_info = _getProductSimple(5, 0, $product['registo_id'], 0, 0, $row['catalogo']);
        $product_info = unserialize($product_info);
        
        if( empty( $product_info['product'] ) || (int)$product_info['product']['id'] <= 0 ){
            continue;
        }
        
        $product_info['product']['list_label'] = $product['label'];
        
        $products[]   = $product_info['product'];
        
    }
    
    return serialize(['success' => true, 'products' => $products]);

}

?>
