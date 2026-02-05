<?

function _setPassword(){
  
    global $CONFIG_TEMPLATES_PARAMS, $B2B;
    global $CONFIG_OPTIONS, $API_CONFIG_PASS_SALT;
  
    foreach( $_POST as $k => $v ){
      $_POST[$k] = utf8_decode($v);
    }
    
    if (!isset($_POST['old_password']) || empty($_POST['old_password'])) return serialize(array("0"=>"0"));
    if (!isset($_POST['new_password']) || empty($_POST['new_password'])) return serialize(array("0"=>"0"));
    if (!isset($_POST['new_password_repeat']) || empty($_POST['new_password_repeat'])) return serialize(array("0"=>"0"));
    

    if( ($_POST['old_password']!=$_POST['new_password']) && ($_POST['new_password'] == $_POST['new_password_repeat'])){

        # verifica a sintaxe da password
        if (check_password($_POST['new_password']) == false) {
            return serialize(array("0"=>"0"));
        }
    
        $id_user = $_SESSION['EC_USER']['id']; 

        if((int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==1 && (int)$_SESSION['EC_USER']['id_original']>0){
            $id_user = $_SESSION['EC_USER']['id_original'];
        }
            
        if((int)$_SESSION['EC_USER']['id_utilizador_restrito']>0){                    
            $id_user = $_SESSION['EC_USER']['id_utilizador_restrito'];  
        }
        
        $tabela = "_tusers"; 
        if((int)$_SESSION['EC_USER']["type"]>0 && $B2B==1){
            $tabela = "_tusers_sales";
        }

        $sql_query  = cms_query("SELECT password,email FROM $tabela WHERE id='".$id_user."'");
        $sql        = cms_fetch_assoc($sql_query);  
        
        
        
        if ($API_CONFIG_PASS_SALT=='' && crypt($_POST['old_password'], $sql['password']) != $sql['password'] && $_POST['old_password']!=$sql['password']) 
          return serialize(array("0"=>"0"));
          
                         
        if ($API_CONFIG_PASS_SALT!='' && !hash_equals($sql['password'], crypt($_POST['old_password'], $API_CONFIG_PASS_SALT)) && $_POST['old_password']!=$sql['password'] )
            return serialize(array("0"=>"0"));     

        require_once($_SERVER['DOCUMENT_ROOT']."/plugins/azure/AzureAD.php");
        if(trim($CONFIG_TEMPLATES_PARAMS['azure']['Tenant'])!=''){
            $azure = new AzureAD();   
            $azure->updateADPassword($sql['email'], $_POST['new_password']); 
        }
        
        $sql = "update $tabela set password='".crypt($_POST['new_password'], $API_CONFIG_PASS_SALT)."' where `id`='".$id_user."' ";
        cms_query($sql);
        
        
        
        return serialize(array("0"=>"1"));

    }
 
    return serialize(array("0"=>"0"));
    
}
?>
