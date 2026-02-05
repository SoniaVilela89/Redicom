<?

function _getUserVehicleGarage(){
    
    global $userID, $CONFIG_OPTIONS, $db_name_cms; 

    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    $user_id = $userOriginalID;
    if( $user_id <= 0 ){
        return serialize(['success' => false]);
    }
    
    global $LG;
    
    $vehicles_res = cms_query("SELECT `pecas_veiculos_utilizadores`.`id`, 
                                        `pecas_veiculos`.`id` as `veiculo_id`,
                                        `pecas_veiculos_segmentos`.`id` as `segmento_id`, `pecas_veiculos_segmentos`.`nome".$LG."` as `segmento_nome`,
                                        `pecas_veiculos_marcas`.`id` as `marca_id`, `pecas_veiculos_marcas`.`nome".$LG."` as `marca_nome`,
                                        `pecas_veiculos_modelos`.`id` as `modelo_id`, `pecas_veiculos_modelos`.`nome".$LG."` as `modelo_nome`,
                                        `pecas_veiculos_anos`.`id` as `ano_id`, `pecas_veiculos_anos`.`nome".$LG."` as `ano_nome`
                                FROM `pecas_veiculos_utilizadores`
                                  JOIN `pecas_veiculos` ON `pecas_veiculos`.`id`=`pecas_veiculos_utilizadores`.`veiculo_id`
                                  JOIN `pecas_veiculos_segmentos` ON `pecas_veiculos_segmentos`.`id`=`pecas_veiculos`.`segmento`
                                  JOIN `pecas_veiculos_marcas` ON `pecas_veiculos_marcas`.`id`=`pecas_veiculos`.`marca`
                                  JOIN `pecas_veiculos_modelos` ON `pecas_veiculos_modelos`.`id`=`pecas_veiculos`.`modelo`
                                  JOIN `pecas_veiculos_anos` ON `pecas_veiculos_anos`.`id`=`pecas_veiculos`.`ano`
                                 WHERE `pecas_veiculos`.`exibir_em` IN (0,1) AND `pecas_veiculos_utilizadores`.`user_id`=".$user_id);
    
    $vehicles_found = [];
    
    $parts_page = cms_fetch_assoc( cms_query("SELECT `id` FROM `$db_name_cms`.`_trubricas` WHERE `sublevel`=67 AND `hidden`=0 LIMIT 1") );
    
    while( $vehicle = cms_fetch_assoc($vehicles_res) ){
    
        if( $parts_page['id'] > 0 ){
            $vehicle['detail_url'] = 'index.php?id='.$parts_page['id'].'&vc='.$vehicle['veiculo_id'];
        }
    
        $vehicle['nome']  = $vehicle['marca_nome'].' '.$vehicle['modelo_nome'].' '.$vehicle['ano_nome'];
        $vehicles_found[] = $vehicle;
    
    }
    
    
    return serialize(['success' => true, 'vehicles' => $vehicles_found]);

}

?>
