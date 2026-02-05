<?


function _sendEmailGest($enc_id=null, $template=null, $ret_id=null, $emailto=null, $review_id=null, $data=null, $deposito_id=0){

    global $fx;
    global $LG;
    global $toemail;
    global $slocation;
    global $pagetitle;
    global $eComm;
    global $Email_From, $Email_2_From;
    global $CONFIG_EMAIL_ID9_NOTSEND, $EMAILS_LAYOUT_SEND, $EMAILS_LAYOUT_FONT;
    global $BCC_TRANSACIONAL;
    global $COUNTRY, $MARKET, $MOEDA;

    if ($template > 0){
       $enc_id        = (int)$enc_id;
       $template      = (int)$template;
       $ret_id        = (int)$ret_id;
       $emailto       = $emailto;
       $review_id     = (int)$review_id;       
       $deposito_id   = (int)$deposito_id;
    }else{
       $enc_id        = (int)params('orderid');
       $template      = (int)params('template');
       $ret_id        = (int)params('returnid');
       $emailto       = params('emailto');
       $review_id     = (int)params('review_id');
       $data          = params('data');
       $deposito_id   = (int)params('deposito_id');
    }
    
    $emailto = trim($emailto);
    $data = trim($data);
    
    

    if($enc_id>0) $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
    
    
    # 2021-12-06 
    # Não se envia emails para encomendas ou devoluções marketplace
    if($_enc['pm_marketplace_id']>0){
        return serialize(array("0"=>"0"));
    }
    
    
    
    # Não envia email ID=9 (Aviso de Expedição) $CONFIG_EMAIL_ID9_NOTSEND == 1
    if($template == 9 && $_enc['metodo_shipping_type']==2 && (int)$CONFIG_EMAIL_ID9_NOTSEND == 1) {
        return serialize(array("0"=>"0"));
    }

    # Email ID=58 se encomenda for para entrega em loja 
    if($template == 9 && $_enc['metodo_shipping_type']==2) {
        $temp_email = call_api_func("get_line_table","ec_email_templates", "id='58' and hidden='0'");
        if((int)$temp_email['id']>0){
            $template = 58;
        }        
    }


    $temp_email = call_api_func("get_line_table","ec_email_templates", "id='".$template."' and hidden='0'");
    if((int)$temp_email['id']<1){
        return serialize(array("0"=>"0"));
    }
    
    
    
    
    if($ret_id>0) {
        $_ret = call_api_func("get_line_table","ec_devolucoes", "id='".$ret_id."'");
        
        # 2021-02-11
        # Existindo devlução os dados que mandam são os da devolução, por causa das devoluções de oferta
        $_enc['email_cliente'] = $_ret['cliente_email'];    
        $_enc['cliente_final'] = $_ret['cliente_id'];
        
        if($_ret['devolucao_oferta']==1){
            $cliente = get_line_table("_tusers", "id='".$_ret['cliente_id']."'");
            $_enc['nome_cliente'] = $cliente['nome'];
        }
    }
    
    
    
    if($review_id>0) {  
        $review = call_api_func("get_line_table","registos_avaliacoes", "id='".$review_id."'");
        
        $prod                     = call_api_func("get_line_table","registos", "sku_family='".$review['sku_family']."'");
        $user_sql                 = cms_query("SELECT * FROM _tusers WHERE id='".$review["user_id"]."'");
        $user                     = cms_fetch_assoc($user_sql);
        
        $_enc['cliente_final']    = $user['id'];
        $_enc['nome_cliente']     = $user['nome'];
        $_enc['email_cliente']    = $user['email'];
        $_enc['idioma_user']      = $review['lg'];

    }

    # Não enviar emails para clientes com esta opção ativa
    if( is_numeric($_enc['cliente_final']) && (int)$_enc['cliente_final'] > 0 ){
        $user = call_api_func("get_line_table","_tusers", "id='".$_enc['cliente_final']."'");
        if( (int)$user['impedir_envio_emails'] == 1 ){
            return serialize(array("0"=>"0"));
        }
    }


    
    $_lg = strtolower($_enc['idioma_user']);
    if( trim($_lg)=="" ) $_lg = "pt";
    if( $_lg=="en" ) $_lg = "gb";
    if( $_lg=="es" ) $_lg = "sp";
    $LG = $_lg;
    
    
    
    $html       = $temp_email['bloco'.$_lg];
    $_assunto   = $temp_email['assunto'.$_lg];
    $_toemail   = $_enc['email_cliente'];
    
    
    
    # Não enviar email se conteúdo está vazio
    if(trim($html)==''){
        return serialize(array("0"=>"0"));
    }
    
  
    
    $_assunto = str_replace('{ORDERID}', $_enc['order_ref'], $_assunto);
    $_assunto = str_replace('{order_ref}', $_enc['order_ref'], $_assunto);    
     
    $_assunto = str_replace('{ORDER_REF}', $_enc['order_ref'], $_assunto);
    $_assunto = str_replace('{ORDER_ID}', $_enc['id'], $_assunto);
    
    $_assunto = str_replace('{RETURN_REF}', $_ret['return_ref'], $_assunto);
    $_assunto = str_replace('{RETURN_ID}', $_ret['id'], $_assunto);
 
    
    
    if(strlen($data)>1){
        $data = base64_decode($data);
        $data = urldecode($data);
        $data = gzinflate($data);
        $data = gzinflate($data);
        $data = unserialize($data);

        foreach($data as $k => $v){                
            $html = str_ireplace("{".$k."}", $v, $html);
            $html = str_ireplace("{".strtoupper($k)."}", $v, $html);
            
            $_enc['val_data'][strtoupper($k)] = $v;
        }
    }
        
        
    
         
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
                   
            
            
    $html = __substituirVariavies($html, $_enc, $_ret, $prod, $temp_email, $deposito_id);
    

   
    if($template==15) $_toemail = $Email_2_From;
    
           
    if(trim($emailto)!='' && $emailto!==' ' && $emailto!=='0') $_toemail = $emailto;
    

    
    if($template==9 || $template==29 || $template==58 || $template==99) $factura = $_enc['tracking_factura']; 
    
    
    if(file_exists('../prints/ENC/'.$_enc['id'].'.pdf')){
        if($factura!='')  
            $factura .= ';prints/ENC/'.$_enc['id'].'.pdf';
        else   
            $factura = 'prints/ENC/'.$_enc['id'].'.pdf';     
    } 
    
  
    if($template==50) $factura = $_ret['tracking_dev_factura'];
    
    if($template==110) {
        if(file_exists('../prints/CP/ret_' . $_ret['id'] . '.png')){
              $img = "<img src='".$slocation."/prints/CP/ret_".$_ret['id'].".png' style='max-width: 200px;margin:30px 0;'>";              
              $html = str_ireplace('{QR_CODE}', $img, $html);                 
        }
    }
   
    
    
    $email_bcc = "";
    if(!empty($BCC_TRANSACIONAL[$template])){
      $email_bcc = $BCC_TRANSACIONAL[$template];
    }
    
    
    if((int)$EMAILS_LAYOUT_SEND==1){
    
        $font_family = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
        
        $style= 'font-family: -apple-system, BlinkMacSystemFont, '.$font_family;

        $html = "<div class='content_bloco' style='$style; color: #333;line-height: 150%;font-size: 16px;'>".$html."</div>";
           
           
        $html = sendNewEmailLayout($_assunto, $html, $LG, $_enc, $_ret, $temp_email);
        
                     
    
        #saveEmailInBD($_toemail, $_assunto, $html, $_enc['cliente_final'], 0, $temp_email['nomept'], '0', 'ec_email_queue', 1, $factura);
        saveEmailInBD($_toemail, $_assunto, $html, $_enc['cliente_final'], 0, $temp_email['nomept'], '0', 'ec_email_queue', 1, $factura, 0, '', 0, $temp_email['id'], $email_bcc, (int)$_enc['id']);
                
        if($template==20){
        
            if(trim($MARKET['email_bcc'])!=''){
                $em = preg_split("/[;,]/",$MARKET['email_bcc']);
                foreach($em as $k => $v){   
                    #saveEmailInBD($v, $_assunto, $html, $_enc['cliente_final'], 0, $temp_email['nomept'], '0', 'ec_email_queue', 1, $factura);
                    saveEmailInBD($v, $_assunto, $html, $_enc['cliente_final'], 0, $temp_email['nomept'], '0', 'ec_email_queue', 1, $factura, 0, '', 0, $temp_email['id'], '', (int)$_enc['id']);
                }
            }
            
        }
        
        
        
        # Notificações APP
        if((int)$temp_email['app_ativo'] == 1){
            require_once($_SERVER['DOCUMENT_ROOT'].'/api/controllers/_sendNotificationApp.php');
            $resp_push = _sendNotificationApp($template,$_enc['cliente_final'],2);

            # se enviado com sucesso, regista o envio na encomenda
            if($resp_push) {
                $sql = "INSERT INTO ec_encomendas_log SET autor='APP Push Notification', encomenda='".$_enc['id']."', estado_novo='98', obs='Enviada notificação APP ID: ".$template."'";
                @cms_query($sql);
            }
        }
        
        
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);
                         
    }

    $return_btt_ink = $slocation.'/account/?id=16&code_order='.base64_encode($_enc['id'])."&cl=".base64_encode($_enc['cliente_final']);
                                                                                                  
    $return_button = '<a href="'.$return_btt_ink.'" style="font-size: 16px; text-decoration: underline; color:#000000 !important;">'.estr2(201).'</a>';
    
                     
    $html = str_replace('{RETURN_LINK}', $return_button, $html);                                                               
    
        
    $font_family = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
        
    
    $style= 'font-family: -apple-system, BlinkMacSystemFont, '.$font_family;
    
    $html = "<div style='$style; font-size: 13px; color: #000; line-height:17px;padding-left: 7px;'>".$html."</div>";
    
    
    
    #sendEmailFromController($html, $_assunto, $_toemail, $factura, $_enc['cliente_final'], $temp_email['nomept']);
    sendEmailFromController($html, $_assunto, $_toemail, $factura, $_enc['cliente_final'], $temp_email['nomept'], 0, $temp_email['id'], $email_bcc, (int)$_enc['id']);    
    
    if($template==20){
    
        if(trim($MARKET['email_bcc'])!=''){
            $em = preg_split("/[;,]/",$MARKET['email_bcc']);
            foreach($em as $k => $v){   
                #sendEmailFromController($html, $_assunto, $v, $factura, $_enc['cliente_final'], $temp_email['nomept']);
                sendEmailFromController($html, $_assunto, $v, $factura, $_enc['cliente_final'], $temp_email['nomept'], 0, $temp_email['id']);
            }
        }
        
    }
        
    $arr = array();
    $arr['0'] = 1;

    return serialize($arr);
}


function __printVale($y, $_enc){
    
    global $fx, $_exp;
    
    $y['vl_code'] = $_enc['val_data']['DISCOUNT_CODE'];
    $y['vl_valor'] = $_enc['val_data']['DISCOUNT_REMAINING_AMOUNT'];
    $y['vl_data'] = $_enc['val_data']['DISCOUNT_DATE'];

    
    $html = $fx->printTwigTemplate("inc_vale.htm", $y, true, $_exp);    
    
    return $html;    
}

function __printPagamento($y, $_enc){

    global $fx, $_exp;

    $html = '';
    
    if( in_array( $_enc['tracking_tipopaga'], array(4,10,12,13,21,23,29,31,32,37,80,82,84,108) ) ){
    
        $y['mb_exp_1'] = estr2(92);
        $y['mb_exp_2'] = estr2(93);
        $y['mb_exp_3'] = estr2(94);
        
        $y['payment_id']    = $_enc['tracking_tipopaga'];
        
        $y['ref_multi_enti'] = $_enc['ref_multi_enti'];
        $y['ref_multi'] = $_enc['ref_multi'];
        
        $pag = call_api_func("get_line_table","ec_pagamentos", "id='".$_enc['tracking_tipopaga']."'");
       
        if((int)$pag['notifi_cancel_wait_hrs']==0) $pag['notifi_cancel_wait_hrs'] = 2;
        
        $pag_data = date('d-m-Y H:i', strtotime('+'.$pag['notifi_cancel_wait_hrs'].' days', strtotime(date("Y-m-d H:i"))) ); 
        
        $y['pagamento_dia'] = $pag_data;
                 
        $html = $fx->printTwigTemplate("confirmacao_topo_mb_dados.htm", $y, true, $_exp);

        
    } elseif( $_enc['tracking_tipopaga']==24){
             
        $html = "";
                  
    } elseif($_enc['tracking_tipopaga']==6){

        $y['tb_exp_1'] = 'IBAN:';
        $y['tb_exp_2'] = 'SWIFT:';
        $y['tb_exp_3'] = estr2(138);
        
        $y['tb_iban'] = $_enc['iban'];
        $y['tb_swift'] = $_enc['swift'];
        
        $html = $fx->printTwigTemplate("confirmacao_topo_tb_dados.htm", $y, true, $_exp);
    }  
    
    
    $html .= $fx->printTwigTemplate("confirmacao_topo_cc.htm", $y, true, $_exp);        

    return $html;  

}

function sendNewEmailLayout($_assunto, $html="", $LG, $_enc=array(), $_ret=array(), $temp_email=array()){

    global $eComm, $fx, $pagetitle, $slocation;
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $CONFIG_TEMPLATES_PARAMS;
    global $COUNTRY, $MARKET, $MOEDA;
    
    
    $array_templates = array(); 
    
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
       

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
    
    
    require_once $_SERVER['DOCUMENT_ROOT']."/api/lib/shortener/shortener.php";
    
    
    $_exp           = array();
    $_exp['table']  = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang']   = $LG;
    

    
    $user = call_api_func("get_line_table","_tusers", "id='".$_enc['cliente_final']."'");
   
    $mcode = base64_encode('0|||'.$user['id'].'|||'.$user['email']);
   
    $url_encomenda = $slocation.'/account/index.php?id=17&order='.$_enc['id'].'&m2code='.$mcode;
    
    $url_encomenda = short_url($url_encomenda, $_SERVER["SERVER_NAME"]);
    
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
    
    

    $valor_cheques            = $eComm->getInvoiceChecksValue($_enc['id']);
    $total                    = $_enc['valor']-$valor_cheques;

    $y['encomenda']['total_pago'] = $_enc['moeda_prefixo'].number_format($total, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    

    $y['url_encomenda']                     = $url_encomenda->short_url;  
                                         
    $y['nome_site']                         = $pagetitle;
    $y['url_site']                          = $slocation;
    

      
    $html =  str_replace('{PAYMENT_DATA}', __printPagamento($y, $_enc), $html);
    
    $html =  str_replace('{DISCOUNT_REMAINING_DATA}', __printVale($y, $_enc), $html);
    
    
    
    # Link de devolução
    $y_ret = $y;
    
    
    $estr = call_api_func("get_line_table","exp_emails", "id='36'");
          
    $url_encomenda = $slocation.'/account/?id=16&code_order='.base64_encode($_enc['id'])."&cl=".base64_encode($_enc['cliente_final']).'&m2code='.$mcode;    
    $url_encomenda = short_url($url_encomenda, $_SERVER["SERVER_NAME"]);
    
    $y_ret['url_encomenda']   = $url_encomenda->short_url;         
    $y_ret['texto']           = $estr['nome'.$LG];           

    #$html = str_replace('{RETURN_LINK}', $fx->printTwigTemplate("botao.htm", $y_ret, true, $_exp), $html);   
                                      
    $return_button = '<a href="'.$url_encomenda->short_url.'" style="font-size: 16px; text-decoration: underline; color: '.$y['EMAILS_LAYOUT_COR_LK'].' !important;">'.$estr['nome'.$LG].'</a>';
    $html = str_replace('{RETURN_LINK}', $return_button, $html);   
    
                       
    
    
    $y['template']['titulo']                = strip_tags(str_replace($_ret['return_ref'], '', str_replace($_enc['order_ref'], '', $_assunto)));
    $y['template']['descricao']             = $html;
    $y['template']['cabecalho']             = "";
    if(trim($_enc['order_ref'])!=''){
        $y['template']['cabecalho']         = "[@1] #".$_enc['order_ref'];
    }
    
    $y['encomenda']['order_ref']            = $_enc['order_ref'];          
    
    if((int)$_ret['id']>0){
        $y['template']['cabecalho']         = "[@31] #".$_ret['return_ref'];
        
        if($_ret['troca']==1)
            $y['template']['cabecalho']     = "[@57] #".$_ret['return_ref'];
            
        $y['encomenda']['order_ref']        = $_ret['return_ref'];          
    }

                
    # Perguntas
    $y['perguntas'] = array();
    if(trim($temp_email['perguntas'])!=''){
        
        $perg_s = "SELECT nome$LG, desc$LG FROM _tfaqs_emails WHERE id IN (".$temp_email['perguntas'].") ORDER BY ordem, nome$LG ASC";
        $perg_q = cms_query($perg_s);
        while($perg = cms_fetch_assoc($perg_q)){
            $y['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
        }
    }
    
                                 
    $message_body = $fx->printTwigTemplate("email.htm",$y, true, $_exp);

    return $message_body;
      
      
}



function __substituirVariavies($bloco="", $_enc=array(), $_ret=array(), $prod=array(), $temp_email=array(), $deposito_id=0){

    global $pagetitle, $slocation, $eComm, $LG, $fx;    
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT;
    global $COUNTRY, $MARKET, $MOEDA;
    
    
    $array_tracking           = __getTrackingLink($_enc, $deposito_id);    
    $tracking                 = $array_tracking['tracking']; 
    $tracking_number_and_link = $array_tracking['tracking_number_and_link']; 
    $transportadora           = $array_tracking['transportadora'];
    
    $tracking_ret             = __getTrackingRet($_ret);
        
    $morada                   = __getMorada($_enc);
    
    $morada_entrega           = __getMoradaEntrega($_enc, $_ret);
    
    $morada_entrega_transporte = $_enc['metodo_shipping_nome']."<br>".$morada_entrega;
        
    $valor_cheques            = $eComm->getInvoiceChecksValue($_enc['id']);
    $total                    = $_enc['valor']-$valor_cheques;
    
    
    $informacao_entrega = '';


    #49- estorno à vnda
    #95 - expedição parcial 
    #89 - entregue
    if($temp_email['id'] == 49 || $temp_email['id'] == 95 || $temp_email['id'] == 89) {
        
        
        if($temp_email['id'] == 95) {
            if($deposito_id>0) $lines = $eComm->getOrderLinesEnc($_enc['id'], 1, 1, " AND deposito_cativado='$deposito_id' " );
            else $lines = $eComm->getOrderLinesEnc($_enc['id'], 1, 1 );
        }else $lines = $eComm->getOrderLinesEnc($_enc['id'], 1, 2);
        
        
        
        
        $s              = "SELECT * FROM `ec_encomendas_original_header` WHERE `order_id`=".$_enc['id'];
        $q              = cms_query($s);
        $row_old_info   = cms_fetch_assoc($q);
        
        if( $row_old_info['order_id'] > 0 ){                
            $_enc['valor'] = $row_old_info['original_value'];                
        }
        
    
        $vale_de_desconto_s = "SELECT SUM(valor_descontado) as total from ec_vales_pagamentos WHERE order_id='".$_enc['id']."' AND valor_descontado>0 AND vale_de_desconto=1 LIMIT 0,1"; 
        $vale_de_desconto_q  = cms_query($vale_de_desconto_s);  
        $vale_de_desconto_r  = cms_fetch_assoc($vale_de_desconto_q);
        
        if($vale_de_desconto_r['total']>0){      
            $TOTAL_ENC = ($_enc['valor']-$_enc['custo_pagamento']-$_enc['imposto']) + $vale_de_desconto_r['total'];                  
        }
                     
      
        # 2025-06-09
        # Configuração a indicar que os pontos não afetam os portes
        $CONF_PONTOS = cms_fetch_assoc(cms_query("SELECT campo_11 FROM b2c_config_loja WHERE id=20 LIMIT 0,1"));     
        if($CONF_PONTOS['campo_11']==1) $TOTAL_ENC -= $_enc['portes'];
        
        
    
        
        $produtos_html = "";

        if($lines) {

            global $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY;

            require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getSingleImage.php';

            foreach($lines as $k => $v){
            
                 
                # 2024-08-05
                # Definido por Serafim que só enviamos os produtos estornados
                if($temp_email['id'] == 49 && $v['recepcionada']==1 ) continue;
                 
                
                if($vale_de_desconto_r['total']>0){
                    $vale_de_desconto_tot += (((($v['valor_final']-$v['desconto_final'])*100)/$TOTAL_ENC)*$vale_de_desconto_r['total'])/100;     
                } 
                

                $label_preco_r  = $_enc['moeda_prefixo'].number_format($v['valoruni_anterior_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
                $label_preco    = $_enc['moeda_prefixo'].number_format($v['valor_final']-$v['desconto_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
                $label_desconto = $_enc['moeda_prefixo'].number_format($v['desconto_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
                $label_promo    = $_enc['moeda_prefixo'].number_format($v['valoruni_anterior_final']-$v['valor_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];


                if($v['valoruni_anterior_final']==0){
                    $label_preco_r  = 0;
                    $label_promo = 0;
                }

                if($v['desconto_final']==0){
                    $label_desconto = 0;
                }

                if($v['oferta']==1){
                    $label_preco = "<span style='background-color: rgba(248, 231, 28, 0.25);padding: 4px 5px;font-size: 14px;'>".estr2(152)."</span>";
                    $label_preco_r = 0;
                    $label_desconto = 0;
                }

                $more_desc = '';

                if($v['ref']=='PORTES'){
                    if((int)$EMAIL_CONFIRMATION_HIDE_PORTES==1) continue;
                }else{
                    $more_desc_array = array();
                    if(trim($v['cor_name'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_COR==0) $more_desc_array[] = estr2(1)." ".$v['cor_name'];
                    if(trim($v['tamanho'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_TAMANHO==0) $more_desc_array[] = estr2(2)." ".$v['tamanho'];
                    $more_desc = implode(' / ', $more_desc_array);

                    if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['composition'] = '';
                }


                if( trim($v['ref']) != '' && $v['pack'] == 0 && $v['tipo_linha'] !=5 && $v['egift'] == 0 && $v['custom'] == 0 ){
                    $imagem = _getSingleImage(60, 0, 3, $v['ref'], 1);
                    $imagem = str_replace('../', $slocation.'/', $imagem);
                }else{
                    $imagem = $v['image'];
                }

                $servico = 0;
                if($v['id_linha_orig']>0) $servico = 1;
                
                
                if($temp_email['id'] == 95) $v['recepcionada'] = 1;

                $resp_twig['EMAILS_LAYOUT_FONT'] = $EMAILS_LAYOUT_FONT;
                $resp_twig['produtos'][] = array(   "imagem" => $imagem,
                                                    "nome" => $v['nome'],
                                                    "desc" => $more_desc,
                                                    "caracteristicas" => $v['composition'],
                                                    "preco" => $label_preco,
                                                    "preco_riscado" => $label_preco_r,
                                                    "desconto" => $label_desconto,
                                                    "promo" => $label_promo,
                                                    "qtds" => $v['qnt_total'],
                                                    "servico" => $servico,
                                                    "recepcionada" => $v['recepcionada']
                                                );

            }

            $array_templates = array();
            if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
            $array_templates[] = _ROOT.'/plugins/emails';

            $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');

            $produtos_html = $fx->printTwigTemplate("products.htm", $resp_twig, true, $_exp);

        }
        
        
        if($vale_de_desconto_tot>0){
            $vale_de_desconto_tot = $_enc['moeda_prefixo'].number_format($vale_de_desconto_tot, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
            
            $produtos_html .= ' <tr class="subtotal-line">
                                  <td class="subtotal-line__title" style="font-family: -apple-system, BlinkMacSystemFont; padding: 20px 0 0;">
                                      <p style="color: #555; line-height: 1.2em; font-size: 16px; margin: 0;text-align: right;">
                                          <span style="font-size: 16px;margin-right: 25px;">[@20]</span>
                                          <strong style="font-size: 16px; color: #333;">-'.$vale_de_desconto_tot.'</strong>
                                      </p>
                                  </td>
                              </tr>';
        }

    }
    
    
    #49- estorno à vnda
    #95 - expedição parcial 
    if($temp_email['id'] == 49) {
    
        $exp = cms_fetch_assoc(cms_query("SELECT * FROM `exp_emails` WHERE `id` = 66"));

        $txt_vouchers = '';
        
        $hoje = date('Y-m-d');
        
        $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        
        
        # quando o desconto_vaucher_id já está a negativo é no cancelamento total de encomendas 
        $q_voucher = cms_query("SELECT DISTINCT abs(el.desconto_vaucher_id) as desconto_vaucher_id, el.moeda_prefixo, el.moeda_sufixo, el.moeda_decimais, el.moeda_casa_decimal, el.moeda_casa_milhares, el.idioma_user 
                                  FROM `ec_encomendas_lines` el
                                      INNER JOIN ec_vauchers v ON abs(el.desconto_vaucher_id)=v.id AND v.campanha_id=0 
                                  WHERE el.`order_id` = ".$_enc['id']." AND el.qnt=0 AND el.desconto_vaucher_id<0 AND el.desconto>0");
                                  
        while($r_voucher = cms_fetch_assoc($q_voucher)){
        
              $_lg = strtolower($r_voucher['idioma_user']);
              if( trim($_lg)=="" ) $_lg = "pt";
              if( $_lg=="en" ) $_lg = "gb";
              if( $_lg=="es" ) $_lg = "sp";
              
              $LG = $_lg;
                
              $vouc = cms_fetch_assoc(cms_query("SELECT data_limite, valor, tipo, cod_promo  FROM ec_vauchers WHERE id = '".$r_voucher['desconto_vaucher_id']."' LIMIT 0,1"));
              if($vouc['data_limite']>$hoje){
              
                    if($vouc['tipo']==2){
                        $valor_final_voucher = $r_voucher['moeda_prefixo'].number_format($vouc['valor'], $r_voucher['moeda_decimais'], $r_voucher['moeda_casa_decimal'], $r_voucher['moeda_casa_milhares']).$r_voucher['moeda_sufixo'];
                    }else{
                        $valor_final_voucher = round($vouc['valor']).'%';
                    } 
              
                   $mais_texto = str_replace("{DISCOUNT_VALUE}", $valor_final_voucher, $exp['nome'.$LG]);
                   $mais_texto = str_replace("{DISCOUNT_DATE}", $vouc['data_limite'], $mais_texto);
              
                   $txt_vouchers  .= '<span style="color: '.$link_cor.' !important;
                                        display: inline-block;
                                        text-align: center;
                                        border: 1px solid '.$link_cor.';
                                        border-radius: 4px 4px 4px 4px;
                                        font: normal normal 400 normal 20px / 150% monospace !important;
                                        padding: 7px;
                                        margin-top: 10px !important;
                                        min-width: 150px;">'.$vouc['cod_promo'].'</span><span style="margin-left:15px !important;color: '.$link_cor.' !important;">&#x2794;</span><span style="margin-left:15px !important;">'.$mais_texto.'</span><br>' ;                  
              }
        }
        
        
        # quando o desconto_vaucher_id ainda está a positivo é no estorno à venda parcial
        $q_voucher = cms_query("SELECT DISTINCT el.desconto_vaucher_id as desconto_vaucher_id, el.moeda_prefixo, el.moeda_sufixo, el.moeda_decimais, el.moeda_casa_decimal, el.moeda_casa_milhares, el.idioma_user 
                                FROM `ec_encomendas_lines` el
                                    INNER JOIN ec_vauchers v ON el.desconto_vaucher_id=v.id AND v.campanha_id=0  
                                WHERE el.`order_id` = ".$_enc['id']." AND el.qnt=0 AND el.desconto_vaucher_id>0 AND el.desconto>0");
                                
        while($r_voucher = cms_fetch_assoc($q_voucher)){
        
              # Validar se o voucher está aplicado a linhas que não estejam estornadas
              $val_voucher_s = "SELECT id FROM `ec_encomendas_lines` WHERE order_id='".$_enc['id']."' AND qnt>0 AND desconto_vaucher_id='".$r_voucher['desconto_vaucher_id']."' LIMIT 0,1";                             
              $val_voucher_q = cms_query($val_voucher_s);
              $val_voucher_r = cms_fetch_assoc($val_voucher_q);
              
              if($val_voucher_r['id']>0) {          
                  continue;
              }
        
              $_lg = strtolower($r_voucher['idioma_user']);
              if( trim($_lg)=="" ) $_lg = "pt";
              if( $_lg=="en" ) $_lg = "gb";
              if( $_lg=="es" ) $_lg = "sp";
              
              $LG = $_lg;
                
              $vouc = cms_fetch_assoc(cms_query("SELECT data_limite, valor, tipo, cod_promo FROM ec_vauchers WHERE id = '".$r_voucher['desconto_vaucher_id']."' LIMIT 0,1"));
              if($vouc['data_limite']>$hoje){
              
                    if($vouc['tipo']==2){
                        $valor_final_voucher = $r_voucher['moeda_prefixo'].number_format($vouc['valor'], $r_voucher['moeda_decimais'], $r_voucher['moeda_casa_decimal'], $r_voucher['moeda_casa_milhares']).$r_voucher['moeda_sufixo'];
                    }else{
                        $valor_final_voucher = round($vouc['valor']).'%';
                    } 
              
                   $mais_texto = str_replace("{DISCOUNT_VALUE}", $valor_final_voucher, $exp['nome'.$LG]);
                   $mais_texto = str_replace("{DISCOUNT_DATE}", $vouc['data_limite'], $mais_texto);
              
                   $txt_vouchers  .= '<span style="color: '.$link_cor.' !important;
                                        display: inline-block;
                                        text-align: center;
                                        border: 1px solid '.$link_cor.';
                                        border-radius: 4px 4px 4px 4px;
                                        font: normal normal 400 normal 20px / 150% monospace !important;
                                        padding: 7px;
                                        margin-top: 10px !important;
                                        min-width: 150px;">'.$vouc['cod_promo'].'</span><span style="margin-left:15px !important;color: '.$link_cor.' !important;">&#x2794;</span><span style="margin-left:15px !important;">'.$mais_texto.'</span><br>' ;                  
              }
        }

        if(trim($txt_vouchers)!=''){
            $txt_vouchers = "<br>[@65]<br>".$txt_vouchers."<br>";        
        } 
        
        
        $bloco = str_replace('{DISCOUNTS}', $txt_vouchers, $bloco);   
 
    }
    
    
    # estorno devolução efetuado
    if($temp_email['id'] == 50) {
        if($_ret['troca']==0){
        
              $exp = cms_fetch_assoc(cms_query("SELECT * FROM `exp_emails` WHERE `id` = 66"));

              $txt_vouchers = '';
              
              $hoje = date('Y-m-d');
              
              $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
              
              
              # quando o desconto_vaucher_id já está a negativo é na devolução da encomenda
              $q_voucher = cms_query("SELECT DISTINCT abs(el.desconto_vaucher_id) as desconto_vaucher_id, el.moeda_prefixo, el.moeda_sufixo, el.moeda_decimais, el.moeda_casa_decimal, el.moeda_casa_milhares, el.idioma_user 
                                        FROM `ec_encomendas_lines` el
                                            INNER JOIN ec_vauchers v ON abs(el.desconto_vaucher_id)=v.id AND v.campanha_id=0 
                                        WHERE el.`return_id` = ".$_ret['id']." AND el.desconto_vaucher_id<1 AND el.desconto>0");
                                        
              while($r_voucher = cms_fetch_assoc($q_voucher)){
              
              
                    # Validar se o voucher está aplicado a linhas que não estejam estornadas
                    $val_voucher_s = "SELECT id FROM `ec_encomendas_lines` WHERE order_id='".$_enc['id']."' AND return_id='0' AND qnt>0 AND desconto_vaucher_id='".$r_voucher['desconto_vaucher_id']."' LIMIT 0,1";                             
                    $val_voucher_q = cms_query($val_voucher_s);
                    $val_voucher_r = cms_fetch_assoc($val_voucher_q);
                    
                    if($val_voucher_r['id']>0) {          
                        continue;
                    }
              
                    $_lg = strtolower($r_voucher['idioma_user']);
                    if( trim($_lg)=="" ) $_lg = "pt";
                    if( $_lg=="en" ) $_lg = "gb";
                    if( $_lg=="es" ) $_lg = "sp";
                    
                    $LG = $_lg;
                      
                    $vouc = cms_fetch_assoc(cms_query("SELECT data_limite, valor, tipo, cod_promo  FROM ec_vauchers WHERE id = '".$r_voucher['desconto_vaucher_id']."' LIMIT 0,1"));
                    if($vouc['data_limite']>$hoje){
                    
                          if($vouc['tipo']==2){
                              $valor_final_voucher = $r_voucher['moeda_prefixo'].number_format($vouc['valor'], $r_voucher['moeda_decimais'], $r_voucher['moeda_casa_decimal'], $r_voucher['moeda_casa_milhares']).$r_voucher['moeda_sufixo'];
                          }else{
                              $valor_final_voucher = round($vouc['valor']).'%';
                          } 
                    
                         $mais_texto = str_replace("{DISCOUNT_VALUE}", $valor_final_voucher, $exp['nome'.$LG]);
                         $mais_texto = str_replace("{DISCOUNT_DATE}", $vouc['data_limite'], $mais_texto);
                    
                         $txt_vouchers  .= '<span style="color: '.$link_cor.' !important;
                                              display: inline-block;
                                              text-align: center;
                                              border: 1px solid '.$link_cor.';
                                              border-radius: 4px 4px 4px 4px;
                                              font: normal normal 400 normal 20px / 150% monospace !important;
                                              padding: 7px;
                                              margin-top: 10px !important;
                                              min-width: 150px;">'.$vouc['cod_promo'].'</span><span style="margin-left:15px !important;color: '.$link_cor.' !important;">&#x2794;</span><span style="margin-left:15px !important;">'.$mais_texto.'</span><br>' ;                  
                    }
              }  
              
              if(trim($txt_vouchers)!=''){
                  $txt_vouchers = "<br>[@65]<br>".$txt_vouchers."<br>";        
              } 
              
              
              $bloco = str_replace('{DISCOUNTS}', $txt_vouchers, $bloco);    
        }
    }


    # 2022-07-05
    # Confirmação de MB - enviamos a variavel com a informação de previsão de entrega  
    if($temp_email['id']==20){
                  
                  
        #Expedition Info
        $depositos = array();
        $services = array();
        $delivery_days = array();
        #Expedition Info

        $lines = $eComm->getOrderLinesEnc($_enc['id'], 1, 1);
                 
        foreach($lines as $k => $v){
            
            $servico = 0;
            if($v['id_linha_orig']>0) $servico = 1;

            $deposito_cativado = $v['deposito_cativado'];
            if((int)$v['deposito_cativado'] == 0){
                $cativar_stock = orderAvailableStock($v['ref'], $MARKET["deposito"], 0, $MARKET);
                $deposito_cativado = $cativar_stock['iddeposito'];
            }
            
            if( $servico == 0 ){
                
                $pid_final_temp = end( explode( "|||", $v['pid'] ) );
                $depositos[$deposito_cativado] = $deposito_cativado;
            
                #Expedition Info
                $prod = cms_fetch_assoc(cms_query("SELECT PreparationTime AS dias, ReplacementTime AS diasR FROM registos WHERE id=$pid_final_temp"));
                if( (int)$prod['diasR'] > 0 || (int)$prod['dias'] > 0 ){

                    $prod_p_days = explode(" ", $prod['dias']);
                    foreach($prod_p_days as $key=>$value){
                        if( is_numeric($value) == false )
                            unset($prod_p_days[$key]);
                    }
    
                    $delivery_min_days = (int)reset($prod_p_days);
                    $delivery_max_days = (int)end($prod_p_days);
                    
                    $negative_sale = cms_fetch_assoc(cms_query("SELECT property_value_int AS venda_negativo FROM ec_encomendas_lines_props WHERE order_line_id='".$v['id']."' AND property LIKE 'VENDA_NEGATIVO'"));
                    if( ( (int)$negative_sale['venda_negativo'] == 1 || (int)$cativar_stock["VENDA_NEGATIVO"] > 0 ) && (int)$prod['diasR'] > 0 ){
                        
                        $prod_r_days = explode(" ", $prod['diasR']);
                        foreach($prod_r_days as $key=>$value){
                            if( is_numeric($value) == false )
                                unset($prod_r_days[$key]);
                        }
    
                        $delivery_min_days += (int)reset($prod_r_days);
                        $delivery_max_days += (int)end($prod_r_days);
                        
                        $prod['dias'] += (int)$prod['diasR'];
                        
                    }

                }
                
                if( (int)$delivery_min_days > (int)$delivery_days['min'] ) $delivery_days['min'] = $delivery_min_days;
                if( (int)$delivery_max_days > (int)$delivery_days['max'] ) $delivery_days['max'] = $delivery_max_days;     
                #Expedition Info
    
            }else{
                 
                $pid_service_temp = (int)$v['sku_family'];
                $services[$pid_service_temp] = $pid_service_temp;
                
            }
            
        }
        
        $expedition_info = printExpeditionInfoEmail($depositos, $_enc['metodo_shipping_id'], $_enc['b2c_pais'], $services, $delivery_days, $_enc['id']);

        
        # Definições de envio
        $ship_encomenda = call_api_func("get_line_table","ec_shipping", "id='".$_enc['metodo_shipping_id']."'");
        
        $shipping_days_text = '';
        if(trim($_enc['shipping_days'])!=''){
            $informacao_entrega = '('.$_enc['shipping_days'].' '.estr2(97).')';
            
            if($expedition_info != ''){
                $informacao_entrega = $expedition_info;
            }
        }
                
        if(trim($ship_encomenda['bloco'.$LG])!=''){
            $informacao_entrega = nl2br(strip_tags($ship_encomenda['bloco'.$LG]));
            
            if($expedition_info != ''){
                $informacao_entrega .= "<br>".$expedition_info;
            }
        }
        
        if($informacao_entrega=='' && $expedition_info != ''){
            $informacao_entrega = $expedition_info;
        }
       

        $nome_expedicao = ($ship_encomenda['id']>0) ? $ship_encomenda['nome'.$LG]."<br>" : "";
        
        $informacao_entrega = '<h4 style="font-weight: 500; font-size: 16px; color: #333; margin: 0 0 5px;">[@15]</h4>'.$nome_expedicao.$informacao_entrega;
        
        $bloco = str_replace('{ESTIMATED_SHIPPING}', $informacao_entrega, $bloco);
               
    }
    
    
    
    
    #Variáveis antigas para compatibilizar - descontinuadas
    $bloco = str_replace('{NOME}', $_enc['nome_cliente'], $bloco);
    $bloco = str_replace('{ORDERID}', $_enc['order_ref'], $bloco);
    $bloco = str_replace('{MORADA_ENTREGA}', $morada_entrega, $bloco);
    $bloco = str_replace('{TRACKING}', $tracking, $bloco);    
    $bloco = str_replace('{TRACKING_LINK}', $transportadora['link'], $bloco);       
    $bloco = str_replace('{EMAIL}', $_enc['email_cliente'], $bloco);
    

    $bloco = str_ireplace("{nome_cliente}", $_enc['nome_cliente'], $bloco);
    $bloco = str_ireplace("{apelido_cliente}", $_enc['apelido_cliente'], $bloco);
    $bloco = str_ireplace("{entidade}", $_enc['ref_multi_enti'], $bloco);
    $bloco = str_ireplace("{order_ref}", $_enc['order_ref'], $bloco);
    $bloco = str_ireplace("{ref}", $_enc['ref_multi'], $bloco);
    $bloco = str_ireplace("{valor}", $total.$_enc['moeda_simbolo'], $bloco);

    $bloco = str_replace('{VALOR_VALE}', $_ret['valor_aceite'].$_ret['moeda_abreviatura'], $bloco);
    $bloco = str_replace('{VALE}', $_ret['metodo_devolucao_vale_criado'], $bloco);
    #Variáveis antigas para compatibilizar - descontinuadas
    
    
    
    
    #Variaveis gerais
    $bloco = str_ireplace("{PAGETITLE}", $pagetitle, $bloco);

    $bloco = str_replace('{ORDER_ID}', $_enc['id'], $bloco);
    $bloco = str_replace('{ORDER_REF}', $_enc['order_ref'], $bloco);
    $bloco = str_replace("{ORDER_TOTAL}", $total.$_enc['moeda_simbolo'], $bloco);
    $bloco = str_replace('{ORDER_TRACKING_NUMBER}', $tracking, $bloco);
    $bloco = str_replace('{ORDER_TRACKING_LINK}', $transportadora['link'], $bloco);
    
    $bloco = str_replace('{ORDER_TRACKING_NUMBER_AND_LINK}', $tracking_number_and_link, $bloco);
    
   
    $bloco = str_replace("{CLIENT_NAME}", $_enc['nome_cliente'], $bloco);
    $bloco = str_replace('{CLIENT_EMAIL}', $_enc['email_cliente'], $bloco);
    $bloco = str_replace('{CLIENT_ADDRESS}', $morada, $bloco);
    $bloco = str_replace('{CLIENT_DELIVERY_ADDRESS}', $morada_entrega, $bloco);
    $bloco = str_replace('{SHIPPING_METHOD}', $_enc['metodo_shipping_nome'], $bloco);
    
    

    $bloco = str_ireplace("{ENTITY}", $_enc['ref_multi_enti'], $bloco);
    $bloco = str_ireplace("{REFERENCE}", $_enc['ref_multi'], $bloco);
    
    $bloco = str_ireplace("{IBAN}", $_enc['iban'], $bloco);
    $bloco = str_ireplace("{SWIFT}", $_enc['swift'], $bloco);


    $bloco = str_replace('{RETURN_ID}', $_ret['id'], $bloco);
    $bloco = str_replace('{RETURN_REF}', $_ret['return_ref'], $bloco);
    $bloco = str_replace("{RETURN_TOTAL}", $_ret['valor'].$_enc['moeda_abreviatura'], $bloco);
    $bloco = str_replace('{RETURN_SHIPPING_DATE}', $_ret['shipping_date'], $bloco);
    $bloco = str_replace('{RETURN_TRACKING_NUMBER}', $tracking_ret, $bloco);
    
    
    $bloco = str_replace('{PRODUCT_NAME}', $prod['nome'.$LG], $bloco);
    $bloco = str_replace('{PRODUCT_SKU}', $prod['sku'], $bloco);

    $bloco = str_replace('{DETALHES}', $produtos_html, $bloco);

    
    $bloco = str_replace('{DISCOUNT_TOTAL}', $_ret['valor_aceite'].$_ret['moeda_abreviatura'], $bloco);
    $bloco = str_replace('{DISCOUNT_CODE}', $_ret['metodo_devolucao_vale_criado'], $bloco);
    
    
    $bloco = str_replace('{STORE_NAME}', $_enc['pickup_loja_nome'], $bloco);
    
    
    
    $q_codes = cms_query("SELECT codigo_rececao, shipping_tracking_number  
                            FROM ec_encomendas_lines 
                            WHERE order_id='".$_enc['id']."' AND status='72' AND recepcionada>0 AND qnt>0 
                            GROUP BY shipping_tracking_number
                            ORDER BY actualizado DESC ");
                            
    $lines_codigo_sql = cms_fetch_assoc($q_codes);        
    
    $store_code = '';
    if(trim($lines_codigo_sql['codigo_rececao'])!=''){
                                                           
        $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        
        $store_code = '<br><span style="color: '.$link_cor.' !important;
                                    display: inline-block;
                                    text-align: center;
                                    border: 1px solid '.$link_cor.';
                                    border-radius: 4px 4px 4px 4px;
                                    font: normal normal 400 normal 20px / 150% monospace !important;
                                    padding: 7px;
                                    margin-top: 10px !important;
                                    min-width: 150px;">'.$lines_codigo_sql['codigo_rececao'].'</span>';
                                    
      
        require_once $_SERVER["DOCUMENT_ROOT"]."/api/lib/barcode39/Barcode39.php";
                                                          
        $bc = new Barcode39("*".$lines_codigo_sql['codigo_rececao']."*");
        $bc->barcode_text = true;     
                      
        $barcode_fac_final_path = _ROOT.'/prints/IMGSTORECD/bc_'.$_enc["id"].'.gif';                        
        $bc->draw($barcode_fac_final_path);
      
        $bar_code = "<img style='margin-top:10px;' src='".$slocation."/prints/IMGSTORECD/bc_".$_enc["id"].".gif?".filemtime($barcode_fac_final_path)."'>";        
        
        
        
        
        $qtd_embalagens = cms_num_rows($q_codes);
        
        $bloco = str_replace('{NUMBER_OF_PACKAGES}', $qtd_embalagens, $bloco);
                                                                 
    }
    
    
    
    $bloco = str_replace('{STORE_CODE}.', $store_code, $bloco);
    $bloco = str_replace('{STORE_CODE}', $store_code, $bloco);
    
    $bloco = str_replace('{STORE_BARCODE}', $bar_code, $bloco);

    $sql_prop = "SELECT id, property_value FROM ec_encomendas_props WHERE property='ORDER_VALUE' AND order_id='".$_enc["id"]."'";
    $res_prop = cms_query($sql_prop);
    $row_prop = cms_fetch_assoc($res_prop);

    $total_confirmado = "";
    if((int) $row_prop["id"] > 0) $total_confirmado = $row_prop["property_value"].$_enc['moeda_simbolo'];
                                   
    $bloco = str_replace("{CONFIRMED_ORDER_VALUE}", $total_confirmado, $bloco);
    $bloco = str_replace("{ORDER_VALUE}", $total.$_enc['moeda_simbolo'], $bloco);
    
    $bloco = str_ireplace('€', '&euro;', $bloco);
    $bloco = str_ireplace('$', '&dollar;', $bloco);
    

    # 2020-07-22
    $bloco = str_replace(array("\r", "\n", "\t", "\r\n"), "", $bloco);                      


    $auto_button = '';
    if($temp_email['btt_titulo'.$LG]!='' && $temp_email['btt_url']!=''){
    
        foreach($_enc['val_data'] as $k => $v){                
            $temp_email['btt_url'] = str_ireplace("{".$k."}", $v, $temp_email['btt_url']);
        }
        
        $_exp           = array();
        $_exp['table']  = "exp_emails";
        $_exp['prefix'] = "nome";
        $_exp['lang']   = $LG;
    
        $y_ret = array();
        $y_ret['EMAILS_LAYOUT_COR_BT']     = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
        $y_ret['EMAILS_LAYOUT_COR_BG_BT']  = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
        $y_ret['EMAILS_LAYOUT_COR_LK']     = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        $y_ret['EMAILS_LAYOUT_COR_PORTES'] = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
        $y_ret['EMAILS_LAYOUT_FONT']       = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
    
        $y_ret['url_encomenda'] = $temp_email['btt_url'];         
        $y_ret['texto']         = $temp_email['btt_titulo'.$LG];
        
        $array_templates = array();
        if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
        $array_templates[] = _ROOT.'/plugins/emails';
           
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
        
        $auto_button = $fx->printTwigTemplate("botao.htm", $y_ret, true, $_exp);
        
    }
    $bloco = str_ireplace("{AUTO_BUTTON}", $auto_button, $bloco);
     

    return $bloco;
}



function __getMorada($_enc=array()){

    $morada = array();
    $morada[] = $_enc['morada1_cliente'];
    if($_enc['morada2_cliente']!='') $morada[] = $_enc['morada2_cliente'];
    $morada[] = $_enc['cp_cliente'];
    $morada[] = $_enc['cidade_cliente'];
    $morada[] = $_enc['pais_cliente'];

    return implode(' ', $morada);
}


function __getMoradaEntrega($_enc=array(), $_ret=array()){

    $morada_entrega = array();
    
    if($_enc['pickup_loja_id']>0 && $_enc['metodo_shipping_type']!=4){
        $morada_entrega[] = $_enc['pickup_loja_nome'];
        $morada_entrega[] = $_enc['pickup_loja_morada'];
        $morada_entrega[] = $_enc['pickup_loja_cp'];
        $morada_entrega[] = $_enc['pickup_loja_cidade'];
        $morada_entrega[] = $_enc['entrega_pais'];
    }else{
        $morada_entrega[] = $_enc['entrega_morada1'];
        if($_enc['entrega_morada2']!='') $morada_entrega[] = $_enc['entrega_morada2'];
        $morada_entrega[] = $_enc['entrega_cp'];
        $morada_entrega[] = $_enc['entrega_cidade'];
        $morada_entrega[] = $_enc['entrega_pais'];
        
        if($_enc['metodo_shipping_type']==4){
            $morada_entrega[] = "+";
            $morada_entrega[] = estr2(243).": ".$_enc['pickup_loja_nome'];
        }
    }
    
    
    if((int)$_ret['id']>0){
        $morada_entrega = array();
        $morada_entrega[] = $_ret['shipping_morada1'];
        if($_ret['shipping_morada2']!='') $morada_entrega[] = $_ret['shipping_morada2'];
        $morada_entrega[] = $_ret['shipping_cp'];
        $morada_entrega[] = $_ret['shipping_cidade'];
        $morada_entrega[] = $_ret['shipping_pais_nome'];
    }
    
    return implode(' ', $morada_entrega);
}


function __getTrackingRet($_ret=array()){

    $tracking_ret = "";
    
    if((int)$_ret['id']>0){
    
        $tracking_array = array();
        if(trim($_ret['shipping_tracking_number'])!=''){
            $tracking_array[$_ret['shipping_tracking_number']] = $_ret['shipping_tracking_number'];
        }
        
        if(count($tracking_array)<1){
            $lines_sql = cms_query("SELECT shipping_tracking_number_back FROM ec_encomendas_lines WHERE return_id='".$_ret['id']."' AND shipping_tracking_number_back!='' AND recepcionada>0 AND qnt>0 ");
            while($line_row = cms_fetch_assoc($lines_sql)){
                $tracking_array[$line_row["shipping_tracking_number_back"]] = $line_row["shipping_tracking_number_back"];  
            }
        }
        
        
        $tracking_ret = implode(" / ", $tracking_array);
    }
    
    return $tracking_ret;
    
}

function __getTrackingLink($_enc, $deposito_id=0){
    
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $LG, $fx;                          

    $tracking                       = "";
    $tracking_number_and_link       = "";

    $transportadora                 = [];
    $tipo_envio                     = [];
    $tracking_array                 = [];
    $tracking_number_and_link_array = [];    


    # Envio de expedição parcial em ISA
    if($deposito_id>0){
        $line_row = cms_fetch_assoc(cms_query("SELECT shipping_tracking_number,deposito_cativado FROM ec_encomendas_lines WHERE order_id='".$_enc['id']."' AND shipping_tracking_number!='' AND recepcionada>0 AND qnt>0 AND deposito_cativado='$deposito_id' "));
        if(trim($line_row["shipping_tracking_number"])!=''){
            $_enc['shipping_tracking_number'] = $line_row["shipping_tracking_number"];
        }  
    }        

    $shipping_tracking_info = get_order_shipping_tracking_info($_enc, $transportadora, $tipo_envio);

    if( trim($transportadora['link']) == '' ){
        $transportadora['link'] = $tipo_envio['tracking_url'];
    }        

    if( trim($_enc['shipping_tracking_number']) != '' ){
    
        $tracking_array[ $_enc['shipping_tracking_number'] ] = $_enc['shipping_tracking_number'];

        $nome_transportadora = $shipping_tracking_info['nome'];
        $url_transportadora  = $shipping_tracking_info['url'];

         
        $_exp           = array();
        $_exp['table']  = "exp_emails";
        $_exp['prefix'] = "nome";
        $_exp['lang']   = $LG;
    
        $y_ret = array();
        $y_ret['EMAILS_LAYOUT_COR_BT']     = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
        $y_ret['EMAILS_LAYOUT_COR_BG_BT']  = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
        $y_ret['EMAILS_LAYOUT_COR_LK']     = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        $y_ret['EMAILS_LAYOUT_COR_PORTES'] = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
        $y_ret['EMAILS_LAYOUT_FONT']       = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
    
        $y_ret['url_encomenda'] = $url_transportadora;         
        $y_ret['texto']         = "[@41]";
        
        $array_templates = array();
        if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
        $array_templates[] = _ROOT.'/plugins/emails';
           
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
        
        $tipo_envio_string = $fx->printTwigTemplate("botao.htm", $y_ret, true, $_exp);        
        
        if(in_array($shipping_tracking_info['tipo_envio'], [97,98,99]) && $shipping_tracking_info['manual_com_http']==1){
           
        }else{
        
            if( trim($nome_transportadora) != '' ) $nome_transportadora = '<span style="margin-left:15px !important;color: '.$y_ret['EMAILS_LAYOUT_COR_LK'].' !important;">&#x2794;</span><span style="margin-left:15px !important;">'.$nome_transportadora.'</span>';
        
            $_enc_shipping_tracking_number = '<span style="color: #333 !important;
                                                    display: inline-block;
                                                    text-align: center;
                                                    border: 1px solid #333;
                                                    border-radius: 4px 4px 4px 4px;
                                                    font: normal normal 400 normal 20px / 150% monospace !important;
                                                    padding: 7px;
                                                    margin-top: 10px !important;
                                                    min-width: 150px;">'.$_enc['shipping_tracking_number'].'</span>'.$nome_transportadora;

        }
        
        
        $tracking_number_and_link_array[$_enc['shipping_tracking_number']] = $_enc_shipping_tracking_number.$tipo_envio_string;
    }
    


    if(count($tracking_array)==0){
        
        $link_cor = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        
        $lines_sql = cms_query("SELECT shipping_tracking_number,deposito_cativado FROM ec_encomendas_lines WHERE order_id='".$_enc['id']."' AND shipping_tracking_number!='' AND recepcionada>0 AND qnt>0 AND deposito_cativado GROUP BY shipping_tracking_number ");
        
        while($line_row = cms_fetch_assoc($lines_sql)){
            $tracking_array[$line_row["shipping_tracking_number"]] = $line_row["shipping_tracking_number"];  
               
            $tipo_envio_string = '';
            if($line_row['deposito_cativado']>0){
                $deposito_linha = call_api_func("get_line_table","ec_deposito", "id='".$line_row['deposito_cativado']."'");
                
                if($deposito_linha['tipo_envio']>0){
                    $tipo_envio_l = call_api_func("get_line_table","ec_shipping_types", "id='".$deposito_linha['tipo_envio']."'");       
                    if(trim($tipo_envio_l['tracking_url'])!='') $tipo_envio_string = ' <span style="padding:5px;color: '.$link_cor.' !important;">&#x2794;</span> <a href="'.$tipo_envio_l['tracking_url'].'" style="font-size: 16px; text-decoration: none; color: #333333">'.$tipo_envio_l['tracking_url'].'</a>';
                }else{            
                    if(trim($tipo_envio['tracking_url'])!='') $tipo_envio_string = ' <span style="padding:5px;color: '.$link_cor.' !important;">&#x2794;</span> <a href="'.$tipo_envio['tracking_url'].'" style="font-size: 16px; text-decoration: none; color: #333333">'.$tipo_envio['tracking_url'].'</a>';   
                }
                
            }
            
            
            $_enc_shipping_tracking_number = '<span style="color: #333 !important;
                                                    display: inline-block;
                                                    text-align: center;
                                                    border: 1px solid #333;
                                                    border-radius: 4px 4px 4px 4px;
                                                    font: normal normal 400 normal 20px / 150% monospace !important;
                                                    padding: 7px;
                                                    margin-top: 10px !important;
                                                    min-width: 150px;">'.$line_row['shipping_tracking_number'].'</span>';
                                                  
            $tracking_number_and_link_array[$line_row['shipping_tracking_number']] = $_enc_shipping_tracking_number.$tipo_envio_string;
    
        } 
    }
   
    
    
    $tracking = implode(" / ", $tracking_array);
    
    $tracking_number_and_link = implode("<br>", $tracking_number_and_link_array);
    
    return array('tracking' => $tracking, 'tracking_number_and_link' => $tracking_number_and_link, "transportadora" => $transportadora);

}



?>
