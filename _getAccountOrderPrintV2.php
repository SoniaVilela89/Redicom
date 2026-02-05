<?

function _getAccountOrderPrintV2($token = null, $view = null)
{

    global $eComm, $LG, $fx, $CONFIG_TEMPLATES_PARAMS, $MOEDA, $CONFIG_OPTIONS, $SETTINGS_LOJA, $B2B, $B2B_LAYOUT, $slocation;

    if (is_null($token)) {
        $token = (int)params('token');
        $view = (int)params('view');
    }

    $orderId = base64_decode($token);
    if ($orderId < 0) {
        $arr = array();
        $arr['0'] = 0;

        return serialize($arr);
    }

    $order = get_line_table("ec_encomendas", "id='" . $orderId . "'");

    if ((int)$order["id"] == 0) {
        $arr = array();
        $arr['0'] = 0;

        return serialize($arr);
    }

    # Pagamentos parciais
    if ((int)$order['percentagem_parcial'] > 0 && $order['valor_anterior'] > 0 && $order['tracking_status'] <= 1) {
        $orderValueAux           = $order['valor'];
        $order['valor']          = $order['valor_anterior'];
        $order['valor_anterior'] = $orderValueAux;
    }

    $client  = get_line_table("_tusers", "id='" . $order["cliente_final"] . "'");
    /*$clientCountry = get_line_table("ec_paises", "id='" . $client["pais"] . "'", "`idioma`");

    /*$language = 0;
    if ((int)$clientCountry["idioma"] > 0) {
        $language = $clientCountry['idioma'];
    }

    $languageInfo = get_line_table("ec_language", "id='" . $language . "'", "`code`");

    if ($languageInfo['code'] == "es") $languageInfo['code'] = "sp";
    if ($languageInfo['code'] == "en") $languageInfo['code'] = "gb";
    $LG = $languageInfo['code'];*/


    if ((int)$order['entidade_faturacao'] <= 0) {
        $market = cms_fetch_assoc(cms_query("SELECT `depositos_condicionados_ativo`, `entidade_faturacao` FROM `ec_mercado` WHERE `id`=" . $order['mercado_id']));
        $invoiceEntity = (int)$market['entidade_faturacao'];
    } else {
        $invoiceEntity = (int)$order['entidade_faturacao'];
    }

    $invoiceEntity = cms_fetch_assoc(cms_query("SELECT `id` FROM `ec_invoice_companies` WHERE `id`=" . $invoiceEntity));

    $logoImage = "sysimages/logo.png";
    if ((int)$invoiceEntity['id'] > 0) {
        $logoImage = "images/cab_" . $invoiceEntity['id'] . ".jpg?".filemtime($_SERVER['DOCUMENT_ROOT']."/images/cab_".$invoiceEntity['id'].".jpg");
    }

    # Coluna disponibilidade
    if( (int)$B2B>0 ){
        $property_s = "SELECT property_value FROM ec_encomendas_props WHERE order_id='".$orderId."' AND property='REGRAVSTOCK' LIMIT 0,1";
        $property_q = cms_query($property_s);
        $property_r = cms_fetch_assoc($property_q);

        if(is_null($market) || empty($market)){
            $market = cms_fetch_assoc(cms_query("SELECT `depositos_condicionados_ativo` FROM `ec_mercado` WHERE `id`=" . $order['mercado_id']));
        }

    }

    $show_availability = ( (int)$property_r['property_value'] == 2 || ( (int)$B2B>0 && (int)$property_r['property_value'] <= 0 && (int)$market['depositos_condicionados_ativo'] == 1 ) ) ? 1 : 0;
    # Coluna disponibilidade

    $templateInfo = array();
    $templateInfo['CONFIG_OPTIONS']             = $CONFIG_OPTIONS;
    
    if($templateInfo['CONFIG_OPTIONS']['hide_prices_promo_perc_val']==0) 
        $templateInfo['CONFIG_OPTIONS']['all_prices'] = 1;
    
    
    $templateInfo['b2b_style_version']          = (int)$B2B_LAYOUT['b2b_style_version'];
    $templateInfo["response"]["order_ref"]      = $order["order_ref"];
    $templateInfo["response"]["order_qtd"]      = $order["qtd"];
    $templateInfo["response"]["expressions"]    = get_expressions_checkout();

    $_GET['order']  = $order["id"];
    $templateInfo["response"]["order"]       = orderOBJ($order, 0, 1);

    $orderLines     = getOrderLinesPDF($orderId, 1);
    $subtotal       = 0;
    $iva            = 0;
    $seasonTitle    = "";
    $totalsByGender = array();
    $products       = array();
    $no_return      = 0;

    foreach ($orderLines as $v) {

        if ($v["id_gender"] != '') {
            if (isset($totalsByGender[$v["id_gender"]])) {
                $totalsByGender[$v["id_gender"]]["qtd"]   += $v["qnt_total"];
                $totalsByGender[$v["id_gender"]]["total"] += $v["qnt_total"] * $v['valoruni_sem_iva'];

                $totalsByGender[$v["id_gender"]]["label_price"] = call_api_func('OBJ_money', $totalsByGender[$v["id_gender"]]["total"], $MOEDA['id']);
            } else {
                $totalsByGender[$v["id_gender"]] = array(
                    "name"        => $v["name_gender"],
                    "qtd"         => $v["qnt_total"],
                    "total"       => $v['valoruni_sem_iva'] * $v["qnt_total"],
                    "label_price" => ""
                );

                $totalsByGender[$v["id_gender"]]["label_price"] = call_api_func('OBJ_money', $totalsByGender[$v["id_gender"]]["total"], $MOEDA['id']);
            }
        }

        /*if ($v['marca'] != '') {
            $v['nome'] .= ' - ' . $v['marca'];
        }*/

        if (trim($seasonTitle) == "" && $v["page_cat_id"] > 0) {
            $row_catalog = get_line_table_cache_api('registos_catalogo', "`id`='" . $v["page_cat_id"] . "' AND `deleted`='0'");
            $seasonTitle = $row_catalog["nome" . $LG];
        }

        $comp = explode(' - ', $v['composition']);
        $v['composition'] = $comp[0];

        $size = array(
            'ref'  => $v["ref"],
            'size' => $v["tamanho"]." ".$comp[1],
            'qnt'  => $v["qnt_total"]
        );

        $iva      += $v["valor_iva"];
        $subtotal += $v['valor_final'];

        $_qtds     = $v["qnt_total"];
        $price     = $v['valoruni_avg'];

        $size["price_unit"] = call_api_func('OBJ_money', $price, $MOEDA['id']);

        if (count($v["service"]) > 0) {

            foreach ($v["service"] as $service) {

                $subtotal     += $service['valor_final_serv'];
                $iva          += $service["valor_servico_iva"];
                $order["qtd"] += $service["quantidade"];

                if (isset($totalsByGender["service"])) {
                    $totalsByGender["service"]["qtd"]   += $service["quantidade"];
                    $totalsByGender["service"]["total"] += $service["valor_final_serv"];

                    $totalsByGender["service"]["label_price"] = call_api_func('OBJ_money', $totalsByGender["service"]["total"], $MOEDA['id']);
                } else {
                    $totalsByGender["service"] = array(
                        "name"        => $templateInfo["response"]["expressions"]["533"],
                        "qtd"         => $service["quantidade"],
                        "total"       => $service["valor_final_serv"],
                        "label_price" => ""
                    );

                    $totalsByGender["service"]["label_price"] = call_api_func('OBJ_money', $totalsByGender["service"]["total"], $MOEDA['id']);
                }
            }
        }

        if ($v['valoruni_anterior_sem_iva'] == 0) $v['valoruni_anterior_sem_iva'] = $v['valoruni_sem_iva'];

        $size["price_unit_anterior_sem_iva"] = call_api_func('OBJ_money', $v['valoruni_anterior_sem_iva'], $MOEDA['id']);
        $size["price_unit_sem_iva"]          = call_api_func('OBJ_money', $v['valoruni_sem_iva'], $MOEDA['id']);

        $decimal = (int)$v['moeda_decimais'] > 0 ? (int)$v['moeda_decimais'] : 2;

        $b2b_discount_price_perc = 0;
        $b2b_discount_price      = 0;
        if ($v['valoruni_anterior_sem_iva'] > 0) {

            if( strtotime($v['data']) > strtotime("2022-12-01") ){

                $b2b_discount_price = $v["valoruni_desconto_sem_iva"];
                $b2b_discount_price_perc = str_replace( ["-", "%", " "], "", $v['promo_perc']);

            }else{

                $b2b_discount_price = $v["valoruni_anterior_sem_iva"] - $v['valoruni_sem_iva'];

                $temp_b2b_discount_price_perc = 100 - (((float)$v['valoruni_sem_iva'] * 100) / $v['valoruni_anterior_sem_iva']);
                preg_match("/\.?0*$/", $temp_b2b_discount_price_perc, $matches, PREG_OFFSET_CAPTURE);
                if (count($matches) > 0) {
                    $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
                } else {
                    $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $decimal, '.', '');
                }

            }

        }

        $size["b2b_discount_price"] = call_api_func('moneyOBJ', $b2b_discount_price, $v['moeda']);
        $size["b2b_discount_raw"]   = $b2b_discount_price_perc;
        $size["b2b_discount_perc"]  = str_replace("-", "", $b2b_discount_price_perc) . "%";

        $b2b_pvr_discount_perc = 0;
        if ($v['pvr_desconto_sem_iva'] > 0) {

            if( strtotime($v['data']) > strtotime("2022-12-01") ){

                $b2b_pvr_discount_perc = str_replace( ["-", "%", " "], "", $v['desconto_linha_perc'] );

            }else{

                $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto_sem_iva'] * 100) / $v['pvr_sem_iva'];
                preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
                if (count($matches) > 0) {
                    $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
                } else {
                    $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $decimal, '.', '');
                }

            }

        }

        if ($v['pvr_sem_iva'] == 0) {
            if ($v['valoruni_anterior_sem_iva'] > 0) {
                $size['pvr_sem_iva'] = $v['valoruni_anterior_sem_iva'];
            } else {
                $size['pvr_sem_iva'] = $v['valoruni_sem_iva'];
            }
        }

        $size["b2b_pvr"]               = call_api_func('OBJ_money', $v['pvr_sem_iva'], $v['moeda']);
        $size["b2b_pvr_discount"]      = call_api_func('OBJ_money', $v['pvr_desconto_sem_iva'], $v['moeda']);
        $size["b2b_pvr_discount_raw"]  = $b2b_pvr_discount_perc;
        $size["b2b_pvr_discount_perc"] = str_replace("-", "", $b2b_pvr_discount_perc . "%");

        $size["price_rrp"]              = call_api_func('OBJ_money', $v['pvpr'], $v['moeda']);
        $size["markup"]                 = $v['markup'];
        $size["no_return"]              = (int)$v['no_return'];

        if((int)$v['no_return'] > 0) $no_return = 1;

        $productKey = $v['sku_family'] . '_' . $v['cor_id'].'_'.$v['oferta'];

        $lineKey = $v['sku_family'] . '_' . $v['cor_id'] . '_' . $v['prod_tamanho'];
        if (trim($v['variante']) != '') {
            $lineKey .= '_' . $v['variante'];
        }

        if (trim($v['variante2']) != '') {
            $lineKey .= '_' . $v['variante2'];
        }


        if ($v["custom"] > 0 || $v["id_linha_orig"] > 0) $lineKey = $v["pid"];

        if ($v["pack"] > 0 && $v["pack"] != 3) $lineKey = $v["pid"] . "|||" . $v["id"];



        if (isset($products[$productKey])) {

            $products[$productKey]["quantidade"]   += $_qtds;
            $products[$productKey]["subtotal_raw"] += $v['valor_final'];
            $products[$productKey]["subtotal"]      = call_api_func('OBJ_money', $products[$productKey]["subtotal_raw"], $MOEDA['id']);

        } else {

            $products[$productKey]                  = $v;
            $products[$productKey]["quantidade"]    = $_qtds;
            $products[$productKey]["subtotal_raw"]  = $v['valor_final'];
            $products[$productKey]["subtotal"]      = call_api_func('OBJ_money', $products[$productKey]["subtotal_raw"], $MOEDA['id']);

        }


        $stock_condicionado_arr = [];
        # Coluna disponibilidade
        if( !empty($v['disp_condicionada']) && $show_availability == 1 ){
            $stock_condicionado_arr = json_decode($v['disp_condicionada'], true);

            $stock_condicionado = $stock_condicionado_arr['inventory_conditioned_quantity'];
            $data_condicionada  = $stock_condicionado_arr['inventory_conditioned_date'];
            $stock_disponivel   = $stock_condicionado_arr['stock'];

            $stock_pendente = 0;
            if( (int)$_qtds < (int)$stock_disponivel ){
                $stock_disponivel = $_qtds;
            }elseif( (int)$_qtds > (int)$stock_disponivel + (int)$stock_condicionado ){
                $stock_pendente = (int)$_qtds - (int)$stock_disponivel - (int)$stock_condicionado;
            }else{
                $stock_condicionado = (int)$_qtds - (int)$stock_disponivel;
            }

            if($stock_disponivel < $_qtds && isset($stock_condicionado_arr['inventory_conditioned_arr']) && count($stock_condicionado_arr['inventory_conditioned_arr'])){
                $total_stock_aux    = $stock_disponivel;

                $stockDispLineKey = $lineKey.'_0';
                $size['data_condicionada'] = estr2(657);
                addSizeToProdArr($products[$productKey]['sizes'], $size, $stockDispLineKey, $stock_disponivel, $v);

                $max_date = "1990-01-01";

                foreach($stock_condicionado_arr['inventory_conditioned_arr'] as $stock_cond_row){

                    if($total_stock_aux >= $_qtds){
                        break;
                    }

                    if( (int)$stock_condicionado_arr['replacement_time'] > 0 && strtotime($stock_cond_row['data_condicionada']) > strtotime($max_date) ){
                        $max_date = $stock_cond_row['data_condicionada'];
                    }

                    if( $total_stock_aux + $stock_cond_row['stock_condicionado'] <= $_qtds ){

                        $total_stock_aux += (int)$stock_cond_row['stock_condicionado'];

                        $stockCondLineKey = $lineKey.'_'.$stock_cond_row['data_condicionada'];
                        $size['data_condicionada'] = strtolower(estr2(656)) . ' ' . $stock_cond_row['data_condicionada'];
                        addSizeToProdArr($products[$productKey]['sizes'], $size, $stockCondLineKey, (int)$stock_cond_row['stock_condicionado'], $v);

                    }else{
                        $stock_cond_row['stock_condicionado'] = (int)$_qtds - (int)$total_stock_aux;
                        $total_stock_aux += (int)$stock_cond_row['stock_condicionado'];

                        $stockCondLineKey = $lineKey.'_'.$stock_cond_row['data_condicionada'];
                        $size['data_condicionada'] = strtolower(estr2(656)) . ' ' . $stock_cond_row['data_condicionada'];
                        addSizeToProdArr($products[$productKey]['sizes'], $size, $stockCondLineKey, (int)$stock_cond_row['stock_condicionado'], $v);
                        break;
                    }

                }

                if( ($_qtds - $total_stock_aux) >= 0 ){
                    $stock_pendente = $_qtds - $total_stock_aux;
                }

            }else{

                $stockDispLineKey = $lineKey.'_0';
                $size['data_condicionada'] = estr2(657);
                addSizeToProdArr($products[$productKey]['sizes'], $size, $stockDispLineKey, $stock_disponivel, $v);

                if($stock_condicionado > 0 && $data_condicionada!='' && $data_condicionada!='0000-00-00'){
                    $stockCondLineKey = $lineKey.'_'.$data_condicionada;
                    $size['data_condicionada'] = strtolower(estr2(656)) . ' ' . $data_condicionada;
                    addSizeToProdArr($products[$productKey]['sizes'], $size, $stockCondLineKey, $stock_condicionado, $v);
                }

            }

            if( (int)$stock_condicionado_arr['replacement_time'] > 0 && $stock_pendente > 0 ){

                $replacement_time = strtotime("+" . $stock_condicionado_arr['replacement_time'] . " day");
                if( $replacement_time > strtotime($max_date) ){
                    $size['data_condicionada'] = strtolower(estr2(656)) . ' ' . date("Y-m-d", $replacement_time);
                    $stockPendLineKey = $lineKey.'_ZZ'; # Force this position to be the last
                    addSizeToProdArr($products[$productKey]['sizes'], $size, $stockPendLineKey, $stock_pendente, $v);
                }

            }elseif( $stock_pendente > 0 ){

                $size['data_condicionada'] = estr2(315);
                $stockPendLineKey = $lineKey.'_ZZ'; # Force this position to be the last
                addSizeToProdArr($products[$productKey]['sizes'], $size, $stockPendLineKey, $stock_pendente, $v);

            }

        }else{

            addSizeToProdArr($products[$productKey]['sizes'], $size, $lineKey, $_qtds, $v);

        }

        ksort($products[$productKey]['sizes']);

    }

    if (trim($client['cod_erp']) != '') {
        $templateInfo["response"]["order"]['billing_address']['name'] = $client['cod_erp'] . '<br>' . $templateInfo["response"]["order"]['billing_address']['name'];
    }


    $templateInfo["response"]["shop"]              = OBJ_shop_mini();
    $templateInfo["response"]["lines"]             = $products;
    $templateInfo["response"]["sub_total"]         = call_api_func('OBJ_money', $subtotal, $MOEDA['id']);
    $templateInfo["response"]["iva"]               = $templateInfo["response"]["order"]["tax_amount"];
    $templateInfo["response"]["title_season"]      = $seasonTitle;
    $templateInfo["response"]["lines_gender"]      = $totalsByGender;
    $templateInfo["response"]["logo"]              = $logoImage;
    $templateInfo["response"]["show_availability"] = $show_availability;
    $templateInfo["response"]["no_return"]         = $no_return;


    $processedBySalesman = 0;
    $processedBy         = $templateInfo["response"]["expressions"]["590"];
    if ((int)$order["b2b_vendedor"] > 0) {
        $processedBySalesman = 1;
        $salesman            = cms_fetch_assoc(cms_query("SELECT `nome` FROM `_tusers_sales` WHERE `id`='" . $order["b2b_vendedor"] . "'"));
        $processedBy         = $templateInfo["response"]["expressions"]["591"] . " " . $salesman["nome"];
    } elseif ((int)$order["b2b_id_utilizador_restrito"] > 0) {
        $restrictedClient    = cms_fetch_assoc(cms_query("SELECT `nome` FROM `_tusers` WHERE `id`='" . $order["b2b_id_utilizador_restrito"] . "'"));
        $processedBy         = $templateInfo["response"]["expressions"]["591"] . " " . $restrictedClient["nome"];
    }

    $templateInfo["response"]["processado_por"]          = $processedBy;
    $templateInfo["response"]["processado_por_vendedor"] = $processedBySalesman;

    #VALES
    // $templateInfo["response"]['vales'] = $eComm->getInvoiceChecks($orderId);
    // foreach ($templateInfo["response"]['vales'] as &$v) {

    //     $v["price"] = call_api_func('OBJ_money', $v['valor_descontado'], $MOEDA['id']);

    //     $name = $templateInfo["response"]["expressions"][75];
    //     $obsParts = explode("_", $v["obs"]);
    //     if ($obsParts[0] == "desconto-pagamento") {
    //         $name = $templateInfo["response"]["expressions"][879];
    //     }elseif($obsParts[0] == "pontosck"){
    //         $name = $templateInfo["response"]["expressions"][953];
    //     }

    //     $v["name"] = $name;
    // }
    $arr_vales = $templateInfo["response"]['order']['transactions'];
    unset($arr_vales[0]);
    foreach ($arr_vales as &$vale) {
        $vale["price"] = $vale['amount'];
        $vale["name"] = $vale["gateway"];
    }
    $templateInfo["response"]['vales'] = $arr_vales;

    #VOUCHERS
    $templateInfo["response"]['voucher'] = array();

    if ($order['iva_valor'] > 0) {
        $vouchersSQL = "SELECT SUM(`desconto_sem_iva`*`qnt`) as `desconto`, `desconto_vaucher_ref` FROM `ec_encomendas_lines` WHERE `order_id`='" . $orderId . "' AND `ref`<>'PORTES' AND `desconto_vaucher_id`>0 GROUP BY `desconto_vaucher_id`";
    } else {
        $vouchersSQL = "SELECT SUM(`desconto`*`qnt`) as `desconto`, `desconto_vaucher_ref` FROM `ec_encomendas_lines` WHERE `order_id`='" . $orderId . "' AND `ref`<>'PORTES' AND `desconto_vaucher_id`>0 GROUP BY `desconto_vaucher_id`";
    }

    $vouchersRes = cms_query($vouchersSQL);
    while ($voucherRow = cms_fetch_assoc($vouchersRes)) {

        $voucherRow["price"] = call_api_func('OBJ_money', $voucherRow['desconto'], $MOEDA['id']);
        $voucherRow["cod_voucher"] = $voucherRow['desconto_vaucher_ref'];

        $templateInfo["response"]['voucher'][] = $voucherRow;
    }

    #CREDITO
    $templateInfo["response"]["credit"] = "";
    if ((int)$order['valor_credito'] > 0) {
        $templateInfo["response"]["credit"] = call_api_func('OBJ_money', $order['valor_credito'] - $order['desconto_credito'], $MOEDA['id']);;
    }

    #Portes
    $templateInfo["response"]['portes'] = "";
    if ((int)$order['portes'] > 0) {
        $templateInfo["response"]['portes'] = call_api_func('OBJ_money', $order['portes'], $MOEDA['id']);
    }

    #PONTOS
    $templateInfo["response"]['pontos'] = "";
    if ((int)$order['generatedPoints'] > 0) {
        $txt_generatedPoints = $order['generatedPoints']." ".$templateInfo["response"]["expressions"]["350"];
        if((int)$SETTINGS_LOJA["pontos"]["campo_6"] > 0){
            $txt_generatedPoints = $order['moeda_prefixo'].number_format($order['generatedPoints']*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$order['moeda_sufixo'];
        }
        $templateInfo["response"]['pontos'] = $txt_generatedPoints;
    }

    #IMPOSTO
    $templateInfo["response"]['imposto'] = "";
    if ($order['imposto'] > 0) {
        $templateInfo["response"]['imposto'] = call_api_func('OBJ_money', $order['imposto'], $MOEDA['id']);
    }

    #CUSTO PAGAMENTO
    $templateInfo["response"]['custo_pag'] = "";
    if ($order['custo_pagamento'] > 0) {
        $templateInfo["response"]['custo_pag'] = call_api_func('OBJ_money', $order['custo_pagamento'], $MOEDA['id']);
    }

    #OBS
    $templateInfo["response"]['obs'] = "";
    $comment = cms_fetch_assoc(cms_query("SELECT `id`, `obs` FROM `ec_encomendas_log` WHERE `estado_novo`='98' AND `autor`='Observações' AND `encomenda`='" . $orderId . "'  LIMIT 1"));
    if ($comment['id'] > 0) {
        $templateInfo["response"]['obs'] = $comment['obs'];
    }

    #PO NUMBER
    $templateInfo["response"]['po_number'] = "";
    $purchaseOrderNumber = cms_fetch_assoc(cms_query("SELECT `id`, `obs` FROM `ec_encomendas_log` WHERE `estado_novo`='98' AND `autor`='PO Number' AND `encomenda`='" . $orderId . "'  LIMIT 1"));
    if ($purchaseOrderNumber['id'] > 0) {
        $templateInfo["response"]['po_number'] = $purchaseOrderNumber['obs'];
    }

    #DATA ENTREGA
    $templateInfo["response"]['data_entrega'] = "";
    if (trim($order['b2b_data_entrega']) != '' && $order['b2b_data_entrega'] != "0000-00-00") {
        $templateInfo["response"]['data_entrega'] = $order['b2b_data_entrega'];
    }

    $templateInfo['response']['entrega_pais'] = $order['entrega_pais'];

    $site_expressions = get_expressions();

    $templateInfo['response']['exp_pvr']      = $site_expressions[472];
    $templateInfo['response']['exp_pvr_desc'] = $site_expressions[473];

    $templateInfo['response']['additional_info_enc'] = trim($client['additional_info_enc']);

    if ((int)$CONFIG_OPTIONS['PAYMENT_MODALITIES_MODULE_ACTIVE'] == 1 && (int)$client['modalidade_pagamento'] > 0) {
        $paymentModality = cms_fetch_assoc(cms_query("SELECT `id`, `nome" . $LG . "` AS nome, `bloco" . $LG . "` AS bloco
                                                        FROM `modalidades_pagamento`
                                                        WHERE `id`='" . $client['modalidade_pagamento'] . "' AND `nome" . $LG . "`!=''
                                                        LIMIT 1"));
        if( (int)$paymentModality['id'] > 0 && (trim($paymentModality['limitar_metodos_pagamentos']) == '' || (trim($paymentModality['limitar_metodos_pagamentos']) !='' && in_array($order["tracking_tipopaga"], explode(',', $paymentModality['limitar_metodos_pagamentos'] )) ))){
            $templateInfo['response']['payment_modality'] = array("name" => $paymentModality['nome'], "desc" => $paymentModality['bloco']);
        }
    }

    if (is_callable('custom_controller_account_order_print')) {
        call_user_func_array('custom_controller_account_order_print', array(&$templateInfo));
    }

    if (file_exists("../templates/account/account_order_print_v2.htm")) {
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/account/', false, _ROOT.'/temp_twig/');
    } else {
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/' . $CONFIG_TEMPLATES_PARAMS["account_version"], false, _ROOT.'/temp_twig/');
    }

    $html = $fx->printTwigTemplate("account_order_print_v2.htm", $templateInfo, true, []);

    $documentTemplate = '
    <!doctype html>
    <html>
        <body>
            <div id="wrapper">
                ' . utf8_encode($html) . '
            </div>
        </body>
    </html>';
    // echo $documentTemplate; exit;
    include("lib/mpdf/mpdf.php");

    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 9, 9, 9, 9, 'C');
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($stylesheet, 1);
    $mpdf->WriteHTML($documentTemplate);


    $outputType = 'D';
    $savePath = str_replace('/', '', $order["order_ref"]) . '.pdf';
    if ($view > 0) {
        $outputType = 'F';
        $savePath = _ROOT . '/downloads/orders/' . $token . '.pdf';
    }

    $mpdf->Output($savePath, $outputType);
    
            
    if($view==2){
        ob_clean();
        header('Content-Type: application/pdf');
        header("Location: ".$slocation.'/downloads/orders/' . $token . '.pdf', true, 301);
        header('Content-Length: ' . filesize($savePath));
        header('Content-Disposition: inline; filename="Downloaded"');
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
    }
    
    exit;
}

function addSizeToProdArr(&$productSizes, $size, $lineKey, $_qtds, $v){

    global $MOEDA;

    $size["quantidade"]     = $_qtds;

    $size["price_line"]     = call_api_func('OBJ_money', $_qtds * $v['valoruni_sem_iva'], $MOEDA['id']);
    $size["label_discount"] = call_api_func('OBJ_money', $v["discount"], $MOEDA['id']);

    $productSizes[$lineKey] = $size;

}

function getOrderLinesPDF($orderID, $without_portes = 0)
{
    global $LG;

    $more = '';
    if ($without_portes == 1) $more = 'AND ec_enc_l.ref<>"PORTES"';

    $classifier = getORderResumeClassifier();
    $_table = $classifier['table'];
    $_field = $classifier['field'];

    $sql = "SELECT
          `reg`.`sem_devolucao` as `no_return`,
          `reg_g`.`id` as `id_gender`,
          `reg_g`.`nome" . $LG . "` as `name_gender`,
          `reg`.`variante` as `variante`,
          `reg`.`variante2` as `variante2`,
          `reg`.`tamanho` as `prod_tamanho`,
          `ec_enc_l`.*,
          SUM(ec_enc_l.iva_valor) as valor_iva,
          SUM(ec_enc_l.qnt) as qnt_total,
          SUM((ec_enc_l.valoruni_sem_iva/ec_enc_l.taxa_cambio)*qnt) as valor_final,
          SUM(((ec_enc_l.valoruni_sem_iva-ec_enc_l.desconto)/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valor_final_f,
          AVG(ec_enc_l.valoruni_sem_iva) as valoruni_avg,
          SUM((ec_enc_l.valoruni_anterior_sem_iva/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valoruni_anterior_final,
          AVG(ec_enc_l.valoruni_anterior_sem_iva) as valoruni_anterior_avg
        FROM `ec_encomendas_lines` as `ec_enc_l`
        LEFT JOIN `registos` as `reg` ON `reg`.`id` = `ec_enc_l`.`pid`
        LEFT JOIN `registos_$_table` as `reg_g` ON `reg_g`.`id` = `reg`.`$_field`
        WHERE `ec_enc_l`.`order_id`='" . $orderID . "' " . $more . " AND `id_linha_orig`<1
        GROUP BY `ec_enc_l`.`pid`
        ORDER BY `ec_enc_l`.`ref`='PORTES' ASC, `ec_enc_l`.`sku_family` ASC, `ec_enc_l`.`cor_name` ASC, `ec_enc_l`.`ref` ASC";

    $res = cms_query($sql);

    $_arr_lines = array();
    while ($row = cms_fetch_assoc($res)) {

        $row["service"] = array();

        if (trim($row["servico_add"]) != "") {
            $sql_service = "SELECT * FROM ec_encomendas_lines WHERE id_linha_orig='" . $row["id"] . "' ";
            $res_service = cms_query($sql_service);
            while ($row_service = cms_fetch_assoc($res_service)) {
                $sql_service_g = "SELECT SUM(qnt) as qnt_total, SUM((valoruni_sem_iva/taxa_cambio)*qnt) as valor_final, SUM(iva_valor) as valor_iva FROM ec_encomendas_lines WHERE pid='" . $row_service["pid"] . "' AND order_id='" . $row_service["order_id"] . "' ";
                $res_service_g = cms_query($sql_service_g);
                $row_service_g = cms_fetch_assoc($res_service_g);

                $row_service["quantidade"] = $row_service_g["qnt_total"];
                $row_service["valor_final_serv"] = $row_service_g["valor_final"];
                $row_service["valor_final"] = call_api_func('OBJ_money', $row_service_g["valor_final"], $row_service['moeda']);
                $row_service["valor_servico"] = call_api_func('OBJ_money', $row_service["valoruni_sem_iva"], $row_service['moeda']);
                $row_service["valor_servico_iva"] = $row_service_g["valor_iva"];
                $row["service"][] = $row_service;
            }
        }
        $_arr_lines[] = $row;
    }

    return $_arr_lines;
}

