<?

function _getOrderStatus($page_id=0){
  $arr = array();
  $arr['code_order'] = "";
  
  
  $order_ref = safe_value($_POST['order_ref']);
  $email = safe_value($_POST['email']);
 
 
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      ob_clean();
      header('HTTP/1.0 400 Bad Request');
      exit;
  } 

  $s = "SELECT id FROM `ec_encomendas` WHERE `order_ref` = '%s' AND `email_cliente` = '%s' AND pagref!='' LIMIT 1";
  $f = sprintf($s,$order_ref,$email);

  $sql = cms_query($f);
  $row = cms_fetch_assoc($sql);

  if($row['id'] > 0 ) {
      $arr['code_order'] = 1;    
      $_SESSION['sys_codeorder'] = $row['id']; 
  }

  return serialize($arr);
}

?>
