<?

function _searchVehicleByAttributes($attributes_to_search=NULL){
    
    if( isset($_GET) && !empty($_GET) && is_null($attributes_to_search) ){
        $attributes_to_search = $_GET;
    }
    
    if( is_null($attributes_to_search) || empty($attributes_to_search) ){
        return serialize(['success' => false, 'vehicles' => []]);
    }
    
    global $LG;
    
    $vehicle_attributes = get_vehicle_filters_names();
    $attributes_query   = [];
    
    foreach( $attributes_to_search as $attr_key_to_apply => $attr_value_to_apply ){
        if( isset($vehicle_attributes[ $attr_key_to_apply ]) && filter_var($attr_value_to_apply, FILTER_VALIDATE_INT) !== false ){
            $attributes_query[] = '`pecas_veiculos`.`'.$attr_key_to_apply.'`='.$attr_value_to_apply;
        }
    }
    
    if( !empty($attributes_query) ){
        $attributes_query = 'WHERE '.implode(' AND ', $attributes_query);
    }else{
        return serialize(['success' => false, 'vehicles' => []]);
    }
    
    $filters_res = cms_query("SELECT `pecas_veiculos`.`id`
                              FROM `pecas_veiculos`
                              ".$attributes_query);
    
    $vehicles_found = [];
    while( $filter_value = cms_fetch_assoc($filters_res) ){
        $vehicles_found[] = $filter_value;
    }
    
    
    return serialize(['success' => true, 'vehicles' => $vehicles_found]);

}

?>