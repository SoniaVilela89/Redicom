<?

function _getAccountOrderPrint($token=null, $view=null)
{

    global $userID, $eComm, $LG, $fx, $CONFIG_TEMPLATES_PARAMS, $MOEDA, $CONFIG_OPTIONS, $SETTINGS_LOJA;

    if(is_null($token)){
        $token = (int)params('token');
    }

    $enc_id = base64_decode($token);

    #$encomenda  = $eComm->getOrder($userID, $enc_id);
    $encomenda = get_line_table("ec_encomendas", "id='" . $enc_id . "'");

    if( (int)$encomenda["id"] == 0 ){
        return serialize( array( '0' => 0 ) );
    }

    # Pagamentos parciais
    if((int)$encomenda['percentagem_parcial'] > 0 && $encomenda['valor_anterior'] > 0 && $encomenda['tracking_status'] <= 1){
        $previous_value_temp = $encomenda['valor'];
        $encomenda['valor'] = $encomenda['valor_anterior'];
        $encomenda['valor_anterior'] = $previous_value_temp;
    }

    $cliente = get_line_table("_tusers", "id='".$encomenda["cliente_final"]."'");

    $pais = get_line_table("ec_paises", "id='".$cliente["pais"]."'", "`idioma`");

    $language = 0;
    /*if($cliente['id_lingua']>0){
        $language = $cliente['id_lingua'];
    }else{*/
        if((int)$pais["idioma"]>0){
            $language = $pais['idioma'];
        }
    /*}*/

    $idioma = get_line_table("ec_language", "id='".$language."'", "`code`");

    if( $idioma['code']=="es" ) $idioma['code']="sp";
    if( $idioma['code']=="en" ) $idioma['code']="gb";

    $LG = $idioma['code'];

    $x = array();
    $x["response"]["order_ref"]     = $encomenda["order_ref"];
    $x["response"]["order_qtd"]     = $encomenda["qtd"];
    $x["response"]["expressions"]   = get_expressions_checkout();

    $_GET['order'] = $encomenda["id"];

    #$order = call_api_func('orderOBJ', $encomenda, 1, 1);
    $order = orderOBJ($encomenda, 0, 1);

    if( trim($cliente['cod_erp']) != '' ){
        $order['billing_address']['name'] = $cliente['cod_erp'] . '<br>' . $order['billing_address']['name'];
    }

    $lines = getOrderLinesPDF($enc_id, 1);

    $sub_total = 0;
    $arr_prods = array();
    $iva = 0;
    $title_season = "";
    $arr_gender = array();
    foreach($lines as $k => $v){
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

        if( $v['marca'] != '' ){
            $v['nome'] .= ' - '.$v['marca'];
        }

        if(trim($title_season)=="" && $v["page_cat_id"]>0){

            $row_catalog =  get_line_table_cache_api('registos_catalogo', "id='".$v["page_cat_id"]."' AND deleted='0'");

            $title_season = $row_catalog["nome$LG"];
        }

        $iva              += $v["valor_iva"];

        $preco            = $v['valoruni_avg'];
        $_qtds            = $v["qnt_total"];

        $label_preco      = call_api_func('OBJ_money',$preco, $MOEDA['id']);
        $v["price_unit"]  = $label_preco;

        $sub_total += $v['valor_final'];

        if(count($v["service"]) > 0){

            foreach ($v["service"] as $key_s => $value_s) {

                $sub_total        += $value_s['valor_final_serv'];
                $iva              += $value_s["valor_servico_iva"];
                $encomenda["qtd"] += $value_s["quantidade"];
                if(isset($arr_gender["service"])){
                    $arr_gender["service"]["qtd"]  += $value_s["quantidade"];
                    $arr_gender["service"]["total"] += $value_s["valor_final_serv"];

                    $label_preco_gender = call_api_func('OBJ_money',$arr_gender["service"]["total"], $MOEDA['id']);
                    $arr_gender["service"]["label_price"] = $label_preco_gender;
                }else{
                    $arr_gender["service"] = array(
                        "name"  => $x["response"]["expressions"]["533"],
                        "qtd"   => $value_s["quantidade"],
                        "total" => $value_s["valor_final_serv"],
                        "label_price" => ""
                    );
                    $label_preco_gender = call_api_func('OBJ_money',$arr_gender["service"]["total"], $MOEDA['id']);
                    $arr_gender["service"]["label_price"] = $label_preco_gender;
                }

            }

        }


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
            preg_match("/\.?0*$/", $temp_b2b_discount_price_perc, $matches, PREG_OFFSET_CAPTURE);
            if ( count($matches) > 0 ){
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, 0, '.', '');
            }else{
                $b2b_discount_price_perc = number_format($temp_b2b_discount_price_perc, $Decimais, '.', '');
            }
        }

        $v["b2b_discount_price"]  = call_api_func('moneyOBJ',$b2b_discount_price, $v['moeda']);
        $v["b2b_discount_raw"]    = $b2b_discount_price_perc;
        $v["b2b_discount_perc"]   = str_replace("-", "", $b2b_discount_price_perc)."%";

        $b2b_pvr_discount_perc = "";
        if($v['pvr_desconto_sem_iva'] > 0 && trim($v['desconto_linha_perc']) == "") {
            $temp_b2b_pvr_discount_perc = ((float)$v['pvr_desconto_sem_iva']*100)/$v['pvr_sem_iva'];
            preg_match("/\.?0*$/", $temp_b2b_pvr_discount_perc, $matches, PREG_OFFSET_CAPTURE);
            if ( count($matches) > 0 ){
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, 0, '.', '');
            }else{
                $b2b_pvr_discount_perc = number_format($temp_b2b_pvr_discount_perc, $Decimais, '.', '');
            }
        }elseif($v['pvr_desconto_sem_iva'] > 0 && trim($v['desconto_linha_perc']) != ""){
            $b2b_pvr_discount_perc = $v['desconto_linha_perc'];
        }

        if( $v['pvr_sem_iva'] == 0 ){
            if( $v['valoruni_anterior_sem_iva'] > 0 ){
                $v['pvr_sem_iva'] = $v['valoruni_anterior_sem_iva'];
            }else{
                $v['pvr_sem_iva'] = $v['valoruni_sem_iva'];
            }
        }

        $v["b2b_pvr"]               = call_api_func('OBJ_money',$v['pvr_sem_iva'], $v['moeda']);
        $v["b2b_pvr_discount"]      = call_api_func('OBJ_money',$v['pvr_desconto_sem_iva'], $v['moeda']);
        $v["b2b_pvr_discount_raw"]  = $b2b_pvr_discount_perc;
        $v["b2b_pvr_discount_perc"] = str_replace("-", "", $b2b_pvr_discount_perc."%");


        $tam = array(
            "size"  => $v["tamanho"],
            "qnt"   => $v["qnt_total"],
        );

        $tamanho = call_api_func("get_line_table", "registos_tamanhos", "id='". $v['size_id']."'");

        $chave = $v['sku_family'].'_'.$v['cor_id'].'_';

        $ordem_tamanho = $chave.$tamanho['nome'.$LG].'_'.$v['variante'];

        if($ordem_tamanho=='' || strlen(trim($ordem_tamanho))<1) $ordem_tamanho = $chave.'999999999|'.$v['pid'];

        if($tamanho['ordem']=='' || strlen(trim($tamanho['ordem']))<1 || !isset($tamanho['ordem'])) $tamanho['ordem'] = $ordem_tamanho.'_'.$v['variante'];

        $chave .= $tamanho['ordem'];

        if($v["custom"]>0 || $v["id_linha_orig"]>0 || $v["pack"]>0){
            $chave = $v["pid"];
            if($v["pack"]>0) $chave = $v["pid"]."|||".$v["id"];
        }

        $prod_key = $v["sku_family"] . $v["cor_id"] . $v['valoruni_avg'];

        if(isset($arr_prods[$prod_key])){

            $arr_prods[$prod_key]['sizes'][$chave]    = $tam;

            ksort($arr_prods[$prod_key]['sizes']);

            $arr_prods[$prod_key]["quantidade"]       += $_qtds;
            $arr_prods[$prod_key]["price_abs"]        += $preco;
            $arr_prods[$prod_key]["price_a"]          += $v['valor_final'];

            $label_disc = call_api_func('OBJ_money',$arr_prods[$prod_key]["discount"], $MOEDA['id']);
            $arr_prods[$prod_key]["label_discount"] = $label_disc;

            $label_preco_a = call_api_func('OBJ_money',$arr_prods[$prod_key]["price_a"], $MOEDA['id']);
            $arr_prods[$prod_key]["price_line"] = $label_preco_a;

            $label_preco_quan = call_api_func('OBJ_money',$arr_prods[$prod_key]["quantidade"]*$preco, $MOEDA['id']);

            $arr_prods[$prod_key]["price_quant"] = $label_preco_quan;

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

            $arr_prods[$prod_key] = $v;

        }
    }

    $x["response"]["order"]         = $order;

    $x["response"]["shop"]          = OBJ_shop_mini();
    $x["response"]["lines"]         = $arr_prods;

    $x["response"]["sub_total"]     = call_api_func('OBJ_money',$sub_total, $MOEDA['id']);

    #$x["response"]["iva"]           = call_api_func('OBJ_money',$iva, $MOEDA['id']);
    $x["response"]["iva"]           = $x["response"]["order"]["tax_amount"];

    $x["response"]["title_season"]  = $title_season;

    $x["response"]["lines_gender"]  = $arr_gender;

    if( $encomenda['entidade_faturacao'] == 0 ){
        $mercado  = cms_fetch_assoc( cms_query("SELECT `entidade_faturacao` FROM `ec_mercado` WHERE `id`='" . $encomenda['mercado_id'] . "' LIMIT 0,1") );
        $encomenda['entidade_faturacao'] = $mercado['entidade_faturacao'];
    }

    $entidade = cms_fetch_assoc( cms_query("SELECT `id` FROM `ec_invoice_companies` WHERE `id`='".$encomenda['entidade_faturacao']."' LIMIT 0,1") );

    $imagem   = "sysimages/logo.png";
    if( (int)$entidade['id'] > 0 ){
        $imagem   = "images/cab_".$entidade['id'].".jpg?".filemtime($_SERVER['DOCUMENT_ROOT']."/images/cab_".$entidade['id'].".jpg");
    }

    $x["response"]["logo"]          = $imagem;

    $processado_por_vendedor = 0;
    $processado_por                 = $x["response"]["expressions"]["590"];
    if((int)$encomenda["b2b_vendedor"]>0){
        $processado_por_vendedor = 1;
        $row_vendedor = cms_fetch_assoc( cms_query("SELECT nome FROM _tusers_sales WHERE id='".$encomenda["b2b_vendedor"]."'") );
        $processado_por = $x["response"]["expressions"]["591"]." ".$row_vendedor["nome"];
    }elseif((int)$encomenda["b2b_id_utilizador_restrito"] > 0){
        $row_user_restrito  = cms_fetch_assoc( cms_query("SELECT nome FROM _tusers WHERE id='".$encomenda["b2b_id_utilizador_restrito"]."'") );
        $processado_por = $x["response"]["expressions"]["591"]." ".$row_user_restrito["nome"];
    }
    $x["response"]["processado_por"]          = $processado_por;
    $x["response"]["processado_por_vendedor"] = $processado_por_vendedor;

    #VALES
    // $arr_vales                      = array();
    // $vales                          = $eComm->getInvoiceChecks($enc_id);
    // foreach($vales as $k => $v){
    //     $v["price"]                 = call_api_func('OBJ_money',$v['valor_descontado'], $MOEDA['id']);

    //     $name                       = $x["response"]["expressions"][75];
    //     $arr = explode("_", $v["obs"]);
    //     if($arr[0] == "desconto-pagamento"){
    //         $name = $x["response"]["expressions"][879];
    //     }elseif($arr[0] == "pontosck"){
    //         $name = $x["response"]["expressions"][953];
    //     }

    //     $v["name"]                  = $name;
    //     $arr_vales[]                = $v;
    // }
    // $x["response"]['vales']         = $arr_vales;
    $arr_vales = $x["response"]["order"]['transactions'];
    unset($arr_vales[0]);
    foreach ($arr_vales as &$vale) {
        $vale["price"] = $vale['amount'];
        $vale["name"] = $vale["gateway"];
    }
    $x["response"]['vales'] = $arr_vales;

    #VOUCHER
    $arr_v      = array();

    if($encomenda['iva_valor']>0){
        $vouchers_s = "SELECT SUM(desconto_sem_iva*qnt) as desconto, desconto_vaucher_ref FROM ec_encomendas_lines WHERE order_id='".$enc_id."' AND ref<>'PORTES' AND desconto_vaucher_id>0 GROUP BY desconto_vaucher_id ";
    }else{
        $vouchers_s = "SELECT SUM(desconto*qnt) as desconto, desconto_vaucher_ref FROM ec_encomendas_lines WHERE order_id='".$enc_id."' AND ref<>'PORTES' AND desconto_vaucher_id>0 GROUP BY desconto_vaucher_id ";
    }

    $vouchers_q = cms_query($vouchers_s);
    while($v = cms_fetch_assoc($vouchers_q)){
        $v["price"] = call_api_func('OBJ_money',$v['desconto'], $MOEDA['id']);
        $arr_v[] = $v;
    }

    $x["response"]['voucher']       = $arr_v;



    #CREDITO
    $x["response"]["credit"]        = "";
    if((int)$encomenda['valor_credito'] > 0){
        $x["response"]["credit"]    = call_api_func('OBJ_money',$encomenda['valor_credito']-$encomenda['desconto_credito'], $MOEDA['id']);;
    }

    #Portes
    $x["response"]['portes']        = "";
    if((int)$encomenda['portes']>0){
        $x["response"]['portes']    = call_api_func('OBJ_money',$encomenda['portes'], $MOEDA['id']);
    }

    #PONTOS
    $x["response"]['pontos'] = "";
    if ((int)$encomenda['generatedPoints'] > 0) {
        $txt_generatedPoints = $encomenda['generatedPoints']." ".$x["response"]["expressions"]["350"];
        if((int)$SETTINGS_LOJA["pontos"]["campo_6"] > 0){
            $txt_generatedPoints = $encomenda['moeda_prefixo'].number_format($encomenda['generatedPoints']*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$encomenda['moeda_sufixo'];
        }
        $x["response"]['pontos'] = $txt_generatedPoints;
    }

    #IMPOSTO
    $x["response"]['imposto']       = "";
    if((int)$encomenda['imposto'] > 0){
        $x["response"]['imposto']   = call_api_func('OBJ_money',$encomenda['imposto'], $MOEDA['id']);
    }

    #CUSTO PAGAMENTO
    $x["response"]['custo_pag']     = "";
    if($encomenda['custo_pagamento'] > 0){
        $x["response"]['custo_pag'] = call_api_func('OBJ_money',$encomenda['custo_pagamento'], $MOEDA['id']);
    }

    #OBS
    $x["response"]['obs']       = "";
    $comentario = cms_fetch_assoc(cms_query("SELECT id,obs FROM ec_encomendas_log WHERE estado_novo='98' and autor='Observações' AND encomenda='$enc_id'  LIMIT 0,1"));
    if($comentario['id'] > 0){
        $x["response"]['obs']       = $comentario['obs'];
    }

    #PO NUMBER
    $x["response"]['po_number']       = "";
    $po_number = cms_fetch_assoc(cms_query("SELECT id,obs FROM ec_encomendas_log WHERE estado_novo='98' and autor='PO Number' AND encomenda='$enc_id'  LIMIT 0,1"));
    if($po_number['id'] > 0){
        $x["response"]['po_number']       = $po_number['obs'];
    }

    #DATA ENTREGA
    $x["response"]['data_entrega']    = "";
    if(trim($encomenda['b2b_data_entrega'])!='' && $encomenda['b2b_data_entrega']!="0000-00-00"){
        $x["response"]['data_entrega'] = $encomenda['b2b_data_entrega'];
    }

    $x['response']['entrega_pais'] = $encomenda['entrega_pais'];


    $site_expressions = get_expressions();

    $x['response']['exp_pvr']       = $site_expressions[472];
    $x['response']['exp_pvr_desc']  = $site_expressions[473];

    $x['CONFIG_OPTIONS'] = $CONFIG_OPTIONS;
    
    if($x['CONFIG_OPTIONS']['hide_prices_promo_perc_val']==0) 
        $x['CONFIG_OPTIONS']['all_prices'] = 1;

    $x['response']['additional_info_enc'] = nl2br(trim($cliente['additional_info_enc']));

    if( (int)$CONFIG_OPTIONS['PAYMENT_MODALITIES_MODULE_ACTIVE'] == 1 && (int)$cliente['modalidade_pagamento'] > 0 ){
        $payment_modality = cms_fetch_assoc( cms_query("SELECT `id`, `nome".$LG."` AS nome, `bloco".$LG."` AS bloco, limitar_metodos_pagamentos
                                                        FROM `modalidades_pagamento`
                                                        WHERE `id`='".$cliente['modalidade_pagamento']."' AND `nome".$LG."`!=''
                                                        LIMIT 1") );
        if( (int)$paymentModality['id'] > 0 && (trim($paymentModality['limitar_metodos_pagamentos']) == '' || (trim($paymentModality['limitar_metodos_pagamentos']) !='' && in_array($order["tracking_tipopaga"], explode(',', $paymentModality['limitar_metodos_pagamentos'] )) ))){
            $x['response']['payment_modality'] = array( "name" => $payment_modality['nome'], "desc" => $payment_modality['bloco'] );
        }
    }

    if(is_callable('custom_controller_account_order_print')) {
        call_user_func_array('custom_controller_account_order_print', array(&$x));
    }

    if(file_exists("../templates/account/account_order_print.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/account/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/'.$CONFIG_TEMPLATES_PARAMS["account_version"], false, _ROOT.'/temp_twig/');
    }

    $html = $fx->printTwigTemplate("account_order_print.htm",$x, true, []);

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


    $type_output = 'D';
    // $type_output = 'I';
    $save_cam = str_replace('/', '', $encomenda["order_ref"]).'.pdf';
    /* if($view>0){
        $type_output = 'F';
        $save_cam = _ROOT.'/downloads/orders/'.$token.'.pdf';
    } */

    $mpdf->Output($save_cam, $type_output);

    exit;

}


function getOrderLinesPDF($orderID, $without_portes=0) {
    global $LG;

    $more = '';
    if($without_portes==1) $more = 'AND ec_enc_l.ref<>"PORTES"';

    $classifier = getORderResumeClassifier();
    $_table = $classifier['table'];
    $_field = $classifier['field'];

    $sql = "SELECT
          reg_g.id as id_gender,
          reg_g.nome$LG as name_gender,
          `reg`.`tamanho` AS `size_id`,
          `reg`.`variante`,
          ec_enc_l.*,
          SUM(ec_enc_l.iva_valor) as valor_iva,
          SUM(ec_enc_l.qnt) as qnt_total,
          SUM((ec_enc_l.valoruni_sem_iva/ec_enc_l.taxa_cambio)*qnt) as valor_final,
          SUM(((ec_enc_l.valoruni_sem_iva-ec_enc_l.desconto)/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valor_final_f,
          AVG(ec_enc_l.valoruni_sem_iva) as valoruni_avg,
          SUM((ec_enc_l.valoruni_anterior_sem_iva/ec_enc_l.taxa_cambio)*ec_enc_l.qnt) as valoruni_anterior_final,
          AVG(ec_enc_l.valoruni_anterior_sem_iva) as valoruni_anterior_avg
        FROM ec_encomendas_lines as ec_enc_l
        LEFT JOIN registos as reg ON SUBSTRING_INDEX(ec_enc_l.pid, '|||', -1)=reg.id
        LEFT JOIN registos_$_table as reg_g ON reg_g.id = reg.$_field
        WHERE ec_enc_l.order_id='".$orderID."' $more AND id_linha_orig<1
        GROUP BY ec_enc_l.pid,ec_enc_l.valoruni
        ORDER BY ec_enc_l.ref='PORTES' asc, ec_enc_l.sku_family ASC, ec_enc_l.cor_name ASC, ec_enc_l.pid DESC";

    $res = cms_query($sql);

    $_arr_lines = Array();
    while($row = cms_fetch_assoc($res)){

        $row["service"] = array();

        if(trim($row["servico_add"]) != ""){
            $sql_service = "SELECT * FROM ec_encomendas_lines WHERE id_linha_orig='".$row["id"]."' ";
            $res_service = cms_query($sql_service);
            while($row_service = cms_fetch_assoc($res_service)){
                $sql_service_g = "SELECT SUM(qnt) as qnt_total, SUM((valoruni_sem_iva/taxa_cambio)*qnt) as valor_final, SUM(iva_valor) as valor_iva FROM ec_encomendas_lines WHERE pid='".$row_service["pid"]."' AND order_id='".$row_service["order_id"]."' ";
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

?>
