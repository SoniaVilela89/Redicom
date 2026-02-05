<?

function _setAccountConfirmClientCard($token=null){
    
    global $SETTINGS;

    if(is_null($token)){
        $token = params('token');
    }
    
    $token      = safe_value($token);
    $token = str_replace(' ', '', $token);

    $result_sql = cms_query("SELECT id,f_code,telefone,nif,email FROM _tusers WHERE token_cartao='$token' and DATEDIFF(NOW(), data_cartao ) < 2 AND estado_cartao=1");
    $result     = cms_fetch_assoc($result_sql);
    
    $continue = true;
    
    if((int)$SETTINGS['cartao_cliente_associacao'] > 1) {
                
      	if(is_callable('checkoutValidateClientCard')) {
            
            $arr["id"]            = $result["id"];  
            $arr["cartao_num"]    = $result["f_code"];
            $arr["telefone"]      = $result["telefone"];
            $arr["nif"]           = $result["nif"];
            $arr["email"]         = $result["email"];
            
      	    $continue = call_user_func('checkoutValidateClientCard', $arr);
      	}

    }
    
    if( $continue==true && (int)$result["id"]>0){
        cms_query("UPDATE _tusers SET token_cartao='', data_cartao=NOW(), estado_cartao='2' WHERE id='".$result["id"]."'");
        return serialize(array("0"=>"1"));
    }else{
    
        if((int)$result["id"]>0){
            cms_query("UPDATE _tusers SET f_cartao='0', f_code='', data_cartao='0000-00-00', estado_cartao='0', token_cartao='' WHERE id='".$result["id"]."'");
        }
        
        return serialize(array("0"=>"0"));
    }
    
}

?>
