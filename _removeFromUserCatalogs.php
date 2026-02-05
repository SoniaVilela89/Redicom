<?php

function _removeFromUserCatalogs() {
    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;

    if (empty($_POST)) {
        $_POST = json_decode(file_get_contents('php://input'), true);
    }    

    $pid = (int)$_POST['product_id'];
    $arr_catalogs = $_POST['catalogs'];

    if ($pid < 1 || empty($arr_catalogs) || is_null($arr_catalogs)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid or missing catalog id or product id']);
    }

    global $userID, $CONFIG_OPTIONS;

    foreach($arr_catalogs as $k => $v){
        $catalog_id = $v;

        $catalog_info = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = $catalog_id AND type = $TYPE"));

        $hasOriginalID = false;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            // $user_id = $_SESSION['EC_USER']['id_original'];
            $hasOriginalID = true;
        }
        if ((!isset($catalog_info['id']) || empty($catalog_info)) || ((int)$catalog_info['user_id'] != $userID && !$hasOriginalID)) {
            continue;
        }
        if ($catalog_info['status'] == $CATALOG_CONCLUDED_ID) {
            continue;
        }

        cms_query("DELETE FROM budgets_lines WHERE budget_id = $catalog_id AND product = $pid");
        if (cms_affected_rows() <= 0) {
            continue;
        }

    }

    $catalogs = get_line_table("budgets", "user_id='".$userID."' AND type='".$TYPE."' AND status != '".$CATALOG_CONCLUDED_ID."'", "GROUP_CONCAT(id) as catalogs");
    $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product`='" . $pid . "'", "COUNT(`id`) AS `quantity`");

    return serialize(['success' => 1, 'catalog_qty' => (int)$product_in_catalog['quantity']]);
}
