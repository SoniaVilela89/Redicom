<?

function _sendEmailConfirmationEncB2B($enc_id=null, $emailto= null, $vendedor = 0, $emailbcc = '')
{

    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;

    if ($enc_id > 0) {
        $enc_id   = (int)$enc_id;
        $emailto  = $emailto;
        $emailbcc = $emailbcc;
        $vendedor = (int)$vendedor;
    } else {
        $enc_id   = (int)params('orderid');
        $emailto  = params('emailto');
        $emailbcc = params('emailbcc');
        $vendedor = (int)params('vendedor');
    }
    
    if($enc_id>0) $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
    
    if((int)$_enc["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }
    
    
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    
    $_lg = strtolower($_enc['idioma_user']);
    if( trim($_lg)=="" ) $_lg = "pt";
    if( $_lg=="en" ) $_lg = "gb";
    if( $_lg=="es" ) $_lg = "sp";
    $LG = $_lg;
    
    
   #######################
    
    $userID  = $_enc['cliente_final'];
   
   
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
    

    $_exp = array();
    $_exp['table'] = "ec_exp";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;
    
 
    $_exp_emails           = array();
    $_exp_emails['table']  = "exp_emails";
    $_exp_emails['prefix'] = "nome";
    $_exp_emails['lang']   = $LG;


    $cliente_q = cms_query("select * from _tusers where id='".$_enc['cliente_final']."' LIMIT 0,1");
    $cliente_r = cms_fetch_assoc($cliente_q);
    
    
    # Não enviar emails para clientes com esta opção ativa
    if( (int)$cliente_r['impedir_envio_emails'] == 1 ){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);
    }
    
    
    $vendedor_q = cms_query("SELECT id, email, b2b_vendedor_email_enc_cc FROM _tusers_sales WHERE id='".$cliente_r['vendedor']."' LIMIT 0,1");
    $vendedor_r = cms_fetch_assoc($vendedor_q);
    


    # Se for encomenda de pagamento pendente, envia o template id=91
    $regra_catalogo = cms_query("SELECT property_value FROM ec_encomendas_props WHERE order_id='".$_enc['id']."' AND property='REGRACAR' LIMIT 0,1");
    $regra_catalogo = cms_fetch_assoc($regra_catalogo);
    if($regra_catalogo['property_value'] < 0) {

        $email = __getEmailBody(91, $LG);
        $email_template_id = 91;

    # Senão, restantes encomendas
    } else {


        
        $email = __getEmailBody(1, $LG); #CC
        
        if($_enc['tracking_tipopaga']==4 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==10 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==12 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==13 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==21 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==23 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==29 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB   
        if($_enc['tracking_tipopaga']==32 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==37 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==80 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==82 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB
        if($_enc['tracking_tipopaga']==84 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB     
        if($_enc['tracking_tipopaga']==108 && $_enc['pagref']=="") $email = __getEmailBody(14, $_enc['entrega_pais_lg']); #MB        
        
        if($_enc['tracking_tipopaga']==6 && $_enc['pagref']=="") $email = __getEmailBody(54, $_enc['entrega_pais_lg']); #TB
        if($_enc['tracking_tipopaga']==53 && $_enc['pagref']=="") $email = __getEmailBody(54, $_enc['entrega_pais_lg']); #TB            
        
        if($_enc['tracking_tipopaga']==31) $email = __getEmailBody(38, $_enc['entrega_pais_lg']); #Sem cartão cliente associado
        

        $email_template_id = 1;
        if( in_array( $_enc['tracking_tipopaga'], [4,10,12,13,21,23,29,32,37,80,82,84,108] ) ){
            $email_template_id = 14;
        }else if( in_array( $_enc['tracking_tipopaga'], [6,53] ) ){
            $email_template_id = 54;
        }else if( $_enc['tracking_tipopaga'] == 31 ){
            $email_template_id = 38;
        }


    }

    $email_documentos = 0;
    $sql_lines = "SELECT pid,valoruni,tamanho FROM ec_encomendas_lines WHERE order_id='".$_enc["id"]."' AND tipo_linha=6";
    $res_lines = cms_query($sql_lines);
    $num_lines = cms_num_rows($res_lines);
    if((int)$num_lines > 0){        
        $email = __getEmailBody(101, $LG); #Pagamento de documentos
        $email_documentos = 1;
    }

        
    $base_codigo = base64_encode('0|||'.$_enc['cliente_final'].'|||'.$_enc['email_cliente']);
    $more_link   = '&m2code='.$base_codigo;    
        
    #$link = $slocation."/api/api.php/getAccountOrderPrint/".base64_encode($_enc['id'])."/1?rand=".rand();                           
    $link = $slocation."/account/index.php?id=17&order=".$_enc['id']."&f=1".$more_link;
  
    if($vendedor == 1){
        $base_codigo_vendedor =  base64_encode($vendedor_r['id'].'|||'.$vendedor_r['email']);
        $base_codigo_cliente =  base64_encode($_enc['cliente_final'].'|||'.$_enc['email_cliente']);
        $more_link_vendedor   = '&scode='.$base_codigo_vendedor."&id_cli=".$base_codigo_cliente;  

        $link = $slocation."/account/index.php?id=17&order=".$_enc['id']."&f=1&sale=1".$more_link_vendedor;
    }

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/emails', false, _ROOT.'/temp_twig/');

    if( $_enc['pagref']==""){

        $y['url_site'] = $slocation;
        
        $valor_cheques  = $eComm->getInvoiceChecksValue($_enc['id']);
        $total_pago     = $_enc['valor']-$valor_cheques; 
    
        $total_pago = $_enc['moeda_prefixo'].number_format($total_pago, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    
    
        $y['total_pago'] = $total_pago;
        
             
        if( in_array( $_enc['tracking_tipopaga'], array(4,10,12,13,21,23,29,31,32,37,80,82,84,108) ) ){  
            
            $y['mb_exp_1'] = estr2(92);
            $y['mb_exp_2'] = estr2(93);
            $y['mb_exp_3'] = estr2(94);
                        
            $y['ref_multi_enti'] = $_enc['ref_multi_enti'];
            $y['ref_multi'] = $_enc['ref_multi'];
            
            $html = $fx->printTwigTemplate("confirmacao_b2b_topo_mb.htm", $y, true, $_exp); 
            $html .= "<span>".estr2(91)."</span><br><br><br>";
              
           
        } elseif($_enc['tracking_tipopaga']==6){
            $topo = 'tb';
            
            $y['tb_exp_1'] = 'IBAN:';
            $y['tb_exp_2'] = 'SWIFT:';
            $y['tb_exp_3'] = estr2(94);
            
            
            $y['tb_iban'] = $_enc['iban'];
            $y['tb_swift'] = $_enc['swift'];
            
            $html = $fx->printTwigTemplate("confirmacao_b2b_topo_tb.htm", $y, true, $_exp); 
            $html .= "<span>".estr2(138)."</span><br><br><br>";
        }
        

    }    

  
    
    
    $email['blocopt'] = nl2br($email['blocopt']);

    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT;

    $t['EMAILS_LOGO']                       = $logotipo;
    $t['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
    $t['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
    $t['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
    $t['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
    $t['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
        
    # inc_titles.htm do email (titulo e número de encomenda)
    $t['template']['titulo']    = str_replace("{ORDERID}", '', $email['nomept']);
    $t['template']['titulo']    = str_replace("{ORDER_REF}", '', $t['template']['titulo']);
    $t['template']['titulo']    = str_replace("{ORDER_REF}", '', $t['template']['titulo']);
    $t['template']['titulo']    = trim(strip_tags($t['template']['titulo']));
    $t['template']['cabecalho'] = "[@1] #".$_enc['order_ref'];
    $titles = $fx->printTwigTemplate("inc_titles.htm", $t, true, $_exp_emails);
    $email['blocopt'] = $titles.$email['blocopt'];


    #Variáveis antigas para compatibilizar - descontinuadas
    $email['blocopt'] = str_ireplace("{ORDERID}", $_enc['id'], $email['blocopt']);
    $email['nomept'] = str_ireplace("{ORDERID}", $_enc['id'], $email['nomept']);
    
    #Variaveis gerais 
    
    $email['blocopt'] = str_ireplace("{CLIENT_NAME}", $_enc['nome_cliente'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{CLIENT_COD_ERP}", $cliente_r['cod_erp'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{ORDER_ID}", $_enc['id'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{ORDER_DATE}", $_enc['data'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);        
    $email['blocopt'] = str_ireplace("{ORDER_TOTAL}", $total, $email['blocopt']);
    $email['blocopt'] = str_ireplace("{ENTITY}", $_enc['ref_multi_enti'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{REFERENCE}", $_enc['ref_multi'], $email['blocopt']);
    
    $email['nomept'] = str_ireplace("{ORDER_ID}", $_enc['id'], $email['nomept']);
    $email['nomept'] = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $email['nomept']);
              
              
    $_toemail = $_enc['email_cliente'];          
    if(trim($emailto)!='' && $emailto!==' ') $_toemail = $emailto;
    
    
    
    
    # Um email igaul ao original mas com o link direto para o pdf em vez de encomenda
    $email['blocopt_moradas'] = $email['blocopt'];
    
    if((int)$email_documentos == 0){
        $html_principal = $html.'<div class="em-button-border"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$link.'" style="height:50px;v-text-anchor:middle;width:200px;" arcsize="8%" stroke="f" fillcolor="#000000"><w:anchorlock/><center><![endif]--><a href="'.$link.'" target="_blank" style="background-color:#000000;border-radius:4px;color:#ffffff;display:inline-block;font-family: Arial, Helvetica, sans-serif;font-size:16px;font-weight:normal;line-height:50px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;" class="em-button">'.estr2(110).'</a><!--[if mso]></center></v:roundrect><![endif]--></div>';
    }else{
        $_exp           = array();
        $_exp['table']  = "exp_emails";
        $_exp['prefix'] = "nome";
        $_exp['lang']   = $LG;

        $y                                      = array(); 
        $y['EMAILS_LOGO']                       = $logotipo;
        $y['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
        $y['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
        $y['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
        $y['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
        $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
        
    
        
        # Linhas da encomenda
        $lines = $eComm->getOrderLinesEnc($_enc['id'], 1);
        
        $descontos = 0;
        $subtotal  = 0;
        
        #Expedition Info
        $depositos = array();
        $services = array();
        $delivery_days = array();
        #Expedition Info
        foreach($lines as $k => $v){

            $subtotal   += $v['valor_final']-$v['desconto_final']; 
            
            $desconto_temp = 0;
            if($v['valoruni_anterior_final']>0) $desconto_temp =  $v['valoruni_anterior_final']-$v['valor_final'];
            $descontos  += $v['desconto_final']+$desconto_temp;
                    
            $_qtds          = $v['qnt_total'];
            
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
                $preco_total = "";
                $label_preco_r = 0;
                $label_desconto = 0;  
            }
            
            
            $more_desc = '';
    
            
            $more_desc_array = array();
            if(trim($v['cor_name'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_COR==0) $more_desc_array[] = estr2(1)." ".$v['cor_name'];     
            if(trim($v['tamanho'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_TAMANHO==0) $more_desc_array[] = estr2(2)." ".$v['tamanho'];                        
            $more_desc = implode(' / ', $more_desc_array); 
            
            if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['composition'] = '';    

    
            
            $points = "";
            if($v['points']>0 && (int)$user["sem_registo"] == 0 ){
                $points = "+ ".$v['points']*$_qtds." ".estr2(350);
            }
            
            $servico = 0;
            if($v['id_linha_orig']>0) $servico = 1;
            
            $y['encomenda']['produtos'][] = array( "imagem" => $imagem,
                                                    "nome" => $v['nome'],
                                                    "desc" => $v['sku_group'],
                                                    "caracteristicas" => $v['composition'],
                                                    "preco" => $label_preco,
                                                    "preco_riscado" => $label_preco_r, 
                                                    "desconto" => $label_desconto,
                                                    "promo" => $label_promo,
                                                    "qtds" => $_qtds, 
                                                    "pontos" => $points,
                                                    "servico" => $servico );
            
             
        }

        if($descontos>0){
            $y['encomenda']['descontos_total'] = $_enc['moeda_prefixo'].number_format($descontos,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        }else{
            $y['encomenda']['descontos_total'] = 0;   
        }
        
    
        $valor_cheques  = $eComm->getInvoiceChecksValue($_enc['id']);
        $total_pago_sem_format = $total_pago = $_enc['valor']-$valor_cheques; 
        
        
        
        
        $total      = $_enc['moeda_prefixo'].number_format($_enc['valor'], $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        
        $total_pago = $_enc['moeda_prefixo'].number_format($total_pago, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
         
        $portes     = $_enc['moeda_prefixo'].number_format($_enc['portes'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    
        $subtotal   = $_enc['moeda_prefixo'].number_format($subtotal, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];  
        
    
        $y['encomenda']['total']                = $total;
        $y['encomenda']['total_pago']           = $total_pago;
        $y['encomenda']['total_pago_s_format']  = $total_pago_sem_format;
        $y['encomenda']['portes_valor']         = $_enc['portes'];
        $y['encomenda']['portes']               = $portes;
       
      
        $y['encomenda']['valores']['antes_portes'] = array();
        $y['encomenda']['valores']['depois_portes'] = array();
        
        if($subtotal != $total)        
            $y['encomenda']['valores']['antes_portes'][] = array( "texto" => "[@6]", "valor" => $subtotal);  
        
        # Pagamentos efectuados     
        $y['encomenda']['pagamento']          = $_enc['metodo_pagamento'];
        $y['encomenda']['pagamento_vale']     = 0;
        if($valor_cheques>0){
            $y['encomenda']['pagamento_vale'] = $_enc['moeda_prefixo'].number_format($valor_cheques,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        }

        $y['encomenda']['vales_desconto'] = $vales_array_desconto;
    
        if($_enc['valor_credito'] > 0){
            $y['encomenda']['valores']['antes_portes'][] = array( "texto" => "[@21]", 
                                            "valor" => $_enc['moeda_prefixo'].number_format($_enc['valor_credito']-$_enc['desconto_credito'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );                                    
        }

        if($_enc['imposto'] > 0){
            $y['encomenda']['valores']['depois_portes'][] = array( "texto" => "[@18]", 
                                            "valor" => $_enc['moeda_prefixo'].number_format($_enc['imposto'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );             
        }

        if($_enc['custo_pagamento'] > 0){
            $y['encomenda']['valores']['depois_portes'][] = array( "texto" => "[@22]", "valor" => $_enc['moeda_prefixo'].number_format($_enc['custo_pagamento'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );             
        }

        $detalhe = $fx->printTwigTemplate("inc_documentos.htm", $y, true, $_exp);  
        $html_principal = $html.$detalhe;    
    }

    $email['blocopt'] = str_ireplace("{DETALHES}", $html_principal, $email['blocopt']);
    


    if(is_callable('custom_controller_send_email_confirmation_B2B')){
        # Pedido pelo carlos - 23/09/2021
        #call_user_func_array('custom_controller_send_email_confirmation_B2B', array(&$email));
        call_user_func_array('custom_controller_send_email_confirmation_B2B', array(&$email, $_enc));
    }      




    sendEmailFromController($email['blocopt'], $email['nomept'], $_toemail, "", $userID, "Confirmação de Encomenda", 0, $email_template_id, $emailbcc, $_enc['id']);
    
    # 2022-01-27
    # Enviar email para utilizador restrito
    if( (int)$_enc['b2b_id_utilizador_restrito'] > 0 && trim($emailto) == "" ){
        $restricted_user = call_api_func("get_line_table","_tusers", "id='".$_enc['b2b_id_utilizador_restrito']."' AND id_user='$userID'");
        if( trim($restricted_user['email']) != "" ){
            sendEmailFromController($email['blocopt'], $email['nomept'], $restricted_user['email'], "", $userID, "Confirmação de Encomenda", 0, $email_template_id);
        }      
    }

    if ($vendedor == 1 && !empty($vendedor_r['b2b_vendedor_email_enc_cc'])) {
        $v_em = preg_split("/[;,]/", $vendedor_r['b2b_vendedor_email_enc_cc']);
        foreach ($v_em as $v) {
            sendEmailFromController($email['blocopt'], $email['nomept'], $v, "", $userID, "Confirmação de Encomenda", 0, $email_template_id);
        }
    }      
            
    if( trim($MARKET['email_bcc']) != '' && $_toemail != $vendedor_r['email'] ){
        $em = preg_split("/[;,]/",$MARKET['email_bcc']);     
        foreach($em as $k => $v){
            sendEmailFromController($email['blocopt'], $email['nomept'], $v, "", $userID, "Confirmação de Encomenda", 0, $email_template_id);
        }
    }
   
                                    
  
    $morada_sel_q = cms_query("SELECT property_value FROM ec_encomendas_props WHERE order_id='".$_enc['id']."' AND property='ADDRESS_ID' LIMIT 0,1");
    $morada_sel_r = cms_fetch_assoc($morada_sel_q);
    
    if($morada_sel_r['property_value']>0){
    
        $morada_q = cms_query("SELECT email_loja FROM ec_moradas WHERE id='".$morada_sel_r['property_value']."'  LIMIT 0,1");
        $morada_r = cms_fetch_assoc($morada_q);
    
        if($morada_r['email_loja']>0){
            $loja_q = cms_query("SELECT email FROM ec_lojas WHERE id='".$morada_r['email_loja']."'  LIMIT 0,1");
            $loja_r = cms_fetch_assoc($loja_q);
            
            if(trim($loja_r['email'])!=''){
            
            
                # Um email igaul ao original mas com o link direto para o pdf em vez de encomenda

                $link = $slocation."/api/api.php/getAccountOrderPrintV2/".base64_encode($_enc['id'])."/2";
                                                
                $html_principal = $html.'<div class="em-button-border"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="'.$link.'" style="height:50px;v-text-anchor:middle;width:200px;" arcsize="8%" stroke="f" fillcolor="#000000"><w:anchorlock/><center><![endif]--><a href="'.$link.'" target="_blank" style="background-color:#000000;border-radius:4px;color:#ffffff;display:inline-block;font-family: Arial, Helvetica, sans-serif;font-size:16px;font-weight:normal;line-height:50px;text-align:center;text-decoration:none;width:200px;-webkit-text-size-adjust:none;" class="em-button">'.estr2(110).'</a><!--[if mso]></center></v:roundrect><![endif]--></div>';

                $email['blocopt_moradas'] = str_ireplace("{DETALHES}", $html_principal, $email['blocopt_moradas']);

                sendEmailFromController($email['blocopt_moradas'], $email['nomept'], $loja_r['email'], "", $userID, "Confirmação de Encomenda", 0, $email_template_id);     
            }
        }
        
    } 
        
    
    $arr = array();
    $arr['0'] = 1;

    return serialize($arr);
}

?>
