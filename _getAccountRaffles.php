<?

function _getAccountRaffles($user_id=0){
    
    global $USAR_CACHE_PROD_PROMO;

    if( (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
    }else{
        $user_id = (int)$user_id;
    }

    if( (int)$user_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }

    $page_id = 55;

    $payload                        = array();
    $payload['page']                = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $payload['account_pages']       = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $payload['customer']            = call_api_func('getCustomer');
    $payload['shop']                = call_api_func('OBJ_shop_mini');
    $payload['account_expressions'] = call_api_func('getAccountExpressions');
    
     

    $q = cms_query('SELECT *
                    FROM `sorteios_linhas_encomendas`
                        INNER JOIN `sorteios_linhas` ON `sorteios_linhas`.`id`=`sorteios_linhas_encomendas`.`sorteios_linhas_id`
                        INNER JOIN `sorteios` ON `sorteios`.`id`=`sorteios_linhas`.`sorteio_id` 
                    WHERE utilizador_id="'.$user_id.'"
                    ORDER BY `sorteios_linhas_encomendas`.`id` DESC');

    $arr_raffles = array();
    while($row = cms_fetch_assoc($q)){

        $temp = array();

        $temp['product'] = call_api_func('get_product','',$row['sku'],5,0,1);
        
        $order = cms_fetch_assoc( cms_query("SELECT `id`, `data`, `valor`, `moeda_id`, `tracking_status`, `token`, `datahora`, `ip_client` FROM `ec_encomendas` WHERE id='".$row['encomenda_id']."'") );

        $order_line = cms_fetch_assoc( cms_query("SELECT `valoruni`, `desconto` FROM `ec_encomendas_lines` WHERE order_id='".$order['id']."' LIMIT 1") );
        
        $temp['product']['price'] = call_api_func('OBJ_money',($order_line['valoruni']-$order_line['desconto']), $order['moeda_id']);

        $row['entry_date']  = date("Y-m-d", strtotime($order['data']));

        $row['end_date'] = date("Y-m-d", strtotime($row['data_fim']));

        $allow_cancel = 0;
        if( (int)$order['tracking_status'] == -90 && $row['estado'] == 0 ){
            $allow_cancel = 1;
        }

        $raffle_status = $payload['account_expressions'][893];
        if( (int)$order['tracking_status'] == 100 ){
            $raffle_status = $payload['account_expressions'][627];
        }elseif( $row['estado'] > 0 ){

            $raffle_status = $payload['account_expressions'][894];
            if( $row['premiado'] == 1 ){
                $raffle_status = $payload['account_expressions'][895];
            }
            
        }

        $row['status']          = $raffle_status;
        $row['allow_cancel']    = $allow_cancel;
        $row['token']           = md5($order['token']."|||".$order['datahora']."|||".$order['ip_client']);

        $temp['product']['raffle'] = $row;

        $arr_raffles[] = $temp;

    }

    $payload['raffles'] = $arr_raffles;

    return serialize($payload);

}

?>
