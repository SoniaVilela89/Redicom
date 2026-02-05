<?

function _sendEmailAlocacaoStock($enc_id=null, $store_id=null){
    
    global $eComm, $fx, $pagetitle, $LG, $slocation, $SETTINGS, $_CHECKOUT_VER, $EMAIL_CONTACT_PAGE, $sitelocation, $SIGLA_SITE;
    
    if ($enc_id > 0){
       $enc_id = (int)$enc_id;
       $store_id = (int)$store_id;
    }else{
       $enc_id = (int)params('orderid');
       $store_id = (int)params('storeid');
    }
    
    if($enc_id>0) $_enc = call_api_func("get_line_table","ec_encomendas", "id='".$enc_id."'");
    
    if((int)$_enc["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }
    
    
    if(is_callable('custom_controller_send_email_alocacao_stock')){
        # Pedido pelo rui - 04/03/2022
        call_user_func('custom_controller_send_email_alocacao_stock', $_enc);
        
        $arr = array();
        $arr['0'] = 1;
        
        return serialize($arr); 
    }
        
    
    $_lg = strtolower($_enc['idioma_user']);
    if( trim($_lg)=="" ) $_lg = "pt";
    if( $_lg=="en" ) $_lg = "gb";
    if( $_lg=="es" ) $_lg = "sp";
    $LG = $_lg;
    
    $userID  = $_enc['cliente_final'];
    $COUNTRY = $eComm->countryInfo($_enc['b2c_pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($_enc['moeda_id']);
    
    $LG = $_enc['entrega_pais_lg'];
    
    $_exp = array();
    $_exp['table'] = "exp";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;
    
    
                        
    $morada_entrega = array();
    
    if($_enc['pickup_loja_id']>0 && $_enc['metodo_shipping_type']!=4){
        $morada_entrega[] = $_enc['pickup_loja_nome'];
        $morada_entrega[] = $_enc['pickup_loja_morada'];
        $morada_entrega[] = $_enc['pickup_loja_cp'];
        $morada_entrega[] = $_enc['pickup_loja_cidade'];
        $morada_entrega[] = $_enc['entrega_pais'];
    }else{
        $morada_entrega[] = $_enc['entrega_morada1'];
        if($_enc['entrega_morada2']!='') $morada_entrega[] = $_enc['entrega_morada2'];
        $morada_entrega[] = $_enc['entrega_cp'];
        $morada_entrega[] = $_enc['entrega_cidade'];
        $morada_entrega[] = $_enc['entrega_pais'];
        
        if($_enc['metodo_shipping_type']==4){
            $morada_entrega[] = "+";
            $morada_entrega[] = estr2(243).": ".$_enc['pickup_loja_nome'];
        }
    }
    
    

    $lines = $eComm->getOrderLinesUngrupped($_enc['id']);
    
       
    
    $array_depositos = array();
    foreach($lines as $k => $line){ 
    
        $qtds_de_depositos = array_unique(explode(',', $line['deposito_cativado_pack']));
          
        # Se é pack e está tudo alocado ao mesmo deposito   
        if($line['pack']==1 && count($qtds_de_depositos)==1) $line['deposito_cativado'] = $qtds_de_depositos[0];      
    
        if($line['deposito_cativado']!=0){  
            
            $arr_pid = array();
            $arr_pid = explode("|||", $line["pid"]);
            if(count($arr_pid)>1){
                $line["pid"] = $arr_pid[1];
            } 
            
            $ean = call_api_func("get_line_table","registos", "id='".$line["pid"]."'");
            $line["ean"] = $ean["ean"];
            
            if($store_id>0){
                if($store_id==$line['deposito_cativado']) $array_depositos[$line['deposito_cativado']][] = $line;
            }else{
                $array_depositos[$line['deposito_cativado']][] = $line;
            }
        }
    }
      
    
    foreach($array_depositos as $k => $v){
    
        $deposito_sql = cms_query("select * from ec_deposito where id='".$k."' AND notificacao='1' AND email!='' LIMIT 0,1");
        $deposito     = cms_fetch_assoc($deposito_sql);
        
       
    
        if(empty($deposito)) continue;
        
        $html = "<table border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr style='background-color: #7E7E7E;'>
                  <th colspan='2' height='36' align='left' style='padding:0 19px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;'>".estr2(106)."</b></p></th>
                  <th height='36' align='right' style='padding:0 19px; line-height:100%;'><p><b style='color: #FFF; line-height:100%;word-break:keep-all;'>".estr2(108)."</b></p></th>
                </tr>";
                
                
              
                
                
        foreach($v as $v1 => $k1){
            
            $more_desc = " - ".$k1['ref'];
            $desc_ean = "";
            if(trim($k1['ean'])!="") $desc_ean = "<p><b>EAN:</b> ".$k1['ean']."</p>";
           
            if($k1['pack']==1){
                $caracteristicas = "<p>
                                        ".$k1['composition']."
                                    </p>";
            }else{
                $caracteristicas = "<p>
                                          <b>".estr2(1)."</b> ".$k1['cor_name']."
                                          <span style='padding-right:10px;'></span>
                                          <b>".estr2(2)."</b> ".$k1['tamanho']."
                                      </p>";
               
            }
          
            $html .= "  <tr>
                        <td style='border-bottom:1px solid #CCCCCC;padding:8px 0;width:80px;line-height:0;' valign='middle'><img src='".$k1['image']."' style='width:80px;line-height:0;display:block;'></td>
                        <td style='border-bottom:1px solid #CCCCCC; padding:8px 19px;' valign='middle' height='74'>
                          <p><b>".$k1['nome']."</b>".$more_desc."</p>
                          ".$desc_ean."
                          ".$caracteristicas."
                        </td>
                        <td style='border-bottom:1px solid #CCCCCC; padding:8px 19px;' valign='middle' height='74' align='right'><p>1</p></td>
                      </tr>";
        }  
        
        $html .= "</table>";

        $email = __getEmailBody(17, $_enc['entrega_pais_lg']);
        
        
        
    
    
        if(trim($email['blocopt'])==''){
            return serialize(array("0"=>"0"));
        }
        
        $email['blocopt'] = str_ireplace("{DETALHES}", $html, $email['blocopt']);
        $email['blocopt'] = str_ireplace("{ORDER_DEPOSIT}", $deposito['nome'], $email['blocopt']);      
        $email['blocopt'] = str_ireplace("{CLIENT_NAME}", $_enc['nome_cliente'], $email['blocopt']);                  
        $email['blocopt'] = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $email['blocopt']);
        $email['blocopt'] = str_ireplace("{ORDER_ID}", $_enc['id'], $email['blocopt']);
        $email['blocopt'] = str_ireplace("{ORDER_DATE}", $_enc['data'], $email['blocopt']);
        $email['blocopt'] = str_ireplace("{PAGETITLE}", $pagetitle, $email['blocopt']);
        
        $email['blocopt'] = str_replace('{CLIENT_DELIVERY_ADDRESS}', implode(' ', $morada_entrega), $email['blocopt']);
        
        $email['nomept'] = str_ireplace("{ORDER_REF}", $_enc['order_ref'], $email['nomept']);        
      

        #troca no dia 25/05/2020
        #sendEmailTransacional($email['blocopt'], $email['nomept'], $deposito['email'], 0, "Alocação de Stock");
        sendEmailFromController($email['blocopt'], $email['nomept'], $deposito['email'], '', $_enc['cliente_final'], "Alocação de Stock");
                                                                                             
        cms_query( "INSERT INTO ec_encomendas_log SET autor='Processo automático', encomenda='".$_enc['id']."', estado_novo='98', obs='Enviado email alocação de stock para: ".$deposito['email']." - ".$deposito['nome']."' ");
    }

    $arr = array();
    $arr['0'] = 1;
    
    return serialize($arr);
}

?>
