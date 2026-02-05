<?php

function _removeUserCatalog($catalog_id = 0) {
    $TYPE = 2;
    $CATALOGS_PATH = "/downloads/catalogs";

    if ((int)$catalog_id == 0) {
        $catalog_id = (int)params('catalog_id');
    }

    $catalog_id = safe_value($catalog_id);

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


    $CURRENT_CATALOG_PATH = $_SERVER['DOCUMENT_ROOT']  . "/$CATALOGS_PATH/$catalog_id";

    cms_query("DELETE FROM budgets_logs WHERE budget_id = $catalog_id");
    cms_query("DELETE FROM budgets_lines WHERE budget_id = $catalog_id");
    cms_query("DELETE FROM budgets WHERE id = $catalog_id AND type = $TYPE");
    if (cms_affected_rows() <= 0) {
        return serialize(['success' => 0, 'error' => 'Error removing user catalog']);
    }

    foreach (scandir($CURRENT_CATALOG_PATH) as $item) {
        $fileComplete = "$CURRENT_CATALOG_PATH/$item";
        if (file_exists($fileComplete) && $item != '.' && $item != '..') {
            unlink($fileComplete);
        }
    }

    if (is_dir($CURRENT_CATALOG_PATH)) {
        if (!rmdir($CURRENT_CATALOG_PATH)) {
            return serialize(['success' => 0, 'error' => 'Error removing user catalog directory']);
        };
    }

    return serialize(['success' => 1, 'payload' => ['message' => 'User catalog succefully removed from user catalog', 'catalog id' => $catalog_id]]);
}
