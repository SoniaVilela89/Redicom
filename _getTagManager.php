<?php
     
function _getTagManager()
{
    global $id, $slocation_relative, $pid;
    
    $DADOS = $_POST;
    
    foreach( $DADOS as $k => $v ){
        $DADOS[$k] = safe_value(utf8_decode($v));
    }
    
    $id  = $DADOS['id'];
    $cat = $DADOS['cat'];
    $pid = $DADOS['pid'];

          
    $url = $_SERVER["REQUEST_URI"] = base64_decode($DADOS['url']);


    if($pid<1 && $cat>0 && $id>0) {
        $_SERVER["REQUEST_URI"] .= '&t=1'; 
    }
    
  
    $xtag_manager_head = call_api_func('getTrackingTagManager',"Head");        
    $xtag_manager_body = call_api_func('getTrackingTagManager',"Body");
               
                
    $arr = array( "head" => $xtag_manager_head,
                  "body" => $xtag_manager_body);
    
    return serialize($arr);
}
?>
