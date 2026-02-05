<?

function _editBillingAddress(){
    
    global $COUNTRY, $eComm, $CONFIG_TEMPLATES_PARAMS, $B2B, $MARKET, $SETTINGS;
    
     
    $DADOS = $_POST;
    
    foreach( $DADOS as $k => $v ){
        $DADOS[$k] = str_replace("&#39;", "´", utf8_decode($v));
    }

    $userID = (int)$_SESSION['EC_USER']['id'];
    
    
    $s_user = "SELECT * FROM _tusers WHERE id='".$userID."' LIMIT 0,1";
    $q_user = cms_query($s_user);
    $r_user = cms_fetch_assoc($q_user);
    
    
    
    
    # 2020-1-28 - posto à parte para ser compativél caso a coluna não exista    
    cms_query("UPDATE _tusers SET last_update=NOW() WHERE id='$userID'");
    
    
    
    $prefix = $COUNTRY['phone_prefix'];
    if((int)$DADOS["prefix"] > 0){
        $country_prefix    = call_api_func("get_line_table","ec_paises", "id='".$DADOS["prefix"]."'");
        if($country_prefix["id"] > 0) $prefix = $country_prefix["phone_prefix"];
    }
    
    if($B2B==1){
        
        $sql = "UPDATE _tusers SET
            b2b_contacto='".$DADOS['b2b_contacto']."',
            b2b_telefone='".$DADOS['b2b_telefone']."',            
            b2b_email='".$DADOS['b2b_email']."'            
          WHERE id='$userID'";
         
        cms_query($sql);
        
        return serialize(array("0"=>1));
    }
    
    
    if((int)$SETTINGS['validar_telefone'] > 0 && trim($DADOS['phone']) != ""){

        $phone = $prefix.$DADOS['phone'];

        $sql_verify_phone = "SELECT id FROM _tusers WHERE telefone='%s' AND sem_registo=0 AND id!='".$userID."' LIMIT 0,1";
        $sql_verify_phone = sprintf($sql_verify_phone, $phone);
        $res_verify_phone = cms_query($sql_verify_phone);
        $num_verify_phone = cms_num_rows($res_verify_phone);
        if((int)$num_verify_phone > 0){
            return serialize(array("0" => 99));
        }
        
        
        
        # 2024-04-18
        # A ser usada na Salsa para validar o telefone também no ERP
        if(is_callable('custom_validatePhone')){
        
            #Só validamos lá fora se o telefone novo for diferente do atual
            if($prefix.$DADOS['phone'] != $_SESSION['EC_USER']['telefone']){
        
                $json = array();
                $json['erro'] = 0;
                call_user_func_array('custom_validatePhone', array(&$json), $prefix, $DADOS['phone']);
                
                if($json['erro']==1){
                    return serialize(array("0" => 99));                                
                }             
            }
        }
                                                               
    }

    $sql_nif = "";
    if( isset($DADOS['nif']) ) $sql_nif = "nif='".$DADOS['nif']."',";
    
    $sql_data_nasc = "";
    if(trim($DADOS['birthdate'])!="") $sql_data_nasc = "datan='".$DADOS['birthdate']."',";
    
    $sql_sexo = "";
    if(trim($DADOS['gender'])!="") $sql_sexo = "sexo='".$DADOS['gender']."',";
    
    $sql_telf = "";
    if( trim($DADOS['phone']) != "" || (int)$SETTINGS['telfSN'] == 1 ){
        $temp_telf = "";
        if( trim($DADOS['phone']) != "" ) $temp_telf = $prefix.$DADOS['phone'];
        $sql_telf = "telefone='".$temp_telf."',";
        if((int)$country_prefix["id"] > 0){
            $sql_telf .= "pais_indicativo_tel='".$country_prefix["id"]."',";
        }
    }
    
    $sql_nome_contacto = "";
    if(trim($DADOS['name_contact'])!="") $sql_nome_contacto = "b2b_contacto='".$DADOS['name_contact']."',";
    
    $sql_cae = "";
    if( isset($DADOS['cae']) ) $sql_cae = "cae='".$DADOS['cae']."',";
    
    $sql = "UPDATE _tusers SET
            nome='".$DADOS['name']."',
            cp='".$DADOS['zip']."',
            cidade='".$DADOS['city']."',
            morada1='".$DADOS['address1']."',
            morada2='".$DADOS['address2']."',
            ".$sql_nif."
            ".$sql_data_nasc."
            ".$sql_sexo."
            ".$sql_telf."
            ".$sql_nome_contacto."
            ".$sql_cae."
            distrito='".$DADOS['distrito']."'
          WHERE id='$userID'";

    cms_query($sql);
    

    $_SESSION['EC_USER']['nome']      = $DADOS['name'];
    $_SESSION['EC_USER']['cp']        = $DADOS['zip'];
    $_SESSION['EC_USER']['cidade']    = $DADOS['city'];
    $_SESSION['EC_USER']['morada1']   = $DADOS['address1'];
    $_SESSION['EC_USER']['morada2']   = $DADOS['address2'];
    if(trim($DADOS['birthdate'])!="") $_SESSION['EC_USER']['datan']     = $DADOS['birthdate'];
    if(trim($DADOS['gender'])!="")    $_SESSION['EC_USER']['sexo']      = $DADOS['gender'];
    if( isset($DADOS['nif']) )        $_SESSION['EC_USER']['nif']       = $DADOS['nif'];
    if( trim($DADOS['phone']) != "" || (int)$SETTINGS['telfSN'] == 1 ) $_SESSION['EC_USER']['telefone'] = $temp_telf;
    $_SESSION['EC_USER']['cp_promo']  = preg_replace("/[^0-9]/", "", $DADOS['zip']);
    $_SESSION['EC_USER']['distrito']  = $DADOS['distrito'];
    $_SESSION['EC_USER']['pais_indicativo_tel'] = (int)$country_prefix["id"];
    $_SESSION['EC_USER']['cae']     = $DADOS['cae'];
    
    $sql = "UPDATE ec_moradas SET
            nome='".$DADOS['name']."',
            cp='".$DADOS['zip']."',
            cidade='".$DADOS['city']."',
            morada1='".$DADOS['address1']."',
            morada2='".$DADOS['address2']."',
            distrito='".$DADOS['distrito']."'
          WHERE id_user='$userID' AND descpt='".estr2(55)."' ";

    cms_query($sql); 
    
    
    # Obter a última morada para calcúlo por métodos limitados a cp 
    $shippingAddress = $eComm->getShippingAddress($userID);
        
    $_SESSION['EC_USER']['deposito_express']  = $eComm->getDepositoExpress(preg_replace("/[^0-9]/", "", $shippingAddress['cp']), $COUNTRY["id"], $MARKET);    
        
    
    # 2025-04-08
    # Chamar os webhooks da API 
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/webhooks_api/Webhooks.php");
    $web = new Webhooks();   
    $web->setAction('EDIT_CLIENT', array('id' => $userID, 'user_before_edit' => $r_user));
    
    
    
    if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
        require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
        $azure = new AzureAD();   
        $azure->updateAD($userID, ""); 
    }
    

    if(is_callable('custom_controller_edit_billing')) {
        call_user_func('custom_controller_edit_billing', $userID, $_POST);
    }
    
    
    return serialize(array("0"=>1));
}

?>
