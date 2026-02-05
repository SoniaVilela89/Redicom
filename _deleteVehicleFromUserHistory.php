<?

function _deleteVehicleFromUserHistory($id=0){
    
    if( $id <= 0 ){
        $id = (int)params('id');
    }
    
    $user_id = (int)$_SESSION['EC_USER']['id'];
    if( $user_id <= 0 ){
        return serialize(['success' => false]);
    }
    
    $query_success = cms_query("DELETE FROM `pecas_veiculos_historico` WHERE `id`=".$id." AND `user_id`=".$user_id);
    
    return serialize(['success' => $query_success]);
    
}

?>