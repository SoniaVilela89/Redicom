<?

function _getReviews($sku_family=null, $only_average=null, $color_id=null){

    if( empty($sku_family) ){
       $sku_family      = params('sku_family');
       $only_average    = params('only_average');
       $color_id        = params('color_id');
    }

    $only_average   = (int)$only_average;
    $color_id       = (int)$color_id;

    global $LG, $userID, $CONFIG_TEMPLATES_PARAMS, $CONFIG_OPTIONS, $TRADUZIR_REVIEWS;
    
    $sku_family = utf8_decode($sku_family);

    $order_by_color = "";
    if( (int)$CONFIG_TEMPLATES_PARAMS['review_by_sku_family_color'] == 1 && $color_id > 0 ){
        $order_by_color = "IF(`cor`=$color_id, 0, 1),";
    }

    $order_tanslate = "FIELD(lg,'".$LG."') DESC,";
    if((int)$TRADUZIR_REVIEWS == 1) $order_tanslate = "";

    $s = 'SELECT ra.*, u.nome
            FROM registos_avaliacoes ra
                LEFT JOIN _tusers u ON ra.user_id=u.id
            WHERE ((user_id="'.$userID.'" AND status>-1) OR status=1) AND sku_family="'.$sku_family.'" ORDER BY '.$order_by_color.' '.$order_tanslate.' data DESC, id DESC';
    $q = cms_query($s);


    $resp             = array();
    $resp['reviews']  = array();
    while($r = cms_fetch_assoc($q)){

        if((int)$CONFIG_OPTIONS['review_tamanho'] == 0){
            $r['tamanho'] = 0;
            $r['largura'] = 0;
        }
        
        $resp['reviews'][] = call_api_func('OBJ_review',$r);
    }
    
    $resp['reviews_count'] = count($resp['reviews']);

    $resp['reviews_rating'] = array();
    
    $total_reviews = count($resp['reviews']);
    $mau = $fraco = $medio = $bom = $excelente = 0;
    $mau_total = $fraco_total = $medio_total = $bom_total = $excelente_total = 0;
    $other1_yes = $other2_yes = $total_other_1 = $total_other_2 = 0;
    $total_size = $size_val = 0;
    $total_width = $width_val = 0;

    $rating_reviews_lines = [];

    foreach( $resp['reviews'] as $key=>$value ){

        if( $value["avaliacao"] == 1 ){
            $mau++;
            $mau_total += $value["avaliacao"];
        }elseif( $value["avaliacao"] >= 1.1 && $value["avaliacao"] <= 2 ){
            $fraco++;
            $fraco_total += $value["avaliacao"];
        }elseif( $value["avaliacao"] >= 2.1 && $value["avaliacao"] <= 3 ){
            $medio++;
            $medio_total += $value["avaliacao"];
        }elseif( $value["avaliacao"] >= 3.1 && $value["avaliacao"] <= 4 ){
            $bom++;
            $bom_total += $value["avaliacao"];
        }elseif( $value["avaliacao"] >= 4.1 && $value["avaliacao"] <=  5){
            $excelente++;
            $excelente_total += $value["avaliacao"];
        }

        # 0 - não respondido / 1 - sim / 2 - não
        if( $value['other1'] > 0 ){
            $total_other_1++;
            if($value['other1'] == 1) $other1_yes++;
        }
        if( $value['other2'] > 0 ){
            $total_other_2++;
            if($value['other2'] == 1) $other2_yes++;
        }

        if((int)$CONFIG_OPTIONS['review_tamanho'] == 1){
           
            $value['tamanho'] = $value['size'];
            $value['largura'] = $value['width'];

            $arr_review_size_width = get_calculate_review_size_width($value);

            $total_size += $arr_review_size_width['size'];
            $size_val += $arr_review_size_width['size_val'];
                
            $total_width += $arr_review_size_width['width'];
            $width_val += $arr_review_size_width['width_val'];

        }
        
        if( $only_average == 0 ){

            $img_count = 1;
            $has_image = true;
            while($has_image){
                
                $review_img = "/images/review_".$value['id']."_i".$img_count.".jpg";
                if(file_exists($_SERVER['DOCUMENT_ROOT'].$review_img)){
                    $resp['reviews'][ $key ]['images'][] = call_api_func('imageOBJ', "", 1, $review_img, 'reviews');
                    $img_count++;
                }else{
                    $has_image = false;
                }

            }

            foreach( $value['lines'] as $key_line => $value_line ){

                if( !isset($rating_reviews_lines[$key_line]) ){
    
                    $rating_reviews_lines[$key_line]            = $value_line;
                    $rating_reviews_lines[$key_line]['count']   = 1;
    
                }else{
    
                    $rating_reviews_lines[$key_line]['value'] += $value_line['value'];
                    $rating_reviews_lines[$key_line]['count']++;
    
                }
    
            }

        }

        $resp['reviews'][$key]['lines'] = array_values($value['lines']);

    }
    
    if( $only_average == 0 ){

        $other1 = $other1_yes*100/$total_other_1;
        $other2 = $other2_yes*100/$total_other_2;

        $resp['reviews_others'] = array(
            "other1" => (int)$other1,
            "other2" => (int)$other2
        );
    
    }

    $arr_info = get_info_size_width($size_val, $total_size, $width_val, $total_width);
    
    $resp['reviews_sizes'] = array(
        "size" => (int)$arr_info["size"],
        "width" => (int)$arr_info["width"]
    );

    $prec_exc = ($excelente*100)/$total_reviews;
    if( $prec_exc > 0 ){
        $prec_exc = number_format($prec_exc,0);
    }else{
        $prec_exc = 0; 
    }

    $media_exc = 0;
    if($excelente>0) $media_exc = $excelente_total/$excelente;
    
    $resp['reviews_rating'][] = array(
                                  "title" => estr(407),
                                  "total" => $excelente,
                                  "value" => $prec_exc,
                                  "average" => floatval( number_format($media_exc,2) )
                                );

    $prec_bom = ($bom*100)/$total_reviews;
    if( $prec_bom > 0 ){
        $prec_bom = number_format($prec_bom,0);
    }else{
        $prec_bom = 0;
    }
    
    $media_bom = 0;
    if($bom>0) $media_bom = $bom_total/$bom;                   
    $resp['reviews_rating'][] = array(
                                  "title" => estr(406),
                                  "total" => $bom,
                                  "value" => $prec_bom,
                                  "average" => floatval( number_format($media_bom,2) )
                                );

    $prec_medio = ($medio*100)/$total_reviews;
    if( $prec_medio > 0 ){
        $prec_medio = number_format($prec_medio,0);
    }else{
        $prec_medio = 0;
    }

    $media_medio = 0;
    if($medio>0) $media_medio = $medio_total/$medio;   
    $resp['reviews_rating'][] = array(
                                  "title" => estr(405),
                                  "total" => $medio,
                                  "value" => $prec_medio,
                                  "average" => floatval( number_format($media_medio,2) )
                                );

    $prec_fraco = ($fraco*100)/$total_reviews;
    if( $prec_fraco > 0 ){
        $prec_fraco = number_format($prec_fraco,0);
    }else{
        $prec_fraco = 0;
    }
    
    $media_fraco = 0;
    if($fraco>0) $media_fraco = $fraco_total/$fraco;   
    $resp['reviews_rating'][] = array(
                                  "title" => estr(404),
                                  "total" => $fraco,
                                  "value" => $prec_fraco,
                                  "average" => floatval( number_format($media_fraco,2) )
                                );
                                
    $prec_mau = ($mau*100)/$total_reviews;
    if( $prec_mau > 0 ){
        $prec_mau = number_format($prec_mau,0);
    }else{
        $prec_mau = 0;
    }
    
    $media_mau = 0;
    if($mau>0) $media_mau = $mau_total/$mau;   
    $resp['reviews_rating'][] = array(
                                  "title" => estr(403),
                                  "total" => $mau,
                                  "value" => $prec_mau,
                                  "average" => floatval( number_format($media_mau,2) )
                                );

    if( $only_average == 0 ){                                

        foreach( $rating_reviews_lines as $review_line ){

            $resp['reviews_rating_lines'][] = array(
                "title" => $review_line['name'],
                "average" => floatval( number_format( ($review_line['value']/$review_line['count']), 2 ) )
            );

        }

    }else{
        unset($resp['reviews']);
    }
    
    if(is_callable('custom_controller_reviews')) {
        call_user_func_array('custom_controller_reviews', array(&$resp));
    }
    
    return serialize($resp);

}

?>
