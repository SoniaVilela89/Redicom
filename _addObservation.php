<?

function _addObservation(){

    $_POST = decode_array_to_UTF8($_POST);

    $id             = (int)$_POST['id'];
    $observation    = trim($_POST['obs']);
    $token          = trim($_POST['token']);
    $type           = (int)$_POST['type'];
    $is_seller      = (int)$_POST['is_seller'];

    if( $id <= 0 || $type <= 0 || empty($observation) || empty($token) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }

    global $userID, $CONFIG_OPTIONS;

    $userOriginalID = (int)$userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if( $type == 1 ){ # RMA

        $rma = cms_fetch_assoc( cms_query( "SELECT `id`, `ref`, `created_at`, `user_id` FROM `ec_rmas` WHERE `id`='".$id."' AND `user_id`='".$userOriginalID."'" ) );
        if( (int)$rma['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }
    
        if( $token != md5($rma['id']."|||".$rma['ref']."|||".$rma['created_at']."|||".$rma['user_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $table_obs  = "ec_rmas_observacoes";
        $field_fk   = "rma_id";
        $value_fk   = $rma['id'];

        $arr_extra_fields = array('notificacao_bo' => 1);

    }elseif( $type == 2 ){ # Warranty
        
        $warranty = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda_avarias` WHERE `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'" ) );
        if( (int)$warranty['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($warranty['id']."|||".$warranty['veiculo_id']."|||".$warranty['data_criacao']."|||".$warranty['utilizador_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $table_obs  = "b2b_pos_venda_avarias_observacoes";
        $field_fk   = "avaria_id";
        $value_fk   = $warranty['id'];

        $arr_extra_fields = array('notificacao_bo' => 1);

        # update à data de última atualização efetuada no veículo
        @cms_query("UPDATE `b2b_pos_venda` SET `updated_at`='".date("Y-m-d H:i:s")."' WHERE `veiculo_id`='".$warranty['veiculo_id']."' AND `utilizador_id`='".$warranty['utilizador_id']."'");


    }elseif( $type == 3 ){ # Budget

        $budget = cms_fetch_assoc( cms_query("SELECT `id`, `user_id`, `created_at`, `type`, `client_email`, `client_name`, `ref`, `seller_id`
                                                FROM `budgets` 
                                                WHERE `id`='".$id."' AND `user_id`='".$userOriginalID."'") );
        
        if( (int)$budget['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($budget['id']."|||". $budget['user_id']."|||". $budget['created_at']."|||". $budget['type']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $table_obs  = "budgets_observations";
        $field_fk   = "budget_id";
        $value_fk   = $budget['id'];
        
        $seller = cms_fetch_assoc( cms_query("SELECT `id`, `nome`, `email` FROM `_tusers_sales` WHERE `id`='".$budget['seller_id']."'") );
        
        if( $is_seller > 0 ){
            $arr_extra_fields = array('autor' => $seller['nome']);
        }

    }
    /*
    # observacoes_veiculos
    ## desenvolvido e a funcionar mas não está a ser usado - comentado caso seja necessário no futuro
    elseif( $type == 4 ){ # Pós-venda - observações veículo

        $after_sale = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda` WHERE `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'" ) );
        if( (int)$after_sale['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($after_sale['id']."|||".$after_sale['veiculo_id']."|||".$after_sale['data_criacao']."|||".$after_sale['utilizador_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $table_obs  = "b2b_pos_venda_observacoes";
        $field_fk   = "pos_venda_id";
        $value_fk   = $after_sale['id'];

        # update à data de última atualização efetuada no veículo
        @cms_query("UPDATE `b2b_pos_venda` SET `updated_at`='".date("Y-m-d H:i:s")."' WHERE `id`='".$after_sale['id']."' AND `utilizador_id`='".$userOriginalID."'");


    }*/
    else{
        return serialize(['success' => 0, 'error' => 'Bad Request - Unprocessable Entity']);
    }
    
    $arr_insert = array('autor' => $_SESSION['EC_USER']['nome'], $field_fk => $value_fk, 'obs' => $observation);

    if( count($arr_extra_fields) > 0 ){
        $arr_insert = array_merge($arr_insert, $arr_extra_fields);
    }
    
    $res = insertLineTable($table_obs, $arr_insert);
    if( !$res ){
        return serialize(['success' => 0, 'error' => 'Error on create observation']);
    }

    if( $type == 3 ){

        if( $is_seller > 0 ){
            $email_to = $budget['client_email'];
            $client_name = $budget['client_name'];
        }else{
            $email_to = $seller['email'];
            $client_name = $seller['nome'];
        }
        

        $data = array(
            "email_cliente"     => $email_to,
            "id_cliente"        => $budget['user_id'],
            "CLIENT_NAME"       => $client_name,
            "REF"               => $budget['ref']
        );
        
        $data = serialize($data);
        $data = gzdeflate($data, 9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);

        global $sslocation;

        require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/client/client_rest.php';
        $r = new Rest($sslocation . '/api/api.php');
        $ret = $r->get("/sendEmail/14/" . $data . "/0/1");

    }

    return serialize(['success' => 1, 'message' => 'Observation added successfully', 'id' => $res]);

}

?>
