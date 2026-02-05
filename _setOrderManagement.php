<?
# Controlador para alterar o estado de uma encomenda
## Usado pelo Backoffice (ec_encomendas.php / ajax_separar_encomendas.php / ec_encomendas_action.php)
## Usado pela RESTAPI (ClassOrderStatus.php)
## Usado pelo Cronjob (cj_encomendas.php)


# @enc_id = id da encomenda
# @accao = acção a ser executada (switch case em baixo)
# @autor = tem que ser recebido em base64
# @faturacao_cloud = se projeto tem faturação cloud, é passado pelo Backoffice e RESTAPI
# @novo_estado = forçar um estado de encomenda (usado na separação pelo Backoffice)
function _setOrderManagement($enc_id=0, $accao, $autor="Processo automático API", $faturacao_cloud=0, $novo_estado=0){

    if ($enc_id < 1 || $accao == ""){
        return serialize(array("success"=>"false", "errorCode"=>"001"));
    }

    if(params('author') == "") {
        $autor = "Processo automático API";
    } else {
        $autor = base64_decode(params('author'));
    }

    if((int)params('cloud_invoice') > 0) $faturacao_cloud = (int)params('cloud_invoice');
    if((int)params('new_status') > 0) $novo_estado = (int)params('new_status');

    global $ARRAY_ESTADOS_ENCOMENDAS, $ARRAY_MB, $ARRAY_ONLY_TB;

    $ARRAY_MB = array(4,10,12,13,23,29,32,37,80,82,84,96,108);
    $ARRAY_ONLY_TB = array(6,39,53,120);

    if(!is_array($ARRAY_ESTADOS_ENCOMENDAS)){
      $ARRAY_ESTADOS_ENCOMENDAS = array("1" => "1", "10" => "10", "40" => "40", "42" => "42", "45" => "45", "50" => "50", "70" => "70", "80" => "80", "100" => "100", "103" => "103", "1000" => "1000");
    }
    $ARRAY_ESTADOS_ENCOMENDAS[48] = "48";


    $s = "SELECT * FROM `ec_encomendas` WHERE `id` = '%s' LIMIT 1";
    $s = sprintf($s,$enc_id);
    $q = cms_query($s);
    $enc = cms_fetch_assoc($q);    

    if($enc['id'] < 1 ) {
        return serialize(array("success"=>"false", "errorCode"=>"002"));
    }

    $origem = "BACKOFFICE";
    switch ($autor) {
        case 'RESTAPI':
            $autor  = "RESTAPI";
            $origem = "RESTAPI";
            break;
        case 'CRONJOB': 
            $autor  = "Processo automático cronjob";
            $origem = "CRONJOB";
            break;
    }


    switch ($accao) {

        # Confirmar pagamento
        case 'payment':
          $response = __pagamento($enc, $autor, $origem);
          break;

        # Cancelar encomenda
        case 'cancel':
          $response = __cancelar($enc, $autor, $origem);
          break;

        # Separar encomenda
        case 'separate':
          $response = __separar($enc, $autor, $origem, $faturacao_cloud, $novo_estado);
          break;

        # Confirmar reembolso
        case 'confirm-refund':
          $response = __confirmar_reembolso($enc, $autor, $origem, $faturacao_cloud);
          break;

        # Colocar em transito
        case 'transit':
          $response = __transito($enc, $autor, $origem);
          break;

        # Colocar entregue
        case 'delivered':
          $response = __entregue($enc, $autor, $origem);
          break;

        default:
          return serialize(array("success"=>"false", "errorCode"=>"003"));
          break;

    }


    return serialize($response);
}




##################################################
#################### FUNÇÕES ####################
##################################################


##################################################
# PAGAMENTO
## Confirma o pagamento da encomenda
function __pagamento($enc, $autor, $origem) {   
    global $ARRAY_ESTADOS_ENCOMENDAS, $CONFIG_CHECKOUT_SEM_EMAIL_ALOCACAO, $slocation, $B2B, $MYACCOUNT_CHANGE_MORE_VALUE;

    $estados_permitidos = array("-1", $ARRAY_ESTADOS_ENCOMENDAS[1]); # 1=em pagamento

    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
      return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }

    if($origem == "RESTAPI") {
        if($enc['tracking_pago']!='') {
            return array("success"=>"false", "errorCode"=>"006");
        }

        $obs = "Processo despoletado por RESTAPI [Pagamento confirmado]";
        cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','98','".$obs."')");
        cms_query("UPDATE ec_encomendas SET tracking_status=1, tracking_pago='".date("Y-m-d H:i:s")."', pagref='RESTAPI_".$enc['id']."' WHERE id='".$enc['id']."'");

        $s = "SELECT * FROM `ec_encomendas` WHERE `id` = '%s' LIMIT 1";
        $s = sprintf($s,$enc['id']);
        $q = cms_query($s);
        $enc = cms_fetch_assoc($q);
    }

    if($enc['tracking_pago']=='') {
        return array("success"=>"false", "errorCode"=>"005");
    }



    if((int)$B2B > 0){
        # Pagamentos de Documentos
        $sql_lines = "SELECT pid,valoruni,tamanho,composition FROM ec_encomendas_lines WHERE order_id='".$enc["id"]."' AND tipo_linha=6";
        $res_lines = cms_query($sql_lines);
        
        if(cms_num_rows($res_lines)>0){
            cms_query("UPDATE ec_encomendas SET tipoencomenda=10 WHERE id='".$enc['id']."'");

        }
         
        while($row_lines = cms_fetch_assoc($res_lines)){
            if($row_lines["tamanho"] == "doc"){
                $update = "UPDATE _tdocumentos SET encomenda_id='".$enc["id"]."', estado_pagamento='1', valor_pago=(valor_pago+".$row_lines["valoruni"].") WHERE id='".$row_lines["pid"]."' ";
            }else{
                $update = "UPDATE ec_pagamentos_emitidos SET encomenda_id='".$enc["id"]."', estado_pagamento='1', datahora_pagamento='".$enc["tracking_pago"]."' WHERE id='".$row_lines["pid"]."' ";
            }
            @cms_query($update);
            
            
            cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('Processo automático','".$enc['id']."','98','Documento pago - ".$row_lines["composition"]." - Valor: ".$row_lines["valoruni"]."')");
        }
        
        
    }
    

    if($enc['cativar_stock']==0) {

        if(is_callable('orderUpdateStock')) {
            call_user_func('orderUpdateStock', $enc['id']);
            cms_query( "UPDATE ec_encomendas SET cativar_stock='1' WHERE id='".$enc['id']."' ");
        }

        if((int)$CONFIG_CHECKOUT_SEM_EMAIL_ALOCACAO==0){
            require_once '_sendEmailAlocacaoStock.php';
            $resp = _sendEmailAlocacaoStock($enc['id']);
        }

    }


    #SEGMENTOS - para voltar a calcular o segmento
    cms_query("UPDATE _tusers SET last_segmt_update='".date("Y-m-d", strtotime('-1 day'))." 00:00:00' WHERE id='".$enc['cliente_final']."'");

    $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[40];
    $estado = 'Em embalamento';
 

    # Envio de SMS com balanco de pts
    if((int)$enc["generatedPoints"] > 0 || (int)$enc["usedPoints"] > 0){
        require_once '_sendSMSBalancePoints.php';
        $resp = _sendSMSBalancePoints($enc['cliente_final']);
    }
  


    #Subscrições prime
    processLinesSubscription($enc);


    # Entrega Geograficamente Limitada
    $new_enc_qnt = processLinesGeographicallyLimitedDelivery($enc, $novo_estado, $estado);
    if( $new_enc_qnt > 0 && $new_enc_qnt != $enc['qtd'] ) $enc['qtd'] = $new_enc_qnt;



    if(strlen($enc['tracking_pago'])>3) {

        if($enc['tracking_tipopaga']==15){ # Em separação loja

            # Alterar o estado
            cms_query("UPDATE ec_encomendas SET tracking_status='".$novo_estado."' WHERE id='".$enc['id']."' LIMIT 1");
            cms_query("UPDATE ec_encomendas_lines SET status='$novo_estado' WHERE order_id='".$enc['id']."'");
            cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='".$novo_estado."', obs='Encomenda colocada no estado $estado' ");

        } else {

            if($enc['pm_marketplace_id']<1){
                __atribuirDescontosCampanhasNImediatas($enc);
            }
            
            $s = "SELECT SUM(unidade_portes) as total FROM ec_encomendas_lines WHERE id_linha_orig<1 AND order_id='".$enc['id']."' and qnt>0";
            $q = cms_query($s);
            $r = cms_fetch_assoc($q);
            
            if($r['total']=="0.00"){
                $novo_estado = 103; # Fechada
                $estado = 'Fechada';
            }


            # Alterar o estado
            cms_query("UPDATE ec_encomendas SET tracking_status='".$novo_estado."' WHERE id='".$enc['id']."' LIMIT 1");
            cms_query("UPDATE ec_encomendas_lines SET status='$novo_estado' WHERE order_id='".$enc['id']."'");
            cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='".$novo_estado."', obs='Encomenda colocada no estado ".$estado."' ");


            # Produtos sem unidades portes - sem entrega
            @cms_query("UPDATE ec_encomendas_lines SET status='103', recepcionada='1' WHERE id_linha_orig<1 AND order_id='".$enc['id']."' AND unidade_portes='0.00' ");

            
            if($novo_estado==103){

                if($enc['pm_marketplace_id']<1){
                    require_once '_setEGift.php';
                    _setEGift($enc['id']);
                }
                __criaFatura($enc, 1);

            }


        }

    }

   
    $s = "SELECT * FROM ec_encomendas_lines where order_id='".$enc['id']."' AND pack=1 AND qnt=1";
    $q = cms_query($s);
    $total = 0;
    $i_pack = 0;

    require_once '_addToBasketExternal.php';

    while($row_l = cms_fetch_assoc($q)){

        $pack = unserialize($row_l["info_pack"]);
        $i_pack++;
        $total -= 1;
        $arr_skus = explode(" + ",$row_l["composition"]);
        $depositos = explode(",",$row_l["deposito_cativado_pack"]);
        $i=1;
        $z=0;

        foreach($arr_skus as $campo=>$valor){
            $quanti = 1;
            if((int)$pack["qtd".$i]>0) $quanti = $pack["qtd".$i];

            $total += $quanti;
            $resp = _addToBasketExternal(base64_encode(trim($valor)), $row_l['id'], $pack["id"], $i, $quanti);
            $i++;
            $a = 1;
            while ($a <= $quanti) {
                $update_dep = "UPDATE ec_encomendas_lines SET deposito_cativado='".$depositos[$z]."' WHERE order_id='".$enc['id']."' AND ref='".trim($valor)."' AND (deposito_cativado='' OR deposito_cativado=0) LIMIT 1";
                cms_query($update_dep);
                $z++;
                $a++;
            }
        }
        
        __aplicaDescontosVoucher($row_l);
        
        $update = "UPDATE ec_encomendas_lines SET qnt='0' WHERE id='".$row_l["id"]."' AND pack='1'";
        cms_query($update);
    }

    if((int)$i_pack > 0){
        if((int)$B2B == 0 ) process_products_iva_b2c($enc['cliente_final'], 0, $enc['id']);
        else process_products_iva_b2b($enc['cliente_final'], '-2', 0, $enc['id']);
    }

    if($total>0){
        $update = "UPDATE ec_encomendas SET qtd=(qtd+".$total.") WHERE id='".$enc['id']."' ";
        cms_query($update);
    }

    # B2B - Pagamentos parciais
    if((int)$enc['percentagem_parcial'] > 0 && $enc['valor_anterior'] > 0){
        $update = "UPDATE ec_encomendas SET valor='".$enc['valor_anterior']."', valor_anterior='".$enc['valor']."' WHERE id='".$enc['id']."' ";
        cms_query($update);
    }


    # 2022-06-06
    # Guardar na ficha do cliente a data da ultima encomenda paga
    cms_query("UPDATE _tusers SET ultima_encomenda_online='".$enc['data']."' WHERE id='".$enc['cliente_final']."' LIMIT 1");


    # Click & Collect
    $s = "SELECT click_collect FROM ec_shipping WHERE id='".$enc['metodo_shipping_id']."' LIMIT 0,1";
    $q = cms_query($s);
    $r = cms_fetch_assoc($q);

    if($r['click_collect']==1){

        $s = "SELECT telemovel_sms, pais FROM ec_lojas WHERE id='".$enc['pickup_loja_id']."' LIMIT 0,1";
        $q = cms_query($s);
        $r = cms_fetch_assoc($q);
        
        if(strlen($r['telemovel_sms'])>5){


            $data = array(
                "telemovel"             =>  $r['telemovel_sms'],
                "lg"                    =>  "pt",
                "CLIENT_NAME"           =>  $enc['nome_cliente'],
                "CLIENT_EMAIL"          =>  $enc['email_cliente'],
                "ORDER_REF"             =>  $enc['order_ref'], 
                "ORDER_TOTAL"           =>  $enc['valor'],
                "ORDER_ID"              =>  $enc['id'],
                "USER_ID"               =>  $enc['cliente_final'],
                "int_country_id"        =>  $r['pais']          
            );  

            $data = serialize($data);
            $data = gzdeflate($data, 9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);

            # 2020-07-16
            require_once '_sendSMSGeneral.php';
            $resp = _sendSMSGeneral(71, $data);

        }
    }

    return array("success"=>"true", "newStatus"=>$novo_estado);
}





##################################################
# CANCELAR ENCOMENDA
## Cancela uma encomenda
function __cancelar($enc, $autor, $origem){
    global $ARRAY_ESTADOS_ENCOMENDAS, $ARRAY_MB, $ARRAY_ONLY_TB;

    if($origem == "RESTAPI") {
        $estados_permitidos = array("-1", $ARRAY_ESTADOS_ENCOMENDAS[1], $ARRAY_ESTADOS_ENCOMENDAS[40], $ARRAY_ESTADOS_ENCOMENDAS[48]); # -1=Processamento de pagamento, 1=em pagamento, 40=Prontas a separar    
    } else {
        $estados_permitidos = array("-1", $ARRAY_ESTADOS_ENCOMENDAS[1], $ARRAY_ESTADOS_ENCOMENDAS[10], $ARRAY_ESTADOS_ENCOMENDAS[40], $ARRAY_ESTADOS_ENCOMENDAS[48]); # -1=Processamento de pagamento, 1=em pagamento, 10=troca, 40=Prontas a separar
    }

    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
      return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }


    if($enc["cativar_stock"]>0){
        if(is_callable('orderReturnStock')) {
            call_user_func('orderReturnStock', $enc['id']);
        }
    }


    if($enc['pagref']==''){

        # 2021-04-14
        # Liberta vouchers
        cms_query("DELETE FROM ec_vouchers_log WHERE user_id='".$enc['cliente_final']."' and encomenda_id='".$enc['id']."' ");
        $linhas_afetadas = cms_affected_rows();
        if($linhas_afetadas==1){
            @cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='98', obs='Cupão libertado por cancelamento da encomenda' ");
        }

        # 2021-04-14
        # Efectua a devolução dos vales
        $checks = cms_query( "SELECT `id` FROM `ec_vales_pagamentos` WHERE `order_id`='".$enc['id']."'" );
        while( $rchecks = cms_fetch_assoc( $checks ) ){
            @cms_query("UPDATE ec_vales_pagamentos SET order_id='-".$enc['id']."' WHERE id='".$rchecks['id']."'");
            @cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='98', obs='Vale libertado por cancelamento da encomenda (ID: ".$rchecks['id'].")' ");
        }

    }


    $obs = "Processo despoletado manualmente no BO [Encomenda cancelada]";
    switch ($origem) {
        case 'RESTAPI': $obs = "Processo despoletado por RESTAPI [Encomenda cancelada]"; break;
        case 'CRONJOB': $obs = "Processo despoletado por cronjob [Encomenda cancelada]"; break;
    }


    # Template de emails
    $template_email_id = 10;
    if($enc['pagref']=='' && in_array($enc['tracking_tipopaga'],$ARRAY_MB)) {

        $template_email_id = 19;

        if($origem == "CRONJOB"){
            $obs = "Cancelamento automático por falha de pagamento multibanco";
        }

    }elseif($enc['pagref']=='' && in_array($enc['tracking_tipopaga'],$ARRAY_ONLY_TB)) {

        $template_email_id = 47;

        if($origem == "CRONJOB"){
            $obs = "Cancelamento automático por falha de pagamento transferência bancária";
        }

    } else {
        $template_email_id = 0;
    }

    if($enc['tracking_status'] == $ARRAY_ESTADOS_ENCOMENDAS[48]) {
        $template_email_id = 10;
    }


    # Alterar o estado
    cms_query("UPDATE ec_encomendas SET tracking_status='".$ARRAY_ESTADOS_ENCOMENDAS[100]."' WHERE id='".$enc['id']."'");
    cms_query("UPDATE ec_encomendas_lines SET status='".$ARRAY_ESTADOS_ENCOMENDAS[100]."' WHERE order_id='".$enc['id']."'");
    cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','".$ARRAY_ESTADOS_ENCOMENDAS[100]."', '".$obs."')");


    # Envio de emails
    if($template_email_id > 0) {
        require_once '_sendEmailGest.php';
        _sendEmailGest($enc['id'],$template_email_id);
    }


    return array("success"=>"true", "newStatus"=>$ARRAY_ESTADOS_ENCOMENDAS[100]);
}





##################################################
# SEPARAR ENCOMENDA
## Separa uma encomenda
## $novo_estado>0 = força a encomenda para um estado (48=Estorno à venda / 50=Separada)
function __separar($enc, $autor, $origem, $faturacao_cloud, $novo_estado=0){

    global $ARRAY_ESTADOS_ENCOMENDAS, $fx, $_API_PATH, $_CHECKOUT_VER, $imagepath, $slocation;

    $estados_permitidos = array($ARRAY_ESTADOS_ENCOMENDAS[40], $ARRAY_ESTADOS_ENCOMENDAS[42], $ARRAY_ESTADOS_ENCOMENDAS[48]); # 40=Prontas a separar, 42=Separadas parcialmente, 48=Estorno à venda
                                    
    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
        return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }



    $recepcionado = cms_fetch_assoc(cms_query("SELECT COUNT(id) as total FROM `ec_encomendas_lines` WHERE order_id='".$enc['id']."' AND recepcionada=1 AND ref!='PORTES' AND id_linha_orig<1"));


    if($novo_estado > 0) { # usado nos botões do BO

        $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[$novo_estado];

    } else if($origem == "RESTAPI" && $enc['tracking_status'] == $ARRAY_ESTADOS_ENCOMENDAS[42]) { # se for RESTAPI e estiver no estado 42

        $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[50];

        if($enc['qtd'] > $recepcionado['total']) {
            $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[48];
        }

    } else {

        # Coloca no estado Separada $ARRAY_ESTADOS_ENCOMENDAS[50]
        if($enc['qtd'] > 0 && $enc['qtd'] == $recepcionado['total']) {

            $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[50];

        # Coloca no estado Separada parcialmente $ARRAY_ESTADOS_ENCOMENDAS[42]
        } else if($recepcionado['total'] > 0) {

            $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[42];

        } else if($origem == "RESTAPI") {

            $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[48];

        } else {
            return array("success"=>"false", "errorCode"=>"007"); # encomenda sem nenhum produto separado
        }

    }

    if($enc['tracking_status'] == $novo_estado) {
        return array("success"=>"false", "errorCode"=>"010"); # a encomenda já está no respetivo estado de destino
    }



    $obs_aux = " [Encomenda embalada]";
    $obs = "Processo despoletado manualmente no BO";

    switch ($origem) {
        case 'RESTAPI': $obs = "Processo despoletado por RESTAPI"; break;
    }

    if($novo_estado == $ARRAY_ESTADOS_ENCOMENDAS[42]) $obs_aux = " [Encomenda embalada parcialmente]";
    if($novo_estado == $ARRAY_ESTADOS_ENCOMENDAS[48]) $obs_aux = " [Encomenda em estorno]";

    $obs = $obs.$obs_aux;

    # Alterar o estado
    cms_query("UPDATE ec_encomendas SET tracking_status='".$novo_estado."' WHERE id='".$enc['id']."'");
    cms_query("UPDATE ec_encomendas_lines SET status='".$novo_estado."' WHERE order_id='".$enc['id']."'");
    cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','".$novo_estado."', '$obs')");


    if($novo_estado == $ARRAY_ESTADOS_ENCOMENDAS[48]) {

        if($enc['tracking_status'] == $ARRAY_ESTADOS_ENCOMENDAS[40]) {
            cms_query("UPDATE ec_encomendas_lines SET qnt=0 WHERE order_id='".$enc['id']."'  ");
        } else {
            cms_query("UPDATE ec_encomendas_lines SET qnt=0 WHERE order_id='".$enc['id']."' AND recepcionada='0' ");
        }


        $s = "SELECT SUM(qnt) as total FROM ec_encomendas_lines WHERE id_linha_orig<1 AND order_id='".$enc['id']."' AND qnt>0 AND ref<>'PORTES'";
        $q = cms_query($s);
        $r = cms_fetch_assoc($q);

        $s = "SELECT SUM(valoruni-desconto) as valor FROM ec_encomendas_lines WHERE order_id='".$enc['id']."' AND qnt>0 AND ref!='PORTES'";
        $q = cms_query($s);
        $rv = cms_fetch_assoc($q);

        cms_query("INSERT INTO ec_encomendas_original_header SET order_id=".$enc['id'].", original_quantity=".$enc['qtd'].", original_value=".$enc['valor']);


        $qtds_finais = cms_fetch_assoc(cms_query("SELECT id FROM ec_encomendas_lines WHERE order_id='".$enc['id']."' AND qnt>0 AND ref!='PORTES' AND ( (pack=1 AND qnt=1) OR (pack!=1 AND qnt=1) OR pack=0 )"));
        if($qtds_finais['id']==0) $valor += $enc["portes"] + $enc["imposto"] + $enc["valor_credito"] + $enc["custo_pagamento"];

        @cms_query("INSERT INTO ec_encomendas_props (`order_id`,`property`,`property_value`) VALUES ('".$enc['id']."','ORDER_VALUE','".$enc['valor']."') ON DUPLICATE KEY UPDATE property_value='".$enc['valor']."'");
        @cms_query("INSERT INTO ec_encomendas_props (`order_id`,`property`,`property_value`) VALUES ('".$enc['id']."','ORDER_QTD','".$enc['qtd']."') ON DUPLICATE KEY UPDATE property_value='".$enc['qtd']."'");

        cms_query("UPDATE ec_encomendas SET valor='".($rv['valor']+$enc['portes']+$enc['imposto']+$enc['custo_pagamento'])."', qtd='".$r['total']."' WHERE id='".$enc['id']."'");
        cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='".$novo_estado."', obs='Dados anteriores: ".$enc['moeda_prefixo'].$enc['valor'].$enc['moeda_sufixo']." - ".$enc['qtd']." unids.'");

    }


    if($novo_estado == $ARRAY_ESTADOS_ENCOMENDAS[50]) {
        __criaFatura($enc, $faturacao_cloud);
    }

    return array("success"=>"true", "newStatus"=>$novo_estado);
}






##################################################
# CONFIRMAR REEMBOLSO
## Confirma o reembolso de uma encomenda
## aceita o estado 42 e 48, e passa para o 50 ou 100 (separada ou cancelada)
function __confirmar_reembolso($enc, $autor, $origem, $faturacao_cloud){

    global $ARRAY_ESTADOS_ENCOMENDAS, $fx, $_API_PATH, $_CHECKOUT_VER, $imagepath, $slocation;

    #$estados_permitidos = array($ARRAY_ESTADOS_ENCOMENDAS[42], $ARRAY_ESTADOS_ENCOMENDAS[48]); # 42=Separadas parcialmente, 48=Estorno à venda
    $estados_permitidos = array($ARRAY_ESTADOS_ENCOMENDAS[48]); # 42=Separadas parcialmente, 48=Estorno à venda

    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
        return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }


    $obs = "Processo despoletado manualmente no BO [Reembolso confirmado]";
    switch ($origem) {
        case 'RESTAPI': $obs = "Processo despoletado por RESTAPI [Reembolso confirmado]"; break;
    }

    cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','98', '$obs')");

    $recepcionado = cms_fetch_assoc(cms_query("SELECT `id` FROM `ec_encomendas_lines` WHERE `order_id`='".$enc['id']."' AND `recepcionada`=1 AND `ref`!='PORTES' AND `id_linha_orig`<1 LIMIT 1"));

    # avança para separada
    if($recepcionado['id'] > 0) {

        $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[50]; # separada
        $resp = __separar($enc, $autor, $origem, $faturacao_cloud, $novo_estado);

        if($resp['success'] == "false") {
            return array("success"=>"false", "errorCode"=>"009");
        }

    # cancela encomenda
    } else {

        $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[100]; # cancelada
        $resp = __cancelar($enc, $autor, $origem);

        if($resp['success'] == "false") {
            return array("success"=>"false", "errorCode"=>"008");
        }

    }


    return array("success"=>"true", "newStatus"=>$novo_estado);
}





##################################################
# TRANSITO
## Coloca a encomenda em transito
function __transito($enc, $autor, $origem){

    global $ARRAY_ESTADOS_ENCOMENDAS;

    $estados_permitidos = array($ARRAY_ESTADOS_ENCOMENDAS[50]); # 50=Separada

    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
      return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }


    $obs = "Processo despoletado manualmente no BO [Encomenda em trânsito]";
    switch ($origem) {
        case 'RESTAPI': $obs = "Processo despoletado por RESTAPI [Encomenda em trânsito]"; break;
    }


    $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[70];


    # Alterar o estado
    cms_query("UPDATE ec_encomendas SET tracking_status='".$novo_estado."' WHERE id='".$enc['id']."'");
    cms_query("UPDATE ec_encomendas_lines SET status='".$novo_estado."' WHERE order_id='".$enc['id']."'");
    cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','".$novo_estado."', '$obs')");


    require_once '_sendEmailGest.php';
    _sendEmailGest($enc['id'],9);

    ### SMS
    $tabela_shipping = "ec_shipping";
    if(trim($enc['return_ref'])!=''){
        $tabela_shipping = "ec_shipping_returns";
    }

    $s = "SELECT sms_notification, sms_shipping_delay_hours FROM $tabela_shipping WHERE id='".$enc['metodo_shipping_id']."' LIMIT 0,1";
    $r = cms_fetch_assoc(cms_query($s));


    # 2021-03-11 - Definido por Serafim que msm que a sms de transito não estaja ativa se a de atraso estiver e tiver sido enviada o sms é enviado na mesma
    if((int)$r['sms_notification']==1 || ((int)$r['sms_shipping_delay_hours']>0 && $enc['remember_mb']==70)){
        try {

            require_once '_sendSMS.php';
            $resp = _sendSMS($enc['id'],62,"");
            $resp = json_decode($resp, true);

            if($resp['status'] == true && (int)$resp['response'][0] == 1){
                @cms_query("INSERT INTO ec_encomendas_log SET autor='Processo automático', encomenda='".$enc['id']."', estado_novo='98', obs='Notificação de SMS ID: 62 '");
            }
        } catch (Exception $e) {
            return array("success"=>"false", "errorCode"=>"011");
        }
    }
    ### SMS


    return array("success"=>"true", "newStatus"=>$novo_estado);
}





##################################################
# ENTREGUE
## Coloca a encomenda entregue
function __entregue($enc, $autor, $origem){

    global $ARRAY_ESTADOS_ENCOMENDAS;

    $estados_permitidos = array($ARRAY_ESTADOS_ENCOMENDAS[70]); # 70=Em trânsito

    if(!in_array($enc['tracking_status'], $estados_permitidos)) {
      return array("success"=>"false", "errorCode"=>"004"); # encomenda no estado errado
    }


    $obs = "Processo despoletado manualmente no BO [Encomenda entregue]";
    switch ($origem) {
        case 'RESTAPI': $obs = "Processo despoletado por RESTAPI [Encomenda entregue]"; break;
        case 'CRONJOB': $obs = "Processo despoletado por cronjob [Encomenda entregue]"; break;
    }


    $novo_estado = $ARRAY_ESTADOS_ENCOMENDAS[80];

    $hoje = date("Y-m-d");
    $pais = cms_fetch_assoc(cms_query("SELECT return_days, return_days_extended FROM ec_mercado WHERE id='".$enc['mercado_id']."' LIMIT 0,1"));
    $dias = $pais['return_days'];

    if((int)$dias<1){
        $dias = 15;
    }

    # Extended reflection period
    $date_interval = (array)date_diff( new DateTime($hoje), new DateTime($pais['return_days_extended']) );
    if( $date_interval['invert'] == 0 && $date_interval['days'] > $dias ){
        $intermediate_deadline = date('Y-m-d',strtotime($hoje." + ".$dias." days"));
        cms_query("INSERT INTO ec_encomendas_props SET order_id='".$enc['id']."', property='INT_RETURN_DATE', property_value='".$intermediate_deadline."'");
        $dias = $date_interval['days'];
    }
    # Extended reflection period

    $data_limite  = date('Y-m-d',strtotime($hoje." + ".$dias." days"));


    # Alterar o estado
    cms_query("UPDATE ec_encomendas SET tracking_status='".$novo_estado."', return_max_date='".$data_limite."'  WHERE id='".$enc['id']."'");
    cms_query("UPDATE ec_encomendas_lines SET status='".$novo_estado."' WHERE order_id='".$enc['id']."' AND status='70' AND qnt>0");
    cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('".$autor."','".$enc['id']."','".$novo_estado."', '$obs')");


    ### Enviar email - template 89 - Encomenda entregue
    require_once '_sendEmailGest.php';
    _sendEmailGest($enc['id'],89);
    @cms_query("INSERT INTO ec_encomendas_log SET autor='".$autor."', encomenda='".$enc['id']."', estado_novo='98', obs='Notificação de email ID 89'");


    ### Enviar SMS - template 90 - Encomenda entregue
    $template_sms  = cms_fetch_assoc(cms_query("SELECT `sms_notification_received` FROM `ec_shipping` WHERE `id` = ".$enc['metodo_shipping_id']." LIMIT 0,1"));
    if((int)$template_sms['sms_notification_received']==1){
        require_once '_sendSMS.php';
        _sendSMS($enc['id'],90,"");
    }

    return array("success"=>"true", "newStatus"=>$novo_estado);
}






###################################################
############### FUNÇÕES AUXILIARES ###############
###################################################


# Atribuir ao utilizador as campanhas não imediatas que possa ter subscrito
function __atribuirDescontosCampanhasNImediatas($row){
    global $slocation, $_CHECKOUT_VER;

    $curl     = curl_init();
    curl_setopt( $curl , CURLOPT_URL , $slocation."/checkout/".$_CHECKOUT_VER."campanhas_funcs.php?enc_id=".$row['id'] );
    curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false );
    curl_setopt( $curl , CURLOPT_RETURNTRANSFER , 1 );
    curl_setopt( $curl , CURLOPT_POST , 1 );
    curl_setopt( $curl , CURLOPT_POSTFIELDS , $request );
    $response = curl_exec( $curl );
    curl_close( $curl );
}

function __aplicaDescontosVoucher($line){
    if($line['desconto_vaucher_id'] == 0) return;

    $sqlLinesChart = "SELECT * FROM ec_encomendas_lines where id_linha_orig<1 AND order_id='".$line['order_id']."' AND pack<>'0' AND ref<>'PORTES' AND desconto_vaucher_id='".$line['desconto_vaucher_id']."'";
    $resLinesChart = cms_query($sqlLinesChart);

    if (cms_num_rows($resLinesChart)==0) return;

    while($rowLinesChart = cms_fetch_assoc($resLinesChart)){
        $c_perc = ($rowLinesChart['valoruni'] * 100) / $line['valoruni'];
        $desconto = ($c_perc * $line['desconto']) / 100;
        cms_query("UPDATE `ec_encomendas_lines` SET `desconto`='".$desconto."' WHERE `id`='".$rowLinesChart['id']."'");
    }
    return;
}


# Cria fatura
function __criaFatura($enc, $faturacao_cloud){
        global $fx, $_API_PATH, $_CHECKOUT_VER, $slocation;

        if((int)$faturacao_cloud>0) {
            $file = $_SERVER['DOCUMENT_ROOT']."/plugins/billing/funcs_print_docs.php";
            if (!defined('_API_PATH')) define("_API_PATH", "../");
        } else {
            $file = $_SERVER['DOCUMENT_ROOT']."/prints/funcs_print_docs.php";
        }


        if(file_exists($file)) {

            include_once $file;
            if (!defined('_ROOT')) define("_ROOT", "../");

            $s   = "SELECT * FROM ec_encomendas where id='".$enc['id']."' LIMIT 0,1";
            $q   = cms_query($s);
            $enc = cms_fetch_assoc($q);

            if(strlen($enc["tracking_factura"])==0){
                setFactura($enc);
            }

            if(is_callable('setNotaEncomenda')){
                setNotaEncomenda($enc);
            }

            $s    = "SELECT * FROM ec_encomendas where id='".$enc['id']."' LIMIT 0,1";
            $q    = cms_query($s);
            $enc  = cms_fetch_assoc($q);
             
            if(trim($enc['tracking_factura'])!=''){
                require_once '_sendEmailGest.php';
                _sendEmailGest($enc['id'],99);

                cms_query("INSERT INTO ec_encomendas_log (autor,encomenda,estado_novo,obs) VALUES('Processo automático','".$enc['id']."','98','Notificação de email ID: 99')");
        
            }

        }

        $s_v = "SELECT ec_vales.id, ec_vales.origin_order_id, ec_vales_pagamentos.valor_descontado 
            FROM ec_vales_pagamentos 
              INNER JOIN ec_vales ON ec_vales.id=ec_vales_pagamentos.check_id 
                AND ec_vales.gift_card_id>0 
            WHERE order_id='".$enc['id']."' AND valor_descontado>0";
        $q_v = cms_query($s_v);
        $r_v = cms_fetch_assoc($q_v);

        if((int)$faturacao_cloud>0 && (int)$r_v['id']>0 && $r_v['origin_order_id']>0 && $r_v['valor_descontado']>0){
            setNotaCredito(array(), $r_v['origin_order_id'], array(), 1, $r_v['valor_descontado']);
        }

        $curl = curl_init();
        curl_setopt( $curl , CURLOPT_URL , $slocation."/checkout/".$_CHECKOUT_VER."campanhas_funcs.php?enc_id='".$enc['id']."'");
        curl_setopt( $curl , CURLOPT_SSL_VERIFYPEER , false );
        curl_setopt( $curl , CURLOPT_RETURNTRANSFER , 1 );
        $response =  curl_exec( $curl );
        curl_close( $curl );

        return $response;
}

?>
