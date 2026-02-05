<?

function _getAccount($page_id=null)
{
   
    global $userID, $eComm, $LG, $B2B, $SETTINGS, $CONFIG_TEMPLATES_PARAMS;
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    
    $arr = array();
    $arr['page']                = call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages']       = call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer']            = call_api_func('getCustomer');
    $arr['shop']                = call_api_func('OBJ_shop_mini');                
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    
    $TEMPLATES_PARAMS_ACCOUNT = array();
    get_options_template($TEMPLATES_PARAMS_ACCOUNT, 'account');
    $TEMPLATES_PARAMS_ACCOUNT = $TEMPLATES_PARAMS_ACCOUNT['response']['shop']['TEMPLATES_PARAMS'];
    $arr['shop']['TEMPLATES_PARAMS'] = array_merge((array)$arr['shop']['TEMPLATES_PARAMS'], (array)$TEMPLATES_PARAMS_ACCOUNT);
    
    
    if( (int)$B2B == 0 && (int)$page_id == 9 && (int)$TEMPLATES_PARAMS_ACCOUNT['myAccountIntroDisplaySummary'] == 1 ){
        
        $count_orders = cms_fetch_assoc( cms_query("SELECT COUNT(id) AS total FROM ec_encomendas WHERE cliente_final='".$userID."' AND tracking_status NOT IN('-1','-50')") );
        $arr['account_summary']['orders'] = $count_orders['total'];
        
        $count_returns = cms_fetch_assoc( cms_query("SELECT COUNT(id) AS total FROM ec_devolucoes WHERE cliente_id='".$userID."' AND tipo=0") );
        $arr['account_summary']['returns'] = $count_returns['total'];
        
        $count_returns = cms_fetch_assoc( cms_query("SELECT COUNT(id) AS total FROM ec_moradas WHERE id_user='".$userID."'") );
        $arr['account_summary']['adresses'] = $count_returns['total'];
        
        $count_vouchers = cms_fetch_assoc( cms_query("SELECT COUNT(v.id) AS total
                                                      FROM ec_vauchers v
                                                        LEFT JOIN ec_vouchers_log vl ON vl.voucher_id = v.id AND vl.user_id = '$userID'
                                                      WHERE cod_cliente='$userID' 
                                                        AND (campanha_id=0 OR motivo_id='5') 
                                                        AND data_limite>=CURDATE() 
                                                        AND ( ( crit_utilizacoes = 0 AND vl.id IS NULL ) OR crit_utilizacoes!=0 )
                                                      ORDER BY data_limite asc") );
        
        $count_vouchers2 = 0;
        
        $init_date = date("Y-m-d", strtotime("-3 months"));
        
        $res_vouchers2 = cms_query("SELECT ec_vales.*, vales_log.datahora AS last_usage
                                    FROM ec_vales
                                      INNER JOIN (SELECT t1.check_id, t2.datahora 
                                                    FROM ec_vales_pagamentos t1 
                                                      JOIN (SELECT check_id, MAX(datahora) datahora 
                                                            FROM ec_vales_pagamentos 
                                                            WHERE user_id='$userID' 
                                                              AND user_id>0 
                                                            GROUP BY check_id) t2 ON t1.check_id = t2.check_id AND t1.datahora = t2.datahora 
                                                    WHERE user_id='$userID' and user_id>0) vales_log ON vales_log.check_id = ec_vales.id    
                                    WHERE data_validade>='".date("Y-m-d")."' 
                                      AND (obs NOT LIKE 'pontosck%' AND obs NOT LIKE 'credito%' AND obs NOT LIKE 'prime%')");
                                      
        while( $row_vouchers2 = cms_fetch_assoc($res_vouchers2) ){
            
            $voucher = cms_fetch_assoc( cms_query("SELECT SUM(valor_descontado) as gasto FROM ec_vales_pagamentos WHERE check_id = '".$row_vouchers2['id']."' AND order_id > 0") );
            
            $balance = $row_vouchers2["valor"] - $voucher['gasto']; 
            
            if( $balance <= 0 && $row_vouchers2['last_usage'] < $init_date ) continue;
            
            $count_vouchers2++;
            
        }
                                                              
        $arr['account_summary']['vouchers'] = (int)$count_vouchers['total'] + $count_vouchers2;
        
    }

        
    $arr['my_client_id'] = ((int)$SETTINGS['show_client_id'] > 0 && $_SESSION["EC_USER"]["client_id"] != '' ? 1 : 0);
    $arr['code_client_id'] = ((int)$SETTINGS['show_client_id'] > 0 ? $_SESSION["EC_USER"]["client_id"] : '');
    $arr['policy_page_id'] = $CONFIG_TEMPLATES_PARAMS['policy_page_id'];
    
    return serialize($arr);
    
}

?>
