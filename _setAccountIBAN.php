<?php

function _setAccountIBAN(){

    global $userID;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode(safe_value($v));
    }

    if( !isset($_POST['csrf']) || strlen($_POST['csrf'])<8 || $_SESSION['csrf'] != $_POST['csrf']){
               
        ob_end_clean();
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/plain");
        exit;
    }

    unset($_SESSION['csrf']);

    if (isset($_POST['save_iban']) && empty($_POST['iban'])) return serialize(array("0"=>"0"));  

    $iban = $_POST['iban'];
    $save_iban = (int)$_POST['save_iban'];
    $clean_iban = (int)$_POST['clean_iban'];

    if($save_iban == 1){
        $iban = str_replace(' ', '', $iban);
        $sql_update = sprintf("UPDATE _tusers SET iban='%s' WHERE id='%d'", $iban, $userID);
        cms_query($sql_update);

    }elseif($clean_iban == 1){

        $sql_update = sprintf("UPDATE _tusers SET iban='1' WHERE id='%d'", $userID);
        cms_query($sql_update);

        $sql_update = sprintf("UPDATE encomendas_estornos SET save_iban='0' WHERE cliente_id='%d' AND save_iban=1", $userID);
        cms_query($sql_update);
    }

    return serialize(array("0" => "1"));
}