<?

function _setChangeEmail(){

    global $userID, $sslocation;
        
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }

    $sql_user = "SELECT id, nome, email FROM _tusers WHERE id='%d' LIMIT 0,1";
    $sql_user = sprintf($sql_user, $userID);
    $res_user = cms_query($sql_user);
    $row_user = cms_fetch_assoc($res_user);

    $action = (int)$_POST["action"];
    if((int)$action == 0){
        return serialize(array("0"=>"0"));     
    }

    switch ($action) {
        case 1:
            $status = sendCodeEmail($_POST, $row_user);
            break;
        case 2:
            $status = resendCodeEmail($_POST, $row_user);
            break;
        case 3:
            $status = changeEmail($_POST, $row_user);
            break;
        default:
            $status = 0;
            break;

    }
    
    
    if(is_callable('custom_controller_set_change_email')) {
        call_user_func('custom_controller_set_change_email', $userID, $row_user, $_POST, $status );
    }
    
    return serialize(array("0"=> $status));
}

function resendCodeEmail($POST=array(), $row_user=array()){

    global $userID, $sslocation, $LG;

    if (trim($POST['email']) == '' || !filter_var($POST['email'], FILTER_VALIDATE_EMAIL)) {
        return 0;
    }

    $sql_v_token = "SELECT id FROM ec_rec_pw WHERE user_id='%d' AND user_email='%s' AND tentativa!=0 AND datahora >= DATE_SUB(NOW(), INTERVAL 120 SECOND) LIMIT 0,1";
    $sql_v_token = sprintf($sql_v_token, $userID, $POST['email']);
    $res_v_token = cms_query($sql_v_token); 
    $row_v_token = cms_fetch_assoc($res_v_token);
    if((int)$row_v_token["id"] > 0){
        return -1;
    }

    cms_query("DELETE FROM ec_rec_pw WHERE user_id='".$userID."' AND user_email='".$POST['email']."'");

    $codigo = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    cms_query("INSERT INTO ec_rec_pw SET token='".$codigo."', user_id='".$userID."', user_email='".$POST['email']."', tentativa=1");

    sendC($POST, $row_user, 108, $codigo);  
    
    return 1;
}

function sendCodeEmail($POST=array(), $row_user=array()){
    
    global $userID, $sslocation, $LG;

    if (trim($POST['email']) == '' || !filter_var($POST['email'], FILTER_VALIDATE_EMAIL)) {
        return 0;
    }

    // $sql_v_token = "SELECT id FROM ec_rec_pw WHERE user_id='%d' AND user_email='%s' AND datahora >= DATE_SUB(NOW(), INTERVAL 300 SECOND) LIMIT 0,1";
    // $sql_v_token = sprintf($sql_v_token, $userID, $POST['email']);
    // $res_v_token = cms_query($sql_v_token); 
    // $row_v_token = cms_fetch_assoc($res_v_token);
    // if((int)$row_v_token["id"] > 0){
    //     return -1;
    // }

    $sql_verifica = "SELECT id, sem_registo FROM _tusers WHERE email='%s' ORDER BY if(sem_registo=0,0,999999),sem_registo DESC, id DESC LIMIT 0,1";
    $sql_verifica = sprintf($sql_verifica, $POST['email']);
    $res_verifica = cms_query($sql_verifica); 
    $row_verifica = cms_fetch_assoc($res_verifica);
    if($row_verifica['id'] > 0 && $row_verifica['sem_registo'] == 0){
        return -1;
    }

    cms_query("DELETE FROM ec_rec_pw WHERE user_id='".$userID."' AND user_email='".$POST['email']."'");

    $codigo = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    cms_query("INSERT INTO ec_rec_pw SET token='".$codigo."', user_id='".$userID."', user_email='".$POST['email']."'");

    sendC($POST, $row_user, 108, $codigo); 
    
    return 1;
}

function sendC($POST=array(), $row_user=array(), $template_id=108, $codigo=''){
    
    global $LG, $sslocation, $pagetitle;

    $data = array(
        "lg"                    =>  $LG,
        "email_cliente"         =>  $POST['email'],
        "id_cliente"            =>  $row_user['id'],                 
        "CLIENT_NAME"           =>  $row_user['nome'],
        "CODE"                  =>  $codigo,                            
        "NOME"                  =>  $row_user['nome'],
        "PAGETITLE"             =>  $pagetitle,
        "EMAIL_OLD"             =>  $POST['email_old'],
        "EMAIL_NEW"             =>  $POST['email_new']
    );    
    
    $data = serialize($data);
    $data = gzdeflate($data, 9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/client/client_rest.php';
    $r = new Rest($sslocation . '/api/api.php');
    $resp = $r->get("/sendEmail/$template_id/$data"); 
    $resp = json_decode($resp, true);
    
    file_get_contents($sslocation."/cj_send_emails.php?email_id=".$resp['response'][0]);  
}

function changeEmail($POST=array(), $row_user=array()){

    global $userID, $sslocation, $LG;

    if (!preg_match('/^\d{6}$/', trim($POST['code'])) ) {
        return 0;
    }

    

    $sql_v_token = "SELECT id, user_email FROM ec_rec_pw WHERE user_id='%d' AND token='%d' AND datahora >= DATE_SUB(NOW(), INTERVAL 300 SECOND) LIMIT 0,1";
    $sql_v_token = sprintf($sql_v_token, $userID, trim($POST['code']));
    $res_v_token = cms_query($sql_v_token); 
    $row_v_token = cms_fetch_assoc($res_v_token);
    if((int)$row_v_token["id"] > 0){  
        
        sendC(array("email" => $row_user["email"], "email_old" => $row_user["email"], "email_new" => $row_v_token['user_email']), $row_user, 109);

        sendC(array("email" => $row_v_token['user_email'], "email_old" => $row_user["email"], "email_new" => $row_v_token['user_email']), $row_user, 109);

        $sql_update_user = "UPDATE _tusers SET email='%s' WHERE id='%d'";
        $sql_update_user = sprintf($sql_update_user, $row_v_token['user_email'], $userID);
        cms_query($sql_update_user);
        
        require_once($_SERVER['DOCUMENT_ROOT']."/plugins/privacy/Log.php");
        $log = new Log();   
        $log->change_email_account($row_user["id"]);

        $_SESSION["EC_USER"]["email"] = $row_v_token['user_email'];

        return 1;
    }

    return -1;

}
