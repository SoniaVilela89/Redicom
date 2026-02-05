<?

function _sendEmail($template_id=0, $data, $file="", $table_id=0){
    
    global $EMAILS_LAYOUT_SEND, $LG, $pagetitle, $EMAILS_LAYOUT_FONT;
 
    if ($template_id > 0){
       $template_id = (int)$template_id;
       $data = $data;
    }else{
       $template_id = (int)params('template_id');
       $data = params('data');
       $file = params('file');
    }

    $arr = array();
    $arr['0'] = 0;

    $data           = base64_decode($data);
    $data           = urldecode($data);
    $data           = gzinflate($data);
    $data           = gzinflate($data);
    $data           = unserialize($data);


    if($template_id<1 && !isset($data['EMAIL_TEXTO'])){ # se existir $data['EMAIL_TEXTO'] não é usado template de email
        return $arr;
    }
      
      

    if( trim($data['lg']) == "" && (int)$data['id_cliente'] > 0 ){

        $user = call_api_func("get_line_table","_tusers", "id='".$data['id_cliente']."'");
        
        if( (int)$user['id_lingua'] > 0 ){
            $language_id = (int)$user['id_lingua'];
        }else{
            $country = call_api_func("get_line_table", "ec_paises", "id='".$user['pais']."'");
            $language_id = $country['idioma'];     
        }
        
        if( (int)$user['id_user'] > 0 ){

            $user_master = call_api_func("get_line_table","_tusers", "id='".$user['id_user']."'");
            if( (int)$user_master['id_lingua'] > 0 ){
                $language_id = (int)$user['id_lingua'];
            }

            if( $language_id <= 0 ){

                if( (int)$user['pais'] > 0 ){
                    $country_id = (int)$user['pais'];
                }elseif( (int)$user_master['pais'] > 0 ){
                    $country_id = (int)$user_master['pais'];
                }

                $country = call_api_func("get_line_table", "ec_paises", "id='".$country_id."'");
                $language_id = $country['idioma'];

            }

        }
        
        
        if( (int)$language_id > 0 ){

            $language = call_api_func("get_line_table", "ec_language", "id='".$language_id."'");
            if( $language["code"] == 'es' ) $language["code"] = 'sp';
            elseif( $language["code"] == 'en' ) $language["code"] = 'gb';

            $data['lg'] = $language['code'];

        }else{
            $data['lg'] = $LG;
        }

    }elseif( trim($data['lg']) != "" ){

        if( $data['lg'] == 'es' ) $data['lg'] = 'sp';
        elseif( $data['lg'] == 'en' ) $data['lg'] = 'gb';
        
    }

    $LG = $_lg = $data['lg'];
    
    if( (int)$table_id == 0 ){
        $table = "ec_email_templates";
    }elseif((int)$table_id == 1){
        $table = "email_templates";
    }



        
    if(isset($data['EMAIL_TEXTO'])) {

        $html       = $data['EMAIL_TEXTO'];
        $_assunto   = $data['EMAIL_ASSUNTO'];
        $temp_email['nomept'] = $_assunto;   
        
        if(isset($data['EMAIL_FILE'])) $file = $data['EMAIL_FILE'];
        
        if(isset($data['LINK_AUTO_BUTTON'])){
            $temp_email['btt_url'] = '1';
            $temp_email['btt_titulo'.$LG] = $data['TXT_AUTO_BUTTON'];
            
            $html = $html."<br><br>{AUTO_BUTTON}";
        }

    } else {

        $temp_email = call_api_func("get_line_table",$table, "id='".$template_id."' and hidden='0'");
        $html           = nl2br($temp_email['bloco'.$_lg]);
        $_assunto   = $temp_email['assunto'.$_lg];

    }

    if(trim($html)==''){
        return serialize(array("0"=>"0"));
    }
    
    $html = __replaceData($data, $html); 
    
    
    $_assunto = str_replace("{CLIENT_NAME}", $data['CLIENT_NAME'], $_assunto);
    
    $html = str_replace("{PAGETITLE}", $pagetitle, $html);
    
    
    $_toemail   = $data['email_cliente'];
    $id_cliente = (int)$data['id_cliente'];
    
    # 2021-09-02
    # Não enviar emails para clientes com esta opção ativa
    if( is_numeric($id_cliente) && (int)$id_cliente > 0 ){
        $user = call_api_func("get_line_table","_tusers", "id='".$id_cliente."'");
        if( (int)$user['impedir_envio_emails'] == 1 ){
            return serialize(array("0"=>"0"));
        }
    }
    
    if( trim($file) == "0" ){
        $file = "";
    }
    
    if( trim($file) != "" ){
        $file = base64_decode($file);
    } 
    
    
    if((int)$EMAILS_LAYOUT_SEND==1){
    
        $font_family = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';

        $style= 'font-family: -apple-system, BlinkMacSystemFont, '.$font_family;
        
        
        $tabela = 'ec_email_queue';
        
        if(isset($data['CHATAI'])) {
            $html = "<p><b>Descrição do Problema:</b><br></p>".$html;
            $tabela = 'email_queue';
            $temp_email['id'] = -99;
        }
        

        $html = "<div class='content_bloco' style='$style; color: #333;line-height: 150%;font-size: 16px;'>".$html."</div>";        


        $html = __sendEmailLayout($_assunto, $html, $LG, $data, $temp_email);
        
   
        $id_email = saveEmailInBD($_toemail, $_assunto, $html, $id_cliente, 0, $temp_email['nomept'], '0', $tabela, 1, $file, 0, '', 0, $temp_email['id'], '', $data['ORDER_ID']);
        
        $arr = array();
        $arr['0'] = $id_email;
    
        return serialize($arr);
                         
    }
    
    
    
    $auto_button = '';
    if($temp_email['btt_titulo'.$LG]!='' && $temp_email['btt_url']!=''){
    
        $url = $data['LINK_AUTO_BUTTON'];  
        if(trim($data['BUTTON_URL'])!=''){
            $url = $data['BUTTON_URL'];  
        }   
        $texto = $temp_email['btt_titulo'.$LG];          
        
        $auto_button = '<div class="em-button-border">
                                                <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;"><tr><td align="center">
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$url.'" style="height:31.5pt; width:150pt; v-text-anchor:middle;" arcsize="10%" stroke="false" fillcolor="#000000"><w:anchorlock/>
                            <v:textbox inset="0,0,0,0"><center style="color:#ffffff; font-family:Arial, sans-serif; font-size:16px"><![endif]--><a href="'.$url.'" style="-webkit-text-size-adjust: none; text-decoration: none; display: inline-block; color: #ffffff; background-color: #000000; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; width: auto; width: auto; border-top: 1px solid #000000; border-right: 1px solid #000000; border-bottom: 1px solid #000000; border-left: 1px solid #000000; padding-top: 5px; padding-bottom: 5px; font-family: Arial, Helvetica, sans-serif; text-align: center; mso-border-alt: none; word-break: keep-all;" target="_blank" class="em-button"><span style="padding-left:20px;padding-right:20px;font-size:16px;display:inline-block;">
                                                <span style="font-size: 16px; line-height: 32px;">'.$texto.'</span>
                                                </span></a>
                                                <!--[if mso]></center></v:textbox></v:roundrect></td></tr></table><![endif]-->
                                            </div>';                                                        
    }
    
    $html = str_ireplace("{AUTO_BUTTON}", $auto_button, $html);

    
    $id_email = sendEmailFromController($html, $_assunto, $_toemail, $file, $id_cliente, $temp_email['nomept']);
     
    $arr = array();
    $arr['0'] = $id_email;

    return serialize($arr);

}


function __sendEmailLayout($_assunto, $html="", $LG, $data=array(), $temp_email=array()){

    global $eComm, $fx, $pagetitle, $slocation;
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $CONFIG_TEMPLATES_PARAMS;
    
    
    $array_templates = array(); 
    
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
       

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    
    require_once $_SERVER['DOCUMENT_ROOT']."/api/lib/shortener/shortener.php";
    
    
    $_exp           = array();
    $_exp['table']  = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang']   = $LG;
        
    
    if($CONFIG_TEMPLATES_PARAMS['site_version']>23){
        $logotipo = "/images/logo_email@2x.png";             
    }else{
        $logotipo = "/email/sysimages/logo_email_new_layout.jpg";             
        if(file_exists(_ROOT.'/images/logo_email_new_layout.jpg')){
            $logotipo = "/images/logo_email_new_layout.jpg";
        }  
    }   
    
                                    
    $y = array();
    $y['EMAILS_LOGO']                       = $logotipo;
    $y['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
    $y['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
    $y['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
    $y['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
    $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
                                        
    $y['nome_site']                         = $pagetitle;
    $y['url_site']                          = $slocation;
    
                                 
    if(isset($data['CHATAI'])) {
        $_assunto = "";
    }                                 
                                 
    $y['template']['titulo']                = strip_tags($_assunto);
    $y['template']['descricao']             = $html;    
                      
        
        
          
                
                                       
    # AUTO_BUTTON
    if(trim($temp_email['btt_url'])!='' && trim($data['LINK_AUTO_BUTTON'])!=''){
        
        $y_ret = $y;
        
        $y_ret['url_encomenda']   = $data['LINK_AUTO_BUTTON'];  
        $y_ret['texto']           = $temp_email['btt_titulo'.$LG];           
    
        $y['template']['descricao'] = str_replace("{AUTO_BUTTON}", $fx->printTwigTemplate("botao.htm", $y_ret, true, $_exp), $y['template']['descricao']);
                                   
    }


    # Botão detalhe RMA
    if (trim($data['LINK_RMA']) != "" && strpos($html, '{BUTTON_RMA}') !== false) {
        $exp64 = call_api_func("get_line_table","exp_emails", "id='64'");
        $y_ret = $y;
        $y_ret['url_encomenda']   = $data['LINK_RMA'];
        $y_ret['texto']           = $exp64['nome'.$LG];
        $y['template']['descricao'] = str_replace("{BUTTON_RMA}", $fx->printTwigTemplate("botao.htm", $y_ret, true, $_exp), $y['template']['descricao']);
    }


    $message_body = $fx->printTwigTemplate("email.htm",$y, true, $_exp);
    
    return $message_body;
      
      
}
  
         
function __replaceData($Data, $Msg){
  
    global $EMAILS_LAYOUT_COR_LK;
  
    foreach($Data as $k => $v){      
    
        if($k=="CODE"){
        
            $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
            
            $v = '<span style="color: '.$link_cor.' !important;
                                display: inline-block;
                                text-align: center;
                                border: 1px solid '.$link_cor.';
                                border-radius: 4px 4px 4px 4px;
                                font: normal normal 400 normal 20px / 150% monospace !important;
                                padding: 7px;
                                margin-top: 10px !important;
                                min-width: 150px;">'.$v.'</span>';          
        
        }
    
              
        $Msg = str_ireplace("{".$k."}", $v, $Msg);
        $Msg = str_ireplace("{".strtoupper($k)."}", $v, $Msg);
    }
    
    return $Msg;

}

?>
