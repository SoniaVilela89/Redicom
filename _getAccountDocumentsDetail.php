<?

function _getAccountDocumentsDetail($page_id=null, $doc_id=null)
{
    global $userID;
    global $eComm;
    global $LG;
    
    if(is_null($page_id)){
        $page_id  = (int)params('page_id');
        $doc_id   = (int)params('doc_id');
    }
    
    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['documents'] = getDocumentDetail($doc_id, $userID);
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);
    
}

function getDocumentDetail($doc_id, $userID){
    global $LG;
    global $CONFIG_OPTIONS;
    
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userID = $_SESSION['EC_USER']['id_original'];
    }
    
    $arr_doc_lines = array(); 
    
    $sql_doc = "SELECT *
                FROM _tdocumentos
                WHERE id_user='".$userID."' AND id='".$doc_id."' LIMIT 0,1";
    $res_doc = cms_query($sql_doc);
    $row_doc = cms_fetch_assoc($res_doc);
    
    if((int)$row_doc["id"]==0) return;
    
    $sql_doc_lines = "SELECT *, COUNT(`sku`) as `qtd`, SUM(`valor`) as `valor_total` FROM `_tdocumentos_lines` WHERE `id_documento`=".$row_doc["id"]." GROUP BY `sku`, `valor` ORDER BY `sku`";

    $res_doc_lines = cms_query($sql_doc_lines);
    while($row_doc_lines = cms_fetch_assoc($res_doc_lines)){
        $arr_doc_lines[]  = array(
            "id"          => $row_doc_lines["id"],
            "qtd"         => $row_doc_lines["qtd"],
            "sku"         => $row_doc_lines['sku'],
            "name"        => $row_doc_lines['nome'],
            "value"       => call_api_func('moneyOBJ',$row_doc_lines['valor'], $row_doc_lines['moeda']),
            "total_value" => call_api_func('moneyOBJ',$row_doc_lines['valor_total'], $row_doc_lines['moeda'])
        );
    }
    
    $arr_doc = array(); 
    
    $type = get_documents_type($row_doc["tipo"]);
    
    $arr_doc = array(
        "id"         => $row_doc["id"],
        "type"       => $type["nome".$LG],
        "number"     => $row_doc['num'],
        "date_doc"   => $row_doc['data_doc'],
        "date_exp"   => $row_doc['data_vencimento'],
        "qtd"        => $row_doc['qtd'],
        "valor"      => call_api_func('moneyOBJ', $row_doc['valor'], $row_doc['moeda']),
        "valor_pago" => call_api_func('moneyOBJ', $row_doc['valor_pago'], $row_doc['moeda']),
        "debito"     => call_api_func('moneyOBJ', $row_doc['debito'], $row_doc['moeda']),
        "credito"    => call_api_func('moneyOBJ', $row_doc['credito'], $row_doc['moeda']),
        "saldo"      => call_api_func('moneyOBJ', $row_doc['saldo'], $row_doc['moeda']),
        "lines"      => $arr_doc_lines
    );
    
    return $arr_doc;
}
    
?>
