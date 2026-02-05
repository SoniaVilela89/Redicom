<?

function _getShoppingBagPrint($catalog_id=null, $unit_store=0)
{

    global $userID, $eComm, $LG, $fx, $MOEDA, $CONFIG_OPTIONS;

    if(is_null($catalog_id)){
        $catalog_id = (int)params('catalog_id');
        $unit_store = (int)params('unit_store');
    }

    $cliente = call_api_func("get_line_table", "_tusers", "id='".$userID."'");

    $pais = call_api_func("get_line_table", "ec_paises", "id='".$cliente["pais"]."'");

    $language = 0;
    /*if($cliente['id_lingua']>0){
        $language = $cliente['id_lingua'];
    }else{*/
        if((int)$pais["idioma"]>0){
            $language = $pais['idioma'];
        }
    /*}*/

    $idioma = call_api_func("get_line_table", "ec_language", "id='".$language."'");

    if( $idioma['code']=="es" ) $idioma['code']="sp";
    if( $idioma['code']=="en" ) $idioma['code']="gb";

    $LG = $idioma['code'];


    $lines = getLinesBasketPDF($userID, $catalog_id, $unit_store);
    #d($lines);exit;

    $info_basket = $eComm->getInfoBasket($userID, $MOEDA, $catalog_id, $unit_store);

    #$qtd_cart = count($lines);
    $qtd_cart                   = 0;
    $sub_total                  = 0;
    $total_rappel               = 0;
    $total_products_rappel      = 0;
    $total_products_orig_rappel = 0;
    $arr_prods                  = array();
    $iva                        = 0;
    $title_season               = "";
    $arr_gender                 = array();

    foreach($lines as $k => $v){

        $qtd_cart += $v["qnt_total"];

        # Compatibilizar os cálculos abaixo
        $v['valoruni_sem_iva']          = $v['valoruni'];
        $v['valoruni_anterior_sem_iva'] = $v['valoruni_anterior'];
        $v['valor_desc_final']          = $v['valor_final_f'];
        # Compatibilizar os cálculos abaixo

        if( $v['marca'] != '' ){
            $v['nome'] .= ' - '.$v['marca'];
        }
        if ($v["id_gender"] != '') {
            if(isset($arr_gender[$v["id_gender"]])){
                $arr_gender[$v["id_gender"]]["qtd"] += $v["qnt_total"];
                $arr_gender[$v["id_gender"]]["total"] += $v["qnt_total"]*$v['valoruni_sem_iva'];

                $label_preco_gender = call_api_func('OBJ_money',$arr_gender[$v["id_gender"]]["total"], $MOEDA['id']);
                $arr_gender[$v["id_gender"]]["label_price"] = $label_preco_gender;

            }else{
                $arr_gender[$v["id_gender"]] = array(
                    "name"  => $v["name_gender"],
                    "qtd"   => $v["qnt_total"],
                    "total" => $v['valoruni_sem_iva']*$v["qnt_total"],
                    "label_price" => ""
                );

                $label_preco_gender = call_api_func('OBJ_money',$arr_gender[$v["id_gender"]]["total"], $MOEDA['id']);
                $arr_gender[$v["id_gender"]]["label_price"] = $label_preco_gender;
            }
        }

        if(trim($title_season)=="" && $v["page_cat_id"]>0){
            $row_catalog =  get_line_table_cache_api('registos_catalogo', "id='".$v["page_cat_id"]."' AND deleted='0'");

            $title_season = $row_catalog["nome$LG"];
        }

        $preco            = $v['valoruni_avg'];
        $_qtds            = $v["qnt_total"];

        $label_preco      = call_api_func('OBJ_money',$preco, $MOEDA['id']);
        $v["price_unit"]  = $label_preco;

        $sub_total += $v['valor_final'];

        if($v['valoruni_anterior_sem_iva']==0) $v['valoruni_anterior_sem_iva'] = $v['valoruni_sem_iva'];

        $preco_anterior_sem_iva       = $v['valoruni_anterior_sem_iva'];
        $label_preco_anterior_sem_iva = call_api_func('OBJ_money',$preco_anterior_sem_iva, $MOEDA['id']);
        $v["price_unit_anterior_sem_iva"]  = $label_preco_anterior_sem_iva;

        $preco_sem_iva       = $v['valoruni_sem_iva'];
        $label_preco_anterior_sem_iva = call_api_func('OBJ_money',$preco_sem_iva, $MOEDA['id']);
        $v["price_unit_sem_iva"]  = $label_preco_anterior_sem_iva;

        $Decimais = (int)$v['moeda_decimais'];
        if($Decimais==0) $Decimais = 2;

        $b2b_discount_price_perc = 0;
        $b2b_discount_price = 0;
        if($v['valoruni_anterior_sem_iva']>0) {
            $b2b_discount_price = $v["valoruni_anterior_sem_iva"]-$v['valoruni_sem_iva'];

            $temp_b2b_discount_price_perc = 100-(((float)$v['valoruni_sem_iva']*100)/$v['valoruni_anterior_sem_iva']);

            /*if(fmod($temp_b2b_discount_price_perc, 1) === 0.00){
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
            }else{
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $Decimais, '.', '');
            }*/
            preg_match("/\.?0*$/", $temp_b2b_discount_price_perc, $matches, PREG_OFFSET_CAPTURE);
            if ( count($matches) > 0 ){
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
            }else{
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $Decimais, '.', '');
            }

        }

        $v["b2b_discount_price"]  = call_api_func('moneyOBJ',$b2b_discount_price, $v['moeda']);
        $v["b2b_discount_raw"]   = $b2b_discount_price_perc;
        $v["b2b_discount_perc"]   = str_replace("-", "", $b2b_discount_price_perc)."%";

        $b2b_pvr_discount_perc = 0;
        $b2b_pvr_discount      = 0;
        $b2b_pvr               = 0;
        if($v['pvr_desconto_sem_iva']>0) {

            $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto_sem_iva']*100)/$v['pvr_sem_iva'];
            /*if(fmod($temp_b2b_pvr_discount_perc, 1) === 0.00){
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            }else{
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $Decimais, '.', '');
            }*/
            preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
            if ( count($matches) > 0 ){
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            }else{
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $Decimais, '.', '');
            }

            $b2b_pvr          = $v['pvr_sem_iva'];
            $b2b_pvr_discount = $v['pvr_desconto_sem_iva'];

        }else if( $v['pvr_desconto'] > 0 ){

            $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto']*100)/$v['pvr'];
            preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
            if ( count($matches) > 0 ){
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            }else{
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $Decimais, '.', '');
            }

            $b2b_pvr          = $v['pvr'];
            $b2b_pvr_discount = $v['pvr_desconto'];

        }

        $v["b2b_pvr"]               = call_api_func('OBJ_money', $b2b_pvr, $v['moeda']);
        $v["b2b_pvr_discount"]      = call_api_func('OBJ_money', $b2b_pvr_discount, $v['moeda']);
        $v["b2b_pvr_discount_raw"]  = $b2b_pvr_discount_perc;
        $v["b2b_pvr_discount_perc"] = str_replace("-", "", $b2b_pvr_discount_perc."%");


        $tam = array(
            "size"  => $v["tamanho"],
            "qnt"   => $v["qnt_total"],
        );


        $registo = call_api_func("get_line_table", "registos", "id='".$v['pid']."'");

        $tamanho = call_api_func("get_line_table", "registos_tamanhos", "id='".$registo['tamanho']."'");

        $chave = $v['sku_family'].'_'.$v['cor_id'].'_';

        $ordem_tamanho = $chave.$tamanho['nome'.$LG].'_'.$registo['variante'];

        if($ordem_tamanho=='' || strlen(trim($ordem_tamanho))<1) $ordem_tamanho = $chave.'999999999|'.$v['pid'];

        if($tamanho['ordem']=='' || strlen(trim($tamanho['ordem']))<1 || !isset($tamanho['ordem'])) $tamanho['ordem'] = $ordem_tamanho.'_'.$registo['variante'];

        $chave .= $tamanho['ordem'];


        if(isset($arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']])){

            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]['sizes'][$chave]    = $tam;


            ksort($arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]['sizes']);


            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["quantidade"]       += $_qtds;
            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["price_abs"]        += $preco;
            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["price_a"]          += $v['valor_final'];
            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]['valor_desc_final'] += $v['valor_final_f'];


            $label_disc = call_api_func('OBJ_money',$arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["discount"], $MOEDA['id']);
            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["label_discount"] = $label_disc;

            $label_preco_a = call_api_func('OBJ_money',$arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["price_a"], $MOEDA['id']);
            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["price_line"] = $label_preco_a;

            $label_preco_quan = call_api_func('OBJ_money',$arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["quantidade"]*$preco, $MOEDA['id']);

            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']]["price_quant"] = $label_preco_quan;
        }else{
            $v['sizes'][$chave]   = $tam;
            $v["quantidade"]      = $_qtds;
            $v["price_abs"]       = $preco;

            $label_preco_quan = call_api_func('OBJ_money',$_qtds*($v['valoruni']-$v['desconto']), $MOEDA['id']);

            $v["price_quant"] = $label_preco_quan;
            $v["price_a"]     = $v['valor_final'];

            $label_preco_a = call_api_func('OBJ_money',$v["price_a"], $MOEDA['id']);
            $v["price_line"] = $label_preco_a;

            $label_disc = call_api_func('OBJ_money',$v["discount"], $MOEDA['id']);
            $v["label_discount"] = $label_disc;

            $arr_prods[$v["sku_family"].$v["cor_id"].$v['valoruni_avg']] = $v;
        }

        $desconto_rappel = 0;
        if( ($v["promotion"] == 2 || $v["promotion"] == 3)  && $v["valoruni_anterior"] > 0 ){
            if((int)$v["valoruni_anterior_sem_iva"] > 0 && (int)$v["valoruni_sem_iva"] ){
                $desconto_rappel = $v["valoruni_anterior_sem_iva"] - $v["valoruni_sem_iva"];
                if($v["id_linha_orig"]<1){
                    $total_products_rappel += $v['valoruni_sem_iva'] * $v['qnt'];
                    $total_products_orig_rappel += $v['valoruni_anterior_sem_iva'] * $v['qnt'];
                }
            }else{
                $desconto_rappel = $v["valoruni_anterior"] - $v["valoruni"];
                if($v["id_linha_orig"] == 0){
                    $total_products_rappel += $v['valoruni'] * $v['qnt'];
                    $total_products_orig_rappel += $v['valoruni_anterior'] * $v['qnt'];
                }
            }
        }
        $total_rappel += $desconto_rappel * $v['qnt'];
    }

    $x = array();
    $x["response"]["user"]          = $cliente; 
    $x["response"]["order_qtd"]     = $qtd_cart;
    $x["response"]["expressions"]   = call_api_func('getAccountExpressions');
    $x["response"]["order"]         = $temp;
    $x["response"]["order"]['date'] = date("Y-m-d H:i");

    $x["response"]["shop"]          = call_api_func('OBJ_shop_mini');
    $x["response"]["lines"]         = $arr_prods;
    $x["response"]["sub_total_raw"] = $sub_total;
    $x["response"]["sub_total"]     = call_api_func('OBJ_money',$sub_total, $MOEDA['id']);

    if( $total_rappel > 0 ){

        $prec_rapple = 0;
        if($total_products_orig_rappel > 0 && $total_products_rappel > 0 ) $prec_rapple = (($total_products_orig_rappel - $total_products_rappel) /  $total_products_orig_rappel ) * 100;

        $x["response"]["total_rappel"] = call_api_func('OBJ_money', $total_rappel, $MOEDA['id']);
        $x["response"]["total_perc_rappel"] = $prec_rapple;

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

    $x["response"]["title_season"]  = $title_season;

    $x["response"]["lines_gender"]  = $arr_gender;


    $s        = "SELECT * FROM ec_mercado WHERE id='".$_SESSION['_MARKET']['id']."' LIMIT 0,1";
    $q        = cms_query($s);
    $mercado  = cms_fetch_assoc($q);

    $s        = "SELECT * FROM ec_invoice_companies WHERE id='".$mercado['entidade_faturacao']."' LIMIT 0,1";
    $q        = cms_query($s);
    $entidade = cms_fetch_assoc($q);

    $imagem   = "sysimages/logo.png";
    if((int)$entidade['id']>0){
        $imagem   = "images/cab_".$entidade['id'].".jpg?".filemtime($_SERVER['DOCUMENT_ROOT']."/images/cab_".$entidade['id'].".jpg");
    }

    $x["response"]["logo"]          = $imagem;

    $x['response']['exp_pvr']       = estr(472);
    $x['response']['exp_pvr_desc']  = estr(473);

    $x['CONFIG_OPTIONS'] = $CONFIG_OPTIONS;
    
    if($x['CONFIG_OPTIONS']['hide_prices_promo_perc_val']==0) 
        $x['CONFIG_OPTIONS']['all_prices'] = 1;

    if(is_callable('custom_controller_shopping_bag_print')) {
        call_user_func_array('custom_controller_shopping_bag_print', array(&$x));
    }

    if(file_exists("../templates/shopping_bag_print.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_base_b2b/', false, _ROOT.'/temp_twig/');
    }

    $html = $fx->printTwigTemplate("shopping_bag_print.htm",$x, true, $exp);

    $documentTemplate = '
    <!doctype html>
    <html>
        <body>
            <div id="wrapper">
                '.utf8_encode($html).'
            </div>
        </body>
    </html>';
    #echo $documentTemplate;exit;
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
          reg_g.id as id_gender,
          reg_g.nome$LG as name_gender,
          ec_enc_l.*,
          SUM(ec_enc_l.iva_valor) as valor_iva,
          SUM(ec_enc_l.qnt) as qnt_total,
          SUM((ec_enc_l.valoruni/ec_enc_l.taxa_cambio)*qnt) as valor_final,
          SUM(((ec_enc_l.valoruni-ec_enc_l.desconto)/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valor_final_f,
          AVG(ec_enc_l.valoruni) as valoruni_avg,
          SUM((ec_enc_l.valoruni_anterior/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valoruni_anterior_final,
          AVG(ec_enc_l.valoruni_anterior) as valoruni_anterior_avg
        FROM ec_encomendas_lines as ec_enc_l
        LEFT JOIN registos as reg ON SUBSTRING_INDEX(ec_enc_l.pid, '|||', -1)=reg.id
        LEFT JOIN registos_$_table as reg_g ON reg_g.id = reg.$_field
        WHERE ec_enc_l.id_cliente='".$userID."' AND ec_enc_l.status='0' AND ec_enc_l.id_linha_orig=0 AND ec_enc_l.page_cat_id='".$catalogID."'".$moreSql."
        GROUP BY ec_enc_l.pid,ec_enc_l.valoruni
        ORDER BY ec_enc_l.sku_family ASC, ec_enc_l.cor_name ASC, ec_enc_l.pid DESC";

    $res = cms_query($sql);

    $_arr_lines = Array();
    while($row = cms_fetch_assoc($res)){

        $_arr_lines[] = $row;
    }

    return $_arr_lines;
}

?>
