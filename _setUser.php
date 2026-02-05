<?

function _setUser($user_id, $data){


    global $LG, $eComm, $_DOMAIN;    
    
    
    if ($user_id > 0){
        $user_id   = (int)$user_id;
        $data      = $data;
    }else{
        $user_id   = (int)params('user_id');
        $data      = params('data');
    }
    
    
    $arr = array("0" => 0);

    if( (int)$user_id<1 || trim($data)==''){ 
        return $arr;
    }


    $s    = "SELECT * FROM _tusers WHERE id='%d' LIMIT 0,1";
    $f    = sprintf($s, $user_id);
    $q    = cms_query($f);
    $user = cms_fetch_assoc($q);
    

    if( md5($user['id'].'|'.$user['email'].'|'.$user['registed_at'])!=$data ){
        return $arr;    
    }
    
    $eComm->createUserSession($user);
    

    $s    = "SELECT * FROM ec_language WHERE activo='1' AND id='".$_SESSION['_COUNTRY']['idioma']."' LIMIT 0,1";   
    $q    = cms_query($s);
    $lang = cms_fetch_assoc($lang_sql);
    
    
    if( $lang['id']>0 ){
    
        if( $lang['code']=="es" ) $lang['code']="sp";
        if( $lang['code']=="en" ) $lang['code']="gb";
    
        $_SESSION['LG'] = $lang['code'];
    }
    

    $arr = array("session_id" => session_id());
    
    
    #$_SESSION['ACCESSED_USERS'][$user['id']] = array("user_id" => $user['id'], "date" => date("Y-m-d H:i:s"));      

    setcookie("ACCESSED_USERS[".$user['id']."]", base64_encode(serialize(array("user_id" => $user['id'], "date" => date("Y-m-d H:i:s")))) , time()+3600, "/", $_DOMAIN, true, true);


    return serialize($arr);
}

?>
