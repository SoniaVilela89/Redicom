<?

function _getAccountPassword($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;
    global $B2B;
    global $SETTINGS;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    $arr['email_change'] = (int)$SETTINGS['alterar_email'];
    
    if((int)$_SESSION['EC_USER']['id_utilizador_restrito']>0){
                                
        $sql  = cms_query("SELECT * FROM _tusers WHERE id='".$_SESSION['EC_USER']['id_utilizador_restrito']."' LIMIT 0,1");
        $_usr = cms_fetch_assoc($sql);   
        
        $arr['customer']["email"] = $_usr['email']; 
    }
    
    return serialize($arr);

}
?>
