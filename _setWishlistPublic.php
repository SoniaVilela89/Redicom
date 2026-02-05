<?
function _setWishlistPublic(){

    global $userID;
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }
    
    if(!is_numeric($userID)){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    if(empty($_POST)){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    $_SESSION['EC_USER']['public_wishlist'] = $_POST["active"];
    
    $s = "UPDATE _tusers set public_wishlist='".$_POST["active"]."' WHERE id='%d'";
    $sql = sprintf($s, $userID);
    cms_query($sql);
    
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
}
?>