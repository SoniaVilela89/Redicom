<?php

function _getUserCatalogs($user_id = 0, $product_id = 0, $my_account = false) {
    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;

    global $userID, $CONFIG_OPTIONS, $LG;
    $hasOriginalID = false;
    if ((int)$user_id <= 0) {
        $user_id = (int)params('user_id');
    }
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
        $hasOriginalID = true;
    }
    if (!is_numeric($user_id) || (int)$user_id < 1 || ($user_id != $userID && !$hasOriginalID)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid user']);
    }

    $filter = '';
    if (!isset($_GET['id'])) {
        $filter = "AND status != $CATALOG_CONCLUDED_ID";
    }
    $catalogResult = cms_query("SELECT * FROM budgets WHERE user_id = $user_id AND type = $TYPE $filter ORDER BY id DESC");

    $catalogs['user_catalogs'] = [];
    $catalogsAux = [];

    while ($catalogInfo = cms_fetch_assoc($catalogResult)) {
        if (!$my_account) {
            $catalogTmp = [
                'id' => $catalogInfo['id'],
                'name' => $catalogInfo['ref']
            ];

            if ($product_id > 0) {
                $productInCatalog = cms_fetch_assoc(cms_query("SELECT COUNT('id') as quantity FROM budgets_lines WHERE budget_id = '" . $catalogTmp['id'] . "' AND product = '$product_id'"));
                if ($productInCatalog['quantity'] > 0) {
                    $catalogTmp['product_in_catalog'] = 1;
                }
            } else {
                $productInCatalog = cms_fetch_assoc(cms_query("SELECT COUNT('id') as quantity FROM budgets_lines WHERE budget_id = '" . $catalogTmp['id'] . "'"));
                $catalogTmp['quantity'] = (int)$productInCatalog['quantity'];
            }
        } else {
            $catalogTmp = $catalogInfo;

            $catalogTmp['last_log'] = cms_fetch_assoc(cms_query("SELECT * FROM budgets_logs WHERE budget_id = '" . $catalogInfo['id'] . "' ORDER BY id DESC LIMIT 1"));
            if (strlen($catalogTmp['last_log']['observation']) > 120) {
                $budget_temp['last_log']['observation'] = substr($catalogTmp['last_log']['observation'], 0, 120) . "...";
            }

            $catalogStatusInfo = $catalogsAux[$catalogInfo['status']];

            if (empty($catalogStatusInfo)) {
                $catalogStatusInfo = cms_fetch_assoc(cms_query("SELECT * FROM catalogs_status WHERE id = '" . $catalogInfo['status'] . "'"));
                $catalogStatusInfo['name'] = $catalogStatusInfo['name' . $LG];
                $catalogStatusInfo['description'] = $catalogStatusInfo['description' . $LG];

                $catalogsAux[$catalogInfo['status']] = $catalogStatusInfo;
                $catalogsAux[$catalogInfo['status']]['total_catalogs'] = 1;
            } else {
                $catalogsAux[$catalogInfo['status']]['total_catalogs'] += 1;
                unset($catalogStatusInfo['total_catalogs']);
            }

            $catalogTmp['status_info'] = $catalogStatusInfo;
            $catalogTmp['products'] = getCatalogProducts($catalogInfo['id']);;
            $catalogTmp['status_history'] = getCatalogStatusHistory($catalogTmp);
        }

        $catalogs['user_catalogs'][] = $catalogTmp;
    }

    usort($catalogsAux, function ($state1, $state2) {
        return strcmp($state1["process_position"], $state2["process_position"]);
    }); # Sorts the array by "process_position" ASC
    $catalogs['all_status_info'] = $catalogsAux;

    return serialize($catalogs);
}

function getCatalogProducts($catalogId) {
    if ((int)$catalogId <= 0) {
        return [];
    }

    $catalogProducts = [];
    $catalogProducts_res = cms_query("SELECT * FROM budgets_lines WHERE budget_id = '$catalogId' ORDER BY id");

    while ($catalogProduct = cms_fetch_assoc($catalogProducts_res)) {
        $catalogProducts[] = $catalogProduct;
    }

    return $catalogProducts;
}

function getCatalogStatusHistory($catalogInfo) {

    global $LG;

    if (!isset($catalogInfo['status_info'])) {
        return [];
    }

    $historyStatus = [];
    $historyStatus_res = cms_query('SELECT *, GROUP_CONCAT(`name' . $LG . '` SEPARATOR " / ") as `name' . $LG . '` FROM `catalogs_status` GROUP BY `process_position` ORDER BY `process_position`');

    while ($catalogStatus = cms_fetch_assoc($historyStatus_res)) {
        $catalogStatus['name'] = $catalogStatus['name' . $LG];
        $catalogStatus['description'] = $catalogStatus['description' . $LG];
        $catalogStatus['current_status'] = 0;

        if ($catalogInfo['status_info']['process_position'] == $catalogStatus['process_position']) {
            $catalogStatus['current_status'] = 1;
        }

        $historyStatus[] = $catalogStatus;

        if ($catalogStatus['current_status'] == 1 && $catalogInfo['status_info']['final_status'] == 1) {
            break;
        }
    }

    return $historyStatus;
}
