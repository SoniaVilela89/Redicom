<?
function _setStockAlert(){

    global $LG, $COUNTRY, $session_id, $userID;
    
    
    if(!is_numeric($userID) && !isset($_POST['email'])){
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    } 
     
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }

   
    #Para evitar a submissão excessiva de formularios
    $key2 = md5(base64_encode($_SERVER['REMOTE_ADDR'] . "stock"));
    $x2 = 0 + @apc_fetch($key2);
    if ($x2>10 && !is_numeric($userID))
    {
        $x2++;
        apc_store($key2, $x2, 2592000);  #1mes
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x2++;
        apc_store($key2, $x2, 60*5); #5minutos
    }
    
    
    
    $email_form = $_SESSION['EC_USER']['email'];
    $cliente_id = $_SESSION['EC_USER']['id'];
    
    
    if(isset($_POST['email']) && $_POST['email']!=''){
        $email_form = $_POST['email'];
        if (!filter_var($email_form, FILTER_VALIDATE_EMAIL)) {
            ob_end_clean();
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
        if(!is_numeric($cliente_id)) $cliente_id = 0;
    }
    
    
               

    $s      = "SELECT id FROM avisos_stock WHERE email='".$email_form."' and pid='".$_POST['pid']."' AND estado=0 LIMIT 0,1";
    $q      = cms_query($s);
    $existe = cms_fetch_assoc($q);

    if( $existe['id']>0 ){
        $sql = "UPDATE avisos_stock SET data=NOW() WHERE id='".$existe['id']."'";
        cms_query($sql);
        return serialize(array("0"=>"1"));
    }

    
    $sql = "INSERT INTO avisos_stock SET email='".$email_form."',
                                        pid='".$_POST['pid']."',
                                        sku_family='".$_POST['sku_family']."',
                                        sku='".$_POST['sku']."',
                                        cor='".$_POST['cor']."',
                                        tamanho='".$_POST['size']."',
                                        mercado='".$_SESSION['_MARKET']['id']."',
                                        pais='".$_SESSION['_COUNTRY']['id']."',
                                        depositos='".$_SESSION['_MARKET']['deposito']."',
                                        lg='".$LG."',
                                        data=NOW(),
                                        id_cliente='".$cliente_id."'";

    cms_query($sql);

    return serialize(array("0"=>"1"));
}
?>
