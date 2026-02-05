<?
function _createBudget($budget_to_create=null){

    if( is_null($budget_to_create) ){

        if( !empty($_POST) ){
            $budget_to_create = $_POST;
        }else{
            $budget_to_create = json_decode( file_get_contents('php://input'), true );
        }

    }
    
    unset($_POST);
    global $CONFIG_OPTIONS;

    $userOriginalID = (int)$_SESSION['EC_USER']['id'];
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if( empty($budget_to_create) || is_null($budget_to_create) || empty($budget_to_create['products']) || !isset($budget_to_create['products']) || (int)$userOriginalID <= 0 ){
        return serialize(['success' => false]);
    }

    $budget_type = (int)$budget_to_create['type'];

    if( $budget_type == 1 && (int)$_SESSION['EC_CLIENTE']['id'] <= 0 ){
        return serialize(['success' => false]);
    }

    global $CONFIG_OPTIONS;

    $expiration_days = (int)$CONFIG_OPTIONS['budget_expiration_days'];
    if( (int)$_SESSION['EC_USER']['budget_expiration_days'] > 0 ){
        $expiration_days = (int)$_SESSION['EC_USER']['budget_expiration_days'];
    }elseif( $expiration_days <= 0 ){
        $expiration_days = 30;
    }

    $arr                    = Array();
    $arr['user_id']         = (int)$userOriginalID;
    $arr['client_name']     = utf8_decode( $budget_to_create["client_name"] );
    $arr['client_email']    = $budget_to_create['client_email'];
    $arr['client_phone']    = $budget_to_create['client_phone'];
    $arr['observations']    = utf8_decode( $budget_to_create['obs'] );
    $arr['status']          = ( $budget_to_create['pending'] == 1 ) ? -1 : 1; #se proposta começa por -1 (Pendente)
    $arr['expiration_date'] = date('Y-m-d', strtotime('+'.$expiration_days.' days'));
    $arr['created_at_date'] = date('Y-m-d');
    
    $creation_log_name = $_SESSION['EC_USER']['nome'];
    
    if( $budget_type == 1 ){
        $arr['type'] = 1;
        $arr['seller_id'] = (int)$_SESSION['EC_CLIENTE']['id'];
        $creation_log_name = $_SESSION['EC_CLIENTE']['nome'];
    }

    foreach( $arr as $campo=>$valor ){
        $f[] = "`".$campo."`";
        $v[] = "'".safe_value($valor)."'";
    }

    $budget_created = cms_query("INSERT INTO `budgets` (" . implode(",",$f) . ") VALUES(". implode(",",$v) .")");
    
    if( !$budget_created ){
        return serialize(['success' => false]);
    }

    $budget_created_id = cms_insert_id();

    if( (int)$budget_created_id <= 0 ){
        return serialize(['success' => false]);
    }

    $state_observation = str_replace("{NAME}", $creation_log_name, estr2(980));
    cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES (".$budget_created_id.", '".$state_observation."')");

    $ref_prefix = "BDG";
    if( $budget_type == 1 ){
        $ref_prefix = "PRP";
    }
    $arr['ref'] = $ref_prefix.' '.date("y").".".str_pad($budget_created_id, 6, "0", STR_PAD_LEFT);
    cms_query("UPDATE `budgets` SET `ref`='".$arr['ref']."' WHERE `id`=".$budget_created_id);

    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addProductToBudget.php';
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addServiceToBudget.php';
    $arr['products_added'] = [];

    foreach( $budget_to_create['products'] as $product_info){
        
        switch( $product_info['type'] ){
            case '1':
                $response = _addProductToBudget($budget_created_id, $product_info, $budget_type);
                break;
            case '2':
                $response = _addServiceToBudget($budget_created_id, $product_info);
                break;
        }
        
        $response = unserialize($response);
        $arr['products_added'][] = $response['payload']['product_added'];
        
    }

    update_budget_totals($budget_created_id);
    $arr['id'] = $budget_created_id;

    $obs_prefix = "Orçamento criado";
    if( $budget_type == 1 && !$budget_to_create['pending'] ){

        $obs_prefix = "Proposta criada";

        $data = array(
            "email_cliente"     => $arr['client_email'],
            "id_cliente"        => $arr['user_id'],
            "CLIENT_NAME"       => $arr['client_name'],
            "REF"               => $arr['ref']
        );
        
        $data = serialize($data);
        $data = gzdeflate($data, 9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);

        global $sslocation;

        require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/client/client_rest.php';
        $r = new Rest($sslocation . '/api/api.php');
        $ret = $r->get("/sendEmail/16/" . $data . "/0/1");

    }

    return serialize(['success' => $update_success, 'payload' => $arr]);

}

?>
