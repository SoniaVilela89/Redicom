<?

function _addWishlistGroup(){

    global $userID;

    $_POST = decode_array_to_UTF8($_POST);

    $group_name = $_POST['name'];

    if( empty($group_name) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }

    if( !is_numeric($userID) || (int)$userID < 1 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - User invalid']);
    }

    $arr_insert = [
        "nome" => $group_name,
        "utilizador_id" => $userID
    ];

    $res = insertLineTable("registos_wishlist_grupo", $arr_insert);
    if( !$res ){
        return serialize(['success' => 0, 'error' => 'Error on create wishlist group']);
    }

    return serialize(['success' => 1, 'message' => 'Wishlist group added successfully', 'list_id' => $res]);

}
