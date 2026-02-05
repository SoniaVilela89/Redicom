<?

function _setAccountDelete(){
  
    global $CONFIG_TEMPLATES_PARAMS, $API_CONFIG_PASS_SALT;
  
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }
  
    $sql_query  = cms_query("SELECT password, fornecedor_identidade_token FROM _tusers WHERE id='".$_SESSION['EC_USER']['id']."' LIMIT 0,1");
    $sql        = cms_fetch_assoc($sql_query);  
     
     
     
    if(isset($_POST['password'])){ 
        if ($API_CONFIG_PASS_SALT=='' && crypt($_POST['password'], $sql['password']) != $sql['password'] ) 
          return serialize(array("0"=>"0"));          
                         
        if ($API_CONFIG_PASS_SALT!='' && !hash_equals($sql['password'], crypt($_POST['password'], $API_CONFIG_PASS_SALT)))
            return serialize(array("0"=>"0"));
    }    
        
            
 
    
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/privacy/Log.php");
    $log = new Log();   
    $log->delete_account($_SESSION['EC_USER']['id']);
    
    
    
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
    if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
        $azure = new AzureAD();
        $test = $azure->deleteAD($_SESSION['EC_USER']['id'], $_POST['password']);
    }
    
    
    
    # 2023-05-30
    # Chamar os webhooks da API 
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/webhooks_api/Webhooks.php");
    $web = new Webhooks();   
    $web->setAction('DELETE_CLIENT', array('id' => $_SESSION['EC_USER']['id']));
    
    

    
    $_SESSION['EC_USER']['cookie_publicidade'] = 0;
    $_SESSION['EC_USER']['cookie_funcionais'] = 0;

    unset($_SESSION['plg_cp']);
    unset($_SESSION['plg_cp_1']);
    unset($_SESSION['plg_cp_2']);


    return serialize(array("0"=>"1"));
   
}

?>
