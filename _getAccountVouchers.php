<?

function _getAccountVouchers($page_id=null)
{

    global $userID;
    global $LG;
    global $MOEDA;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    

    
    $q = cms_query('(SELECT *
                    FROM ec_vauchers 
                    WHERE cod_cliente="'.$userID.'" AND (campanha_id=0 OR motivo_id="5") AND data_limite>=CURDATE())
                    UNION
                    (SELECT v.*
                    FROM ec_vauchers v
                                   INNER JOIN ec_encomendas e ON v.origin_order_id=e.id AND e.cliente_final="'.$userID.'"
                    WHERE motivo_id=11 AND campanha_id>0 AND data_inicio<=CURDATE() AND data_limite>=CURDATE())
                    ORDER BY data_limite asc');
                    
    
    while($row = cms_fetch_assoc($q)){


        if( (int)$row['crit_utilizacoes']==0 ){
            $used = cms_query("select * from ec_vouchers_log where voucher_id='".$row['id']."' and user_id='".$userID."' ");           
            if (cms_num_rows($used)){
                continue;            
            }
        }
        
        if( (int)$row['crit_utilizacoes']==-1 ){
            $used = cms_query("select * from ec_vouchers_log where voucher_id='".$row['id']."' ");           
            if (cms_num_rows($used)){
                continue;            
            }
        }

        $expirado = 0;
        $now = date('Y-m-d H:i:s');
        if($row["campanha_id"]>0){
            static $static_Campanha;
            $row_campanha = array();
           
            if ( isset( $static_Campanha[md5($row["campanha_id"])] ) ){
                $row_campanha = $static_Campanha[md5($row["campanha_id"])];
            }else{
                $sql_campanha = "SELECT * FROM ec_campanhas WHERE id='".$row['campanha_id']."' AND deleted='0' LIMIT 0,1";
                $res_campanha = cms_query($sql_campanha);
                $row_campanha = cms_fetch_assoc($res_campanha);
                $static_Campanha[md5($row["campanha_id"])] = $row_campanha;
            }
            
            if((int)$row_campanha["id"]>0 && (int)$row_campanha["validar_data_voucher"]==0){
                if(strtotime($now)>strtotime($row_campanha["data_fim"]." ".$row_campanha["hora_fim"].":59:59")){
                    $row['data_limite'] = estr("416");
                    $expirado = 1;
                }
            }
        }
        
        if($row["tipo"]=="1"){
            $row["valor"] = number_format($row['valor'], 0).'%';
        }elseif($row["tipo"]=="2"){
            $row["valor"] = call_api_func('moneyOBJ',$row['valor'], $MOEDA['id']);  
        }        
        
        
        $tipo_desconto = 1;
        if($row['motivo_id']==6) {
            $tipo_desconto = 3;
            $row['cod_promo'] = estr2("857");
        }

        
        #comentado e deixado a vazio proque este campo não tem traduções
        #"motivo_emissao"  => $row['motivo_emissao'],
        $descontos[] = array(
            "cod_promo"       => $row['cod_promo'],
            "motivo_emissao"  => "",
            "valor"           => $row['valor'],
            "data_inicio"     => ($row['data_inicio'] >= $now) ? $row['data_inicio'] : $data_inicio,
            "data_limite"     => $row['data_limite'],
            "saldo"           => $row['valor'],
            "tipo"            => $row['tipo'],
            "tipo_desconto"   => $tipo_desconto,
            "expirado"        => $expirado 
        );  
    }
    
    
    # Uso dos cupões de compensação
    $q = cms_query('SELECT v.*, vl.used_value, DATE(vl.data_usado) as data_usado  
                      FROM ec_vouchers_log vl 
                        LEFT JOIN ec_vauchers v ON vl.voucher_id=v.id 
                      WHERE vl.user_id="'.$userID.'" AND v.motivo_id=6');
       
             
    $voucher = array();
    while($row = cms_fetch_assoc($q)){
                   
        if($row['used_value']==0) continue;
           
        #comentado e deixado a vazio proque este campo não tem traduções
        #"motivo_emissao"  => $row['motivo_emissao'],
        $descontos[] = array(
            "cod_promo"       => $row['cod_promo'],
            "motivo_emissao"  => "",
            "valor"           => call_api_func('moneyOBJ',$row['valor'], $MOEDA['id']),
            "data_inicio"     => "",
            "data_limite"     => $row['data_usado'],
            "saldo"           => call_api_func('moneyOBJ',($row['valor']-$row['used_value']), $MOEDA['id']),
            "tipo"            => $row['tipo'],
            "tipo_desconto"   => 3,
            "expirado"        => $expirado 
        );  
    }
    
    
    
    #vales
    
    $aux = cms_query("SELECT t1.* 
                        FROM ec_vales_pagamentos t1
                            JOIN (SELECT check_id, MAX(datahora) datahora FROM ec_vales_pagamentos WHERE user_id='$userID' and user_id>0 GROUP BY check_id) t2
                          ON t1.check_id = t2.check_id AND t1.datahora = t2.datahora
                        WHERE user_id='$userID' and user_id>0 "); 
                                                                                                       
                        
    
    $val = array();         
    $control = array();       
    while($row3 = cms_fetch_assoc($aux)){
        if($row3['user_id'] ==  $userID){
            $val[] = $row3['check_id'];   
            
            $control[] = array(
                "vale" => $row3['check_id'],
                "data" => date("Y-m-d", strtotime($row3['datahora']))
            );                                               
        }
    }
    
    
    
    
    $aux = cms_query("SELECT * 
                        FROM ec_vales      
                        WHERE cliente_id='$userID'"); 
                                                                                                           
    while($row3 = cms_fetch_assoc($aux)){
        $val[] = $row3['id'];   
        
        $control[] = array(
            "vale" => $row3['id'],
            "data" => '0'
        );                                               
    }
    
    
    
    $val = implode(",",$val);
    
    
    $menos_3meses = date("Y-m-d", strtotime("-3 months"));                                                                             
    
    $today = date("Y-m-d");
    
    $s = cms_query("SELECT *
                    FROM ec_vales 
                    WHERE data_validade>='".$today."' 
                        AND id IN(".$val.") 
                        AND (obs NOT LIKE 'pontosck%' AND obs NOT LIKE 'credito%' AND obs NOT LIKE 'prime%') ");
      
                    
    $vales = array();                
    while($row2 = cms_fetch_assoc($s)){
        
        $vales[] = $row2;
        
        #$a = cms_query("SELECT SUM(valor_descontado) as gasto FROM ec_vales_pagamentos WHERE check_id = '".$row2['id']."'");
        $a = cms_query("SELECT SUM(valor_descontado) as gasto FROM ec_vales_pagamentos WHERE check_id = '".$row2['id']."' AND order_id > 0");
        $temp = cms_fetch_assoc($a);
        
        $saldo = $row2["valor"] - $temp['gasto'];
                        
        $saldo = call_api_func('moneyOBJ',$saldo, $MOEDA['id']);
        
        $row2["valor"] = call_api_func('moneyOBJ',$row2['valor'], $MOEDA['id']);  
        
        $data = "";
        
        foreach ($control as $key=>$value) {   
            if($row2['id'] == $value['vale']){
                $data = $value['data'];    
            }           
        }     
              
        # 2020-03-03 - Definido por Serafim que mesmo depois de usado se só gastou há menos de 3 meses ainda fica visivel       
        if($saldo['value'] <= 0 && $data<$menos_3meses) continue;                           
        
        
        $desc = estr2(340)." ".$data;
        if($data==0) $desc='';
           
        $descontos[] = array(
            "cod_promo" => $row2['codigo'],
            "motivo_emissao" => $desc,
            "valor" => $row2['valor'],
            "data_inicio"     => "",
            "data_limite" => $row2['data_validade'],
            "saldo" => $saldo,
            "tipo" => "",
            "tipo_desconto" => 2 
        );  
         
    }
    

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    
    $arr['vouchers'] = $descontos;
    
    $arr['control'] = $control;
    
    
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}
?>
