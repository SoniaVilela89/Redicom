<?

function _addToBasketDocumentPayments(){
    
    global $userID, $eComm, $LG, $COUNTRY, $MARKET, $MOEDA;
    
    
             

    if(count($_POST["pids"]) == 0){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);  
    }

    $pids = implode("','", $_POST["pids"]);

    if(trim($pids) == ''){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);  
    }

    $sql_pagamento  = "SELECT doc.*
                        FROM _tdocumentos doc
                        LEFT JOIN ec_encomendas_lines ec_l ON doc.id=ec_l.pid AND ec_l.tipo_linha=6 AND (ec_l.status>=0 AND ec_l.status!=100)
                        WHERE doc.id in ('".$pids."')
                            AND doc.estado_pagamento='0'
                            AND doc.encomenda_id='0'
                            AND doc.valor > doc.valor_pago
                            AND doc.id_user='".$userID."'
                            AND ec_l.id is NULL;";

    $res_pagamento  = cms_query($sql_pagamento);
    $num_pagamento  = cms_num_rows($res_pagamento);

    if((int)$num_pagamento == 0 || count($_POST["pids"]) != $num_pagamento){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);  
    }   

    $arr_pagamentos = array();
    $total = 0;
    while ($row_pagamento  = cms_fetch_assoc($res_pagamento)) {

        $doc_type = get_documents_type($row_pagamento["tipo"]);
        $valor_linha = $row_pagamento['valor']-$row_pagamento['valor_pago'];
        if((strpos(strtolower($doc_type["nome".$LG]), 'cr')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nc')!==false || strpos(strtolower($doc_type["nome".$LG]), 'nt')!==false )){
            $total -= $valor_linha;
            $valor_linha = -$valor_linha;
        }else{
            $total += $valor_linha;
        }  
        $row_pagamento["valor_linha"] = $valor_linha;
        $row_pagamento['sku_group'] = $doc_type["nome".$LG];

        $arr_pagamentos[] = $row_pagamento;
        
    } 

    if($total < 0){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp); 
    }

    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);

    foreach($arr_pagamentos as $k => $v){
        add_invoice_credit_note_basket($v);
    }

    $resp           =   array();
    $resp['cart']   =   OBJ_cart(true);

    $data = serialize($resp['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;
    
    
  
    return serialize($resp);
    
}
