<?php

function _getAfterSaleDamageDetail($vehicle_id=0, $damage_id=0){
    
    if( (int)$vehicle_id <= 0 || (int)$damage_id <= 0 ){
        $vehicle_id = (int)params('vehicle_id');
        $damage_id = (int)params('damage_id');
    }

    if( (int)$vehicle_id <= 0 || $damage_id <= 0 ){
        exit;
    }
    
    $vehicle = cms_fetch_assoc( cms_query("SELECT `id` FROM `b2b_pos_venda_veiculos` WHERE `id`='".$vehicle_id."' AND `deleted`=0 LIMIT 1") );
    if( (int)$vehicle['id'] <= 0 ){
        exit;
    }
    
    global $LG, $userID;
    
    $damage = cms_fetch_assoc( cms_query("SELECT * FROM `b2b_pos_venda_avarias` WHERE `veiculo_id`='".$vehicle_id."' AND `id`='".$damage_id."' LIMIT 1") );
    
    $damage_info                = array();
    $damage_info['id']          = $damage['id'];
    $damage_info['ref']         = $damage['ref'];
    $damage_info['date']        = date("Y-m-d", strtotime($damage['data_criacao']));
    $damage_info['kms']         = $damage['kms'];
    $damage_info['date_kms']    = $damage['data_kms'];
    $damage_info['obs1']        = $damage['obs1'];
    $damage_info['obs2']        = $damage['obs2'];
    $damage_info['token']       = md5($damage['id']."|||".$damage['veiculo_id']."|||".$damage['data_criacao']."|||".$damage['utilizador_id']);
    
    $damage_status = cms_fetch_assoc( cms_query('SELECT * FROM `b2b_pos_venda_avarias_estados` WHERE `id`='.$damage['estado']) );
    
    $status_info         = array();
    $status_info['id']   = $damage_status['id'];
    $status_info['name'] = $damage_status['nome'.$LG];
    
    $damage_info['status_info'] = $status_info;
    
    # Observations
    $damage_info['observations'] = array();
    
    $damage_obs_res = cms_query("SELECT `id`, `datahora` AS `datetime`, `obs`, `autor` AS `author` FROM `b2b_pos_venda_avarias_observacoes` WHERE `avaria_id`=".$damage['id']." ORDER BY `id` DESC");
    while( $damage_obs = cms_fetch_assoc($damage_obs_res) ){

        $damage_obs['file'] = "";

        $file_obs = "/downloads/warranties/".$damage['id']."/obs_".$damage_obs['id'];
        $exts = array("jpeg","jpg","png","pdf","mp4","zip");
        foreach ($exts as $ext) {
            if( file_exists($_SERVER['DOCUMENT_ROOT'].$file_obs.".".$ext) ) {
                $damage_obs['file'] = $file_obs.".".$ext;
                continue;
            }
        }

        $damage_info['observations'][] = $damage_obs;
    }

    $damage_info['allow_create_observation'] = 1;
    if( $userID != $damage['utilizador_id'] ){
        $damage_info['allow_create_observation'] = 0;
    }
    # Observations
    
    # Uploaded files
    $damage_info['photos'] = array();

    $estr_900 = estr2(900); 
    $file_number = 1;
    
    $files_in_folder = glob($_SERVER["DOCUMENT_ROOT"]."/downloads/warranties/".$damage['id']."/*");

    foreach ($files_in_folder as $key=>$value) {

        $file = explode(".",$value);

        $file_extension = strtolower(end($file));
        
        $html_file_extension = call_api_func('getHtmlIconToFileExtension', $file_extension);
        
        $rowFile = array(
            'nome' => $html_file_extension, 
            'nome'.$LG => $estr_900." ".$file_number." ".strtoupper($file_extension), 
            'extension' => $file_extension,
            'download_name' => $estr_900."_".$file_number
        );

        $damage_info['photos'][] = call_api_func( 'fileOBJ', $rowFile, str_replace($_SERVER["DOCUMENT_ROOT"]."/", "", $value), 1 );
        $file_number++;

    }
    # Uploaded files

    # Parts
    $damage_info['parts'] = array();
    
    $parts_res = cms_query("SELECT `b2b_pos_venda_avarias_pecas`.`produto_id` AS `pid`,
                                    CONCAT(`registos`.`sku`, ' - ', `registos`.`nome$LG`) AS `label`,
                                    `b2b_pos_venda_avarias_pecas`.`quantidade` AS `qty`,
                                    `registos`.`units_in_package`,
                                    `registos`.`package_price_auto`,
                                    `registos`.`package_type`
                            FROM `b2b_pos_venda_avarias_pecas`
                                INNER JOIN `registos` ON `b2b_pos_venda_avarias_pecas`.`produto_id` = `registos`.`id`
                            WHERE `b2b_pos_venda_avarias_pecas`.`avaria_id`=".$damage['id']);
    while( $row = cms_fetch_assoc($parts_res) ){

        if((int)$row['units_in_package'] > 1 && (int)$row['package_price_auto'] == 1) {
            $embalagem = cms_fetch_assoc( cms_query("SELECT nome$LG as nome FROM `registos_embalagens` WHERE `id`='".$row['package_type']."'") );
            if($embalagem['nome'] != '') {
                $row['label'] = $row['label']." (".($row['qty'] / (int)$row['units_in_package'])."x ".$embalagem['nome'].")";
            }
        }

        $damage_info['parts'][] = $row;
    }
    # Parts
    
    $x = array();
    $response["damage"] = $damage_info;
    
    return serialize(['success' => 1, 'payload' => $response]);

}

?>
