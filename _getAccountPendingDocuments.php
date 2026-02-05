<?php

function _getAccountPendingDocuments($page_id=null){

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
    
    $documents = get_pending_documents($userID);
    $arr['documents']   = $documents["documents"];
    $arr['balance']     = $documents["balance"];
    $arr['saldo_exp']   = $documents["saldo_exp"];
    

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);   
    
}


function get_pending_documents($user_id){

    global $LG, $slocation;
    global $CONFIG_OPTIONS;
    
   
    
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }

    $arr_docs = array();    

    $more_where = " AND num NOT LIKE 'NC%' ";
    if((int)$CONFIG_OPTIONS["allow_credit_notes_documents"] == 1) $more_where = "";
    
    // $sql_doc = "SELECT *
    //             FROM _tdocumentos
    //             WHERE id_user='".$user_id."' AND valor_pago < valor $more_where AND debito > 0
    //             ORDER BY data_doc DESC, id DESC";
    $sql_doc = "SELECT *
                FROM _tdocumentos
                WHERE id_user='".$user_id."' AND valor_pago < valor $more_where 
                ORDER BY data_doc DESC, id DESC";
                
    $res_doc = cms_query($sql_doc);
    
    $saldo = 0; 
    
    while($row_doc = cms_fetch_assoc($res_doc)){
    
        $doc_type = get_documents_type($row_doc["tipo"]);
        
        if((int)$CONFIG_OPTIONS["allow_credit_notes_documents"] == 0 && (strpos(strtolower($doc_type["nome".$LG]), 'cr')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nc')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nt')!==false )){
            continue;
        }
        
        $valor_pendente = $row_doc['valor'] - $row_doc['valor_pago'];
                
        if($row_doc['valor_pago'] >= $row_doc['valor']){
            $status_name =  estr2(278);
            $status_id   = 3;
            $status_class = "rdc-state-05";
        }else{
            $status_name  =  estr2(524);
            $status_id    = 1;
            $status_class = "rdc-state-02";
            if( strtotime(date("Y-m-d")) > strtotime($row_doc['data_vencimento']) ){
                $status_name  =  estr2(523);
                $status_id    = 2;
                $status_class = "rdc-state-04";
            }
        }
                
        $file = "";
        $external_file = 0;
        
        if( file_exists($_SERVER['DOCUMENT_ROOT']."/downloads/documents/".$row_doc['num'].".pdf") ){

            if((int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1) {
                $file = $slocation."/api/api.php/validateAccountDocumentsSMS/3/".base64_encode(json_encode(array("docs" => $row_doc['id'])));
            } else {
                $file = $slocation."/api/api.php/getAccountDocument/".base64_encode($user_id."|||".$row_doc['num']);    
            }

        }elseif( trim($row_doc['url']) ){
            $file           = trim($row_doc['url']);
            $external_file  = 1;
        }

        $doc_type = get_documents_type($row_doc["tipo"]);
      
        $moeda = $row_doc['moeda'];
        $link_detalhe = "";
        
        if( (int)$CONFIG_OPTIONS['allow_documents_details'] == 1 ){
            $link_detalhe = "onclick=\"location='?id=38&idd=".$row_doc['id']."'\"";
        }
        
        $status = 0;
        $sql_pagamento  = "SELECT doc.id
                        FROM _tdocumentos doc
                        LEFT JOIN ec_encomendas_lines ON doc.id=ec_encomendas_lines.pid AND ec_encomendas_lines.tipo_linha=6 AND (ec_encomendas_lines.status>=0 AND ec_encomendas_lines.status!=100)
                        WHERE doc.id='".$row_doc["id"]."'
                            AND doc.estado_pagamento='0'
                            AND doc.encomenda_id='0'
                            AND doc.valor > doc.valor_pago
                            AND ec_encomendas_lines.id is NULL;";
                            
        $res_pagamento  = cms_query($sql_pagamento);
        $row_pagamento  = cms_fetch_assoc($res_pagamento);

        if((int)$row_pagamento["id"] == 0) $status = 1;
        


        if($CONFIG_OPTIONS["allow_credit_notes_documents"] == 1 && (strpos(strtolower($doc_type["nome".$LG]), 'cr')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nc')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nt')!==false )){
            
            $saldo -= $valor_pendente;
            
            $valor_pendente = -$valor_pendente;
        }else{
            $saldo += $valor_pendente;
        }
        
        $permite_download = 1;

        # se tiver ativa a opção de validar download por SMS, e for nota de crédito
        if((int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1 && $row_doc['tipo'] == 2) {
            $permite_download = (int)$row_doc['validado_sms'];
        }
        
        $arr_docs[] = array(
            "id"                =>  $row_doc["id"],
            "type"              =>  $doc_type["nome".$LG],
            "number"            =>  $row_doc['num'],
            "description"       =>  $row_doc['descricao'],
            "date_doc"          =>  $row_doc['data_doc'],
            "date_exp"          =>  $row_doc['data_vencimento'],
            "qtd"               =>  $row_doc['qtd'],
            "valor"             =>  call_api_func('moneyOBJ', $row_doc['valor'], $row_doc['moeda']),
            "valor_pago"        =>  call_api_func('moneyOBJ', $row_doc['valor_pago'], $row_doc['moeda']),
            "valor_pendente"    =>  call_api_func('moneyOBJ', $valor_pendente, $row_doc['moeda']),
            "file"              =>  $file,
            "external_file"     =>  $external_file,
            "link_detail"       =>  $link_detalhe,
            "status_id"         =>  $status_id,
            "status_name"       =>  $status_name,
            "class_name"        =>  $status_class,
            "status"            =>  $status,
            "debito"            =>  call_api_func('moneyOBJ', $row_doc['debito'], $row_doc['moeda']),
            "credito"           =>  call_api_func('moneyOBJ', $row_doc['credito'], $row_doc['moeda']),
            "allow_download"    =>  (int)$permite_download,
        );

    }
    
    
    $saldo_exp = estr2(630);
    if($saldo<0) $saldo_exp = estr2(629);
  
    $arr_return = array(
        "documents" => $arr_docs,
        "balance"   => call_api_func('moneyOBJ', abs($saldo), $moeda),
        "saldo_exp" => $saldo_exp
    );
    
    return $arr_return;
}

?>
