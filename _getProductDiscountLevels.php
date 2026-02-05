<?

function _getProductDiscountLevels($product, $catalog_id=0){
    
    global $CONFIG_OPTIONS;

    if (trim($product) == ''){
        $product    = trim(params('product'));
        $catalog_id = (int)params('catalog_id');
    }

    $userOriginalID = (int)$_SESSION['EC_USER']['id'];;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if($userOriginalID<=0 || trim($product) == ''){
        return serialize(['error' => 1, 'mesage' => 'Invalid parameters'] );
    }

    global $CONFIG_OPTIONS;

    
    preparar_regras_carrinho($catalogo_id);
    
    
    $levels = get_all_product_discount_levels($userOriginalID, $product, $catalog_id);

    $arr = [
        'levels' => $levels,
        'layout' => (int)$CONFIG_OPTIONS['product_levels_layout']
    ];

    return serialize($arr);
}


?>
