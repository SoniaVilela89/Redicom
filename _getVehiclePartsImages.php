<?

function _getVehiclePartsImages($vehicle_id=0, $level1_value=0, $level2_value=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id = params('vehicle_id');
    }
    
    if( $level1_value <= 0 ){
        $level1_value = (int)$_GET['l1'];
    }
    
    if( $level2_value <= 0 ){
        $level2_value = (int)$_GET['l2'];
    }
    
    if( $vehicle_id <= 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
        return serialize(['success' => false]);
    }

    global $LG;

    $more_where = "";
    if( $level1_value > 0 && $level2_value > 0 ){
        $more_where = "AND `nivel1_valor`=".$level1_value." AND `nivel2_valor`=".$level2_value;
    }
    
    $vehicle_images = array();

    $image_res = cms_query("SELECT `imagem_id`, `nome".$LG."` AS `name`
                            FROM `pecas_imagens_veiculos`
                                INNER JOIN `pecas_imagens` ON `pecas_imagens`.`id`=`pecas_imagens_veiculos`.`imagem_id`
                            WHERE `veiculo_id`=".$vehicle_id." AND `nome".$LG."`!='' $more_where
                            GROUP BY `imagem_id`
                            ORDER BY `nome".$LG."`");

    while( $image_row = cms_fetch_assoc($image_res) ){
        
        $image_info = [];

        $image_info['id']   = $image_row['imagem_id'];
        $image_info['name'] = $image_row['name'];
        
        $file = "images/pecas_imagem_".$image_row['imagem_id'].".jpg";
        if( file_exists( $_SERVER['DOCUMENT_ROOT']."/".$file ) ){
            
            $image_info['image'] = $file;
            $vehicle_images[]    = $image_info;

        }else{
            continue;
        }

    }
    
    return serialize(['success' => true, 'images' => $vehicle_images]);

}

?>
