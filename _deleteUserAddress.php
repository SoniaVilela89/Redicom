<?

function _deleteUserAddress(int $id=0, string $token=NULL){

    if( $id <= 0 ){
        $id = (int)params('id');
    }

    if( $id <= 0 || empty($token) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }

    global $userID;

    $user_address = cms_fetch_assoc( cms_query( "SELECT `id`, 
                                                        `id_user`, 
                                                        `morada1`, 
                                                        `pais`, 
                                                        `created_at`,
                                                        `descpt` 
                                                    FROM `ec_moradas` 
                                                    WHERE `id`='".$id."' 
                                                        AND `id_user`='".$userID."'" ) );
    if( (int)$user_address['id'] <= 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
    }

    if( $token != md5($user_address['id']."|||".$user_address['id_user']."|||".$user_address['morada1']."|||".$user_address['pais']."|||".$user_address['created_at']) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
    }
    
    if( trim($user_address['descpt']) == trim( estr2(55) ) ){
        return serialize(['success' => 0, 'error' => "User address can't be removed"]);
    }
    
    $query_success = cms_query("DELETE FROM `ec_moradas` WHERE `id`='".$id."' AND `id_user`='".$userID."'");
    if( !$query_success ){
        return serialize(['success' => 0, 'message' => 'Error on remove user address']);
    }

    return serialize(['success' => 1, 'message' => 'User address removed successfully']);

}

?>
