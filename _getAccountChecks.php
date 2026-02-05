<?

function _getAccountChecks($page_id=null){
    
    global $userID;
    global $eComm;
    global $LG;
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    
    
    
    
    $arr['checks'] = getChecks($userID);

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    return serialize($arr);   
    
}


function getChecks($user_id){
    global $LG, $CONFIG_OPTIONS;


    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }
    

    $arr_checks = array();    
    
    $sql_che = "SELECT b2b_cheques.*, b2b_cheques_estados.nomept as nome_estado, b2b_cheques_estados.class_name
                  FROM b2b_cheques
                    INNER JOIN b2b_cheques_estados
                    ON b2b_cheques_estados.id = b2b_cheques.estado_id
                  WHERE b2b_cheques.utilizador_id='".$user_id."'
                    ORDER BY b2b_cheques.data_emissao DESC, b2b_cheques.id DESC";

         
    $res_che = cms_query($sql_che);    
    while($row_che = cms_fetch_assoc($res_che)){
        
        $arr_checks[] = array(
            "id"            =>  $row_che["id"],
            "number"        =>  $row_che['numero_titulo'],
            "bank"          =>  $row_che['banco'],
            "date_issuance" =>  $row_che['data_emissao'],
            "date_due"      =>  $row_che['data_vencimento'],
            "date_state"    =>  $row_che['data_estado'],
            "status_id"     =>  $row_che['estado_id'],
            "status"        =>  $row_che['nome_estado'],
            "value"         =>  call_api_func('moneyOBJ', $row_che['total'], $row_che['moeda_id']),
            "status_class"  =>  $row_che['class_name']
        );
    }
    
    return $arr_checks;

}

?>
