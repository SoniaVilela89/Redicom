<?

function _getProductsComparator($page_id=0){

    global $LG;
    global $userID, $CONFIG_TEMPLATES_PARAMS;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    
    $s    = "SELECT * FROM registos_comparador WHERE id_cliente='$userID' AND status='0' ORDER BY id DESC LIMIT 0,3 ";
    $q    = cms_query($s);
    $nr   = cms_num_rows($q);      
    
    $resp                 = array();
    $resp['products']     = array();
    
    $row = call_api_func('get_pagina', 85, "_trubricas");
    $resp['page'] = call_api_func('OBJ_page', $row, 85, 0);  
    
    $caminho = call_api_func('get_breadcrumb', 85);
    $resp['page']['breadcrumb'] = $caminho;

    
    $resp['shop']         = call_api_func('OBJ_shop_mini');
    $resp['expressions']  = call_api_func('get_expressions',85);
    
    if($nr<1){
        return serialize($resp);
    }
    
    $pids = array();
    while($res = cms_fetch_assoc($q)){
        $pids[] = $res['pid'];
    }

    $resp['products'] = call_api_func('get_products_comparator', "AND registos.id IN (".implode(',', $pids).")" );
    
    # Entrega Geograficamente Limitada
    $has_geo_limited_delivery = hasGeoLimitedDelivery();
    if( !empty($has_geo_limited_delivery) ){
        $shipping_express = cms_fetch_assoc( cms_query("SELECT GROUP_CONCAT(id) AS shipping_ids FROM `ec_shipping` WHERE `geo_limited`=1 AND `id` IN(".$_SESSION['_MARKET']['metodos_envio'].")") );
    }
    # Entrega Geograficamente Limitada
    
    $arr_sku_family = array();
    $caract = array();
    $caract_order = array();

    foreach($resp['products'] as $k => $v){
        
        $arr_sku_family[$v['sku_family']] = $v['sku_family'];
        
        foreach($v['composition'] as $kk => $vv){
            
            $caract_group_row = call_api_func("get_line_table", "registos_caracteristicas_grupo", "nome$LG='".$kk."'");
            
            foreach($vv as $kkk => $vvv){

                $caract_order['0'.$caract_group_row['ordem'].'_'.$kk]['0'.$vvv['ordem']."_".$vvv['name']] = $vvv['name'];

                $caract[$kk][$vvv['name']][$k]=$vvv['feature'];

            }

        }
        
        # Entrega Geograficamente Limitada
        if( !empty($has_geo_limited_delivery) ){
            $prod = call_api_func("get_line_table", "registos", "id='".$v['id']."'");
            if( trim($prod['generico30']) != "" && count( array_intersect( explode(",", $prod['generico30']), explode(",", $shipping_express['shipping_ids']) ) ) > 0 ){
                $resp['products'][$k]['allow_add_cart'] = 0;
            }
        }
        # Entrega Geograficamente Limitada
        
    }
    
    # Sort $caract by 'ordem'
    ksort($caract_order, SORT_NATURAL);

    $new_caract = array();
    $new_caract_order = array();

    foreach($caract_order as $key_caract_order => $value_caract_order){

        $caract_group = str_replace( explode("_", $key_caract_order)[0]."_", "", $key_caract_order );

        $new_caract[$caract_group] = $caract[$caract_group];

        $new_caract_order[$caract_group] = $value_caract_order;

    }

    $caract = $new_caract;
    $caract_order = $new_caract_order;

    foreach( $caract as $key => $value ){
        
        ksort($caract_order[$key], SORT_NATURAL);
        
        $ordered_array = array_merge(array_flip($caract_order[$key]), $value);
        
        $caract[$key] = $ordered_array;

    }
    # Sort $caract by 'ordem'
    
    # Reviews
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){
       
        $arr_review_product            = call_api_func('get_reviews_product_by_sku_familys', $arr_sku_family);
        foreach($resp['products'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $resp['products'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
    }
    
    $resp['comparator'] = $caract;

    $caract_features = [];
    $caract_group_key = 0;
    foreach($caract as $key_caract => $value_caract){

        $caract_features[$caract_group_key] = [
            'name' => $key_caract,
        ];
        
        foreach( $value_caract as $key_caract_v => $value_caract_v ){

            $arr_value_caract = [
                'name' => $key_caract_v
            ];

            for( $i = 0; $i < count($resp['products']); $i++ ){

                $arr_value_caract['products'][] = [
                    'id' => $resp['products'][$i]['id'],
                    'value' => $value_caract_v[$i]
                ];

            }
            
            $caract_features[$caract_group_key]['lines'][] = $arr_value_caract;

        }

        $caract_group_key++;

    }
    $resp['comparator_features'] = $caract_features;

    return serialize($resp);

}

?>
