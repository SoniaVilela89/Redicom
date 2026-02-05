<?
function _redefinePasswordB2B(){

    global $CONFIG_TEMPLATES_PARAMS, $B2B;
    global $CONFIG_OPTIONS, $API_CONFIG_PASS_SALT;
  
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }
    
    if (!isset($_POST['new_password']) || empty($_POST['new_password'])) return serialize(array("0"=>"-1"));
    if (!isset($_POST['new_password_repeat']) || empty($_POST['new_password_repeat'])) return serialize(array("0"=>"-1"));

    if($_POST['old_password'] != $_POST['new_password']){
        
        if( $_POST['new_password'] != $_POST['new_password_repeat'] ){
            return serialize(array("0"=>"-1"));
        }
        
        $id_user = $_SESSION['EC_USER']['id'];

        if( (int)$id_user <= 0 ){
            return serialize(array("0"=>"-1"));
        }
            
        global $ConfigRec;
        
        if( empty($ConfigRec) ){
            $ConfigRec = cms_fetch_assoc(cms_query("SELECT * FROM b2c_config_loja WHERE id='5' LIMIT 0,1"));
        }

        if( (int)$ConfigRec['campo_3'] <= 0 ){
            return serialize(array("0"=>"-2"));
        }
            
        $date_to_validate = $_SESSION['EC_USER']['last_pass_update'];
        if( $date_to_validate == '' || $date_to_validate == '0000-00-00 00:00:00' ){
            $date_to_validate = $_SESSION['EC_USER']['registed_at'];
        }
        
        $date_limit = date('Y-m-d H:i:s', strtotime("-".$ConfigRec['campo_3']." months"));
        
        if( $date_to_validate == '' || $date_to_validate >= $date_limit ){
            return serialize(array("0"=>"-2"));
        }


        if((int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==1 && (int)$_SESSION['EC_USER']['id_original']>0){
            $id_user = $_SESSION['EC_USER']['id_original'];
        }
            
        if((int)$_SESSION['EC_USER']['id_utilizador_restrito']>0){
            $id_user = $_SESSION['EC_USER']['id_utilizador_restrito'];
        }
        
        $tabela = "_tusers";
        if( (int)$_SESSION['EC_USER']["type"] > 0 && $B2B==1 ){
            $tabela = "_tusers_sales";
        }

        $sql_query  = cms_query("SELECT password,email FROM $tabela WHERE id='".$id_user."'");
        $sql        = cms_fetch_assoc($sql_query);
        
        
        
        if ($_POST['new_password'] == $sql['password'])
          return serialize(array("0"=>"-2"));
        
        if ($API_CONFIG_PASS_SALT=='' && crypt($_POST['new_password'], $sql['password']) == $sql['password'])
            return serialize(array("0"=>"-2"));
      
        if ($API_CONFIG_PASS_SALT!='' && hash_equals($sql['password'], crypt($_POST['new_password'], $API_CONFIG_PASS_SALT)))
            return serialize(array("0"=>"-2"));     
    
             
        require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
        if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
            $azure = new AzureAD();   
            $azure->updateADPassword($sql['email'], $_POST['new_password']);
        }
        
        $sql = "update $tabela set password='".crypt($_POST['new_password'], $API_CONFIG_PASS_SALT)."', `last_pass_update`=NOW() WHERE `id`='".$id_user."' ";
        cms_query($sql);
        
        $_SESSION['EC_USER']['last_pass_update'] = date("Y-m-d H:i:s");
        
        
        return serialize(array("0"=>"1"));

    }
 
    return serialize(array("0"=>"-2"));

}
?>
