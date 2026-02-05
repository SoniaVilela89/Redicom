<?php

function _updateUserCatalog($catalog_id = 0, $products_to_update = null) {
    $TYPE = 2;
    $CATALOG_CONCLUDED_ID = 2;
    $CATALOG_EDITABLE_STATES = [1];

    $UPDATABLE_FIELDS = [
        'ref',
        'status',
        'title',
        'footer_notes',
        'cover_image',
        'cover_image_aux',
        'logo',
        'products'
    ];
    $FILE_SIZE_LIMIT = 5; //MB needs to change on js aswell
    $FILE_TYPES = [
        'jpg',
        'jpeg',
        'png'
    ];
    $DEFAULT_LOGO_LOCATION = '/images/logo_email_new_layout.png';
    $CATALOGS_PATH = "/downloads/catalogs";

    $_POST = decode_array_to_UTF8($_POST);

    if ((int)$catalog_id == 0) {
        $catalog_id = (int)params('catalog_id');
    }

    if (!is_numeric($catalog_id) || $catalog_id < 1) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Invalid or missing catalog id']);
    }

    if (empty($_POST)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing update information']);
    }

    if (!empty(array_diff(array_keys($_POST), $UPDATABLE_FIELDS)) || !empty(array_diff(array_keys($_FILES), $UPDATABLE_FIELDS))) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Wrong field(s) specified']);
    }

    if (count(array_filter($_POST)) != count($_POST)) {
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing field(s) data']);
    }

    $catalog_info = cms_fetch_assoc(cms_query("SELECT * FROM budgets WHERE id = '$catalog_id' AND type = '$TYPE'"));
    global $userID, $CONFIG_OPTIONS;
    $hasOriginalID = false;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $hasOriginalID = true;
    }

    if ((!isset($catalog_info['id']) || empty($catalog_info)) || ((int)$catalog_info['user_id'] != $userID && !$hasOriginalID) || !in_array($catalog_info['status'], $CATALOG_EDITABLE_STATES)) {
        return serialize(['success' => 0, 'error' => 'Error - Unknown or invalid catalog']);
    }
    if ($catalog_info['status'] == $CATALOG_CONCLUDED_ID) {
        return serialize(['success' => 0, 'error' => 'Error - Catalog already closed']);
    }

    if (is_null($products_to_update)) {
        if (!empty($_POST)) {
            $products_to_update = json_decode(htmlspecialchars_decode($_POST['products']), true);
        } else {
            $products_to_update = json_decode(file_get_contents('php://input'), true);
        }
    }

    $CURRENT_CATALOG_PATH = $_SERVER['DOCUMENT_ROOT']  . "/$CATALOGS_PATH/$catalog_id";
    if ($_FILES) {
        foreach ($_FILES as $key => $file) {
            if ($file['size'] == 0 || ($file['size'] / (1024 * 1024)) > $FILE_SIZE_LIMIT) {
                return serialize(['success' => 0, 'error' => 'Bad Request - Invalid image (size limit: ' . $FILE_SIZE_LIMIT . ')']);
            }

            $ext = explode('.', strtolower($file['name']));
            $ext = array_pop($ext);

            $mimetype = mime_content_type($file['tmp_name']);
            if (!in_array($ext, $FILE_TYPES) || $mimetype == "text/x-php" || stripos($mimetype, "php") !== false) {
                return serialize(['success' => 0, 'error' => 'Bad Request - Invalid file type']);
            }

            if ($file['size'] > 0) {
                $filename = "$key.$ext";

                if (!is_dir($CURRENT_CATALOG_PATH)) {
                    mkdir($CURRENT_CATALOG_PATH, 0777, true);
                }

                if (!copy($file['tmp_name'], "$CURRENT_CATALOG_PATH/$filename")) {
                    return serialize(['success' => 0, 'error' => 'Error copying image file', 'file' => $CURRENT_CATALOG_PATH . $filename]);
                }

                $_POST[$key] = "$CATALOGS_PATH/$catalog_id/$filename";
            }
        }
    }

    if ($_POST['cover_image'] == 'null') {
        if ($_POST['cover_image_aux'] != 'null' && $_POST['cover_image_aux'] != '') {
            $_POST['cover_image'] = $_POST['cover_image_aux'];
        }
    }

    if ($_POST['logo'] == 'null') {
        if (trim($catalog_info['logo']) == '' && file_exists($CURRENT_CATALOG_PATH.'/'.$DEFAULT_LOGO_LOCATION)) {
            $_POST['logo'] = $DEFAULT_LOGO_LOCATION;
        } else {
            unset($_POST['logo']);
        }
    }

    $fields_query = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['products', 'cover_image_aux'])) continue;
        $fields_query[] = safe_value($key)." = '".safe_value($value)."'";
    }

    if (!empty($fields_query)) {
        $sql = "UPDATE budgets SET ".implode(", ", $fields_query)." WHERE id = $catalog_id AND type = $TYPE";
        cms_query($sql);

        if (cms_affected_rows() < 0) {
            return serialize(['success' => 0, 'error' => 'Error updating fields']);
        }
    }




    $catalog_info['products'] = [];
    if (isset($products_to_update) && !empty($products_to_update)) { # Adds if no ID or Updates given the ID in the array structure
        foreach ($products_to_update as $product_info) {
            if (isset($product_info['id']) && (int)$product_info['id'] > 0) {
                $product_budget = cms_fetch_assoc(cms_query("SELECT * FROM `budgets_lines` WHERE `budget_id`=" . $catalog_id . " AND `id`=" . $product_info['id']));
                if (
                    $product_budget['markup_percentage'] != $product_info['markup']['percentage'] ||
                    $product_budget['discount_percentage'] != $product_info['discount']['percentage'] ||
                    $product_budget['quantity'] != $product_info['quantity']['value_original'] ||
                    $product_budget['product_price'] != $product_info['price']['value_original']
                ) {

                    $markup = $product_info['markup']['percentage'] / 100;
                    $discount = $product_info['discount']['percentage'] / 100;
                    $quantity = (int)$product_info['quantity']['value_original'] <= 0 ? 1 : $product_info['quantity']['value_original'];
                    if ($markup <= 0 && $product_budget['product_price'] != $product_info['price']['value_original']) {
                        $product_price = $product_info['price']['value_original'];
                    } else {
                        $product_price = $product_budget['product_price'];
                    }

                    $product_price_with_markup = $product_price + ($product_price * $markup);
                    $final_price_uni = $product_price_with_markup;
                    $final_price_uni -= $final_price_uni * $discount;
                    if ($catalog_info['type'] == 0 && $product_budget['product_type'] != 2 && $final_price_uni < $product_price) {
                        $product_info['discount']['percentage'] = round(100 - (($product_price * 100) / $product_price_with_markup), 2);
                        $final_price_uni = $product_price;
                    }

                    $final_price = $final_price_uni * $quantity;

                    $product_budget['product_price'] = $product_price;
                    $product_budget['markup_percentage'] = $product_info['markup']['percentage'];
                    $product_budget['discount_percentage'] = $product_info['discount']['percentage'];
                    $product_budget['quantity'] = $product_info['quantity']['value_original'];
                    $product_budget['final_price_uni'] = $final_price_uni;
                    $product_budget['final_price'] = $final_price;

                    cms_query(
                        "
                        UPDATE `budgets_lines`
                        SET
                            `markup_percentage` = " . $product_info['markup']['percentage'] . ",
                            `discount_percentage` = " . $product_info['discount']['percentage'] . ",
                            `quantity` = " . $product_info['quantity']['value_original'] . ",
                            `final_price` = " . $final_price . ",
                            `final_price_uni` =" . $final_price_uni . ",
                            `product_price` = " . $product_price . "
                        WHERE `budget_id` = " . $catalog_id . " AND `id` = " . $product_info['id']
                    );
                }
            }

            $catalog_info['products'][] = $product_budget;
        }
    }

    update_budget_totals($catalog_id);

    return serialize(['success' => 1, 'payload' => ['message' => 'User catalog updated', 'catalog' => $catalog_info]]);
}

function cleanDir($path = null, $remove = false) {
    foreach (scandir($path) as $item) {
        $fileComplete = "$path/$item";
        if (file_exists($fileComplete) && $item != '.' && $item != '..') {
            unlink($fileComplete);
        }
    }

    if ($remove && is_dir($path)) {
        if (!rmdir($path)) {
            echo "error removing folder\n";
        };
    }
}
