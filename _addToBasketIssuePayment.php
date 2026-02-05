<?
function _addToBasketIssuePayment($pid=null)
{
    if(is_null($pid)){
        $pid = (int)params('pid');
    }

    global $userID, $eComm, $LG, $COUNTRY, $MARKET, $MOEDA;
    
    

    
    $sql_pagamento  = "SELECT ec_m.*
                        FROM ec_pagamentos_emitidos ec_m
                        LEFT JOIN ec_encomendas_lines ec_l ON ec_m.id=ec_l.pid AND ec_l.tipo_linha=6 AND (ec_l.status>=0 AND ec_l.status!=100)
                        WHERE ec_m.activo='1' 
                            AND ec_m.deleted='0'
                            AND ec_m.id='".$pid."'
                            AND ec_m.estado_pagamento='0'
                            AND ec_m.encomenda_id='0'
                            AND ec_m.valor > 0
                            AND ec_m.cliente_id='".$userID."'
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

    $arr                        = array();
    $arr['status']              = "0";
    $arr['page_id']             = 0;
    $arr['page_cat_id']         = (int)$GLOBALS["REGRAS_CATALOGO"];
    $arr['page_count']          = 0;
    
    $arr['data']                = date("Y-m-d");
    $arr['datahora']            = date("Y-m-d H:i:s");
        
    $arr['id_cliente']          = $userID;
    $arr['email']               = $_SESSION['EC_USER']['email'];
    $arr['idioma_user']         = $LG;    
    
    $arr['pid']                 = $row_pagamento['id'];
    $arr['ref']                 = $row_pagamento['num_doc'];
    $arr['sku_family']          = $row_pagamento['num_doc'];
    $arr['sku_group']           = $row_pagamento['num_doc'];
    $arr['nome']                = $row_pagamento['num_doc'];
    
    $arr['cor_id']              = "";
    $arr['cor_cod']             = "";
    $arr['cor_name']            = "";
    $arr['peso']                = "";
    $arr['tamanho']             = "";
    $arr['unidade_portes']      = 0;
    $arr['qnt']                 = "1"; 
    $arr['composition']         = $row_pagamento['motivo_emissao'];

    $arr['valoruni']            = $row_pagamento['valor'];

    $arr['mercado']             = $MARKET['id']; 
    
    $arr['pais_cliente']        = $COUNTRY['id'];
    $arr['pais_iso']            = $COUNTRY['country_code'];
    
    $arr['moeda']               = $MOEDA['id'];  
    $arr['taxa_cambio']         = (float)$MOEDA['cambio']==0 ? 1 : $MOEDA['cambio'];
    $arr['moeda_simbolo']       = $MOEDA['abreviatura'];
    $arr['moeda_prefixo']       = $MOEDA['prefixo'];
    $arr['moeda_sufixo']        = $MOEDA['sufixo'];
    $arr['moeda_decimais']      = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares'] = $MOEDA['casa_milhares'];

    $arr['iva_taxa_id']         = 4;
    $arr['image']               = "";
    $arr['page_cat_id']         = "-1";

    # 0 - produto normal; 
    # 1 - produto configurador avançado; 
    # 2 - serviço que não permite desconto de campanha; 
    # 3 - para produto com unidades a multiplicar preço;
    # 4 - produto com entrega geograficamente limitada; 
    # 5 - subscrições prime;
    # 6 - Pagamentos emitidos;
    # 7 - Entrega expresso;
    # 8 - Produto de sorteio;
    # 9 - para que este produto seja ignorado na aplicação, ou cálculo de descontos (campanhas, vouchers, e desconto de modalidade de pagamento);
    $arr['tipo_linha'] = 6;

    $eComm->addToBasket($arr);

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
?>
