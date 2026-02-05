<?

function _getAccountComplainOrder($page_id=null, $order_id=0)
{
    
    global $userID;
    global $LG;
    global $eComm;
    global $MYACCOUNT_SEM_VALES;
    global $MARKET;
    global $CONFIG_OPTIONS;
    
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
        $v = $eComm->getOrder($userOriginalID, $order_id); 
        $pais_id = $v["b2c_pais"];
        
        $metodo_envio = $v["transportadora_id"];

        if(trim($v['return_ref'])!=''){
            #$return = 1;
            
            $sql_dev = "SELECT e.transportadora_id FROM ec_devolucoes d INNER JOIN ec_encomendas e ON e.id=d.order_id AND e.return_ref='' WHERE d.return_ref='".$v['return_ref']."' ";
            $res_dev = cms_query($sql_dev);
            $row_dev = cms_fetch_assoc($res_dev);

            if(trim($row_dev["transportadora_id"])!="") $metodo_envio = $row_dev["transportadora_id"];  
        }
        
        $complaint_products_res = cms_query( "SELECT `order_line_id`, `complaint_id` FROM `ec_reclamacoes_lines` WHERE `order_id`=".$order_id );
        while( $complaint_row = cms_fetch_assoc( $complaint_products_res ) ){
            $complained_products[ $complaint_row['order_line_id'] ] = $complaint_row['complaint_id'];
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
        foreach($v[lines] as $kk => $vv){

            if( $vv['data_line']['unidade_portes'] == 0 ){
                unset($orders[$k]['lines'][$kk]);
            }
            
            if( isset($complained_products[ $vv['id'] ]) ){
                $orders[$k]['lines'][$kk]['data_line']['complaint_id'] = $complained_products[ $vv['id'] ];
            }else{
                $orders[$k]['lines'][$kk]['data_line']['complaint_id'] = 0;
            }
            
        }
    }
    
    $MARKET = $eComm->marketInfo($pais_id);
    
    $return_reasons = array();

    $sql = cms_query("SELECT * FROM b2c_reclamacoes_motivo WHERE nome$LG!='' ORDER BY id");
    while($v = cms_fetch_assoc($sql)){
    
         if((int)$_GET['oms']==0 && (int)$v['only_oms']>0) continue;
          
         $return_reasons[] = array(
            "id" => $v['id'],
            "title"=> $v['nome'.$LG]
         );
    }



    $arr = array();
    $arr['page']                = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']       = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['orders']              = $orders;
    $arr['customer']            = call_api_func('getCustomer');
    $arr['shop']                = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    $arr['complaint_reasons']   = $return_reasons;
    $arr['MYACCOUNT_SEM_VALES'] = $MYACCOUNT_SEM_VALES;    
    
    # 2021-02-26
    # Colocar o id do beneficiario para das devolucoes de oferta colocar as moradas deste cliente e não do original
    if((int)$_SESSION['OMS_BENEFICIARIO']>0){
        $userID = $_SESSION['OMS_BENEFICIARIO'];    
    }
    
    $arr['requestCollection'] = [];
    if( $MARKET['reclamacao_recolha_ativa'] == 1 ){
        $arr['requestCollection'] = getRequestCollection($pais_id, $metodo_envio, $return, $order);
    }
    
    if( empty($arr['requestCollection']) ){
        $arr['COMPLAINT_WITH_PICKUP'] = 0;
    }else{
        $arr['COMPLAINT_WITH_PICKUP'] = 1;
        $arr['COMPLAINT_WITH_REFUND'] = $MARKET['reclamacao_estorno_ativo'];
    }
    
    $arr['order'] = $order;
    
    $MARKET = $MARKET_temp;
    
    # Exibição do tipo de pagamento para solicitação do IBAN
    if( (int)$order['transactions'][0]['gateway_id'] == 29 ){
        $arr['payment_type'] = array();
        $arr['payment_type'][0] = "Caixa de Multibanco ( ATM )";
        $arr['payment_type'][1] = "HomeBanking / Mobile Banking";
        $arr['payment_type'][2] = "Ponto de pagamento: CTT / Payshop / Paga Aqui / MBSpot / etc...";
    }
    
    return serialize($arr);

}


function getRequestCollection($pais_id=0, $metodo_envio=0, $return=0, $order=null){
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
    
    $metodos_recolha = $MARKET["reclamacao_recolha_metodos"];
    
    $arr_shipping = array();        
    $sql = "SELECT * FROM ec_shipping_returns WHERE id in(".$metodos_recolha.")";
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        
        $shipping_zip_restrictions = [];        
        if( !empty($order) && $row['express'] == 1 ){
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
                    "selected"      => $sel
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
            
            $sql_lojas = "SELECT ec_lojas.id FROM `ec_lojas` INNER JOIN ec_shipping_returns_express ON `id_shipping`=".$row['id']." WHERE activo_ship=1 ".$more_sql_country." AND codpostal_inicio <= REPLACE(cp, '-', '') AND codpostal_fim >= REPLACE(cp, '-', '') LIMIT 0,1";
            $res_lojas = cms_query($sql_lojas);
            $row_lojas = cms_fetch_assoc($res_lojas);
            if((int)$row_lojas==0) continue;
            
        }
        
        $arr_shipping[] = array(
            "id"                => $row["type"],
            "id_shipping_return"=> $row["id"],
            "name"              => $row["nome".$LG],
            "short_content"     => nl2br($row["desc".$LG]),
            "title"             => $row["title".$LG],
            "content"           => $row["bloco".$LG],
            "return_option"     => $return_options,
            "shipping_address"  => $MARKET["morada_devolucao".$LG],
            "pickme_type"       => $pickme_type,
            "addresses"         => $arr_moradas,
            "country_id"        => $pais_id,
            "not_finish"        => (int)$row['sem_finalizar']
        );
    }

    return $arr_shipping;
}


?>
