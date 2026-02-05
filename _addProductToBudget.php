<?php
# No caso de ser um pedido POST, a informação vem no pedido.
# No caso de ser chamado por função, a informação do produto vem por parâmetro.
function _addProductToBudget($budget_id=0, $product_to_insert=null, $type=0){

    if( (int)$budget_id == 0 ){
        $budget_id = (int)params('budget_id');
    }

    if( is_null($product_to_insert) ){

        if( !empty($_POST) ){
            $product_to_insert = $_POST;
        }else{
            $product_to_insert = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( $budget_id <= 0 || empty($product_to_insert) || is_null($product_to_insert) ){
        return serialize(['success' => false]);
    }

    $budget_info = cms_fetch_assoc( cms_query('SELECT `id`,`type`,`status` FROM `budgets` WHERE `id`='.$budget_id) );
    $budget_editable_states = [-1,0,1];

    if( !isset( $budget_info['id'] ) || empty( $budget_info ) || !in_array( $budget_info['status'], $budget_editable_states ) ){
        return serialize(['success' => false]);
    }

    global $COUNTRY, $MARKET, $MOEDA, $CONF_VAR_PID_REPLACE, $B2B, $LG;

    try {
       @include $_SERVER['DOCUMENT_ROOT']. "/custom/shared/store_settings.inc";
    } catch (Throwable $t) {
    }

    $priceList = $MARKET['lista_preco'];
    $pid       = $product_to_insert['product'];

    # Lista de Empresas
    if( $_SESSION['_MARKET']['lista_exclusiva1'] > 0 ){
        $mercad = call_api_func('get_line_table', 'ec_mercado', "id='".$MARKET["id"]."'");
        $priceList = $mercad["lista_preco"];

        # 2020-09-03
        # Se cliente Empresa com NIF validado, finaliza checkout com lista de preços sem IVA
        if( (int)$_SESSION['EC_USER']['tipo_utilizador'] == 1 && (int)$_SESSION['EC_USER']['nif_validado'] == 1 ){
            $priceList = $mercad["lista_exclusiva1"];
        }
    }

    if((int)$B2B>0){

        $page = call_api_func('get_pagina', $id, "_trubricas");
        if($page["catalogo"]>0){
            $catalogo_id = $page["catalogo"];
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }

        preparar_regras_carrinho($catalogo_id);

        if((int)$GLOBALS["REGRAS_CATALOGO"]==0) preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);

        if($GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0) $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];

    }



    $q = "SELECT registos.*,registos_precos.preco, registos_stocks.produto_digital
          FROM registos
            LEFT JOIN registos_stocks ON registos_stocks.sku=registos.sku
            INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE activo='1'
            AND  registos.id='$pid'
            AND registos_precos.idListaPreco='".$priceList."'
            AND (registos_precos.preco>0 OR registos_stocks.produto_digital=1)
          GROUP BY registos.id    
          LIMIT 0,1";

    $sql         = cms_query($q);
    $prod        = cms_fetch_assoc($sql);

    $preco       = __getPrice($prod['sku'], $priceList, 0, $prod);


    if((int)$prod['units_in_package'] > 1 && (int)$prod['package_price_auto'] == 1) {
        $preco['precophp'] = $preco['precophp'] / (int)$prod['units_in_package'];
    }

    if( $preco['precophp'] <= 0 ){
        return serialize(['success' => false]);
    }

    $markup      = $product_to_insert['markup']['percentage'] / 100;
    $discount    = $product_to_insert['discount']['percentage'] / 100;

    if((int)$CONFIG_OPTIONS['B2B_PRECO_ORCAMENTO_SOBRE'] == 1) { # PVPR

        //$preco_pvpr            = __getPrice($prod['sku'], $MARKET['lista_pvpr'], 0, $prod);
        $preco_pvpr['precophp']  = $product_to_insert['price_pvpr']['value_original'];        

        if($preco_pvpr['precophp'] <= 0) {
            $preco_pvpr = $preco;
        }

        $final_price_uni = $preco_pvpr['precophp'] + ( $preco_pvpr['precophp'] * $markup );
        $final_price_uni -= $final_price_uni * $discount;
        if( $budget_info['type'] == 0 && $final_price_uni < $preco_pvpr['precophp'] ){
            $final_price_uni = $preco_pvpr['precophp'];
        }

    } else {

        $final_price_uni = $preco['precophp'] + ( $preco['precophp'] * $markup );
        $final_price_uni -= $final_price_uni * $discount;
        if( $budget_info['type'] == 0 && $final_price_uni < $preco['precophp'] ){
            $final_price_uni = $preco['precophp'];
        }

    }

    $quantity    = (int)$product_to_insert['quantity']['value'] <= 0 ? 1 : $product_to_insert['quantity']['value'];
    
    #2025-08-13 Multimoto    
    #<Services>  
    $services = call_api_func('get_services',$prod['servicos'],$preco['precophp']);
    $service_total_price = 0;
    foreach ($services as $k=>$item){ 
          $service_total_price += $item['service']['0']['price']['value'];
         
    }
    
    $final_price_uni += $service_total_price;
    #</Services>    
    
    $final_price = $final_price_uni * $quantity;

    $prod_name = $res_prod_name = $prod['nome'.$LG];

    if((int)$prod['units_in_package'] > 1 && (int)$prod['package_type'] > 0) {

        $embalagem = cms_fetch_assoc(cms_query("SELECT nome$LG as nome FROM `registos_embalagens` WHERE `id`='".$prod['package_type']."'"));

        if((int)$prod['package_price_auto'] == 1) {
            $res_prod_name  = $prod_name." (".($quantity / (int)$prod['units_in_package'])."x ".$embalagem['nome'].")";
        }

        if((int)$prod['package_price_auto'] == 3) {
            $unidvenda = cms_fetch_assoc(cms_query("SELECT nome$LG as nome FROM `registos_unidades_venda` WHERE `id`='".$prod['sales_unit']."'"));
            $prod_name = $res_prod_name = $prod_name." (".$embalagem['nome']." ".(int)$prod['units_in_package']." ".$unidvenda['nome'].")";
        }

    }



    $arr                        = Array();
    $arr['budget_id']           = $budget_id;
    $arr['product']             = $prod['id'];
    $arr['product_sku_group']   = $prod['sku_group'];
    $arr['product_name']        = $prod_name;
    $arr['product_type']        = 1;
    $arr['market_id']           = $MARKET["id"];
    $arr['currency_id']         = $MOEDA['id'];
    $arr['country_id']          = $COUNTRY['id'];
    $arr['date']                = date('Y-m-d');
    $arr['datetime']            = date("Y-m-d H:i:s");
    $arr['quantity']            = $quantity;
    $arr['product_price']       = $preco['precophp'];
    $arr['markup_percentage']   = $product_to_insert['markup']['percentage'];
    $arr['discount_percentage'] = $product_to_insert['discount']['percentage'];
    $arr['final_price_uni']     = $final_price_uni;
    $arr['final_price']         = $final_price;
    $arr['price_pvpr']          = ((int)$CONFIG_OPTIONS['B2B_PRECO_ORCAMENTO_SOBRE'] == 1) ? $preco_pvpr['precophp'] : 0;

    foreach( $arr as $campo=>$valor ){
        $f[] = "`".$campo."`";
        $v[] = "'".safe_value($valor)."'";
    }
    
    $product_inserted    = cms_query("INSERT INTO `budgets_lines` (" . implode(",",$f) . ") VALUES(". implode(",",$v) .")");
    $product_inserted_id = cms_insert_id();

    if( !$product_inserted || (int)$product_inserted_id <= 0 ){
        return serialize(['success' => false]);
    }

    $arr['id'] = $product_inserted_id;

    $final_value_str = $MOEDA['prefixo'].number_format($final_price, $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];

    $state_observation = str_replace("{REF}", $prod['sku'], estr2(981));
    $state_observation = str_replace("{PRICE}", $final_value_str, $state_observation);
    $state_observation = str_replace("{QTY}", $quantity, $state_observation);

    cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES(".$budget_id.", '".$state_observation."')");

    $arr['product_name'] = $res_prod_name;

    return serialize(['success' => $product_inserted, 'payload' => ['product_added' => $arr]]);

}

?>
