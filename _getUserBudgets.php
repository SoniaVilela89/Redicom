<?
function _getUserBudgets($user_id=0, $type=0){

    global $LG, $CONFIG_OPTIONS;

    if( (int)$user_id <= 0 ){
        $user_id    = (int)params('user_id');
        $type       = (int)params('type');
    }

    $userOriginalID = $user_id;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if( (int)$userOriginalID <= 0 ){
        return serialize(['success' => false]);
    }

    if( (int)$_SESSION['EC_CLIENTE']['type'] < 1 ){
        $add_sql_budgets = " AND status>0 ";
    }

    $budgets_res = cms_query('SELECT * FROM `budgets` WHERE `user_id`='.$userOriginalID." AND `type`='".$type."' $add_sql_budgets ORDER BY `id` DESC");

    $budgets['budgets'] = [];
    $budgets_status_aux = [];

    while( $budget_info = cms_fetch_assoc($budgets_res) ){

        $budget_temp = $budget_info;

        $budget_temp['last_log'] = cms_fetch_assoc( cms_query('SELECT * FROM `budgets_logs` WHERE `budget_id`='.$budget_info['id'].' ORDER BY `id` DESC LIMIT 1') );
        if( strlen($budget_temp['last_log']['observation']) > 120 ){
            $budget_temp['last_log']['observation'] = substr($budget_temp['last_log']['observation'], 0, 120)."...";
        }
        
        $budget_status_info = $budgets_status_aux[ $budget_info['status'] ];

        if( empty( $budget_status_info ) ){
            $budget_status_info                = cms_fetch_assoc( cms_query('SELECT * FROM `budgets_status` WHERE `id`='.$budget_info['status']) );
            $budget_status_info['name']        = $budget_status_info['name'.$LG];
            $budget_status_info['description'] = $budget_status_info['description'.$LG];
    
            
            $budgets_status_aux[ $budget_info['status'] ] = $budget_status_info;
            $budgets_status_aux[ $budget_info['status'] ]['total_budgets'] = 1;
            
        }else{
            $budgets_status_aux[ $budget_info['status'] ]['total_budgets'] += 1;
            unset( $budget_status_info['total_budgets'] );
        }
        
        $budget_temp['status_info']    = $budget_status_info;
        $budget_temp['products']       = getBudgetProducts($budget_info['id']);
        $budget_temp['status_history'] = getBudgetStatusHistory($budget_temp,$type);

        $budgets['budgets'][] = $budget_temp;
    }
    
    usort($budgets_status_aux, function($state1, $state2){ return strcmp($state1["process_position"], $state2["process_position"]); }); # Sorts the array by "process_position" ASC
    $budgets['all_status_info'] = $budgets_status_aux;

    return serialize(['success' => true, 'payload' => $budgets]);

}


function getBudgetProducts($budget_id){

    if( (int)$budget_id <= 0 ){
        return [];
    }

    $budget_products     = [];
    $budget_products_res = cms_query('SELECT * FROM `budgets_lines` WHERE `budget_id`='.$budget_id.' ORDER BY `id`');

    while( $budget_product = cms_fetch_assoc($budget_products_res) ){
        $budget_products[] = $budget_product;
    }

    return $budget_products;

}

?>
