<?

function _getBudgetDetail($user_id=0, $budget_id=0){

    global $CONFIG_OPTIONS, $COUNTRY, $MARKET, $LG,$MOEDA;

    if( (int)$budget_id <= 0 ){
        $user_id = (int)params('user_id');
        $budget_id = (int)params('budget_id');
    }


    $userOriginalID = $user_id;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if( (int)$budget_id <= 0 || $userOriginalID <= 0 ){
        return serialize(['success' => false]);
    }

    if( (int)$_SESSION['EC_CLIENTE']['type'] < 1 ){
        $add_sql_budgets = " AND status>0 ";
    }  

    $budget = cms_fetch_assoc( cms_query('SELECT * FROM `budgets` WHERE `id`='.$budget_id.' AND `user_id`='.$userOriginalID.$add_sql_budgets) );
    
    if( empty($budget) || (int)$budget['id'] <= 0 ){
        return serialize(['success' => false, 'payload' => []]);
    }

    $iva_percentage = 0;
    $entidade_r = call_api_func("get_line_table", "ec_invoice_companies", "id='".$MARKET['entidade_faturacao']."'");
    if( $entidade_r['country'] == $COUNTRY['id'] || ( $COUNTRY['extracomunitario'] == 0 && $_SESSION['EC_USER']['tipo_utilizador'] == 2 ) ) {
    
        
        if((int)$entidade_r['apply_country_vat_rates']==1){
            $pais_s = "SELECT * FROM ec_paises WHERE id=".$COUNTRY['id']." LIMIT 0,1";
            $pais_q = cms_query($pais_s);
            $pais_r = cms_fetch_assoc($pais_q);
            
            $entidade_r['tax_rate'] = $pais_r['tax_rate'];         
        }
    
    
        $iva_percentage = (int)$entidade_r['tax_rate'];
    }


    $budget['token'] = md5($budget['id'] . "|||" . $budget['user_id'] . "|||" . $budget['created_at'] . "|||" . $budget['type']);

    $budget['status_info']                = cms_fetch_assoc( cms_query('SELECT * FROM `budgets_status` WHERE `id`='.$budget['status']) );
    $budget['status_info']['name']        = $budget['status_info']['name'.$LG];
    $budget['status_info']['description'] = $budget['status_info']['description'.$LG];
    
    $budget['products']         = getBudgetProducts($budget['id'], $iva_percentage);
    $budget['logs']             = getBudgetLogs($budget['id']);
    $budget['available_status'] = getBudgetNextStatus($budget);
    $budget['market_iva']       = $iva_percentage;

    # Observations
    if( $budget['type'] == 1 ){
        $budget['observations_log'] = array();

        $budget_obs_res = cms_query("SELECT `datahora` AS `datetime`, `obs`, `autor` AS `author` FROM `budgets_observations` WHERE `budget_id`=" . $budget['id'] . " ORDER BY `id` DESC");
        while ($budget_log = cms_fetch_assoc($budget_obs_res)) {
            $budget['observations_log'][] = $budget_log;
        }
    }
    # Observations

    #2025-08-13 Multimoto    
    #<Services>
    foreach ($budget['products'] as $k=>$item){
      $service_description = Array();
      $services_total_price = 0;
      foreach ($item['product_details']['services'] as $kk=>$item2) {
          $services_total_price += $item2['service']['0']['price']['value'];      
          $service_description[] = $item2['name']." - ".$item2['service']['0']['name']." <span class=\"small\">(".$item2['service']['0']['price']['currency']['prefix'] .number_format($item2['service']['0']['price']['value'],$MOEDA['decimais'],$MOEDA['casa_decimal'],$MOEDA['casa_milhares']) . $item2['service']['0']['price']['currency']['sufix'].")</span>"; #Descrição do serviço
      }  
      $budget['products'][$k]['services_description'] = $service_description;
      $budget['products'][$k]['services_total_price'] = (float)$services_total_price;      
    }
    #</Services>

    if( (int)$_SESSION['EC_CLIENTE']['type'] > 0 ){
        $budget['seller_access'] = 1;
    }

    return serialize(['success' => true, 'payload' => $budget]);

}


function getBudgetProducts($budget_id, $iva_percentage=0){

    if( (int)$budget_id <= 0 ){
        return [];
    }

    $budget_products     = [];
    $budget_products_res = cms_query('SELECT * FROM `budgets_lines` WHERE `budget_id`='.$budget_id.' ORDER BY `id`');

    while( $budget_product = cms_fetch_assoc($budget_products_res) ){

        if( $budget_product['product_type'] == 1 ){
            $budget_product['product_details'] = call_api_func('get_product', $budget_product['product'], '',$_GET['id']);
        } else {
            $budget_product['product_details']['iva_percentage'] = $iva_percentage;
        }

        $budget_products[] = $budget_product;
    }

    return $budget_products;

}


function getBudgetLogs($budget_id){

    if( (int)$budget_id <= 0 ){
        return [];
    }

    $budget_logs     = [];
    $budget_logs_res = cms_query('SELECT * FROM `budgets_logs` WHERE `budget_id`='.$budget_id.' ORDER BY `id` DESC');

    while( $budget_log = cms_fetch_assoc($budget_logs_res) ){
        $budget_logs[] = $budget_log;
    }

    return $budget_logs;

}

?>
