<?

function _getUserRMAs($user_id=0){
    
    
    
    if( (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
    }else{
        $user_id = (int)$user_id;
    }

    if( (int)$user_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    
    global $LG, $CONFIG_OPTIONS;
    
    

    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }
    
    $rmas_res = cms_query('SELECT * FROM `ec_rmas` WHERE `user_id`='.$user_id." AND status!='0' ORDER BY id desc");
    
    $rmas['rmas'] = [];
    $rmas_status_aux = [];
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getRMADetail.php';
    
    $rma_all_status = array();
    
    while( $rma_info = cms_fetch_assoc($rmas_res) ){
        
        $rma_temp                     = $rma_info; 
        
        $rma_temp['last_log']             = cms_fetch_assoc( cms_query('SELECT `datetime`, `obs` FROM `ec_rmas_logs` WHERE `rma_id`='.$rma_info['id'].' ORDER BY `id` DESC LIMIT 1') );
        
        $rmas_status_info_row = cms_fetch_assoc( cms_query('SELECT * FROM `ec_rmas_status` WHERE `id`='.$rma_temp['status']) );
    
        $rmas_status_info['id']           = $rmas_status_info_row['id'];
        $rmas_status_info['name']         = $rmas_status_info_row['name'.$LG];
        $rmas_status_info['description']  = $rmas_status_info_row['description'.$LG];
        $rmas_status_info['class_name']   = $rmas_status_info_row['class_name'];
        
        $rma_temp['status_info'] = $rmas_status_info;
        
        $rma_temp['status_history'] = _getRMADetail_getRMAStatus($rma_temp);
        
        $rmas['rmas'][] = $rma_temp;   
         
        $rma_all_status[ $rmas_status_info['id'] ]['id']          = $rmas_status_info['id'];
        $rma_all_status[ $rmas_status_info['id'] ]['name']        = $rmas_status_info['name'];
        $rma_all_status[ $rmas_status_info['id'] ]['total_rmas']  = (int)$rma_all_status[ $rmas_status_info['id'] ]['total_rmas'] + 1;
                
    }
    
    usort($rma_all_status, function($a, $b) {
        return $a['name'] <=> $b['name'];
    });
    $rmas['rma_all_status'] = $rma_all_status;
    
    # RMA REASONS
    $rmas_reasons_res = cms_query("SELECT id, name$LG AS name FROM `ec_rmas_reasons` WHERE `hidden`=0 ORDER BY name$LG");
    
    $rmas['rma_reasons'] = array();
    
    while( $rmas_reason = cms_fetch_assoc($rmas_reasons_res) ){
        $rmas['rma_reasons'][] = $rmas_reason;    
    }
    # RMA REASONS
    
    
    
    $rmas['rma_optional_reason'] = (int)$CONFIG_OPTIONS['B2B_RMAS_MOTIVO_FACULTATIVO'];
    
    return serialize(['success' => 1, 'payload' => $rmas]);
    
}

?>
