<?

function _viewPopup($popup_id){

    if( $popup_id == "" ){
        $popup_id = params('popup_id');
    }

    $popup_id_initial = $popup_id;

    $popup_id = (int)end( explode("P_", $popup_id) );
     
    $arr = array();
    $arr['0'] = 0;
                
    if( $popup_id < 1 ){
        return serialize($arr);
    }

    $popup = cms_fetch_assoc( cms_query("SELECT `id` FROM b2c_campanhas_crm WHERE id='".$popup_id."'") );
    if( (int)$popup['id'] < 1 ){
        return serialize($arr);
    }
    
    global $_DOMAIN;

    $_SESSION['pop_exibido'] = 1; #exibir só um popup por sessão



    setcookie("_see".$popup_id_initial, 1, time()+ 31536000, "/", $_DOMAIN, true, true);

    unset($_SESSION['popups'][$popup['id']]);

    cms_query("UPDATE b2c_campanhas_crm SET `count`=`count`+1 WHERE id='".$popup['id']."'");

    $arr['0'] = 1;
    return serialize($arr);

}

?>
