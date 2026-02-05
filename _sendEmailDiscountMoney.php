<?


#Usado em ISA

function _sendEmailDiscountMoney($orderid){
    
    global $eComm, $ssitelocation;
    
    if ($orderid > 0){
       $orderid = (int)$orderid;
    }else{
       $orderid = (int)params('orderid');
    }
    
    
    if($orderid>0) $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$orderid."'");
    
    if((int)$_enc["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }
    

    if($eComm->getInvoiceChecksValue($_enc['id']) > 0) {

        $sql = "SELECT check_id
                      FROM ec_vales_pagamentos 
                      WHERE order_id='".$_enc['id']."'
                      ORDER by id DESC
                      LIMIT 0,1";
                      
        $res = cms_query($sql);
        $row = cms_fetch_assoc($res);
             
        $sql = "SELECT *
                      FROM ec_vales 
                      WHERE id='".$row['check_id']."' 
                      LIMIT 0,1";
                      
        $res = cms_query($sql);
        $row_vale = cms_fetch_assoc($res);

        $sql = "SELECT SUM(valor_descontado) as valor_descontado
                      FROM ec_vales_pagamentos 
                      WHERE check_id='".$row['check_id']."'
                      ORDER by id DESC
                      LIMIT 0,1";
                      
        $res = cms_query($sql);
        $row_usado = cms_fetch_assoc($res);

        $valor_vale_disponivel = $row_vale['valor'] - $row_usado['valor_descontado']; # total vale - total descontado nas encomendas

        if($valor_vale_disponivel > 0) {
        
            $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
            $MARKET  = $eComm->marketInfo($COUNTRY['id']);
            $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
                     
            $data = array(
                "DISCOUNT_CODE" => $row_vale['codigo'],
                "DISCOUNT_DATE" => $row_vale['data_validade'],
                "DISCOUNT_REMAINING_AMOUNT" => $_enc['moeda_prefixo'].number_format($valor_vale_disponivel,  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$_enc['moeda_sufixo']
            );
               
            $send_data = serialize($data);
            $send_data = gzdeflate($send_data, 9);
            $send_data = gzdeflate($send_data, 9);
            $send_data = urlencode($send_data);
            $send_data = base64_encode($send_data);
            
                    
            require_once '_sendEmailGest.php';
                          
            _sendEmailGest($_enc['id'], 57, 0, $_enc['email_cliente'], 0, $send_data);
                  
            $arr = array();
            $arr['0'] = 1;
        
            return serialize($arr);      
        }

    }
    
    $arr = array();
    $arr['0'] = 0;

    return serialize($arr);
    
}

?>