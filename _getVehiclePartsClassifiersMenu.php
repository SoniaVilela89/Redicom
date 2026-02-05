<?

function _getVehiclePartsClassifiersMenu($vehicle_id=0, $first_level_value=0, $second_level_value=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id = params('vehicle_id');
    }
    
    if( $vehicle_id <= 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
        return serialize(['success' => false]);
    }
    
    global $id;
    global $LG, $CONFIG_OPTIONS;
    
    $menu_res = cms_query("SELECT DISTINCT `".$CONFIG_OPTIONS['pecas_nivel_1']."`.`id` as `level1_id`, `".$CONFIG_OPTIONS['pecas_nivel_2']."`.`id` as `level2_id`, `".$CONFIG_OPTIONS['pecas_nivel_1']."`.`nome".$LG."` as `level1_nome`, `".$CONFIG_OPTIONS['pecas_nivel_2']."`.`nome".$LG."` as `level2_nome`
                            FROM `pecas_veiculos_registos`
                              JOIN `".$CONFIG_OPTIONS['pecas_nivel_1']."` ON `".$CONFIG_OPTIONS['pecas_nivel_1']."`.`id`=`pecas_veiculos_registos`.`nivel1_valor`
                              JOIN `".$CONFIG_OPTIONS['pecas_nivel_2']."` ON `".$CONFIG_OPTIONS['pecas_nivel_2']."`.`id`=`pecas_veiculos_registos`.`nivel2_valor`
                            WHERE `pecas_veiculos_registos`.`veiculo_id`=".$vehicle_id." AND `".$CONFIG_OPTIONS['pecas_nivel_1']."`.`nome".$LG."` != '' AND `".$CONFIG_OPTIONS['pecas_nivel_2']."`.`nome".$LG."` != ''");
    
    $menu = [];
    while( $menu_item = cms_fetch_assoc($menu_res) ){
        
        $selected = 0;
        if( $first_level_value == $menu_item['level1_id'] ){
            $selected = 1;
        }
        
        if( !isset( $menu[ $menu_item['level1_id'] ] ) ){
            $menu[ $menu_item['level1_id'] ] = [ 'id' => $menu_item['level1_id'], 'link_name' => $menu_item['level1_nome'], 'url' => 'index.php?id='.$id.'&vc='.$vehicle_id.'&l1='.$menu_item['level1_id'], 'folder_open' => $selected, 'childs' => [] ];
        }
        
        $selected = 0;
        if( $second_level_value == $menu_item['level2_id'] ){
            $selected = 1;
        }
        
        if( !isset( $menu[ $menu_item['level2_id'] ] ) ){
            $menu[ $menu_item['level1_id'] ]['childs'][ $menu_item['level2_id'] ] = [ 'id' => $menu_item['level2_id'], 'link_name' => $menu_item['level2_nome'], 'url' => 'index.php?id='.$id.'&vc='.$vehicle_id.'&l1='.$menu_item['level1_id'].'&l2='.$menu_item['level2_id'], 'folder_open' => $selected ];
        }
        
    }
    
    return serialize(['success' => true, 'menu' => $menu]);
    
}

?>
