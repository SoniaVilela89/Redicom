<?

function _getAccountComplaints($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;
    global $CONFIG_OPTIONS;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $returns  = array();
    $sql      = cms_query("SELECT * FROM ec_devolucoes WHERE cliente_id='$userOriginalID' AND tipo=1 ORDER BY id DESC");
    
    while($v = cms_fetch_assoc($sql)){
        $returns[] = call_api_func('complaintOBJ', $v);
    }


    $return_reasons = array();
    $sql            = cms_query("select * from b2c_reclamacoes_motivo");
    
    while($v = cms_fetch_assoc($sql)){
         $return_reasons[] = array(
            "id"    => $v['id'],
            "title" => $v['nome'.$LG]
         );
    }



    $arr                        = array();
    $arr['page']                =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']       =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['returns']             =  $returns;
    $arr['customer']            = call_api_func('getCustomer');
    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    $arr['return_reasons']      = $return_reasons;
    return serialize($arr);

}
?>
