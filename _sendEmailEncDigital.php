<?

function _sendEmailEncDigital($enc_id=null){
    
    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE;
    
    if ($enc_id > 0){
       $enc_id = (int)$enc_id;
    }else{
       $enc_id = (int)params('orderid');
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
    
    $_lg = strtolower($_enc['idioma_user']);
    if( trim($_lg)=="" ) $_lg = "pt";
    if( $_lg=="en" ) $_lg = "gb";
    if( $_lg=="es" ) $_lg = "sp";
    $LG = $_lg;
    
    $userID  = $_enc['cliente_final'];
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
    
    $_exp = array();
    $_exp['table'] = "exp";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;
    
    $arr_files = array();
    $lines = $eComm->getOrderLinesUngrupped($_enc['id'], 1);
    foreach($lines as $k => $v){
        
        if($v['egift']==1) continue;
        
        if($v["unidade_portes"]==0){
            $sql_file = "SELECT * FROM registos_stocks WHERE sku='".$v["ref"]."' AND iddeposito IN(".$v["deposito_cativado"].") AND produto_digital=1 LIMIT 0,1";
            $res_file = cms_query($sql_file);
            $row_file = cms_fetch_assoc($res_file);
            
            if((int)$row_file["id"]==0){
                $sql_file = "SELECT * FROM registos_stocks WHERE sku='".$v["ref"]."' AND iddeposito IN(".$v["deposito"].") AND produto_digital=1 LIMIT 0,1";
                $res_file = cms_query($sql_file);
                $row_file = cms_fetch_assoc($res_file);
            }

            if(file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/DOWNLOADS/".$row_file["id"].".pdf")){
               $arr_files[] = "images_prods_static/DOWNLOADS/".$row_file["id"].".pdf";
            }else{
                cms_query("INSERT INTO ec_encomendas_log SET autor='Processo automático API', encomenda='".$enc_id."', estado_novo='98', obs='Não foi possivel enviar ficheiro digital: ".$v["ref"]." - documento em falta.'");
            }
        }
    }
    
    if(count($arr_files)==0){
        $arr = array();
        $arr['0'] = 1;    
        return serialize($arr); 
    }
    
    $email = __getEmailBody(44, $_enc['entrega_pais_lg']);
        
    $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);
    $email['blocopt'] = str_ireplace('{ORDER_ID}', $_enc['id'], $email['blocopt']);      
    $email['blocopt'] = str_ireplace('{ORDER_REF}', $_enc['order_ref'], $email['blocopt']);                  
    $email['blocopt'] = str_ireplace("{ORDER_ID}", $_enc['id'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{ORDER_DATE}", $_enc['data'], $email['blocopt']);
    $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);
    $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);
    $email['blocopt'] = str_ireplace("{CLIENT_NAME}", $_enc['nome_cliente'], $email['blocopt']);
        
    $email['nomept'] = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $email['nomept']);        
    
    $files = implode(";", $arr_files);

    sendEmailFromController($email['blocopt'], $email['nomept'], $_enc['email_cliente'], $files, $userID, "Produtos digitais", 0, 44, '', $enc_id);
    
    cms_query("INSERT INTO ec_encomendas_log SET autor='Processo automático API', encomenda='".$enc_id."', estado_novo='98', obs='Enviado email de produtos digitais.'");
        
    
    $arr = array();
    $arr['0'] = 1;    
    return serialize($arr);
    
}

?>
