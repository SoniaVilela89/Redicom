<?

function _getVehiclePartsFilterValues($filter_name=''){
    
    if( $filter_name != '' ){
        $filter_name = trim(params('filter'));
    }

    global $LG;

    $filter_table_name   = '';
    $vehicle_column_name = '';
    $vehicle_filters     = get_vehicle_filters_names();
    
    switch( strtoupper($filter_name) ){
        case 'SEGMENTO':
          $filter_table_name   = 'pecas_veiculos_segmentos';
          $vehicle_column_name = 'segmento';
          break;
        case 'MARCA':
          $filter_table_name   = 'pecas_veiculos_marcas';
          $vehicle_column_name = 'marca';
          break;
        case 'MODELO':
          $filter_table_name   = 'pecas_veiculos_modelos';
          $vehicle_column_name = 'modelo';
          break;
        case 'ANO':
          $filter_table_name   = 'pecas_veiculos_anos';
          $vehicle_column_name = 'ano';
          break;
    }
    
    $JOIN_arr   = [];
    $JOIN_QUERY = 'JOIN `pecas_veiculos` ON `'.$filter_table_name.'`.`id`=`pecas_veiculos`.`'.$vehicle_column_name.'` AND `pecas_veiculos`.`segmento`>0 AND `pecas_veiculos`.`marca`>0 AND `pecas_veiculos`.`modelo`>0 AND `pecas_veiculos`.`ano`>0';
    
    if( isset( $_GET ) && !empty( $_GET ) ){
        
        foreach( $_GET as $filter_key_to_apply => $filter_value_to_apply ){
            if( isset($vehicle_filters[ $filter_key_to_apply ]) && filter_var($filter_value_to_apply, FILTER_VALIDATE_INT) !== false ){
                $JOIN_arr[] = '`pecas_veiculos`.`'.$filter_key_to_apply.'`='.$filter_value_to_apply;
            }
        }
        
        if( !empty($JOIN_arr) ){
            $JOIN_QUERY = 'JOIN `pecas_veiculos` ON `'.$filter_table_name.'`.`id`=`pecas_veiculos`.`'.$vehicle_column_name.'` AND `pecas_veiculos`.`segmento`>0 AND `pecas_veiculos`.`marca`>0 AND `pecas_veiculos`.`modelo`>0 AND `pecas_veiculos`.`ano`>0 AND '.implode(' AND ', $JOIN_arr);
        }
        
    }
    
    $filters_res = cms_query("SELECT DISTINCT `".$filter_table_name."`.`id`, `".$filter_table_name."`.`nome".$LG."` as `nome`
                              FROM `".$filter_table_name."` 
                                ".$JOIN_QUERY."
                              WHERE `pecas_veiculos`.`exibir_em` IN (0,2)   
                              ORDER BY `".$filter_table_name."`.`nome".$LG."`");
    
    $filter_values = [];
    while( $filter_value = cms_fetch_assoc($filters_res) ){
        $filter_values[] = $filter_value;
    }
    
    
    return serialize(['success' => true, 'values' => $filter_values]);

}

?>
