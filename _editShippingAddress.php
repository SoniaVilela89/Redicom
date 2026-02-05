<?

function _editShippingAddress(){

    global $eComm;
    global $LG;
    global $MARKET;
    global $COUNTRY;
    global $B2B;
    global $CONFIG_OPTIONS;
    global $CONFIG_TEMPLATES_PARAMS;

    $DADOS = $_POST;

    foreach( $DADOS as $k => $v ){
        $DADOS[$k] = str_replace("&#39;", "´", utf8_decode($v));
    }

    $userID = (int)$_SESSION['EC_USER']['id'];   
    
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userID = $_SESSION['EC_USER']['id_original'];
    }

    if( (int)$B2B==0 && (int)$DADOS['address_id'] > 0 ){    
        
        $maID   = (int)$DADOS['address_id'];
        
        $desc = $DADOS['address1'];  
        if(strlen($desc)>50) {      
            $desc = substr($DADOS['address1'], 0, 50)." (...)";
        }
    
        $estr55SQL  = cms_query("select nome$LG as nome from ec_exp where id='55' LIMIT 0,1");
        $estr55     = cms_fetch_assoc($estr55SQL);
        
        $moradaSQL  = cms_query("select * from ec_moradas WHERE id='$maID' LIMIT 0,1");
        $morada     = cms_fetch_assoc($moradaSQL);
        if( $estr55['nome']==$morada['descpt'] ) $desc = $estr55['nome'];
    
    
        cms_query("UPDATE ec_moradas SET descpt='".$desc."',
                                 nome='".$DADOS['name']."',
                                 morada1='".$DADOS['address1']."',
                                 morada2='".$DADOS['address2']."',
                                 cp='".$DADOS['zip']."',
                                 cidade='".$DADOS['city']."',
                                 distrito='".$DADOS['distrito']."',
                                 updated_at=NOW()
                              WHERE id='$maID'");
        
        require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
        if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
            $azure = new AzureAD();   
            $azure->updateAD($userID); 
        }
        
        
        # Obter a última morada para calcúlo por métodos limitados a cp 
        $shippingAddress = $eComm->getShippingAddress($userID);
            
        $_SESSION['EC_USER']['deposito_express']  = $eComm->getDepositoExpress(preg_replace("/[^0-9]/", "", $shippingAddress['cp']), $COUNTRY["id"], $MARKET);    
    
    }
    
    
    $_SESSION['EC_USER']['loja_pref_id'] = $DADOS['preferred_store'];
    
    cms_query("UPDATE _tusers SET loja_pref_id='".$DADOS['preferred_store']."' WHERE id='$userID'");
    
    if(isset($_COOKIE["USER_STORE"]) && (int)$DADOS['preferred_store']>0){
        createCookie("USER_STORE", base64_encode($DADOS['preferred_store']), "31536000");
    }
    
    if((int)$DADOS['preferred_store']==0){
        unset($_COOKIE["USER_STORE"]);
        removeCookie('USER_STORE', null, -1); 
    }
    
    return serialize(array("0"=>1));
}
?>
