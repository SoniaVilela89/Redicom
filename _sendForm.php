<?

function _sendForm($careers_inquest=null){

    global $fx, $LG, $toemail, $B2B, $SENDER_EMAIL_CONTACTS, $Email_From, $Email_Reply_To, $pagetitle, $userID;


    foreach( $_POST as $k => $v ){
        if(is_array($v)){
            $_POST[$k] = safe_value(decode_string_api(implode(',', $v)));
        }else{
            $_POST[$k] = safe_value(decode_string_api($v));
        }

        if( stripos($_POST[$k], 'docs.google') !== false ||  stripos($_POST[$k], 'btc') !== false || stripos($_POST[$k], 'telegram') !== false || stripos($_POST[$k], 't.me') !== false  || stripos($_POST[$k], 'bit.ly') !== false || stripos($_POST[$k], '.page.link') !== false || stripos($_POST[$k], 'qq.com') !== false || stripos($_POST[$k], 'example.com') !== false ){
            ob_end_clean();
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
    }
    
    if($careers_inquest==2 && (!isset($_SESSION['MA_9008_ENC']) || !is_numeric($userID)) ){
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    } 

   
    if(!is_numeric($userID)){

        #Para evitar a submissão excessiva de formularios
        $key2 = md5(base64_encode($_SERVER['REMOTE_ADDR'] . "sendform2"));
        $x2 = 0 + @apc_fetch($key2);
        if ($x2>10)
        {
            $x2++;
            apc_store($key2, $x2, 31536000);  #1ano
            ob_end_clean();
            header('HTTP/1.1 403 Forbidden');
            exit;
        } else {
            $x2++;
            apc_store($key2, $x2, 3600); #1h
        }
    
    
        $key  = md5(base64_encode($_SERVER['REMOTE_ADDR'] . "sendform"));
        $x    = 0 + @apc_fetch($key);
        if ($x>3){
            $x++;
            apc_store($key, $x, 60*60*24); #1dia
            header('HTTP/1.1 403 Forbidden');
            exit;
        } else {
            $x++;
            apc_store($key, $x, 60*3); #3minutos
        }

    }else{
        
        $key = md5(base64_encode($_SERVER['REMOTE_ADDR']. $DADOS['form_id'] . "sendform_cli"));
        $x   = 0 + @apc_fetch($key);
        if ($x>10){
            $x++;
            apc_store($key, $x, 60*60*24*7); #7dia
            header('HTTP/1.1 403 Forbidden');
            exit;
        } else {
            $x++;
            apc_store($key, $x, 60*3); #3minutos
        }
            
    }


    $DADOS = $_POST;

    if(empty($_POST)){
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    }


    $q    = cms_query("SELECT * FROM _tforms WHERE id='".$DADOS['form_id']."' LIMIT 0,1 ");
    $form = cms_fetch_assoc($q);

    $assunto_email_interno = "";

    $val  = processInformation($fx, $DADOS, $arr_dados, $toemail, $assunto_email_interno);
    if($val==1)
    {
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    }



    # 2023-04-06
    # Grava submissões
    $data = array(
        'id_form' => $DADOS['form_id'],
        'nome_form' => getLineTable('_tforms', "id=". $DADOS['form_id'])['nomept'],
        'ip_cliente' => $_SERVER['REMOTE_ADDR'],
        'browser_cliente' => $_SERVER["HTTP_USER_AGENT"],
        'url' => $_SERVER['HTTP_REFERER'],
        'id_cliente' => $_SESSION['EC_USER']['id']
    );
      
    $id_submissao = insertLineTable('_tforms_submissoes', $data);
    
    if ($id_submissao > 0) {
        foreach ($arr_dados as $key => $value) {
            if ($value['valor'] != '') {
                $data = array(
                    'id_submissao' => $id_submissao,
                    'id_linha' => $key,
                    'nome' => $value['nome'],
                    'valor' => $value['valor']
                );
                insertLineTable('_tforms_submissoes_linhas', $data);
            }
        }
    }




    $arr_sku = array();

    # 2022-06-08
    # Acrescentado empty($DADOS["cms_field_23"]) por causa do formulário de Pedido de Informações - para não ir repetido
    if( $DADOS["product_id"] != "" && empty($DADOS["cms_field_23"]) ){
        $prod     = call_api_func("get_line_table","registos", "id='".$DADOS["product_id"]."'");
        $arr_sku  = array(
            "nome"      =>  estr(50).'. '.$prod["sku_family"],
            "valor"     => $prod["nome".$LG],
            "tipo"      => "text",
            "default"   => "",
        );
        array_push($arr_dados, $arr_sku);
    }

    if($_FILES) {
        $allowed    = array();
        $allowed[]  = 'png';
        $allowed[]  = 'jpg';
        $allowed[]  = 'jpeg';
        $allowed[]  = 'gif';
        $allowed[]  = 'pdf';
        $allowed[]  = 'zip';
        $allowed[]  = 'doc';
        $allowed[]  = 'docx';

        foreach ($_FILES as $key=>$value) {

            if($value['size']==0) continue;

            $ext = explode('.', strtolower($value['name']));
            $ext = array_pop($ext);


            if(!in_array($ext,$allowed)) {
                $arr    = array();
                $arr[]  = 0;
                return serialize($arr);
            }

            $mimetype = mime_content_type($value['tmp_name']);
            if($mimetype == "text/x-php" || stripos($mimetype,"php") !== false) {
                $arr    = array();
                $arr[]  = 0;
                return serialize($arr);
            }
        }
    }
    
    


    $html   = printHTML($arr_dados);
    $title  = "Contactos";
    if($careers_inquest==1) $title = "Carreiras";           
    elseif($careers_inquest==2) $title = "Inquérito de satisfação da compra";                 


    $assunto = $form['nome'.$LG];
    if(trim($assunto_email_interno)=="") $assunto_email_interno = $assunto;


    if((int)$B2B>0){
      $assunto .= ' - '.$_SESSION["EC_USER"]["email"];
    }

    if((int)$SENDER_EMAIL_CONTACTS>0){
        $temp_pagetitle        = $pagetitle;
        $pagetitle             = $toemail;

        $temp_Email_Reply_To    = $Email_Reply_To;
        $Email_Reply_To         = $toemail;

    }


    if(trim($assunto_email_interno)!=''){
        $send = sendEmail('<p style="font-family: Arial, \'Helvetica Neue\', Helvetica, sans-serif; font-size: 17px; color: #000; line-height:20px;padding-left: 7px;"><b>'.$form['nome'.$LG].'</b></p><br><br><div style="border-bottom: 1px solid lightgray;padding: 7px;margin: 0;text-align: left !important;">'.estr(5).'</div>'.$html, $assunto_email_interno, $form['email'], '', 0, '', 0);
    }


    if((int)$SENDER_EMAIL_CONTACTS>0){
        $pagetitle     = $temp_pagetitle;
        $Email_Reply_To = $temp_Email_Reply_To;
    }

    if ($form['sendmsg']>0) {

      	$sql     = "SELECT * FROM _tmisctext where id=$form[sendmsg]";
      	$result  = cms_query($sql, $connection) or die ("Erro no query");
      	$row     = cms_fetch_array($result);

        if(trim($row['descsys'.$LG])!='' && trim($toemail)!=''){
      	   $send    = sendEmail($row['desc'.$LG].'<br><br><div style="border-bottom: 1px solid lightgray;padding: 7px;margin: 0;text-align: left !important;">'.estr(5).'</div>'.$html, $row['descsys'.$LG], $toemail, "", $_SESSION["EC_USER"]["id"], $title, 0);
        }

    }


    if($careers_inquest==1){

        $ar=array();
        $ar['nome']           = $DADOS['cms_field_6'];
        $ar['rua']            = $DADOS['cms_field_7'];
        $ar['email']          = $DADOS['cms_field_8'];
        $ar['posicao_cand']   = $DADOS['cms_field_12'];
        $ar['motivacao_cand'] = $DADOS['cms_field_13'];
        $ar['porta']          = $DADOS['cms_field_10'];
        $ar['codpostal']      = $DADOS['cms_field_11'];
        $ar['contacto']       = $DADOS['cms_field_15'];
        $ar['habilitacoes']   = $DADOS['cms_field_16'];

        $campos = $DADOS;
        unset($campos['form_id']);
        unset($campos['csrf']);
        unset($campos['cms_field_6']);
        unset($campos['cms_field_7']);
        unset($campos['cms_field_8']);
        unset($campos['cms_field_12']);
        unset($campos['cms_field_13']);
        unset($campos['cms_field_10']);
        unset($campos['cms_field_11']);
        unset($campos['cms_field_15']);
        unset($campos['cms_field_16']);

        $camposExtra = array();

        foreach ($campos as $k=>$v) {

            $name = explode('cms_field_', $k);
            $q = cms_query("SELECT nomept AS nome FROM _tform_lines WHERE unid='".$name[1]."' LIMIT 0,1");
        	  $r = cms_fetch_assoc($q);

            $camposExtra[$r['nome']] = $v;

        }

        $sql = "INSERT INTO resp_ofertas SET
            nome='".$ar['nome']."',
            rua='".$ar['rua']."',
            email='".$ar['email']."',
            posicao_cand='".$ar['posicao_cand']."',
            motivacao_cand='".$ar['motivacao_cand']."',
            porta='".$ar['porta']."',
            codpostal='".$ar['codpostal']."',
            contacto='".$ar['contacto']."',
            habilitacoes='".$ar['habilitacoes']."',
            campos_extra='".cms_escape(json_encode(__encodeArrayToUTF8($camposExtra)))."',
            lg='".$LG."' ";

        $q        = cms_query($sql);
        $id_resp  = cms_insert_id($q);

        foreach($_FILES as $key => $value){
            $ext = explode(".",$value['name']);
            if($value['size'] > 0){
                $file_final = "../cvs/cv_".$id_resp.".".end($ext);
                unlink($file_final);
                copy($value['tmp_name'], $file_final);
            }
        }

    }
    
    
    # 2025-04-14
    # MA - Inquérito de satisfação da compra  
    if($careers_inquest==2){
        
        if ($id_submissao > 0) {
                    
            $campanha = verifyRecommendationCampaign(15);
            
            if( (int)$campanha['automation']!=15 ) {
                $arr    = array();
                $arr[]  = 0;
                return serialize($arr);   
            }   
             
             
            $sem_desconto = 0;
            $tipo = 1;
            if($campanha['ofer_tipo']==3 || $campanha['ofer_tipo']==8){
                if($campanha['ofer_valor']>0){
                    $valor = $campanha['ofer_valor'];
                }elseif($campanha['ofer_perc']>0){
                    $valor = floatval($campanha['ofer_perc']).'%';
                    $tipo = 2;
                }else {
                    $sem_desconto = 1;
                }
            }else{
                $sem_desconto = 1;
            }  
        
        
            if($sem_desconto==1){
                $arr    = array();
                $arr[]  = 0;
                return serialize($arr);  
            }  
            
  
            $str    = microtime(true) *  (rand(100000,999999) / 100000);
            $str    = str_replace(".", "", $str);
            $codigo = $campanha['prefixo'].substr($str,-4).strtoupper(substr(md5($str),0,4));
            
        
            $data_validade = date("Y-m-d", strtotime("+30 days"));
             
            $sql = "INSERT INTO ec_vauchers SET cod_promo='$codigo',
                                                valor='$valor',
                                                tipo='$tipo',
                                                valido_para='1',
                                                cod_cliente='".$userID."',
                                                data_limite='".$data_validade."',
                                                crit_prod_promo='".$campanha['crit_prod_promo']."',
                                                motivo_emissao='Cupão de inquérito de satisfação da compra',
                                                campanha_id='".$campanha['id']."',
                                                motivo_id='5', 
                                                moeda='".$campanha['moeda']."',
                                                catalogo_voucher='".$campanha['crit_catalogo']."',
                                                min_value='".$campanha['bask_min_valor']."',
                                                created_at='".date("Y-m-d H:i:s")."' ";
        
            cms_query($sql);
            
        
            cms_query("INSERT INTO `ec_campanhas_segmentos_inquerito` (`id_encomenda`, `id_campanha`, `date`, `id_cliente`, `id_submissao`, `codigo`) 
                      VALUES ('".$_SESSION['MA_9008_ENC']."', '9008', NOW(), '".$userID."', '".$id_submissao."', '".$codigo."')");
                 
                      
            cms_query("INSERT INTO `registos_status_listagem` (`mes`, `ano`, `nav_id`, `impressoes`, `tipo`) VALUES ('".date('m')."', '".date('Y')."', 9008, 1, '2') ON DUPLICATE KEY UPDATE impressoes=impressoes+1");
           
           
            cms_query("UPDATE _tforms_submissoes SET ma_encomenda='".$_SESSION['MA_9008_ENC']."', ma_codigo='".$codigo."' WHERE id='".$id_submissao."' LIMIT 1"); 
            
            unset($_SESSION['MA_9008_ENC']);
            
            
            include _ROOT."/plugins/ma/app.funcs.php";
            include _ROOT."/plugins/ma/processors/1.php";
            include _ROOT."/marketing_automation/functions.php";
            
            
            $sql_utilizadores = "SELECT * FROM _tusers WHERE id='".$userID."' LIMIT 0,1";
            
            $campanha = call_api_func('get_line_table', 'ec_campanhas', "id='9001'");
            $campanha['id'] = 9008;
                    
                     
            _ma_processa_clientes($sql_utilizadores, $campanha, 1, 9008);  # Processa a campanha para os clientes
            
                                
        }
    }

    # 2022-03-10
    # A pedido do Carlos - Playup
    if(is_callable('custom_controller_send_form')) {
        call_user_func('custom_controller_send_form', $DADOS);
    }
        
    $arr    = array();
    $arr[]  = 1;
    return serialize($arr);

}

?>
