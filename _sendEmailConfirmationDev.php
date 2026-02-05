<?

function _sendEmailConfirmationDev($dev_id=null){

    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;
    
    if ($dev_id > 0){
       $dev_id = (int)$dev_id;
    }else{
       $dev_id = (int)params('devid');
    }
    
    if($dev_id>0) $_dev = call_api_func("get_line_table","ec_devolucoes", "id='".$dev_id."'");
    
    if((int)$_dev["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }

    # Não enviar emails para clientes com esta opção ativa
    if( is_numeric($_dev['cliente_final']) && (int)$_dev['cliente_final'] > 0 ){
        $user = call_api_func("get_line_table","_tusers", "id='".$_dev['cliente_final']."'");
        if( (int)$user['impedir_envio_emails'] == 1 ){
            $arr = array();
            $arr['0'] = 1;
        
            return serialize($arr);
        }
    }
    
    $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$_dev["order_id"]."'");
    
    
    # 2024-12-30
    # Não se envia emails para encomendas ou devoluções marketplace
    if($_enc['pm_marketplace_id']>0){
        return serialize(array("0"=>"0"));
    }
    
    
    
    /*$COUNTRY = $eComm->countryInfo($_dev['cliente_pais_id']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_dev['moeda_id']);*/
 
    $LG = $_enc['entrega_pais_lg'];
    
                               
    $template_id = 55;
    if($_dev['troca']==1) $template_id = 83;
                               
    $template = __getEmailBody($template_id, $LG);
    
    
    # Não enviar email se conteúdo está vazio
    if(trim($template['desc'.$LG])==''){
        return serialize(array("0"=>"0"));
    }
    
                        
    $html = __infoEmailDev($_dev, $_enc, $template, $LG);  
    
    $template['assunto'.$LG] = __substituirVariavies($template['assunto'.$LG], $_dev); 
    

    saveEmailInBD($_dev['cliente_email'], $template['assunto'.$LG], $html, $_dev['cliente_id'], 0, "Confirmação de Devolução", '0', 'ec_email_queue', 1, '', 0, '', 0, $template_id, '', $_enc['id']);
             
    
    /*if(trim($MARKET['email_bcc'])!=''){
        $em = preg_split("/[;,]/",$MARKET['email_bcc']);
        foreach($em as $k => $v){   
            saveEmailInBD($v, $template['assunto'.$LG], $html, $_dev['cliente_id'], 0, "Confirmação de Devolução", '0', 'ec_email_queue', 1);    
        }
    }*/
    
        
    $arr = array();
    $arr['0'] = 1;

    return serialize($arr);
}

               
function __infoEmailDev($_dev, $_enc, $template, $LG){

    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;    
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_FONT, $CONFIG_TEMPLATES_PARAMS;
    
    
    $array_templates = array(); 
    
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
       

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
   
  
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getSingleImage.php';
    
    require_once $_SERVER['DOCUMENT_ROOT']."/api/lib/shortener/shortener.php";


    $COUNTRY = $eComm->countryInfo($_dev['cliente_pais_id']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_dev['moeda_id']);
    
    
    
    $_exp           = array();
    $_exp['table']  = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang']   = $LG;
    
    

    $template['desc'.$LG] = __substituirVariavies($template['desc'.$LG], $_dev); 



    $user = call_api_func("get_line_table","_tusers", "id='".$_dev['cliente_id']."'");
   
    $mcode = base64_encode('0|||'.$user['id'].'|||'.$user['email']);
   
    $url_encomenda = $slocation.'/account/index.php?id=14&return='.$_dev['id'].'&m2code='.$mcode;    
    
    $url_encomenda = short_url($url_encomenda, $_SERVER["SERVER_NAME"]);
        
    if($CONFIG_TEMPLATES_PARAMS['site_version']>23){
        $logotipo = "/images/logo_email@2x.png";             
    }else{
        $logotipo = "/email/sysimages/logo_email_new_layout.jpg";             
        if(file_exists(_ROOT.'/images/logo_email_new_layout.jpg')){
            $logotipo = "/images/logo_email_new_layout.jpg";
        }  
    }  
             
    
    $y                                      = array(); 
    $y['EMAILS_LOGO']                       = $logotipo;
    $y['EMAILS_LAYOUT_COR_BT']              = strlen($EMAILS_LAYOUT_COR_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BT : "#ffffff";
    $y['EMAILS_LAYOUT_COR_BG_BT']           = strlen($EMAILS_LAYOUT_COR_BG_BT)>1  ? "#".$EMAILS_LAYOUT_COR_BG_BT : "#000000";
    $y['EMAILS_LAYOUT_COR_LK']              = strlen($EMAILS_LAYOUT_COR_LK)>1  ? "#".$EMAILS_LAYOUT_COR_LK : "#000000";
    $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
    
    
    
    $y['nome_site']                         = $pagetitle;
    $y['url_site']                          = $slocation;
    $y['url_encomenda']                     = $url_encomenda->short_url;  
    
    $y['template']['assunto']               = strip_tags($template['assunto'.$LG]);
    $y['template']['titulo']                = nl2br(strip_tags($template['titulo'.$LG]));
    $y['template']['descricao']             = nl2br(strip_tags($template['desc'.$LG]));
    
   
    $y['encomenda']['return_ref']           = $_dev['return_ref'];
    $y['encomenda']['troca']                = $_dev['troca'];

    # Carta Back devolução
    $y['encomenda']['files']['back'] = "";
    $file = "prints/CP/enc_".$_enc['id']."_back.pdf";
    if(!in_array($_dev['metodo_shipping_type'], [3,4]) && file_exists($_SERVER['DOCUMENT_ROOT']."/".$file)) {
        $y['encomenda']['files']['back'] = $slocation.'/api/open_file.php?params='.base64_encode(serialize(array("file" => _ROOT.'/'.$file, "name" => $_dev['return_ref'].'.pdf', "sys" => 1)));
    }
   
   
    # Morada de entrega
    $y['encomenda']['entrega']['nome']      = strip_tags($_dev['shipping']);
    $y['encomenda']['entrega']['tipo']      = $_dev['metodo_shipping_type'];
    
    
    $ent_morada1 = $_dev['shipping_morada1'];
    if($_dev['shipping_morada2']!="") $ent_morada1 .= "<br>".$_dev['shipping_morada2'];
    
    $y['encomenda']['entrega']['nome']      = strip_tags($_dev['shipping_nome']);
    $y['encomenda']['entrega']['morada']    = strip_tags($ent_morada1);
    $y['encomenda']['entrega']['cp']        = strip_tags($_dev['shipping_cp']);
    $y['encomenda']['entrega']['cidade']    = strip_tags($_dev['shipping_cidade']);
    $y['encomenda']['entrega']['pais']      = strip_tags($_dev['shipping_pais_nome']);
    
    
    $y['encomenda']['entrega']['telefone']      = strip_tags($_dev['cliente_tel']);
     
      
  
    # Morada de faturação
    $fact_morada = $_dev['cliente_morada1'];
    if($_dev['cliente_morada2']!="") $fact_morada .= " ".$_dev['cliente_morada2'];
    
    
    $y['encomenda']['fact']['nome']      = strip_tags($_dev['cliente_nome']);
    $y['encomenda']['fact']['email']     = strip_tags($_dev['cliente_email']);
    $y['encomenda']['fact']['morada']    = strip_tags($fact_morada);
    $y['encomenda']['fact']['cp']        = strip_tags($_dev['cliente_cp']);
    $y['encomenda']['fact']['cidade']    = strip_tags($_dev['cliente_cidade']);
    $y['encomenda']['fact']['pais']      = $_dev['cliente_pais_nome']; 
    
    
    
    
    # Definições de envio
    $ship_encomenda = call_api_func("get_line_table","ec_shipping_returns", "id='".$_dev['metodo_shipping_id']."'");
   
    $shipping_days_text = '';
    if(trim($_dev['shipping_pickup_time_desc'])!=''){
        $shipping_days_text = estr2(225).": ".$_dev['shipping_pickup_time_desc'];
    }
    

    $y['encomenda']['entrega']['envio']['nome']         = ($ship_encomenda['id']>0) ? $ship_encomenda['nome'.$LG] : "";
    $y['encomenda']['entrega']['envio']['descricao']    = $shipping_days_text;
    
    
    $y['encomenda']['pagamento']                        = $_dev['metodo_devolucao_desc'];
    
    $y['encomenda']['devolucao_oferta']                 = $_dev['devolucao_oferta'];
       
    
    # Linhas da encomenda
    $lines = $eComm->getReturnedLinesEnc($_dev['id']);
   
   
    $vale_de_desconto_tot = 0;
    
    $ENC = cms_fetch_assoc(cms_query("SELECT * FROM ec_encomendas WHERE id='".$_dev['order_id']."' LIMIT 0,1"));     

        
    $s              = "SELECT * FROM `ec_encomendas_original_header` WHERE `order_id`=".$_dev['order_id'];
    $q              = cms_query($s);
    $row_old_info   = cms_fetch_assoc($q);
    
    if( $row_old_info['order_id'] > 0 ){                
        $ENC['valor'] = $row_old_info['original_value'];                
    }
    

    $vale_de_desconto_s = "SELECT SUM(valor_descontado) as total from ec_vales_pagamentos WHERE order_id='".$_dev['order_id']."' AND valor_descontado>0 AND vale_de_desconto=1 LIMIT 0,1"; 
    $vale_de_desconto_q  = cms_query($vale_de_desconto_s);  
    $vale_de_desconto_r  = cms_fetch_assoc($vale_de_desconto_q);
    
    if($vale_de_desconto_r['total']>0){      
        $TOTAL_ENC = ($ENC['valor']-$ENC['custo_pagamento']-$ENC['imposto']) + $vale_de_desconto_r['total'];                  
    }
                 
  
    # 2025-06-09
    # Configuração a indicar que os pontos não afetam os portes
    $CONF_PONTOS = cms_fetch_assoc(cms_query("SELECT campo_11 FROM b2c_config_loja WHERE id=20 LIMIT 0,1"));     
    if($CONF_PONTOS['campo_11']==1) $TOTAL_ENC -= $ENC['portes'];
          
                      
        
    foreach($lines as $k => $v){

        $_qtds          = $v['qtds'];
        

        if($vale_de_desconto_r['total']>0){
            $vale_de_desconto_tot += (((($v['valor_final']-$v['desconto_final'])*100)/$TOTAL_ENC)*$vale_de_desconto_r['total'])/100;     
        }
        
        $label_preco_r  = $_enc['moeda_prefixo'].number_format($v['valoruni_anterior_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];       
        $label_preco    = $_enc['moeda_prefixo'].number_format($v['valor_final']-$v['desconto_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        $label_desconto = $_enc['moeda_prefixo'].number_format($v['desconto_final'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
               
        
        if($v['valoruni_anterior_final']==0){
            $label_preco_r  = 0;  
            $label_desconto = 0;  
        }
        

        $more_desc = '';
        
        $more_desc_array = array();
        if(trim($v['cor_name'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_COR==0) $more_desc_array[] = estr2(1)." ".$v['cor_name'];     
        if(trim($v['tamanho'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_TAMANHO==0) $more_desc_array[] = estr2(2)." ".$v['tamanho'];                        
        $more_desc = implode(' / ', $more_desc_array); 
        
        if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['composition'] = ''; 
        
        $points = "";
        if($v['points']>0){
            $points = "+ ".$v['points']*$_qtds." ".estr2(350);
        }
        
              
        $imagem = _getSingleImage(60,0,3,$v['ref'],1);
        $imagem = str_replace('../', $slocation.'/', $imagem);
        
        $y['encomenda']['produtos'][] = array( "imagem" => $imagem,
                                                "nome" => $v['nome'],
                                                "desc" => $more_desc,
                                                "caracteristicas" => $v['composition'],
                                                "preco" => $label_preco,
                                                "preco_riscado" => $label_preco_r, 
                                                "desconto" => $label_desconto,
                                                "qtds" => $_qtds, 
                                                "pontos" => $points );

    }
    
    
    $total_pago = $total      = $_enc['moeda_prefixo'].number_format($_dev['valor']-$_dev['valor_recolha'], $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    
    $y['encomenda']['total']                = $total;
    $y['encomenda']['total_pago']           = $total_pago;
    
    $y['encomenda']['valor_recolha']        = $_enc['moeda_prefixo'].number_format($_dev['valor_recolha'], $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    $y['encomenda']['valor_recolha_raw']    = $_dev['valor_recolha'];


    $y['encomenda']['valor_fidelizacao']        = $_enc['moeda_prefixo'].number_format($vale_de_desconto_tot, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    $y['encomenda']['valor_fidelizacao_raw']    = $vale_de_desconto_tot;
    
    # Perguntas
    $y['perguntas'] = array();
    if(trim($template['perguntas'])!=''){
        
        $perg_s = "SELECT nome$LG, desc$LG FROM _tfaqs_emails WHERE id IN (".$template['perguntas'].") ORDER BY ordem, nome$LG ASC";
        $perg_q = cms_query($perg_s);
        while($perg = cms_fetch_assoc($perg_q)){
            $y['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
        }
    }

          
    
    $html = $fx->printTwigTemplate("confirmacao_dev.htm", $y, true, $_exp);    

    return $html;

}



function __substituirVariavies($bloco, $_dev){

    global $pagetitle;
    
    
    if($_dev['devolucao_oferta']==1){
        $cliente = get_line_table("_tusers", "id='".$_dev['cliente_id']."'");
        $_dev['cliente_nome'] = $cliente['nome'];
    }
    

    $bloco = str_ireplace("{CLIENT_NAME}", $_dev['cliente_nome'], $bloco);
    $bloco = str_ireplace("{ORDER_REF}", $_dev['order_ref'], $bloco);
    $bloco = str_ireplace("{RETURN_REF}", $_dev['return_ref'], $bloco);    
    $bloco = str_ireplace("{RETURN_ID}", $_dev['id'], $bloco);
    $bloco = str_ireplace("{PAGETITLE}", $pagetitle, $bloco);           

    return $bloco;
}


?>

