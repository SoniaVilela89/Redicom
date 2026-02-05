<?

function _getWishlist($page_id=0, $list_id=0, $product_id=0){

    global $userID, $CACHE_HOME, $fx, $CACHE_KEY, $B2B, $CONFIG_OPTIONS, $db_name_cms;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if ($page_id==0){

       $page_id     = (int)params('page_id');
       $list_id     = params('list_id');
       $product_id  = params('product_id');
       
    }

    $list_id        = (int)$list_id;
    $product_id     = (int)$product_id;

    $row = call_api_func('get_pagina', 41, "_trubricas");
    
    $scope                      = array();
    $scope['page_id']           = 41;
    $scope['LG']                = $_SESSION['LG'];
    $scope['PAIS']              = $_SESSION['_COUNTRY']['id'];
    $scope['BLOCK']             = $row["ContentBlock"];
    
    $_WLcacheid = $CACHE_KEY."WL_".implode('_', $scope);
    
    $dados = $fx->_GetCache($_WLcacheid, $CACHE_HOME);
    
    $arr = array();
    if ($dados!=false && $_GET['nocache']!=1 ){         
        $arr = $dados;  
    } else { 

        $arr['page'] = call_api_func('OBJ_page', $row, 41, 0, 0, "_trubricas");
        $arr['page']['breadcrumb'] = call_api_func('get_breadcrumb', 41);
        
        $arr['content_blocks'] = get_content_blocks($row["ContentBlock"], 0);
        
        $table_style = "`$db_name_cms`.ContentBlocksStyles";                                                                        
        $style = get_line_table_api_obj($table_style, "Id='1'");
    
        $arr['content_blocks_style'] = $style;
        
        $fx->_SetCache($_WLcacheid, $arr, $CACHE_HOME);

    }

    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['wishlist_public']     = call_api_func('getUserWish', $userOriginalID, $arr['items']);
    $arr['expressions']         = call_api_func('get_expressions',41);
    
    if( $B2B > 0 && $list_id == 0 ){
        $arr['lists'] = _getWishlistGroups($userOriginalID, $product_id);
    }else{

        if( $list_id > 0 ){

            $list = get_line_table("registos_wishlist_grupo", "`id`='".$list_id."' AND `utilizador_id`='$userOriginalID'", "`id`, `nome`");
            if( (int)$list['id'] < 1 ){
                return serialize(['success' => false]);
            }

            $arr['page']['page_title'] = $list['nome'];

        }

        $arr['items'] = call_api_func('OBJ_lines', 0, 2, 41, $userOriginalID, "", false, $list_id);

    }

    $arr['grid_view'] =  $_SESSION['GridView'];
    $arr['grid_view_mobile'] =  $_SESSION['GridViewMobile'];
    
    
    
    
    
    cms_query("DELETE FROM registos_wishlist WHERE actualizado<DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"); # anteriores há 1 ano
    cms_query("DELETE FROM registos_wishlist WHERE id_cliente NOT REGEXP '^-?[0-9]+$' AND actualizado<DATE_SUB(NOW(), INTERVAL 1 DAY)");  # anteriores há 1 dia para sem sessão


    return serialize($arr);

}

function _getWishlistGroups($userID, $product_id){

    global $slocation;

    $arr_lists = [];

    if( !is_numeric($userID) || (int)$userID < 1 ){
        exit("<script>location='".$slocation."'</script>");
    }

    $wishlist_res = cms_query("SELECT `id`, `nome` FROM `registos_wishlist_grupo` WHERE `utilizador_id`='".$userID."'");
    while( $list = cms_fetch_assoc($wishlist_res) ){

        $list_temp = [
            "id"        => $list['id'],
            "name"      => $list['nome'],
            "url"       => $slocation."/index.php?id=41&l=".$list['id']
        ];
        
        if( $product_id > 0 ){

            $product_in_list = get_line_table("registos_wishlist", "`id_cliente`='".$userID."' AND `status`='0' AND `wishlist_grupo_id`='".$list['id']."' AND `pid`='".$product_id."'", "COUNT(`id`) AS `quantity`");
            if( $product_in_list['quantity'] > 0 ){
                $list_temp['product_in_list'] = 1;
            }

        }else{

            $products_in_list = get_line_table("registos_wishlist", "`id_cliente`='".$userID."' AND `status`='0' AND `wishlist_grupo_id`='".$list['id']."'", "COUNT(`id`) AS `quantity`");
            $list_temp['quantity'] = (int)$products_in_list['quantity'];

        }

        $arr_lists[] = $list_temp;
        
    }

    return $arr_lists;

}

?>
