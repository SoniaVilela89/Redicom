<?

function _getInfoToRMA(){
    
    global $CONFIG_OPTIONS;

    $_POST = decode_array_to_UTF8($_POST);

    $type = (int)$_POST['type'];
    $ref  = trim($_POST['ref']);
    
    if( (int)$type <= 0 || $ref == "" ){
        return serialize(["success" => 0, "error" => "Bad Request - Missing Data", "error_type" => $type]);
    }
    
    $error_type = 2;
    
    $payload = array();
    
    
    $userOriginalID = (int)$_SESSION['EC_USER']['id'];
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    
    $more_query_data = '';
    if($CONFIG_OPTIONS['B2B_RMAS_DATA_LIMITE']>0) $more_query_data = " AND DATEDIFF(NOW(),t.`data_doc`)<".$CONFIG_OPTIONS['B2B_RMAS_DATA_LIMITE']; 
                                                                                                                                
    if( $type == 1 ){

        $q = cms_query("SELECT id, num AS ref FROM `_tdocumentos` t WHERE `id` = '".$ref."' AND `id_user`='".$userOriginalID."' AND `credito`=0 AND `debito`>0 $more_query_data LIMIT 1");
        $invoice_info = cms_fetch_assoc($q);
        $invoice_info_total = cms_num_rows($q);

        if( (int)$invoice_info['id'] > 0 ){

            $sw = "SELECT `_tdocumentos_lines`.`id`, `_tdocumentos_lines`.`sku`, `_tdocumentos_lines`.`nome`, `_tdocumentos_lines`.`valor`, `_tdocumentos_lines`.`moeda`, COUNT(`_tdocumentos_lines`.`id`) AS `qnt_total`, `registos`.`sem_devolucao` 
                      FROM `_tdocumentos_lines`
                          LEFT JOIN `ec_rmas_lines` ON `ec_rmas_lines`.`document_line_id` = `_tdocumentos_lines`.`id`     
                          LEFT JOIN `registos` ON `_tdocumentos_lines`.`sku` = `registos`.`sku`      
                      WHERE `_tdocumentos_lines`.`id_documento`='".$invoice_info['id']."' AND `ec_rmas_lines`.`document_line_id` IS NULL 
                      GROUP BY _tdocumentos_lines.`sku`";
            $doc_lines_res = cms_query($sw);
            
            if( cms_num_rows($doc_lines_res) > 0 ){

                $payload['invoice'] = $invoice_info;
                $payload['invoice']['lines'] = array();
                $payload['invoice']['no_return'] = 0;


                while( $doc_line_info = cms_fetch_assoc($doc_lines_res) ){
                    $doc_line_temp              = array();
                    $doc_line_temp['id']        = $doc_line_info['id'];
                    $doc_line_temp['ref']       = $doc_line_info['sku'];
                    $doc_line_temp['name']      = $doc_line_info['nome'];
                    $doc_line_temp['price']     = call_api_func('OBJ_money', $doc_line_info['valor'], $doc_line_info['moeda']);
                    $doc_line_temp['quantity']  = $doc_line_info['qnt_total'];
                    $doc_line_temp['no_return'] = (int)$doc_line_info['sem_devolucao'];
                    $doc_line_temp['RMAS_NO_DEV'] = ((int)$doc_line_info['sem_devolucao'] == 1 && (int)$CONFIG_OPTIONS['RMAS_NO_DEV'] == 1) ? 1 : 0;

                    if((int)$doc_line_info['sem_devolucao'] > 0 ) $payload['invoice']['no_return'] = 1;
                    
                    $payload['invoice']['lines'][] = $doc_line_temp;
                }
            
            }
            
        }else{
            $error_type = 1;
        }
        
    }elseif( $type == 2 ){
        
        $invoice_lines_info_res = cms_query("SELECT tl.*, COUNT(tl.`id`) AS qnt_total, GROUP_CONCAT(tl.`id`) AS doc_line_ids, t.`num` AS doc_ref, t.`data_doc` AS doc_date, reg.sem_devolucao 
                                              FROM `_tdocumentos_lines` tl 
                                                  INNER JOIN `_tdocumentos` t ON t.`id` = tl.`id_documento` AND t.`id_user`='".$userOriginalID."' AND t.`credito`=0 AND t.`debito`>0 $more_query_data
                                                  LEFT JOIN `ec_rmas_lines` rl ON rl.`document_line_id` = tl.`id` 
                                                  LEFT JOIN `registos` reg ON tl.`sku` = reg.`sku`
                                              WHERE (tl.`sku`='".$ref."' OR tl.`ean`='".$ref."') AND rl.`document_line_id` IS NULL
                                              GROUP BY tl.`id_documento`
                                              ORDER BY t.`data_doc` DESC, t.`id` DESC");
                                              
        while( $invoice_line_info = cms_fetch_assoc($invoice_lines_info_res) ){
            
            $invoice_line_temp              = array();
            $invoice_line_temp['id']        = $invoice_line_info['id_documento'];
            $invoice_line_temp['ref']       = $invoice_line_info['doc_ref'];
            $invoice_line_temp['date']      = $invoice_line_info['doc_date'];

            $invoice_line_temp['line']['ref']       = $invoice_line_info['sku'];
            $invoice_line_temp['line']['name']      = $invoice_line_info['nome'];
            $invoice_line_temp['line']['quantity']  = $invoice_line_info['qnt_total'];
            $invoice_line_temp['line']['price']     = call_api_func('OBJ_money', $invoice_line_info['valor'], $invoice_line_info['moeda']);
            $invoice_line_temp['line']['no_return'] = (int)$invoice_line_info['sem_devolucao'];
            $invoice_line_temp['line']['RMAS_NO_DEV'] = ((int)$invoice_line_info['sem_devolucao'] == 1 && (int)$CONFIG_OPTIONS['RMAS_NO_DEV'] == 1) ? 1 : 0;
            
            if((int)$invoice_line_info['sem_devolucao'] > 0 ) $payload['no_return'] = 1;
            
            $payload['invoices'][] = $invoice_line_temp;      
            
        }
        

    }elseif( $type == 3 ){ # autocomplete referencias

        $s = "SELECT _tdocumentos_lines.id, _tdocumentos_lines.nome$LG, _tdocumentos_lines.sku
              FROM `_tdocumentos_lines` 
                  INNER JOIN `_tdocumentos` t ON t.`id` = _tdocumentos_lines.`id_documento` AND t.`id_user`='".$userOriginalID."' AND t.`credito`=0 AND t.`debito`>0 $more_query_data 
                  LEFT JOIN `ec_rmas_lines` ON ec_rmas_lines.`document_line_id` = _tdocumentos_lines.`id` 
                  LEFT JOIN `registos` ON _tdocumentos_lines.`sku` = registos.`sku`
              WHERE (_tdocumentos_lines.`sku` LIKE '%".$ref."%' OR _tdocumentos_lines.`ean` LIKE '%".$ref."%' OR _tdocumentos_lines.`nome` LIKE '%".$ref."%') AND ec_rmas_lines.`document_line_id` IS NULL
              GROUP BY _tdocumentos_lines.`sku`
              LIMIT 0,5";
        $q = cms_query($s);

        while($r = cms_fetch_assoc($q)){
            $resp[] = array( "ref" => $r['sku'], "label" => $r['sku'].' - '.$r['nome'.$LG] );
        }

        return serialize(['success' => 1, 'count' => count($resp), 'products' => $resp]);


    }elseif( $type == 4 ){ # autocomplete faturas

        $s = "SELECT `id`, `num` FROM `_tdocumentos` t WHERE `num` LIKE '%".$ref."%' AND `id_user`='".$userOriginalID."' AND `credito`=0 AND `debito`>0 $more_query_data";

        $q = cms_query($s);

        $count=0;
        while($r = cms_fetch_assoc($q)){

            if($count>=5) continue;

            $sw = "SELECT `_tdocumentos_lines`.`id`
                      FROM `_tdocumentos_lines`
                          LEFT JOIN `ec_rmas_lines` ON `ec_rmas_lines`.`document_line_id` = `_tdocumentos_lines`.`id`     
                          LEFT JOIN `registos` ON `_tdocumentos_lines`.`sku` = `registos`.`sku`      
                      WHERE `_tdocumentos_lines`.`id_documento`='".$r['id']."' AND `ec_rmas_lines`.`document_line_id` IS NULL";
            $qw = cms_query($sw);
            $rw = cms_fetch_assoc($qw);

            if($rw['id'] > 0 ){
                $resp[] = array( "ref" => $r['id'], "label" => $r['num'] );
                $count++;
            }

        }

        return serialize(['success' => 1, 'count' => $count, 'products' => $resp]);


    }
    
    if( count($payload) > 0 ){
        return serialize(['success' => 1, 'payload' => $payload]);
    }
     
    return serialize(['success' => 0, 'error' => 'Not Found', 'error_type' => $error_type]);
    
}

?>
