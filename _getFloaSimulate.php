<?
function _getFloaSimulate($price=0, $payment_id=0){  
    
    global $_CHECKOUT_VER, $MOEDA, $LG, $CACHE_KEY, $fx;
    
    if(is_null($price)){
        $price              = params('price');
        $payment_id         = (int)params('payment_id');
    }
    
    $price = base64_decode($price);
    
    $arr = array();

    $scope = array();
    $scope['payment_id']        = $payment_id;
    $scope['MOEDA_ID']          = $MOEDA['id'];
    $scope['LG']                = $LG;
    $scope['price']             = $price;
    
    $_FLOAScacheid = $CACHE_KEY."FLOASML_".$payment_id.'_'.md5(serialize($scope));
              
    $dados = $fx->_GetCache($_FLOAScacheid, 1440); #1 dia

    if ($dados!=false && !isset($_GET['nocache'])){
        
        $arr = unserialize($dados);  
                         
    }else{
                
        if((int)$payment_id==0){
            $payment_info = cms_fetch_assoc( cms_query("SELECT * FROM ec_pagamentos WHERE id IN (114,115) AND activo=1 AND id IN (".$_SESSION['_MARKET']['metodos_pagamento'].") ORDER BY id ASC") );        
        }else{
            $payment_info = cms_fetch_assoc( cms_query("SELECT * FROM ec_pagamentos WHERE id='".$payment_id."'") );
        }

        
        require_once $_SERVER['DOCUMENT_ROOT'].'/checkout/'.$_CHECKOUT_VER.'floa_pay/funcs.php';

        $response_data = doFloaAuthentication($payment_info);

        $authentication_token = $response_data['token'];
        if( empty($authentication_token) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
        }

        $amount = (int)round( $price * 100 );


        $response_data = doFloaScheduleSimulation($payment_info, $authentication_token, $amount);    
            
            
        $schedule = $response_data['schedule_detail'];
        if( empty($schedule) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
        }

        $exp = call_api_func('get_expressions');

        $arr_schedules = array();
        foreach ($schedule['schedules'] as $key => $value) {
            $title = $value["rank"]."ª ".$exp[731];
            if($value["rank"] > 1) $title = $value["rank"]."ª ".$exp[730]." ".date("d-m-Y", strtotime($value["date"]));
            
            $amount = round( $value['amount'] / 100, 2);

            $arr_schedules[] = array(
                "title"  => $title,
                "amount" => call_api_func('OBJ_money',$amount, $MOEDA['id']),
            );
        }

        
        $arr["schedules"] = $arr_schedules;
        if((int)$payment_id == 0){
        
            $payment_info = cms_fetch_assoc( cms_query("SELECT id, nome$LG FROM ec_pagamentos WHERE id='114' AND activo=1 AND id IN (".$_SESSION['_MARKET']['metodos_pagamento'].")") );
            if($payment_info['id']>0){
                $arr_options[] = array(            
                    'nome' => $payment_info['nome'.$LG],
                    'id' => $payment_info['id']
                );
            }

            $payment_info = cms_fetch_assoc( cms_query("SELECT id, nome$LG FROM ec_pagamentos WHERE id='115' AND activo=1 AND id IN (".$_SESSION['_MARKET']['metodos_pagamento'].")") );
            if($payment_info['id']>0){
                $arr_options[] = array(            
                    'nome' => $payment_info['nome'.$LG],
                    'id' => $payment_info['id']
                );
            }

            $arr["opcoes"] = $arr_options;
        }
    
        $fx->_SetCache($_FLOAScacheid, serialize($arr), 1440); #1 dia
    }
    
    

    return serialize($arr);
  
}
?>
