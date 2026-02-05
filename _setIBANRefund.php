<?

function _setIBANRefund(){
    
    global $slocation;
    
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = utf8_decode($v);
    }
    
    if (!isset($_POST['iban']) || empty($_POST['iban'])) return serialize(array("0"=>"0"));
    if (!isset($_POST['rfd']) || empty($_POST['rfd']) || (int)$_POST['rfd']==0) return serialize(array("0"=>"0"));
    if (!isset($_POST['token']) || empty($_POST['token'])) return serialize(array("0"=>"0"));
    
    $id_refund = $_POST['rfd'];
    $iban = $_POST['iban'];
    $save_iban = $_POST['save_iban'];
    
    $sql_refund = sprintf("SELECT * FROM encomendas_estornos WHERE id='%d' AND nib='' AND status IN (20,25) LIMIT 0,1", $id_refund);
    $res_refund = cms_query($sql_refund);
    $row_refund = cms_fetch_assoc($res_refund);
    
    if ((int)$row_refund['id']==0) return serialize(array("0"=>"0"));
    
    $sql_order = sprintf("SELECT * FROM ec_encomendas WHERE id='%d' LIMIT 0,1", $row_refund["order_id"]);
    $res_order = cms_query($sql_order);
    $row_order = cms_fetch_assoc($res_order);
    
    $token = md5($row_refund["id"]."|||".$row_refund["pagref"]."|||".$row_order["email_cliente"]."|||".$row_order["id"]);
    
    if ($token!=$_POST['token']) return serialize(array("0"=>"0"));
    
    $sql_update = sprintf("UPDATE encomendas_estornos SET nib='%s',save_iban='%d', status='30' WHERE id='%d' AND status IN (20,25) ", $iban, $save_iban, $id_refund);
    cms_query($sql_update);
    
    $sql_update = sprintf("UPDATE ec_encomendas SET nib='%s' WHERE id='%d'", $iban, $row_refund['order_id']);
    cms_query($sql_update);
    
    cms_query("INSERT INTO ec_encomendas_log SET autor='".$row_order['email']."', estado_novo=98, autor_id=0, encomenda='".$row_refund[order_id]."', obs='IBAN fornecido por cliente em site: $iban'");
            
    
    if($row_refund['return_id']>0){
        $sql_update = sprintf("UPDATE ec_devolucoes SET nib='%s' WHERE id='%d'", $iban, $row_refund['return_id']);
        cms_query($sql_update);
        
        cms_query("INSERT INTO ec_devolucoes_log SET autor='".$_SESSION['FIXED']['USERFULLNAME']."', estado_novo=98, encomenda='".$row_refund[return_id]."', obs='IBAN fornecido por cliente em site: $iban'");          
    }
    
    
    
    $data = array("IBAN" => $iban); 

    $send_data = serialize($data);
    $send_data = gzdeflate($send_data, 9);
    $send_data = gzdeflate($send_data, 9);
    $send_data = urlencode($send_data);
    
    $send_data = base64_encode($send_data);
    
    
    require_once $_SERVER["DOCUMENT_ROOT"].'/api/controllers/_sendEmailGest.php';
    _sendEmailGest($row_order["id"], "59", 0, $row_order["email_cliente"], 0, $send_data);
    
    
    return serialize(array("0"=>"1"));
}


?>
