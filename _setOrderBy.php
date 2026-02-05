<?
  function _setOrderBy(){
   
    
    
   
    if( $_SESSION['order_active'][$_POST['page_id']][$_POST['order_by']['field']]==$_POST['order_by']['value'] ){
      unset( $_SESSION['order_active'][$_POST['page_id']][$_POST['order_by']['field']] );
      
      if( empty( $_SESSION['order_active'][$_POST['page_id']][$_POST['order_by']['field']] ) )
        unset( $_SESSION['order_active'][$_POST['page_id']][$_POST['order_by']['field']] );
      
      if( empty( $_SESSION['order_active'][$_POST['page_id']] ) )
        unset( $_SESSION['order_active'][$_POST['page_id']] );
      
      if( empty( $_SESSION['order_active'] ) )
        unset( $_SESSION['order_active'] );  
        
    }else{
    unset( $_SESSION['order_active'][$_POST['page_id']]);
      $_SESSION['order_active']
        [$_POST['page_id']]
          [$_POST['order_by']['field']] = $_POST['order_by']['value'];
    }
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
  }
?>