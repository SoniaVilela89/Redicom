<?

function _getAfterSalesVehicleInfo($vehicle_id, $vin="", $engine_number=""){
    
    if( trim($vehicle_id) == '' ){
        $vehicle_id = params('vehicle_id');
        $vin = params('vin');
        $engine_number = params('engine_number');
    }

    if( trim($vehicle_id) == '' && ( trim($vin) == '' || trim($engine_number) == '' ) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    global $LG, $userID;
    
    $payload = array();
    
    $vehicle_where = "`id`='".(int)$vehicle_id."'";
    if( (int)$vehicle_id == 0 ){
    
        $vin = base64_decode($vin);
        $engine_number = base64_decode($engine_number);
        
        $vehicle_where = "`vin`='".$vin."' AND ( `numero_motor`='".$engine_number."' OR `matricula`='".$engine_number."')";
    
    }
    
    $vehicle_info = cms_fetch_assoc( cms_query("SELECT * FROM `b2b_pos_venda_veiculos` WHERE `deleted`=0 AND ".$vehicle_where) );
    
    if( (int)$vehicle_info['id'] > 0 ){
        
        if( (int)$vehicle_id == 0 ){
        
            $after_sale = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda` WHERE `veiculo_id`='".$vehicle_info['id']."' AND `utilizador_id`='".$userID."'") );
            if( (int)$after_sale['id'] > 0 ){
                return serialize(['success' => 0, 'error' => 'After Sale record already exists for this vehicle', 'error_type' => 2]);
            }
        
        }
        
        $payload['vehicle']['id'] = $vehicle_info['id'];
        $payload['vehicle']['reference'] = $vehicle_info['referencia'];
        $payload['vehicle']['vin'] = $vehicle_info['vin'];
        $payload['vehicle']['engine_number'] = $vehicle_info['numero_motor'];
        $payload['vehicle']['brand'] = $vehicle_info['marca'];
        $payload['vehicle']['model'] = $vehicle_info['modelo'];
        $payload['vehicle']['color'] = $vehicle_info['cor'];
        $payload['vehicle']['engine_capacity'] = $vehicle_info['cilindrada'];
        
        if( (int)$vehicle_info['matricula'] == -1 ){
            $payload['vehicle']['registration_number_requested'] = 1;
        }elseif( trim( $vehicle_info['matricula'] ) != '' ){
            $payload['vehicle']['registration_number'] = $vehicle_info['matricula'];
            $payload['vehicle']['registration_date'] = $vehicle_info['data_matricula'];
        }else{
            $payload['vehicle']['registration_number'] = "-";
        }
        
        $vehicle_owners_res = cms_query("SELECT * FROM `b2b_pos_venda_veiculos_proprietarios` WHERE `veiculo_id`='".$vehicle_info['id']."' ORDER BY `id` DESC");  
        if( cms_num_rows($vehicle_owners_res) > 0 ){
        
            $payload['vehicle']['owners'] = array();
        
            while( $vehicle_owner_info = cms_fetch_assoc($vehicle_owners_res) ){
                
                $vehicle_owner_temp                   = array();
                $vehicle_owner_temp['name']           = $vehicle_owner_info['nome'];
                $vehicle_owner_temp['nif']            = $vehicle_owner_info['nif'];
                $vehicle_owner_temp['address']        = $vehicle_owner_info['morada'];
                $vehicle_owner_temp['zip']            = $vehicle_owner_info['cp'];
                $vehicle_owner_temp['city']           = $vehicle_owner_info['cidade'];
                
                $country = call_api_func("get_line_table", "ec_paises", "id='".$vehicle_owner_info['pais']."'");
                $vehicle_owner_temp['country']['id']    = $country['id'];
                $vehicle_owner_temp['country']['name']  = $country['nome'.$LG];
                
                $vehicle_owner_temp['phone_number']   = $vehicle_owner_info['telefone'];
                $vehicle_owner_temp['email']          = $vehicle_owner_info['email'];
                $vehicle_owner_temp['date']           = $vehicle_owner_info['data_inicio'];
                
                $payload['vehicle']['owners'][] = $vehicle_owner_temp;
                
            }
        
        }
        
    }else{
        return serialize(['success' => 0, 'error' => 'Not Found', 'error_type' => 1]);    
    }
    
    return serialize(['success' => 1, 'payload' => $payload]);
    
}

?>
