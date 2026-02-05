<?
# No caso de ser um pedido POST, a informação vem no pedido.
# No caso de ser chamado por função, a informação do produto vem por parâmetro.
function _addServiceToBudget($budget_id=0, $service_to_insert=null){

    if( (int)$budget_id == 0 ){
        $budget_id = (int)params('budget_id');
    }

    if( is_null($service_to_insert) ){

        if( !empty($_POST) ){
            $service_to_insert = $_POST;
        }else{
            $service_to_insert = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( $budget_id <= 0 || empty($service_to_insert) || is_null($service_to_insert) ){
        return serialize(['success' => false]);
    }

    $budget_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets` WHERE `id`='.$budget_id) );
    $budget_editable_states = [0,1];

    if( !isset( $budget_info['id'] ) || empty( $budget_info ) || !in_array( $budget_info['status'], $budget_editable_states ) ){
        return serialize(['success' => false]);
    }

    global $COUNTRY, $MARKET, $MOEDA;

    $markup    = $service_to_insert['markup']['percentage'] / 100;
    $discount  = $service_to_insert['discount']['percentage'] / 100;
    $preco     = $service_to_insert['price']['value'];

    $final_price_uni = $preco + ( $preco * $markup );
    $final_price_uni -= $final_price_uni * $discount;

    $quantity    = (int)$service_to_insert['quantity']['value'] <= 0 ? 1 : $service_to_insert['quantity']['value'];
    $final_price = $final_price_uni * $quantity;

    $arr                        = Array();
    $arr['budget_id']           = $budget_id;
    $arr['product']             = 0;
    $arr['product_type']        = 2;
    $arr['product_name']        = utf8_decode( $service_to_insert['description'] );
    $arr['market_id']           = $MARKET["id"];
    $arr['currency_id']         = $MOEDA['id'];
    $arr['country_id']          = $COUNTRY['id'];
    $arr['date']                = date('Y-m-d');
    $arr['datetime']            = date("Y-m-d H:i:s");
    $arr['quantity']            = $quantity;
    $arr['product_price']       = $preco;
    $arr['markup_percentage']   = $service_to_insert['markup']['percentage'];
    $arr['discount_percentage'] = $service_to_insert['discount']['percentage'];
    $arr['final_price_uni']     = $final_price_uni;
    $arr['final_price']         = $final_price;

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

    $state_observation = str_replace("{REF}", $arr['product_name'], estr2(982));
    $state_observation = str_replace("{PRICE}", $final_value_str, $state_observation);
    $state_observation = str_replace("{QTY}", $quantity, $state_observation);

    cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES(".$budget_id.", '".$state_observation."')");

    return serialize(['success' => $product_inserted, 'payload' => ['product_added' => $arr]]);
    
}

?>
