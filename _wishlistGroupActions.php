<?

const WISHLIST_ACTION_MOVE = 1;
const WISHLIST_ACTION_COPY = 2;

function _wishlistGroupActions(){

    global $userID;

    $_POST = decode_array_to_UTF8($_POST);

    $list_from = (int)$_POST['list_from'];
    $arr_list_to = $_POST['list_to'];
    $action = (int)$_POST['action'];

    $wishlist_from = get_line_table("registos_wishlist_grupo", "`utilizador_id`='".$userID."' AND `id`='".$list_from."'", "id");

    if( $list_from < 1 || count($arr_list_to) < 1 || $wishlist_from['id'] < 1 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - List undefined']);
    }

    foreach($arr_list_to as $list_to){

        $wishlist_to = get_line_table("registos_wishlist_grupo", "`utilizador_id`='".$userID."' AND `id`='".$list_to."'", "id");
        if( $wishlist_to['id'] > 0 ){

            switch($action){
                case WISHLIST_ACTION_MOVE:
                    _wishlistGroupActionsProducts($list_from, $list_to, WISHLIST_ACTION_MOVE);
                    break;
                case WISHLIST_ACTION_COPY:
                    _wishlistGroupActionsProducts($list_from, $list_to, WISHLIST_ACTION_COPY);
                    break;
                default:
                    break;
            }

        }

    }

    if( $action == WISHLIST_ACTION_MOVE ){
        require_once(__DIR__."/_removeWishlistGroup.php");
        _removeWishlistGroup($list_from);
    }



    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    # Aqui temos de limpar porqu as quantidades foram mexidas
    unset($_SESSION['sys_qr_qtw']);
    
    
    
    require_once(__DIR__."/_getWishlist.php"); 
    return _getWishlist(41);

}

# Auxiliar functions
function _wishlistGroupActionsProducts(int $list_from, int $list_to, int $action){

    global $userID;

    $products_res = cms_query("SELECT * FROM `registos_wishlist` WHERE `wishlist_grupo_id`='".$list_from."'");
    while( $product = cms_fetch_assoc($products_res) ){
        
        $product_in_list = get_line_table("registos_wishlist", "`id_cliente`='".$userID."' AND `status`='0' AND `wishlist_grupo_id`='".$list_to."' AND `ref`='".$product['ref']."'", "`id`");
        if( (int)$product_in_list['id'] > 0 ){
            continue;
        }

        if( in_array( $action, [WISHLIST_ACTION_MOVE, WISHLIST_ACTION_COPY] ) ){

            unset($product['id']);
            
            $product['wishlist_grupo_id'] = $list_to;
            
            insertLineTable("registos_wishlist", $product);

        }

    }

}
