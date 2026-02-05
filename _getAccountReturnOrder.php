<?

function _getAccountReturnOrder($page_id=null, $order_id=0)
{
    
    global $userID;
    global $LG;
    global $eComm;
    global $MYACCOUNT_SEM_VALES, $MYACCOUNT_EXCHANGES_WITHOUT_COLORS;    
    global $MARKET;
    global $CONFIG_OPTIONS, $CONFIG_DEVS_ISA_C_SERVICOS, $CONFIG_DEV_PICAGEM, $CONFIG_DEVS_TB_SEM_IBAN, $MYACCOUNT_CHANGE_MORE_VALUE;
    
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    
    if(is_null($order_id)){
        $order_id = (int)params('order_id');
    }

    global $ARRAY_ESTADOS_ENCOMENDAS;
    if(!is_array($ARRAY_ESTADOS_ENCOMENDAS)){
        $ARRAY_ESTADOS_ENCOMENDAS = array("1" => "1", "10" => "10", "40" => "40", "42" => "42", "45" => "45", "50" => "50", "70" => "70", "80" => "80", "100" => "100", "103" => "103", "1000" => "1000");        
    }
    
    $MARKET_temp = $MARKET;
    
    $orders = array(); 
    $order = array();
    $metodo_envio = 0;
    $return = 0;      
        
    if($order_id>0){    
        
        $ENC_ORIG = $v = $eComm->getOrder($userOriginalID, $order_id); 
        $pais_id = $v["b2c_pais"];
        
        $metodo_envio = $v["transportadora_id"];

        if(trim($v['return_ref'])!=''){
            #$return = 1;
            
            $sql_dev = "SELECT e.transportadora_id FROM ec_devolucoes d INNER JOIN ec_encomendas e ON e.id=d.order_id AND e.return_ref='' WHERE d.return_ref='".$v['return_ref']."' ";
            $res_dev = cms_query($sql_dev);
            $row_dev = cms_fetch_assoc($res_dev);

            if(trim($row_dev["transportadora_id"])!="") $metodo_envio = $row_dev["transportadora_id"];  
        }
        
        $orders[] = $order = call_api_func('orderOBJ',$v);  
                
    }else{ 
        
        $encomendas = $eComm->getOrders($userOriginalID, $ARRAY_ESTADOS_ENCOMENDAS[80].",".$ARRAY_ESTADOS_ENCOMENDAS[103]);
        foreach( $encomendas as $k => $v ){
            $orders[] = call_api_func('orderOBJ',$v);
            $pais_id = $v["b2c_pais"]; 
            $metodo_envio = $v["transportadora_id"];          
            if(trim($v['return_ref'])!=''){
                #$return = 1;
                
                $sql_dev = "SELECT e.transportadora_id FROM ec_devolucoes d INNER JOIN ec_encomendas e ON e.id=d.order_id AND e.return_ref='' WHERE d.return_ref='".$v['return_ref']."' ";
                $res_dev = cms_query($sql_dev);
                $row_dev = cms_fetch_assoc($res_dev);
                
                if(trim($row_dev["transportadora_id"])!="") $metodo_envio = $row_dev["transportadora_id"];
            }
        } 
                
    } 
    
    
                



    #07/04/2020 Retirar produtos de download para nao poder devolver
    foreach($orders as $k => $v){    
    
             
        $s              = "SELECT * FROM `ec_encomendas_original_header` WHERE `order_id`=".$v['order_number'];
        $q              = cms_query($s);
        $row_old_info   = cms_fetch_assoc($q);
        
        if( $row_old_info['order_id'] > 0 ){                
            $v['total_price']['value'] = $row_old_info['original_value'];                
        }
                
        $TOTAL_ENC = ($v['total_price']['value']-$v['total_payment_tax']['value_original']-$v['imposto']['value_original']);  
      
        $vale_de_desconto_s = "SELECT SUM(valor_descontado) as total from ec_vales_pagamentos WHERE order_id='".$v['order_number']."' AND valor_descontado>0 AND vale_de_desconto=1 LIMIT 0,1"; 
        $vale_de_desconto_q  = cms_query($vale_de_desconto_s);  
        $vale_de_desconto_r  = cms_fetch_assoc($vale_de_desconto_q);
        
        if($vale_de_desconto_r['total']>0){        
            $TOTAL_ENC += $vale_de_desconto_r['total'];                 
        }
        
         
            
        # 2025-06-09
        # Configuração a indicar que os pontos não afetam os portes
        $CONF_PONTOS = cms_fetch_assoc(cms_query("SELECT campo_11 FROM b2c_config_loja WHERE id=20 LIMIT 0,1"));     
        if($CONF_PONTOS['campo_11']==1) $TOTAL_ENC -= $v['shipping_price']['value_original'];
        
                
        foreach($v['lines'] as $kk => $vv){
            if( $vv['data_line']['unidade_portes'] == 0 || $vv['data_line']['tipo_linha'] == 6 ){ unset($orders[$k]['lines'][$kk]); }  
            
            $vale_de_desconto = 0;
            if($vale_de_desconto_r['total']>0){
                $orders[$k]['lines'][$kk]['line_price']['value'] -= (((($orders[$k]['lines'][$kk]['line_price']['value'])*100)/$TOTAL_ENC)*$vale_de_desconto_r['total'])/100;     
            }
            
        }
    }
    
    $MARKET = $eComm->marketInfo($pais_id);
    
    $return_reasons = array();



    $hoje = strtotime(date('Y-m-d'));    

    $more_q_reason = '';  
    if(trim($order['return_max_date'])!='' && $hoje>strtotime($order['return_max_date']) && $_SESSION['dev_isa']>0 ){
        $more_q_reason = " AND id=4 "; 
    }
    

    $sql = cms_query("SELECT * FROM b2c_devolucoes_motivo WHERE nome$LG!='' $more_q_reason ORDER BY id");
    while($v = cms_fetch_assoc($sql)){
    
         if((int)$_GET['oms']==0 && (int)$v['only_oms']>0) continue;
          
         $return_reasons[] = array(
            "id" => $v['id'],
            "title"=> $v['nome'.$LG]
         );
    }

    $SHOW_BENEFIT_MESSAGE = 0;
    if($orders[0]["generatedPoints_value"] > 0){
        $sql_enc_point = "SELECT GROUP_CONCAT(id) as ids FROM ec_encomendas WHERE id > '".$orders[0]["order_number"] ."' AND tracking_status!=100 AND cliente_final='".$userID."'";
        $res_enc_point = cms_query($sql_enc_point);
        $row_enc_point = cms_fetch_assoc($res_enc_point);

        if(trim($row_enc_point["ids"]) != ''){
            $sql_vale_point = "SELECT id FROM ec_vales_pagamentos WHERE order_id in (".$row_enc_point["ids"].") AND vale_de_desconto = 1";
            $res_enc_point = cms_query($sql_vale_point);
            $num_enc_point = cms_num_rows($res_enc_point);
            if((int)$num_enc_point > 0) $SHOW_BENEFIT_MESSAGE = 1;
        }
    }

    $arr = array();
    $arr['page']                      = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']             = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['orders']                    = $orders;
    $arr['customer']                  = call_api_func('getCustomer');
    $arr['shop']                      = call_api_func('OBJ_shop_mini');
    $arr['account_expressions']       = call_api_func('getAccountExpressions');
    $arr['return_reasons']            = $return_reasons;
    $arr['MYACCOUNT_SEM_VALES']       = $MYACCOUNT_SEM_VALES;
    $arr['EXCHANGES_WITHOUT_COLORS']  = (int)$MYACCOUNT_EXCHANGES_WITHOUT_COLORS;
    
    
    
    $arr['CONFIG_DEVS_ISA_C_SERVICOS']  = (int)$CONFIG_DEVS_ISA_C_SERVICOS;    
    $arr['CONFIG_DEVS_PICAGEM']         = (int)$CONFIG_DEV_PICAGEM;    
    $arr['CONFIG_DEVS_TB_SEM_IBAN']     = (int)$CONFIG_DEVS_TB_SEM_IBAN;
    $arr['CHANGE_MORE_VALUE']           = (int)$MYACCOUNT_CHANGE_MORE_VALUE;

    $arr['SHOW_BENEFIT_MESSAGE']        = (int)$SHOW_BENEFIT_MESSAGE;
    
    
    
    $sql_condi_dev = "SELECT condicoes_devolucao$LG FROM ec_mercado_info WHERE id_mercado='".$MARKET["id"]."' LIMIT 0,1";
    $res_condi_dev = cms_query($sql_condi_dev);
    $row_condi_dev = cms_fetch_assoc($res_condi_dev);

    $arr['page']['content'] = base64_decode($arr['page']['content']);
    $arr['page']['content'] = base64_encode($arr['page']['content'].$row_condi_dev["condicoes_devolucao$LG"]);
    
    
    # 2021-02-26
    # Colocar o id do beneficiario para das devolucoes de oferta colocar as moradas deste cliente e não do original
    if((int)$_SESSION['OMS_BENEFICIARIO']>0){
        $userID = $_SESSION['OMS_BENEFICIARIO'];    
    }
    
    $arr['requestCollection']   = getRequestCollection($pais_id, $metodo_envio, $return, $order);
    $arr['requestDelivery']     = getRequestDelivery($pais_id, $metodo_envio, $return, $order);
    $arr['order']               = $order;
    
    
    
    # 2023-12-18
    # Natura
    # Validar se na encomenda foi usado algum código de uma campanha imediata que esteja limitada a reembolso por vale
    $vouchers = cms_fetch_assoc(cms_query("SELECT group_concat(distinct(desconto_vaucher_id)) as ids FROM `ec_encomendas_lines` WHERE order_id='".$order['order_number']."' AND desconto_vaucher_id>0"));
    if(trim($vouchers['ids'])!=''){
        $campanhas = cms_fetch_assoc(cms_query("SELECT group_concat(distinct(campanha_id)) as ids FROM `ec_vauchers` WHERE id in (".$vouchers['ids'].") and campanha_id>0 "));
        if(trim($campanhas['ids'])!=''){
            $campanhas_rem = cms_fetch_assoc(cms_query("SELECT MAX(`reembolso_vale`) as rem FROM `ec_campanhas` WHERE id in (".$campanhas['ids'].") AND ofer_tipo!=10"));
            if($campanhas_rem['rem']==1){
                $arr['hide_payments'] = 1;           
            }
        }
    }
    
    
    
    # 2024-11-05
    # Salsa
    # Validar se na encomenda foi atribuido algum cupão não imediato que já esteja usado de uma campanha que esteja limitada a reembolso por vale    
    $vouchers = cms_fetch_assoc(cms_query("SELECT group_concat(distinct(voucher_id_generated)) as ids, id_campanha FROM `ec_campanhas_tmp_ofertas` WHERE encomenda_origem_id='".$order['order_number']."' AND id_campanha>0 AND status=2 "));
    if($vouchers['id_campanha']>0){
        $campanhas_rem = cms_fetch_assoc(cms_query("SELECT MAX(`reembolso_vale`) as rem FROM `ec_campanhas` WHERE id='".$vouchers['id_campanha']."' AND ofer_tipo=10 LIMIT 0,1"));                  
        if($campanhas_rem['rem']==1){
        
            $voucher_used_res = cms_query("SELECT `id` FROM `ec_vouchers_log` WHERE `voucher_id` IN (".$vouchers['ids'].") ");                                                
            if( cms_num_rows($voucher_used_res) > 0 ){
                $arr['hide_payments'] = 1;           
            }
        }  
    }
    
       
    
    
    $MARKET = $MARKET_temp;
    
    # Exibição do tipo de pagamento para solicitação do IBAN
    if( in_array((int)$order['transactions'][0]['gateway_id'], [29, 82, 108]) ){
        $arr['payment_type'] = array();
        $arr['payment_type'][0] = "Caixa de Multibanco ( ATM )";
        $arr['payment_type'][1] = "HomeBanking / Mobile Banking";
        $arr['payment_type'][2] = "Ponto de pagamento: CTT / Payshop / Paga Aqui / MBSpot / etc...";
    }
    
    # Exibição do código postal da loja/ ponto pickme
    $arr['store_zip'] = ( trim($order['shipping_address']['zip']) != '' ) ? $order['shipping_address']['zip'] : $arr['customer']['default_address']['zip'] ;
    
     
    # 2025-03-13
    # Amazon o cp está codificado 
    if($ENC_ORIG['pm_marketplace_id']==8){ $arr['store_zip'] = ''; }
    
        
    if(isset($_SESSION['dev_isa_cp']) && $_SESSION['dev_isa_cp']!='')  $arr['store_zip'] = $_SESSION['dev_isa_cp'];
    
    
        
       
    
    # Extended reflection period
    $intermediate_deadline = cms_fetch_assoc( cms_query("SELECT property_value FROM ec_encomendas_props WHERE order_id = '".$order_id."' AND property = 'INT_RETURN_DATE' LIMIT 1") );
    if( $_SESSION['dev_oms']!=2 && trim($intermediate_deadline['property_value']) != '' && strtotime($intermediate_deadline['property_value']) < strtotime(date("Y-m-d")) && (int)$MARKET['return_days_extended_refunds'] == 0 ){
        $arr['hide_payments'] = 1;       
    }
    # Extended reflection period
    
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

    $arr['iban'] = $iban;      

    return serialize($arr);

}


function getRequestCollection($pais_id=0, $metodo_envio=0, $return=0, $order=null){
    global $MOEDA;
    global $MARKET;
    global $LG;
    global $userID;
    global $CONFIG_OPTIONS;
    global $COUNTRY;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    $shipping_enc = array();

    if((int)$metodo_envio>0){
        
        $tabela_shipping = "ec_shipping";
        if((int)$return>0) $tabela_shipping = "ec_shipping_returns";
        
        $shipping_enc = call_api_func('get_line_table', $tabela_shipping, "id='".$metodo_envio."'");
    }
    
    $metodos_devolucao = $MARKET["metodos_devolucao"];
    if(trim($shipping_enc["limitar_metodo_devolucao"])!=""){
       $arr_metodos_devolucao = explode(",", $MARKET["metodos_devolucao"]);
       $arr_limitar_metodos_devolucao = explode(",", $shipping_enc["limitar_metodo_devolucao"]);
       $result = array_intersect($arr_metodos_devolucao, $arr_limitar_metodos_devolucao); 
       if(count($result)>0) $metodos_devolucao = implode(",",$result);
    }

    # Campanhas de devolução
    # Salsa
    $arr_campanhas_devolucao = array();
    $sql_camp_devolucao = "SELECT * 
                            FROM ec_campanhas_devolucao 
                            WHERE moeda='".$MOEDA["id"]."'
                              AND deleted='0'
                              AND (ofer_perc>0 OR ofer_valor>0)
                              AND NOW() between CONCAT(data_inicio, ' ',hora_inicio, ':00:00') and CONCAT(data_fim, ' ',hora_fim, ':59:59')
                              AND (crit_mercados='' OR concat(',',`crit_mercados`,',') LIKE '%,".$MARKET['id'].",%' )
                              AND (crit_aplicar_cod=0 OR ( crit_aplicar_cod=1 AND (crit_paises='' OR concat(',',`crit_paises`,',') LIKE '%,".$COUNTRY['id'].",%' )))
                            ORDER BY id DESC";
                    
    $res_camp_devolucao = cms_query($sql_camp_devolucao);
    while($row_camp_devolucao = cms_fetch_assoc($res_camp_devolucao)){
        
        if($row_camp_devolucao['metodos_devolucao'] != ""){
            $metodos   = explode(",", $row_camp_devolucao['metodos_devolucao']);
            $metodos_mercado  = explode(",", $metodos_devolucao);
 
            $exclui = 0;
 
            $intersect = array_intersect($metodos, $metodos_mercado);
            
            if(count($intersect)<1) continue;
        }
        
        if ( $row_camp_devolucao['crit_tipo_cliente'] != ""){
 
            $tipo_validos   = explode(",", $row_camp_devolucao['crit_tipo_cliente']);
            $tipos_cliente  = explode(",", $_SESSION['EC_USER']['tipo']);
 
            $exclui = 0;
 
            $intersect = array_intersect($tipo_validos, $tipos_cliente);
            
            if(count($intersect)<1) continue;
 
        }

        if((int)$row_camp_devolucao['tipo_utilizador'] > 0 && $row_camp_devolucao['tipo_utilizador']!=$_SESSION['EC_USER']['tipo_utilizador']){
            continue;
        }

        $arr_campanhas_devolucao[] = $row_camp_devolucao;

    }
    
    
    $more_dev = '';
    if(isset($_GET["cdln"])) $more_dev = " AND type!=1 ";
    
    $arr_shipping = array();        
    $sql = "SELECT * FROM ec_shipping_returns WHERE id in(".$metodos_devolucao.") $more_dev ORDER BY ordem, id ASC ";
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        
        $shipping_zip_restrictions = [];        
        if( !empty($order) && $row['express'] == 1 && !isset($_SESSION['dev_isa']) ){
            if( !check_collect_zip_code_restrictions($row['id'], $order['shipping_address']['zip']) ){
                continue;
            }else{
                $shipping_zip_restrictions = get_collect_zip_code_restrictions($row['id']);
            }
        }
        
        $arr_moradas = array();
        $return_options = array();
        if($row["type"]==1){
        
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $sql_morada = "select * from ec_moradas where id_user='".$userOriginalID."' $more_sql_country order by id desc";
            $res_morada = cms_query($sql_morada);
            if((int)cms_num_rows($res_morada)==0){
                $sql_morada = "select * from ec_moradas where id_user='".$userOriginalID."' order by id desc";
                $res_morada = cms_query($sql_morada);
            }
            while($row_morada = cms_fetch_assoc($res_morada)){
                $sel = "";
                $i++;
                
                if( $row['express'] == 1 && !empty($shipping_zip_restrictions) ){
                    
                    $is_valid = false;
                    $morada_zip_parsed = preg_replace("/[^0-9]/", "", $row_morada['cp']);
                    
                    foreach($shipping_zip_restrictions as $restriction ){
                        if( $restriction['zip_ini'] <= $morada_zip_parsed && $restriction['zip_fim'] >= $morada_zip_parsed ){
                            $is_valid = true;
                            break;
                        }
                    }
                    
                    if( !$is_valid ){
                        continue;
                    }
                }
                
                if($i==1) $sel = "selected";
                
                $sql_pais = "select * from ec_paises where id='".$row_morada["pais"]."' LIMIT 0,1";
                $res_pais = cms_query($sql_pais);
                $row_pais = cms_fetch_assoc($res_pais);
                
                $nome = (trim($row_pais['nome'.$LG])!='') ? $row_pais['nome'.$LG] : $row_pais['country'];
                
                $arr_moradas[] = array(
                    "id"            => $row_morada["id"],
                    "name_address"  => $row_morada["descpt"],
                    "name"          => $row_morada["nome"],
                    "address"       => $row_morada["morada1"],
                    "address2"      => $row_morada["morada2"],
                    "zip_code"      => $row_morada["cp"],
                    "city"          => $row_morada["cidade"],
                    "country_id"    => $row_morada["pais"],
                    "country"       => $nome,
                    "distrito"      => $row_morada["distrito"],
                    "selected"      => $sel,
                    "country_code"  => $row_pais["country_code"]
                );
            }
            
            if( empty($arr_moradas) ){
                continue;
            }
            
            $sql_option = cms_query("SELECT * FROM ec_pickup_settings WHERE id_shipping='".$row["id"]."' order by id desc");
            while($row_option = cms_fetch_assoc($sql_option)){
                $return_options[] = array(
                    "id" => $row_option['id'],
                    "title" => $row_option['nome'.$LG]
                );
            }
        }
        
        $pickme_type = 0;
        if($row["type"]==2){

            $pickme_type = $row["tipo_envio"];

            if ( $row["tipo_envio"] == 150 ) {
                $pickme_type = 101;
            } elseif ( in_array($row["tipo_envio"], [105, 106, 111, 112, 113]) ) {
                $pickme_type  = 101;
            }
            
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $more_sql_zip_restriction = '';
            $join_sql_zip_restriction = '';
            if( $row['express'] == 1 && !empty($shipping_zip_restrictions) ){
                $more_sql_zip_restriction = ' AND codpostal_inicio <= REPLACE(cp, "-", "") AND codpostal_fim >= REPLACE(cp, "-", "")';
                $join_sql_zip_restriction = 'INNER JOIN ec_shipping_returns_express ON `id_shipping`='.$row['id'];
            }
            
            # 2022-04-04
            # Como nestes casos a consulta é efetuada na hora, não convém validar a tabela
            if( !in_array($pickme_type, [104, 107, 110, 116]) ){
            
                $sql_pickme = "SELECT ec_pickme.id FROM ec_pickme ".$join_sql_zip_restriction." WHERE tipo='".$pickme_type."' ".$more_sql_country." ".$more_sql_zip_restriction." LIMIT 0,1";
                $res_pickme = cms_query($sql_pickme);
                $row_pickme = cms_fetch_assoc($res_pickme);
                if((int)$row_pickme==0) continue;
            
            }
            
        }else if($row["type"]==3 && $row['express'] == 1 && !empty($shipping_zip_restrictions)){
            
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $more = " AND activo_ship IN (1,3) ";
            
            # 2025-07-30
            # Comentei porque wm isto apagaha as lojas toda e a ONE
            #if($_SESSION['dev_isa']>0 || $_SESSION['dev_oms']==2) $more = "";
            
            
            
            $sql_lojas = "SELECT ec_lojas.id FROM `ec_lojas` INNER JOIN ec_shipping_returns_express ON `id_shipping`=".$row['id']." WHERE codpostal_inicio <= REPLACE(cp, '-', '') AND codpostal_fim >= REPLACE(cp, '-', '') ".$more_sql_country.$more." LIMIT 0,1";
            $res_lojas = cms_query($sql_lojas);
            $row_lojas = cms_fetch_assoc($res_lojas);
            if((int)$row_lojas==0) continue;
            
        }
        
        $old_price = 0;
        if(count($arr_campanhas_devolucao) > 0){
            $old_price = $row['valor'];
            foreach ($arr_campanhas_devolucao as $k => $v) {
                if($v["metodos_devolucao"] == "" || in_array($row["id"], explode(",", $v["metodos_devolucao"]))){
                    if($v["ofer_valor"] > 0){
                        $row['valor'] = $row['valor']-$v["ofer_valor"];
                    }elseif($v['ofer_perc'] > 0){                        
                        $row['valor'] = $row['valor']-($row['valor']*$v["ofer_perc"])/100;
                    }
                    if($row['valor'] < 0) $row['valor'] = 0;
                    break;
                }
            }
            if($old_price == $row['valor']) $old_price = 0;
        }
        
        $arr_shipping[] = array(
            "id"                    => $row["type"],
            "id_shipping_return"    => $row["id"],
            "name"                  => $row["nome".$LG],
            "short_content"         => nl2br($row["desc".$LG]),
            "title"                 => $row["title".$LG],
            "content"               => str_replace('{ORDER_ID}', $order['order_number'], $row["bloco".$LG]),
            "return_option"         => $return_options,
            "shipping_address"      => $MARKET["morada_devolucao".$LG],
            "pickme_type"           => $pickme_type,
            "addresses"             => $arr_moradas,
            "country_id"            => $pais_id,
            "not_finish"            => (int)$row['sem_finalizar'],
            "price"                 => call_api_func('OBJ_money', $row['valor'], $MOEDA['id']),
            "previous_price"        => call_api_func('OBJ_money', $old_price, $MOEDA['id']),
            "no_charge_per_val"     => (int)$row['nao_cobrar_custo_recolha_vale'],
            "no_charge_exchanges"   => (int)$row['nao_cobrar_custo_recolha_trocas']
        );
    }

    return $arr_shipping;
}

function getRequestDelivery($pais_id=0, $metodo_envio=0, $return=0, $order=null){
    global $MOEDA;
    global $MARKET;
    global $LG;
    global $userID;
    global $CONFIG_OPTIONS; 
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    $shipping_enc = array();
    if((int)$metodo_envio>0){
        
        $tabela_shipping = "ec_shipping";
        if((int)$return>0) $tabela_shipping = "ec_shipping_returns";
        
        $shipping_enc = call_api_func('get_line_table', $tabela_shipping, "id='".$metodo_envio."'");
    }
    
    $metodos_troca = $MARKET["metodos_troca"];
    if(trim($shipping_enc["limitar_metodo_troca"])!=""){
       $arr_metodos_troca = explode(",", $MARKET["metodos_troca"]);
       $arr_limitar_metodos_troca = explode(",", $shipping_enc["limitar_metodo_troca"]);
       $result = array_intersect($arr_metodos_troca, $arr_limitar_metodos_troca); 
       if(count($result)>0) $metodos_troca = implode(",",$result);
    }
    
    $arr_shipping = array();
    
    $sql = "SELECT * FROM ec_shipping_returns WHERE id in(".$metodos_troca.") ORDER BY ordem, id ASC";
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        
        $shipping_zip_restrictions = [];        
        if( !empty($order) && $row['express'] == 1 && !isset($_SESSION['dev_isa'])){
            if( !check_collect_zip_code_restrictions($row['id'], $order['shipping_address']['zip']) ){
                continue;
            }else{
                $shipping_zip_restrictions = get_collect_zip_code_restrictions($row['id']);
            }
        }
        
        $arr_moradas = array();
        if($row["type"]==1){
            
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $sql_morada = "select * from ec_moradas where id_user='".$userOriginalID."' $more_sql_country order by id desc";
            $res_morada = cms_query($sql_morada);
            if((int)cms_num_rows($res_morada)==0){
                $sql_morada = "select * from ec_moradas where id_user='".$userOriginalID."' order by id desc";
                $res_morada = cms_query($sql_morada);
            }
            while($row_morada = cms_fetch_assoc($res_morada)){

                $sel = "";
                $i++;
                
                if( $row['express'] == 1 && !empty($shipping_zip_restrictions) ){
                    
                    $is_valid = false;
                    $morada_zip_parsed = preg_replace("/[^0-9]/", "", $row_morada['cp']);
                    
                    foreach($shipping_zip_restrictions as $restriction ){
                        if( $restriction['zip_ini'] <= $morada_zip_parsed && $restriction['zip_fim'] >= $morada_zip_parsed ){
                            $is_valid = true;
                            break;
                        }
                    }
                    
                    if( !$is_valid ){
                        continue;
                    }
                }
                
                if($i==1) $sel = "selected";
                
                $sql_pais = "select id, country from ec_paises where id='".$row_morada["pais"]."' LIMIT 0,1";
                $res_pais = cms_query($sql_pais);
                $row_pais = cms_fetch_assoc($res_pais);
                
                $nome = (trim($row_pais['nome'.$LG])!='') ? $row_pais['nome'.$LG] : $row_pais['country'];

                $arr_moradas[] = array(
                    "id"            => $row_morada["id"],
                    "name_address"  => $row_morada["descpt"],
                    "name"          => $row_morada["nome"],
                    "address"       => $row_morada["morada1"],
                    "address2"      => $row_morada["morada2"],
                    "zip_code"      => $row_morada["cp"],
                    "city"          => $row_morada["cidade"],
                    "country_id"    => $row_morada["pais"],
                    "country"       => $nome,
                    "distrito"      => $row_morada["distrito"],
                    "selected"      => $sel,
                    "country_code"  => $row_pais["country_code"]
                );
            }
            
            if( empty($arr_moradas) ){
                continue;
            }
            
        }
        
        $pickme_type = 0;
        if($row["type"]==2){

            $pickme_type = $row["tipo_envio"];

            if ( $row["tipo_envio"] == 150 ) {
                $pickme_type = 101;
            } elseif ( in_array($row["tipo_envio"], [105, 106, 111, 112, 113]) ) {
                $pickme_type  = 101;
            }
            
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $more_sql_zip_restriction = '';
            $join_sql_zip_restriction = '';
            if( $row['express'] == 1 && !empty($shipping_zip_restrictions) ){
                $more_sql_zip_restriction = ' AND codpostal_inicio <= REPLACE(cp, "-", "") AND codpostal_fim >= REPLACE(cp, "-", "")';
                $join_sql_zip_restriction = 'INNER JOIN ec_shipping_returns_express ON `id_shipping`='.$row['id'];
            }
            
            $sql_pickme = "SELECT ec_pickme.id FROM ec_pickme ".$join_sql_zip_restriction." WHERE tipo='".$pickme_type."' ".$more_sql_country." ".$more_sql_zip_restriction." LIMIT 0,1";
            $res_pickme = cms_query($sql_pickme);
            $row_pickme = cms_fetch_assoc($res_pickme);
            if((int)$row_pickme==0) continue;
        }else if($row["type"]==3 && $row['express'] == 1 && !empty($shipping_zip_restrictions)){
            
            $more_sql_country = "";
            if($pais_id>0) $more_sql_country = " AND pais='".$pais_id."'";
            
            $sql_lojas = "SELECT ec_lojas.id FROM `ec_lojas` INNER JOIN ec_shipping_returns_express ON `id_shipping`=".$row['id']." WHERE activo_ship IN (1,2) ".$more_sql_country." AND codpostal_inicio <= REPLACE(cp, '-', '') AND codpostal_fim >= REPLACE(cp, '-', '') LIMIT 0,1";
            $res_lojas = cms_query($sql_lojas);
            $row_lojas = cms_fetch_assoc($res_lojas);
            if((int)$row_lojas==0) continue;
            
        }
             
        $arr_shipping[] = array(
            "id"                    => $row["type"],
            "id_shipping_delivery"  => $row["id"],
            "name"                  => $row["nome".$LG],
            "short_content"         => $row["desc".$LG],
            "title"                 => $row["title".$LG],
            "content"               => str_replace('{ORDER_ID}', $order['order_number'], $row["bloco".$LG]),
            "return_option"         => $return_options,
            "shipping_address"      => $MARKET["morada_devolucao".$LG],
            "pickme_type"           => $pickme_type,
            "addresses"             => $arr_moradas,
            "country_id"        => $pais_id
    
        );
    }
    return $arr_shipping;
}
?>
