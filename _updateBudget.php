<?
function _updateBudget($budget_id=0, $status_info=null){

    if( is_null($status_info) ){

        if( isset($_POST) ){
            $status_param = $_POST;
        }else{
            $status_param = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( (int)$budget_id <= 0 ){
        $budget_id = (int)params('budget_id');
    }

    if( $budget_id <= 0 || is_null($status_param) || empty($status_param) ){
        return serialize(['success' => false]);
    }

    $status_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets_status` WHERE `id`='.$status_param['status']) );

    if( !isset( $status_info['id'] ) || empty( $status_info ) ){
        return serialize(['success' => false]);
    }

    $budget_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets` WHERE `id`='.$budget_id) );

    if( !isset( $budget_info['id'] ) || empty( $budget_info ) || $status_param['status'] <= $budget_info['status'] ){
        return serialize(['success' => false]);
    }

    global $LG;

    $update_success = cms_query( "UPDATE `budgets` SET `status`=".$status_param['status']." WHERE `id`=".$budget_id );

    if( $update_success ){

        $add_observations = '';
        if( trim( $status_param['obs'] ) != '' ){
            $add_observations = ' - '.utf8_decode( $status_param['obs'] );
        }

        $budget_type_info = "Orçamento colocado";
        if( (int)$budget_info['type'] == 1 ){
            $budget_type_info = "Proposta colocada";
        }
        $state_observation = $budget_type_info.' no estado: '.$status_info['name'.$LG].$add_observations;
        cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES(".$budget_id.", '".$state_observation."')");

        $budget_info['status']           = $status_param['status'];
        $budget_info['status_info']      = $status_info;
        $budget_info['available_status'] = getBudgetNextStatus($budget_info);

        if( (int)$budget_info['type'] == 1 ){
            
            $data = array(
                "email_cliente"     => $budget_info['client_email'],
                "id_cliente"        => $budget_info['user_id'],
                "CLIENT_NAME"       => $budget_info['client_name'],
                "REF"               => $budget_info['ref']
            );
            
            $data = serialize($data);
            $data = gzdeflate($data, 9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);
    
            global $sslocation;
    
            require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/client/client_rest.php';
            $r = new Rest($sslocation . '/api/api.php');
            $ret = $r->get("/sendEmail/17/" . $data . "/0/1");

        }

        return serialize(['success' => $update_success, 'payload' => $budget_info]);

    }else{
        return serialize(['success' => false]);
    }

}

?>
