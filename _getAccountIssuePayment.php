<?

function _getAccountIssuePayment($page_id=null)
{
    global $userID, $CONFIG_OPTIONS;

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');

    
    $arrIssuePayments = getIssuePayments($userOriginalID);
    $arr['payments'] = $arrIssuePayments['payments'];
    $arr["all_status"] = $arrIssuePayments['all_status'];

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}

function getIssuePayments($userID){
 
    $arr_payments = array(); 
    $sql_pay = "SELECT *
                  FROM ec_pagamentos_emitidos
                  WHERE cliente_id='".$userID."' AND activo=1 AND deleted=0
                    ORDER BY datahora DESC, id DESC";

    $array_status = array();            
    $res_pay = cms_query($sql_pay);    
    while($row_pay = cms_fetch_assoc($res_pay)){
        $sql_pagamento  = "SELECT ec_m.*
                            FROM ec_pagamentos_emitidos ec_m
                            LEFT JOIN ec_encomendas_lines ec_l ON ec_m.id=ec_l.pid AND ec_l.tipo_linha=6 AND (ec_l.status>=0 AND ec_l.status!=100)
                            WHERE ec_m.activo='1' 
                                AND ec_m.deleted='0'
                                AND ec_m.id='".$row_pay["id"]."'
                                AND ec_m.estado_pagamento='0'
                                AND ec_m.encomenda_id='0'
                                AND ec_m.valor > 0
                                AND ec_m.cliente_id='".$userID."'
                                AND ec_l.id is NULL;";
        $res_pagamento  = cms_query($sql_pagamento);
        $row_pagamento  = cms_fetch_assoc($res_pagamento);
        if((int)$row_pagamento["id"] == 0 && $row_pay['estado_pagamento'] == 0){
            $row_pay['estado_pagamento'] = -1;
        }

        $name_status = estr2(272);
        $class_status = "rdc-state-05";
        if((int)$row_pay['estado_pagamento'] == 0){
            $name_status = estr2(958);
            $class_status = "rdc-state-05";
        }elseif((int)$row_pay['estado_pagamento'] == 1){
            $name_status = estr2(278);
            $class_status = "rdc-state-02";
            $sql_order_ref = "SELECT order_ref FROM ec_encomendas WHERE id='".$row_pay['encomenda_id']."' LIMIT 0,1";
            $res_order_ref  = cms_query($sql_order_ref);
            $row_order_ref  = cms_fetch_assoc($res_order_ref);
        }

        $arr_payments[] = array(
            "id"                =>  $row_pay["id"],
            "value"             =>  call_api_func('moneyOBJ', $row_pay['valor'], $row_pay['moeda_id']),
            "num_doc"           =>  $row_pay['num_doc'],
            "emission_reason"   =>  $row_pay['motivo_emissao'],
            "date"              =>  date("Y-m-d", strtotime($row_pay['datahora'])),
            "enc_id"            =>  $row_pay['encomenda_id'],
            "order_ref"         =>  $row_order_ref['order_ref'],
            "status_payment"    =>  array(
                                        "id"            => $row_pay['estado_pagamento'],
                                        "name"          => $name_status, 
                                        "class_name"    => $class_status 
                                    ),
            "date_payment"      =>  $row_pay['datahora_pagamento']
        );

        $array_status[$row_pay['estado_pagamento']]["id"] = $row_pay['estado_pagamento'];
        $array_status[$row_pay['estado_pagamento']]["name"] = $name_status;
        $array_status[$row_pay['estado_pagamento']]["total_payment"]++;

    }
    
    $arr_return["payments"] = $arr_payments;
    $arr_return["all_status"] = $array_status;

    return $arr_return;
}

?>
