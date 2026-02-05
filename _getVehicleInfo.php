<?

function _getVehicleInfo($vehicle_id){
    
    if( $vehicle_id <= 0 ){
        return null;
    }
    
    global $LG;
    
    $vehicle_res = cms_query("  SELECT `pecas_veiculos`.`id`, 
                                        `pecas_veiculos_segmentos`.`id` as `segmento_id`, `pecas_veiculos_segmentos`.`nome".$LG."` as `segmento_nome`,
                                        `pecas_veiculos_marcas`.`id` as `marca_id`, `pecas_veiculos_marcas`.`nome".$LG."` as `marca_nome`,
                                        `pecas_veiculos_modelos`.`id` as `modelo_id`, `pecas_veiculos_modelos`.`nome".$LG."` as `modelo_nome`,
                                        `pecas_veiculos_anos`.`id` as `ano_id`, `pecas_veiculos_anos`.`nome".$LG."` as `ano_nome`
                                FROM `pecas_veiculos`
                                    JOIN `pecas_veiculos_segmentos` ON `pecas_veiculos_segmentos`.`id`=`pecas_veiculos`.`segmento`
                                    JOIN `pecas_veiculos_marcas` ON `pecas_veiculos_marcas`.`id`=`pecas_veiculos`.`marca`
                                    JOIN `pecas_veiculos_modelos` ON `pecas_veiculos_modelos`.`id`=`pecas_veiculos`.`modelo`
                                    JOIN `pecas_veiculos_anos` ON `pecas_veiculos_anos`.`id`=`pecas_veiculos`.`ano`
                                WHERE `pecas_veiculos`.`id`=".cms_escape($vehicle_id));
    
    $vehicle = cms_fetch_assoc($vehicle_res);
    $vehicle['nome'] = $vehicle['marca_nome'].' '.$vehicle['modelo_nome'].' '.$vehicle['ano_nome'];
    
    return serialize(['success' => true, 'vehicle' => $vehicle]);
    
}

?>