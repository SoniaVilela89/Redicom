<?

function _unsubscribeEmail(){
    
    $leid     = trim($_POST['leid']);
    $leid_arr = explode('|||', base64_decode($leid));
    
    $type    = (int)safe_value($leid_arr[0]);

          
    if($type == 0){
        $client_email = trim(safe_value($leid_arr[2]));
        $client_lista = (int)safe_value($leid_arr[3]);

        if( $client_lista <= 0 || $client_email == '' ){
            return serialize(['success' => 0]); # return error
        }

        $unsubsribe_info = cms_fetch_assoc(cms_query("SELECT `email`, id FROM `ec_sms_listas_externas_contactos` WHERE `email`='".$client_email."' AND `lista`=".$client_lista));
        
        cms_query('UPDATE `ec_sms_listas_externas_contactos` SET rejeita_email_marketing=1 WHERE id=' . $unsubsribe_info['id'] . ' LIMIT 1');
        $linhas_afetadas = cms_affected_rows();
        if($linhas_afetadas==1){
             cms_query("UPDATE `ec_sms_listas_externas_contactos` SET rejeita_email_marketing=1 WHERE email='".$client_email."'");
        }
        
            
    }else if($type == 1){
        $client_email = trim(safe_value($leid_arr[2]));
        $confirmed    = (int)safe_value($leid_arr[3]);

        if( !is_numeric($confirmed) || $client_email == '' ){
            return serialize(['success' => 0]); # return error
        }

        $unsubsribe_info = cms_fetch_assoc(cms_query("SELECT id, `email` FROM `_tnewsletter` WHERE `email`='".$client_email."' "));
        
        
        cms_query('UPDATE `_tnewsletter` SET ma_remocao=1 WHERE id=' . $unsubsribe_info['id'] . ' AND ma_remocao=0 LIMIT 1');        
        
    }else if($type == 5){
        $client_email = trim(safe_value($leid_arr[2]));
      
        if( $client_email == '' ){
            return serialize(['success' => 0]); # return error
        }

        $unsubsribe_info['email'] = $client_email;
    }

    if(trim($unsubsribe_info['email'])==''){
        return serialize(['success' => 0]); # return error
    }

    cms_query('INSERT INTO `ec_sms_listas_externas_remove` (`email`, `origem`, `job_id`) VALUES("' . $unsubsribe_info['email'] . '", "PAGE_98", "'.$_POST['jobid'].'")');
    
    return serialize(['success' => 1]); # return success

}

?>
