<?

function _setAccountCookies(){

    global $_DOMAIN;
  
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }
    

    # Cookies de controlo
    if(!isset($_POST['cookie_1'])){
        $_SESSION['plg_cp_1'] = 0;
        setcookie('plg_cp_1', null, -1, '/', $_DOMAIN, true, true);
    }else{
        $_SESSION['plg_cp_1'] = 1; 
        $_COOKIE["plg_cp_1"] = 1;   
    }
    
    # Cookies de controlo
    if(!isset($_POST['cookie_2'])){
        $_SESSION['plg_cp_2'] = 0;
        setcookie('plg_cp_2', null, -1, '/', $_DOMAIN, true, true);
    }else{
        $_SESSION['plg_cp_2'] = 1;
        $_COOKIE["plg_cp_2"] = 1; 
    }
    
    
    
    $_SESSION['EC_USER']['cookie_funcionais']   = $_SESSION['plg_cp_1'];
    $_SESSION['EC_USER']['cookie_publicidade']  = $_SESSION['plg_cp_2'];
    
  
    $sql = "update _tusers set cookie_funcionais='".(int)$_SESSION['plg_cp_1']."', cookie_publicidade='".(int)$_SESSION['plg_cp_2']."' where id='".$_SESSION['EC_USER']['id']."' ";
    cms_query($sql);
    
    
    require_once("../plugins/privacy/Log.php");
    $log = new Log();   
    $log->update_cookie($_SESSION['EC_USER']['id']);
    
    return serialize(array("0"=>"1"));
     
}

?>
