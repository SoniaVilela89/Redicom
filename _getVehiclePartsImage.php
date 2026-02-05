<?

function _getVehiclePartsImage($vehicle_id=0, $level1_value=0, $level2_value=0, $image_id=0){
    
    if( $vehicle_id <= 0 ){
        $vehicle_id = params('vehicle_id');
    }
    
    if( $level1_value <= 0 ){
        $level1_value = (int)$_GET['l1'];
    }
    
    if( $level2_value <= 0 ){
        $level2_value = (int)$_GET['l2'];
    }

    if( $image_id <= 0 ){
        $image_id = (int)$_GET['image_id'];
    }
    
    if( $vehicle_id <= 0 || filter_var($vehicle_id, FILTER_VALIDATE_INT) === FALSE ){
        return serialize(['success' => false]);
    }
    
    $more_where = "AND `nivel1_valor`=".$level1_value." AND `nivel2_valor`=".$level2_value;
    if( (int)$image_id > 0 ){
        $more_where = "AND `imagem_id`=".$image_id;
    }
    
    $image_row = cms_fetch_assoc(cms_query("SELECT `imagem_id`
                                            FROM `pecas_imagens_veiculos`
                                            WHERE `veiculo_id`=".$vehicle_id." $more_where") );
    
    if( (int)$image_row['imagem_id'] <= 0 ){
        return serialize(['success' => false]);
    }
    
    $image_info = [];
    
    $file = "images/pecas_imagem_".$image_row['imagem_id'].".jpg";
    if( file_exists( $_SERVER['DOCUMENT_ROOT']."/".$file ) ){
        $image_info['image'] = call_api_func('imageOBJ', '', 1, $file);
    }else{
        return serialize(['success' => false]);
    }
    
    global $LG;
    
    $hotspots_res = cms_query("SELECT `id`,`registo_id`,`nome$LG`,`desc$LG`,`X`,`Y` FROM `pecas_imagens_hotspots` WHERE `nome".$LG."`!='' AND `imagem_id`=".$image_row['imagem_id']);
    $image_prods  = [];
    
    while( $hotspot = cms_fetch_assoc($hotspots_res) ){

        if( (int)$hotspot['registo_id'] <= 0 ){
            continue;
        }
        
        $hotspot_tmp = ['id'            => $hotspot['id'],
                        'title'         => $hotspot['nome'.$LG],
                        'description'   => $hotspot['desc'.$LG],
                        'X'             => $hotspot['X'],
                        'Y'             => $hotspot['Y'],
                        'product'       => $hotspot['registo_id']
                      ];

        $image_info['hotspots'][] = $hotspot_tmp;
        
    }
    
    return serialize(['success' => true, 'image' => $image_info, 'products' => $image_prods]);

}

?>
