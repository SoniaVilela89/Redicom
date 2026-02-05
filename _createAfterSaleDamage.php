<?

function _createAfterSaleDamage(){
    
    $_POST = decode_array_to_UTF8($_POST);

    # LOGS
    if (!file_exists(_ROOT.'/logs/logs_pos_venda_'.date("Y"))) {
        mkdir(_ROOT.'/logs/logs_pos_venda_'.date("Y"), 0777, true);
    }
    $handler = fopen(_ROOT.'/logs/logs_pos_venda_'.date("Y").'/'.date("Ymd").'_log_warranty.txt', 'a+');
    fwrite($handler, print_r("\r\n"."-------------------------------------------------------"."\r\n", true));
    fwrite($handler, date("Y-m-d H:i:s"));
    fwrite($handler, print_r("\r\n", true));
    fwrite($handler, print_r($_POST, true));
    fclose($handler);
    # LOGS


    $vehicle_id             = (int)$_POST['id'];
    $num_kms                = (int)$_POST['number_kms'];
    $date_kms               = trim($_POST['date']);
    $damage_report          = trim($_POST['damage_report']);
    $diagnose_dealership    = trim($_POST['diagnose_dealership']);
    $parts                  = $_POST['parts'];
    
    if( $num_kms <= 0 || $date_kms == "" || $damage_report == "" || $diagnose_dealership == "" || $vehicle_id <= 0 || empty($parts) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    global $userID, $CONFIG_OPTIONS;

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    $has_vehicle_created = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda_veiculos` WHERE `id`='".$vehicle_id."' AND `deleted`=0") );
    if( (int)$has_vehicle_created['id'] <= 0 ){
        return serialize(['success' => 0, 'error' => 'Error on create Damage - no Vehicle found']);
    }
    
    $has_after_sale_created = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda` WHERE `veiculo_id`='".$vehicle_id."'") );
    if( (int)$has_after_sale_created['id'] <= 0 ){
        return serialize(['success' => 0, 'error' => 'Error on create Damage - no After Sale found']);
    }
    
    $date_kms = date( "Y-m-d", strtotime($_POST['date']) );
    
    $damage_created = cms_query("INSERT INTO `b2b_pos_venda_avarias` SET 
                                `utilizador_id`='".$userOriginalID."', `veiculo_id`='".$vehicle_id."', `kms`='".$num_kms."',
                                `data_kms`='".$date_kms."', `obs1`='".$damage_report."', `obs2`='".$diagnose_dealership."',`hidden`='1'");
    if( !$damage_created ){
        return serialize(['success' => 0, 'error' => 'Error on create Damage']);
    }

    $damage_created_id = cms_insert_id();
    if( (int)$damage_created_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Error on get Damage id']);
    }

    $damage_created_ref = 'GRT '.date("y").".".str_pad($damage_created_id, 6, "0", STR_PAD_LEFT);
    cms_query("UPDATE `b2b_pos_venda_avarias` SET `ref`='".$damage_created_ref."', `estado`='1' WHERE `id`=".$damage_created_id);

    foreach( $parts as $key => $value ){
        cms_query("INSERT INTO `b2b_pos_venda_avarias_pecas` (`avaria_id`, `produto_id`, `quantidade`) VALUES(" . $damage_created_id . ", " . $key . ", " . $value . ")");
    }
    
    cms_query("INSERT INTO `b2b_pos_venda_avarias_logs` (`avaria_id`, `obs`, `autor`, `estado`) VALUES(".$damage_created_id.", 'Avaria registada', 'Cliente - ação manual em site', '1')");
    
    $created_warranty = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda_avarias` WHERE `id`='".$damage_created_id."'" ) );
    $created_warranty_token = md5($created_warranty['id']."|||".$created_warranty['veiculo_id']."|||".$created_warranty['data_criacao']."|||".$created_warranty['utilizador_id']);

    # update à data de última atualização efetuada no veículo
    @cms_query("UPDATE `b2b_pos_venda` SET `updated_at`='".date("Y-m-d H:i:s")."' WHERE `id`='".$has_after_sale_created['id']."'");

    return serialize(['success' => 1, 'msg' => 'Damage created successfully', 'damage' => $damage_created_id, 'token' => $created_warranty_token]);
    
}

?>
