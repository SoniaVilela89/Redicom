<?

function _setDeleteUserRestricted(){
  
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }

    $userID = (int)$_SESSION['EC_USER']['id'];
    
    # É necessário login para avançar
    if(!is_numeric($userID) || $userID < 1){
        return serialize(array("0"=>"0", "error"=>"1"));
    }
   
    $utilizador_q = cms_query("SELECT * FROM _tusers WHERE id='".$_POST['id']."' AND id_user='".$userID."' LIMIT 0,1");
    $utilizador_n = cms_num_rows($utilizador_q);
    
    if($utilizador_n<1){
        return serialize(array("0"=> "0"));
    }
    
   
    cms_query("DELETE FROM _tusers WHERE id='".$_POST['id']."'");

    return serialize(array("0"=>"1"));
   
}

?>
