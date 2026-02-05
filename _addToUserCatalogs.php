<?php

function _addToUserCatalogs() {
    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;


    if (empty($_POST)) {
        $_POST = json_decode(file_get_contents('php://input'), true);
    }    

    $pid = (int)$_POST['product_id'];
    $arr_catalogs = $_POST['catalogs'];
    $last_request = (int)$_POST['last_request']; # ultimo pedido - usado quando são adicionadas várias referências de uma só vez

    if ($pid < 1 || empty($arr_catalogs) || is_null($arr_catalogs)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid or missing catalog id or product id']);
    }
    
    global $userID, $CONFIG_OPTIONS, $COUNTRY, $MARKET, $MOEDA, $CONF_VAR_PID_REPLACE, $B2B, $LG;

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
    
    foreach($arr_catalogs as $k => $v){
        $catalog_id = $v;
        
        $catalog_info = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = '$catalog_id' AND type = $TYPE"));
        
        $hasOriginalID = false;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            // $user_id = $_SESSION['EC_USER']['id_original'];
            $hasOriginalID = true;
        }
        
        if ((!isset($catalog_info['id']) || empty($catalog_info)) || ((int)$catalog_info['user_id'] != $userID && !$hasOriginalID)) {
            continue;
        }
        
        if ($catalog_info['status'] == $CATALOG_CONCLUDED_ID) {
            continue;
        }

        $product_in_catalog = get_line_table("budgets_lines", "`budget_id`='" . $catalog_id . "' AND `product`='" . $product['id'] . "'", "COUNT(`id`) AS `quantity`");
        if ((int)$product_in_catalog['quantity'] > 0) {
            continue;
        }

        $arr_insert = [
            'budget_id' => $catalog_id,
            'product' => $product['id'],
            'product_sku_group' => $product['sku_group'],
            'product_name' => $product['nome'.$LG],
            'product_type' => 1,
            'market_id' => $MARKET["id"],
            'currency_id' => $MOEDA['id'],
            'country_id' => $COUNTRY['id'],
            'date' => date('Y-m-d'),
            'datetime' => date("Y-m-d H:i:s"),
            'quantity' => 1,
            'product_price' => $preco['precophp'],
            'final_price_uni' => $preco['precophp'],
            'final_price' => $preco['precophp']
        ];

        $f = array();
        $v = array();
        foreach ($arr_insert as $campo => $valor) {
            $f[] = $campo;
            $v[] = "'" . safe_value($valor) . "'";
        }
        
        $product_inserted    = cms_query("INSERT INTO budgets_lines (" . implode(",", $f) . ") VALUES (" . implode(",", $v) . ")");
        $product_inserted_id = cms_insert_id();

        if (!$product_inserted || (int)$product_inserted_id < 1) {
            continue;
        }

        $arr_insert['id'] = $product_inserted_id;

        $state_observation = str_replace("{REF}", $product['sku'], estr2(981));
        $state_observation = str_replace("{PRICE}", '', $state_observation);
        $state_observation = str_replace("{QTY}", 1, $state_observation);

        $log_inserted = cms_query("INSERT INTO budgets_logs (budget_id, observation) VALUES(" . $catalog_id . ", '" . $state_observation . "')");
        $log_inserted_id = cms_insert_id();

    }

    if((int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'] > 0 && $last_request == 0) {
        return serialize(['success' => 1, 'catalog_qty' => 0]);
    }

    $catalogs = get_line_table("budgets", "user_id='".$userID."' AND type='".$TYPE."' AND status != '".$CATALOG_CONCLUDED_ID."'", "GROUP_CONCAT(id) as catalogs");

    if((int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'] == 1) {
        $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product_sku_group`='" . $product['sku_group'] . "'", "COUNT(DISTINCT(`budget_id`)) AS `quantity`");
    } else {
        $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product`='" . $product['id'] . "'", "COUNT(`id`) AS `quantity`");
    }

    return serialize(['success' => 1, 'catalog_qty' => (int)$product_in_catalog['quantity']]);
}
