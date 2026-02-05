<?

function _createAfterSale(){

    if (!file_exists($_SERVER["DOCUMENT_ROOT"].'/logs_payments_'.date("m"))) {
        mkdir($_SERVER["DOCUMENT_ROOT"].'/logs_payments_'.date("m"), 0777, true);
    }
    
    $handler = fopen($_SERVER["DOCUMENT_ROOT"].'/logs_payments_'.date("m").'/'.date("Ymd").'_after_sales.txt', 'a+');
    fwrite($handler, date("Y-m-d H:i:s")."\n");
    fwrite($handler, "Antes do decode: \n");
    fwrite($handler, print_r($_POST, true));
    
    $_POST = decode_array_to_UTF8($_POST);

    fwrite($handler, "Depois do decode: \n");
    fwrite($handler, print_r($_POST, true));
    
    $vehicle_id = (int)$_POST['vehicle']['id']; 
    
    
    if( $vehicle_id <= 0 ){
        fwrite($handler, "Exit 1 \n");
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    global $userID, $CONFIG_OPTIONS;

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    $has_vehicle_created = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda_veiculos` WHERE `id`='".$vehicle_id."' AND `deleted`=0") );
    if( (int)$has_vehicle_created['id'] <= 0 ){
        fwrite($handler, "Exit 2 \n");
        return serialize(['success' => 0, 'error' => 'Error on create After Sale - no Vehicle found']);
    }
    
    $after_sale = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda` WHERE `utilizador_id`='".$userOriginalID."' AND `veiculo_id`='".$vehicle_id."'") );
    if( (int)$after_sale['id'] <= 0 ){
        
        $after_sale_created = cms_query("INSERT INTO `b2b_pos_venda` SET `utilizador_id`='".$userOriginalID."', `veiculo_id`='".$vehicle_id."', `updated_at`='".date("Y-m-d H:i:s")."'");
        if( !$after_sale_created ){
            fwrite($handler, "Exit 3 \n");
            return serialize(['success' => 0, 'error' => 'Error on create After Sale']);
        }
    
        $after_sale_created_id = cms_insert_id();
        if( (int)$after_sale_created_id <= 0 ){
            fwrite($handler, "Exit 4 \n");
            return serialize(['success' => 0, 'error' => 'Error on get After Sale id']);
        }
        
    }else{
        $after_sale_created_id = $after_sale['id'];

        # update à data de última atualização efetuada no veículo
        @cms_query("UPDATE `b2b_pos_venda` SET `updated_at`='".date("Y-m-d H:i:s")."' WHERE `id`='".$after_sale_created_id."'");
    }
    
    if( isset($_POST['new_owner']) && trim($_POST['new_owner']['nif']) != "" ){

        $_POST['new_owner']['date'] = date( "Y-m-d", strtotime($_POST['new_owner']['date']) );
        
        $_POST['new_owner']['name']         = safe_value( $_POST['new_owner']['name'] );
        $_POST['new_owner']['nif']          = safe_value( $_POST['new_owner']['nif'] );
        $_POST['new_owner']['address']      = safe_value( $_POST['new_owner']['address'] );
        $_POST['new_owner']['zip']          = safe_value( $_POST['new_owner']['zip'] );
        $_POST['new_owner']['city']         = safe_value( $_POST['new_owner']['city'] );
        $_POST['new_owner']['phone_number'] = safe_value( $_POST['new_owner']['phone_number'] );
        
        $sql = "INSERT INTO b2b_pos_venda_veiculos_proprietarios SET 
                      `nome`='".$_POST['new_owner']['name']."', `nif`='".$_POST['new_owner']['nif']."', `morada`='".$_POST['new_owner']['address']."',
                      `cp`='".$_POST['new_owner']['zip']."', `cidade`='".$_POST['new_owner']['city']."', `pais`='".$_POST['new_owner']['country']['id']."',
                      `telefone`='".$_POST['new_owner']['phone_number']."', `email`='".$_POST['new_owner']['email']."', `data_inicio`='".$_POST['new_owner']['date']."',
                      `veiculo_id`='".$vehicle_id."', `data_criacao`=NOW()
                    ON DUPLICATE KEY UPDATE 
                      `nome`='".$_POST['new_owner']['name']."', `morada`='".$_POST['new_owner']['address']."', `cp`='".$_POST['new_owner']['zip']."',
                      `cidade`='".$_POST['new_owner']['city']."', `pais`='".$_POST['new_owner']['country']['id']."', `telefone`='".$_POST['new_owner']['phone_number']."',
                      `email`='".$_POST['new_owner']['email']."', `data_inicio`='".$_POST['new_owner']['date']."'
                  ";
        
        cms_query($sql);
        
        
        fwrite($handler, $sql." \n");        
        
    }

    if( (int)$_POST['ask_registration'] == 1 && (int)$_SESSION['EC_USER']['request_license_plates'] == 1 ){

        $s_license = "";
        if(trim($_POST['license_obs']) != ''){
            $s_license = ",`matricula_obs` = '%s'";
            $s_license = sprintf($s_license, safe_value(trim($_POST['license_obs'])));
        }

        $s = "UPDATE `b2b_pos_venda_veiculos` SET `matricula`='-1' $s_license WHERE `id`='".$vehicle_id."'";
        $after_sale_created = cms_query($s);
    }

    fwrite($handler, "Exit 5 \n");
    return serialize(['success' => 1, 'msg' => 'After Sale created successfully', 'after_sale' => $after_sale_created_id]);
    
}

?>
