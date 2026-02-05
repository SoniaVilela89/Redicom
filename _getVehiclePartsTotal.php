<?

function _getVehiclePartsTotal($vehicle_id=0, $level1_value=0, $level2_value=0, $image_id=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id     = params('vehicle_id');
        $level1_value   = params('level1_value');
        $level2_value   = params('level2_value');
        $image_id       = (int)params('image_id');
    }
    
    $level1_value = $level1_value > 0 ? $level1_value : (int)$_GET['l1'];
    $level2_value = $level2_value > 0 ? $level2_value : (int)$_GET['l2'];
    $quantity     = $quantity > 0 ? $quantity : (int)$_GET['qtd'];
    $offset       = $offset > 0 ? $offset : (int)$_GET['offset'];
    
    if( $vehicle_id <= 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
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
    
    global $LG, $CONFIG_OPTIONS;
    
    if( $CONFIG_OPTIONS['DESENHO_EXPL_MODULE_ACTIVE'] > 0 ){
        $sql = "SELECT COUNT(*) as `total_prods` 
                FROM(
                      SELECT `pecas_imagens_hotspots`.`registo_id`
                      FROM `pecas_imagens_hotspots`
                        JOIN `pecas_imagens_veiculos` ON `pecas_imagens_veiculos`.`imagem_id`=`pecas_imagens_hotspots`.`imagem_id` AND `pecas_imagens_veiculos`.`veiculo_id`=".$vehicle_id." $more_where_join
                      GROUP BY `pecas_imagens_hotspots`.`registo_id`
                    UNION
                      SELECT `registo_id`
                      FROM `pecas_veiculos_registos`
                      WHERE `veiculo_id`=".$vehicle_id." $more_where
                    ) as `total_tbl`";
    }else{
        $sql = "SELECT COUNT(`registo_id`) as `total_prods`
                FROM `pecas_veiculos_registos`
                WHERE `veiculo_id`=".$vehicle_id." $more_where";
    }
    
    $total = cms_fetch_assoc( cms_query($sql) );
    
    return serialize(['success' => true, 'total' => $total['total_prods'] ]);
        
}

?>
