<?
function _createShippingAddress(){

    global $eComm;
    global $LG;
    global $MARKET;
    global $COUNTRY;

      
    $DADOS = $_POST;
    foreach( $DADOS as $k => $v ){
        $DADOS[$k] = utf8_decode($v);
    }
    
    $userID = (int)$_SESSION['EC_USER']['id'];
    $maID = (int)$DADOS['morada_id'];

    if( strlen( trim($DADOS['address1']) ) < 5 || strlen( trim($DADOS['name']) ) < 3 ){
        return serialize(array("0"=>0));
    }

    $new_address = array();
    $new_address['id']      = $userID;
    $new_address['nome']    = $DADOS['name'];
    $new_address['morada1'] = $DADOS['address1'];
    $new_address['morada2'] = $DADOS['address2'];
    $new_address['cp']      = $DADOS['zip'];
    $new_address['cidade']  = $DADOS['city'];
    $new_address['pais']    =  $_SESSION['_COUNTRY']['id'];
    $new_address['distrito']= $DADOS['distrito'];
    $maID = $eComm->setShippingAddress($new_address);
    
    $_SESSION['EC_USER']['deposito_express']  = $eComm->getDepositoExpress(preg_replace("/[^0-9]/", "", $DADOS['zip']), $COUNTRY["id"], $MARKET);
    
    return serialize(array("0"=>1));
}
?>
