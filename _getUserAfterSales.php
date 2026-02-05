<?

function _getUserAfterSales($user_id=0){
    
    if( (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
    }else{
        $user_id = (int)$user_id;
    }

    if( (int)$user_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    $payload = array();
    
    $after_sales_res = cms_query("SELECT `b2b_pos_venda`.* FROM `b2b_pos_venda` 
                                    INNER JOIN `b2b_pos_venda_veiculos` ON `b2b_pos_venda_veiculos`.`id`=`b2b_pos_venda`.`veiculo_id` AND `b2b_pos_venda_veiculos`.`deleted`=0 
                                    WHERE `b2b_pos_venda`.`utilizador_id`='".$user_id."' ORDER BY `b2b_pos_venda`.`id` DESC");
    $payload['after_sales'] = [];
    
    if( cms_num_rows($after_sales_res) > 0 ){
        
        require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getAfterSalesVehicleInfo.php';
        
        while( $after_sale_info = cms_fetch_assoc($after_sales_res) ){
            
            $after_sale_info_temp           = array();
            $after_sale_info_temp['id']     = $after_sale_info['id'];
            $after_sale_info_temp['date']   = date("Y-m-d H:i", strtotime($after_sale_info['data_criacao']) );
            
            $vehicle_info = unserialize( _getAfterSalesVehicleInfo($after_sale_info['veiculo_id']) );
            $vehicle_info = $vehicle_info['payload']['vehicle'];
            unset($vehicle_info['owners']);
            
            $vehicle_info['allows_report_damage'] = 0;
            /*if( $vehicle_info['registration_date'] != "" && $vehicle_info['registration_number'] != "" 
                    && strtotime(date('Y-m-d')) > strtotime($vehicle_info['registration_date'].' +3 year') ){
                $vehicle_info['flag']['name']       = estr2(852);
                $vehicle_info['flag']['class_name'] = "rdc-state-04";    
            }elseif( $vehicle_info['registration_number'] == ""  || $vehicle_info['registration_number'] == "-"  ){
                $vehicle_info['flag']['name']       = estr2(853);
                $vehicle_info['flag']['class_name'] = "rdc-state-05";     
            }else{
                $vehicle_info['allows_report_damage'] = 1;
            }*/
            if( $vehicle_info['registration_date'] != "" && strtotime(date('Y-m-d')) > strtotime($vehicle_info['registration_date'].' +3 year') ){
                $vehicle_info['flag']['name']       = estr2(852);
                $vehicle_info['flag']['class_name'] = "rdc-state-04";    
            }else{
                $vehicle_info['allows_report_damage'] = 1;
            }
            
            $after_sale_info_temp['vehicle'] = $vehicle_info;
            
            $payload['after_sales'][] = $after_sale_info_temp;   
                    
        }
    
    }

    $payload['request_license_plates'] = (int)$_SESSION['EC_USER']['request_license_plates'];

    return serialize(['success' => 1, 'payload' => $payload]);
    
}

?>
