<?

function _getShoppingBagPrintV2($catalog_id=null, $unit_store=0)
{

    global $userID, $eComm, $LG, $fx, $MOEDA, $CONFIG_OPTIONS, $B2B_LAYOUT;

    if(is_null($catalog_id)){
        $catalog_id = (int)params('catalog_id');
        $unit_store = (int)params('unit_store');
    }

    $client  = call_api_func("get_line_table", "_tusers", "id='" . $userID . "'");
    $clientCountry = call_api_func("get_line_table", "ec_paises", "id='" . $client["pais"] . "'");


    $market = cms_fetch_assoc(cms_query("SELECT `entidade_faturacao`, depositos_condicionados_ativo FROM `ec_mercado` WHERE `id`=" . $_SESSION['_MARKET']['id']));

    /*$language = 0;
    if ((int)$clientCountry["idioma"] > 0) {
        $language = $clientCountry['idioma'];
    }

    $idioma = call_api_func("get_line_table", "ec_language", "id='".$language."'");

    if( $idioma['code']=="es" ) $idioma['code']="sp";
    if( $idioma['code']=="en" ) $idioma['code']="gb";

    $LG = $idioma['code'];*/



    if($catalog_id>-2){
        $regra = preparar_regras_carrinho($catalog_id);
    }

    $show_availability = !empty($regra) && ( (int)$regra['validar_carrinho'] == 2 || ( (int)$regra['validar_carrinho'] <= 0 && (int)$market['depositos_condicionados_ativo'] == 1 ) ) ? 1 : 0;

    $lines          = getLinesBasketPDF($userID, $catalog_id, $unit_store);

    $subtotal                   = 0;
    $totalProds                 = 0;
    $total_rappel               = 0;
    $total_products_rappel      = 0;
    $total_products_orig_rappel = 0;
    $seasonTitle                = "";
    $totalsByGender             = array();
    $products                   = array();
    $no_return      = 0;

    foreach($lines as $v){

        # Compatibilizar os cálculos abaixo
        $v['valoruni_sem_iva']          = $v['valoruni'];
        $v['valoruni_anterior_sem_iva'] = $v['valoruni_anterior'];
        $v['valor_desc_final']          = $v['valor_final_f'];
        # Compatibilizar os cálculos abaixo

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

        $totalProds += $v["qnt_total"];
        $subtotal   += $v['valor_final'];

        $_qtds       = $v["qnt_total"];
        $price       = $v['valoruni_avg'];

        $size["price_unit"] = call_api_func('OBJ_money', $price, $MOEDA['id']);

        if($v['valoruni_anterior_sem_iva']==0) $v['valoruni_anterior_sem_iva'] = $v['valoruni_sem_iva'];

        $size["price_unit_anterior_sem_iva"] = call_api_func('OBJ_money', $v['valoruni_anterior_sem_iva'], $MOEDA['id']);
        $size["price_unit_sem_iva"]          = call_api_func('OBJ_money', $v['valoruni_sem_iva'], $MOEDA['id']);

        $decimal = (int)$v['moeda_decimais'] > 0 ? (int)$v['moeda_decimais'] : 2;

        $b2b_discount_price_perc = 0;
        $b2b_discount_price      = 0;
        if ($v['valoruni_anterior_sem_iva'] > 0) {
            $b2b_discount_price = $v["valoruni_anterior_sem_iva"] - $v['valoruni_sem_iva'];

            $temp_b2b_discount_price_perc = 100 - (((float)$v['valoruni_sem_iva'] * 100) / $v['valoruni_anterior_sem_iva']);
            preg_match("/\.?0*$/", $temp_b2b_discount_price_perc, $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) > 0) {
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
            } else {
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $decimal, '.', '');
            }
        }

        $size["b2b_discount_price"] = call_api_func('moneyOBJ', $b2b_discount_price, $v['moeda']);
        $size["b2b_discount_raw"]   = $b2b_discount_price_perc;
        $size["b2b_discount_perc"]  = str_replace("-", "", $b2b_discount_price_perc) . "%";

        // $b2b_pvr_discount_perc = 0;
        $b2b_pvr_discount_perc = $v['desconto_linha_perc'];
        $b2b_pvr_discount      = 0;
        $b2b_pvr               = 0;
        if($v['pvr_desconto_sem_iva']>0) {

            // $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto_sem_iva']*100)/$v['pvr_sem_iva'];
            // preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
            // if ( count($matches) > 0 ){
            //     $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            // }else{
            //     $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $decimal, '.', '');
            // }

            $b2b_pvr          = $v['pvr_sem_iva'];
            $b2b_pvr_discount = $v['pvr_desconto_sem_iva'];

        }else if( $v['pvr_desconto'] > 0 ){

            // $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto']*100)/$v['pvr'];
            // preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
            // if ( count($matches) > 0 ){
            //     $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            // }else{
            //     $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $decimal, '.', '');
            // }

            $b2b_pvr          = $v['pvr'];
            $b2b_pvr_discount = $v['pvr_desconto'];

        }


        $size["b2b_pvr"]               = call_api_func('OBJ_money', $b2b_pvr, $v['moeda']);
        $size["b2b_pvr_discount"]      = call_api_func('OBJ_money', $b2b_pvr_discount, $v['moeda']);
        $size["b2b_pvr_discount_raw"]  = $b2b_pvr_discount_perc;
        // $size["b2b_pvr_discount_perc"] = str_replace("-", "", $b2b_pvr_discount_perc."%");
        $size["b2b_pvr_discount_perc"] = $b2b_pvr_discount_perc;
        $size["no_return"]              = (int)$v['no_return'];

        if((int)$v['no_return'] > 0) $no_return = 1;

        $productKey = $v['sku_family'] . '_' . $v['cor_id'];

        $lineKey = $v['sku_family'] . '_' . $v['cor_id'] . '_' . $v['prod_tamanho'];
        if (trim($v['variante']) != '') {
            $lineKey .= '_' . $v['variante'];
        }

        if (trim($v['variante2']) != '') {
            $lineKey .= '_' . $v['variante2'];
        }

        if ($v["custom"] > 0 || $v["id_linha_orig"] > 0 || $v["pack"] > 0) {
            $lineKey = $v["pid"];
            if ($v["pack"] > 0) $lineKey = $v["pid"] . "|||" . $v["id"];
        }

        if (isset($products[$productKey])) {

            $products[$productKey]["quantidade"]                    += $_qtds;
            $products[$productKey]["subtotal_raw"]                  += $v['valor_final'];
            $products[$productKey]["subtotal"]                      = call_api_func('OBJ_money', $products[$productKey]["subtotal_raw"], $MOEDA['id']);

            # Compatibilizar os cálculos abaixo
            $products[$productKey]['valoruni_sem_iva']              += $v['valoruni'];
            $products[$productKey]['valoruni_anterior_sem_iva']     += $v['valoruni_anterior'];
            $products[$productKey]['valor_desc_final']              += $v['valor_final_f'];
            # Compatibilizar os cálculos abaixo

        } else {

            $products[$productKey]                 = $v;
            $products[$productKey]["quantidade"]   = $_qtds;
            $products[$productKey]["subtotal_raw"] = $v['valor_final'];
            $products[$productKey]["subtotal"]     = call_api_func('OBJ_money', $products[$productKey]["subtotal_raw"], $MOEDA['id']);

        }

        $desconto_rappel = 0;
        if( ($v["promotion"] == 2 || $v["promotion"] == 3)  && $v["valoruni_anterior"] > 0 ){
            if((int)$v["valoruni_anterior_sem_iva"] > 0 && (int)$v["valoruni_sem_iva"] ){
                $desconto_rappel = $v["valoruni_anterior_sem_iva"] - $v["valoruni_sem_iva"];
                if($v["id_linha_orig"]<1){
                    $total_products_rappel += $v['valoruni_sem_iva'] * $v['qnt_total'];
                    $total_products_orig_rappel += $v['valoruni_anterior_sem_iva'] * $v['qnt_total'];
                }
            }else{
                $desconto_rappel = $v["valoruni_anterior"] - $v["valoruni"];
                if($v["id_linha_orig"]<1){
                    $total_products_rappel += $v['valoruni'] * $v['qnt_total'];
                    $total_products_orig_rappel += $v['valoruni_anterior'] * $v['qnt_total'];
                }
            }
        }
        $total_rappel += $desconto_rappel * $v['qnt_total'];

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

                if( ($_qtds - $total_stock_aux)>0 ){
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

        #ksort($products[$productKey]['sizes']);

    }


    $x = array();
    $x["response"]["user"]             = $client; 
    $x["response"]["country"]["nome"]  = $clientCountry['nome'.$LG];         
    
    $x["response"]["order_qtd"]         = $totalProds;
    $x["response"]["expressions"]       = call_api_func('getAccountExpressions');
    $x["response"]["order"]             = [];
    $x["response"]["order"]['date']     = date("Y-m-d H:i");
    $x["response"]["show_availability"] = $show_availability;

    $x["response"]["shop"]              = call_api_func('OBJ_shop_mini');
    $x["response"]["lines"]             = $products;
    $x["response"]["sub_total_raw"]     = $subtotal;
    $x["response"]["sub_total"]         = call_api_func('OBJ_money', $subtotal, $MOEDA['id']);
    $x["response"]["no_return"]         = $no_return;

    if( $total_rappel > 0 ){

        $prec_rapple = 0;
        if($total_products_orig_rappel > 0 && $total_products_rappel > 0 ) $prec_rapple = (($total_products_orig_rappel - $total_products_rappel) /  $total_products_orig_rappel ) * 100;

        $x["response"]["total_rappel"] = call_api_func('OBJ_money', $total_rappel, $MOEDA['id']);
        $x["response"]["total_perc_rappel"] = number_format($prec_rapple, 0, ".", "");

    }

    $IVA_array = $eComm->getIVAProdutos($x['response']['lines'], $x['response']['sub_total_raw']);

    #VALES
    $x["response"]['vales'] = $eComm->getInsertedChecks($userID);
    foreach ($x["response"]['vales'] as &$v) {

        $v["price"] = call_api_func('OBJ_money', $v['valor'], $MOEDA['id']);

        $IVA_array['total'] -= $v['valor'];

        $name = $x["response"]["expressions"][75];
        $obsParts = explode("_", $v["obs"]);
        if ($obsParts[0] == "desconto-pagamento") {
            $percentage_discount = $_SESSION["EC_USER"]["desconto_modalidade_pagamento"];
            if (fmod($percentage_discount, 1) === 0.00) {
                $percentage_discount = number_format($percentage_discount, 0, '.', '');
            }
            $name = $x["response"]["expressions"][879]." ".$percentage_discount."%";
        }elseif($obsParts[0] == "pontosck"){
            $name = $x["response"]["expressions"][953];
        }

        $v["name"] = $name;
    }

    $x['response']['iva']           = call_api_func('OBJ_money', $IVA_array['iva'], $MOEDA['id']);
    $x['response']['basket_total']  = call_api_func('OBJ_money', $IVA_array['total'], $MOEDA['id']);
    $x["response"]["title_season"]  = $seasonTitle;
    $x["response"]["lines_gender"]  = $totalsByGender;

    $invoiceEntity = cms_fetch_assoc(cms_query("SELECT `id` FROM `ec_invoice_companies` WHERE `id`=" . $market['entidade_faturacao']));

    $logoImage = "sysimages/logo.png";
    if ((int)$invoiceEntity['id'] > 0) {
        $logoImage = "images/cab_" . $invoiceEntity['id'] . ".jpg?".filemtime($_SERVER['DOCUMENT_ROOT']."/images/cab_".$invoiceEntity['id'].".jpg");
    }

    $x["response"]["logo"]          = $logoImage;

    $x['response']['exp_pvr']       = estr2(937);
    $x['response']['exp_pvr_desc']  = estr2(938);

    $x['CONFIG_OPTIONS'] = $CONFIG_OPTIONS;
    
    if($x['CONFIG_OPTIONS']['hide_prices_promo_perc_val']==0) 
        $x['CONFIG_OPTIONS']['all_prices'] = 1;
    
    $x['b2b_style_version'] = (int)$B2B_LAYOUT['b2b_style_version'];

    if(is_callable('custom_controller_shopping_bag_print')) {
        call_user_func_array('custom_controller_shopping_bag_print', array(&$x));
    }

    if(file_exists("../templates/shopping_bag_print_v2.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_base_b2b/', false, _ROOT.'/temp_twig/');
    }

    $html = $fx->printTwigTemplate("shopping_bag_print_v2.htm", $x, true, []);

    $documentTemplate = '
    <!doctype html>
    <html>
        <body>
            <div id="wrapper">
                '.utf8_encode($html).'
            </div>
        </body>
    </html>';
    // echo $documentTemplate;exit;
    include("lib/mpdf/mpdf.php");

    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 9, 9, 9, 9, 'C');
    $mpdf->SetDisplayMode('fullpage');
    $mpdf->WriteHTML($stylesheet,1);
    $mpdf->WriteHTML($documentTemplate);

    $doc_name = "export_cart";

    $type_output = 'D';

    $save_cam = str_replace('/', '', $doc_name).'.pdf';
   /* if($view>0){
        $type_output = 'F';
        $save_cam = _ROOT.'/downloads/orders/'.$token.'.pdf';
    } */

    $mpdf->Output($save_cam, $type_output);

    exit;

}

function addSizeToProdArr(&$productSizes, $size, $lineKey, $_qtds, $v){

    global $MOEDA;

    $size["quantidade"]     = $_qtds;

    $size["price_line"]     = call_api_func('OBJ_money', $_qtds * ($v['valoruni'] - $v['desconto']), $MOEDA['id']);
    $size["label_discount"] = call_api_func('OBJ_money', $v["discount"], $MOEDA['id']);

    $productSizes[$lineKey] = $size;

}


function getLinesBasketPDF($userID, $catalogID, $unitStore=0) {

    global $LG;

    $moreSql = '';
    if($unitStore>0){
        $moreSql = " AND `ec_enc_l`.`col1`='".$unitStore."' ";
    }

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
          SUM(`ec_enc_l`.`iva_valor`) as `valor_iva`,
          SUM(`ec_enc_l`.`qnt`) as `qnt_total`,
          SUM((`ec_enc_l`.`valoruni`/`ec_enc_l`.`taxa_cambio`)*`qnt`) as `valor_final`,
          SUM(((`ec_enc_l`.valoruni-`ec_enc_l`.`desconto`)/`ec_enc_l`.`taxa_cambio`)*`ec_enc_l`.`qnt`) as `valor_final_f`,
          AVG(`ec_enc_l`.`valoruni`) as `valoruni_avg`,
          SUM((`ec_enc_l`.`valoruni_anterior`/`ec_enc_l`.taxa_cambio)*`ec_enc_l`.qnt) as valoruni_anterior_final,
          AVG(`ec_enc_l`.`valoruni_anterior`) as `valoruni_anterior_avg`
        FROM ec_encomendas_lines as `ec_enc_l`
        LEFT JOIN registos as reg ON SUBSTRING_INDEX(ec_enc_l.pid, '|||', -1)=reg.id
        LEFT JOIN `registos_$_table` as `reg_g` ON `reg_g`.`id` = `reg`.`$_field`
        WHERE `ec_enc_l`.id_cliente='".$userID."' AND `ec_enc_l`.status='0' AND `ec_enc_l`.id_linha_orig<1 AND `ec_enc_l`.page_cat_id='".$catalogID. "'" . $moreSql . "
        GROUP BY `ec_enc_l`.`pid`
        ORDER BY `ec_enc_l`.sku_family ASC, `ec_enc_l`.cor_name ASC, `ec_enc_l`.ref ASC"; #Esta ordenação tem de bater certo com a ordenação do carrinho

    $res = cms_query($sql);

    $_arr_lines = Array();
    while($row = cms_fetch_assoc($res)){

        $_arr_lines[] = $row;
    }

    return $_arr_lines;
}

?>
