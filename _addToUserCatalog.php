<?php

function _addToUserCatalog($catalog_id = 0, $product_to_insert = null) {

    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;

    if ((int)$catalog_id == 0) {
        $catalog_id = (int)params('catalog_id');
    }

    if (is_null($product_to_insert)) {
        if (!empty($_POST)) {
            $product_to_insert = $_POST;
        } else {
            $product_to_insert = json_decode(file_get_contents('php://input'), true);
        }
    }

    $pid = $product_to_insert['product_id'];

    if ($catalog_id < 1 || empty($product_to_insert) || is_null($product_to_insert)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid or missing catalog id or product id']);
    }

    $catalog_info = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = '$catalog_id' AND type = $TYPE"));
    global $userID, $CONFIG_OPTIONS;
    $hasOriginalID = false;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        // $user_id = $_SESSION['EC_USER']['id_original'];
        $hasOriginalID = true;
    }
    
    if ((!isset($catalog_info['id']) || empty($catalog_info)) || ((int)$catalog_info['user_id'] != $userID && !$hasOriginalID)) {
        return serialize(['success' => 0, 'error' => 'Error - Unknown or invalid catalog']);
    }
    
    if ($catalog_info['status'] == $CATALOG_CONCLUDED_ID) {
        return serialize(['success' => 0, 'error' => 'Error - Catalog already closed']);
    }
    

    $product_in_catalog = get_line_table("budgets_lines", "`budget_id`='" . $catalog_id . "' AND `product`='" . $pid . "'", "COUNT(`id`) AS `quantity`");
    if ($product_in_catalog['quantity'] > 0) {
        return serialize(['success' => 0, 'error' => 'Error - Product already in catalog']);
    }

    global $COUNTRY, $MARKET, $MOEDA, $CONF_VAR_PID_REPLACE, $B2B;
    
    $priceList = $MARKET['lista_preco'];
    
    # Lista de Empresas
    if($_SESSION['_MARKET']['lista_exclusiva1']>0){
        $mercad = call_api_func('get_line_table', 'ec_mercado', "id='".$_SESSION['_MARKET']["id"]."'");         
        $priceList = $mercad["lista_preco"];
        
        
        if($mercad['entidade_faturacao']>0){    
            $entidade_r = call_api_func('get_line_table', 'ec_invoice_companies', "id='".$mercad["entidade_faturacao"]."'");         
        }
        
        # 2020-09-03
        # Se cliente Empresa com NIF validado, finaliza checkout com lista de preços sem IVA se paisfor diferente da entidade faturadora
        if((int)$_SESSION['EC_USER']['tipo_utilizador']==1 && (int)$_SESSION['EC_USER']['nif_validado']==1 && ($entidade_r['id']>0 && $_SESSION['_COUNTRY']['id']!=$entidade_r['country']) ){
            $priceList = $mercad["lista_exclusiva1"];            
        }
    }
    
    
    
    
    $catalogo_id = $_SESSION['_MARKET']['catalogo'];
    
    preparar_regras_carrinho($catalogo_id);
    
    if ((int)$GLOBALS["REGRAS_CATALOGO"] == 0) {
        preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);
    }
    
    if ($GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"] > 0) {
        $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];
    }
    

    $sql = "SELECT registos.*,registos_precos.preco, registos_stocks.produto_digital
          FROM registos
            LEFT JOIN registos_stocks ON registos_stocks.sku=registos.sku
            INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE activo='1'
            AND  registos.id='$pid'
            AND registos_precos.idListaPreco='" . $priceList . "'
            AND (registos_precos.preco>0 OR registos_stocks.produto_digital=1)
          GROUP BY registos.id    
          LIMIT 0,1";

    $product = cms_fetch_assoc(cms_query($sql));
    if (!isset($product)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Unknown product']);
    }

    $preco = __getPrice($product['sku'], $priceList, 0, $product);
    if ($preco['precophp'] <= 0) {
        return serialize(['success' => 0, 'error' => 'Error - Problem getting prices']);
    }

    $markup = $product_to_insert['markup']['percentage'] / 100;
    $discount = $product_to_insert['discount']['percentage'] / 100;

    $final_price_uni = $preco['precophp'] + ($preco['precophp'] * $markup);
    $final_price_uni -= $final_price_uni * $discount;
    if ($catalog_info['type'] == 0 && $final_price_uni < $preco['precophp']) {
        $final_price_uni = $preco['precophp'];
    }

    $quantity = (int)$product_to_insert['quantity']['value'] <= 0 ? 1 : $product_to_insert['quantity']['value'];
    $final_price = $final_price_uni * $quantity;

    $arr_insert = [
        'budget_id' => $catalog_id,
        'product' => $product['id'],
        'product_type' => 1,
        'market_id' => $MARKET["id"],
        'currency_id' => $MOEDA['id'],
        'country_id' => $COUNTRY['id'],
        'date' => date('Y-m-d'),
        'datetime' => date("Y-m-d H:i:s"),
        'quantity' => $quantity,
        'product_price' => $preco['precophp'],
        'markup_percentage' => $product_to_insert['markup']['percentage'],
        'discount_percentage' => $product_to_insert['discount']['percentage'],
        'final_price_uni' => $final_price_uni,
        'final_price' => $final_price
    ];

    foreach ($arr_insert as $campo => $valor) {
        $f[] = $campo;
        $v[] = "'" . safe_value($valor) . "'";
    }

    $product_inserted    = cms_query("INSERT INTO budgets_lines (" . implode(",", $f) . ") VALUES (" . implode(",", $v) . ")");
    $product_inserted_id = cms_insert_id();

    if (!$product_inserted || (int)$product_inserted_id < 1) {
        return serialize(['success' => 0, 'error' => 'Error - Problem adding product to user catalog']);
    }

    $arr_insert['id'] = $product_inserted_id;

    $state_observation = str_replace("{REF}", $product['sku'], estr2(981));
    $state_observation = str_replace("{PRICE}", '', $state_observation);
    $state_observation = str_replace("{QTY}", $quantity, $state_observation);

    $log_inserted = cms_query("INSERT INTO budgets_logs (budget_id, observation) VALUES(" . $catalog_id . ", '" . $state_observation . "')");
    $log_inserted_id = cms_insert_id();

    if (!$log_inserted || (int)$log_inserted_id <= 0) {
        return serialize(['success' => 0, 'error' => 'Error - Problem creating log']);
    }

    return serialize(['success' => $product_inserted, 'payload' => ['product_added' => $arr_insert]]);
}
