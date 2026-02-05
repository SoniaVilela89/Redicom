<?

function _removeWishlistGroup(int $group_id=null){

    global $userID;
    
    if( is_null($group_id) ){
        $group_id = params('group_id');
    }
    
    $group_id = (int)$group_id;

    $result = cms_query("DELETE FROM `registos_wishlist_grupo` WHERE `id`='$group_id' AND `utilizador_id`='$userID'");
    if( $result && cms_affected_rows() > 0 ){
        
        cms_query("DELETE FROM `registos_wishlist` WHERE `wishlist_grupo_id`='$group_id'");
        
        $payload = array( "success" => 1 );

    }else{
        $payload = array( "success" => 0 );
    }
    
    
    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    # Aqui temos de limpar porque as quantidades foram mexidas
    unset($_SESSION['sys_qr_qtw']);
    

    return serialize($payload);

}
