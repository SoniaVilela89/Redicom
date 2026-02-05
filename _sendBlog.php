<?
function _sendBlog($careers=null){
    global $fx;
    global $LG;

    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }
    
    #Para evitar a submissão excessiva de formularios
    $key = md5(base64_encode($_SERVER[REMOTE_ADDR] . "blog"));
    $x = 0 + @apc_fetch($key);
    if ($x>3){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 60);
    }
    
    $DADOS = $_POST;

    if(empty($_POST["nome"]) || empty($_POST["email"]) || empty($_POST["comentario"])){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    if(!preg_match("/^[[:alnum:]][a-z0-9_.-]*@[a-z0-9.-]+\.[a-z]{2,4}$/i", $_POST["email"])){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    
    $sql = "INSERT INTO blog_log  (nome,email,comentario,blog_id) VALUES ('%s','%s','%s','%d')";
    $sql_n = sprintf($sql, $DADOS["nome"],$DADOS["email"],$DADOS["comentario"],$DADOS["id_blog"]);
    cms_query($sql_n);
    
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
}
?>
