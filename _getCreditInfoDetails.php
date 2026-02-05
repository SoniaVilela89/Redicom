<?

function _getCreditInfoDetails($credit_id, $product_id, $requested_value=0, $configurator_id=0){
    
    if( ( (int)$product_id <= 0 && $requested_value <= 0 ) || (int)$credit_id <= 0 ){
        return serialize( [] );
    }
    
    global $LG;
    
    if( (int)$product_id > 0 ){


        if( (int)$configurator_id > 0 ){
            $row_configurator = call_api_func("get_line_table", "registos_configurador_avancado", "id='".$configurator_id."' AND (idioma = '$LG' || idioma = '*')");
            $product_sku = $row_configurator['sku_final'];
                                       
            $PROD = call_api_func("get_line_table", "registos", "sku='".$row_configurator['sku']."'");   
            $PROD['sku'] = $row['sku_final'];  
               
            $preco_info = __getPrice($product_sku, 0, 0, $PROD, 0, $row_configurator['sku']);
             
        }else{
            $product = call_api_func("get_line_table","registos", "id=".$product_id);
            $product_sku = $product['sku'];
            
            $preco_info = __getPrice($product_sku);
        }
        

        $preco = $preco_info['precophp'];
        
    }else{
        $preco = $requested_value;
    }

    require_once _ROOT."/plugins/shoppingtools/credit_simulator/CreditSimulatorFactory.php";
    
    $credit_cetelem = CreditSimulatorFactory::getConnector($credit_id);
    $return_value = $credit_cetelem->getCreditInfo($preco);
    
    return serialize( $return_value );
    
}

?>
