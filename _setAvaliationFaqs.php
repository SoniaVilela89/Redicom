<?
function _setAvaliationFaqs(){

    global $LG;
    global $COUNTRY;   
    global $session_id; 
  
    $faq_id             = (int)$_POST['faq_id'];
    $faq_avaliation     = (int)$_POST['faq_avaliation']; 
    
    #Para evitar a submissão excessiva de formularios
    $key = md5(base64_encode($_SERVER[REMOTE_ADDR] . "avaliationFaqs_".$faq_id));

    $x = 0 + @apc_fetch($key);
    if ($x>1)
    {
        $x++;
        apc_store($key, $x, 120);
        return serialize(array("0"=>"error"));
    } else {
        $x++;
        apc_store($key, $x, 120);
    }
       
   
    if($_SESSION['faq_avaliation'][$faq_id] != $faq_id){       
      
      if($faq_avaliation == 2){
        $_SESSION['faq_avaliation'][$faq_id] = 2;
        $sql = "UPDATE _tfaqs SET ajuda_nao = ajuda_nao + 1 WHERE id=$faq_id";
      }else{
        $_SESSION['faq_avaliation'][$faq_id] = 1;
        $sql = "UPDATE _tfaqs SET ajuda_sim = ajuda_sim + 1 WHERE id=$faq_id";
      }
      
      cms_query($sql);
        
    }else{
      return serialize(array("0"=>"error"));
    }                      

    

    return serialize(array("0"=>"1"));
}
?>
