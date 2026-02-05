<?

function _sendEmailConfirmationEnc($enc_id=null, $emailto=null, $template=null){

    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;
    global $SETTINGS_LOJA, $EMAILS_LAYOUT_SEND, $HIDE_ZIPCODE, $API_CONFIG_PREV_ENTREGA_PENDENTE;
    
    if ($enc_id > 0){
       $enc_id    = (int)$enc_id;
       $emailto   = $emailto;
       $template  = (int)$template;
    }else{
       $enc_id    = (int)params('orderid');
       $emailto   = params('emailto');
       $template  = (int)params('template');
    }
    
    if($enc_id>0) $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
     
    if((int)$_enc["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }
    


    # Não enviar emails para clientes com esta opção ativa
    if( is_numeric($_enc['cliente_final']) && (int)$_enc['cliente_final'] > 0 ){
        $user = call_api_func("get_line_table","_tusers", "id='".$_enc['cliente_final']."'");
        if( (int)$user['impedir_envio_emails'] == 1 ){
            $arr = array();
            $arr['0'] = 1;
        
            return serialize($arr);
        }
    }   
    

    
             
    
    $email = __getEmailBody(1, $_enc['entrega_pais_lg']); #CC
    
    # 85, 86, 89 - Checkout Ideal Sofort Giropay
    # 96 - Multicaixa
    # 122 - Pay Later
    # Restantes ids - MB
    if($_enc['pagref']=="" && in_array($_enc['tracking_tipopaga'], [4,10,12,13,21,23,29,32,37,80,82,84,85,86,89,96,108,122])){
        $email = __getEmailBody(14, $_enc['entrega_pais_lg']);
    }

    # TB
    if($_enc['pagref']=="" && in_array($_enc['tracking_tipopaga'], [6,53,120])){
        $email = __getEmailBody(54, $_enc['entrega_pais_lg']);
    }
        
    
    if($_enc['tracking_tipopaga']==77) $email = __getEmailBody(65, $_enc['entrega_pais_lg']); #Cetelem
    if($_enc['tracking_tipopaga']==87) $email = __getEmailBody(65, $_enc['entrega_pais_lg']); #Universo
    if($_enc['tracking_tipopaga']==128) $email = __getEmailBody(65, $_enc['entrega_pais_lg']); #SEQURA
    
    if($_enc['tracking_tipopaga']==31) $email = __getEmailBody(38, $_enc['entrega_pais_lg']); #Sem cartão cliente associado
    
    
    
    $email_template_id = 1;
    if( in_array( $_enc['tracking_tipopaga'], [4,10,12,13,21,23,29,32,37,80,82,84,96,108,122] ) ){
        $email_template_id = 14;
    }else if( in_array( $_enc['tracking_tipopaga'], [6,53,120] ) ){
        $email_template_id = 54;
    }else if( in_array( $_enc['tracking_tipopaga'], [77,87] ) ){
        $email_template_id = 65;
    }else if( $_enc['tracking_tipopaga']==31 ){
        $email_template_id = 38;
    }

    #Raffle
    if( (int)$_enc['sorteio_id'] > 0 ){
      $email_template_id = 75;
      if( $template == 76 ){
        $email_template_id = 76;
        $email_only_products = 1;
      }elseif( $template == 77 ){
        $email_template_id = 77;
      }
      $email = __getEmailBody($email_template_id, $_enc['entrega_pais_lg']);
    }
    #Raffle
    
    
    #Envio parcial - ISA
    if($_enc['tracking_status']>=40 && $_enc['tracking_status']!=100 && !in_array( $_enc['tipoencomenda'], [3, 4, 5, 6] )){
    
        $dataTracking = strtotime($_enc['tracking_pago']);
        $dezMinAtras  = time() - (10 * 60);
    
        # Por segurança a encomenda teve de ser paga há mais de 10 minutos, 
        # para que encoemendas normais não entrem aqui quando avançam rapido no OMS e o cliente só depois aterra no thankyoupage que faz mandar a confirmação de encomenda
        # e iria enctrar neste if e não pode
        if ($dataTracking <= $dezMinAtras) {
             $email = __getEmailBody(87, $_enc['entrega_pais_lg']);      
        }
    
       
    }
    
    
    # POS
    if($_enc['tipoencomenda'] == 3) $email = __getEmailBody(105, $_enc['entrega_pais_lg']);  
    
    
    $userID  = $_enc['cliente_final'];
    
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
    

    $LG = $_enc['entrega_pais_lg'];
    
    
    if(($email['new_layout']==1 || (int)$EMAILS_LAYOUT_SEND==1) && (int)$B2B==0){    
        
     
        $html = __sendEmailConfirmationEncNew($_enc, $email, $LG, $email_only_products, $user);

        $_toemail = $_enc['email_cliente'];          
        if(trim($emailto)!='' && $emailto!==' ') $_toemail = $emailto;              
    
        $email['assunto'.$LG] = __substituirVariavies($email['assunto'.$LG], $_enc, 0); 
        
        

        $id_email = saveEmailInBD($_enc['email_cliente'], $email['assunto'.$LG], $html, $_enc['cliente_final'], 0, "Confirmação de Encomenda", '0', 'ec_email_queue', 1, '', 0, '', 0, $email_template_id, '', $_enc['id']);
        
        if(trim($MARKET['email_bcc'])!=''){
            $em = preg_split("/[;,]/",$MARKET['email_bcc']);
            foreach($em as $k => $v){   
                saveEmailInBD($v, $email['assunto'.$LG], $html, $_enc['cliente_final'], 0, "Confirmação de Encomenda", '0', 'ec_email_queue', 1, '', 0, '', 0, $email_template_id, '', $_enc['id']);
            }
        }
        
        
        $arr = array();
        $arr['0'] = $id_email;
    
        return serialize($arr);
    }
    

    #######################
    
   
    $_exp = array();
    $_exp['table'] = "exp";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;
    
    
    $lines = $eComm->getOrderLinesEnc($_enc['id'], 1);

    $valor_cheques = $eComm->getInvoiceChecksValue($_enc['id']);
    $total = $_enc['valor']-$valor_cheques; 

    $total = $_enc['moeda_prefixo'].number_format($total, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    
    $portes = $_enc['moeda_prefixo'].number_format($_enc['portes'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];



    $html = "<table border='0' cellpadding='0' cellspacing='0' width='622'><tr>";

    
    if( in_array( $_enc['tracking_tipopaga'], array(4,10,12,13,21,23,29,32,37,80,82,84,96,108) ) && $_enc['pagref'] == "" ){
        $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(90).":</b></td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='padding:13px 16px; height: 65px;'>
                          <span style='padding-bottom: 2px;'><b>".estr2(92).":</b> ".$_enc['ref_multi_enti']."</span><br />
                          <span style='padding-bottom: 2px;'><b>".estr2(93).":</b> ".$_enc['ref_multi']."</span><br />
                          <span style='padding-bottom: 2px;'><b>".estr2(94).":</b> ".$total."</span>
                        </td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #F4F4F4; padding:13px 14px; padding-bottom:12px; line-height: 14px;'>".estr2(91)."</td>
                      </tr>
                    </table>
                  </td>";

        $html .= "<td width='14'>&nbsp;</td>";
    }
    
    if( ($_enc['tracking_tipopaga']==24) && $_enc['pagref']==""){
        $qrcode = "seqr/enc_".$_enc["id"].".png";
        $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(90).":</b></td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='padding:10px 20px; height: 65px;'>
                          <a href='".$_enc['link_seqr']."'>
                            <img src='$qrcode' style='width: 150px;margin: auto;' border='0'/>
                          </a>
                        </td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #F4F4F4; padding:13px 14px; padding-bottom:12px; line-height: 14px;'>".estr2(91)."</td>
                      </tr>
                    </table>
                  </td>";

        $html .= "<td width='14'>&nbsp;</td>";
    }
          
    if($_enc['tracking_tipopaga']==6 || $_enc['tracking_tipopaga']==120){
        $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(90).":</b></td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='padding:13px 16px; height: 65px;'>
                          <span style='padding-bottom: 2px;'>".estr2(137)."</span><br>";
        
        if(trim($_enc['iban'])!=''){
            $html .= "<span style='padding-bottom: 2px;'>IBAN: <b>".$_enc['iban']."</b></span><br>";
        }
        
        if(trim($_enc['swift'])!=''){
            $html .= "<span style='padding-bottom: 2px;'>SWIFT: <b>".$_enc['swift']."</b></span>";
        }
        
         
        $html .= "</td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #F4F4F4; padding:13px 14px; padding-bottom:12px; line-height: 14px;'>".estr2(138)."</td>
                      </tr>
                    </table>
                  </td>";

        $html .= "<td width='14'>&nbsp;</td>";
    }


    $shipping_days_text = '';
    if(trim($_enc['shipping_days'])!=''){
        $shipping_days_text = "<table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                  <tr>
                                    <td style='background-color: #F4F4F4; padding:13px 14px; padding-bottom:12px; line-height: 14px;'>
                                    ".estr2(96).' '.$_enc['shipping_days'].' '.estr2(97)."</td>
                                  </tr>
                                </table>";
    }

    $ship_encomenda = call_api_func("get_line_table","ec_shipping", "id='".$_enc['metodo_shipping_id']."'");
    if(trim($ship_encomenda['bloco'.$LG])!=''){
        $shipping_days_text = "<table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                  <tr>
                                    <td style='background-color: #F4F4F4; padding:13px 14px; padding-bottom:12px; line-height: 14px;'>
                                    ".str_replace('{ORDER_ID}', $_enc['id'], $ship_encomenda['bloco'.$LG])."</td>
                                  </tr>
                                </table>";
    }
         
         
    $telefone = '';
    if($_enc['tel_cliente']!="") $telefone = "<span style='padding-bottom: 2px;'><b>".estr2(85).":</b> ".$_enc['tel_cliente']."</span><br />";
   
     
    $nome_metodo_envio = '';
    $metodo_envio = cms_fetch_assoc(cms_query("SELECT nome$LG, id FROM ec_shipping WHERE id='".$_enc['metodo_shipping_id']."' LIMIT 0,1"));
    
    if((int)$metodo_envio['id']>0){
        $nome_metodo_envio = "<span style='padding-bottom: 2px;'><b>".$metodo_envio['nome'.$LG]."</b></span><br />";    
    }           
    
    if( (int)$B2B == 0 && (int)$HIDE_ZIPCODE == 1 ){
        $_enc_cp_cliente = "";
        $_enc_entrega_cp = "";
    }else{
        $_enc_entrega_cp = "<span style='padding-bottom: 2px;'><b>".estr2(43).":</b> ".$_enc['entrega_cp']."</span><br />";
        $_enc_cp_cliente = "<span style='padding-bottom: 2px;'><b>".estr2(43).":</b> ".$_enc['cp_cliente']."</span><br />";
    }

    if( (int)$_enc['pickup_loja_id']>0 && $_enc['metodo_shipping_type']!=4 ){
       $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(95).":</b></td>
                      </tr>
                    </table>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                         <td style='padding:13px 16px; height: 65px;'>
                            $nome_metodo_envio
                            <span style='padding-bottom: 2px;'><b>".estr2(40).":</b> ".$_enc['entrega_nome']."</span><br />
                            $telefone
                            <span style='padding-bottom: 2px;'><b>".estr2(243).":</b> ".$_enc['pickup_loja_nome']."</span><br />
                            <span style='padding-bottom: 2px;'><b>".estr2(84).":</b> ".$_enc['pickup_loja_morada']."</span><br />
                            <span style='padding-bottom: 2px;'><b>".estr2(43).":</b> ".$_enc['pickup_loja_cp']."</span><br />
                            <span style='padding-bottom: 2px;'><b>".estr2(44).":</b> ".$_enc['pickup_loja_cidade']."</span> 
                          </td>
                      </tr>
                    </table>
                    ".$shipping_days_text."
                  </td>";
    }else{
        $more_loja = '';
        if($_enc['metodo_shipping_type']==4){
            $more_loja = "<br><span style='padding-bottom: 2px;'><b>+ ".estr2(243).":</b> ".$_enc['pickup_loja_nome']."</span><br>";
        }
    
        if($lines[0]['egift']==0){
        
            $distrito = "";
            if($SETTINGS['show_distrito']!=0 && $_enc['entrega_distrito']!=""){
                $distrito = "<br /><span style='padding-bottom: 2px;'><b>".estr2(328).":</b> ".$_enc['entrega_distrito']."</span><br />";
            }
            
            $ent_morada1 = $_enc['entrega_morada1'];
            if($_enc['entrega_morada2']!="") $ent_morada1 .= "<br>".$_enc['entrega_morada2'];
            
            $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                          <tr>
                            <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(95).":</b></td>
                          </tr>
                        </table>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                          <tr>
                             <td style='padding:13px 16px; height: 65px;'>
                                $nome_metodo_envio
                                <span style='padding-bottom: 2px;'><b>".estr2(40).":</b> ".$_enc['entrega_nome']."</span><br />
                                $telefone
                                <span style='padding-bottom: 2px;'><b>".estr2(84).":</b> ".$ent_morada1."</span><br />
                                $_enc_entrega_cp
                                <span style='padding-bottom: 2px;'><b>".estr2(44).":</b> ".$_enc['entrega_cidade']."</span><br />
                                $distrito
                                <span style='padding-bottom: 2px;'><b>".estr2(45).":</b> ".$_enc['entrega_pais']."</span><br />
                                
                                ".$more_loja."
                              </td>
                          </tr>
                        </table>
                        ".$shipping_days_text."
                      </td>";
        }                      
    }
    
    $html .= "<td width='14'>&nbsp;</td>";
    
    if($_enc['sem_entrega']==0  || $lines[0]['egift']==1){

       if( ($_enc['tracking_tipopaga']==4 && $_enc['pagref']!="") || $_enc['tracking_tipopaga']!=4  ){
            $distrito = "";
            if($SETTINGS['show_distrito']!=0 && $_enc['entrega_distrito']!=""){
                $distrito = "<br /><span style='padding-bottom: 2px;'><b>".estr2(328).":</b> ".$_enc['distrito_cliente']."</span><br />";
            }
           $nif = ($_enc['nif_cliente'] === 0 || trim($_enc['nif_cliente'])=='') ? '-' : $_enc['nif_cliente'];
           
           $fact_morada1 = $_enc['morada1_cliente'];
           if($_enc['morada2_cliente']!="") $fact_morada1 .= "<br>".$_enc['morada2_cliente'];
           
          
           $html .= "<td width='304' height='183' style='border:1px solid #CCCCCC' valign='top'>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                          <tr>
                            <td style='background-color: #7E7E7E; color: #FFF; padding:0 12px; line-height:100%;' height='38'><b style='line-height:100%;'>".estr2(102).":</b></td>
                          </tr>
                        </table>
                        <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                          <tr>
                            <td style='padding:13px 16px; height: 65px;'>
                              <span style='padding-bottom: 2px;'><b>".estr2(40).":</b> ".$_enc['nome_cliente']."</span><br />  
                              <span style='padding-bottom: 2px;'><b>".estr2(248).":</b> ".$_enc['email_cliente']."</span><br />
                              <span style='padding-bottom: 2px;'><b>".estr2(103).":</b> ".$nif."</span><br />  
                              <span style='padding-bottom: 2px;'><b>".estr2(84).":</b> ".$fact_morada1."</span><br />
                              $_enc_cp_cliente
                              <span style='padding-bottom: 2px;'><b>".estr2(44).":</b> ".$_enc['cidade_cliente']."</span><br />
                              $distrito
                              <span style='padding-bottom: 2px;'><b>".estr2(45).":</b> ".$_enc['entrega_pais']."</span><br />                                                          
                            </td>
                          </tr>
                        </table>
                      </td>";

      }
    }
    
    
    $html .= "</tr></table>";

    $html .= "<br><br>";

    $html .= "<table border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr style='background-color: #7E7E7E;'>
                  <th height='36' align='left' style='padding:0 12px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;'>".estr2(106)."</b></p></th>
                  <th height='36' align='right' style='padding:0 12px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;'>".estr2(210)."</b></p></th>
                  <th height='36' align='center' style='padding:0 12px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;'>".estr2(108)."</b></p></th>
                  <th height='36' align='right' style='padding:0 12px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;'>".estr2(109)."</b></p></th>
                </tr>";

    foreach($lines as $k => $v){

        $preco = $v['valoruni'];
        
        if((int)$B2B>0){
            if($v['valoruni_sem_iva']>0) $preco = $v['valoruni_sem_iva'];    
        }
        
        $_qtds = $eComm->getOrderProdQtds($userID, $v['pid'], $v['order_id'], $v['oferta']);
        $label_preco = $_enc['moeda_prefixo'].number_format($preco,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        $preco_total = $_enc['moeda_prefixo'].number_format($_qtds*$preco,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
                
        if($v['oferta']==1){
          $label_preco = "&nbsp;";
          $preco_total = "<span class='label oferta'>".estr2(152)."</span>";
        }
        if($v["id_linha_orig"]<1){
        
            if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['sku_family'] = $v['ref'];
            
            $more_desc = " - ".$v['sku_family'];
        }
      
        if($v['ref']=='PORTES'){
            
            if((int)$EMAIL_CONFIRMATION_HIDE_PORTES==1) continue;
            
            $more_desc = '';
            $caracteristicas = '';
        }else{
        
            $cor = '';
            if(trim($v['cor_name'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_COR==0) $cor = "<b>".estr2(1)."</b> ".$v['cor_name']."<span style='padding-right:10px;'></span>";
            
            $tamanho = '';
            if(trim($v['tamanho'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_TAMANHO==0) $tamanho = "<b>".estr2(2)."</b> ".$v['tamanho']."<span style='padding-right:10px;'></span>";                        
            
            if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['composition'] = '';
                                                    
            $caracteristicas = "<p>".$cor.$tamanho." ".$v['composition']."</p>";
        }

        if($v['as_gift']>0 && trim($v['as_gift_desc'])!=''){
            // $caracteristicas .= '<p><i>'.$v['as_gift_desc'].'</i></p>';
        }
        
        $points = "";
        if($v['points']>0 && (int)$user["sem_registo"] == 0 ){
            $points = "<p>+ ".$v['points']*$_qtds." ".estr2(350)."</p>";
        }
        
        $imagem = '';
        
        if((int)$EMAIL_CONFIRMATION_SHOW_IMAGE>0){
            $imagem = "<td style='width:80px'>
                          <img src='".$v['image']."' style='width:80px;'>
                      </td>
                      <td style='width:10px'></td>";
        }

        $html .= "  <tr>
                      <td style='border-bottom:1px solid #CCCCCC; padding:8px 12px;' valign='middle' height='74'>
                        
                        <table style='width:100%'>
                            <tr>
                                ".$imagem."
                                <td>
                                    <p><b>".$v['nome']."</b>".$more_desc."</p>
                                    ".$caracteristicas."
                                </td>
                            </tr>
                        </table>
                        
                        
                      </td>
                      <td style='border-bottom:1px solid #CCCCCC; padding:8px 12px;' valign='middle' height='74' align='right'><p>".$label_preco."</p></td>
                      <td style='border-bottom:1px solid #CCCCCC; padding:8px 12px;' valign='middle' height='74' align='center'><p>".$_qtds."</p></td>
                      <td style='border-bottom:1px solid #CCCCCC; padding:8px 12px;' valign='middle' height='74' align='right'><p><b>".$preco_total."</b></p>".$points."</td>
                    </tr>";
    }

    $html .= "  <tr>
                  <td colspan='4' height='28'></td>
                </tr>";
                
                
    if((int)$B2B>0 && $_enc['iva_valor'] > 0){
    
        $enc_iva = cms_fetch_assoc(cms_query("SELECT SUM(valoruni_sem_iva) as valoruni_sem_iva FROM ec_encomendas_lines WHERE order_id='".$_enc['id']."' AND ref<>'PORTES' "));
    
    
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                        <tr>
                          <td width='50%'><b>".estr2(163).":</b></td>
                          <td width='18'></td>
                          <td align='right'><b>".$_enc['moeda_prefixo'].number_format($enc_iva['valoruni_sem_iva'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></td>
                        </tr>
                      </table>
                    </td>
                  </tr>";
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                        <tr>
                          <td width='50%'><b>".estr2(326).":</b></td>
                          <td width='18'></td>
                          <td align='right'><b>".$_enc['moeda_prefixo'].number_format($_enc['iva_valor'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></td>
                        </tr>
                      </table>
                    </td>
                  </tr>";
    }            

    $vouchers = $eComm->getOrderVouchers($_enc['id']);
    foreach($vouchers as $k => $v){

       $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <div style='overflow:hidden;'>
                        <p style='float:left;'><b>".estr2(14).":</b>&nbsp;&nbsp;<span style='display:inline-block; color: #FFF; background-color: #B4C57F; padding: 0px 10px;'><b>".$v['voucher_cod']."</b></span></p>
                        <p style='float:right;'><b>- ".$_enc['moeda_prefixo'].number_format($v['used_value'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></p>
                      </div>
                    </td>
                  </tr>";

    }    
    
    if($_enc['generatedPoints'] > 0 && (int)$user["sem_registo"] == 0 ){
        $txt_generatedPoints = $_enc['generatedPoints']." ".estr2(350);
        if((int)$SETTINGS_LOJA["pontos"]["campo_6"]>0){
            $txt_generatedPoints = $_enc['moeda_prefixo'].number_format($_enc['generatedPoints']*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
        }
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <div style='overflow:hidden;'>
                        <p style='float:left;'><b>".estr2(416).":</b></p>
                        <p style='float:right;'><b>".$txt_generatedPoints."</b></p>
                      </div>
                    </td>
                  </tr>";
    }
    
    $vales_html = "";
    $pontos_vales = 0;
    $vales = $eComm->getInvoiceChecks($_enc['id']);
    foreach($vales as $k => $v){

      $vales_html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <div style='overflow:hidden;'>
                        <p style='float:left;'><b>".estr2(75).":</b>&nbsp;&nbsp;<span style='display:inline-block; color: #FFF; background-color: #B4C57F; padding: 0px 10px;'><b>".$v['codigo']."</b></span></p>
                        <p style='float:right;'><b>- ".$_enc['moeda_prefixo'].number_format($v['valor_descontado'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></p>
                      </div>
                    </td>
                  </tr>";
      $arr = explode("_", $v["obs"]);
      $pontos_vales += $arr[2];
    }
    
    if($MARKET["usePoints"] == 1 && (int)$user["sem_registo"] == 0 ){
        $total_p_usados = $pontos_vales+$_enc["usedPoints"];
        if($total_p_usados>0 && (int)$SETTINGS_LOJA["pontos"]["campo_6"]==0){

            $html .= "<tr>
                        <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                          <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                            <tr>
                              <td width='50%'><b>".estr2(460).":</b></td>
                              <td width='18'></td>
                              <td align='right'><b>".$total_p_usados." ".estr2(350)."</b></td>
                            </tr>
                          </table>
                        </td>
                      </tr>";
        }
    
    }
    
    if($_enc['valor_credito'] > 0){
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                        <tr>
                          <td width='50%'><b>".estr2(467).":</b></td>
                          <td width='18'></td>
                          <td align='right'><b>".$_enc['moeda_prefixo'].number_format($_enc['valor_credito']-$_enc['desconto_credito'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></td>
                        </tr>
                      </table>
                    </td>
                  </tr>";
    }
    

    if($_enc['portes']>0){
      $html .= "<tr>
                  <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td width='50%'><b>".estr2(9).":</b></td>
                        <td width='18'></td>
                        <td align='right'><b>$portes</b></td>
                      </tr>
                    </table>
                  </td>
                </tr>";
    }else{
        #Adicionado a 01-08-2019 por intruções do Serafim
        $html .= "<tr>
                  <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                      <tr>
                        <td width='50%'><b>".estr2(9).":</b></td>
                        <td width='18'></td>
                        <td align='right'><b>".estr2(10)."</b></td>
                      </tr>
                    </table>
                  </td>
                </tr>";
    }
    
     
    if($_enc['imposto'] > 0){
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                        <tr>
                          <td width='50%'><b>".estr2(326).":</b></td>
                          <td width='18'></td>
                          <td align='right'><b>".$_enc['moeda_prefixo'].number_format($_enc['imposto'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></td>
                        </tr>
                      </table>
                    </td>
                  </tr>";
    }
    
    $html .= $vales_html;

    if($_enc['custo_pagamento'] > 0){
        $html .= "<tr>
                    <td colspan='4' style='padding:12px 12px; border-top:1px solid #CCCCCC;'>
                      <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                        <tr>
                          <td width='50%'><b>".estr2(487).":</b></td>
                          <td width='18'></td>
                          <td align='right'><b>".$_enc['moeda_prefixo'].number_format($_enc['custo_pagamento'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']."</b></td>
                        </tr>
                      </table>
                    </td>
                  </tr>";
    }

    $html  .= " <tr>
                  <td colspan='4' height='11' style='padding:12px 12px; border-top:1px solid #CCCCCC;'></td>
                </tr>
                <tr>
                  <td colspan='4' style='background-color: #F4F4F4; font-size:20px; padding:12px 12px;' align='right'><p><b>".estr2(11)." $total</b></p></td>
                </tr>
              </table>";
              
    $comentario = cms_fetch_assoc(cms_query("SELECT id,obs FROM ec_encomendas_log WHERE estado_novo='98' and autor='Observações' AND encomenda='$enc_id'  LIMIT 0,1"));              
    if($comentario['id'] > 0){
        $html .= "<p style='padding:12px 12px;'><b>".estr2(471).":</b> ".$comentario['obs']."</p>";
    }
    
    $po_number = cms_fetch_assoc(cms_query("SELECT id,obs FROM ec_encomendas_log WHERE estado_novo='98' and autor='PO Number' AND encomenda='$enc_id'  LIMIT 0,1"));              
    if($po_number['id'] > 0){
        $html .= "<p style='padding:12px 12px;'><b>".estr2(494).":</b> ".$po_number['obs']."</p>";
    }         
              
   
    $email['blocopt'] = nl2br($email['blocopt']); 

    #Variáveis antigas para compatibilizar - descontinuadas
    $email['blocopt'] = str_ireplace("{ORDERID}", $_enc['id'], $email['blocopt']);
    $email['nomept'] = str_ireplace("{ORDERID}", $_enc['id'], $email['nomept']);
    
    #Variaveis gerais 
    $email['blocopt'] = str_ireplace("{DETALHES}", $html, $email['blocopt']);
    
    $email['blocopt'] = __substituirVariavies($email['blocopt'], $_enc, $total);     
    $email['nomept']  = __substituirVariavies($email['nomept'], $_enc, $total);
    
              
    $_toemail = $_enc['email_cliente'];          
    if(trim($emailto)!='' && $emailto!==' ') $_toemail = $emailto;              

    $id_email = sendEmailTransacionalController($email['blocopt'], $email['nomept'], $_toemail, $userID, "Confirmaçao de Encomenda", 0, $_enc['id'], $email_template_id);
                               
            
    if(trim($MARKET['email_bcc'])!=''){
        $em = preg_split("/[;,]/",$MARKET['email_bcc']);
        foreach($em as $k => $v){
            sendEmailTransacionalController($email['blocopt'], $email['nomept'], $v, $userID, "Confirmaçao de Encomenda", 0, 0, $email_template_id);
        }
    }
    
    $arr = array();
    $arr['0'] = $id_email;

    return serialize($arr);
}



function __substituirVariavies($bloco, $_enc, $total){

    global $pagetitle;

    $bloco = str_ireplace("{CLIENT_NAME}", $_enc['nome_cliente'], $bloco);
    $bloco = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $bloco);
    $bloco = str_ireplace("{ORDER_ID}", $_enc['id'], $bloco);
    $bloco = str_ireplace("{ORDER_DATE}", $_enc['data'], $bloco);
    $bloco = str_ireplace("{PAGETITLE}", $pagetitle, $bloco);        
    $bloco = str_ireplace("{ORDER_TOTAL}", $total, $bloco);
    $bloco = str_ireplace("{ENTITY}", $_enc['ref_multi_enti'], $bloco);
    $bloco = str_ireplace("{REFERENCE}", $_enc['ref_multi'], $bloco);    

    return $bloco;
}


function __sendEmailConfirmationEncNew($_enc, $template, $LG, $email_only_products=0, $user=array()){

    global $eComm, $fx, $pagetitle, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE, $EMAIL_CONFIRMATION_HIDE_SKUFAMILY, $EMAIL_CONFIRMATION_HIDE_COR, $EMAIL_CONFIRMATION_HIDE_TAMANHO, $EMAIL_CONFIRMATION_HIDE_PORTES, $EMAIL_CONFIRMATION_SHOW_IMAGE, $B2B;
    global $EMAILS_LAYOUT_COR_BT, $EMAILS_LAYOUT_COR_LK, $EMAILS_LAYOUT_COR_BG_BT, $EMAILS_LAYOUT_COR_PORTES, $EMAILS_LAYOUT_FONT, $SETTINGS_LOJA, $HIDE_ZIPCODE, $CONFIG_TEMPLATES_PARAMS, $API_CONFIG_PREV_ENTREGA_PENDENTE;
    
    
    $ARRAY_MB = array(4,10,12,13,23,29,32,37,80,82,84,96,108);
    $ARRAY_ONLY_TB = array(6,39,53,120);
     
    
    $array_templates = array(); 
    
    if(is_dir(_ROOT.'/templates/emails')) $array_templates[] = _ROOT.'/templates/emails';
    
    $array_templates[] = _ROOT.'/plugins/emails';
       

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', $array_templates, false, _ROOT.'/temp_twig/');
   
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getSingleImage.php';
    
    require_once $_SERVER['DOCUMENT_ROOT']."/api/lib/shortener/shortener.php";

    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
    
 
    $_exp           = array();
    $_exp['table']  = "exp_emails";
    $_exp['prefix'] = "nome";
    $_exp['lang']   = $LG;
    


    $template['desc'.$LG] = __substituirVariavies($template['desc'.$LG], $_enc, 0); 
        
   
    $user = call_api_func("get_line_table","_tusers", "id='".$_enc['cliente_final']."'");
   
    $mcode = base64_encode('0|||'.$user['id'].'|||'.$user['email']);
   
    $url_encomenda = $slocation.'/account/index.php?id=17&order='.$_enc['id'].'&m2code='.$mcode;
    
    #Raffle
    if( (int)$_enc['sorteio_id'] > 0 ){
        $url_encomenda = $slocation.'/account/index.php?id=55&mcode='.$mcode;
    }
    #Raffle
    
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
    $y['EMAILS_LAYOUT_COR_PORTES']          = strlen($EMAILS_LAYOUT_COR_PORTES)>1  ? "#".$EMAILS_LAYOUT_COR_PORTES : "";
    $y['EMAILS_LAYOUT_FONT']                = strlen($EMAILS_LAYOUT_FONT)>1  ? $EMAILS_LAYOUT_FONT : '"Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif';
    
    
    $y['nome_site']                         = $pagetitle;
    $y['url_site']                          = $slocation;
    $y['url_encomenda']                     = $url_encomenda->short_url;  

    $y['template']['hide_order_ref'] = 0;
    if( (int)$_enc['sorteio_id'] > 0 ){
        $y['template']['hide_order_ref'] = 1;
    }

    $y['template']['titulo']                = nl2br(strip_tags($template['titulo'.$LG]));
    $y['template']['descricao']             = nl2br(strip_tags($template['desc'.$LG]));
  
    $y['encomenda']['order_ref']            = $_enc['order_ref'];   
    $y['encomenda']['parcial']              = $_enc['tracking_status']>40 ?  1 : 0;   

    # Observações
    $observacoes = cms_fetch_assoc( cms_query( "select obs from ec_encomendas_log where encomenda='".$_enc['id']."' AND estado_novo='98' AND autor='Observações' " ) );
    $y['encomenda']['observacoes']          = $observacoes['obs'];
   
    
    # Linhas da encomenda
    $lines = $eComm->getOrderLinesEnc($_enc['id'], 1);

    # Morada de entrega
    $y['encomenda']['entrega']['nome']      = strip_tags($_enc['entrega_nome']);
    $y['encomenda']['entrega']['telefone']  = ($_enc['tel_cliente']!="") ? $_enc['tel_cliente'] : "";
   
    if( (int)$_enc['pickup_loja_id']>0 && $_enc['metodo_shipping_type']!=4 ){
        $y['encomenda']['entrega']['tipo']      = 2;
        $y['encomenda']['entrega']['nome_l']    = strip_tags($_enc['pickup_loja_nome']);
        $y['encomenda']['entrega']['morada']    = strip_tags($_enc['pickup_loja_morada']);
        $y['encomenda']['entrega']['cp']        = strip_tags($_enc['pickup_loja_cp']);
        $y['encomenda']['entrega']['cidade']    = strip_tags($_enc['pickup_loja_cidade']);
        $y['encomenda']['entrega']['pais']      = $_enc['entrega_pais'];
        
        
    }else{
        $y['encomenda']['entrega']['tipo']      = 0;    
        if($lines[0]['egift']==0){
        
            $ent_morada = $_enc['entrega_morada1'];
            if($_enc['entrega_morada2']!="") $ent_morada .= " ".$_enc['entrega_morada2'];
            
            $distrito = "";
            if($SETTINGS['show_distrito']!=0 && $_enc['entrega_distrito']!="") $distrito = $_enc['entrega_distrito'];
    
            $y['encomenda']['entrega']['tipo']      = 1;    
            $y['encomenda']['entrega']['morada']    = strip_tags($ent_morada); 
            $y['encomenda']['entrega']['cp']        = strip_tags($_enc['entrega_cp']);
            $y['encomenda']['entrega']['cidade']    = strip_tags($_enc['entrega_cidade']);
            $y['encomenda']['entrega']['distrito']  = strip_tags($distrito);
            $y['encomenda']['entrega']['pais']      = $_enc['entrega_pais'];
            $y['encomenda']['entrega']['mais_lj']   = ($_enc['metodo_shipping_type']==4) ? $_enc['pickup_loja_nome'] : "";
        }            
    }
  
    # Morada de faturação
    $fact_morada = $_enc['morada1_cliente'];
    if($_enc['morada2_cliente']!="") $fact_morada .= " ".$_enc['morada2_cliente'];
    
    $distrito_fact = "";
    if($SETTINGS['show_distrito']!=0 && $_enc['distrito_cliente']!="") $distrito_fact = $_enc['distrito_cliente'];
    
    $y['encomenda']['fact']['nome']      = strip_tags($_enc['nome_cliente']);
    $y['encomenda']['fact']['email']     = strip_tags($_enc['email_cliente']);
    $y['encomenda']['fact']['morada']    = strip_tags($fact_morada);
    $y['encomenda']['fact']['cp']        = strip_tags($_enc['cp_cliente']);
    $y['encomenda']['fact']['cidade']    = strip_tags($_enc['cidade_cliente']);
    $y['encomenda']['fact']['distrito']  = strip_tags($distrito_fact);
    $y['encomenda']['fact']['pais']      = $_enc['entrega_pais']; 
    $y['encomenda']['fact']['nif']       = ($_enc['nif_cliente'] === 0 || trim($_enc['nif_cliente'])== '') ? '' : $_enc['nif_cliente'];
    

    if( (int)$B2B == 0 && (int)$HIDE_ZIPCODE == 1 ){
        $y['encomenda']['fact']['cp'] = '';
        $y['encomenda']['entrega']['cp'] = '';
    }
            
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

        if($v['ref']=='PORTES'){
            
            if((int)$EMAIL_CONFIRMATION_HIDE_PORTES==1) continue;

        }else{
        
            $more_desc_array = array();
            if(trim($v['cor_name'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_COR==0) $more_desc_array[] = estr2(1)." ".$v['cor_name'];     
            if(trim($v['tamanho'])!='' && (int)$EMAIL_CONFIRMATION_HIDE_TAMANHO==0) $more_desc_array[] = estr2(2)." ".$v['tamanho'];                        
            $more_desc = implode(' / ', $more_desc_array); 
            
            if((int)$EMAIL_CONFIRMATION_HIDE_SKUFAMILY>0) $v['composition'] = '';    
                                                    
        }

        
        $points = "";
        if($v['points']>0 && (int)$user["sem_registo"] == 0 ){
            $points = "+ ".$v['points']*$_qtds." ".estr2(350);
        }
        
              
        if( trim($v['ref']) != '' && $v['pack'] == 0 && $v['tipo_linha'] !=5 && $v['egift'] == 0 && $v['custom'] == 0 ){
            $imagem = _getSingleImage(60, 0, 3, $v['ref'], 1);
            $imagem = str_replace('../', $slocation.'/', $imagem);
        }else{
            $imagem = $v['image'];
        }
        
        $servico = 0;
        if($v['id_linha_orig']>0) $servico = 1;
        
        $y['encomenda']['produtos'][] = array( "imagem" => $imagem,
                                                "nome" => $v['nome'],
                                                "desc" => $more_desc,
                                                "caracteristicas" => $v['composition'],
                                                "preco" => $label_preco,
                                                "preco_riscado" => $label_preco_r, 
                                                "desconto" => $label_desconto,
                                                "promo" => $label_promo,
                                                "qtds" => $_qtds, 
                                                "pontos" => $points,
                                                "servico" => $servico );
        
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
    
    
    $expedition_info = '';
    if((int)$API_CONFIG_PREV_ENTREGA_PENDENTE==1 || !in_array($_enc['tracking_tipopaga'], $ARRAY_MB)){
        $expedition_info = printExpeditionInfoEmail($depositos, $_enc['metodo_shipping_id'], $_enc['b2c_pais'], $services, $delivery_days, $_enc['id']);
    }
    
    
    # Definições de envio
    $ship_encomenda = call_api_func("get_line_table","ec_shipping", "id='".$_enc['metodo_shipping_id']."'");
    
    $shipping_days_text = '';
    if(trim($_enc['shipping_days'])!=''){
        $shipping_days_text = '('.$_enc['shipping_days'].' '.estr2(97).')';
        
        if($expedition_info != ''){
            $shipping_days_text = '';
        }
    }
    
    if(in_array($_enc['tracking_tipopaga'], $ARRAY_MB)){
        $shipping_days_text = '';
    }
    

    if(trim($ship_encomenda['bloco'.$LG])!=''){
        $shipping_days_text = nl2br(strip_tags($ship_encomenda['bloco'.$LG]));
    }
    

    $y['encomenda']['entrega']['envio']['nome']         = ($ship_encomenda['id']>0) ? $ship_encomenda['nome'.$LG] : "";
    $y['encomenda']['entrega']['envio']['descricao']    = $shipping_days_text;  
    
    
    if($expedition_info != '' && (int)$_enc['sorteio_id'] == 0 ){
        $y['encomenda']['entrega']['envio']['expedition_info'] = $expedition_info;
    }
    
    
    if($descontos>0){
        $y['encomenda']['descontos_total'] = $_enc['moeda_prefixo'].number_format($descontos,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    }else{
        $y['encomenda']['descontos_total'] = 0;   
    }
    

    //$valor_cheques  = $eComm->getInvoiceChecksValue($_enc['id']);
   
    
    # Pagamentos efectuados     
    $y['encomenda']['pagamento']          = $_enc['metodo_pagamento'];
 
    $y['encomenda']['valores']['antes_portes'] = array();
    $y['encomenda']['valores']['depois_portes'] = array();
    

    $valor_credito_troca = 0;
    $valor_cheques = 0;
    $vales_array = array();
    $vales_array_desconto = array();
    $pontos_vales = 0;
    $vales = $eComm->getInvoiceChecks($_enc['id']);
    foreach($vales as $k => $v){
        
        if($v["vale_de_desconto"] == 0 && $v["tipo"] != 4) $valor_cheques += $v['valor_descontado'];

        $vales_array = array( "tipo" => $v["tipo"], "texto" => "[@25]", 
                              "valor" => "- ".$_enc['moeda_prefixo'].number_format($v['valor_descontado'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] ); 
                                          
        $arr = explode("_", $v["obs"]);
        if((int)$v["vale_de_desconto"] == 1 || $v["tipo"] == 4 ){
            
            $pontos_vales += $arr[2];            
            
            switch ($arr[0]) {
                case "pontosck":
                    $exp = "[@53]";
                    break;
                case "prime":
                    $exp = "[@55]";
                    break;
                default:
                    $exp = "[@25]";
                    break;
            }

            if($v["tipo"] == 4){
              $exp = "[@67]";
              $valor_credito_troca += $v['valor_descontado']; 
            }
            
            $vales_array_desconto[] = array( "tipo" => $v["tipo"], "texto" => $exp, 
                              "valor" => "- ".$_enc['moeda_prefixo'].number_format($v['valor_descontado'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] ); 
        
        }
        
        
        if((int)$v["credito_loja"] == 1 ){
            
            $pontos_vales += $arr[2];            
            
            $vales_array_desconto[] = array( "tipo" => $v["tipo"], "texto" => "[@54]", 
                              "valor" => "- ".$_enc['moeda_prefixo'].number_format($v['valor_descontado'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] ); 
        
        }
    }
    
    $y['encomenda']['vales_desconto'] = $vales_array_desconto;
    
    $y['encomenda']['pagamento_vale']     = 0;
    if($valor_cheques>0){
        $y['encomenda']['pagamento_vale'] = $_enc['moeda_prefixo'].number_format($valor_cheques,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    }

    

    if($_enc['valor_credito'] > 0){
        $y['encomenda']['valores']['antes_portes'][] = array( "texto" => "[@21]", 
                                        "valor" => $_enc['moeda_prefixo'].number_format($_enc['valor_credito']-$_enc['desconto_credito'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );                                    
    }

    if($_enc['imposto'] > 0){
        $y['encomenda']['valores']['depois_portes'][] = array( "texto" => "[@18]", 
                                        "valor" => $_enc['moeda_prefixo'].number_format($_enc['imposto'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );             
    }

    $total_pago_sem_format = $total_pago = $_enc['valor']-$valor_cheques-$valor_credito_troca; 
    
    $total      = $_enc['moeda_prefixo'].number_format($_enc['valor']-$valor_credito_troca, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
    
    $total_pago = $_enc['moeda_prefixo'].number_format($total_pago, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];
     
    $portes     = $_enc['moeda_prefixo'].number_format($_enc['portes'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];

    $subtotal   = $_enc['moeda_prefixo'].number_format($subtotal, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'];  
    
    $y['encomenda']['valores']['antes_portes'][] = array( "texto" => "[@6]", "valor" => $subtotal);    

    $y['encomenda']['total']                = $total;
    $y['encomenda']['total_pago']           = $total_pago;
    $y['encomenda']['total_pago_s_format']  = $total_pago_sem_format;
    $y['encomenda']['portes_valor']         = $_enc['portes'];
    $y['encomenda']['portes']               = $portes;
    
    
    # Os vales não entram nos subtotais - ficam como pagamento
    /*if(count($vales_array)>0){
        $y['encomenda']['valores']['depois_portes'][] = $vales_array;
    }*/

    if($_enc['custo_pagamento'] > 0){
        $y['encomenda']['valores']['depois_portes'][] = array( "texto" => "[@22]", "valor" => $_enc['moeda_prefixo'].number_format($_enc['custo_pagamento'],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );             
    }

    # Pontos atribuídos/adicionados com a encomenda
    if($_enc['generatedPoints'] > 0 && (int)$user["sem_registo"] == 0 ){
         if((int)$SETTINGS_LOJA["pontos"]["campo_6"]>0){
            $y['encomenda']['valores']['pontos'][] = array( "texto" => "[@19]" , "valor" => $_enc['moeda_prefixo'].number_format($_enc['generatedPoints']*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );
         }else{
            $y['encomenda']['valores']['pontos'][] = array( "texto" => "[@19]", "valor" => $_enc['generatedPoints']." ".estr2(350));
         }
          
    }
    
     # Pontos usados na encomenda
    if($MARKET["usePoints"] == 1 && (int)$user["sem_registo"] == 0 ){
        #$total_p_usados = $pontos_vales+$_enc["usedPoints"];
        $total_p_usados = $_enc["usedPoints"];
        if($total_p_usados>0){
            $y['encomenda']['valores']['pontos'][] = array( "texto" => "[@20]" , "valor" => $_enc['moeda_prefixo'].number_format($total_p_usados*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo'] );
        }
    }          

    
    # Perguntas
    $y['perguntas'] = array();
    if(trim($template['perguntas'])!=''){
        
        $perg_s = "SELECT nome$LG, desc$LG FROM _tfaqs_emails WHERE id IN (".$template['perguntas'].") ORDER BY ordem, nome$LG ASC";
        $perg_q = cms_query($perg_s);
        while($perg = cms_fetch_assoc($perg_q)){
            $y['perguntas'][] = array("pergunta" => $perg['nome'.$LG], "resposta" => nl2br($perg['desc'.$LG]));    
        }
    }

    
    # Dados para pagamento
    $resp = printPagamento($_enc, $y, $_exp);
     
    $y["topo_1"] = $resp["topo_1"]; #CC
    $y["topo_2"] = $resp["topo_2"]; #MB e TB
    
    $y["email_only_products"] = (int)$email_only_products;

    $y["sorteio"] = 0;
    if( (int)$_enc['sorteio_id'] > 0 ){
        $y["sorteio"] = 1;
    }

    $html = $fx->printTwigTemplate("confirmacao.htm", $y, true, $_exp);     

    return $html;

}



function printPagamento($_enc, $y, $_exp){

    global $LG, $fx;
    
    
    $resp = array("topo_1" => "", "topo_2" => "");
    
    #Raffle
    $y["sorteio"] = 0;
    if( (int)$_enc['sorteio_id'] > 0 ){
        $y["sorteio"] = 1;
    }
    #Raffle
                     
    if( $_enc['pagref']==""){
        
        if( in_array( $_enc['tracking_tipopaga'], array(4,10,12,13,21,23,29,32,37,80,82,84,96,108) ) ){

            $y['payment_id']    = $_enc['tracking_tipopaga'];

            $y['mb_exp_1']      = estr2(92);
            $y['mb_exp_2']      = estr2(93);
            $y['mb_exp_3']      = estr2(94);
            
            
            $y['ref_multi_enti'] = $_enc['ref_multi_enti'];
            $y['ref_multi'] = $_enc['ref_multi'];
            
            $pag = call_api_func("get_line_table","ec_pagamentos", "id='".$_enc['tracking_tipopaga']."'");
           
            if((int)$pag['notifi_cancel_wait_hrs']==0) $pag['notifi_cancel_wait_hrs'] = 2;
            
            $pag_data = date('d-m-Y H:i', strtotime('+'.$pag['notifi_cancel_wait_hrs'].' hours', strtotime(date("Y-m-d H:i"))) ); 
            
            $y['pagamento_dia'] = $pag_data;
            
            $resp["topo_2"] = $fx->printTwigTemplate("confirmacao_topo_mb.htm", $y, true, $_exp);
            
        } elseif( $_enc['tracking_tipopaga']==24){
             
            $resp["topo_2"] = "";
                  
        } elseif($_enc['tracking_tipopaga']==6 || $_enc['tracking_tipopaga']==120){
 
            $y['tb_exp_1'] = 'IBAN:';
            $y['tb_exp_2'] = 'SWIFT:';
            $y['tb_exp_3'] = estr2(138);
            
            $y['tb_iban'] = $_enc['iban'];
            $y['tb_swift'] = $_enc['swift'];
            
            $resp["topo_2"] = $fx->printTwigTemplate("confirmacao_topo_tb.htm", $y, true, $_exp);
        
        }
        
    }
    
    
    $resp["topo_1"] = $fx->printTwigTemplate("confirmacao_topo_cc.htm", $y, true, $_exp);
                  
    return $resp; 
}

?>
