<?

function _getAccountOrderDetail($page_id=null)
{

    global $userID, $eComm, $LG, $ESTADOS_ENCOMENDAS, $CONFIG_TEMPLATES_PARAMS;
    global $CONFIG_OPTIONS, $B2B, $MARKET, $ENCS_OMNI;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
        
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $temp       = array();
    
    if((int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] == 1 && !is_numeric($_GET["order"])){
        
        require_once _ROOT."/api/api_external_functions.php";
        
        $_GET["code_order"] = base64_decode($_GET["order"]);
        
        $encomenda = getBDiOrders($userOriginalID, 0, $_GET["code_order"]);
        
    }elseif((int)$ENCS_OMNI == 1 && !is_numeric($_GET["order"])){
        
        require_once _ROOT."/api/api_external_functions.php";
        
        $_GET["code_order"] = base64_decode($_GET["order"]);
        
        $encomenda = getOMNIOrders($userOriginalID, 0, $_GET["code_order"]);
        
    }else{                                               
        $encomenda  = $eComm->getOrder($userOriginalID, $_GET["order"]);
    }
    

        
    # Pagamentos parciais
    if((int)$encomenda['percentagem_parcial'] > 0 && $encomenda['valor_anterior'] > 0 && $encomenda['tracking_status'] <= 1){
        $previous_value_temp = $encomenda['valor'];
        $encomenda['valor'] = $encomenda['valor_anterior'];
        $encomenda['valor_anterior'] = $previous_value_temp;   
    }
    
    $temp = call_api_func('orderOBJ', $encomenda, 1, 1);
    
 
    if((int)$ENCS_OMNI == 1 && !is_numeric($_GET["order"])) {
        $temp['fulfillment'][0]['receipt'] = (trim($encomenda['tracking_factura']) != '') ? $encomenda['tracking_factura'] : "";
        $CONFIG_TEMPLATES_PARAMS['detail_allow_review'] = 0;
    }

    
    # Reviews
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){
        foreach($temp['lines'] as $k => $v){
            if($v['data_line']['tipo_linha'] != 1 && $v['data_line']['tipo_linha'] != 12){
                $temp['lines'][$k]['product']['review_product'] = call_api_func('get_reviews_product', $v['product']['sku_family'], $v['product']['selected_variant']['color']['color_id']);
                $temp['lines'][$k]['review_made']               = $temp['lines'][$k]['product']['review_product']['review_made'];
            }
        }
        
        if($temp['allstates_status'] >= 80 && $temp['allstates_status'] != 100){
            if($v['data_line']['tipo_linha'] != 1 && $v['data_line']['tipo_linha'] != 12){
                $temp['show_review_button'] = 1;
            }
        }

    }
       
    
    if(trim($ESTADOS_ENCOMENDAS)==''){
        $ESTADOS_ENCOMENDAS = "1,10,40,42,45,50,70,80,103,100";
    }

    
    if(((int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] == 1 || (int)$ENCS_OMNI==1) && !is_numeric($_GET["order"])){

        $logs[] = array("data" => $encomenda['datahora'], "desc" => estr(498));

    } else {

        $group_by = "";
        if($encomenda['tracking_tipopaga'] != 77) $group_by = "GROUP BY ec_encomendas_log.estado_novo";
        
       
        $more = "";
        if($encomenda['tracking_status']!=100) $more = " AND estado_novo!=100 ";
        
        $sql = cms_query("select ec_encomendas_log.id,
                            ec_encomendas_log.datahora,
                            ec_encomendas_log.autor,
                            ec_tracking_status.desc$LG,
                            ec_encomendas_log.obs,
                            ec_encomendas_log.estado_novo
                            from ec_encomendas_log 
                            LEFT JOIN ec_tracking_status ON ( ec_encomendas_log.estado_novo = ec_tracking_status.id ) 
                            WHERE encomenda='".$_GET["order"]."' AND estado_novo NOT IN (15,18,98,45,48) $more 
                            $group_by
                            ORDER BY id");

        while($v = cms_fetch_assoc($sql)){
            $logs[] = array(
                "id"    => $v["id"],
                "data"  => $v["datahora"],
                "autor" => $v["autor"],
                "desc"  => ($v["obs"] != '' && (($encomenda['tracking_tipopaga'] == 77 && in_array($v["estado_novo"], array(1,100))) || ($v["autor"]=='Sistema:cliente' )) ) ? $v["obs"] : $v["desc".$LG]
            );
        }

    }
    
    $temp["logs"] = $logs;
    $orders[] = $temp;
    
    # Coluna disponibilidade
    if( (int)$B2B>0 ){
        $property_s = "SELECT property_value FROM ec_encomendas_props WHERE order_id='".$encomenda['id']."' AND property='REGRAVSTOCK' LIMIT 0,1";
        $property_q = cms_query($property_s);
        $property_r = cms_fetch_assoc($property_q);
    }
    
    $show_availability = ( (int)$property_r['property_value'] == 2 || ( (int)$B2B>0 && (int)$property_r['property_value'] <= 0 && (int)$MARKET['depositos_condicionados_ativo'] == 1 ) ) ? 1 : 0;
    # Coluna disponibilidade
    $return_gift = 0;

    #07/04/2020 Retirar possiblidade de devolver quando nao tem unidades de portes
    foreach($orders as $k => $v){
        $orders[$k]['is_egift'] = 0;
        $unidade_portes = 0;
        foreach($v['lines'] as $kk => $vv){
            
            if($vv["egift"] == 1 && count($v['lines']) == 1){
                $orders[$k]['is_egift'] = 1;
            }
            
            $comp = explode(' - ', $vv['composition']);
            $comp = array_filter($comp);
            
            $orders[$k]['lines'][$kk]['composition'] = implode(" - ", $comp);

            $unidade_portes += $vv['data_line']['unidade_portes'];
            
            # Coluna disponibilidade
            if( !empty($vv['data_line']['disp_condicionada']) && $show_availability == 1 ){
                
                $disp_condicionada_temp = json_decode($vv['data_line']['disp_condicionada'], true);
                
                $stock_condicionado     = $disp_condicionada_temp['inventory_conditioned_quantity'];
                $data_condicionada      = $disp_condicionada_temp['inventory_conditioned_date'];
                $stock_disponivel       = $disp_condicionada_temp['stock'];
                $stock_condicionado_arr = [];
                
                $_qtds = $vv['quantity'];
                
                $stock_pendente = 0;
                if( (int)$_qtds < (int)$stock_disponivel ){
                    $stock_disponivel = $_qtds;
                }elseif( (int)$_qtds > (int)$stock_disponivel + (int)$stock_condicionado ){
                    $stock_pendente = (int)$_qtds - (int)$stock_disponivel - (int)$stock_condicionado;
                }else{
                    $stock_condicionado = (int)$_qtds - (int)$stock_disponivel;
                }
                
                if($stock_disponivel < $_qtds && isset($disp_condicionada_temp['inventory_conditioned_arr']) && count($disp_condicionada_temp['inventory_conditioned_arr'])){
                    $stock_condicionado = 0;
                    $data_condicionada  = '0000-00-00';
                    $total_stock_aux    = $stock_disponivel;
                    $max_date           = "1990-01-01";
        
                    foreach($disp_condicionada_temp['inventory_conditioned_arr'] as $stock_cond_row){
        
                        if( (int)$disp_condicionada_temp['replacement_time'] > 0 && strtotime($stock_cond_row['data_condicionada']) > strtotime($max_date) ){
                            $max_date = $stock_cond_row['data_condicionada'];
                        }
                        
                        if( $total_stock_aux + $stock_cond_row['stock_condicionado'] <= $_qtds ){
                            $stock_condicionado_arr[] = $stock_cond_row;
                            $total_stock_aux += (int)$stock_cond_row['stock_condicionado'];
                        }else{
                            $stock_cond_row['stock_condicionado'] = (int)$_qtds - (int)$total_stock_aux;
                            $stock_condicionado_arr[] = $stock_cond_row;
                            $total_stock_aux += (int)$stock_cond_row['stock_condicionado'];
                            break;
                        }
        
                    }
        
                    $stock_pendente = $_qtds - $total_stock_aux;
        
                }
                
                if( (int)$disp_condicionada_temp['replacement_time'] > 0 && $stock_pendente > 0 ){

                    $replacement_time = strtotime("+" . $disp_condicionada_temp['replacement_time'] . " day");
                    if( $replacement_time > strtotime($max_date) ){
                        $stock_condicionado_arr[] = array( 
                            "stock_condicionado" => $stock_pendente,
                            "data_condicionada" => date("Y-m-d", $replacement_time)
                        );
                    }
        
                    $stock_pendente = 0;
        
                }
                
                $orders[$k]['lines'][$kk]['data_line']['inventory_conditioned_arr']      = $stock_condicionado_arr;
                $orders[$k]['lines'][$kk]['data_line']['inventory_conditioned_quantity'] = $stock_condicionado;
                $orders[$k]['lines'][$kk]['data_line']['inventory_conditioned_date']     = $data_condicionada;
                $orders[$k]['lines'][$kk]['data_line']['inventory_quantity']             = $stock_disponivel;       
                $orders[$k]['lines'][$kk]['data_line']['inventory_pending']              = $stock_pendente;
                
            }
            # Coluna disponibilidade
            
            #Talão devolução para produtos de oferta
            $orders[$k]['lines'][$kk]['return_gift'] = "";

            if($vv["service"] == 0 && $vv["egift"] == 0 && (int)$MARKET["return_gift_active"] == 1 && ($vv["data_line"]["status"] == 80 || ($vv["data_line"]["status"] == 103 && $vv["data_line"]["return_id"] == 0)) && $vv["data_line"]["tipo_linha"] != 5){
                $orders[$k]['lines'][$kk]['return_gift'] = "api/api.php/setAccountReturnGift/".base64_encode($orders[$k]["order_number"])."/".base64_encode($vv["id"])."/".$encomenda['entrega_pais_lg'];
                $return_gift++;
            }

            #Observações as linhas B2B
            if($B2B > 0 && (int)$CONFIG_OPTIONS['CHECKOUT_OBSERVACOES'] == 1){
                $sql_comment = "SELECT property_value FROM `ec_encomendas_lines_props` WHERE `order_line_id`='".$vv['id']."' AND `property`='OBS'";
                $res_comment = cms_query($sql_comment);
                $row_comment = cms_fetch_assoc($res_comment);
            }

            $orders[$k]['lines'][$kk]['comment'] = $row_comment["property_value"];


            if((int)$ENCS_OMNI == 1 && !is_numeric($_GET["order"])) {
                $orders[$k]['lines'][$kk]['variant']['size'] = $vv['data_line']['tamanho'];
                $orders[$k]['lines'][$kk]['variant']['color']['short_name'] = $vv['data_line']['cor_name'];
            }

        }
        
        $orders[$k]['shipping_unit'] = $unidade_portes;
        $orders[$k]['can_complain'] = (int)$eComm->checkIfCanComplain($v['order_number']);
        
    }

    $arr = array();
    $arr['page']                  = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']         = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['orders']                = $orders;
    
    # 2024-02-05
    $arr['in_dispute']= 0;
    if($encomenda['disputa_inicio']!='0000-00-00 00:00:00' && !is_null($encomenda['disputa_inicio'])){
        $arr['in_dispute']= 1;
    }       
    
    $arr['show_availability']     = $show_availability;
    
    $arr['customer']              = call_api_func('getCustomer');
    $arr['shop']                  = call_api_func('OBJ_shop_mini');
    $arr['account_expressions']   = call_api_func('getAccountExpressions');
    
    $return_gift_active = (int)$MARKET["return_gift_active"];
    if($return_gift==0) $return_gift_active = 0;
    
    $arr['return_gift_active']    = $return_gift_active;
    
    $arr['exp_pvr']               = estr(472);
    $arr['exp_pvr_desc']          = estr(473);
    
    $arr['exp106']                = estr(106);
    $arr['exp112']                = estr(112);
    
    # Coluna disponibilidade
    $arr['exp656'] = estr2(656);
    $arr['exp657'] = estr2(657);
    $arr['exp315'] = estr2(315);
    # Coluna disponibilidade

    # Modalidade de pagamento - B2B
    if( (int)$CONFIG_OPTIONS['PAYMENT_MODALITIES_MODULE_ACTIVE'] == 1 ){
        $client = call_api_func("get_line_table", "_tusers", "id='".$userOriginalID."'");
        if( (int)$client['modalidade_pagamento'] > 0 ){
            $payment_modality = cms_fetch_assoc( cms_query("SELECT `id`, `nome".$LG."` AS nome, `bloco".$LG."` AS bloco, limitar_metodos_pagamentos
                                                            FROM `modalidades_pagamento` 
                                                            WHERE `id`='".$client['modalidade_pagamento']."' AND `nome".$LG."`!='' 
                                                            LIMIT 1") );
            if( (int)$payment_modality['id'] > 0 && (trim($payment_modality['limitar_metodos_pagamentos']) == '' || (trim($payment_modality['limitar_metodos_pagamentos']) !='' && in_array($encomenda["tracking_tipopaga"], explode(',', $payment_modality['limitar_metodos_pagamentos'] )) ))){
                $arr['payment_modality'] = array( "name" => $payment_modality['nome'], "desc" => $payment_modality['bloco'] );
            }
        }   
    }
    # Modalidade de pagamento

    if(is_callable('custom_controller_account_order_detail')) {
        call_user_func_array('custom_controller_account_order_detail', array(&$arr));
    }
    
    
    $MYACCOUNT_SEM_TROCAS = 0;
    if($MARKET["metodos_troca"]=="") $MYACCOUNT_SEM_TROCAS = 1;
    $MYACCOUNT_SEM_DEVOLUCOES = 0;
    if($MARKET["metodos_devolucao"]==""){
        $MYACCOUNT_SEM_DEVOLUCOES = 1;
        $MYACCOUNT_SEM_TROCAS = 1;
    }
    
    $arr['MYACCOUNT_SEM_TROCAS'] = $MYACCOUNT_SEM_TROCAS;
    $arr['MYACCOUNT_SEM_DEVOLUCOES'] = $MYACCOUNT_SEM_DEVOLUCOES;
             
    return serialize($arr);

}
?>
