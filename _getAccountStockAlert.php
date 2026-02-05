<?

function _getAccountStockAlert($page_id=null)
{

    global $userID;
    global $LG;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    
    
    
    $q = cms_query('SELECT *
                    FROM avisos_stock 
                    WHERE id_cliente="'.$userID.'" AND estado!=2 AND estado!=3
                    ORDER BY created_at desc
                    LIMIT 0,20');

    $stock_alert = array();
    while($row = cms_fetch_assoc($q)){

        $temp = array();
        $temp['product'] = call_api_func('get_product',$row['pid'],'',5,0,1);
        $temp['product']["wishlist"] = call_api_func('verify_product_wishlist', $temp['product']['sku_family'], $userID);
        $row['data'] = date("Y-m-d", strtotime($row['data']));
        $row['enviado'] = date("Y-m-d", strtotime($row['enviado']));
        $temp['product']['stock_alert'] = $row;
        $stock_alert[] = $temp;
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    
    $arr['stock_alert'] = $stock_alert;

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}
?>
