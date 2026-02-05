<?
function _sendFormLandingPage(){

    # Para evitar a submissão excessiva de formularios
    $key = "FORMLANDPAG_".md5(base64_encode($_SERVER['REMOTE_ADDR']));
    $x = 0 + @apc_fetch($key);
    if ($x>3) {
        $x++;
        apc_store($key, $x, 86400); # 1 dia
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 180);  # 3 minutos
    }


    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $_DOMAIN;
    global $slocation, $pagetitle;
    global $fx;
    global $_CHECKOUT_VER;
    global $CONFIG_TEMPLATES_PARAMS, $API_CONFIG_PASS_SALT, $db_name_cms;
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(decode_string_api($v));
    }

    if(!isset($_POST['name'])  || empty($_POST['name'] )) return serialize(array("success"=>"0", "error" => "#ERR1"));
    if(!isset($_POST['email']) || empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) return serialize(array("success"=>"0", "error" => "#ERR2"));
    if(!isset($_POST['lead'])) return serialize(array("success"=>"0", "error" => "#ERR3"));
    if(!isset($_POST['blockid'])  || empty($_POST['blockid'] )) return serialize(array("success"=>"0", "error" => "#ERR4"));

    $lead_id = (int)$_POST['lead'];
    $block_id = (int)$_POST['blockid'];
    $country_id = (int)$COUNTRY['id'];


    # Validar se cliente não submeteu o formulário
    if($block_id > 0) {
        $rlead = call_api_func("get_line_table","b2c_leads_lines_result", "email='".$_POST['email']."' AND id_block='".$block_id."'");
    } else {
        $rlead = call_api_func("get_line_table","b2c_leads_lines_result", "email='".$_POST['email']."' AND id_page='".$lead_id."'");
    }
    if($rlead["id"]>0){
        return serialize(array("success"=>"0", "error" => "#ERR5"));
    }


    $table_lines = "`$db_name_cms`.ContentBlocksLines";      

    $s     = "SELECT `Catalog2`, `Blocks_LinkButton2` FROM $table_lines WHERE `id`='%d' ";
    $s     = sprintf($s, $block_id);
    $block = cms_fetch_assoc(cms_query($s));
    
    $campanha_id = $block['Catalog2'];
    $campanha = call_api_func("get_line_table","ec_campanhas", "id='".$campanha_id."'");
    if((int)$campanha["id"]<1){
        return serialize(array("success"=>"0", "error" => "#ERR6"));
    }


    # Validar se cliente não está já registado  
    $verifca = call_api_func("get_line_table","_tusers", "email='".$_POST['email']."' AND sem_registo='0'");
    if($verifca['id']>0){
        return serialize(array("success"=>"0", "error" => "#ERR7"));  
    }

    $url = $block['Blocks_LinkButton2']; # url sucesso

    $phone = "";
    if(trim($_POST['phone'])!="") {
        if(!isset($_POST['country']) || empty($_POST['country'] )) return serialize(array("success"=>"0", "error" => "#ERR8"));
        $country_id = (int)$_POST['country'];
        $pais = call_api_func("get_line_table","ec_paises", "id='".$country_id."'");
        $phone = $pais['phone_prefix'].$_POST['phone'];
    }



    # Inserir registo nos dados submetidos
    $sql = "insert into b2c_leads_lines_result (nome,email,cidade,telefone,id_page,id_block) values ('%s','%s','%s','%s','%d','%d')";
    $sql = sprintf($sql, $_POST['name'], $_POST['email'], $_POST['city'], $phone, $lead_id, $block_id);
    cms_query($sql);


    $segmento_auto = call_api_func("get_line_table","_tusers_types", "nomept='{Landing Page}'");

    if((int)$segmento_auto['id']==0){
      cms_query("INSERT INTO `_tusers_types` (`fixo`,`nomept`, `nomegb`) VALUES (2, '{Landing Page}', '{Landing Page}')");
      $seg_id = cms_insert_id();
    }else{
      $seg_id = $segmento_auto['id'];
    }

    $q = cms_query("select id from ec_language where code='$LG' ORDER BY id ASC LIMIT 0,1");
    $idIdioma = cms_fetch_assoc($q);
    if( $LG=="gb" ) $idIdioma['id'] = "43";
    if( $LG=="sp" ) $idIdioma['id'] = "153";


    $password = $_POST['password'];
    if($_POST['password'] == "") {
        $password = crypt($_POST['email'].date("Y-m-d H:i:s"), $API_CONFIG_PASS_SALT);
    } else {

        # verifica a sintaxe da password
        if (check_password($password) == false) {
            header('HTTP/1.1 403 Forbidden');
            echo "ERROR: AR-1053";
            exit;
        }

    }


    $sql = "insert into _tusers set
                          origem_desc='LandingPage#".$lead_id."', 
                          accept_new='0',
                          nome='%s',
                          email='%s',
                          password='%s',
                          pais=%d,
                          cidade='%s',
                          telefone='%s',
                          sem_registo='%d',
                          tracking_campanha_url_id='%d',
                          tipo='%s',
                          cookie_funcionais='%d',
                          cookie_publicidade='%d',
                          rejeita_email_marketing='%d',
                          rejeita_email_marketing_vales='%d',
                          rejeita_sms_marketing='%d',
                          tipo_utilizador=2,
                          ip_client='%s',
                          browser_client='%s',
                          id_lingua='%d',
                          tracking_session_id='%s'";


    #Variaveis para renderizar segundo a politica de cookies    
    $show_cp_1 = (!isset($_SESSION['plg_cp_1']) ? '0' : $_SESSION['plg_cp_1'] );
    $show_cp_2 = (!isset($_SESSION['plg_cp_2']) ? '0' : $_SESSION['plg_cp_2'] );


    $arr_cookie_cpn = getCookieCPN();

    $q_user = sprintf($sql, $_POST['name'], $_POST['email'], crypt($password, $API_CONFIG_PASS_SALT), $country_id, $_POST['city'], $phone, 0, implode(',', array_keys($arr_cookie_cpn)), $seg_id, 
                    $show_cp_1, $show_cp_2, 0, 0, 0, $_SERVER["HTTP_X_REAL_IP"],$_SERVER["HTTP_USER_AGENT"], $idIdioma['id'], $_SESSION['traffic_tracked_session']);
    cms_query($q_user);
    $cliente_id = cms_insert_id();


    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/privacy/Log.php");
    $log = new Log();   
    $log->register_new_account($cliente_id);


    if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
      require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
      $azure = new AzureAD();   
      $azure->CreateUser($cliente_id, $password, $SocialLogin); 
    }



    # Email confirmação registo

    $este = cms_fetch_assoc(cms_query("SELECT * FROM `_tusers` WHERE `id`='$cliente_id'"));
    require_once _ROOT.'/api/controllers/_sendEmail.php';


    if($_POST['password'] == "") {

            $token = md5($este['id'].$este['email'].date("Ymd His"));
            cms_query("INSERT INTO ec_rec_pw set `token`='$token', `user_id`='".$este['id']."', `user_email`='LANDINGPAGE'");
            $link = $slocation."/checkout/".$_CHECKOUT_VER."?id=6&token=$token";
            $data = array(
                 "telemovel"        => $este['telefone'],
                 "lg"               => $LG,
                 "email_cliente"    => $este['email'],
                 "BUTTON_URL"       => $link,
                 "LINK_AUTO_BUTTON" => $link,
                 "CLIENT_NAME"      => $este['nome'],
                 "id_cliente"       => $cliente_id
            );
            $data = serialize($data);
            $data = gzdeflate($data, 9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);
            _sendEmail(36, $data);

    } else {

            $data = array(
                 "lg"               => $LG,
                 "email_cliente"    => $este['email'],
                 "CLIENT_NAME"      => $este['nome'],
                 "id_cliente"       => $cliente_id
            );
            $data = serialize($data);
            $data = gzdeflate($data, 9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);
            _sendEmail(4, $data);

    }



    # Inserir registo na campanha de desconto
    $camp = "insert into ec_campanha_clientes (campanha_id,email_cliente,id_cliente) values ('".$campanha['id']."','".$_POST['email']."', '".$cliente_id."')";
    cms_query($camp);



    # Envio de email
    if($campanha['ofer_tipo']==3){ #Voucher
        if($campanha['ofer_valor']>0){
            $valor        = $campanha['ofer_valor'];
            $tipo_oferta  = 2;
        }elseif($campanha['ofer_perc']>0){
            $valor        = $campanha['ofer_perc'];
            $tipo_oferta  = 1;
        } else {
            $tipo_oferta = 0;
        }
    }else {
        $tipo_oferta = 0;
    }      


    if($tipo_oferta>0){

        $base_codigo = base64_encode($campanha['id'].'|||'.$cliente_id.'|||'.$_POST['email']);
        $more_link   = '&m2code='.$base_codigo.'&utm_medium=email&utm_source=Redicom%20Landing%20Page'.$lead_id.'&utm_campaign='.urlencode($campanha['titulo']);

        if($tipo_oferta == 1){
           $valor = floatval($valor).'%';
        }else{
            $valor = floatval(number_format($valor,2,'.',''));
            $valor = $MOEDA['prefixo'].$valor.$MOEDA['sufixo'];
            $valor = str_replace("€", "&euro;", $valor);
            $valor = str_replace("£", "&pound;", $valor);
        }

        $extra            = '&vc='.$campanha['crit_codigo'];
        $user             = array('id' => $cliente_id, 'email' => $_POST['email'], 'nome' => $_POST['name']);
        $y                = get_info_geral_email($LG, $MARKET, $campanha, $user, $extra);

        $y['LINK']        = $slocation.'/account/?id=11'.$more_link.$extra;
        $y['CODIGO']      = $campanha['crit_codigo'];
        $y['DESCONTO']    = $valor;
        $y['negar_exp']   = '';

        $content = cms_real_escape_string(serialize($y));

        $nome_cliente = explode(' ', $_POST['name']);

        $assunto = $campanha['email_assunto'.$LG];
        $assunto = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $assunto);
        
        if(trim($campanha['email_assunto'.$LG])==''){
            return serialize(array("success"=>"1", "url" => $url));
        }

        saveEmailInBD_Marketing($_POST['email'], $assunto, $content, $cliente_id, 0, "Landing Page", 1, 0, 'lp', $campanha['id'], $y['view_online_code']);

    }


    # Login automatico
    $eComm  = new SiteECOMMERCE();
    $sql = cms_query("select * from _tusers where id='".$cliente_id."' AND activo='1' LIMIT 0,1");
    $_usr = cms_fetch_assoc($sql);
    $eComm->createUserSession($_usr);


    return serialize(array("success"=>"1", "url" => $url));

}



?>
