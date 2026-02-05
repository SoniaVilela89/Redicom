<?

define('REPORT_ORDER_LINES', 1);
define('REPORT_OVERDUE_INVOICES', 10);
define('REPORT_CREDIT_CONDITIONS', 11);
define('REPORT_DELIVERIES_SUMMARY', 12);

function _getAccountReportFromBDI($account_page_id=null, $export_to_excel=null, $order_id=null){
    
    if( is_null($account_page_id) ){
        $account_page_id    = params('account_page_id');
        $export_to_excel    = params('export_to_excel');
        $order_id           = params('order_id');
    }
    
    $return_arr         = [];

    $account_page_id    = (int)$account_page_id;
    $export_to_excel    = (int)$export_to_excel;
    $order_id           = (int)$order_id;
    
    if( $account_page_id <= 0 ){
        $return_arr['report'] = [];
        return serialize($return_arr);
    }
    
    require_once _ROOT."/api/api_external_functions.php";
    $bdi_connection = returnBDiConnection();
    if( empty($bdi_connection) ){
        $return_arr['report'] = [];
        return serialize($return_arr);
    }
    
    if( ( (int)$_SESSION['EC_USER']['type'] != 1 && $account_page_id != 1 ) || (int)$_SESSION['EC_USER']['id'] <= 0 ){
        $return_arr['report'] = [];
        return serialize($return_arr);
    }

    global $userID, $CONFIG_OPTIONS, $COUNTRY;

    if( $account_page_id == 1 ){

        $userOriginalID = $userID;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            $userOriginalID = $_SESSION['EC_USER']['id_original'];
        }

        $user = cms_fetch_assoc( cms_query("SELECT `cod_erp` FROM `_tusers` WHERE `id`='".$userOriginalID."'") );

    }else{
        $seller = cms_fetch_assoc( cms_query("SELECT `cod_erp` FROM `_tusers_sales` WHERE `id`='".$userID."'") );
    }

    $language = call_api_func('get_line_table', 'ec_language', "`id`='".$COUNTRY['idioma']."'");
    $language_code = strtoupper($language['code']);
    
    if( is_callable('custom_controller_account_report_bdi') ) {
        return call_user_func('custom_controller_account_report_bdi', $bdi_connection, $account_page_id, $export_to_excel, $order_id, $user['cod_erp'], $seller['cod_erp'], $language_code);
    }

    switch($account_page_id){
        case REPORT_OVERDUE_INVOICES:
            $report_data = getOverdueInvoicesFromBDI($bdi_connection, $seller['cod_erp'], $language_code);
            $export_file_name = "overdue_invoices"; 
            break;
        case REPORT_CREDIT_CONDITIONS:
            $report_data = getCreditConditionsFromBDI($bdi_connection, $seller['cod_erp'], $language_code);
            $export_file_name = "credit_conditions"; 
            break;
        case REPORT_DELIVERIES_SUMMARY:
            $report_data = getDeliveriesSummaryFromBDI($bdi_connection, $seller['cod_erp'], $language_code);
            $export_file_name = "deliveries_summary"; 
            break;
        case REPORT_ORDER_LINES:
            $report_data = getOrderLinesFromBDI($bdi_connection, $user['cod_erp'], $language_code, $order_id, $userID);
            $export_file_name = "order_".$order_id; 
    }
    
    $return_arr['clients'] = array();
    $return_arr['payment_methods'] = array();
    $return_arr['payment_conditions'] = array();
    foreach($report_data as $k => $v){
        if(isset($v["client"]["erp_code"])) $return_arr['clients'][$v["client"]["erp_code"]] = $v["client"]["erp_code"]." - ".$v["client"]["name"];
        if(isset($v["client"]["credit_condition"])) $return_arr['payment_conditions'][$v["client"]["credit_condition"]] = $v["client"]["credit_condition"];
        if(isset($v["payment_method"])) $return_arr['payment_methods'][$v["payment_method"]] = $v["payment_method"];
    }

    $return_arr['clients'] = array_values($return_arr['clients']);
    $return_arr['payment_methods'] = array_values($return_arr['payment_methods']);
    $return_arr['payment_conditions'] = array_values($return_arr['payment_conditions']);

    $return_arr['report'] = $report_data;

    if( $export_to_excel ){

        $report_data = prepare_report_data($account_page_id, $report_data);

        exportReportToXls($report_data, $export_file_name);
        exit;
        
    }
    
    return serialize($return_arr);

}

function getOverdueInvoicesFromBDI($bdi_connection, $seller_erp_code, $language_code){

    global $MOEDA;
    
    $arr_invoices = array();
    
    $sql = "SELECT [CLIENTES].[ErpCode],
                [CLIENTES].[EmpresaNome],
                [CLIENTES].[RestricaoEncomenda],
                [DOCUMENTOS_FINANCEIROS].[DocumentoRef],
                [DOCUMENTOS_FINANCEIROS].[DataVencimento],
                [DOCUMENTOS_FINANCEIROS].[ValorDocumento],
                [DOCUMENTOS_FINANCEIROS].[ValorLiquidado]
            FROM [DOCUMENTOS_FINANCEIROS]
                INNER JOIN [CLIENTES] ON [DOCUMENTOS_FINANCEIROS].[ClienteErpCode] = [CLIENTES].[ErpCode] 
                    AND [CLIENTES].[VendedorErpCode] = '".$seller_erp_code. "'
            WHERE [DOCUMENTOS_FINANCEIROS].[DataVencimento] < GETDATE()
                AND YEAR([DOCUMENTOS_FINANCEIROS].[DataVencimento]) >= YEAR(GETDATE())-1
                AND ([DOCUMENTOS_FINANCEIROS].[ValorDocumento]-[DOCUMENTOS_FINANCEIROS].[ValorLiquidado])>0
            ORDER BY [DOCUMENTOS_FINANCEIROS].[DataVencimento] DESC";
    
    $res = sqlsrv_query($bdi_connection, $sql);
    while( $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) ){

        $row['ValorVencido']    = $row['ValorDocumento'] - $row['ValorLiquidado'];
        $row['ValorVencido']    = number_format($row['ValorVencido'], $MOEDA['decimais'], ".", "");
        $row['ValorDocumento']  = number_format($row['ValorDocumento'], $MOEDA['decimais'], ".", "");
        
        $arr_invoices[] = [
            "client"            => [
                "erp_code"  => $row['ErpCode'],
                "name"      => $row['EmpresaNome'],
                "blocked"   => getUserBlockedExpression($row)
            ],
            "doc_ref"           => $row['DocumentoRef'],
            "due_date"          => $row['DataVencimento'],
            "amount"            => call_api_func('OBJ_money', $row['ValorDocumento'], $MOEDA['id']),
            "overdue_amount"    => call_api_func('OBJ_money', $row['ValorVencido'], $MOEDA['id'])
        ];
        
    }

    return $arr_invoices;

}

function getCreditConditionsFromBDI($bdi_connection, $seller_erp_code, $language_code){

    global $MOEDA;

    $arr_conditions = array();
    
    $sql = "SELECT [CLIENTES].[ErpCode],
                [CLIENTES].[EmpresaNome],
                [CLIENTES].[RestricaoEncomenda],
                [CLIENTES].[PlafondLimite],
                [CLIENTES_MODALIDADES_PAGAMENTO].[Designacao".$language_code."] AS [Designacao]
            FROM [CLIENTES]
                LEFT JOIN [CLIENTES_MODALIDADES_PAGAMENTO] ON [CLIENTES].[ModalidadePagamentoErpCode] = [CLIENTES_MODALIDADES_PAGAMENTO].[ErpCode]
            WHERE [CLIENTES].[VendedorErpCode] = '".$seller_erp_code."'
            ORDER BY [CLIENTES].[ErpCode]";

    $res = sqlsrv_query($bdi_connection, $sql);
    while( $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) ){
        
        $row['PlafondLimite'] = number_format($row['PlafondLimite'], $MOEDA['decimais'], ".", "");

        $arr_conditions[] = [
            "client" => [    
                "erp_code"          => $row['ErpCode'],
                "name"              => $row['EmpresaNome'],
                "blocked"           => getUserBlockedExpression($row),
                "credit_condition"  => $row['Designacao'],
                "credit_limit"      => call_api_func('OBJ_money', $row['PlafondLimite'], $MOEDA['id'])
            ]
        ];
        
    }

    return $arr_conditions;

}

function getDeliveriesSummaryFromBDI($bdi_connection, $seller_erp_code, $language_code){

    $arr_orders = array();
    
    $sql = "SELECT [CLIENTES].[ErpCode],
                [CLIENTES].[EmpresaNome],
                [CLIENTES].[RestricaoEncomenda],
                [ENCOMENDAS].[EncomendaID],
                [ENCOMENDAS].[EncomendaRef],
                [ENCOMENDAS].[DataHora],
                [ENCOMENDAS].[ValorTotal],
                [ENCOMENDAS].[MoedaCodeISO4217] AS [moeda_sigla],
                [ENCOMENDAS_PAGAMENTOS].[PagamentoMetodo]
            FROM [ENCOMENDAS]
                INNER JOIN [CLIENTES] ON [ENCOMENDAS].[ClienteErpCode] = [CLIENTES].[ErpCode]
                    AND [CLIENTES].[VendedorErpCode] = '".$seller_erp_code."'
                LEFT JOIN [ENCOMENDAS_PAGAMENTOS] ON [ENCOMENDAS].[EncomendaID] = [ENCOMENDAS_PAGAMENTOS].[EncomendaID]
            ORDER BY [ENCOMENDAS].[DataHora] DESC";
    
    $res = sqlsrv_query($bdi_connection, $sql);
    while( $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) ){

        $currency = call_api_func('get_line_table', 'ec_moedas', "`codigo`='".$row['moeda_sigla']."'");

        $sql_lines = "SELECT SUM( [Valor] * [Quantidade] ) AS [ValorInicial],
                            SUM( [Valor] * [ErpQuantidadeConfirmada] ) AS [ValorAtual],
                            SUM( [Valor] * [ErpQuantidadeFaturada] ) AS [ValorFaturado]
                        FROM [ENCOMENDAS_LINHAS]
                        WHERE [EncomendaID]='".$row['EncomendaID']."'";

        $row_lines = sqlsrv_fetch_array( sqlsrv_query($bdi_connection, $sql_lines) , SQLSRV_FETCH_ASSOC);

        $row_lines['ValorFaturadoPerc'] = (int)( ( $row_lines['ValorFaturado'] * 100 )/$row_lines['ValorAtual'] )."%";
        
        $row_lines['ValorAtual']      = number_format($row_lines['ValorAtual'], $currency['decimais'], ".", "");
        $row_lines['ValorFaturado']   = number_format($row_lines['ValorFaturado'], $currency['decimais'], ".", "");

        $arr_orders[] = [
            "client"                        => [
                "erp_code"  => $row['ErpCode'],
                "name"      => $row['EmpresaNome'],
                "blocked"   => getUserBlockedExpression($row)
            ],
            "doc_ref"                       => $row['EncomendaRef'],
            "date"                          => date( "Y-m-d", strtotime($row['DataHora']) ),
            "initial_amount"                => call_api_func('OBJ_money', $row_lines['ValorInicial'], $currency['id']),
            "actual_amount"                 => call_api_func('OBJ_money', $row_lines['ValorAtual'], $currency['id']),
            "invoiced_amount"               => call_api_func('OBJ_money', $row_lines['ValorFaturado'], $currency['id']),
            "invoiced_amount_percentage"    => $row_lines['ValorFaturadoPerc'],
            "payment_method"                => $row['PagamentoMetodo']
        ];
        
    }

    return $arr_orders;

}

function getUserBlockedExpression($arr_user){
    return ( (int)$arr_user['RestricaoEncomenda'] != 2 ) ? call_api_func("estr", 180) : call_api_func("estr", 179);
}

function exportReportToXls($report_data, $file_name){

    if( trim($file_name) == "" ){
        $file_name = "report";
    }
    $file_name .= "_".date("Y-m-d");
    
    require_once _ROOT.'/api/lib/Classes/PHPExcel.php';
    require_once _ROOT.'/api/lib/Classes/PHPExcel/IOFactory.php';

    $objPHPExcel = new PHPExcel();
    $objPHPExcel->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    $sheet = $objPHPExcel->setActiveSheetIndex(0);

    $sheet->getRowDimension(1)->setRowHeight(20);
    $sheet->getStyle('1:1')->getFont()->setBold(true);

    $row = 1;
    foreach($report_data as $kr => $vr ){
        $col = 0;
        foreach( $vr as $k => $v ){
            # Alterado para considerar os zeros à esquerda
            #$sheet->setCellValueByColumnAndRow($col, $row, utf8_encode($v));
            $sheet->setCellValueExplicitByColumnAndRow($col, $row, utf8_encode($v), PHPExcel_Cell_DataType::TYPE_STRING);
            $col++;
        }
        $row++;
    }

    # Auto size columns
    $cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(true);
    foreach ($cellIterator as $cell) {
        $sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
    }
    # Auto size columns

    ob_clean();
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment;filename=\"$file_name\".xls");
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');

}

function prepare_report_data($account_page_id, $report_data){

    $report_excel_data = array();

    switch($account_page_id){
        case REPORT_OVERDUE_INVOICES:
            $report_excel_data[] = [estr2(203),estr2(932),estr2(725),estr2(920),estr2(930),estr2(926),estr2(865)];
            foreach( $report_data as $k => $v ){
                
                $report_excel_data[] = [
                    $v['doc_ref'],
                    $v['client']['erp_code'],
                    $v['client']['name'],
                    $v['client']['blocked'],
                    $v['amount']['value'],
                    $v['overdue_amount']['value'],
                    $v['due_date']
                ];
        
            }
            break;
        case REPORT_CREDIT_CONDITIONS:
            $report_excel_data[] = [estr2(932),estr2(725),estr2(631),estr2(925),estr2(920)];
            foreach( $report_data as $k => $v ){
                
                $report_excel_data[] = [
                    $v['client']['erp_code'],
                    $v['client']['name'],
                    $v['client']['credit_condition'],
                    $v['client']['credit_limit']['value'],
                    $v['client']['blocked']
                ];
        
            }
            break;
        case REPORT_DELIVERIES_SUMMARY:
            $report_excel_data[] = [estr2(205),estr2(932),estr2(725),estr2(920),estr2(631),estr2(358),estr2(921),estr2(922),estr2(923),estr2(924)];
            foreach( $report_data as $k => $v ){
                
                $report_excel_data[] = [
                    $v['doc_ref'],
                    $v['client']['erp_code'],
                    $v['client']['name'],
                    $v['client']['blocked'],
                    $v['client']['credit_condition'],
                    $v['date'],
                    $v['initial_amount']['value'],
                    $v['actual_amount']['value'],
                    $v['invoiced_amount']['value'],
                    $v['invoiced_amount_percentage']
                ];
        
            }
            break;
        case REPORT_ORDER_LINES:
            $report_excel_data[] = [estr2(945),estr2(93),estr2(266),estr2(208),estr2(946),estr2(209),estr2(108),estr2(192),estr2(947),estr2(948),
                estr2(949),estr2(950),estr2(951),estr2(210),estr2(952)
            ];
            foreach( $report_data as $k => $v ){
                
                $report_excel_data[] = [
                    $v['bar_code'],
                    $v['sku'],
                    $v['title'],
                    $v['color'],
                    $v['color_name'],
                    $v['size'],
                    $v['quantity'],
                    $v['gender'],
                    $v['family'],
                    $v['made_in'],
                    $v['hs_code'],
                    $v['composition'],
                    $v['size_grid'],
                    $v['price']['value'],
                    $v['pvr']['value']
                ];
        
            }
            break;
    }

    return $report_excel_data;

}

function getOrderLinesFromBDI($bdi_connection, $user_erp_code, $language_code, $order_id, $user_id){

    $arr_lines = array();

    $sql = "SELECT TOP 1 [ENCOMENDAS].[EncomendaID] AS [id],
                [ENCOMENDAS].[MoedaCodeISO4217] AS [moeda_sigla]
            FROM [ENCOMENDAS] 
            WHERE [ENCOMENDAS].[EncomendaID]='" . $order_id . "' 
                AND ( 
                    ( [ENCOMENDAS].[ClienteErpCode]!='' 
                        AND [ENCOMENDAS].[ClienteErpCode]='".$user_erp_code."' 
                        AND [ENCOMENDAS].[Origem]!='SITE' 
                    ) OR 
                    ( [ENCOMENDAS].[ClienteSiteID]='".$user_id."' 
                        AND [ENCOMENDAS].[Origem]='SITE' 
                    ) 
                )
            ";
    
    $res = sqlsrv_query($bdi_connection, $sql);
    $order = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    
    if( (int)$order['id'] <= 0 ){
        return serialize($arr_lines);
    }

    $currency = call_api_func('get_line_table', 'ec_moedas', "`codigo`='".$order['moeda_sigla']."'");

    $sql = "SELECT [ARTIGOS].[Ean] AS [ean],
                [ARTIGOS].[SkuGroup] AS [sku_group],
                [ARTIGOS].[CorErpCode] AS [color_erp_code],
                [ENCOMENDAS_LINHAS].[Sku] AS [ref],
                [ENCOMENDAS_LINHAS].[ArtigoNome] AS [nome],
                [ENCOMENDAS_LINHAS].[ArtigoCorErpCode] AS [color],
                [ENCOMENDAS_LINHAS].[ArtigoCor] AS [color_name],
                [ENCOMENDAS_LINHAS].[ArtigoTamanho] AS [tamanho],
                [ENCOMENDAS_LINHAS].[Quantidade] AS [qnt_total],
                [ARTIGOS_GENEROS].[Designacao".$language_code."] AS [genero],
                [ARTIGOS_FAMILIAS].[Designacao".$language_code."] AS [familia],
                [ARTIGOS].[PaisOrigemISO3166] AS [made_in],
                [ARTIGOS].[HsCode] AS [hscode],
                [ENCOMENDAS_LINHAS].[InformacaoComplementar] AS [composition],
                [ENCOMENDAS_LINHAS].[Valor] AS [valoruni],
                [ENCOMENDAS_LINHAS].[PVRValor] AS [pvr]
            FROM [ENCOMENDAS_LINHAS]
                LEFT JOIN [ARTIGOS] ON [ENCOMENDAS_LINHAS].[Sku]=[ARTIGOS].[Sku]
                LEFT JOIN [ARTIGOS_FAMILIAS] ON [ARTIGOS].[FamiliaErpCode]=[ARTIGOS_FAMILIAS].[ErpCode]
				LEFT JOIN [ARTIGOS_GENEROS] ON [ARTIGOS].[GeneroErpCode]=[ARTIGOS_GENEROS].[ErpCode]
            WHERE [ENCOMENDAS_LINHAS].[EncomendaID]='".$order_id."'
            ORDER BY [ENCOMENDAS_LINHAS].[Sku]";
            
    $res = sqlsrv_query($bdi_connection, $sql);
    while( $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) ){
        
        $sql_size_grid = "SELECT STRING_AGG(TamanhoErpCode, ',') AS [size_grid]
                            FROM [ARTIGOS]
                            WHERE [SkuGroup]='".$row['sku_group']."' AND [CorErpCode]='".$row['color_erp_code']."'
                            GROUP BY [SkuFamily], [CorErpCode]";
        $res_size_grid = sqlsrv_query($bdi_connection, $sql_size_grid);
        $row_size_grid = sqlsrv_fetch_array($res_size_grid, SQLSRV_FETCH_ASSOC);
        
        # ------------- LINE OBJECT -------------
        $arr_lines[] = [
            "bar_code"              => $row['ean'],
            "sku"                   => $row['ref'],
            "title"                 => utf8_decode($row['nome']),
            "color"                 => $row['color'],
            "color_name"            => $row['color_name'],
            "size"                  => $row['tamanho'],
            "quantity"              => $row['qnt_total'],
            "gender"                => $row['genero'],
            "family"                => $row['familia'],
            "made_in"               => $row['made_in'],
            "hs_code"               => $row['hscode'],
            "composition"           => $row['composition'],
            "size_grid"             => $row_size_grid['size_grid'],
            "price"                 => call_api_func('OBJ_money', $row['valoruni'], $currency['id']),
            "pvr"                   => call_api_func('OBJ_money', $row['pvr'], $currency['id'])
        ];
        # --------------------------

    }

    return $arr_lines;

}

?>
