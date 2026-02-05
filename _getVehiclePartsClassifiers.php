<?

function _getVehiclePartsClassifiers($vehicle_id=0, $first_level_value=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id = params('vehicle_id');
    }
    
    if( $vehicle_id <= 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
        return serialize(['success' => false]);
    }
    
    global $id;
    global $LG, $CONFIG_OPTIONS;
    
    $join_level    = '';
    $level_table   = $CONFIG_OPTIONS['pecas_nivel_1'];
    $current_level = 1;
    $add_url_param = '';
    
    if( $first_level_value > 0 && filter_var($first_level_value, FILTER_VALIDATE_INT) !== FALSE ){
        $level_table   = $CONFIG_OPTIONS['pecas_nivel_2'];
        $join_level    = 'AND `nivel1_valor`='.(int)$first_level_value;
        $current_level = 2;
        $add_url_param = '&l1='.$first_level_value;
    }
    
    $classifiers_res = cms_query("SELECT DISTINCT `".$level_table."`.`id`, `".$level_table."`.`nome".$LG."` as `nome`
                                  FROM `".$level_table."`
                                    JOIN `pecas_veiculos_registos` ON `veiculo_id`=".$vehicle_id." AND `".$level_table."`.`id`=`pecas_veiculos_registos`.`nivel".$current_level."_valor` ".$join_level." 
                                  WHERE `nome".$LG."` != ''");
    
    $classifiers_found = [];
    $images_found      = 0;
    while( $classifier = cms_fetch_assoc($classifiers_res) ){    
        
        $classifier_tmp = [ 'id'           => $classifier['id'],
                            'link_name'    => $classifier['nome'],
                            'url'          => 'index.php?id='.$id.'&vc='.$vehicle_id.'&l'.$current_level.'='.$classifier['id'].$add_url_param
                          ];

        $cam = "images/parts_level_".$current_level."_".$classifier['id'].".jpg";
        if( file_exists( $_SERVER['DOCUMENT_ROOT']."/".$cam ) ){
            $images_found++;
            $classifier_tmp['imageCatalog'] = call_api_func('imageOBJ', $classifier['nome'], 1, $cam);
        }else if( $current_level == 1 ){
            $images_found++;
            $classifier_tmp['imageCatalog'] = call_api_func('imageOBJ', $classifier['nome'], 1, 'plugins/templates_base_b2b/sysimgs/no-image5.jpg');
        }
        
        
        $classifiers_found[] = $classifier_tmp;
    }
    
    return serialize(['success' => true, 'classifiers' => $classifiers_found, 'images_found' => $images_found]);
    
}

?>
