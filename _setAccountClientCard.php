<?

function _setAccountClientCard(){
    global $LG,$pagetitle,$sslocation,$SETTINGS;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode(safe_value($v));
    }

    if (!isset($_POST['cartao_id']) || empty($_POST['cartao_id'])) return serialize(array("0"=>"0"));  

    if((int)$SETTINGS['cartao_cliente_associacao'] == 0 || (int)$SETTINGS['cartao_cliente_associacao'] == 1) {
        if (!isset($_POST['cartao_num']) || empty($_POST['cartao_num'])) return serialize(array("0"=>"0"));
    }

    if((int)$SETTINGS['cartao_cliente_associacao'] == 0) {
        if (!isset($_POST['nif']) || empty($_POST['nif'])) return serialize(array("0"=>"0"));
    }

    if((int)$SETTINGS['cartao_cliente_associacao'] == 0 || (int)$SETTINGS['cartao_cliente_associacao'] == 1 || $_POST['typevalidation'] == 0) {
        if (!isset($_POST['telefone']) || empty($_POST['telefone'])) return serialize(array("0"=>"0"));
    }

    if((int)$SETTINGS['cartao_cliente_associacao'] == 2) { # por email
        if (!isset($_POST['email']) || empty($_POST['email'])) return serialize(array("0"=>"0"));
    }



    # Associação de cartão cliente através de: "Email e Telemóvel" ou "Email ou Telemóvel"
    if((int)$SETTINGS['cartao_cliente_associacao'] > 0) {

        $codigos_links = __get_code_link();

        # por telemóvel
        if((int)$SETTINGS['cartao_cliente_associacao'] == 1 || $_POST['typevalidation'] == 0) {
            __client_card_send_sms($codigos_links);
        }

        # por email
        if((int)$SETTINGS['cartao_cliente_associacao'] == 1 || $_POST['typevalidation'] == 1) {
            __client_card_send_email($codigos_links);
        }
        
        

        if($SETTINGS['cartao_cliente_associacao'] == 2){
            $_POST['cartao_num'] = "AUTO";
        }

        $update = "";
        if($_POST['telefone'] != "") {
            $update = ", `telefone`='".$COUNTRY['phone_prefix'].$_POST['telefone']."'";
        }

        cms_query("UPDATE `_tusers` SET `f_cartao`='".$_POST['cartao_id']."', `estado_cartao`='1', `token_cartao`='".$codigos_links['codigo_sms_no_space']."', f_code='".$_POST['cartao_num']."', data_cartao=NOW() $update WHERE `id`='".$_SESSION['EC_USER']['id']."'");

        $_SESSION['EC_USER']['telefone']      = $COUNTRY['phone_prefix'].$_POST['telefone'];
        $_SESSION['EC_USER']['nif']           = $_POST['nif']; 
        $_SESSION['EC_USER']['f_cartao']      = $_POST['cartao_id']; 
        $_SESSION['EC_USER']['f_code']        = $_POST['cartao_num']; 
        $_SESSION['EC_USER']['estado_cartao'] = 1;


        # Quero aderir
        if(!isset($_POST['typevalidation'])){
            #Ficheiro da raiz funcs_external.php incluido no required_files.php
            if(is_callable('checkoutValidateClientCard')) {
            
                $arr = array();
                $arr["id"]            = $_SESSION["EC_USER"]["id"];  
                $arr["cartao_num"]    = $_SESSION["EC_USER"]["f_code"];
                $arr["telefone"]      = $_SESSION["EC_USER"]["telefone"];
                $arr["nif"]           = $_SESSION["EC_USER"]["nif"];
                $arr["email"]         = $_SESSION["EC_USER"]["email"];
                
                call_user_func('checkoutValidateClientCard', $arr);
            }     
        }
        
        return serialize(array("0"=>"1"));



    # Associação de cartão cliente através de: "Telemóvel e NIF"
    } else {

        $continue = true;    
        
        #Ficheiro da raiz funcs_external.php
        if(is_callable('checkoutValidateClientCard')) {
            $continue = call_user_func('checkoutValidateClientCard', $_POST);
        }
        
        if($continue==true){
            
            $codigos_links = __get_code_link();
            $page_acc = call_api_func("get_line_table","ec_rubricas", "id='27'");
            $estado_cartao = 1; # Pendente
                    
            if((int)$page_acc["embform"]==1){

                $estado_cartao = 2; # Aprovado

            }else if((int)$page_acc["embform"]==2){

                __client_card_send_email($codigos_links);

            }else if((int)$page_acc["embform"]==3){

                __client_card_send_sms($codigos_links);
            }

            cms_query("UPDATE _tusers SET f_cartao='".$_POST['cartao_id']."', f_code='".$_POST['cartao_num']."', nif='".$_POST['nif']."', telefone='".$COUNTRY['phone_prefix'].$_POST['telefone']."', estado_cartao='".$estado_cartao."', token_cartao='".$codigos_links['codigo_sms_no_space']."', data_cartao=NOW() WHERE id='".$_SESSION['EC_USER']['id']."'");
            
            $_SESSION['EC_USER']['telefone']      = $COUNTRY['phone_prefix'].$_POST['telefone'];
            $_SESSION['EC_USER']['nif']           = $_POST['nif'];
            $_SESSION['EC_USER']['f_cartao']      = $_POST['cartao_id'];
            $_SESSION['EC_USER']['f_code']        = $_POST['cartao_num'];
            $_SESSION['EC_USER']['estado_cartao'] = $estado_cartao;
            
            return serialize(array("0"=>"1"));

        }

    }


    return serialize(array("0"=>"0"));
}



# enviar email
function __client_card_send_email($codigos_links){
    global $LG, $pagetitle, $sslocation;

    $data = array(
        "lg"                    => $LG,
        "email_cliente"         => $_SESSION["EC_USER"]["email"],
        "id_cliente"            => $_SESSION["EC_USER"]["id"],
        "CLIENT_NAME"           => $_SESSION["EC_USER"]["nome"],
        "LINK_AUTO_BUTTON"      => $codigos_links['link'],
        "LINK_CONFIRMACAO"      => $codigos_links['link'],
        "NOME"                  => $_SESSION["EC_USER"]["nome"],
        "PAGETITLE"             => $pagetitle
    );

    $data = serialize($data);
    $data = gzdeflate($data,9);
    $data = gzdeflate($data,9);
    $data = urlencode($data);
    $data = base64_encode($data);

    require_once _ROOT.'/api/lib/client/client_rest.php';
    $r = new Rest($sslocation.'/api/api.php');
    $r->get("/sendEmail/32/$data");
}


# enviar sms
function __client_card_send_sms($codigos_links){
    
    global $LG, $pagetitle, $COUNTRY;

    require_once $_SERVER["DOCUMENT_ROOT"].'/plugins/sms/sms_new.php';
    $SMS = new SMS();
    $link_curto = $SMS->getUrl($codigos_links['link'].'&sms=1', '');


    # 2025-01-29
    $id_pais_prefix = $COUNTRY['id'];
    if((int)$_SESSION["EC_USER"]['pais_indicativo_tel'] > 0) $id_pais_prefix = $_SESSION["EC_USER"]['pais_indicativo_tel'];

    $data = array(
        "telemovel"             => $_POST['telefone'],
        "lg"                    => $LG,
        "codigo_cartao_cliente" => $codigos_links['codigo_sms'],
        "LINK_CONFIRMACAO"      => $link_curto,
        "NOME"                  => $_SESSION["EC_USER"]["nome"],
        "USER_ID"               => $_SESSION["EC_USER"]["id"],
        "PAGETITLE"             => $pagetitle,
        "int_country_id"        => $id_pais_prefix
    );

    $data = serialize($data);
    $data = gzdeflate($data,9);
    $data = gzdeflate($data,9);
    $data = urlencode($data);
    $data = base64_encode($data);

    require_once '_sendSMSGeneral.php';
    _sendSMSGeneral("33", $data);
}


# gerar código sms e o link de email
function __get_code_link(){
    global $sslocation;

    $codigo_sms = trim(getCodigo());
    $codigo_sms_no_space = str_replace(' ', '', $codigo_sms);
    $codigo_sms_no_space = md5($_SESSION["EC_USER"]["id"].$_SESSION["EC_USER"]["email"].$codigo_sms_no_space);
    $link = $sslocation."/api/action_card_client.php?token=".$codigo_sms_no_space;

    $resp = array(
        "codigo_sms"            => $codigo_sms,
        "codigo_sms_no_space"   => $codigo_sms_no_space,
        "link"                  => $link
    );

    return $resp;
}


?>
