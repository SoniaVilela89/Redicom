<?

function _addVehicleToUserGarage($vehicle_id=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id = (int)params('vehicle_id');
    }
    
    $user_id = (int)$_SESSION['EC_USER']['id'];
    if( $user_id <= 0 ){
        return serialize(['success' => false]);
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getVehicleInfo.php';
    $vehicle_ctrl_info = _getVehicleInfo($vehicle_id);
    $vehicle_ctrl_info = unserialize($vehicle_ctrl_info);
    $vehicle_info      = $vehicle_ctrl_info['vehicle'];
    
    if( (int)$vehicle_info['id'] <= 0 ){
        return serialize(['success' => false, "error" => ['code' => '001', 'msg' => 'Vehicle Not Found!']]);
    }
    
    $query_success = cms_query("INSERT INTO `pecas_veiculos_utilizadores` (`veiculo_id`, `user_id`) VALUES(".cms_escape($vehicle_id).", ".$user_id.")");
    if( $query_success === false ){
        return serialize(['success' => false]);
    }
    
    $inserted_id = cms_insert_id();
    
    $vehicle_info['vehicle_id'] = $vehicle_info['id'];
    $vehicle_info['id']         = $inserted_id;
    
    return serialize(['success' => $query_success, 'vehicle' => $vehicle_info]);
    
}

?>
