<?php

function _getAccountBankDetails($page_id=null)
{   
    global $userID;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");

    $sql_user   = "SELECT iban FROM _tusers WHERE id='".$userID."'";
    $res_user   = cms_query($sql_user);
    $row_user   = cms_fetch_assoc($res_user);
    $iban       = $row_user["iban"];
    
    if(trim($iban) == "" || strlen($iban) < 10){
        $sql = "SELECT nib FROM encomendas_estornos WHERE nib<>'' AND cliente_id='".$userID."' AND save_iban=1 ORDER BY id DESC LIMIT 0,1";
        $res = cms_query($sql);
        $row = cms_fetch_assoc($res);
        $iban = $row["nib"];
    }

    if(strlen($iban) < 10) $iban = "";
    $iban = str_replace(' ', '', $iban);
    
    $arr['iban'] =  $iban;
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');    
    $arr['account_expressions'] = call_api_func('getAccountExpressions');

    return serialize($arr);

}