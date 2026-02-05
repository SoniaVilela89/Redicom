<?

function _getAfterSaleDetail($user_id=0, $after_sale_id=0){
    
    if( (int)$after_sale_id <= 0 || (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
        $after_sale_id = (int)params('after_sale_id');
    }

    if( (int)$after_sale_id <= 0 || $user_id <= 0 ){
        exit;
    }
    
    $after_sale = cms_fetch_assoc( cms_query("SELECT `id`,`veiculo_id`,`utilizador_id`,`data_criacao`,`updated_at` FROM `b2b_pos_venda` WHERE `id`='".$after_sale_id."' AND `utilizador_id`='".$user_id."' LIMIT 1") );
    if( (int)$after_sale['id'] <= 0 ){
        exit;
    }
    
    global $LG;
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getAfterSalesVehicleInfo.php';

    $vehicle_info = unserialize( _getAfterSalesVehicleInfo($after_sale['veiculo_id']) );
    if( $vehicle_info['success'] === false || empty($vehicle_info['payload']) ){
        exit;
    }
    
    $vehicle_info = $vehicle_info['payload']['vehicle'];
    if( empty($vehicle_info) || (int)$vehicle_info['id'] <= 0 ){
        exit;
    }
    
    $after_sale_info = array();
    $after_sale_info['id'] = $after_sale['id'];
    $after_sale_info['date'] = date("Y-m-d", strtotime($after_sale['updated_at'])); # data da ultima atualização
    $after_sale_info['token'] = md5($after_sale['id']."|||".$after_sale['veiculo_id']."|||".$after_sale['data_criacao']."|||".$after_sale['utilizador_id']); # token validação observações
    
    $response = array();
    $response["after_sale"] = $after_sale_info;
    
    $response["after_sale"]['vehicle'] = $vehicle_info;
    
    $response["after_sale"]['damages'] = array();
    $damages_res = cms_query("SELECT `id`,`ref`,`estado`,`estado_info`,`data_criacao`,`kms` FROM `b2b_pos_venda_avarias` WHERE `veiculo_id`='".$vehicle_info['id']."' AND `hidden`='0' ORDER BY `id` DESC");
    while( $damage = cms_fetch_assoc($damages_res) ){
    
        $damage_status = cms_fetch_assoc( cms_query("SELECT `id`, `nome$LG` AS `name`, `class_name` FROM `b2b_pos_venda_avarias_estados` WHERE `id`=".$damage['estado']) );

        $status_info                = array();
        $status_info['id']          = $damage_status['id'];
        $status_info['name']        = $damage_status['name'];
        $status_info['class_name']  = $damage_status['class_name'];

        if( $damage['estado_info'] > 0 ){
            $damage_status_info = cms_fetch_assoc( cms_query("SELECT `nome$LG` AS `name`, `class_name` FROM `b2b_pos_venda_avarias_estados_info` WHERE `id`=".$damage['estado_info']) );
            $status_info['name'] = $damage_status_info['name'];
            if($damage_status_info['class_name'] != '') {
                $status_info['class_name'] = $damage_status_info['class_name'];
            }
        }
        
        $damage_info                = array();
        $damage_info['id']          = $damage['id'];
        $damage_info['ref']         = $damage['ref'];
        $damage_info['date']        = date("Y-m-d", strtotime($damage['data_criacao']));
        $damage_info['kms']         = $damage['kms'];
        $damage_info['status_info'] = $status_info;
        
        $response["after_sale"]['damages'][] = $damage_info;

    }


    /*
    # observações
    # observacoes_veiculos
    ## desenvolvido e a funcionar mas não está a ser usado - comentado caso seja necessário no futuro
    ## tabela b2b_pos_venda_observacoes (accelerator-b2b)
    $response["after_sale"]['observations'] = array();

    $s_obs = cms_query("SELECT `id`, `datahora` AS `datetime`, `obs`, `autor` AS `author` FROM `b2b_pos_venda_observacoes` WHERE `pos_venda_id`=".$after_sale_id." ORDER BY `id` DESC");
    while($obs = cms_fetch_assoc($s_obs)){

        $obs['file'] = "";

        $file_obs = "/downloads/vehicles/".$after_sale_id."/obs_".$obs['id'];
        $exts = array("jpeg","jpg","png","pdf","mp4","mov","zip");
        foreach ($exts as $ext) {
            if( file_exists($_SERVER['DOCUMENT_ROOT'].$file_obs.".".$ext) ) {
                $obs['file'] = $file_obs.".".$ext;
                continue;
            }
        }

        $response["after_sale"]['observations'][] = $obs;
    }*/

    return serialize(['success' => 1, 'payload' => $response]);

}

?>
