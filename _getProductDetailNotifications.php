<?

function _getProductDetailNotifications($product_id, $notification_id=0, $configurator_id=0){
    
    $notifications = [];
    
    if( (int)$product_id <= 0 ){
        return serialize( $notifications );
    }
    
    global $LG, $MOEDA, $fx, $CACHE_KEY, $MARKET;
    
    $scope = array();
    $scope['MARKET']        = $MARKET['id'];
    $scope['LG']            = $LG;
    $scope['NOTIF']         = ( (int)$notification_id > 0 ) ? 1 : 0;    
    
    $cacheid = $CACHE_KEY."PD_NOTIF_".implode('_', $scope);
    
    $dados = $fx->_GetCache($cacheid, 720);
        
    if( $dados!=false && !isset($_GET['nocache']) && (int)$notification_id == 0 ){
    
        $arr_data = unserialize($dados);
         
    }else{

        /*$sql = "SELECT `id`, `tipo`, `prestacoes_valor_".$LG."` as `prestacoes_valor`, `prestacoes_num`, `informacao_".$LG."` as `informacao`, `simulador`, `simulador_prestacao_preferencial`, `destaque`, `url_mais_info`
                FROM `product_detail_notifications`
                WHERE `ativo`=1 AND (paises LIKE '' OR paises IS NULL OR CONCAT(',',paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%')";*/
        $sql = "SELECT `id`, `tipo`, `prestacoes_valor_".$LG."` as `prestacoes_valor`, `prestacoes_num`, `informacao_".$LG."` as `informacao`, `simulador`, `simulador_prestacao_preferencial`, `destaque`, `url_mais_info`, `payment_id`
                FROM `product_detail_notifications`
                WHERE `ativo`=1 AND payment_id IN(".$MARKET['metodos_pagamento'].")";
                
        if( (int)$notification_id > 0 ){
            $sql = "SELECT `id`, `tipo`, `prestacoes_valor_".$LG."` as `prestacoes_valor`, `prestacoes_num`, `informacao_".$LG."` as `informacao`, `simulador`, `simulador_prestacao_preferencial`, `destaque`, `url_mais_info`, `payment_id`
                    FROM `product_detail_notifications`
                    WHERE `id`=".$notification_id;
        }
                    
        $not_res           = cms_query($sql);
        
        $arr_data = array();
        while( $notification = cms_fetch_assoc( $not_res ) ){
            $arr_data[] = $notification;
        }
        
        $fx->_SetCache($cacheid, serialize($arr_data), 720);
        
    }

    if( (int)$configurator_id > 0 ){
        $row_configurator = call_api_func("get_line_table", "registos_configurador_avancado", "id='".$configurator_id."' AND (idioma = '$LG' || idioma = '*')");
        $product_sku = $row_configurator['sku_final'];
                                   
        $PROD = call_api_func("get_line_table", "registos", "sku='".$row_configurator['sku']."'");   
        $PROD['sku'] = $row['sku_final'];  
           
        $preco = __getPrice($product_sku, 0, 0, $PROD, 0, $row_configurator['sku']);
         
    }else{
        $product = call_api_func("get_line_table","registos", "id=".$product_id);
        $product_sku = $product['sku'];
        
        $preco = __getPrice($product_sku);
    }
    
    

    if(search_multidimensional_array($arr_data, 'payment_id', '114') !== false){
        $index = search_multidimensional_array($arr_data, 'payment_id', '115');
        if( $index !== false){
            unset($arr_data[$index]);
        }
    }
    
    
    
    foreach ($arr_data as $key=>$notification) {
        
        $credit_option = [];
        
        require_once _ROOT."/plugins/shoppingtools/credit_simulator/CreditSimulatorFactory.php";
        $prestacao_mensal_valor = CreditSimulatorUtils::getMonthlyPaymentForNotification($notification, $preco['precophp'], $credit_option);
        
        if( (int)$credit_option['option_id'] > 0 ){

            $credit_simulator = CreditSimulatorFactory::getConnector($credit_option['option_id']);
            
            if( $credit_simulator->getSimulatorType() == 1 ){
                $credit_info = $credit_simulator->getCreditInfo($preco['precophp']);
                $credit_option['title'] = $credit_info['duration'];
            }  
        
        }
       
        if( (int)$notification_id == 0 && $prestacao_mensal_valor <= 0 ){
            continue;
        }
        
        $payment_method = call_api_func("get_line_table","ec_pagamentos", "id='".$notification['payment_id']."'");

        
        if( (int)$notification_id == 0 && ($payment_method['valor_min_enc'] > 0 && $preco['precophp'] < $payment_method['valor_min_enc']) || ($payment_method['valor_max_enc'] > 0 && $preco['precophp'] > $payment_method['valor_max_enc']) ){
            continue;
        }
        
        $prestacao_mensal_valor = api_money_format($prestacao_mensal_valor, $MOEDA['id']);

        $notification['prestacoes_valor'] = "<b>".str_replace('{VALOR}', $prestacao_mensal_valor, $notification['prestacoes_valor'])."</b>";
        $notification['informacao'] = str_replace('{VALOR}', $prestacao_mensal_valor, $notification['informacao']);
        $notification['informacao'] = str_replace('{NOME_PRESTACAO}', $credit_option['title'], $notification['informacao']);
        
        if( $notification['tipo'] != 1 && $notification['url_mais_info'] > 0 ){
            $notification['url_mais_info'] = '/index.php?id='.$notification['url_mais_info'];
        }else{
            $notification['url_mais_info'] = '';
        }
        
        $notification_image = 'images/prod_mark_notification_'.$notification['id'].'.jpg';
        if( !file_exists($_SERVER['DOCUMENT_ROOT']."/".$notification_image) ) {
            $notification_image = '';
            $notification_image_info = '';
            $notification_image_alt = '';
        }else{
            $notification_image_info = getimagesize($_SERVER['DOCUMENT_ROOT']."/".$notification_image);
            $notification_image .= '?m='.filemtime($_SERVER['DOCUMENT_ROOT'].'/'.$notification_image);
            $notification_image_alt = $payment_method['nome'.$LG];
        }
        
        $notifications[] = ['id' => $notification['id'],
                            'info_text' => $notification['prestacoes_valor'],
                            'info_text_prefix' => '',
                            'info_text_sufix' => $notification['informacao'],
                            'url_mais_info' => $notification['url_mais_info'],
                            'tipo' => $notification['tipo'],
                            'destaque' => $notification['destaque'],
                            'image' => $notification_image,
                            'image_info' => $notification_image_info,
                            'image_alt' => $notification_image_alt,
                            'simulador' => $notification['simulador'] ];
        
    }
    
    return serialize($notifications);
    
}


function search_multidimensional_array($array, $field, $value) {
    foreach ($array as $key => $subarray) {
        if ($subarray[$field] == $value) {
            return $key;
        }
    }
    return false;
}
?>
