<?
function _addToBasketDocumentPayment($pid = null)
{

    if(is_null($pid)){
        $pid = (int)params('pid');
    }

    global $userID, $eComm, $LG, $COUNTRY, $MARKET, $MOEDA;
    
    
    

    $sql_pagamento  = "SELECT doc.*
                        FROM _tdocumentos doc
                        LEFT JOIN ec_encomendas_lines ec_l ON doc.id=ec_l.pid AND ec_l.tipo_linha=6 AND (ec_l.status>=0 AND ec_l.status!=100)
                        WHERE doc.id='".$pid."'
                            AND doc.estado_pagamento='0'
                            AND doc.encomenda_id='0'
                            AND doc.valor > doc.valor_pago
                            AND doc.id_user='".$userID."'
                            AND ec_l.id is NULL;";

    $res_pagamento  = cms_query($sql_pagamento);
    $row_pagamento  = cms_fetch_assoc($res_pagamento);


    if((int)$row_pagamento["id"] == 0){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);  
    }

    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);

    $row_pagamento["valor_linha"] = $row_pagamento['valor']-$row_pagamento['valor_pago'];
    $row_pagamento['sku_group'] = $row_pagamento["num"];

    add_invoice_credit_note_basket($row_pagamento);


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
