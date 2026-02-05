<?
function _sendFormOffer(){

    global $fx;
    global $LG;
    global $MARKET, $slocation;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(decode_string_api($v));
    }
    
    #Para evitar a submissão excessiva de formularios
    $key  = md5(base64_encode($_SERVER[REMOTE_ADDR] . "form"));
    $x    = 0 + @apc_fetch($key);
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
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }

    if(!isset($_POST['name'])  || empty($_POST['name'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['email']) || empty($_POST['email'])) return serialize(array("0"=>"0"));
    if(!isset($_POST['friend_name'])  || empty($_POST['friend_name'] )) return serialize(array("0"=>"0"));
    if(!isset($_POST['friend_email'])  || empty($_POST['friend_email'] )) return serialize(array("0"=>"0"));
    
    $DADOS = $_POST;
    
    
    $produtos = array();    
    if($DADOS["product_id"]!=""){
        $prod = call_api_func('get_product', $DADOS["product_id"]);

        if($prod["id"]<1){ return serialize(array("0"=>"0"));}
        
        get_layout_prod($produtos, $prod);

    }else{
        return serialize(array("0"=>"0"));
    }
    
    if(count($produtos)<1) return serialize(array("0"=>"0"));

    $produtos_f = array();
    foreach($produtos as $k => $v){
        $produtos_f[] = $v;
    }
    
    $temp_email = call_api_func("get_line_table","email_templates", "id='6'");
    $template   = '';
    if (!empty($temp_email)){
                                
        $assunto = $temp_email['assunto'.$LG];
        
        $template = $temp_email['bloco'.$LG];
        
        $DADOS['message'] = str_replace("\\n", " ", $DADOS['message']);
        $DADOS['message'] = str_replace("\\r", " ", $DADOS['message']);
        $DADOS['message'] = str_replace("\\t", " ", $DADOS['message']);        
        
        
        
        # VARIAVEIS DESCONTINUADAS - FICAM POR COMPATIBILIDADE 
        $assunto = str_ireplace("{NOME}", $DADOS['name'], $assunto);
        
        $template = str_ireplace("{NOME_AMIGO}", $DADOS['friend_name'], $template);
        $template = str_ireplace("{NOME}", $DADOS['name'], $template);
        $template = str_ireplace("{MSG}", $DADOS['message'], $template);   
        
        ######################################################
        
                
        $assunto      = str_ireplace("{NAME_TO}", $DADOS['friend_name'], $assunto);
        $template     = str_ireplace("{NAME_TO}", $DADOS['friend_name'], $template);
        $template     = str_ireplace("{NAME_FROM}", $DADOS['name'], $template);         
        
        
        $template     = str_ireplace("{MESSAGE}", $DADOS['message'], $template);
    }
    
    $user = array('id' => 0, 'email' => $DADOS['email']);
    $y = get_info_geral_email($LG, $MARKET, '', $user);
    $y['LINK']        = $slocation.'/'.$prod['url'].$more_link.$extra;
    $y['PRODUTOS']    = $produtos_f;
    $y['SUBTITULO']   = $template;
    $y['TITULO'] = $assunto;
    $y['negar_exp']   = '';
    $y['FINALIZAR']   = estr(126);


    $content = cms_real_escape_string(serialize($y));
    saveEmailInBD_Marketing($DADOS['friend_email'], $temp_email['assunto'.$LG], $content, "", 0, "Pedido de produto", 1, 0, 'sa', 0, $y['view_online_code']);

    $arr    = array();
    $arr[]  = 1;
    return serialize($arr);

}


?>
