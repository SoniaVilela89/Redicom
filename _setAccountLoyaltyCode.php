<?

function _setAccountLoyaltyCode(){    
    
    global $sslocation;
          
    $num_cliente = $_SESSION['EC_USER']['id'];

    $num_hex = str_pad(dechex($num_cliente),6,"0",STR_PAD_LEFT);

    $code_email = hash('sha512', $_SESSION['EC_USER']['email']);

    $hash = substr($num_hex, 0, 3).substr($code_email, 0, 4).substr($num_hex, 3, 3);

    cms_query("UPDATE _tusers SET hash_ma='".$hash."' WHERE id='".$num_cliente."'");
    
    $_SESSION['EC_USER']['hash_ma'] = $hash;
      
    $LINK       = $sslocation."/?pf=".$hash;
    
    $_SERVIDOR  = $_SERVER["SERVER_NAME"];
     
    require_once(_ROOT."/api/lib/shortener/shortener.php");
    $short_url = short_url($LINK , $_SERVIDOR);

    $short_url = explode("rdc.la/", $short_url->short_url);
    
    $url_shared = $short_url[1];
    
    $resp = array();
    $resp['hash_ma'] = $url_shared;
        
    return serialize($resp);
    
}
?>
