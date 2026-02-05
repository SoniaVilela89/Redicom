<?

function _searchVehicleByText($term=''){
    
    if( $term != '' ){
        $term = trim(params('term'));
    }

    global $LG;

    if(is_callable('custom_controller_search_vechicle_by_text')) {
        return call_user_func_array('custom_controller_search_vechicle_by_text', array(&$term));
    }


    $terms = explode(" ", $term);
    $terms_query = [];
    foreach( $terms as $term ){
        $terms_query[] = "(`pecas_veiculos_marcas`.`nome".$LG."` LIKE '%".safe_value($term)."%' OR `pecas_veiculos_modelos`.`nome".$LG."` LIKE '%".safe_value($term)."%' OR `pecas_veiculos_anos`.`nome".$LG."` LIKE '%".safe_value($term)."%')";
    }
    
    $vehicles_res = cms_query("SELECT `pecas_veiculos`.`id`, CONCAT(`pecas_veiculos_marcas`.`nome".$LG."`, ' ', `pecas_veiculos_modelos`.`nome".$LG."`, ' ', `pecas_veiculos_anos`.`nome".$LG."`) as `label`
                                FROM `pecas_veiculos`
                                  JOIN `pecas_veiculos_marcas` ON `pecas_veiculos_marcas`.`id`=`pecas_veiculos`.`marca`
                                  JOIN `pecas_veiculos_modelos` ON `pecas_veiculos_modelos`.`id`=`pecas_veiculos`.`modelo`
                                  JOIN `pecas_veiculos_anos` ON `pecas_veiculos_anos`.`id`=`pecas_veiculos`.`ano`
                                WHERE ".implode(' AND ', $terms_query)."
                                ORDER BY `label`");
    
    $resp = [];
    while( $vehicle = cms_fetch_assoc($vehicles_res) ){
        $resp[] = $vehicle;
    }
    

    return serialize(['success' => true, 'vehicles' => $resp]);

}

?>
