<?php

function _getUserCatalogPDF($catalog_id = null) {
    global $userID, $LG, $fx, $MOEDA, $CONFIG_OPTIONS, $MARKET, $B2B_LAYOUT;

    if (is_null($catalog_id)) {
        $budget_id = (int)params('budget_id');
    } else {
        $budget_id = $catalog_id;
    }

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    $budget = call_api_func("get_line_table", "budgets", "id='" . $budget_id . "' AND `user_id`=" . $userOriginalID);
    if (empty($budget) || (int)$budget['id'] <= 0) {
        exit;
    }

    $user = call_api_func("get_line_table", "_tusers", "id='" . $userOriginalID . "'");
    $pais = call_api_func("get_line_table", "ec_paises", "id='" . $user["pais"] . "'");

    $language = 0;
    if ((int)$pais["idioma"] > 0) {
        $language = $pais['idioma'];
    }

    $idioma = call_api_func("get_line_table", "ec_language", "id='" . $language . "'");

    if ($idioma['code'] == "es") $idioma['code'] = "sp";
    if ($idioma['code'] == "en") $idioma['code'] = "gb";

    $LG = $idioma['code'];

    $budget_prods = getLinesBudgetPDF($budget_id);

    $x = array();
    $x["response"]["budget"]          = $budget;

    $x["response"]["user_info"]     = $user;
    $x["response"]["user_info"]['budget_address_info'] = nl2br($user['budget_address_info']);
    $x["response"]['conditions'] = nl2br($user['budget_footer_conditions']);

    $x["response"]["order_qtd"]     = $budget['total_quantity'];
    $x["response"]["expressions"]   = call_api_func('getAccountExpressions');

    $x["response"]["shop"]          = call_api_func('OBJ_shop_mini');
    $x["response"]["lines"]         = $budget_prods;
    $x["response"]["sub_total"]     = call_api_func('OBJ_money', $budget['total_value'], $MOEDA['id']);

    $IVA_array = getIVABudget($budget_prods, $budget['total_value']);
    $x['response']['iva']           = call_api_func('OBJ_money', $IVA_array['iva'], $MOEDA['id']);
    $x['response']['basket_total']  = call_api_func('OBJ_money', $IVA_array['total'], $MOEDA['id']);

    $x["response"]["processado_por"] = $x["response"]["expressions"]["591"] . ' ' . $user['nome'];
    $x["response"]["exp_data_validade"] = str_replace("{DATA_VALIDADE}", $budget['expiration_date'], $x["response"]["expressions"]["883"]);

    $img = $_SERVER['DOCUMENT_ROOT'] . $budget['logo'];
    if (!file_exists($img)) {
        $img = $_SERVER['DOCUMENT_ROOT'] . '/images/logo_email_new_layout.png';
    }
    $img_logo = $fx->makeimage($img, 150, 80, 0, 0, 3, 'FFFFFF', '', 'JPG', 0, '', 'FFFFFF');
    $x["response"]["budget"]["logo"] = str_replace('..', '', $img_logo);

    $x['CONFIG_OPTIONS'] = $CONFIG_OPTIONS;
    $x['b2b_style_version'] = (int)$B2B_LAYOUT['b2b_style_version'];

    if (is_callable('custom_controller_budget_pdf')) {
        call_user_func_array('custom_controller_budget_pdf', array(&$x));
    }

    if (file_exists("../templates/account_catalog_print.htm")) {
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    } else {
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/v2/', false, _ROOT.'/temp_twig/');
    }

    $documentTemplate = '
        <!doctype html>
        <html>
            <body>
                <div id="wrapper">
                    {HTML}
                </div>
            </body>
        </html>
    ';

    include("lib/mpdf/mpdf.php");

    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 40, 9, 9, 9, 'C');
    $mpdf->simpleTables = true;
    $mpdf->packTableData = true;
    $mpdf->keep_table_proportions = TRUE;
    $mpdf->shrink_tables_to_fit = 1;
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($stylesheet, 1);

    $cover = $fx->printTwigTemplate("account_catalog_print_cover.htm", $x, true, []);
    $mpdf->WriteHTML(str_replace('{HTML}', utf8_encode($cover), $documentTemplate));


    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $url = "https://";
    } else {
        $url = "http://";
    }
    $url .= $_SERVER['HTTP_HOST'];

    $chunks = array_chunk($budget_prods, 4, true);
    foreach ($chunks as $chunk) {
        foreach ($chunk as $key => $item) {
            $img = $_SERVER['DOCUMENT_ROOT'] . str_replace($url, '', $item['image']);
            $img_prd = $fx->makeimage($img, 300, 300, 0, 0, 3, 'FFFFFF', '', 'JPG', 0, '', 'FFFFFF');
            $img_prd = str_replace('..', '', $img_prd);

            $chunk[$key]['image'] = $img_prd;
        }

        for ($i = count($chunk); $i < 4; $i++) {
            $chunk['tempo-' . $i] = ['placeholder' => 1];
        }
        $x['response']['products_to_print'] = $chunk;

        global $CONFIG_OPTIONS;
        $x['response']['B2B_CATALOGOS_PRODUTOS_TIPO'] = (int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'];

        $products = $fx->printTwigTemplate("account_catalog_print_products.htm", $x, true, []);
        $mpdf->AddPage('', '', 0, '', '', 9, 9, 9, 9, 9, 9);
        $mpdf->WriteHTML(str_replace('{HTML}', utf8_encode($products), $documentTemplate));
    }

    if ($_GET['p']) {
        dd($x);
    }

    $type_output = 'S';
    $save_cam = str_replace('/', '', 'Catalog ' . $budget_id) . '.pdf';

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $save_cam . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    echo $mpdf->Output($save_cam, $type_output);

    exit;
}

function getLinesBudgetPDF($budget_id) {

    global $LG, $slocation, $MOEDA, $CONFIG_OPTIONS;

    $sql = "SELECT * FROM `budgets_lines` WHERE `budget_id`=" . $budget_id;
    $res = cms_query($sql);

    $_arr_lines = array();
    while ($row = cms_fetch_assoc($res)) {

        if ((int)$row['product_type'] == 1) {

            $sql   = "SELECT `sku`, `sku_group`, `sku_family`, `tamanho`, `cor`, `nome" . $LG . "` as `nome` FROM `registos` WHERE `id`=" . $row['product'];
            $prod  = cms_fetch_assoc(cms_query($sql));

            $sizes_arr  = getTamanho($prod['tamanho']);

            // if ($CONFIG_OPTIONS['ORDER_PDF_VERSION'] == 0) {
            //     $prod_group_key = $prod['sku_family'] . $row['final_price'] . $row['discount_percentage'] . $row['markup_percentage'];
            // } else {
            //     $prod_group_key = $prod['sku_family'];
            // }

            $prod_group_key = $prod['sku'];

            $row['b2b_discount_price'] = 0;
            $row['price_unit_anterior_sem_iva'] = $row['final_price_uni'];
            if ($row['discount_percentage'] > 0) {
                $row['price_unit_anterior_sem_iva'] = $row['product_price'] * (1 + ($row['markup_percentage'] / 100));
                $row['b2b_discount_price'] = $row['price_unit_anterior_sem_iva'] - $row['final_price_uni'];
                $row['b2b_discount_perc'] = (int)$row['discount_percentage'] . "%";
            }

            $prod_size = [
                'size'                          => $sizes_arr['nome'],
                'quantity'                      => $row['quantity'],
                'ref'                           => $prod['sku'],
                'price_unit_anterior_sem_iva'   => call_api_func('OBJ_money', $row['price_unit_anterior_sem_iva'], $MOEDA['id']),
                'b2b_discount_perc'             => $row['b2b_discount_perc'],
                'b2b_discount_price'            => call_api_func('OBJ_money', $row['b2b_discount_price'], $MOEDA['id']),
                'price_unit_sem_iva'            => call_api_func('OBJ_money', $row['final_price_uni'], $MOEDA['id']),
                'price_line'                    => call_api_func('OBJ_money', $row['final_price'], $MOEDA['id'])
            ];

            if (isset($_arr_lines[$prod_group_key])) {

                $_arr_lines[$prod_group_key]['sizes'][]       = $prod_size;
                $_arr_lines[$prod_group_key]['quantity']      += $row['quantity'];
                $_arr_lines[$prod_group_key]['final_price']   = call_api_func('OBJ_money', ($_arr_lines[$prod_group_key]['final_price']['value'] + $row['final_price']), $MOEDA['id']);
                $_arr_lines[$prod_group_key]['subtotal']      = $_arr_lines[$prod_group_key]['final_price'];

                continue;
            }

            $image        = getProductImageToBudgetPDF($prod);
            $colour_arr   = getColor($prod['cor'], $prod['sku']);

            $prod_name    = $prod['nome'];
            $prod_colour  = $colour_arr['long_name'];
        } else {
            $image          = 'plugins/system/sysimgs/img-services.jpg';
            $prod_name      = $row['product_name'];
            $prod_size      = null;
            $prod_colour    = '';
            $prod_group_key = $row['id'];
        }

        $row['image']        = $slocation . '/' . $image;
        $row['name']         = $prod_name;
        $row['color']        = $prod_colour;
        $row['markup_price'] = $row['product_price'] + (($row['product_price'] * $row['markup_percentage']) / 100);

        $row['final_price']         = call_api_func('OBJ_money', $row['final_price'], $MOEDA['id']);
        $row['product_price']       = call_api_func('OBJ_money', $row['product_price'], $MOEDA['id']);
        $row['markup_price']        = call_api_func('OBJ_money', $row['markup_price'], $MOEDA['id']);
        $row['discount_percentage'] = (int)$row['discount_percentage'];
        $row['markup_percentage']   = (int)$row['markup_percentage'];

        $row['subtotal']     = $row['final_price'];

        $row['sku_family']   = $prod['sku_family'];

        if (!is_null($prod_size)) {
            $row['sizes'][] = $prod_size;
        }

        $_arr_lines[$prod_group_key] = $row;
    }

    return $_arr_lines;
}

function getProductImageToBudgetPDF($prod) {

    $imagens = getImagens($prod['sku'], $prod['sku_group'], $prod['sku_family']);

    $img_list_0 = '';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU/" . $prod['sku'] . "_0.jpg")) $img_list_0 = "images_prods_static/SKU/" . $prod['sku'] . "_0.jpg";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU_GROUP/" . $prod['sku_group'] . "_0.jpg")) $img_list_0 = "images_prods_static/SKU_GROUP/" . $prod['sku_group'] . "_0.jpg";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU_FAMILY/" . $prod['sku_family'] . "_0.jpg")) $img_list_0 = "images_prods_static/SKU_FAMILY/" . $prod['sku_family'] . "_0.jpg";

    if ($img_list_0 != '') {
        return $img_list_0;
    } else {
        return reset($imagens);
    }
}

function getIVABudget($linhas = array(), $basketInfoTotal = 0) {
    
    global $COUNTRY, $MARKET;

    $iva = 0;

    if ($MARKET['entidade_faturacao'] > 0) {

        $entidade_s = "SELECT * FROM ec_invoice_companies WHERE id=" . $MARKET['entidade_faturacao'] . " LIMIT 0,1";
        $entidade_q = cms_query($entidade_s);
        $entidade_r = cms_fetch_assoc($entidade_q);

        # 2020-05-07
        # Cobramos IVA se pais do cliente for igual ao pais da entiaade faturadora
        # Ou se o cliente é particular dentro da comunidade europeia
        if ($entidade_r['country'] == $COUNTRY['id'] || ($COUNTRY['extracomunitario'] == 0 && $_SESSION['EC_USER']['tipo_utilizador'] == 2)) {

            if((int)$entidade_r['apply_country_vat_rates']==1){
                $pais_s = "SELECT * FROM ec_paises WHERE id=".$COUNTRY['id']." LIMIT 0,1";
                $pais_q = cms_query($pais_s);
                $pais_r = cms_fetch_assoc($pais_q);
                
                $entidade_r['tax_rate']               = $pais_r['tax_rate'];     
                $entidade_r['tax_rate_intermediate']  = $pais_r['tax_rate_intermediate'];      
                $entidade_r['tax_rate_reduced']       = $pais_r['tax_rate_reduced'];      
                $entidade_r['tax_rate_super_reduced'] = $pais_r['tax_rate_super_reduced'];       
            }


            if ($entidade_r['tax_rate_intermediate'] == 0)
                $entidade_r['tax_rate_intermediate'] = $entidade_r['tax_rate'];

            if ($entidade_r['tax_rate_reduced'] == 0)
                $entidade_r['tax_rate_reduced'] = $entidade_r['tax_rate'];

            if ($entidade_r['tax_rate_super_reduced'] == 0)
                $entidade_r['tax_rate_super_reduced'] = $entidade_r['tax_rate'];

            $taxas = array();
            $taxas['0'] = $entidade_r['tax_rate'];
            $taxas['1'] = $entidade_r['tax_rate_intermediate'];
            $taxas['2'] = $entidade_r['tax_rate_reduced'];
            $taxas['3'] = $entidade_r['tax_rate_super_reduced'];
            $taxas['4'] = 0;

            $subtotal = 0;
            $iva = 0;
            foreach ($linhas as $k => $v) {

                if ($v['product_type'] == 1) {
                    $prod_info = cms_fetch_assoc(cms_query("SELECT `iva` FROM `registos` WHERE `id`=" . $v['product'] . " LIMIT 1"));
                    $v['iva_taxa_id'] = $prod_info['iva'];
                } elseif ($v['product_type'] == 2) {
                    $v['iva_taxa_id'] = 0;
                }

                $iva += (($v['final_price']['value'] * $taxas[$v['iva_taxa_id']]) / 100);
                $subtotal += $v['final_price']['value'];
            }
        }
    }

    $res = array(
        "iva"      => $iva,
        "total"    => $basketInfoTotal + $iva,
        "subtotal" => $subtotal
    );

    return $res;
}
