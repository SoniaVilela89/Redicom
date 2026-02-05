<?

function _getWishlistPublic($page_id=0, $id_user=0, $wishlist_ids=0){
    

    if ($page_id==0){
       $page_id = (int)params('page_id');
       $id_user = (int)params('id_user');
       $wishlist_ids = (int)params('wishlist_ids');
    }
    
    $arr = array();
   
    $row = call_api_func('get_pagina', 93, "_trubricas");
        
    $arr['page'] = call_api_func('OBJ_page', $row, 93, 0, 0, "_trubricas");
    $arr['items'] = call_api_func('OBJ_lines', 0, 2, 41, $id_user, $wishlist_ids);
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['wishlist_public'] = call_api_func('getUserWish', $id_user);
    $arr['expressions'] = call_api_func('get_expressions',41);
    return serialize($arr);

}


?>
