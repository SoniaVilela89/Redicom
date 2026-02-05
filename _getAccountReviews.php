<?

function _getAccountReviews($page_id=null)
{

    global $userID, $CONFIG_OPTIONS;
    

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    /*$q = cms_query('SELECT ecl.*,ec.data as enc_data
                    FROM ec_encomendas_lines ecl
                        INNER JOIN ec_encomendas ec ON ecl.order_id=ec.id AND ec.pagref<>""
                    WHERE ref<>"PORTES" and status > 0 AND status NOT IN (100,48) and id_cliente="'.$userID.'" and order_id>0 and pack="0" and egift="0" and review_made="0"
                    ORDER BY id desc
                    LIMIT 0,20');*/
    $q = cms_query('SELECT ecl.*,ec.data as enc_data
                    FROM ec_encomendas_lines ecl
                        INNER JOIN ec_encomendas ec ON ecl.order_id=ec.id AND ec.pagref<>""
                    WHERE ref<>"PORTES" and status > 0 AND status NOT IN (0,100,48) and id_cliente="'.$userOriginalID.'" and order_id>0 and pack="0" and egift="0" and review_made="0" AND id_linha_orig<1
                    GROUP BY ecl.sku_family
                    ORDER BY id desc
                    LIMIT 0,20');
                    
    $reviews = array();
    while($row = cms_fetch_assoc($q)){

        $temp                         = array();
        $prod                         = array();
        #$prod                         = call_api_func('get_product',$row['pid'],'',5,0);
        $prod                         = call_api_func('get_product',$row['pid'],'',5,0,1);
        
        if( $prod["id"] == 0 || $prod["review_product"]["allow_review"] == 0 ) continue;
        
        $temp['product']              = $prod;
        $temp['product']["wishlist"]  = call_api_func('verify_product_wishlist', $temp['product']['sku_family'], $userOriginalID);

        $temp['date']                 = $row['enc_data'];

        $reviews[] = $temp;
                
    }


    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');

    $arr['reviews'] = $reviews;

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}

?>
