<?

function _getAccountOrderLinesFromBDI($order_id=null, $grouped=null){

    if(is_null($order_id)){
        $order_id   = params('order_id');
        $grouped    = params('grouped');
    }

    $return_arr = [];

    $order_id   = (int)$order_id;
    $grouped    = (int)$grouped;

    if( $order_id <= 0 ){
        $return_arr['orders'] = [];
        return serialize($return_arr);
    }

    require_once _ROOT."/api/api_external_functions.php";
    $bdi_connection = returnBDiConnection();
    if( empty($bdi_connection) ){
        $return_arr['lines'] = [];
        return serialize($return_arr);
    }

    global $userID;
    global $CONFIG_OPTIONS;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    $user = cms_fetch_assoc( cms_query("SELECT `cod_erp` FROM `_tusers` WHERE `id`='".$userOriginalID."'") );
    
    $sql = "SELECT TOP 1 [ENCOMENDAS].[EncomendaID] AS [id],
                [ENCOMENDAS].[MoedaCodeISO4217] AS [moeda_sigla]
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
    $order = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

    if( (int)$order['id'] <= 0 ){
        $return_arr['lines'] = [];
        return serialize($return_arr);
    }

    $currency = call_api_func('get_line_table', 'ec_moedas', "`codigo`='".$order['moeda_sigla']."' AND `activo`=1");

    $lines                  = [];
    $discounts_arr          = [];
    $total_prods_sem_iva    = 0;
    $lines                  = getOrderLinesFromBDI($order['id'], $currency, $bdi_connection, $discounts_arr, $total_prods_sem_iva, $grouped);
    $fromBDI                = checkIfAllProductsAreFromSite($lines) ? 0 : 1;
    
    $return_arr['lines'] = $lines;
    return serialize($return_arr);

}

function getOrderLinesFromBDI($order_id, $currency, $bdi_connection, &$discounts_arr=[], &$total_prods_sem_iva=0, $grouped){

    global $pagetitle, $CONFIG_IMAGE_SIZE, $CONFIG_OPTIONS;

    $sql = "SELECT [Sku] AS [ref],
                [SkuGroup] AS [sku_group],
                [SkuFamily] AS [sku_family],
                [ArtigoNome] AS [nome],
                [ArtigoCor] AS [color_name],
                [ArtigoTamanho] AS [size_name],
                ([Valor]+[DescontoCampanha]) AS [valoruni],
                [Valor] AS [valoruni_com_desconto],
                [ValorBase] AS [valoruni_anterior],
                [DescontoPromo] AS [valoruni_desconto],
                [DescontoCampanha] AS [campanha_valoruni],
                [DescontoCampanhaCode] AS [campanha_code],
                [PVRValor] AS [pvpr],
                [PVRValor] AS [pvr],
                [PVRValorDesconto] AS [pvr_desconto],
                [IVAValor] AS [iva_valoruni],
                [IVATaxa] AS [taxa_iva],
                [Quantidade] AS [qnt_total],
                [ErpQuantidadeConfirmada] AS [qnt_confirmada],
                [ErpQuantidadeFaturada] AS [qnt_faturada],
                [Origem] AS [origem],
                [Markup] AS [markup],
                [InformacaoComplementar] AS [composition]
            FROM [ENCOMENDAS_LINHAS] WITH (NOLOCK)
            WHERE [EncomendaID]='".$order_id."'
                AND ( [Quantidade]>0 OR [ErpQuantidadeConfirmada]>0 )
                AND [SkuFamily] != '' 
                AND [SkuGroup] != ''
            ORDER BY [SkuFamily], [ArtigoCor], [Sku], [EncomendaLinhaID]";

    if( $grouped ){

        $sql = "SELECT MIN([Sku]) AS [ref],
                    MIN([SkuGroup]) AS [sku_group],
                    [SkuFamily] AS [sku_family],
                    MIN([ArtigoNome]) AS [nome],
                    [ArtigoCor] AS [color_name],
                    MAX([Valor]+[DescontoCampanha]) AS [valoruni],
                    MAX([Valor]) AS [valoruni_com_desconto],
                    MAX([ValorBase]) AS [valoruni_anterior],
                    MAX([DescontoPromo]) AS [valoruni_desconto],
                    MAX([DescontoCampanha]) AS [campanha_valoruni],
                    MAX([DescontoCampanhaCode]) AS [campanha_code],
                    MAX([PVRValor]) AS [pvpr],
                    MAX([PVRValor]) AS [pvr],
                    MAX([PVRValorDesconto]) AS [pvr_desconto],
                    MAX([IVAValor]) AS [iva_valoruni],
                    MAX([IVATaxa]) AS [taxa_iva],
                    SUM([Quantidade]) AS [qnt_total],
                    SUM([ErpQuantidadeConfirmada]) AS [qnt_confirmada],
                    SUM([ErpQuantidadeFaturada]) AS [qnt_faturada],
                    MIN([Origem]) AS [origem],
                    MAX([Markup]) AS [markup],
                    MIN([Valor]+[DescontoCampanha]) AS [valoruni_min], 
                    MAX([Valor]+[DescontoCampanha]) AS [valoruni_max],
                    MIN([InformacaoComplementar]) AS [composition]
                FROM [ENCOMENDAS_LINHAS] WITH (NOLOCK)
                WHERE [EncomendaID]='".$order_id."'
                    AND ( [Quantidade]>0 OR [ErpQuantidadeConfirmada]>0 )
                    AND [SkuFamily] != '' 
                    AND [SkuGroup] != ''
                GROUP BY [SkuFamily], [ArtigoCor], [ValorBase]
                ORDER BY [SkuFamily], [ArtigoCor]";

    }

    $_arr_lines = array();
    
    $res = sqlsrv_query($bdi_connection, $sql);
    while( $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC) ){

        $row = array_map("utf8_decode", $row);
        
        if( number_format($row['valoruni_min'], 2, '.', '') != number_format($row['valoruni_max'], 2, '.', '') ){
            $row['valoruni_com_desconto']   = 0;
            $row['valoruni_anterior']       = 0;
            $row['valoruni_desconto']       = 0;
            $row['campanha_valoruni']       = 0;
            $row['pvpr']                    = 0;
            $row['pvr']                     = 0;
            $row['pvr_desconto']            = 0;
            $row['iva_valoruni']            = 0;
        }
        
        $b2b_discount_price_perc = 0;
        $b2b_discount_price      = 0;
        $b2b_pvr_discount_perc   = 0;
        $Decimais                = (int)$currency['moeda_decimais'] > 0 ? (int)$currency['moeda_decimais'] : 2;

        $row['valoruni_anterior_sem_iva'] = $row['valoruni_anterior'] / (($row['taxa_iva']/100)+1);
        $row['valoruni_sem_iva']          = $row['valoruni'] / (($row['taxa_iva']/100)+1);
        $row['pvr_sem_iva']               = $row['pvr'] / (($row['taxa_iva']/100)+1);
        $row['pvr_desconto_sem_iva']      = $row['pvr_desconto'] / (($row['taxa_iva']/100)+1);
        $row['pvpr_sem_iva']              = $row['pvpr'] / (($row['taxa_iva']/100)+1);

        if( trim($row['campanha_code']) != '' ){
            $campanha_valor_sem_iva = $row['campanha_valoruni'] / (($row['taxa_iva']/100)+1);
            if( !isset($discounts_arr[$row['campanha_code']]) ){
                $discounts_arr[$row['campanha_code']] = array(
                    "cod_voucher" => $row['campanha_code'],
                    "amount"      => $campanha_valor_sem_iva * $row['qnt_total']
                );
            }else{
                $discounts_arr[$row['campanha_code']]['amount'] += $campanha_valor_sem_iva * $row['qnt_total'];
            }
        }else{
            $campanha_valor_sem_iva = 0;
        }

        $total_prods_sem_iva += ( $row['valoruni_com_desconto'] + $campanha_valor_sem_iva - $row['iva_valoruni'] ) * $row['qnt_total'];
        

        if($row['valoruni_anterior_sem_iva']>0) {
            $b2b_discount_price = $row["valoruni_anterior_sem_iva"]-$row['valoruni_sem_iva'];
            
            $temp_b2b_discount_price_perc = number_format( 100-(((float)$row['valoruni_sem_iva']*100)/$row['valoruni_anterior_sem_iva']), 2, ".", "");
            if(fmod($temp_b2b_discount_price_perc, 1) === 0.00){
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
            }else{
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $Decimais, '.', '');
            }
        }

        
        if($row['pvr_desconto_sem_iva']>0) {
            $temp_b2b_pvr_discount_perc = number_format( (((float)$row['pvr_desconto_sem_iva']*100)/$row['pvr_sem_iva']), 2, ".", "");
            
            if(fmod($temp_b2b_pvr_discount_perc, 1) === 0.00){
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            }else{
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $Decimais, '.', '');          
            } 
        }

        require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getSingleImage.php';
        // list($width, $height, $crop) = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['thumb']);
        $width = 160;
        $height = 160;
        $crop = 3;
        $imagem = _getSingleImage($width, $height, $crop, $row['ref'], 1);

        $markup = 0;
        if( (int)$row['pvr'] > 0 && (int)$CONFIG_OPTIONS['layout_list_markup'] == 1 ){
            $markup = $row['markup'];
        }

        $prod_quantity = $row['qnt_total'];
        if( $row['qnt_total'] == 0 && $row['qnt_confirmada'] > 0 ){
            $prod_quantity = $row['qnt_confirmada'];
        }

        # ------------- ORDER OBJECT -------------
        $_arr_lines[] = [
            "id"                    => 0,
            "egift"                 => 0,
            "product"               => [],
            "variant"               => [],
            "title"                 => $row['nome'],
            "color_name"            => $row['color_name'],
            "size_name"             => $row['size_name'],
            "composition"           => $row['composition'],
            "image"                 => $imagem,
            "price"                 => call_api_func('OBJ_money', $row['valoruni']-$row['desconto'], $currency['id']),
            "line_price"            => call_api_func('OBJ_money', ($row['valoruni']-$row['desconto'])*$prod_quantity, $currency['id']),
            "old_price"             => call_api_func('OBJ_money', $row['valoruni'], $currency['id']), #antes de descontos de cupões
            "previous_price"        => call_api_func('OBJ_money', $row['valoruni_anterior'], $currency['id']), #antes de promos
            
            "b2b_base_price"        => call_api_func('OBJ_money', $row['valoruni_anterior_sem_iva'], $currency['id']),
            "b2b_discount_price"    => call_api_func('OBJ_money', $b2b_discount_price, $currency['id']),
            "b2b_price"             => call_api_func('OBJ_money', $row['valoruni_sem_iva'], $currency['id']),
            
            "b2b_discount_perc"     => str_replace("-", "", $b2b_discount_price_perc)."%",
            "b2b_campaign_price"    => call_api_func('OBJ_money', $row['valoruni_desconto_sem_iva']*$prod_quantity, $currency['id']),
            "b2b_line_price"        => call_api_func('OBJ_money', ($row['valoruni_sem_iva'])*$prod_quantity, $currency['id']),
            
            "b2b_pvr"               => call_api_func('OBJ_money', $row['pvr_sem_iva'], $currency['id']),
            "b2b_pvr_discount"      => call_api_func('OBJ_money', $row['pvr_desconto_sem_iva'], $currency['id']),
            "b2b_pvr_discount_perc" => str_replace("-", "", $b2b_pvr_discount_perc."%"),
            
            
            "quantity"              => $row['qnt_total'],
            "confirmed_quantity"    => $row['qnt_confirmada'],
            "billed_quantity"       => $row['qnt_faturada'],
            "pending_quantity"      => $row['qnt_confirmada'] - $row['qnt_faturada'],
            "grams"                 => 1,
            "sku"                   => $row['ref'],
            "sku_group"             => $row['sku_group'],
            "sku_family"            => $row['sku_family'],
            "vendor"                => $pagetitle,
            "requires_shipping"     => 1,
            "variant_id"            => 0,
            "product_id"            => 0,
            "discount"              => [],
            "review_made"           => 0,
            "points"                => 0,
            "service"               => [],
            "tracking_code"         => '',
            "data_line"             => [],
            "boxes"                 => 0,
            "pack_id"               => 0,
            "value_additional"      => call_api_func('OBJ_money', 0, $currency['id']),
            "prime"                 => 0,
            "origem"                => getOrigem($row['origem']),
            "markup"                => $markup,
            "vat"                   => number_format($row['taxa_iva'], 0, '.', '')."%",
            "ref"                   => ($grouped) ? $row['sku_group'] : $row['ref'],
            "b2b_pvpr"              => call_api_func('OBJ_money', $row['pvpr'], $currency['id'])
        ];
        # --------------------------
        
    }
    
    foreach($discounts_arr as &$value){
        $value['amount'] = call_api_func('OBJ_money', $value['amount'], $currency['id']);
    }

    return $_arr_lines;

}

function getOrigem($origem){

    $origem_key = 0;

    switch(strtoupper($origem)){
        case 'SITE': $origem_key = 1;break;
        default: $origem_key = 2;break;
    }

    return $origem_key;

}

function checkIfAllProductsAreFromSite($lines){

    $origem_values_found = array_count_values(array_column($lines, 'origem')); # Counts all the different values for the position "origem". Return an array with the values as key and how many times he appears on the array
    
    return $origem_values_found[1] == count($lines); # If there number of values found is equal to the number of lines, then all the lines have the same value.

}

?>
