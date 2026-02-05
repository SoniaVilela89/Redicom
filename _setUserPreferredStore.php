<?

function _setUserPreferredStore(){

    $DADOS = $_POST;
    
    foreach( $DADOS as $k => $v ){
    
        if( is_array($v) ) continue;
            
        $DADOS[$k] = safe_value(utf8_decode($v));
        
    }  

    $userID = (int)$_SESSION['EC_USER']['id'];
    
    $storeID = (int)$DADOS['preferred_store'];
    
    if( $storeID > 0 ){
    
        if( (int)$userID ){
            
            $_SESSION['EC_USER']['loja_pref_id'] = $storeID;
    
            $sql = "UPDATE _tusers SET
                      loja_pref_id='".$storeID."'          
                    WHERE id='".$userID."'"; 
            cms_query($sql);
            
        }
        
        createCookie("USER_STORE", base64_encode($storeID), "31536000");
        
        return serialize(array("0"=>"1"));
    
    }
    
    return serialize(array("0"=> "0"));
    
}

?>