<?

function _unsubscribeSms(){
    
    
    if(isset($_SESSION['sms_un'])){
        ob_clean();
        header('HTTP/1.1 403 Forbidden'); 
        exit;
    }  
    
    
    # Email
    if( (int)$_POST['ml'] == 1 ){
        
        $email = trim($_POST['pn']);
        
        if( $email == "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ){
            return serialize(['success' => 0, 'error' => ['code' => '001', 'info' => 'An error occurred!'] ]);
        }
        
        cms_query("UPDATE `_tnewsletter` SET `ma_remocao`=1 WHERE `ma_remocao`=0 AND `email`='".$email."'");    
        
    }else{
        
        #SMS
        
        $phoneNumber = trim($_POST['pn']);      
        $phoneNumber = substr($phoneNumber, -9);
        
        preg_match('/^9[1236]{1}[0-9]{7}$/', $phoneNumber, $matches, PREG_OFFSET_CAPTURE);
        
        
        if (count($matches)==0){
            ob_clean();
            header('HTTP/1.1 400 Bad Request'); 
            exit;
        }

        if( $phoneNumber == '' || $phoneNumber == '123' ){ # Phone equals to 123 to test the error case
            return serialize(['success' => 0, 'error' => ['code' => '001', 'info' => 'An error occurred!'] ]); # returns an error
        }
        
        
        cms_query("INSERT INTO `ec_sms_listas_externas_remove` (`tel`, `origem`) VALUES ('".$phoneNumber."', 'PAGE_96') ");  
        
        $_SESSION['sms_un'] = 1;  
        
    }
    
    return serialize(['success' => 1]); # return success

}

?>
