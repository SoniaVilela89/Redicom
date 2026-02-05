<?
function _setAddressDeposit(){

    global $userID, $_DOMAIN, $eComm;

    $address_id  = (int)$_POST['address_id'];

    $arr_add = get_pop_up_addresses();
    $arr_addresses = $arr_add['popup_addresses'];

    $arr_address = array_filter($arr_addresses, function ($item) use ($address_id) {
        return $item["id"] == $address_id;
    });
    $arr_address = reset($arr_address);
      

    $arr_resp = array();
    
    if(!isset($arr_address)){

        $_SESSION['_MARKET'] = $eComm->marketInfo($_SESSION['_COUNTRY']['id']);
        setcookie('POP_ADD', null, -1, '/', $_DOMAIN, true, true);
        
        $arr_resp = array(
            "error" =>  "1",
        );
        return serialize($arr_resp);
    }


    if($_SESSION['_MARKET']['deposito'] != $arr_address['deposit_id']){
        
        $eComm->removeInsertedChecks($userID);
        $eComm->clearTempCampanhas($userID);
       
        @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE id_cliente='$userID' AND status='0' AND (deposito='".$_SESSION['_MARKET']['deposito']."' OR deposito != '".$arr_address['deposit_id']."')");
        unset($_SESSION['sys_qr_bsk']);
    }
    
    $_SESSION['_MARKET']['deposito'] = $arr_address['deposit_id'];
    setcookie('POP_ADD', $arr_address['id'], time()+ 31536000, "/", $_DOMAIN, true, true);


    $arr_resp = array(
        "error"             => "0",
        "popup_address"     => $arr_address,
        "popup_address_id"  => $address_id
    );
    
    return serialize($arr_resp);

}
?>
