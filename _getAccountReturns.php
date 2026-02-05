<?

function _getAccountReturns($page_id=null)
{

    global $userID, $eComm, $LG, $CONFIG_OPTIONS, $ENCS_OMNI;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $returns  = array();
    $sql      = cms_query("select * from ec_devolucoes where cliente_id='$userOriginalID' AND tipo=0 ORDER BY id DESC");
    
    while($v = cms_fetch_assoc($sql)){
        $returns[] = __account_returns_get_return($v, 0);
    }


    if((int)$ENCS_OMNI == 1){

        $moedas = array();

        $sql    = cms_query("SELECT `id`, `abreviatura` FROM `ec_moedas` WHERE `activo` = 1");
        while($v = cms_fetch_assoc($sql)){
            $moedas[$v['abreviatura']] = $v['id'];
        }

        $sql = cms_query("SELECT `id`, `id` as `order_id`, `venda_ref`, `datahora`, `cliente_id`, `qnt`, `valor_total` as `valor`, `loja_nome`, `entrega_pais_sigla`, `moeda_sigla`, `metodo_pagamento` as `metodo_devolucao_desc`, `tracking_dev_factura`, `iva`, `iva_valor`, `return_ref`, `tracking_status` as `status` FROM `omni_devolucoes` WHERE `cliente_id`='".$userOriginalID."' ORDER BY `id` DESC");
        
        while($v = cms_fetch_assoc($sql)){
            $returns[] = __account_returns_get_return($v, 1, $moedas);
        }

    }


    $return_reasons = array();
    $sql            = cms_query("select * from b2c_devolucoes_motivo");
    
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


function __account_returns_get_return($return, $omni=0, $moedas=array()){

    $return['omni'] = $omni;

    if(!isset($return['moeda_id']) && $return['moeda_sigla'] != "") {
        $return['moeda_id'] = $moedas[$return['moeda_sigla']];
    }

    $return_obj = call_api_func('returnOBJ',$return);

    if( in_array($return_obj['allstates_status'], [1000,1005,1010,1020,1025,1030]) ){
        $return_obj['status_class_name'] = "rdc-state-02";
    }elseif( in_array($return_obj['allstates_status'], [1040]) ){
        $return_obj['status_class_name'] = "rdc-state-04";
    }else{
        $return_obj['status_class_name'] = "rdc-state-03";
    }


    return $return_obj;
}

?>
