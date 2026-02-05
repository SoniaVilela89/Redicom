<?

function _sendSMSGeneral($template_id=0, $data){

    global $ADDON_2004, $ADDON_2004_SENDER; // SMSs da Plataforma Base configurado bo store_settings.inc


    $CONFFILE = _ROOT."/custom/shared/addons_info.php";
    include($CONFFILE);
    
    if ($template_id > 0 || $template_id == -1){
        $template_id = (int)$template_id;
    } else {
        $template_id = (int)params('template_id');
        $data = params('data');
    }

    $arr = array();
    $arr['0'] = 0;

    $data = base64_decode($data);
    $data = urldecode($data);
    $data = gzinflate($data);
    $data = gzinflate($data);
    $data = unserialize($data);

    if ($template_id < 1 && $template_id != -1) { 
        return $arr;
    }

    if($data["telemovel"]=='' || ($data["lg"]=='' && $template_id != -1)){
        return $arr;
    }

    if ($template_id == -1 && ( !isset($data['SMS_TEXTO']) || strlen($data['SMS_TEXTO']) < 10 || strlen($data['SMS_TEXTO']) > 300) ) { # template_id -1 com $data['SMS_TEXTO'] é para usar sem template de sms
        return $arr;
    }
    
    if ($template_id > 0 && isset($data['SMS_TEXTO']) ) { 
        return $arr;
    }

    $config = call_api_func("get_line_table","b2c_config_loja", "id='6'");
    
    
    
    if( strlen($data['phone_prefix'])>0 && stripos($data['telemovel'], '-')==false){
        $data['telemovel'] = $data['phone_prefix'].'-'.$data['telemovel'];
    } 
    
    
    # 2022-03-25
    # Alterado por instruções do Serafim - já não sabiamos onde a variavel $ADDON_2004 que está no ficheiro store_settings é carregada
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
            $resp = __sms_base($template_id, $data);
            break;
        case "egoi":
            $resp = __sms_egoi($template_id, $data);
            break;
        case "splio":
            $resp = __sms_splio($template_id, $data);
            break;
        case 'vodafone':
            $resp = __sms_vodafone($template_id, $data);
            break;
        case 'sendinblue':
            $resp = __sms_sendinblue($template_id, $data);
            break;                    
        case 'custom':
            if(is_callable('sms_custom_general')) {
                $resp = call_user_func('sms_custom_general', $template_id, $data);
            }
            break;    
        case 'nos':
            $resp = __sms_nos($template_id, $data);
            break;                                 
    }  
               
     
    $arr = array();
    $arr['0'] = $resp;

    return serialize($arr);

}



function __sms_nos($template_id, $Data){

    global $eComm, $pagetitle, $SMS_REMETENTE;
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_NOS'");

    if( trim($Config["campo_1"]) == "" || trim($Config["campo_2"]) == "" || trim($Config["campo_3"]) == "" || trim($Config["campo_4"]) == "" || trim($Config["campo_5"]) == "" ){
        return 0;
    }


    $Telemovel  = "+".str_replace('-', '', $Data["telemovel"]);

    if((int)$Data['int_country_id']=='0'){
       return 0;
    }

    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG = $Data['lg'];

        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
        
        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        }
        
        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);
    }
    

    $info_data = $Data['info_data'];
    
    @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, user_id, numero, template_id, SMSCount, info_data) VALUES (1, "Processo automático SMS", "'.(int)$Data['ORDER_ID'].'", "'.cms_escape($Mensagem).'", "'.(int)$Data['USER_ID'].'", "'.$Telemovel.'", "'.$template_id.'", 0, "'.$info_data.'")');
    $sms_id = cms_insert_id();
    
    
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
        'cache_wsdl'   => WSDL_CACHE_NONE,
        'soap_version' => SOAP_1_2,
        'trace'        => true,
        'exceptions'   => true,
        'encoding'     => 'UTF-8',
        'location'     => $wsdl_url,
        'uri'          => 'http://tempuri.org/'

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


    @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, user_id, numero, template_id, SMSCount) VALUES (1, "Processo automático SMS", "'.(int)$Data['ORDER_ID'].'", "'.cms_escape($Mensagem).'", "'.(int)$Data['USER_ID'].'", "'.$Telemovel.'", "'.$template_id.'", 0)');
    $sms_id = cms_insert_id();


    try {
        $client = new SoapClient($wsdl_url, $wsdl_options);
    } catch (Exception $e) {
        return 0;
    }

    try {
        $response = $client->SendSMSSelId($send_arr);
    } catch (Exception $e) {
        return 0;
    }


    if( !isset($response->ReturnCode) || (int)$response->ReturnCode > 0 ){
        return 0;
    }*/
    
    

    $sql = "UPDATE ec_sms_logs SET SMSCount='1' WHERE id='".$sms_id."' ";
    @cms_query($sql);
            
    return 1;
} 


function __sms_sendinblue($template_id, $Data){

    global $eComm, $pagetitle, $SMS_REMETENTE;
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_sendinblue'");


    if(trim($Config["campo_1"])==""){
        return 0;
    }

    $Telemovel  = $Data["telemovel"];    
    $Telemovel = str_replace('-', '', $Telemovel);
    

    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG = $Data['lg'];
        
        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
        
        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        } 
        
        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);
        
    }
    
    
    $pagetitle = substr(preg_replace("/[^A-Za-z0-9]/", '', $pagetitle), 0, 10 );
    
    if(trim($SMS_REMETENTE)!='') $pagetitle = $SMS_REMETENTE; 
    
        
    $info = array ( 'type'  => "transactional",
                    'sender' => $pagetitle,
                    'recipient' => $Telemovel, 
                    'content'  => utf8_encode($Mensagem));   
    
    $qstring = json_encode($info);             
  
        

    $curl = curl_init();
    
    $authorization = "api-key: ".$Config["campo_1"]; // **Prepare Autorisation Token**
      
       
     
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.sendinblue.com/v3/transactionalSMS/sms",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "UTF-8",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/json' , $authorization ),
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $qstring
    ));
      
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);

  
      


    $response = json_decode($curl_response, true);
    
    if ($err) {
        return 0;
    } 
    
    return 1;
} 


function __sms_splio($template_id, $Data){

    global $eComm;
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_splio'");

    if(trim($Config["campo_1"])=="" || trim($Config["campo_2"])==""){
        return 0;
    }

    $Telemovel  = $Data["telemovel"];

    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG = $Data['lg'];

        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
        
        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        }
        
        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);

    }

        
    $info = array ( 'universe'  => $Config['campo_3'], 
                    'key'       => $Config['campo_4'], 
                    'messages'  => [array( 
                                      'recipient'     => $Telemovel, 
                                      'content'       => utf8_encode($Mensagem),
                                      'unicode'       => true, 
                                      'long'          => true, 
                                      'tag'           => 'tag', 
                                      'sender'        => $Config['campo_6'])
                                   ]);   
    
    $qstring = json_encode($info);             
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

    return 0;
} 


function __sms_base($template_id, $Data){

    global $eComm, $pagetitle, $SMS_REMETENTE, $SMS_PAISES;
             
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/sms/sms_new.php");
    
    $sms = new SMS();

    $Telemovel  = str_replace('-', "", $Data["telemovel"]);   

    $paises_sms = explode(',', $SMS_PAISES);
    
    if((int)$Data['int_country_id']=='0'){
            return 0;
    }                

    if($Data['int_country_id']!='176' && $Data['int_country_id']!='247' && $Data['int_country_id']!='253' && !in_array($Data['int_country_id'], $paises_sms)){
            return 0;
    }


    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG         = $Data['lg'];       

        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");
        
        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        } 
        
        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);

    }

 
    if (strlen($pagetitle)>10){
        $parts = explode(" ", $pagetitle);
        $pagetitle = $parts[0];
    }
    $pagetitle = substr(preg_replace("/[^A-Za-z0-9]/", '', $pagetitle), 0, 11);   
    
    if(trim($SMS_REMETENTE)!='') $pagetitle = $SMS_REMETENTE; 

    $sms->sender = $pagetitle;
    $TOT =  $sms->sendMessage($Mensagem, $Telemovel, 1, false, $Data['int_country_id']);

    $info_data = $Data['info_data'];

    @cms_query('INSERT INTO ec_sms_logs (tipo, autor, encomenda, obs, user_id, SMSCount, numero, template_id, info_data) VALUES (1,"Redicom SMS", "'.(int)$Data['ORDER_ID'].'", "'.cms_escape($Mensagem).'", "'.(int)$Data['USER_ID'].'", "'.(int)$TOT.'", "'.$Telemovel.'", "'.$template_id.'", "'.$info_data.'")');
     
    return $TOT;
} 


function __sms_egoi($template_id, $Data){

    global $eComm;
    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_egoi'");
    
    if(trim($Config["campo_1"])=="" || trim($Config["campo_2"])==""){
        return 0;
    }


    $Telemovel  = $Data["telemovel"];   

    if( strlen($Telemovel)==12 && $Telemovel[0]=='3'){
        $Telemovel = substr($Telemovel, 0, 3).'-'.substr($Telemovel, 3, 9);
    }


    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG = $Data['lg'];

        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");

        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        }

        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);

    }


    $Data = array(
        'apikey'      => $Config["campo_1"],
        'group'       => '',
        'senderHash'  => $Config["campo_2"],
        'message'     => utf8_encode(strip_tags($Mensagem)),
        'options'     => array(
                            'gsm0338'   => true,
                            'maxCount'  => 5
                         ),
        'mobile' => $Telemovel
    );  

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


function __sms_vodafone($template_id, $Data){

    global $eComm, $pagetitle, $SMS_REMETENTE;    
    
    $Config = call_api_func("get_line_table","b2c_config_loja", "tipo='sms_MB_vodafone'");
    
    if(trim($Config["campo_1"])=="" || trim($Config["campo_2"])==""){
        return 0;
    }

    $Telemovel  = $Data["telemovel"];

    if(isset($Data['SMS_TEXTO'])) {

        $Mensagem = trim($Data['SMS_TEXTO']);

    } else {

        $LG = $Data['lg'];

        $Email = call_api_func("get_line_table","ec_email_templates", "id='$template_id'");

        if (trim($Email['bloco'.$LG])=='' || (int)$Email['ativo'] < 1) {
            return 0;
        }

        $Mensagem = strip_tags( str_replace('&nbsp;', ' ',  str_replace('<br />', "\n", $Email['bloco'.$LG])) );
        $Mensagem = __replaceData($Data, $Mensagem);

    }


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
    try {
        $response = $clientVD->sendShortMessageFromAlphanumeric($arr);
        
    } catch (Exception $e) {
        return 0;
    }
    return 1;
} 
      
      
         
function __replaceData($Data, $Msg){
  
    global $pagetitle, $sslocation;      
  
    $nome = explode(" ",  $Data['CLIENT_NAME']);
    $Data['CLIENT_NAME'] = preg_replace("/&([a-z])[a-z]+;/i", "$1", htmlentities(trim($nome[0])));
    $Data['FILE_LINK']   = $Data['file_url'];


    # 2025-11-06
    # Tiffosi
    # Usado no sms de devolução de rede pickup
    if($Data['RETURN_ID']>0 && $Data['QR_CODE_LINK']>0){
    
        require_once(_ROOT."/api/lib/shortener/shortener.php");
                                                        
        $LINK       = $sslocation."/?id=12&cod=".base64_encode($Data['RETURN_ID']."|||".$Data['USER_ID']);
         
        $short_url = short_url($LINK, $_SERVER["SERVER_NAME"]);
      
        $url_shared = $short_url->short_url;
        
        $Msg = str_ireplace("{QR_CODE_LINK}", $url_shared, $Msg);
        
    }
    
    
    foreach($Data as $k => $v){                
        $Msg = str_ireplace("{".$k."}", $v, $Msg);
        $Msg = str_ireplace("{".strtoupper($k)."}", $v, $Msg);
    }
    
    $Msg = str_ireplace("{PAGETITLE}", $pagetitle, $Msg);
    
    return $Msg;

}

?>
