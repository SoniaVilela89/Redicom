<?
function _sendTestimony(){
    global $fx;
    global $LG, $userID;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }
    
    $qtd = 3;
    if(!is_numeric($userID)){
        $qtd = 5;
    }
    
    
    #Para evitar a submissão excessiva de formularios
    $key = $_SERVER[REMOTE_ADDR]."testimony";
    $x = 0 + @apc_fetch($key);
    if ($x>$qtd){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 60);
    }

    
    
    
    $DADOS = $_POST;

    if(empty($_POST["name"]) || empty($_POST["email_address"]) || empty($_POST["message"]) || empty($_POST["city"])){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    if(!preg_match("/^[[:alnum:]][a-z0-9_.-]*@[a-z0-9.-]+\.[a-z]{2,4}$/i", $_POST["email_address"])){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    $sql = "INSERT INTO b2c_testemunhos  (nome,email,mensagem,cidade,data,ip_cliente,browser_cliente) VALUES ('%s','%s','%s','%s', CURDATE(),'%s','%s')";
    $sql_n = sprintf($sql, $DADOS["name"], $DADOS["email_address"], $DADOS["message"], $DADOS["city"],$_SERVER['REMOTE_ADDR'],$_SERVER['HTTP_USER_AGENT']);
    cms_query($sql_n);
    
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
}
?>
