<?

function _getAccountSubscription($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    if(trim($ESTADOS_ENCOMENDAS)==''){
        $ESTADOS_ENCOMENDAS = "-50";
    }


    $orders = array();
    $encomendas = $eComm->getOrders($userID, $ESTADOS_ENCOMENDAS);
    foreach( $encomendas as $k => $v ){
        $orders[] = call_api_func('orderOBJ',$v, 0);
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['orders'] =  $orders;
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}
?>
