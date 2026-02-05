<?

function _getAccountOrderDetailFromBDI($page_id=null, $order_id=null)
{
    
    if(is_null($page_id)){
        $page_id  = (int)params('page_id');
        $order_id = (int)params('order_id');
    }

    $return_arr                        = [];
    $return_arr['page']                = call_api_func('pageOBJ', $page_id, $page_id, 0, "ec_rubricas");
    $return_arr['account_pages']       = call_api_func('pageOBJ', 9, $page_id, 0, "ec_rubricas");
    $return_arr['customer']            = call_api_func('getCustomer');
    $return_arr['shop']                = call_api_func('OBJ_shop_mini');
    $return_arr['account_expressions'] = call_api_func('getAccountExpressions');

    $return_arr['exp_pvr']             = estr(472);
    $return_arr['exp_pvr_desc']        = estr(473);
    $return_arr['exp106']              = estr(106);
    $return_arr['exp112']              = estr(112);

    if(is_null($order_id) || (int)$order_id <= 0){
        $return_arr['orders'] = [];
        return serialize($return_arr);
    }

    require_once _ROOT."/api/api_external_functions.php";
    $bdi_connection = returnBDiConnection();
    if(empty($bdi_connection)){
        $return_arr['orders'] = [];
        return serialize($return_arr);
    }

    global $userID;
    global $CONFIG_OPTIONS;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    $user = cms_fetch_assoc( cms_query("SELECT `cod_erp` FROM `_tusers` WHERE `id`='".$userOriginalID."'") );

    $orders = [];

    $sql = "SELECT TOP 1 [ENCOMENDAS].[EncomendaID] AS [id],
                [ENCOMENDAS].[EncomendaRef] AS [order_ref],
                [ENCOMENDAS].[ClienteErpCode] AS [client_erp_code],
                [ENCOMENDAS].[ClienteSiteID] AS [cliente_final],
                [ENCOMENDAS].[DataHora] AS [datahora],
                [ENCOMENDAS].[FaturacaoNome] AS [nome_cliente],
                [ENCOMENDAS].[FaturacaoMorada] AS [morada1_cliente],
                [ENCOMENDAS].[FaturacaoMoradaCont] AS [morada2_cliente],
                [ENCOMENDAS].[FaturacaoCidade] AS [cidade_cliente],
                [ENCOMENDAS].[FaturacaoCP] AS [cp_cliente],
                [ENCOMENDAS].[FaturacaoPaisISO3166] AS [pais_cliente_sigla],
                [ENCOMENDAS].[ClienteEmail] AS [email_cliente],
                [ENCOMENDAS].[FaturacaoNif] AS [nif_cliente],
                [ENCOMENDAS].[EntregaNome] AS [entrega_nome],
                [ENCOMENDAS].[EntregaMorada] AS [entrega_morada1],
                [ENCOMENDAS].[EntregaMoradaCont] AS [entrega_morada2],
                [ENCOMENDAS].[EntregaCP] AS [entrega_cp],
                [ENCOMENDAS].[EntregaCidade] AS [entrega_cidade],
                [ENCOMENDAS].[EntregaPaisISO3166] AS [entrega_pais_sigla],
                [ENCOMENDAS].[EntregaTelefone] AS [entrega_telefone],
                [ENCOMENDAS].[EntregaPickupCode] AS [entrega_pickup_code],
                [ENCOMENDAS].[MoedaCodeISO4217] AS [moeda_sigla],
                [ENCOMENDAS].[QuantidadeTotal] AS [qtd],
                [ENCOMENDAS].[ValorTotal] AS [valor],
                [ENCOMENDAS].[IVAValorTotal] AS [iva_valor],
                [ENCOMENDAS].[ValorPortes] AS [portes],
                [ENCOMENDAS].[Observacoes] AS [obs],
                [ENCOMENDAS].[TransportadoraSiteID] AS [metodo_shipping_id],
                [ENCOMENDAS].[TransportadoraTipo] AS [transportadora_tipo],
                [ENCOMENDAS].[PONumber] AS [po_number],
                [ENCOMENDAS].[DataEntrega] AS [data_entrega],
                [ENCOMENDAS].[DescontoModalidadePagamento] AS [desconto_modalidade_pagamento],
                [ENCOMENDAS].[CustoPagamento] AS [custo_pagamento]
            FROM [ENCOMENDAS] WITH (NOLOCK)
            WHERE ( 
                    ( [ENCOMENDAS].[ClienteErpCode]!='' 
                        AND [ENCOMENDAS].[ClienteErpCode]='".$user['cod_erp']."' 
                        AND [ENCOMENDAS].[Origem]!='SITE' 
                    ) OR 
                    ( [ENCOMENDAS].[ClienteSiteID]='".$userOriginalID."' 
                        AND [ENCOMENDAS].[Origem]='SITE' 
                    ) 
                ) AND [ENCOMENDAS].[EncomendaID]='" . $order_id . "'";

    $res = sqlsrv_query($bdi_connection, $sql);
    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){
        
        $row = array_map("utf8_decode", $row);
        $row['discounts_arr'] = [];

        $names = explode(" ", $row['nome_cliente']);
        $row['billing_arr'] = array(
            "first_name"    => $names[0],
            "last_name"     => ( count($names)>1 ) ? $names[count($names)-1] : "",
            "name"          => $row['nome_cliente'],
            "address1"      => $row['morada1_cliente'],
            "address2"      => $row['morada2_cliente'],
            "street"        => $row['morada1_cliente']." ".$row['morada2_cliente'],
            "city"          => $row['cidade_cliente'],
            "zip"           => $row['cp_cliente'],
            "email_address" => $row['email_cliente'],
            "nif"           => $row['nif_cliente'],
            "country_code"  => strtolower($row['pais_cliente_sigla']),
        );
      
      
        $names = explode(" ", $row['entrega_nome']);  
        $row['shipping_arr'] = array(
            "first_name"    => $names[0],
            "last_name"     => ( count($names)>1 ) ? $names[count($names)-1] : "",
            "name"          => $row['entrega_nome'],
            "address1"      => $row['entrega_morada1'],
            "address2"      => $row['entrega_morada2'],
            "street"        => $row['entrega_morada1']." ".$row['entrega_morada2'],
            "city"          => $row['entrega_cidade'],
            "zip"           => $row['entrega_cp'],
            "phone"         => $row['entrega_telefone'],
            "country_code"  => strtolower($row['entrega_pais_sigla']),
            "store_name"    => $name_store
        );
        
        $orders[] = call_api_func('buildOrderInfoFromBDI', $row, $bdi_connection);

    }
    
    $return_arr['orders'] = $orders;
    return serialize($return_arr);

}

function buildOrderInfoFromBDI($order, $bdi_connection){

    global $LG;

    $order_last_log         = getOrderChangeLogInfo($order['id'], $bdi_connection);
    $currency               = call_api_func('get_line_table', 'ec_moedas', "`codigo`='".$order['moeda_sigla']."' AND `activo`=1");
    $shipping_info          = call_api_func('get_line_table', 'ec_shipping', "`id`=".$order['metodo_shipping_id']);
    $valor_vale_de_desconto = 0;
    $order['shipping_tracking_number'] = '';

    $factura  = "";
    if(!is_null($order_last_log['tracking_factura']) && trim($order_last_log['tracking_factura'])!=''){
        $cam_fact = _ROOT.'/prints/FT/import/'.str_replace('/', '', $order_last_log['tracking_factura']);
        if(file_exists($cam_fact)){
            $factura = '/prints/FT/import/'.str_replace('/', '', $order_last_log['tracking_factura']);
        }
    }
    
    $facturas = array();
    foreach (glob($_SERVER['DOCUMENT_ROOT'].'/prints/FT/enc_'.$order['id']."_*.pdf") as $filename) {
        $name = explode('/FT/', $filename);
        if('/prints/FT/'.$name[1]!=$factura){
            $facturas[] = '/prints/FT/'.$name[1];
        }
    }

    $order_confirmation = "";
    $cam_confirmation = _ROOT."/prints/CE/import/".str_replace('/', '', $order['order_ref']).'.pdf';
    if(file_exists($cam_confirmation)){
        $order_confirmation = "/prints/CE/import/".str_replace('/', '', $order['order_ref']).'.pdf';
    }

    $fulfillmentOBJ[] = array(
        "tracking_company"    => $shipping_info['nome'.$LG],
        "transport"           => $order['metodo_shipping_id'],
        "receipt"             => $factura,
        "receipts"            => $facturas,
        "order_confirmation"  => $order_confirmation,
        "shipping_type"       => $order['transportadora_tipo']
    );
   
    $transactionsOBJ = getAllOrderPaymentsFromBDI($order['id'], $bdi_connection, $currency);

    if( $order['desconto_modalidade_pagamento'] > 0 ){
        $transactionsOBJ[] = array(
            "id"                         => "",
            "amount"                     => call_api_func('OBJ_money', $order['desconto_modalidade_pagamento'], $currency['id']),
            "created_at"                 => "",
            "gateway"                    => estr2(879),
            "gateway_id"                 => "5"
        );
        $transactionsOBJ[0]['amount'] = call_api_func('OBJ_money', $transactionsOBJ[0]['amount']['value'] - $order['desconto_modalidade_pagamento'], $currency['id']);
    }
    
    $discounts_arr       = [];
    $total_prods_sem_iva = 0;
    $lines               = getOrderLinesFromBDI($order['id'], $currency, $bdi_connection, $discounts_arr, $total_prods_sem_iva);


    global $ARRAY_ESTADOS_ENCOMENDAS;
    if (!is_array($ARRAY_ESTADOS_ENCOMENDAS)) {
        $ARRAY_ESTADOS_ENCOMENDAS = array("1" => "1", "10" => "10", "40" => "40", "42" => "42", "45" => "45", "50" => "50", "70" => "70", "80" => "80", "100" => "100", "103" => "103", "1000" => "1000");
    }

    $allstates_status = $order_last_log['status']['id'];

    if ($allstates_status > $ARRAY_ESTADOS_ENCOMENDAS[10] && $allstates_status < $ARRAY_ESTADOS_ENCOMENDAS[50])
        $allstates_status = 40;

    if ($allstates_status > $ARRAY_ESTADOS_ENCOMENDAS[70] && $allstates_status < $ARRAY_ESTADOS_ENCOMENDAS[80])
        $allstates_status = 70;

    $allstates_status_label = utf8_decode($order_last_log['status']['nome']);

    $fulfillID   = array($ARRAY_ESTADOS_ENCOMENDAS[1], $ARRAY_ESTADOS_ENCOMENDAS[10], $ARRAY_ESTADOS_ENCOMENDAS[40], $ARRAY_ESTADOS_ENCOMENDAS[42], $ARRAY_ESTADOS_ENCOMENDAS[45], $ARRAY_ESTADOS_ENCOMENDAS[80]);
    $fulfillment_status       = "";
    $fulfillment_status_label = "";
    if (in_array($order_last_log['status']['id'], $fulfillID)) {
        $fulfillment_status       = $order_last_log['status']['id'];
        $fulfillment_status_label = $allstates_status_label;
    }
    
    $arr = array(
        "order_number"              => $order['id'],
        "order_number_encoder"      => base64_encode($order['id']),
        "name"                      => $order['order_ref'],
        "quantity"                  => $order['qtd'],
        "email"                     => $order['email_cliente'],
        "created_at"                => $order['datahora'],
        "date"                      => date("d-m-Y", strtotime($order['datahora'])),
        "customer"                  => $order['cliente_final'],
        "discounts"                 => $discounts_arr,
        "logs"                      => getAllOrderChangesFromBDI($order['id'], $bdi_connection),
        "client_cod_erp"            => $order['client_erp_code'],
        "billing_address"           => $order['billing_arr'],
        "shipping_address"          => $order['shipping_arr'],
        "shipping_price"            => call_api_func('OBJ_money', $order['portes'], $currency['id']),
        "subtotal_amount"           => call_api_func('OBJ_money', $order['valor']-$order['portes']-$order['imposto']-$order['valor_credito']-$order['custo_pagamento']+$valor_vale_de_desconto, $currency['id']),        
        "imposto"                   => call_api_func('OBJ_money', $order['imposto'], $currency['id']),
        "total_price"               => call_api_func('OBJ_money', $order['valor'], $currency['id']),
        "total_price_credit"        => call_api_func('OBJ_money', $order['valor_credito'], $currency['id']),
        "total_price_order"         => call_api_func('OBJ_money', $order['valor']+$order['valor_credito']-$order['desconto_modalidade_pagamento'], $currency['id']),
        "total_payment_tax"         => call_api_func('OBJ_money', $order['custo_pagamento'], $currency['id']),
        "subtotal_b2b_amount"       => call_api_func('OBJ_money', $total_prods_sem_iva, $currency['id']),
        "tax_amount"                => call_api_func('OBJ_money', $order['iva_valor'], $currency['id']),
        "transactions"              => $transactionsOBJ,
        "fulfillment"               => $fulfillmentOBJ,
        "obs"                       => $order['obs'],
        "po_number"                 => $order['po_number'],
        "delivery_date"             => $order['data_entrega'],
        "percentage_part"           => $order['percentagem_parcial'],
        "previous_value"            => call_api_func('OBJ_money',$order['valor_anterior'], $currency['id']),
        "bdi"                       => $order_last_log['site_origin'] ? 0 : 1,
        "fulfillment_status"        => $fulfillment_status,
        "fulfillment_status_label"  => $fulfillment_status_label
    );
    
    return $arr;
}

function getOrderChangeLogInfo($order_id, $bdi_connection){
    
    $sql = "SELECT TOP 1 * FROM [ENCOMENDAS_ALTERACOES_ERP] WHERE [EncomendaID]='".$order_id."' ORDER BY [SysSiteUpdate] DESC";
    $res = sqlsrv_query($bdi_connection, $sql);
    $change_log_info = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    
    if(empty($change_log_info)){
        return [];
    }
    
    $status_info = call_api_func('getStatusInfoByERPCode', $change_log_info['EstadoEncomendaErpCode'], $bdi_connection);
    
    return [
        'status'           => $status_info,
        'tracking_factura' => $change_log_info['DocumentoNome'],
        'tracking_number'  => $change_log_info['TrackingNumber'],
        'site_origin'      => $change_log_info['Origem'] == 'SITE'
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

function getAllOrderChangesFromBDI($order_id, $bdi_connection){

    global $LG;

    switch($LG){
        case 'sp':
            $lang = 'es';break;
        case 'gb':
            $lang = 'en';break;
        default:
            $lang = $LG;
    }

    $sql = "SELECT [ESTADOS_ENCOMENDAS_DEVOLUCOES].[EstadoID] AS [id], 
                [ESTADOS_ENCOMENDAS_DEVOLUCOES].[Designacao".strtoupper($lang)."] AS [nome], 
                [ENCOMENDAS_ALTERACOES_ERP].[SysSiteUpdate] AS [datahora]
            FROM [ENCOMENDAS_ALTERACOES_ERP] WITH (NOLOCK)
                JOIN [ESTADOS_ENCOMENDAS_DEVOLUCOES] ON [ESTADOS_ENCOMENDAS_DEVOLUCOES].[EstadoErpCode]=[ENCOMENDAS_ALTERACOES_ERP].[EstadoEncomendaErpCode]
            WHERE [EncomendaID]='".$order_id."'
            ORDER BY [SysSiteUpdate]";
    
    $logs = [];
    $res  = sqlsrv_query($bdi_connection, $sql);

    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){
        $row = array_map("utf8_decode", $row);
        $logs[] = array(
            "id"    => md5($row["id"].$row["datahora"]),
            "data"  => $row["datahora"],
            "autor" => '',
            "desc"  => utf8_encode($row["nome"]),
            "obs"   => $row["obs"]
        );
    }

    return $logs;

}

function getOrderLinesFromBDI($order_id, $currency, $bdi_connection, &$discounts_arr=[], &$total_prods_sem_iva=0){

    global $pagetitle, $CONFIG_IMAGE_SIZE;

    $sql = "SELECT [Valor] AS [valoruni_com_desconto],
                [DescontoCampanha] AS [campanha_valoruni],
                [DescontoCampanhaCode] AS [campanha_code],
                [IVAValor] AS [iva_valoruni],
                [IVATaxa] AS [taxa_iva],
                [Quantidade] AS [qnt_total]
            FROM [ENCOMENDAS_LINHAS] WITH (NOLOCK)
            WHERE [EncomendaID]='".$order_id."'
                AND [Quantidade]>0
                AND [SkuFamily] != '' 
                AND [SkuGroup] != ''
            ORDER BY [SkuFamily]";

    $_arr_lines = array();
    $res = sqlsrv_query($bdi_connection, $sql);
    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){

        $row = array_map("utf8_decode", $row);

        if( trim($row['campanha_code']) != '' ){
            $campanha_valor_sem_iva = $row['campanha_valoruni'] / (($row['taxa_iva']/100)+1);
            if( $campanha_valor_sem_iva > 0 ){
                if( !isset($discounts_arr[$row['campanha_code']]) ){
                    $discounts_arr[$row['campanha_code']] = array(
                        "cod_voucher" => $row['campanha_code'],
                        "amount"      => $campanha_valor_sem_iva * $row['qnt_total']
                    );
                }else{
                    $discounts_arr[$row['campanha_code']]['amount'] += $campanha_valor_sem_iva * $row['qnt_total'];
                }
            }
        }else{
            $campanha_valor_sem_iva = 0;
        }

        $total_prods_sem_iva += ( $row['valoruni_com_desconto'] + $campanha_valor_sem_iva - $row['iva_valoruni'] ) * $row['qnt_total'];

    }

    foreach($discounts_arr as &$value){
        $value['amount'] = call_api_func('OBJ_money', $value['amount'], $currency['id']);
    }

}

function getAllOrderPaymentsFromBDI($order_id, $bdi_connection, $currency){

    $sql = "SELECT [ENCOMENDAS_PAGAMENTOS].[PagamentoMetodoSiteID] AS [pagamento_id],
                [ENCOMENDAS_PAGAMENTOS].[PagamentoMetodo] AS [pagamento_text],
                [ENCOMENDAS_PAGAMENTOS].[PagamentoValor] AS [pagamento_valor],
                [ENCOMENDAS_PAGAMENTOS].[PagamentoDataHora] AS [pagamento_data],
                [ENCOMENDAS_PAGAMENTOS].[PagamentoCode] AS [pagamento_token]
            FROM [ENCOMENDAS_PAGAMENTOS] WITH (NOLOCK)
            WHERE [EncomendaID]='".$order_id."'
            ORDER BY [PagamentoCode]";
            
    $transactions = [];
    $res  = sqlsrv_query($bdi_connection, $sql);

    while($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)){
        $row = array_map("utf8_decode", $row);
        $transactions[] = array(
            "id"            => trim($row['pagamento_token']) != '' ? $row['pagamento_token'] : $order_id,
            "amount"        => call_api_func('OBJ_money', $row['pagamento_valor'], $currency['id']),
            "created_at"    => $row["pagamento_data"],
            "gateway"       => utf8_encode($row["pagamento_text"]),
            "gateway_id"    => $row['pagamento_id']
        );
    }

    return $transactions;

}

?>
