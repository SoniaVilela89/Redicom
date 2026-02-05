<?

function _getAccountUserRestrictedNew($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;


    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }


    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    
    $arr['informations'] = array();
        
    $docs_s = "select id, nome$LG as nome from downloadcat ORDER BY nome$LG";
    $docs_q = cms_query($docs_s);        
    while($docs_r = cms_fetch_assoc($docs_q)){
        $arr['informations'][] = array("id" => $docs_r['id'], "name" => $docs_r['nome']);        
    }
        
    return serialize($arr);

}
?>
