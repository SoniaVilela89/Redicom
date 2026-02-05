<?

function _getAccountOrdersFromBDI($page_id=null)
{
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $return_arr                        = [];
    $return_arr['page']                = call_api_func('pageOBJ', $page_id, $page_id, 0, "ec_rubricas");
    $return_arr['account_pages']       = call_api_func('pageOBJ', 9, $page_id, 0, "ec_rubricas");
    $return_arr['customer']            = call_api_func('getCustomer');
    $return_arr['shop']                = call_api_func('OBJ_shop_mini');
    $return_arr['account_expressions'] = call_api_func('getAccountExpressions');

    require_once _ROOT."/api/api_external_functions.php";
    $bdi_connection = returnBDiConnection();
    if(empty($bdi_connection)){
        $return_arr['orders'] = [];
        return serialize($return_arr);
    }

    global $userID, $ESTADOS_ENCOMENDAS;
    global $CONFIG_OPTIONS;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    $user = cms_fetch_assoc( cms_query("SELECT `cod_erp` FROM `_tusers` WHERE `id`='".$userOriginalID."'") );
    
    if(trim($ESTADOS_ENCOMENDAS)==''){
        $ESTADOS_ENCOMENDAS = "1,10,40,42,45,50,70,80,103,100";
    }

    $orders = [];

    $return_arr['payment_methods'] = array();

    $sql = "SELECT [ENCOMENDAS].[EncomendaID] AS [id],
                MAX([ENCOMENDAS].[EncomendaRef]) AS [order_ref],
                MAX([ENCOMENDAS].[DataHora]) AS [datahora],
                MAX([ENCOMENDAS].[MoedaCodeISO4217]) AS [moeda_sigla],
                MAX([ENCOMENDAS].[QuantidadeTotal]) AS [qtd],
                MAX([ENCOMENDAS].[ValorTotal]) AS [valor],
                MAX([ENCOMENDAS_PAGAMENTOS].[PagamentoMetodoSiteID]) AS [pagamento_id],
                STUFF(
                        (
                            SELECT ' + ' + [PagamentoMetodo]
                            FROM [ENCOMENDAS_PAGAMENTOS]
                            WHERE [ENCOMENDAS_PAGAMENTOS].[EncomendaID]=[ENCOMENDAS].[EncomendaID]
                            FOR XML PATH('')
                        )
                        , 1, 3, '') AS [pagamento_text],
                SUM([ENCOMENDAS_PAGAMENTOS].[PagamentoValor]) AS [pagamento_valor],
                MAX([ENCOMENDAS_PAGAMENTOS].[PagamentoDataHora]) AS [pagamento_data],
                MAX([ENCOMENDAS_PAGAMENTOS].[PagamentoCode]) AS [pagamento_token]
            FROM [ENCOMENDAS] WITH (NOLOCK)
                LEFT JOIN [ENCOMENDAS_PAGAMENTOS] ON [ENCOMENDAS_PAGAMENTOS].[EncomendaID]=[ENCOMENDAS].[EncomendaID]
            WHERE ( 
                    ( [ENCOMENDAS].[ClienteErpCode]!='' 
                        AND [ENCOMENDAS].[ClienteErpCode]='".$user['cod_erp']."' 
                        AND [ENCOMENDAS].[Origem]!='SITE' 
                    ) OR 
                    ( [ENCOMENDAS].[ClienteSiteID]='".$userOriginalID."' 
                        AND [ENCOMENDAS].[Origem]='SITE' 
                    ) 
                )
            GROUP BY [ENCOMENDAS].[EncomendaID]
            ORDER BY [DataHora] DESC";
            
    $res = sqlsrv_query($bdi_connection, $sql);
    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){
        
        $order_last_log  = getOrderChangeLogInfo($row['id'], $bdi_connection);
        $currency        = call_api_func('get_line_table', 'ec_moedas', "`codigo`='".$row['moeda_sigla']."' AND `activo`=1");
        $transactionsOBJ = [];

        global $ARRAY_ESTADOS_ENCOMENDAS;
        if(!is_array($ARRAY_ESTADOS_ENCOMENDAS)){
            $ARRAY_ESTADOS_ENCOMENDAS = array("1" => "1", "10" => "10", "40" => "40", "42" => "42", "45" => "45", "50" => "50", "70" => "70", "80" => "80", "100" => "100", "103" => "103", "1000" => "1000");        
        } 
        
        $allstates_status = $order_last_log['status']['id'];
        
        if($allstates_status>$ARRAY_ESTADOS_ENCOMENDAS[10] && $allstates_status<$ARRAY_ESTADOS_ENCOMENDAS[50])
            $allstates_status = 40;
            
        if($allstates_status>$ARRAY_ESTADOS_ENCOMENDAS[70] && $allstates_status<$ARRAY_ESTADOS_ENCOMENDAS[80])
            $allstates_status = 70;
        
        $allstates_status_label = utf8_decode($order_last_log['status']['nome']);
        
        $fulfillID   = array($ARRAY_ESTADOS_ENCOMENDAS[1], $ARRAY_ESTADOS_ENCOMENDAS[10], $ARRAY_ESTADOS_ENCOMENDAS[40], $ARRAY_ESTADOS_ENCOMENDAS[42], $ARRAY_ESTADOS_ENCOMENDAS[45], $ARRAY_ESTADOS_ENCOMENDAS[80]);
        $financialID = array($ARRAY_ESTADOS_ENCOMENDAS[50], $ARRAY_ESTADOS_ENCOMENDAS[70], $ARRAY_ESTADOS_ENCOMENDAS[103], $ARRAY_ESTADOS_ENCOMENDAS[100]);
        
        $fulfillment_status       = "";
        $fulfillment_status_label = "";
        $financial_status         = "";
        $financial_status_label   = "";
        
        if( in_array($order_last_log['status']['id'], $fulfillID) ){
            $fulfillment_status       = $order_last_log['status']['id'];
            $fulfillment_status_label = $allstates_status_label;
        }
        
        if( in_array($order_last_log['status']['id'], $financialID) ){
            $financial_status       = $order_last_log['status']['id'];
            $financial_status_label = $allstates_status_label;
        }

        $transactionsOBJ[] = array(
            "id"                      => trim($row['pagamento_token']) != '' ? $row['pagamento_token'] : $row['id'],
            "amount"                  => call_api_func('OBJ_money', $row['pagamento_valor'], $currency['id']),
            "created_at"              => $row['pagamento_data'],
            "gateway"                 => utf8_decode($row['pagamento_text']),
            "gateway_id"              => $row['pagamento_id']
        );

        if(isset($row['pagamento_text'])) $return_arr['payment_methods'][$row['pagamento_text']] = $row['pagamento_text'];

        $arr = array(
            "order_number"              => $row['id'],
            "order_number_encoder"      => base64_encode($row['id']),
            "name"                      => $row['order_ref'],
            "quantity"                  => $row['qtd'],
            "date"                      => date("d-m-Y", strtotime($row['datahora'])),
            "created_at"                => date("d-m-Y", strtotime($row['datahora'])),
            "total_price"               => call_api_func('OBJ_money', $row['valor'], $currency['id']),
            "transactions"              => $transactionsOBJ,
            "allstates_status"          => $allstates_status,
            "allstates_status_label"    => $allstates_status_label,
            "fulfillment_status"        => $fulfillment_status,
            "fulfillment_status_label"  => $fulfillment_status_label,
            "financial_status"          => $financial_status,
            "financial_status_label"    => $financial_status_label
        );    

        $orders[] = $arr;

    }
    
    $return_arr['payment_methods'] = array_values($return_arr['payment_methods']);
    
    $return_arr['orders'] = $orders;
    return serialize($return_arr);

}


function getOrderChangeLogInfo($order_id, $bdi_connection){
    
    $sql = "SELECT TOP 1 * FROM [ENCOMENDAS_ALTERACOES_ERP] WHERE [EncomendaID]='".$order_id."' ORDER BY [SysSiteUpdate]";
    $res = sqlsrv_query($bdi_connection, $sql);
    $change_log_info = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    
    if(empty($change_log_info)){
        return [];
    }

    $status_info = call_api_func('getStatusInfoByERPCode', $change_log_info['EstadoEncomendaErpCode'], $bdi_connection);
    return [
        'status'           => $status_info,
        'tracking_factura' => $change_log_info['DocumentoNome'],
        'tracking_number'  => $change_log_info['TrackingNumber']
    ];

}

function getStatusInfoByERPCode($status_code, $bdi_connection){
    
    global $LG;
    
    switch($LG){
        case 'sp':
            $lang = 'ES';break;
        case 'gb':
            $lang = 'EN';break;
        default:
            $lang = $LG;
    }

    $sql = "SELECT [EstadoID] AS [id], [Designacao".strtoupper($lang)."] AS [nome] FROM [ESTADOS_ENCOMENDAS_DEVOLUCOES] WHERE [EstadoErpCode]='".$status_code."'";
    
    $res = sqlsrv_query($bdi_connection, $sql);
    return sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

}
