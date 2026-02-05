<?

function _setAccountQuiz(){
    
    global $userID;
    global $eComm;
    global $LG;
    global $fx;
    
    #Para evitar a submissão excessiva de formularios
    $key  = md5(base64_encode($_SERVER[REMOTE_ADDR] . "Quiz"));
    $x    = 0 + @apc_fetch($key);
    if ($x>3){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 60);
    }
    
    if(empty($_POST)){
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    }

    $_arr = $_POST;
        
    $q    = cms_query("SELECT * FROM _tforms WHERE id='".$_arr['data']['form_id']."' LIMIT 0,1 ");          
    $form = cms_fetch_assoc($q);
                               
    $val  = processInformation($fx, $_arr['data'], $arr_dados, $toemail, $assunto); 
    if($val==1)
    {
        $arr    = array();
        $arr[]  = 0;
        return serialize($arr);
    } 
      
    unset($_arr['data']['form_id']);
    unset($_arr['data']['csrf']);
            
    $sql_query  = cms_query("INSERT INTO _tusers_quiz (user_id, quiz, language) VALUES ('".$userID."','".json_encode($_arr['data'], JSON_UNESCAPED_UNICODE)."', '".$LG."') ON DUPLICATE KEY UPDATE quiz='".json_encode($_arr['data'], JSON_UNESCAPED_UNICODE)."'");    
    return serialize(array("0"=>"1"));
    
}
?>
