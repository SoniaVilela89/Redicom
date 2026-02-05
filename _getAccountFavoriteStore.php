<?

function _getAccountFavoriteStore($page_id=null)
{
    
    global $userID;
    global $LG;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page']                = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']       = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer']            = call_api_func('getCustomer');
    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['stores']              = call_api_func('get_shipping_stores');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    return serialize($arr);

}

?>
