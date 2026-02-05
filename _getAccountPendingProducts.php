<?
# Usado no B2B na Página de Produtos pendentes

function _getAccountPendingProducts($page_id=null){

    global $userID, $CONFIG_OPTIONS;
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $user_id = $userID;
    $prods   = array();

    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');

    $s = "SELECT `ec_encomendas_lines`.`pid`, `ec_encomendas_lines`.`ref`, `ec_encomendas_lines`.`nome`, `ec_encomendas_lines`.`qnt`, `ec_encomendas_lines`.`qnt_confirmada`, `ec_encomendas_lines`.`cor_name`, `ec_encomendas_lines`.`tamanho`, `ec_encomendas_lines`.`order_id`, `ec_encomendas`.`order_ref`, `ec_encomendas`.`data`, `ec_encomendas_lines_props`.`property_value`
            FROM `ec_encomendas_lines`
            INNER JOIN `ec_encomendas` ON `ec_encomendas`.`id` = `ec_encomendas_lines`.`order_id`
            LEFT JOIN `ec_encomendas_lines_props` ON `property`='OBS_PENDENTE' AND `ec_encomendas_lines_props`.`order_line_id` = `ec_encomendas_lines`.`id`
            WHERE `id_cliente`='".$user_id."' AND `ec_encomendas`.`tracking_status`='42' AND `ec_encomendas_lines`.`qnt_confirmada` < `ec_encomendas_lines`.`qnt` AND `ec_encomendas_lines`.`ref` != 'PORTES'
            GROUP by `ec_encomendas_lines`.`pid`, `ec_encomendas`.`id`, `ec_encomendas_lines_props`.`property_value` ORDER BY `ec_encomendas`.`data`";
    $q = cms_query($s);

    while($r = cms_fetch_assoc($q)){

        $prods[] = array(
            "pid"                       => $r['pid'],
            "sku"                       => $r['ref'],
            "nome"                      => $r['nome'],
            "quantidade"                => $r['qnt'],
            "quantidade_confirmada"     => $r['qnt_confirmada'],
            "cor_nome"                  => $r['cor_name'],
            "tamanho_nome"              => $r['tamanho'],
            "encomenda_id"              => $r['order_id'],
            "encomenda_ref"             => $r['order_ref'],
            "encomenda_data"            => $r['data'],
            "encomenda_link"            => "?id=17&order=".$r["order_id"],
            "observacao_pendente"       => $r['property_value']
        );

    }

    $arr['products'] = $prods;

    return serialize($arr);

}

?>
