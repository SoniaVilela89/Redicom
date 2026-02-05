<?
function _sendWishlistPublic(){
    global $fx;
    global $LG;
    global $MARKET;
    global $userID;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/emails_marketing', false, _ROOT.'/temp_twig/');

    #Para evitar a submissão excessiva de formularios
    $key = md5(base64_encode($_SERVER[REMOTE_ADDR] . "form"));
    $x = 0 + @apc_fetch($key);
    if ($x>3){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 60);
    }

    if(empty($_POST)){
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    }


    if(!isset($_POST['name'])  || empty($_POST['name'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['email']) || empty($_POST['email'])) return serialize(array("0"=>"0"));
    if(!isset($_POST['friend_name'])  || empty($_POST['friend_name'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['friend_email'])  || empty($_POST['friend_email'] )) return serialize(array("0"=>"0"));

    $DADOS = $_POST;
    
  
    $DADOS['message']      = str_replace("\\n\\n", " ", $DADOS['message']);
    $DADOS['message']      = str_replace("\\r\\n", " ", $DADOS['message']);
    $DADOS['message']      = str_replace("\\n", " ", $DADOS['message']);
    $DADOS['message']      = str_replace("\\r", " ", $DADOS['message']);
    $DADOS['message']      = str_replace("\\t", " ", $DADOS['message']);


    $temp_email   = call_api_func("get_line_table","email_templates", "id='7'");
    $template     = '';
    $assunto      = '';
    if (!empty($temp_email)){
        $template = $temp_email['bloco'.$LG];
        $assunto  = $temp_email['assunto'.$LG];
        
        # VARIAVEIS DESCONTINUADAS - FICAM POR COMPATIBILIDADE 
        $assunto  = str_ireplace("{NOME}", $DADOS['name'], $assunto);

        $template = str_ireplace("{NOME_AMIGO}", $DADOS['friend_name'], $template);
        $template = str_ireplace("{NOME}", $DADOS['name'], $template);
        $template = str_ireplace("{MSG}", $DADOS['message'], $template);

        ######################################################
        
        
        
        $assunto       = str_ireplace("{NAME_TO}", $DADOS['name'], $assunto);
        
        $template     = str_ireplace("{NAME_TO}", $DADOS['name'], $template);
        $template     = str_ireplace("{NAME_FROM}", $DADOS['friend_name'], $template);
        $template     = str_ireplace("{MESSAGE}", $DADOS['message'], $template);
    }

    $user = array('id' => $_SESSION['EC_USER']['id'], 'email' => $DADOS['email']);

    $arr['items']             = call_api_func('OBJ_lines', 0, 2, 41);
    $arr['wishlist_public']   = call_api_func('getUserWish', $userID, $arr['items']);

    $y = get_info_geral_email($LG, $MARKET, '', $user);
    $y['LINK']        = $arr['wishlist_public']['link'].$more_link.$extra;
    $y['PRODUTOS']    = "";
    $y['DESCRICAO']   = $template;
    $y['TITULO']      = $assunto;
    $y['negar_exp']   = '';
    $y['FINALIZAR']   = estr(163);


    $html = $fx->printTwigTemplate("email_ld.html", $y, true, $_exp);


    $content = cms_real_escape_string(serialize($y));
    saveEmailInBD_Marketing($DADOS['friend_email'], $assunto, $content, $userID, 0, "Envio da lista de desejos", 1, 0, 'ld', 0, $y['view_online_code']);

    $arr    = array();
    $arr[]  = 1;
    return serialize($arr);

}


?>
