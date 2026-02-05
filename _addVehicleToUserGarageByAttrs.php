<?

function _addVehicleToUserGarageByAttrs($segmento=0, $marca=0, $modelo=0, $ano=0){
    
    if( $segmento <= 0 || $marca <= 0 || $modelo <= 0 || $ano <= 0 ){
        $segmento = (int)params('segmento');
        $marca    = (int)params('marca');
        $modelo   = (int)params('modelo');
        $ano      = (int)params('ano');
    }
    
    $user_id = (int)$_SESSION['EC_USER']['id'];
    if( $user_id <= 0 ){
        return serialize(['success' => false]);
    }
    
    global $LG;
    
    $vehicle_row = cms_fetch_assoc( cms_query("SELECT `pecas_veiculos`.`id` as `vehicle_id`,
                                                        `pecas_veiculos_segmentos`.`id` as `segmento_id`, `pecas_veiculos_segmentos`.`nome".$LG."` as `segmento_nome`,
                                                        `pecas_veiculos_marcas`.`id` as `marca_id`, `pecas_veiculos_marcas`.`nome".$LG."` as `marca_nome`,
                                                        `pecas_veiculos_modelos`.`id` as `modelo_id`, `pecas_veiculos_modelos`.`nome".$LG."` as `modelo_nome`,
                                                        `pecas_veiculos_anos`.`id` as `ano_id`, `pecas_veiculos_anos`.`nome".$LG."` as `ano_nome`
                                                FROM `pecas_veiculos`
                                                    JOIN `pecas_veiculos_segmentos` ON `pecas_veiculos_segmentos`.`id`=`pecas_veiculos`.`segmento`
                                                    JOIN `pecas_veiculos_marcas` ON `pecas_veiculos_marcas`.`id`=`pecas_veiculos`.`marca`
                                                    JOIN `pecas_veiculos_modelos` ON `pecas_veiculos_modelos`.`id`=`pecas_veiculos`.`modelo`
                                                    JOIN `pecas_veiculos_anos` ON `pecas_veiculos_anos`.`id`=`pecas_veiculos`.`ano`
                                                WHERE `pecas_veiculos`.`segmento`=".$segmento."
                                                  AND `pecas_veiculos`.`marca`=".$marca."
                                                  AND `pecas_veiculos`.`modelo`=".$modelo."
                                                  AND `pecas_veiculos`.`ano`=".$ano) );

    $vehicle_id = (int)$vehicle_row['vehicle_id'];
    
    if( $vehicle_id <= 0 ){
        return serialize(['success' => false, "error" => ['code' => '001', 'msg' => 'Vehicle Not Found!']]);
    }
    
    $query_success = cms_query("INSERT INTO `pecas_veiculos_utilizadores` (`veiculo_id`, `user_id`) VALUES(".$vehicle_id.", ".$user_id.")");
    if( $query_success === false ){
        return serialize(['success' => false]);
    }
    
    $inserted_id = cms_insert_id();
    $vehicle_row['id']   = $inserted_id;
    $vehicle_row['nome'] = $vehicle_row['marca_nome'].' '.$vehicle_row['modelo_nome'].' '.$vehicle_row['ano_nome'];
    
    return serialize(['success' => $query_success, 'vehicle' => $vehicle_row]);
    
}

?>
