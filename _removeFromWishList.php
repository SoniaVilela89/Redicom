<?

function _removeFromWishList($pid=null, $sku_family=null, $group_id=0){
    
    if( is_null($pid) ){
        $pid = params('pid');
        $sku_family = params('sku_family');
        $group_id = params('group_id');
    }

    global $userID, $B2B, $COUNTRY, $MOEDA, $LG;

    $group_id = (int)$group_id;

    $more_where = "";
    if( $group_id > 0 ){
        $more_where = "AND `wishlist_grupo_id`='".$group_id."'";    
    }

    $sku_family = utf8_decode($sku_family);
   
    $prod = get_line_table("registos_wishlist", "(pid='$pid' OR sku_family='$sku_family') AND id_cliente='$userID' AND status='0'");
    
    if( $sku_family != "" ){

        if( strpos($sku_family, "||") ){
    
            $arr_sku_family = explode("||", $sku_family);
            foreach( $arr_sku_family as $row_sku_family ){

                if( empty($prod) ){
                    $prod = get_line_table("registos_wishlist", "sku_family='$row_sku_family' AND id_cliente='$userID' AND status='0'");
                }

                cms_query("DELETE FROM registos_wishlist WHERE sku_family='$row_sku_family' AND id_cliente='$userID' AND status='0' ".$more_where);
            }
    
        }else{
            cms_query("DELETE FROM registos_wishlist WHERE sku_family='$sku_family' AND id_cliente='$userID' AND status='0' ".$more_where);
        }
        
    }else{
        cms_query("DELETE FROM registos_wishlist WHERE pid='$pid' AND id_cliente='$userID' AND status='0' ".$more_where);
    }

    $resp=array();
    $resp['wishlist'] = OBJ_lines(0, 2, 41, $userID, "", false, $group_id);
    
    if( (int)$B2B > 0 ){
        $wishlist_groups_qty = cms_fetch_assoc( cms_query("SELECT COUNT(`id`) AS `qty` FROM registos_wishlist WHERE sku_family='$sku_family' AND id_cliente='$userID' AND status='0'") );
        $resp['wishlist_groups_qty'] = (int)$wishlist_groups_qty['qty'];
    }

    $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );

    
    if((int)$B2B==0){
        
        # Collect API  ***************************************************************************************************************
        global $collect_api;
        if (isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1) {
            #<Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>
            include $_SERVER['DOCUMENT_ROOT'] . "/custom/shared/addons_info.php";
            $collectApiExtraClassifier = getCollectApiExtraClassifier();
            $campos                    = $collectApiExtraClassifier['campos'];
            $addJoinArr                = $collectApiExtraClassifier['addJoinArr'];
            $addFields                 = $collectApiExtraClassifier['addFields'];          
            $COLLECT_API_LANG          = $collectApiExtraClassifier['COLLECT_API_LANG'];   
            $num_campos_adicionais     = $collectApiExtraClassifier['num_campos_adicionais'];
            #</Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>

            $q = "SELECT registos_marcas.nome$COLLECT_API_LANG as brand ,registos_familias.nome$COLLECT_API_LANG as family, registos_categorias.nome$COLLECT_API_LANG as category, registos_generos.nome".$COLLECT_API_LANG." as gender  
                   $addFields
                  FROM registos
                    LEFT JOIN registos_marcas ON registos.marca = registos_marcas.id
                    LEFT JOIN registos_familias ON registos.familia = registos_familias.id
                    LEFT JOIN registos_categorias ON registos.categoria = registos_categorias.id
                    LEFT JOIN registos_generos ON registos.genero = registos_generos.id
                     ".implode("\n",$addJoinArr)."
                  WHERE registos.sku_family='$sku_family'
                  LIMIT 0,1";
            $infoprod = cms_fetch_assoc(cms_query($q));

            $prod['family']['name']   = $infoprod['family'];
            $prod['category']['name'] = $infoprod['category'];
            $prod['brand']['name']    = $infoprod['brand'];
            $prod['gender']['name']   = $infoprod['gender'];

            #<Classificadores adicionais>   
            for ($i=1;$i<=$num_campos_adicionais ;$i++ ) {
                 $classificador = ${'ADDON_3010_CLS_ADIC_'.$i};
                 if( empty($classificador) || $infoprod[$campos[$classificador]['alias']] == '' ){ continue;}
                 $prod['extra_classifier']['extra_classifier_'.$i] = $infoprod[$campos[$classificador]['alias']];
            }
            #</Classificadores adicionais> 
            $event_info = ['product' => $prod, 'country' => $COUNTRY, 'currency' => $MOEDA];
            $cart_ungrouped = [];
    
            foreach($resp['wishlist'] as $line){
                $cart_product = buildCartProductInfoForCollectAPI($line);
                
                $line_ungrouped = array_fill(0, $line['quantity'], $cart_product); # copy the line "$line['quantity']" times (creates an array with as many lines as it's quantity)
                $cart_ungrouped = array_merge($cart_ungrouped, $line_ungrouped);
    
            }
            
            $change_cart_info = ['wishlist' => ['items' => $cart_ungrouped], 'country' => $COUNTRY, 'currency' => $MOEDA];
            
            try {
                $collect_api->setEvent(CollectAPI::PRODUCT_REMOVED_FROM_WISHLIST, $_SESSION['EC_USER'], $event_info);
                
                # 2025-10-22 - Este evento não é utilizado por nenhum Publisher
                $collect_api->setEvent(CollectAPI::WISHLIST_CHANGE, $_SESSION['EC_USER'], $change_cart_info);
                
            } catch (Exception $e) {}
        } 
        
        
        
        # 2025-09-11
        # Adicionado para fazer o evento de tracking
        $v = $prod;
        $productOBJ = call_api_func('convert_line_to_product', $v, 1);
        $productOBJ['id'] = end( explode("|||", $pid) ); 
        
        $arr = array("id"                 => $v['id'],
                      "egift"             => $v['egift'],
                      "pack"              => $v['pack'],
                      "product"           => $productOBJ,
                      "title"             => strip_tags($v['nome']),
                      "composition"       => $v['composition'],
                      "image"             => $v['image'],
                      "price"             => call_api_func('OBJ_money',$v['valoruni']-$v['desconto'], $v['moeda']),
                      "line_price"        => call_api_func('OBJ_money',($v['valoruni']-$v['desconto']), $v['moeda']),
                      "old_price"         => call_api_func('OBJ_money',$v['valoruni'], $v['moeda']), #antes de descontos de cupões               
                      "previous_price"    => call_api_func('OBJ_money',$v['valoruni_anterior'], $v['moeda']), #antes de promos
                      "grams"             => $v['unidade_portes'],
                      "sku"               => $v['ref'],
                      "sku_group"         => $v['sku_group'],
                      "variant_id"        => $v['pid'],
                      "product_id"        => end( explode("|||", $v['pid']) ),
                      "discount"          => $discontOBJ,
                      "data_line"         => $v,
                      );

        $resp['wishlist_remove'] = $arr;
        
    }
    
    
    
    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    $_SESSION['sys_qr_qtw'] = count($resp['wishlist']);
    
    
    return serialize($resp);

}

?>
