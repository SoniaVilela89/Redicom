<?

function _getQuickBuyResume($page_id=0)
{

    global $userID, $CONFIG_OPTIONS;

    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();

    $row = call_api_func('get_pagina', $page_id, "_trubricas");

    $arr['quickbuy_resume'] = "";
    $arr['catalog']         = 0;

    if((int)$row["catalogo"] == 0) $row["catalogo"] = $_SESSION['_MARKET']['catalogo'];

    if((int)$CONFIG_OPTIONS['resumo_compra_rapida']>0){
        $classifier = getORderResumeClassifier();

        preparar_regras_carrinho($row["catalogo"]);

        if($row["catalogo"] != (int)$GLOBALS["REGRAS_CATALOGO"]) $row["catalogo"] = $_SESSION['_MARKET']['catalogo'];

        $arr['quickbuy_resume'] = getLinesQuickBuyResume($row["catalogo"], $userID, $classifier);
        $arr['catalog']         = $row["catalogo"];
    }

    if(is_callable('custom_quick_buy_resume')) {
        call_user_func_array('custom_quick_buy_resume', array(&$arr));
    }

    return serialize($arr);
}


function getLinesQuickBuyResume($catalogo, $userid, $classifier = ['table' => 'generos', 'field' => 'genero']){

    global $LG, $MOEDA;

    $arr_lines = array();
    $desconto = array(
        "name"          => estr(93),
        "quantity"      => 0,
        "qnt_ref_color" => 0,
        "value"         => 0
    );

    $total = array();

    $_field = $classifier['field'];
    $_table = $classifier['table'];

    $sql = "SELECT r_g.nome$LG as classifier_name, r.$_field, sum(enc_l.qnt) as qnt_total,
                  SUM((enc_l.valoruni)*enc_l.qnt) as valor_final,
                  SUM((enc_l.valoruni_desconto)*enc_l.qnt) as valoruni_desconto_final
              FROM ec_encomendas_lines as enc_l
                INNER JOIN registos as r ON r.id=enc_l.pid
                INNER JOIN registos_$_table as r_g ON r_g.id=r.$_field
              WHERE enc_l.id_cliente='".$userid."' AND enc_l.status='0' AND enc_l.page_cat_id='".$catalogo."'
              GROUP BY enc_l.sku_family, enc_l.cor_id
              ORDER BY qnt_total DESC";

    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        $total["name"]          = estr(442);
        $total["quantity"]      += $row["qnt_total"];
        $total["qnt_ref_color"] += 1;
        $total["value"]         += $row["valor_final"];

        if($row["valoruni_desconto_final"]>0) $desconto["quantity"]      += $row["qnt_total"];
        if($row["valoruni_desconto_final"]>0) $desconto["qnt_ref_color"] += 1;
        if($row["valoruni_desconto_final"]>0) $desconto["value"]         += $row["valoruni_desconto_final"];

        $arr_lines[$row[$_field]]["name"]          = $row["classifier_name"];
        $arr_lines[$row[$_field]]["quantity"]      += $row["qnt_total"];
        $arr_lines[$row[$_field]]["qnt_ref_color"] += 1;
        $arr_lines[$row[$_field]]["value"]         += $row["valor_final"];
    }

    if (count($arr_lines) > 5) {
        $tmp1 = array_slice($arr_lines, 0, 5, true);
        $tmp2 = array_slice($arr_lines, 5,count($arr_lines), true);
        $arr_lines = $tmp1;
        $tmp3 = [];
        foreach ($tmp2 as $arr) {
            foreach ($arr as $key => $value) {
                $tmp3[$key] += $value;
            }
        }
        $tmp3['name'] = 'Outros';
        array_push($arr_lines, $tmp3);
        unset($tmp1,$tmp2, $tmp3);
    }

    if(count($arr_lines)>0){
        array_push($arr_lines, $desconto);
        array_push($arr_lines, $total);
    }

    if(count($arr_lines)>0){
        foreach ($arr_lines as $k=>$v) {
            $arr_lines[$k]["value"] = call_api_func('OBJ_money', $v["value"], $MOEDA['id']);
        }
    }

    return $arr_lines;
}

?>
