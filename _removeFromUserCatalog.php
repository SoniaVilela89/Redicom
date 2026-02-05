<?php

function _removeFromUserCatalog($catalog_id = 0, $product_id = 0) {
    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;

    if ((int)$catalog_id == 0) {
        $catalog_id = (int)params('catalog_id');
    }
    if ((int)$product_id == 0) {
        $product_id = (int)params('product_id');
    }
    if (!is_numeric($catalog_id) || $catalog_id < 1 || !is_numeric($product_id) || $product_id < 1) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid or missing catalog id or product id']);
    }

    $catalog_info = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = $catalog_id AND type = $TYPE"));
    global $userID, $CONFIG_OPTIONS;
    $hasOriginalID = false;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        // $user_id = $_SESSION['EC_USER']['id_original'];
        $hasOriginalID = true;
    }
    if ((!isset($catalog_info['id']) || empty($catalog_info)) || ((int)$catalog_info['user_id'] != $userID && !$hasOriginalID)) {
        return serialize(['success' => 0, 'error' => 'Error - Unknown or invalid catalog']);
    }
    if ($catalog_info['status'] == $CATALOG_CONCLUDED_ID) {
        return serialize(['success' => 0, 'error' => 'Error - Catalog already closed']);
    }

    cms_query("DELETE FROM budgets_lines WHERE budget_id = $catalog_id AND product = $product_id");
    if (cms_affected_rows() <= 0) {
        return serialize(['success' => 0, 'error' => 'Error removing product from user catalog']);
    }

    $product = cms_fetch_assoc(cms_query("SELECT sku FROM registos WHERE id = $product_id"));
    if (!isset($product)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Unknown product']);
    }

    return serialize(['success' => 1, 'payload' => ['message' => 'Product succefully removed from user catalog', 'product id' => $product_id]]);
}
