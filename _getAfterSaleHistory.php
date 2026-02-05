<?
# Histórico de avarias

function _getAfterSaleHistory($user_id=0){

    if( (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
    }

    global $LG;

    $response    = array();
    $status      = array();
    $status_2    = array();

    $s = "SELECT `b2b_pos_venda_avarias`.`id`, `b2b_pos_venda_avarias`.`ref`, `b2b_pos_venda_avarias`.`data_criacao`, `b2b_pos_venda_avarias`.`kms`, `b2b_pos_venda_avarias`.`veiculo_id`, `b2b_pos_venda_avarias`.`estado`, `b2b_pos_venda_avarias`.`estado_info`, `b2b_pos_venda_veiculos`.`vin`, `b2b_pos_venda_veiculos`.`numero_motor`, `b2b_pos_venda_veiculos`.`marca`, `b2b_pos_venda_veiculos`.`modelo`, `b2b_pos_venda_veiculos`.`matricula`,`b2b_pos_venda_veiculos`.`data_matricula`
            FROM `b2b_pos_venda_avarias`
            INNER JOIN `b2b_pos_venda` ON (`b2b_pos_venda_avarias`.`veiculo_id`=`b2b_pos_venda`.`veiculo_id` AND `b2b_pos_venda`.`utilizador_id` = '".$user_id."')
            INNER JOIN `b2b_pos_venda_veiculos` ON (`b2b_pos_venda_avarias`.`veiculo_id`=`b2b_pos_venda_veiculos`.`id` AND `b2b_pos_venda_veiculos`.`deleted`=0)
            WHERE `b2b_pos_venda_avarias`.`data_criacao` > DATE_SUB(NOW(),INTERVAL 3 YEAR) AND `b2b_pos_venda_avarias`.`utilizador_id` = '".$user_id."' AND `b2b_pos_venda_avarias`.`hidden`='0'
            ORDER BY `b2b_pos_venda_avarias`.`id` DESC;";
    $q = cms_query($s);

    while($r = cms_fetch_assoc($q)){

        if(!array_key_exists($r['estado'], $status)) {
            $status = cms_fetch_assoc(cms_query("SELECT `id`, `nome$LG`, `class_name` FROM `b2b_pos_venda_avarias_estados` WHERE `id`=".$r['estado']));
            $status[$r['estado']] = array(
                "id"         => $status['id'],
                "name"       => $status['nome'.$LG],
                "class_name" => $status['class_name']
            );
        }

        $status_info = $status[$r['estado']];

        if( $r['estado_info'] > 0 ){
            if(!array_key_exists($r['estado_info'], $status_2)) {
                $est_inf = cms_fetch_assoc(cms_query("SELECT `nome$LG`, `class_name` FROM `b2b_pos_venda_avarias_estados_info` WHERE `id`=".$r['estado_info']));
                $status_2[$r['estado_info']] = array("name" => $est_inf['nome'.$LG], "class_name" => $est_inf['class_name']);
            }
            $status_info['name'] = $status_2[$r['estado_info']]['name'];

            if($status_2[$r['estado_info']]['class_name'] != '') {
                $status_info['class_name'] = $status_2[$r['estado_info']]['class_name'];
            }

        }


        $veiculo = array(
                    "id"            => $r['veiculo_id'],
                    "brand"         => $r['marca'],
                    "model"         => $r['modelo'],
                    "license_plate" => $r['matricula'],
                    "vin"           => $r['vin'],
                    "engine_number" => $r['numero_motor']
                    );

        # matricula
        if( (int)$r['matricula'] == -1 ){
            $veiculo['registration_number_requested'] = 1;
        }elseif( trim( $r['matricula'] ) != '' ){
            $veiculo['registration_number'] = $r['matricula'];
            $veiculo['registration_date'] = $r['data_matricula'];
        }else{
            $veiculo['registration_number'] = "-";
        }


        $result                = array();
        $result['id']          = $r['id'];
        $result['ref']         = $r['ref'];
        $result['date']        = date("Y-m-d", strtotime($r['data_criacao']));
        $result['kms']         = $r['kms'];
        $result['status_info'] = $status_info;
        $result['vehicle']     = $veiculo;

        $response["history"][] = $result;
            
    }

    
    return serialize(['success' => 1, 'payload' => $response]);

}

?>
