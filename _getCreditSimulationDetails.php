<?

function _getCreditSimulationDetails($simulator_id, $product_id, $requested_value=0, $notification_id=0, $configurator_id=0){
    
    if( ( (int)$product_id <= 0 && $requested_value <= 0 ) || (int)$simulator_id <= 0 ){
        return serialize( [] );
    }
    
    global $LG;
    
    $simulator = call_api_func("get_line_table", "b2c_simulador_credito", "id=".$simulator_id);
    $preco = $requested_value;
    $product_info = [];
    
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
        
        $product_info['preco'] = $preco;
        
    }
    
    
    $simulator_image = 'images/sim_credito_'.$simulator['id'].'.jpg';
    if( !file_exists($_SERVER['DOCUMENT_ROOT']."/".$simulator_image) ) {
        $simulator_image = '';
    }else{
        $simulator_image .= '?m='.filemtime($_SERVER['DOCUMENT_ROOT'].'/'.$simulator_image);
    }
    
    $mais_info = [];
    if( (int)$simulator['url_mais_info'] != 0 ){
        $mais_info = [ 'url' => '/index.php?id='.$simulator['url_mais_info'], 'text' => $simulator['url_mais_info_text_'.$LG] ];
    }
    
    require_once _ROOT."/plugins/shoppingtools/credit_simulator/CreditSimulatorFactory.php";

    $simulator_info = [ 'titulo' => $simulator['nome'.$LG],
                        'descricao' => $simulator['descricao_'.$LG],
                        'opcoes' => CreditSimulatorUtils::getAllValidCreditOptionsForSimulator($simulator_id, $preco),
                        'image' => $simulator_image,
                        'mais_info' => $mais_info,
                        'product_info' => $product_info];
                        
    if( (int)$notification_id > 0 ){
    
        $prod_notification = call_api_func("get_line_table", "product_detail_notifications", "id=".$notification_id);
    
        $prestacao_preferencial = $prod_notification['simulador_prestacao_preferencial'];
        
        $prestacao_preferencial_posicao = array_search($prestacao_preferencial, array_column($simulator_info['opcoes'], 'id'));
        
        if( $prestacao_preferencial_posicao !== false ){
            $simulator_info['prestacao_preferencial'] = $prestacao_preferencial_posicao;
        }
    
    }
    
    return serialize($simulator_info);
    
}

?>
