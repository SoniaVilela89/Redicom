<?

function _viewEmail($template_id=0){
    
    global $slocation, $fx, $pagetitle, $MARKET, $LG, $SIGLA_SITE, $EMAIL_CONTACT_PAGE, $B2B, $CONFIG_TEMPLATES_PARAMS;  
    global $EMAILS_LAYOUT_SEND, $db_name_cms;  
    
    if ($template_id > 0){
       $template_id = (int)$template_id;
    }else{
       $template_id = (int)params('template_id');
    }
    
    $arr = array();
    $arr['0'] = 0;
               
    if($template_id<1){
        return $arr;
    }
    
    
    $data           = base64_decode($data);
    $data           = urldecode($data);
    $data           = gzinflate($data);
    $data           = gzinflate($data);
    $data           = unserialize($data);
    
    $_lg            = $data[lg];
    
    $_lg            = 'pt';
    
    $lang = params('lang');
    if (strlen($lang)>1)
        $LG = $_lg = $lang;
    
    
    $email_site = (int)params('emailSite');
    
    if((int)$email_site == 1){
        $temp_email = call_api_func("get_line_table","email_templates", "id='".$template_id."'"); 
    }else{
        $temp_email = call_api_func("get_line_table","ec_email_templates", "id='".$template_id."'");
    }

    
    $array_confirmacoes = array(1,14,38,54,55,65,75,83,87,91,101,105);
    $array_conta = array(4,2,3,5,32,34,36,52,60,61,67,73,81,98,100,104,108,109);
    
    if($email_site==0 && in_array($template_id, $array_confirmacoes) ){
    
        if((int)$B2B==0 && ($temp_email['new_layout']==1 || (int)$EMAILS_LAYOUT_SEND>0)){
            
            if($template_id==55){
                $html = __viewEmailConfirmationDEVNew($temp_email, $LG);
            }else{     
                $html = __viewEmailConfirmationEncNew($temp_email, $LG);
            }
            
            $data = base64_encode($html);
      
            $arr = array();
            $arr['0'] = $data;
        
            return serialize($arr);
        
        }
    
    # 09-04-2021 - Retirado o template 21 para exibir com o layout dos emails marketing
    }elseif($email_site==0  && (int)$EMAILS_LAYOUT_SEND>0 && !in_array($template_id, array(21, 72))){

    
        if($email_site==0 && in_array($template_id, $array_conta)){
            $html = __viewEmailUser($temp_email, $LG);
        }else{                                   

            $html = __viewEmailGest($temp_email, $LG);
        }
        
        $data = base64_encode($html);
        
        $arr = array();
        $arr['0'] = $data;
        
        return serialize($arr);
         
    }          
    
    
    
    $html       = nl2br($temp_email['bloco'.$_lg]);
    $_assunto   = $temp_email['assunto'.$_lg];
    $_toemail   = $data['email_cliente'];                   
    
    if(trim($html)=='' && !in_array($template_id, array(72))){
        return serialize(array("0"=>"0"));
    }
    
    $html = ___replaceData($data, $html);

    # inc_titles.htm do email (titulo e número de encomenda)
    if($email_site==0 && in_array($template_id, $array_confirmacoes) ){

        $_exp_emails           = array();
        $_exp_emails['table']  = "exp_emails";
        $_exp_emails['prefix'] = "nome";
        $_exp_emails['lang']   = $LG;

        $t['template']['titulo']    = str_replace("{ORDERID}", '', $temp_email['assunto'.$_lg]);
        $t['template']['titulo']    = str_replace("{ORDER_REF}", '', $t['template']['titulo']);
        $t['template']['titulo']    = str_replace("{ORDER_REF}", '', $t['template']['titulo']);
        $t['template']['titulo']    = trim(strip_tags($t['template']['titulo']));
        $t['template']['cabecalho'] = "[@1] #ODPT2099/000001";
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/emails', false, _ROOT.'/temp_twig/');
        $titles = $fx->printTwigTemplate("inc_titles.htm", $t, true, $_exp_emails);
        $html = $titles.$html;

    }

    $html = str_replace("{PAGETITLE}", $pagetitle, $html);
    
    $html = str_replace("{CLIENT_NAME}", "(nome do cliente)", $html);
    $html = str_replace("{TOKEN}", "123456", $html);
    $html = str_replace("{ORDER_REF}", "ODPT2099/000001", $html);
    $html = str_replace("{RETURN_REF}", "RTPT2099/000001", $html);
    
    $html = str_replace("{ENTITY}", "000001", $html);
    $html = str_replace("{REFERENCE}", "123 456 789", $html);
    $html = str_replace("{ORDER_TOTAL}", "99.99 Eur", $html);
    
    $html = str_replace("{SWIFT}", "XPTOPTPL", $html);
    $html = str_replace("{IBAN}", "PT50 0027 0000 00012345678 33", $html);
    
    $html = str_replace("{CLIENT_DELIVERY_ADDRESS}", "Rua da Liberdade, nº540, 1234-456 Porto Portugal", $html);
    $html = str_replace("{SHIPPING_METHOD}", "Entrega ao domicílio", $html);
    
    
    $html = str_replace("{ORDER_TRACKING_NUMBER_AND_LINK}", "TKNGNMBR0001 - https://www.transportadora.pt/trace/", $html);
    $html = str_replace("{ORDER_TRACKING_NUMBER}", "TKNGNMBR0001", $html);
    $html = str_replace("{ORDER_TRACKING_LINK}", "ORDER_TRACKING_LINK (descontinuado)", $html);
    
    $html = str_replace("{STORE_CODE}", "123456", $html);    
    $html = str_replace("{STORE_NAME}", "(nome da loja)", $html);
    
    $html = str_replace("{CODE}", "COD001ABC", $html);
    
    $html = str_replace("{DISCOUNT_CODE}", "VL001ABC", $html);
    $html = str_replace("{DISCOUNT_TOTAL}", "9.99 Eur", $html);
    
    $html = str_replace("{DISCOUNT_CODE}", "VL001ABC", $html);
    $html = str_replace("{DISCOUNT_DATE}", "2999-01-01", $html);
    $html = str_replace("{DISCOUNT_REMAINING_AMOUNT}", "9.99 Eur", $html);
    
    $html = str_replace("{REFUND_TOTAL}", "9.99 Eur", $html);
    $html = str_replace("{RETURN_TOTAL}", "9.99 Eur", $html);
    
    $html = str_replace("{PRODUCT_NAME}", "(nome do produto)", $html);
    $html = str_replace("{PRODUCT_SKU}", "(SKU do produto)", $html);

    
    #$html = nl2br($html);
        
    $auto_button = '';
    if($temp_email['btt_titulo'.$_lg]!='' && $temp_email['btt_url']!=''){
        $auto_button = '<div class="em-button-border"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$temp_email['btt_url'].'" style="height:50px;v-text-anchor:middle;width:200px;" arcsize="8%" stroke="f" fillcolor="#000000"><w:anchorlock/><center><![endif]--><a href="'.$temp_email['btt_url'].'" target="_blank" style="background-color:#000000;border-radius:4px;color:#ffffff;display:inline-block;font-family: Arial, Helvetica, sans-serif;font-size:16px;font-weight:normal;line-height:50px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;" class="em-button">'.$temp_email['btt_titulo'.$_lg].'</a><!--[if mso]></center></v:roundrect><![endif]--></div>';
    }
    $html = str_ireplace("{AUTO_BUTTON}", $auto_button, $html);
    
    

    ### email_templates
    $html = str_replace("{NAME_TO}", "(nome do destinatário)", $html);
    $html = str_replace("{NOME_AMIGO}", "(nome do destinatário)", $html);
    $html = str_replace("{NAME_FROM}", "(nome do remetente)", $html);
    $html = str_replace("{NOME}", "(nome do remetente)", $html);
    
    $message_wishlist = "Queres oferecer-me um presente e não sabes o quê?<br />Vê aqui a minha lista de desejos e escolhe um para mim :)";
    if($email_site == 1 && $template_id == 6) $message_wishlist = "Queres oferecer-me um presente e não sabes o quê?<br />Vê aqui o meu produto favorito :)";
    $html = str_replace("{MSG}", $message_wishlist, $html);
    
    $html = str_replace("{CODIGO}", "COD001ABC", $html);
    
    $html = str_replace("{DATA}", "2999-01-01", $html);
    
    $_assunto = str_replace("{NAME_TO}", "(nome do remetente)", $_assunto);
    $_assunto = str_replace("{NOME}", "(nome do remetente)", $_assunto);
    ### email_templates
    
                 

    $x['SIGLA_SITE']            = $SIGLA_SITE;
    $x['slocation']             = $slocation;
    $x['PAGETITLE']             = $pagetitle;
    $x['BLOCO']                 = $html;
    $x['exp_1']                 = estr(176); //Todos os direitos reservados
    $x['exp_2']                 = estr(56); //Por favor não responda directamente a este e-mail, pois trata-se de um e-mail automático.<br>Utilize o nosso formulário de contacto:
    $x['exp_3']                 = estr(57); //Clique aqui para aceder
    $x['EMAIL_CONTACT_PAGE']    = $EMAIL_CONTACT_PAGE;
    $x['SERVIDOR']              = $_SERVER['DOCUMENT_ROOT']; 
    
    $exp                        = array();
    $exp['table']               = "exp";
    $exp['prefix']              = "nome";
    $exp['lang']                = $_lg;
    

    if($email_site == 1 && in_array($template_id, array(5,6,7,10,11,13,15))){

        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/emails_marketing', false, _ROOT.'/temp_twig/');
        
        $x = array_merge(get_info_geral_email($LG, $MARKET), $x);
        $x['path'] = $slocation.$x['path'];
        
        
        $table_style = "`$db_name_cms`.b2c_config_email_template";  
        $email_style = call_api_func("get_line_table", $table_style, "id=1");
        
        $x['email_style'] = $email_style;

        $x['LINK']        = $slocation;

        if($template_id == 5){

            $template_file = 'eg';
            $new_version = 0;

            $sql_template = "SELECT campo_3 FROM `b2c_config_loja` WHERE `tipo` LIKE 'base' LIMIT 0,1";
            $res_template = cms_query($sql_template);
            $row_template = cms_fetch_assoc($res_template);

            if((int)$row_template["campo_3"] == 12){
                $template_file = 'eg_12';
                $new_version = 1;
            }            

            $egift = array( "de"        => "(nome do remetente)",
                            "para"      => "(nome do destinatário)",
                            "valor"     => "99 Eur",
                            "codigo"    => "COD001ABC",
                            "mensagem"  => "Parabéns!\nDesejo-te um Feliz Aniversário :)" );
            
            $cartao = call_api_func("get_line_table","ec_egift_desenho", "activo=1");
            
            require_once "_setEGift.php";
            $data_validade = '2999-01-01';                        
            if($new_version == 1){
                $code = $egift["codigo"];
                $img_barcode        = create_barcode39($code);                
                $imagem_cartao      = _createImage_new_layout($cartao, $egift, "0_".$LG);    #Linha da encomenda

                $egift["img_card"]  = $imagem_cartao;
     
                $egift['mensagem']      = nl2br($egift['mensagem']);
                $egift['mensagem']      = strip_tags($egift['mensagem']);
                $egift['mensagem']      = str_replace("\\n\\n", " ", $egift['mensagem']);
                $egift['mensagem']      = str_replace("\\r\\n", " ", $egift['mensagem']);
                $egift['mensagem']      = str_replace("\\n", "\n", $egift['mensagem']);
                $egift['mensagem']      = str_replace("\\r", " ", $egift['mensagem']);
                $egift['mensagem']      = str_replace("\\t", " ", $egift['mensagem']);
    
                $egift["img_barcode"] = $img_barcode;
                $egift["validade"] = $data_validade;
                $egift["msg_template"] = "$template";
                $egift["name_file"] = base64_encode($enc["order_id"]."|||".$enc["id"]);

                $open_file_data = array(
                    "file" => $pdf_egift, 
                    "name" => utf8_encode("E-gift").".pdf"
                );
        
                $link = $slocation."/api/open_file.php?params=".base64_encode( serialize( $open_file_data ) );        
                
                $exp_finalizar = nl2br(estr(676));
    
            }else{
                $imagem_cartao  = _createImage($cartao, $egift, "0_".$LG);
                $link = $slocation;
                $exp_finalizar = nl2br(estr(126));
            }

            $x['location']      = $slocation;
            $x['IMAGEM']        = $slocation."/".$imagem_cartao."?".rand();
            $x['TITULO']        = nl2br(estr(204));
            $x['SUBTITULO']     = nl2br(estr(211));
            $x['DESCRICAO']     = $html;
            $x['FINALIZAR']     = $exp_finalizar;

            $x['GIFT_CODE']     = $code;
            $x['GIFT_BARCODE']  = $img_barcode;
            $x['GIFT_MESSAGE']  = $egift['mensagem'];
            $x['GIFT_VALIDATE'] = estr(672)." ".$data_validade;
        
            $data = $fx->printTwigTemplate("email_$template_file.html", $x, true, $exp);

        }elseif($template_id == 6){
            
            $produtos_f = array();
            
            $prod_pid = cms_fetch_assoc(cms_query("SELECT pid FROM ec_encomendas_lines WHERE order_id > 0 ORDER BY id DESC LIMIT 0,1"));
            $prod = call_api_func('get_product', $prod_pid["pid"]);
            if($prod["id"]>0){
                $produtos = array();
                get_layout_prod($produtos, $prod);
                
                foreach($produtos as $k => $v){
                    $produtos_f[] = $v;
                }
            }
            
            $x['PRODUTOS']    = $produtos_f;
            $x['SUBTITULO']   = $html;
            $x['TITULO']      = $_assunto;
            $x['negar_exp']   = '';
            $x['FINALIZAR']   = estr(126);
            
            $data = $fx->printTwigTemplate("email_sa.html", $x, true, $exp);
            
        }elseif($template_id == 7){
            
            $x['PRODUTOS']    = "";
            $x['DESCRICAO']   = $html;
            $x['TITULO']      = $_assunto;
            $x['negar_exp']   = '';
            $x['FINALIZAR']   = estr(163);
            
            $data = $fx->printTwigTemplate("email_ld.html", $x, true, $exp);
            
        }elseif($template_id == 10){
        
            $x['CODIGO']      = "COD001ABC";
            $x['DESCONTO']    = "10%";
            $x['negar_exp']   = '';
            $x['SUBTITULO']   = $html;
            $x['TITULO']      = $_assunto;
            $x['estr_1']      = estr(185);        
            $x['FINALIZAR']   = estr2(37);
            $x['DESCRICAO']   = estr(120).' '.strtolower(estr(266)).': 2999-01-01';

            $data = $fx->printTwigTemplate("email_pt.html", $x, true, $exp);
            
        }elseif($template_id == 11 || $template_id == 15){            
            
            $date = date_create("2999-01-01");
            $date = date_format($date,"d-m-Y");
            
            if($LG=='gb'){
                $date = strftime("%d %B, %Y",strtotime($date));                  
            }else{
                setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
                $date = strftime("%d  %B  %Y",strtotime($date));   
            }
            
            $hora = "10:10";
            
            $scheduling['nome'.$LG] = "(nome do agendamento)";
            
            $SCHEDULING = get_template_scheduling($scheduling, $date, $hora, $slocation."/");
            
            $html = str_replace("{SCHEDULING}", $SCHEDULING, $html);
            
            $x['LINK']        = "";
            $x['DESCRICAO']   = $html;
            $x['TITULO']      = $_assunto;
            $x['negar_exp']   = '';
            $x['FINALIZAR']   = "";

            $data = $fx->printTwigTemplate("email_schedule.html", $x, true, $exp);
            
        }elseif($template_id == 13){
            
            $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/emails', false, _ROOT.'/temp_twig/');
            
            $prod_ref = cms_fetch_assoc(cms_query("SELECT ref FROM ec_encomendas_lines WHERE order_id > 0 ORDER BY id DESC LIMIT 0,1"));
            $product = get_line_table("registos", "sku='".$prod_ref["ref"]."'");
            
            if($CONFIG_TEMPLATES_PARAMS['site_version']>23){
                $logotipo = "/images/logo_email@2x.png";             
            }else{
                $logotipo = "/email/sysimages/logo_email_new_layout.jpg";             
                if(file_exists(_ROOT.'/images/logo_email_new_layout.jpg')){
                    $logotipo = "/images/logo_email_new_layout.jpg";
                }  
            } 
            
            $x['EMAILS_LOGO']                       = $logotipo;
            
            $x['url_site']                          = $slocation;
            
            $x['template']['titulo']                = nl2br(strip_tags($temp_email['assunto'.$LG]));
            $x['template']['descricao']             = nl2br(strip_tags($temp_email['bloco'.$LG]));
            
            # Product Info            
            require_once _ROOT.'/api/controllers/_getSingleImage.php';
             
            $imagem = _getSingleImage(60,60,3,$product['sku'],1);
            $imagem = str_replace('../', $slocation.'/', $imagem);
            
            $product_size   = get_line_table("registos_tamanhos", "id='".$product["tamanho"]."'");
            $product_color  = get_line_table("registos_cores", "id='".$product["cor"]."'");
    
            $x['product']['image']                  = $imagem;
            $x['product']['name']                   = $product['desc'.$LG];
            $x['product']['color']                  = $product_color['nome'.$LG];
            $x['product']['size']                   = $product_size['nome'.$LG];
            $x['product']['qnt']                    = 3;
            # Product Info
            
            # Customer Info
            $x['customer']['company_name']          = "(nome da empresa)";
            $x['customer']['name']                  = "(nome de contacto)";
            $x['customer']['address']               = "(morada)";
            $x['customer']['postal_code']           = "(código postal)";
            $x['customer']['country']               = "(país)";
            # Customer Info
            
            # Perguntas
            $x['perguntas'] = array();
            if(trim($temp_email['perguntas'])!=''){
                
                $perg_s = "SELECT nome$LG, desc$LG FROM _tfaqs_emails WHERE id IN (".$temp_email['perguntas'].") ORDER BY ordem, nome$LG ASC";
                $perg_q = cms_query($perg_s);
                while($perg = cms_fetch_assoc($perg_q)){
                    $x['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
                }
                
            }
            
            $exp['table']                           = "exp_emails";
            
            $data = $fx->printTwigTemplate("quote.htm", $x, true, $exp);
                                    
        }
        
    }elseif($email_site == 0 && in_array($template_id, array(21))){
        
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/emails_marketing', false, _ROOT.'/temp_twig/');
        
        $x = array_merge(get_info_geral_email($LG, $MARKET), $x);
        $x['path'] = $slocation.$x['path'];
        
        $email_style = call_api_func("get_line_table", "b2c_config_email_template", "id=1");
        $x['email_style'] = $email_style;

        $x['LINK']        = $slocation;
        
        $logotipo = "/email/sysimages/logo_email_new_layout.jpg";             
        if(file_exists(_ROOT.'/images/logo_email_new_layout.jpg')){
            $logotipo = "/images/logo_email_new_layout.jpg";
        }
        $x['EMAILS_LOGO'] = $logotipo;
        
        if($template_id == 21){
            
            $produtos = array();
            
            $prod_q = cms_query("SELECT DISTINCT pid FROM ec_encomendas_lines WHERE order_id > 0 ORDER BY id DESC LIMIT 0,3");
            while($row = cms_fetch_assoc($prod_q)){
                $prod = call_api_func('get_product', $row["pid"]);
                if($prod["id"]>0){
                    get_layout_prod($produtos, $prod);
                }
            }
            
            foreach($produtos as $k => $v){
                $produtos_f[] = $v;
            }
            
            $x['PRODUTOS']  = $produtos_f;
            $x['negar_exp'] = '';
            $x['TITULO']    = $_assunto;
            $x['SUBTITULO'] = $html;
            $x['FINALIZAR'] = nl2br(estr(214));
            
            $data = $fx->printTwigTemplate("email_rw.html", $x, true, $exp);
            
        }
    
    }elseif($email_site == 0 && in_array($template_id, array(72))){
        
        global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $logotipo;
        
        $email = __getEmailBody(72, "pt");
        
        $array_templates[] = _ROOT.'/plugins/emails';
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates , false, _ROOT.'/temp_twig/');
        
        $_exp                                   = array();
        $_exp['table']                          = "exp_emails";
        $_exp['prefix']                         = "nome";
        $_exp['lang']                           = "pt";

        $y                                      = array(); 
        $y['EMAILS_LOGO']                       = $logotipo;
        $y['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
        $y['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
        $y['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        $y['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
        $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
        
        $y['nome_site']                         = $pagetitle;
        $y['url_site']                          = $slocation; 
        
        $y['template']['titulo']                = nl2br(strip_tags($email['titulopt']));
        $y['template']['descricao']             = nl2br(strip_tags($email['descpt']));
         
        $y['url_recompra']                      = $slocation."/checkout/v1/?id=47&m2code=XXX&prime=1";
        
        $y['subscricao']['nome']                = "Subscrição Prime - Anual";
        $y['subscricao']['imagem']              = $slocation."/checkout/v1/sysimages/img-services-prime.png";
        $y['subscricao']['desc']                = "9.50&euro; / mês x 12";
    

        $meses          = 12;
        $valor_desconto = 10 * ( (int)5 / 100);
        $value_price    = number_format(10 - $valor_desconto, 2, '.', '');
        $price          = $meses * $value_price;

        $y['subscricao']['preco']   = call_api_func('OBJ_money', $price, 7);
        
        $y['perguntas'] = array();
        if(trim($email['perguntas'])!=''){
            
            $perg_s = "SELECT nomept, descpt FROM _tfaqs_emails WHERE id IN (".$email['perguntas'].") ORDER BY ordem, nomept ASC";
            $perg_q = cms_query($perg_s);
            while($perg = cms_fetch_assoc($perg_q)){
                $y['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
            }
        }
        
        $data = $fx->printTwigTemplate("prime.htm", $y, true, $_exp);    
        
    }else{
        
        $data = $fx->printTemplate(_ROOT."/email/email.html",$x, true, $exp);
        
    }
        
    $data = base64_encode($data);
  
    $arr = array();
    $arr['0'] = $data;

    return serialize($arr);

}
  
         
function ___replaceData($Data, $Msg){
  
    foreach($Data as $k => $v){                
        $Msg = str_ireplace("{".$k."}", $v, $Msg);
        $Msg = str_ireplace("{".strtoupper($k)."}", $v, $Msg);
    }
    
    return $Msg;
}


function __viewEmailUser($template, $LG){

    global $eComm, $fx, $pagetitle, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B, $EMAILS_LAYOUT_FONT;
    
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
 
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
                            
    
    $_exp = array();
    $_exp['table'] = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;


    $y = array();
                             
    include _ROOT.'/api/controllers/_sendEmail.php';
                          
    
    $html       = nl2br($template['bloco'.$LG]);
    $_assunto   = $template['assunto'.$LG];
    
    
    
    $data = array(
      "CLIENT_NAME"       => "(nome do cliente)",
      "PAGETITLE"         => $pagetitle,
      "LINK_AUTO_BUTTON"  => "link",
      "CODE"              => "123456",
      "DISCOUNT_VALUE"    => "9.99€",
      "DISCOUNT_DATE"     => "2999-01-01"
      
    ); 
    
    $html = __replaceData($data, $html);
    
    
     
    $font_family = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';

    $style= 'font-family: -apple-system, BlinkMacSystemFont, '.$font_family;
    
    $html = "<div class='content_bloco' style='$style; color: #333;line-height: 150%;font-size: 16px;'>".$html."</div>";        

    $html = __sendEmailLayout($_assunto, $html, $LG, $data, $template);
    
                                  
    
    $html = str_replace('src="plugins', 'src="'.$slocation.'/plugins', $html);
    $html = str_replace("src='plugins", "src='".$slocation."/plugins", $html);
    
    $html = str_replace('src="email', 'src="'.$slocation.'/email', $html);
    $html = str_replace("src='email", "src='".$slocation."/email", $html);
    

    $html = '<div><div style="width:100%;height:100%;position:fixed;top:0px;"></div>'.$html."</div>";
              
    return $html;
        
} 


function __viewEmailGest($template, $LG){

    global $eComm, $fx, $pagetitle, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B, $EMAILS_LAYOUT_FONT, $EMAILS_LAYOUT_COR_LK;
    
                            
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
 
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    $_exp = array();
    $_exp['table'] = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;


    $y = array();
    
    include _ROOT.'/api/controllers/_sendEmailGest.php';
    
    
    $more_where = '';
    
    if($template['id']==9) $more_where = " AND tracking_status>=70 AND shipping_tracking_number!='' ";
    elseif($template['id']==58) $more_where = " AND tracking_tipopaga IN (4,10,12,13,21,23,29,32) AND metodo_shipping_type=2";
    elseif($template['id']==18) $more_where = " AND tracking_tipopaga IN (4,10,12,13,21,23,29,32,37,80,82,84,96,108,122) "; # MB
    elseif($template['id']==46) $more_where = " AND tracking_tipopaga IN (6,53,120) "; # TB
    
      
    
    $_enc = cms_fetch_assoc(cms_query("SELECT * FROM ec_encomendas WHERE entrega_pais_lg='$LG' $more_where ORDER BY id DESC LIMIT 0,1"));
    if((int)$_enc['id']<1) $_enc = cms_fetch_assoc(cms_query("SELECT * FROM ec_encomendas ORDER BY id DESC LIMIT 0,1"));
    
   
   
    if(trim($_enc['shipping_tracking_number'])=='') $_enc['shipping_tracking_number'] = 'TRACKING';
    
    
    $html       = $template['bloco'.$LG];
    $_assunto   = $template['assunto'.$LG];
     
     
    $_assunto = str_replace('{ORDERID}', $_enc['order_ref'], $_assunto);
    $_assunto = str_replace('{order_ref}', $_enc['order_ref'], $_assunto);    
     
    $_assunto = str_replace('{ORDER_REF}', $_enc['order_ref'], $_assunto);
    $_assunto = str_replace('{ORDER_ID}', $_enc['id'], $_assunto);
    
    $_assunto = str_replace('{RETURN_REF}', $_ret['return_ref'], $_assunto);
    $_assunto = str_replace('{RETURN_ID}', $_ret['id'], $_assunto);
    
    $html = str_replace("{DISCOUNT_CODE}", "VL001ABC", $html);
    $html = str_replace("{DISCOUNT_DATE}", "2999-01-01", $html);
    $html = str_replace("{DISCOUNT_REMAINING_AMOUNT}", "9.99 Eur", $html);
    $html = str_replace("{IBAN}", "PT50002700000001234567833", $html);
    $html = str_replace("{REFUND_TOTAL}", "99.99EUR", $html);
    
    
    $_enc['val_data']['DISCOUNT_CODE'] = 'VL001ABC';
    $_enc['val_data']['DISCOUNT_DATE'] = '2999-01-01';
    $_enc['val_data']['DISCOUNT_REMAINING_AMOUNT'] = '9.99 Eur';
    
    
    
    $html = str_replace('{STORE_NAME}', "(nome da loja)", $html);
    
    $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        
    $store_code = '<br><span style="color: '.$link_cor.' !important;
                                display: inline-block;
                                text-align: center;
                                border: 1px solid '.$link_cor.';
                                border-radius: 4px 4px 4px 4px;
                                font: normal normal 400 normal 20px / 150% monospace !important;
                                padding: 7px;
                                margin-top: 10px !important;
                                min-width: 150px;">12345678</span>';

    require_once $_SERVER["DOCUMENT_ROOT"]."/api/lib/barcode39/Barcode39.php";
                                                          
    $bc = new Barcode39("*12345678*");
    $bc->barcode_text = true;
            
    $barcode_fac_final_path = _ROOT.'/prints/IMGSTORECD/bc_'.$_enc["id"].'.gif';
                   
    $bc->draw($barcode_fac_final_path);
  
    $bar_code = "<img style='margin-top:10px;' src='".$slocation."/prints/IMGSTORECD/bc_".$_enc["id"].".gif?".filemtime($barcode_fac_final_path)."'>";  
        
    $html = str_replace('{STORE_CODE}.', $store_code, $html);
    $html = str_replace('{STORE_CODE}', $store_code, $html); 
    
    $html = str_replace('{STORE_BARCODE}', $bar_code, $html);
    

    $html = __substituirVariavies($html, $_enc, array(), array(), $template);
    
    
    $font_family = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';

    $style= 'font-family: -apple-system, BlinkMacSystemFont, '.$font_family;
    
    
    $html = "<div class='content_bloco' style='$style; color: #333;line-height: 150%;font-size: 16px;'>".$html."</div>"; 
    
    $array_devs = array(15,16,45,28,37,24,50); 
    if(in_array($template['id'], $array_devs)){
        $_ret = cms_fetch_assoc(cms_query("SELECT * FROM ec_devolucoes ORDER BY id DESC LIMIT 0,1"));
    }
     
    $html = sendNewEmailLayout($_assunto, $html, $LG, $_enc, $_ret, $template);
    
    
    $html = str_replace('src="plugins', 'src="'.$slocation.'/plugins', $html);
    $html = str_replace("src='plugins", "src='".$slocation."/plugins", $html);
    
    $html = str_replace('src="email', 'src="'.$slocation.'/email', $html);
    $html = str_replace("src='email", "src='".$slocation."/email", $html);
    

    $html = '<div><div style="width:100%;height:100%;position:fixed;top:0px;"></div>'.$html."</div>";
        
    return $html;
    
        
}


function __viewEmailConfirmationEncNew($template, $LG){

    global $eComm, $fx, $pagetitle, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;
    
                            
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
 
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    $_exp = array();
    $_exp['table'] = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;


    $y = array();
    
    include _ROOT.'/api/controllers/_sendEmailConfirmationEnc.php';
    
        
    $more_where = '';
    
    if($template['id']==1) $more_where = ' AND tracking_tipopaga NOT IN (4,10,12,13,21,23,29,32)' ;       
    elseif($template['id']==14) $more_where = ' AND tracking_tipopaga IN (4,10,12,13,21,23,29,32) AND pagref=""' ;  
    elseif($template['id']==54) $more_where = ' AND tracking_tipopaga IN (6,53) AND pagref="" ' ;        
    elseif($template['id']==38) $more_where = ' AND tracking_tipopaga NOT IN (4,10,12,13,21,23,29,32)' ; 
    
                      
            
    $_enc = cms_fetch_assoc(cms_query("SELECT * FROM ec_encomendas WHERE entrega_pais_lg='$LG' $more_where ORDER BY id DESC LIMIT 0,1"));
    if((int)$_enc['id']<1) $_enc = cms_fetch_assoc(cms_query("SELECT * FROM ec_encomendas ORDER BY id DESC LIMIT 0,1"));

    
    $html = __sendEmailConfirmationEncNew($_enc, $template, $LG);
    
    
    $html = str_replace('src="plugins', 'src="'.$slocation.'/plugins', $html);
    $html = str_replace("src='plugins", "src='".$slocation."/plugins", $html);
    
    $html = str_replace('src="email', 'src="'.$slocation.'/email', $html);
    $html = str_replace("src='email", "src='".$slocation."/email", $html);
    

    $html = '<div><div style="width:100%;height:100%;position:fixed;top:0px;"></div>'.$html."</div>";


    return $html;
           
}


function __viewEmailConfirmationDEVNew($template, $LG){

    global $eComm, $fx, $pagetitle, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;
    
                            
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
 
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    $_exp = array();
    $_exp['table'] = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;


    $y = array();
    
    include _ROOT.'/api/controllers/_sendEmailConfirmationDev.php';
    
              
    $_dev = cms_fetch_assoc(cms_query("SELECT * FROM ec_devolucoes WHERE metodo_devolucao!=5 ORDER BY id DESC LIMIT 0,1"));
 
    $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$_dev["order_id"]."'");
 
    $html = __infoEmailDev($_dev, $_enc, $template, $LG);
    
    
    $html = str_replace('src="plugins', 'src="'.$slocation.'/plugins', $html);
    $html = str_replace("src='plugins", "src='".$slocation."/plugins", $html);
    
    $html = str_replace('src="email', 'src="'.$slocation.'/email', $html);
    $html = str_replace("src='email", "src='".$slocation."/email", $html);
    

    $html = '<div><div style="width:100%;height:100%;position:fixed;top:0px;"></div>'.$html."</div>";


    return $html;
           
}

?>
