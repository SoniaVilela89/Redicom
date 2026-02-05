<?
function _updateBudgetProducts($budget_id=0, $products_to_update=null){

    if( (int)$budget_id <= 0 ){
        $budget_id = (int)params('budget_id');
    }

    if( is_null($products_to_update) ){

        if( !empty($_POST) ){
            $products_to_update = $_POST;
        }else{
            $products_to_update = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( $budget_id <= 0 || empty($products_to_update) || is_null($products_to_update) ){
        return serialize(['success' => false]);
    }

    $budget_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets` WHERE `id`='.$budget_id) );
    

    if( (int)$products_to_update['pending'] == 0 && $budget_info['status'] == -1 ){
        cms_query('UPDATE `budgets` SET `status`=1 WHERE `id`='.$budget_id);
    }    
    
    $budget_editable_states = [-1,0,1];

    if( !isset( $budget_info['id'] ) || empty( $budget_info ) || !in_array( $budget_info['status'], $budget_editable_states ) ){
        return serialize(['success' => false]);
    }

    $budget_info['products'] = [];

    if( isset( $products_to_update['remove'] ) && !empty( $products_to_update['remove'] ) ){ # Removes the IDs received

        $products_delete_arr_ids = array_column( $products_to_update['remove'], 'id' );
        $products_delete_sql_ids = implode(',', $products_delete_arr_ids);

        if( trim($products_delete_sql_ids) != '' ){
            cms_query("DELETE FROM `budgets_lines` WHERE `budget_id`=".$budget_id." AND `id` IN(".$products_delete_sql_ids.")");
        }

    }

    if( isset( $products_to_update['products'] ) && !empty( $products_to_update['products'] ) ){ # Adds if no ID or Updates given the ID in the array structure

        foreach( $products_to_update['products'] as $product_info){

            if( isset( $product_info['id'] ) && (int)$product_info['id'] > 0 ){

                $product_budget = cms_fetch_assoc( cms_query("SELECT * FROM `budgets_lines` WHERE `budget_id`=".$budget_id." AND `id`=".$product_info['id']) );

                if( $product_budget['markup_percentage'] != $product_info['markup']['percentage'] ||
                    $product_budget['discount_percentage'] != $product_info['discount']['percentage'] ||
                    $product_budget['quantity'] != $product_info['quantity']['value'] || 
                    $product_budget['product_price'] != $product_info['price']['value']
                ){


                    try {
                       @include $_SERVER['DOCUMENT_ROOT']. "/custom/shared/store_settings.inc";
                    } catch (Throwable $t) {
                    }

                    $markup             = $product_info['markup']['percentage'] / 100;
                    $discount           = $product_info['discount']['percentage'] / 100;
                    $quantity           = (int)$product_info['quantity']['value'] <= 0 ? 1 : $product_info['quantity']['value'];
                    $product_price_pvpr = 0;

                    if( $markup <= 0 && $product_budget['product_price'] != $product_info['price']['value_original'] ){
                        $product_price = $product_info['price']['value_original'];
                    }else{
                        $product_price = $product_budget['product_price'];
                    }

                    $product_price_sale = $product_price;

                    if((int)$CONFIG_OPTIONS['B2B_PRECO_ORCAMENTO_SOBRE'] == 1) { # PVPR

                        if($product_info['price_pvpr']['value_original'] > 0) {
                            $product_price_pvpr = $product_info['price_pvpr']['value_original'];
                            $product_price_sale = $product_price_pvpr;
                        } else {
                            $product_price_pvpr = $product_price_sale;
                        }
                        
                    }

                    $product_price_with_markup = $product_price_sale + ( $product_price_sale * $markup );
                    $final_price_uni = $product_price_with_markup;

                    if($product_info['type'] == 2) { # serviço
                        $product_price_pvpr = $product_price;
                        $final_price_uni = $product_price;
                    }

                    $final_price_uni -= $final_price_uni * $discount;
                    if( $product_budget['discount_percentage'] == $product_info['discount']['percentage'] && ($budget_info['type'] == 0 && $product_budget['product_type'] != 2 && $final_price_uni < $product_price_sale )){
                        $product_info['discount']['percentage'] = round( 100 - ( ( $product_price_sale * 100 ) / $product_price_with_markup ), 2 );
                        $final_price_uni = $product_price_sale;
                    }

                    #2025-08-13 Multimoto    
                    #<Services>  
                      $prod = cms_fetch_assoc(cms_query("SELECT servicos FROM registos WHERE id='".$product_info['product']."'"));
                      $services = call_api_func('get_services',$prod['servicos'],0);
                      $service_total_price = 0;
                      foreach ($services as $k=>$item){ 
                            $service_total_price += $item['service']['0']['price']['value'];
                           
                      }
                      $final_price_uni += $service_total_price;
                    #<Services>

                    $final_price = $final_price_uni * $quantity;

                    $product_budget['product_price']       = $product_price;
                    $product_budget['markup_percentage']   = $product_info['markup']['percentage'];
                    $product_budget['discount_percentage'] = $product_info['discount']['percentage'];
                    $product_budget['quantity']            = $product_info['quantity']['value'];
                    $product_budget['final_price_uni']     = $final_price_uni;
                    $product_budget['final_price']         = $final_price;

                    cms_query("UPDATE `budgets_lines` SET `markup_percentage`=".$product_info['markup']['percentage'].",
                                                          `discount_percentage`=".$product_info['discount']['percentage'].",
                                                          `quantity`=".$product_info['quantity']['value'].",
                                                          `final_price`=".$final_price.",
                                                          `final_price_uni`=".$final_price_uni.",
                                                          `product_price`=".$product_price.",
                                                          `price_pvpr`=".$product_price_pvpr."
                                                      WHERE `budget_id`=".$budget_id." AND `id`=".$product_info['id']);

                }

            }else{
                
                require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addProductToBudget.php';
                require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addServiceToBudget.php';
                
                switch( $product_info['type'] ){
                    case '1':
                        $response = _addProductToBudget($budget_id, $product_info, (int)$budget_info['type']);
                        break;
                    case '2':
                        $response = _addServiceToBudget($budget_id, $product_info);
                        break;
                }
                
                $response        = unserialize($response);
                $product_budget = $response['response']['product_added'];

            }

            $budget_info['products'][] = $product_budget;

        }

    }

    update_budget_totals($budget_id);

    return serialize(['success' => $update_success, 'payload' => $budget_info]);

}

?>
