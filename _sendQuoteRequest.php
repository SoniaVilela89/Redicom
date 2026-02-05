<?

function _sendQuoteRequest(){
    
    global $LG, $MARKET, $COUNTRY, $fx, $pagetitle, $slocation, $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, 
            $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $Email_From;
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }                         
    
    #Para evitar a submissão excessiva de formularios
    $key = md5( base64_encode($_SERVER['REMOTE_ADDR']."sendQuoteRequest") );
    $x = 0 + @apc_fetch($key);
    if($x>3){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    }else{
        $x++;
        apc_store($key, $x, 60);
    }
    
    if( empty($_POST["pid"]) || empty($_POST["qnt"]) ){
        return serialize( array( "status" => 0 ) );
    }
    
    $email = call_api_func("get_line_table","email_templates", "id='13'");
    $template = '';
    if( trim($email['bloco'.$LG]) == '' || trim($email['assunto'.$LG]) == '' ){
        return serialize( array( "status" => 0 ) );  
    }
    
    $product = get_line_table("registos", "id='".$_POST["pid"]."'");
    if( (int)$product['id'] == 0 ){   
        return serialize( array( "status" => 0 ) );
    }
    
    $user = get_line_table("_tusers", "id='".$_SESSION['EC_USER']['id']."'");
    if( $user['id_user'] > 0 ){
        $user = get_line_table("_tusers", "id='".$user['id_user']."'");
    }
    
    $array_templates = array(); 
    if( is_dir(_ROOT.'/templates/emails') ) $array_templates[] = _ROOT.'/templates/emails';
    $array_templates[] = _ROOT.'/plugins/emails';
       
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    $logotipo = "/email/sysimages/logo_email_new_layout.jpg";             
    if(file_exists(_ROOT.'/images/logo_email_new_layout.jpg')){
        $logotipo = "/images/logo_email_new_layout.jpg";
    }
    
    $y                                      = array(); 
    $y['EMAILS_LOGO']                       = $logotipo;
    $y['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
    $y['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
    $y['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
    $y['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
    $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
    
    $y['nome_site']                         = $pagetitle;
    $y['url_site']                          = $slocation;  
    
    $y['template']['titulo']                = nl2br(strip_tags($email['assunto'.$LG]));
    $y['template']['descricao']             = nl2br(strip_tags($email['bloco'.$LG]));
    
    # Product Info
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getSingleImage.php';
    
    $imagem = _getSingleImage(60,60,3,$product['sku'],1);
    $imagem = str_replace('../', $slocation.'/', $imagem);
    
    $product_size   = get_line_table("registos_tamanhos", "id='".$product["tamanho"]."'");
    $product_color  = get_line_table("registos_cores", "id='".$product["cor"]."'");
    
    $y['product']['image']                  = $imagem;
    $y['product']['name']                   = $product['desc'.$LG];
    $y['product']['color']                  = $product_color['nome'.$LG];
    $y['product']['size']                   = $product_size['nome'.$LG];
    $y['product']['qnt']                    = (int)$_POST["qnt"];
    # Product Info
    
    # Customer Info
    $y['customer']['company_name']          = $user['nome'];
    $y['customer']['name']                  = $user['b2b_contacto'];
    $y['customer']['address']               = trim($user['morada1']." ".$user['morada2']);
    $y['customer']['postal_code']           = trim($user['cp']." ".$user['cidade']);
    $y['customer']['country']               = $COUNTRY['nome'.$LG];
    # Customer Info
    
    # Perguntas
    $y['perguntas'] = array();
    if(trim($email['perguntas'])!=''){
        
        $perg_s = "SELECT nome$LG, desc$LG FROM _tfaqs_emails WHERE id IN (".$email['perguntas'].") ORDER BY ordem, nome$LG ASC";
        $perg_q = cms_query($perg_s);
        while($perg = cms_fetch_assoc($perg_q)){
            $y['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
        }
        
    }
    
    $_exp           = array();
    $_exp['table']  = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang']   = $LG;
        
    $html = $fx->printTwigTemplate("quote.htm", $y, true, $_exp);     

    if( trim($MARKET['email_bcc']) != '' ){
        $em = preg_split("/[;,]/",$MARKET['email_bcc']);
        foreach($em as $k => $v){   
            saveEmailInBD($v, $email['assunto'.$LG], $html, $_SESSION['EC_USER']['id'], 0, $email['nomept'], '0', 'email_queue', 0, '', 0, '', 0, 13);
        }
    }else{
        saveEmailInBD($Email_From, $email['assunto'.$LG], $html, $_SESSION['EC_USER']['id'], 0, $email['nomept'], '0', 'email_queue', 0, '', 0, '', 0, 13);    
    }

    return serialize( array( "status" => 1 ) );
    
}

?>
