<?

function _sendSMS($enc_id=0, $template_id, $codigo){

    global $ADDON_2004, $ADDON_2004_SENDER; // SMSs da Plataforma Base configurado bo store_settings.inc
    global $eComm;
    
    
    
    $CONFFILE = _ROOT."/custom/shared/addons_info.php";
    include($CONFFILE);
    
    
    if ($enc_id > 0){
       $enc_id = (int)$enc_id;
       $template_id = (int)$template_id;
       $codigo_loja = $codigo;
    }else{
       $enc_id = (int)params('enc_id');
       $template_id = (int)params('template_id');
       $codigo_loja = params('codigo');
    }
    
     
    if($enc_id<1 || $template_id<1){
        return;
    }
  

    $config = call_api_func("get_line_table","b2c_config_loja", "id='6'");
  
  
    # 2022-03-25
    # Alterado por instruções do Serafim - já não sabiamos onde a variavel $ADDON_2004 que está no ficheiro store_settigns é carregada
    #if (isset($ADDON_2004) && $ADDON_2004>=date("Y-m-d")){
    if ((int)$ADDON_2004_ACTIVE == 1 && $_SERVER['SERVER_NAME']!='www.tiffosi.com' && $_SERVER['SERVER_NAME']!='www.vilanova.com'){
        $config["campo_2"] = "system";
    }elseif($config["campo_1"]!=1){
        $arr = array();
        $arr['0'] = -99;
        return $arr;
    }  
    
   
    
    switch ($config["campo_2"]) {
        case "system":
            $resp = __sms_base($enc_id, $template_id, $codigo_loja);
            break;        
        case "egoi":
            $resp = __sms_egoi($enc_id, $template_id, $codigo_loja);
            break;
        case "splio":
            $resp = __sms_splio($enc_id, $template_id, $codigo_loja);
            break;     
        case 'custom':
            if(is_callable('sms_custom')) {
                $resp = call_user_func('sms_custom', $enc_id, $template_id, $codigo_loja);
            }
            break;  
        case 'vodafone':
            $resp = __sms_vodafone($enc_id, $template_id, $codigo_loja);
            break;  
        case 'nos':
            $resp = __sms_nos($enc_id, $template_id, $codigo_loja);
            break;                      
    }
  
  
    cms_query("INSERT INTO ec_encomendas_log SET autor='Processo automático API', encomenda='".$enc_id."', estado_novo='98', obs='Enviada notificação de SMS ID: ".$template_id."' ");        
  
            
    $arr = array();
    $arr['0'] = $resp;

    return serialize($arr);
}


function __sms_splio($enc_id, $template_id, $CodigoLoja){

    global $eComm;
    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_splio'");
          
    $Enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
    
    $LG             = $Enc['idioma_user'];
     
    $Country = call_api_func("get_line_table","ec_paises", "id='".$Enc['b2c_pais']."'");
      
    $Telemovel = '';
    
    if( strlen($Enc['tel_cliente'])==9 && $Enc['tel_cliente'][0]=='9'){
        $Telemovel = $Country['phone_prefix'].'-'.$Enc['tel_cliente'];
    }else if( strlen($Enc['tel_cliente'])==12 && $Enc['tel_cliente'][0]=='3'){
        $Telemovel = $Enc['tel_cliente'];
    }else{
        @cms_query("UPDATE ec_encomendas SET sms_notification='-2' WHERE id='".$Enc['id']."'");
        return 0;
    }
      
      
       
   
    $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
    
    if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
        return 0;
    }
    
    $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );        
   

    $ValorCheques   = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total          = $Enc['valor']-$ValorCheques;
    $Total          = $Total.$Enc['moeda_abreviatura'];
    
              
    $Mensagem = __replaceData($Enc, $Mensagem, $CodigoLoja);  

        
    $Data = array ( 'universe' => $Config[campo_3], 
                    'key' => $Config[campo_4], 
                    'messages' => [array( 
                          'recipient' => $Telemovel, 
                          'content'=> utf8_encode($Mensagem),
                          'unicode' => true, 
                          'long'=> true, 
                          'tag' => 'tag', 
                          'sender'=> $Config[campo_6])
                      ]);  
                      
    if($enc_id>0){
        @cms_query('INSERT INTO ec_sms_logs (autor, encomenda, obs, numero, template_id) VALUES ("Processo automático SMS", "'.$enc_id.'", "'.cms_escape(serialize($Data)).'", "'.$Telemovel.'", "'.$template_id.'")');
    }                    
    
    $qstring = json_encode($Data);             
    $service_url = "https://s3s.fr/api/forwardsms/2.0/";
  
    $curl = curl_init($service_url);

    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // just for the example.
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS,$qstring);
    curl_setopt($curl,CURLOPT_HTTPHEADER,array("Expect:"));
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5); 
    
    $curl_response = @curl_exec($curl);
    
    if (curl_error($curl)) {
        @curl_close($curl);  
        return 0;
    }
    
    curl_close($curl);    
 
    $response = json_decode($curl_response, true);
    
    if($response['code']=='400' or $response['code']=='200'){
        return 1;
    }   

    return 0;  #ERRO
} 



function __sms_nos($enc_id, $template_id, $CodigoLoja){

    global $eComm, $pagetitle, $SMS_REMETENTE;
    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_NOS'");
    
    
    if( trim($Config["campo_1"]) == "" || trim($Config["campo_2"]) == "" || trim($Config["campo_3"]) == "" || trim($Config["campo_4"]) == "" || trim($Config["campo_5"]) == "" ){
        return 0;
    }
          
    $Enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
    
    if($Enc['pm_marketplace_id']>0){
        return 0;
    }
              
    $LG = $Enc['idioma_user'];
        
        
    $Telemovel = "+" . $Enc['tel_cliente']; 


    $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
    
    if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
        return 0;
    }
    
    $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );        
   

    $ValorCheques   = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total          = $Enc['valor']-$ValorCheques;
    $Total          = $Total.$Enc['moeda_abreviatura'];
    
              
    $Mensagem = __replaceData($Enc, $Mensagem, $CodigoLoja);  


    if($enc_id>0){
        @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, numero, template_id, user_id, SMSCount, info_data) VALUES (1, "Processo automático SMS", "'.$enc_id.'", "'.cms_escape($Mensagem).'", "'.$Telemovel.'", "'.$template_id.'", "'.(int)$Enc['cliente_final'].'", 0, "'.$CodigoLoja.'")');
        $sms_id = cms_insert_id();
    }
    
    $xml = "<soapenv:Envelope xmlns:soapenv='http://schemas.xmlsoap.org/soap/envelope/' xmlns:out='http://www.outsystems.com'>
                <soapenv:Body>
                  <out:SendSMSSelId>
                  <out:TenantName>$Config[campo_5]</out:TenantName>
                  <out:strUsername>$Config[campo_1]</out:strUsername>
                  <out:strPassword>$Config[campo_2]</out:strPassword>
                  <out:strIdentifier>$Config[campo_4]</out:strIdentifier>
                  <out:bolInSender>1</out:bolInSender>
                  <out:MsisdnList>$Telemovel</out:MsisdnList>
                  <out:strMessage>".utf8_encode($Mensagem)."</out:strMessage>
                  </out:SendSMSSelId>
                </soapenv:Body>
            </soapenv:Envelope>";
    
    $curl       = curl_init($Config["campo_3"]);
    
    $options    = array(CURLOPT_VERBOSE => false,
                				CURLOPT_RETURNTRANSFER => true,
                				CURLOPT_POST => true,
                				CURLOPT_POSTFIELDS => $xml,
                				CURLOPT_HEADER => false,
                				CURLOPT_HTTPHEADER => array('Content-Type: text/xml; charset=utf-8'),
                				CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_CONNECTTIMEOUT => 5);
                        
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    
    if (curl_errno($curl)){
        $sql = "UPDATE ec_sms_logs SET SMSCount='-10' WHERE id='".$sms_id."' ";
        @cms_query($sql);
        return 0;
    }
    
    $p = xml_parser_create();
    xml_parse_into_struct($p, $response, $vals, $index);
    xml_parser_free($p);
    
    $response_code = $vals[$index[RETURNCODE][0]][value];
    
    if( trim($response_code) == "" || trim($response_code) != "0" ){
        $sql = "UPDATE ec_sms_logs SET SMSCount='-11' WHERE id='".$sms_id."' ";
        @cms_query($sql);
        return 0;  
    }       
  
        
    /*$wsdl_url       = $Config["campo_3"];

    $wsdl_options   = array(
        'cache_wsdl'            => WSDL_CACHE_NONE,
        'soap_version'          => SOAP_1_2,
        'trace'                 => false,
        'exceptions'            => true,
        'encoding'              => 'UTF-8',
        'location'              => $wsdl_url,
        'uri'                   => 'http://tempuri.org/',
        'connection_timeout'    => 5,
        'stream_context'        => stream_context_create(array('http' => array('timeout' => 10)))
    );
    
    
    $send_arr = array(
        "TenantName"    => $Config["campo_5"],
        "strUsername"   => $Config["campo_1"],
        "strPassword"   => $Config["campo_2"],
        "strIdentifier" => $Config["campo_4"],
        "bolInSender"   => true,
        "MsisdnList"    => $Telemovel,
        "strMessage"    => utf8_encode($Mensagem)
    );


    if($enc_id>0){
        @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, numero, template_id, user_id, SMSCount, info_data) VALUES (1, "Processo automático SMS", "'.$enc_id.'", "'.cms_escape($Mensagem).'", "'.$Telemovel.'", "'.$template_id.'", "'.(int)$Enc['cliente_final'].'", 0, "'.$CodigoLoja.'")');
        $sms_id = cms_insert_id();
    }
   

    try {
        $client = new SoapClient($wsdl_url, $wsdl_options);
    } catch (Exception $e) {
    
        if($sms_id>0){
            $sql = "UPDATE ec_sms_logs SET SMSCount='-1' WHERE id='".$sms_id."' ";
            @cms_query($sql);
        }
    
        return 0;
    }

    if($sms_id>0){
        $sql = "UPDATE ec_sms_logs SET SMSCount='-9' WHERE id='".$sms_id."' ";
        @cms_query($sql);
    }
    
        
    try {
        $response = $client->SendSMSSelId($send_arr);
    } catch (Exception $e) {
    
        if($sms_id>0){
            $sql = "UPDATE ec_sms_logs SET SMSCount='-2' WHERE id='".$sms_id."' ";
            @cms_query($sql);
        }
        return 0;
    }

    
    if($sms_id>0){
        $sql = "UPDATE ec_sms_logs SET SMSCount='-10' WHERE id='".$sms_id."' ";
        @cms_query($sql);
    }

    if( !isset($response->ReturnCode) || (int)$response->ReturnCode > 0 ){
        if($sms_id>0){
            $sql = "UPDATE ec_sms_logs SET SMSCount='-3' WHERE id='".$sms_id."' ";
            @cms_query($sql);
        } 
        return 0;
    }*/
    

    if($sms_id>0){
        $sql = "UPDATE ec_sms_logs SET SMSCount='1' WHERE id='".$sms_id."' ";
        @cms_query($sql);
    }
    
    
    return 1;
    
} 

  
  
function __sms_base($enc_id, $template_id, $CodigoLoja){

    require_once($_SERVER[DOCUMENT_ROOT]."/plugins/sms/sms_new.php");
    global $eComm, $pagetitle, $SMS_REMETENTE, $SMS_PAISES;
    
    $sms = new SMS();
    
           
    $Enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
                    
    if($Enc['pm_marketplace_id']>0){
        return 0;
    }
              
    $LG = $Enc['idioma_user'];
        
    $pais_id_prefix = $Enc['b2c_pais'];
    if((int)$Enc['country_code'] > 0){
        $pais_id_prefix = $Enc['country_code'];
    }

    $Country = call_api_func("get_line_table","ec_paises", "id='".$pais_id_prefix."'");   
    
    
        
    $paises_sms = explode(',', $SMS_PAISES);                
                                                                   
    if ($Country['country_code']!="PT" && !in_array($Enc['b2c_pais'], $paises_sms)){
        @cms_query("UPDATE ec_encomendas SET sms_notification='-2' WHERE id='".$Enc['id']."'");
			  return 0;
    }      
            
          
    $Telemovel = '';

    if ($Country['country_code']=="PT"){
        if( strlen($Enc['tel_cliente'])==9 && $Enc['tel_cliente'][0]=='9'){   #telemovel
            $Telemovel = $Country['phone_prefix'].$Enc['tel_cliente'];    
        }elseif( strlen($Enc['tel_cliente'])==12 && $Enc['tel_cliente'][0]=='3' && $Enc['tel_cliente'][3]=='9'){
            $Telemovel = $Enc['tel_cliente'];
        }else{
            @cms_query("UPDATE ec_encomendas SET sms_notification='-2' WHERE id='".$Enc['id']."'");
			       return 0;
        }     
    }else{
        $Telemovel = $Enc['tel_cliente']; # nos restantes paises supomos que o telefone está sempre direito    
    }
       

   
    $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
    
    if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
        return 0;
    }
    
    $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );        


    $ValorCheques   = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total          = $Enc['valor']-$ValorCheques;
    $Total          = $Total.$Enc['moeda_abreviatura'];
    
              
    $Mensagem = __replaceData($Enc, $Mensagem, $CodigoLoja);  


    $Grupo = "Pagamento_Multibanco";
    if($template_id==31) $Grupo = "Levantamento_encomenda_loja";
    

   
	  if (strlen($pagetitle)>10){
		 	$parts = explode(" ", $pagetitle);
		 	$pagetitle = $parts[0];
		}
    $pagetitle = substr(preg_replace("/[^A-Za-z0-9]/", '', $pagetitle), 0, 11);     
		
    if(trim($SMS_REMETENTE)!='') $pagetitle = $SMS_REMETENTE;   
    


    $sms->sender = $pagetitle;     
    $TOT = @$sms->sendMessage($Mensagem, $Telemovel, 1, false, $Enc['b2c_pais']);

    @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, user_id, SMSCount, numero, template_id, info_data) VALUES (1,"Redicom SMS", "'.(int)$enc_id.'", "'.cms_escape($Mensagem).'", "'.(int)$Enc['cliente_final'].'", "'.(int)$TOT.'", "'.$Telemovel.'", "'.$template_id.'", "'.$CodigoLoja.'")');
    
        
    return 1;    
} 



function __sms_egoi($enc_id, $template_id, $CodigoLoja){

    global $eComm;
    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_egoi'");
    
    if(trim($Config["campo_1"])=="" || trim($Config["campo_2"])==""){
        return 0;
    }
      
          
    $Enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
          
    $LG = $Enc['idioma_user'];
     
    $Country = call_api_func("get_line_table","ec_paises", "id='".$Enc['b2c_pais']."'");
      
    $Telemovel = '';
  
    if( strlen($Enc['tel_cliente'])==9 && $Enc['tel_cliente'][0]=='9'){
        $Telemovel = $Country['phone_prefix'].'-'.$Enc['tel_cliente'];
    }else if( strlen($Enc['tel_cliente'])==12 && $Enc['tel_cliente'][0]=='3'){
        $Telemovel = $Country['phone_prefix'].'-'.substr($Enc['tel_cliente'], 3, 9);
    }else{
        @cms_query("UPDATE ec_encomendas SET sms_notification='-2' WHERE id='".$Enc['id']."'");
        return 0;
    }
       
              
   
    $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
    
    if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
        return 0;
    }
    
    $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );        


    $ValorCheques   = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total          = $Enc['valor']-$ValorCheques;
    $Total          = $Total.$Enc['moeda_abreviatura'];
    
              
    $Mensagem = __replaceData($Enc, $Mensagem, $CodigoLoja);  


    $Grupo = "Pagamento_Multibanco";
    if($template_id==31) $Grupo = "Levantamento_encomenda_loja";
    
        
    $Data = array(
        'apikey'      => $Config["campo_1"],
        'group'       => $Grupo,
        'senderHash'  => $Config["campo_2"],
        'message'     => utf8_encode(strip_tags($Mensagem)),
        'options' => array(
            'gsm0338'   => true,
            'maxCount'  => 5
        ),
        'mobile' => $Telemovel
    );  
    
      
    
    if($enc_id>0){
        @cms_query('INSERT INTO ec_sms_logs (autor, encomenda, obs, numero, template_id) VALUES ("Processo automático SMS", "'.$enc_id.'", "'.cms_escape(serialize($Data)).'", "'.$Telemovel.'", "'.$template_id.'")');
    }
         
    
    $Options = array(
        'http' => array(
            'method'        => 'POST',
            'header'        => 'Content-type: application/json',
            'ignore_errors' => true,
            'content'       => json_encode($Data)
        )
    );
     
    $Url = 'https://www51.e-goi.com/api/public/sms/send';
    
    
    try {
        $response = file_get_contents($Url, false, stream_context_create($Options));
    }catch (Exception $e) {
        return 0;
    }
   
    return 1;
} 

function __sms_vodafone($enc_id, $template_id, $CodigoLoja){

    global $eComm, $pagetitle, $SMS_REMETENTE;
    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_vodafone'");
    
    if(trim($Config["campo_1"])=="" || trim($Config["campo_2"])==""){
        return 0;
    }
          
    $Enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
          
    $LG = $Enc['idioma_user'];
     
    $Country = call_api_func("get_line_table","ec_paises", "id='".$Enc['b2c_pais']."'");
      
    $Telemovel = '';
    
    if( strlen($Enc['tel_cliente'])==9 && $Enc['tel_cliente'][0]=='9'){
        $Telemovel = $Country['phone_prefix'].$Enc['tel_cliente'];
    }else if( strlen($Enc['tel_cliente'])==12 && $Enc['tel_cliente'][0]=='3'){
        $Telemovel = $Country['phone_prefix'].substr($Enc['tel_cliente'], 3, 9);
    }else{
        @cms_query("UPDATE ec_encomendas SET sms_notification='-2' WHERE id='".$Enc['id']."'");
        return 0;
    }     
   
    $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
    
    if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
        return 0;
    }
    
    $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) ); 
    
    $ValorCheques   = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total          = $Enc['valor']-$ValorCheques;
    $Total          = $Total.$Enc['moeda_abreviatura'];
              
    $Mensagem = __replaceData($Enc, $Mensagem, $CodigoLoja);  


    $Grupo = "Pagamento_Multibanco";
    if($template_id==31) $Grupo = "Levantamento_encomenda_loja";

    $wsdl_url = "https://smsws.vodafone.pt/SmsBroadcastWs/service.web?wsdl";
    
    try {
        $clientVD = new SoapClient($wsdl_url, array());
    } catch (Exception $e) {
        return 0;
    }
    
    
    $pagetitle = substr(preg_replace("/[^A-Za-z0-9]/", '', $pagetitle), 0, 10 );
    
    if(trim($SMS_REMETENTE)!='') $pagetitle = $SMS_REMETENTE;
    
    
    $arr = array(
                "authentication" => array(
                                        "msisdn"  =>  $Config["campo_1"],
                                        "password"  =>  $Config["campo_2"],
                                        ),
                "alphaOriginator" => $pagetitle,
                "destination"     => $Telemovel,
                "text"            => utf8_encode(strip_tags($Mensagem)),
                "schedule"        => array(
                                      "periodicity" => 0
                                    ),
                "messageName"     =>  ""
                );
                
    
    if($enc_id>0){
        @cms_query('INSERT INTO ec_sms_logs (autor, encomenda, obs, numero, template_id) VALUES ("Processo automático SMS", "'.$enc_id.'", "'.cms_escape(serialize($arr)).'", "'.$Telemovel.'", "'.$template_id.'")');
    }            
                
    try {
        $response = $clientVD->sendShortMessageFromAlphanumeric($arr);
        
    } catch (Exception $e) {
        return 0;
    }   
    return 1;
} 

         
function __replaceData($Enc, $Msg, $CodigoLoja){
  
    global $eComm, $pagetitle;
    
    $ValorCheques = $eComm->getInvoiceChecksValue($Enc['id']);
    $Total = $Enc['valor']-$ValorCheques.$Enc['moeda_abreviatura'];
    
    $nome = explode(" ",  $Enc['nome_cliente']);
    $nome = preg_replace("/&([a-z])[a-z]+;/i", "$1", htmlentities(trim($nome[0])));
  
    $Msg = str_ireplace("{PAGETITLE}", $pagetitle, $Msg);
    $Msg = str_replace('{ORDER_ID}', $Enc['id'], $Msg);
    $Msg = str_replace('{ORDER_REF}', $Enc['order_ref'], $Msg);
    $Msg = str_replace("{ORDER_TOTAL}", $Total, $Msg);
    $Msg = str_replace("{CLIENT_NAME}", $nome, $Msg);
    $Msg = str_replace('{CLIENT_EMAIL}', $Enc['email_cliente'], $Msg);
    $Msg = str_ireplace("{ENTITY}", $Enc['ref_multi_enti'], $Msg);
    $Msg = str_ireplace("{REFERENCE}", $Enc['ref_multi'], $Msg);
    
    $Msg = str_ireplace("{STORE_CODE}", $CodigoLoja, $Msg);  
    $Msg = str_ireplace("{STORE_NAME}", $Enc['pickup_loja_nome'], $Msg); 
    
    
    
    $q_codes = cms_query("SELECT shipping_tracking_number  
                            FROM ec_encomendas_lines 
                            WHERE order_id='".$Enc['id']."' AND status='72' AND recepcionada>0 AND qnt>0 
                            GROUP BY shipping_tracking_number");   
    
    $qtd_embalagens = cms_num_rows($q_codes);
    
    $Msg = str_replace('{NUMBER_OF_PACKAGES}', $qtd_embalagens, $Msg);

    
    $Texto = estr2(92).":".$Enc['ref_multi_enti']."\n".estr2(93).":".$Enc['ref_multi']."\n".estr2(60).":".$Total; 
    $Msg = str_ireplace("{DADOS_PAGAMENTO}", $Texto, $Msg);  
    
    return $Msg;

}

?>
