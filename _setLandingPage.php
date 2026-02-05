<?
function _setLandingPage(){


    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $_DOMAIN;
    global $slocation, $pagetitle;
    global $fx;
    global $_CHECKOUT_VER;
    global $CONFIG_TEMPLATES_PARAMS;
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(decode_string_api($v));
    }

    if(!isset($_POST['name'])  || empty($_POST['name'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['email']) || empty($_POST['email'])) return serialize(array("0"=>"0"));
    #if(!isset($_POST['city'])  || empty($_POST['city'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['lead'])  || empty($_POST['lead'] )) return serialize(array("0"=>"0"));
    
    # Validar se cliente não submeteu o formulário
    $rlead = call_api_func("get_line_table","b2c_leads_lines_result", "email='".$_POST['email']."' AND id_page='".$_POST['lead']."'");
    if($rlead["id"]>0){
        return serialize(array("0"=>"0"));
    }
    
    
    $lead     = call_api_func("get_line_table","b2c_leads_lines", "id='".$_POST['lead']."'");
    
    $campanha = call_api_func("get_line_table","ec_campanhas", "id='".$lead['campanha']."'");
    
    if((int)$campanha["id"]<1){
        return serialize(array("0"=>"0"));
    }



    # 2020-03-11 - Criação automática de registo 
    if($lead['criar_registo_auto']==1){
    
        # Validar se cliente não está já registado  
        $verifca = call_api_func("get_line_table","_tusers", "email='".$_POST['email']."' AND sem_registo='0'");
        if($verifca['id']>0){
            return serialize(array("0"=>"0"));  
        }
        
    }
    
    # 2020-03-12 - URL de sucesso para location
    $url = $lead['url_sucesso'];

    $phone = "";
    if(trim($_POST['phone'])!="") $phone = $COUNTRY['phone_prefix'].$_POST['phone'];
    
    # Inserir registo nos dados submetidos
    $sql = "insert into b2c_leads_lines_result (nome,email,cidade,telefone,id_page) values ('".$_POST['name']."','".$_POST['email']."','".$_POST['city']."','".$phone."','".$_POST['lead']."')";
    cms_query($sql);
    
    

    $cliente_id = 0;
    
    if($lead['criar_registo_auto']==1){
     
          $segmento_auto = call_api_func("get_line_table","_tusers_types", "nomept='{Landing Page}'");

          $password = $_POST['password'];

          # verifica a sintaxe da password
          if (check_password($password) == false) {
            header('HTTP/1.1 403 Forbidden');
            echo "ERROR: AR-1053";
            exit;
          }
          
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
    
          $sql = "insert into _tusers set
                                  origem_desc='LandingPage#".$lead['id']."', 
                                  accept_new='1',
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

          $q_user = sprintf($sql, $_POST['name'], $_POST['email'], crypt($password), $COUNTRY['id'], $_POST['city'], $phone, 0, implode(',', array_keys($arr_cookie_cpn)), $seg_id, 
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
          
          
          
          
          $email = __getEmailBody(4, $LG);

          $email['blocopt'] = str_ireplace("{CLIENT_NAME}", $_POST['name'], $email['blocopt']);
          $email['blocopt'] = str_ireplace("{NOME}", $_POST['name'], $email['blocopt']);
          $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);
      
          $email['nomept'] = str_ireplace("{CLIENT_NAME}", $_POST['name'], $email['nomept']);
          $email['nomept'] = str_ireplace("{NOME}", $_POST['name'], $email['nomept']);
             
          $email['blocopt'] = nl2br($email['blocopt']);
              
          $auto_button = '';
          if($email['btt_titulo'.$LG]!='' && $email['btt_url']!=''){
              $auto_button = '<div class="em-button-border"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$email['btt_url'].'" style="height:50px;v-text-anchor:middle;width:200px;" arcsize="8%" stroke="f" fillcolor="#000000"><w:anchorlock/><center><![endif]--><a href="'.$email['btt_url'].'" target="_blank" style="background-color:#000000;border-radius:4px;color:#ffffff;display:inline-block;font-family: Arial, Helvetica, sans-serif;font-size:16px;font-weight:normal;line-height:50px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;" class="em-button">'.$email['btt_titulo'.$LG].'</a><!--[if mso]></center></v:roundrect><![endif]--></div>';
          }
          $email['blocopt'] = str_ireplace("{AUTO_BUTTON}", $auto_button, $email['blocopt']);
      
          sendEmailFromController($email['blocopt'], $email['nomept'], $_POST['email'], "", $cliente_id, "Registo");
    
          

    }
    
    

    # Inserir registo na campanha de desconto
    $camp = "insert into ec_campanha_clientes (campanha_id,email_cliente,id_cliente) values ('".$campanha['id']."','".$_POST['email']."', '".$cliente_id."')";
    cms_query($camp);
                    
                                      
    #Envio de email
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

        $more_link    = '&m2code='.$base_codigo.'&utm_medium=email&utm_source=Redicom%20Landing%20Page%20'.$lead['id'].'&utm_campaign='.urlencode($campanha['titulo']);


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
            return serialize(array("0"=>"1", "url" => $url));
        }
        
        
        saveEmailInBD_Marketing($_POST['email'], $assunto, $content, $cliente_id, 0, "Landing Page", 1, 0, 'lp', $campanha['id'], $y['view_online_code']);        

    }

    return serialize(array("0"=>"1", "url" => $url));

}



?>
