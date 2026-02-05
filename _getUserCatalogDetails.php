<?php

function _getUserCatalogDetails($user_id = 0, $catalog_id = 0) {

    $TYPE = 2;

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

    $catalog = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = '$catalog_id' AND user_id = '$user_id' AND type = $TYPE"));
    if ((int)$catalog_id < 1 || $catalog_id < 1 || empty($catalog)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid catalog']);
    }



    $catalog['cover_image_file_size'] = file_size(filesize($_SERVER['DOCUMENT_ROOT'] . $catalog['cover_image']));
    $catalog['cover_image_file_url'] = $catalog['cover_image'];
    $catalog['cover_image_file_name'] = getImageName($catalog['cover_image']);
    $catalog['logo_file_size'] = file_size(filesize($_SERVER['DOCUMENT_ROOT'] . $catalog['logo']));
    $catalog['logo_file_url'] = $catalog['logo'];
    $catalog['logo_file_name'] = getImageName($catalog['logo']);

    $catalog['token'] = md5($catalog['id'] . "|||" . $catalog['user_id'] . "|||" . $catalog['created_at'] . "|||" . $catalog['type']);

    $catalog['status_info'] = cms_fetch_assoc(cms_query("SELECT * FROM catalogs_status WHERE id = '" . $catalog['status'] . "'"));
    $catalog['status_info']['name'] = $catalog['status_info']['name' . $LG];
    $catalog['status_info']['description'] = $catalog['status_info']['description' . $LG];
    $catalog['status_info']['budget_editable'] = $catalog['status_info']['catalog_editable'];

    $catalog['products'] = getCatalogProducts($catalog['id']);
    $catalog['logs'] = getCatalogLogs($catalog['id']);

    $catalog['all_status'] = getCatalogAllStatus($catalog['status_info']);

    return serialize(['success' => true, 'payload' => $catalog]);
}

function getCatalogProducts($catalogId) {
    global $CONFIG_OPTIONS;

    if ((int)$catalogId <= 0) {
        return [];
    }

    $catalogProducts = [];
    $catalogProducts_res = cms_query("SELECT * FROM budgets_lines WHERE budget_id = '$catalogId' ORDER BY id");

    while ($catalogProduct = cms_fetch_assoc($catalogProducts_res)) {
        if ($catalogProduct['product_type'] == 1) {
            $catalogProduct['product_details'] = call_api_func('get_product', $catalogProduct['product'], '', 5);

            if((int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'] == 0) { # Se for Adicionar produto ao nível de: Ref. Cor
                $catalogProduct['product_details']['selected_variant']['title'] = $catalogProduct['product_details']['selected_variant']['color']['long_name'];
            }
        }

        $catalogProducts[] = $catalogProduct;
    }

    return $catalogProducts;
}

function getCatalogLogs($catalogId) {
    if ((int)$catalogId < 1) {
        return [];
    }

    $catalogLogs = [];
    $catalogLog_res = cms_query("SELECT * FROM budgets_logs WHERE budget_id = '$catalogId' ORDER BY id DESC");

    while ($catalogLog = cms_fetch_assoc($catalogLog_res)) {
        $catalogLogs[] = $catalogLog;
    }

    return $catalogLogs;
}

function getCatalogAllStatus($currentCatalogStatusInfo) {
    global $LG;

    $all_status     = [];
    $all_status_res = cms_query('SELECT *, GROUP_CONCAT(`name' . $LG . '` SEPARATOR " / ") as `name' . $LG . '` FROM `catalogs_status` WHERE `hide`=0 GROUP BY `process_position` ORDER BY `process_position`');

    while ($catalogStatus = cms_fetch_assoc($all_status_res)) {
        $catalogStatus['name'] = $catalogStatus['name' . $LG];

        if ($currentCatalogStatusInfo['process_position'] == $catalogStatus['process_position']) {
            $catalogStatus = $currentCatalogStatusInfo;
        }

        $all_status[] = $catalogStatus;

        if ((int)$catalogStatus['final_status'] == 1) {
            break;
        }
    }

    return $all_status;
}

function getImageName($filename) {
    $exploded  = explode('.', end(explode('/', $filename)));
    $name = ucwords(str_replace('_', ' ', $exploded[0]));
    $ext = strtoupper($exploded[1]);

    return $name . ' ' . $ext;
}
