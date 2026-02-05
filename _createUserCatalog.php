<?php

function _createUserCatalog() {
    $TYPE = 2;

    $_POST = decode_array_to_UTF8($_POST);

    $userCatalogName = $_POST['name'];
    if (empty($userCatalogName)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing catalog name']);
    }

    global $userID, $CONFIG_OPTIONS;
    $user_id = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }
    if (!is_numeric($user_id) || (int)$user_id < 1) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid user']);
    }

    $existingCatalogs = cms_num_rows(cms_query("SELECT id FROM budgets WHERE ref ='$userCatalogName' AND type = $TYPE AND status!=2"));
    if ((int) $existingCatalogs > 0) {
        return serialize(['success' => 0, 'error' => 'Bad Request - User catalog already exists', 'name' => $userCatalogName]);
    }

    $expiration_days = 0;

    $arr_insert = [
        'ref' => $userCatalogName,
        'title' => $userCatalogName,
        'created_at_date' => date('Y-m-d'),
        'status' => 1,
        'user_id' => $user_id,
        'client_name' => $_SESSION['EC_USER']['nome'],
        'client_email' => $_SESSION['EC_USER']['email'],
        'client_phone' => $_SESSION['EC_USER']['telefone'],
        'observations' => '',
        'total_value' => 0,
        'total_quantity' => 0,
        'expiration_date' => date('Y-m-d', strtotime('+' . $expiration_days . ' days')),
        'type' => $TYPE,
        'seller_id' => 0
    ];

    $res = insertLineTable('budgets', $arr_insert);
    if (!$res) {
        return serialize(['success' => 0, 'error' => 'Error - Problem creating user catalog']);
    }

    return serialize(['success' => 1, 'payload' => ['message' => 'User catalog created succefully', 'catalog_id' => $res]]);
}
