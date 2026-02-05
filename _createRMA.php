<?

function _createRMA(){
    
    $_POST = decode_array_to_UTF8($_POST);
    
    $type     = (int)$_POST['type_path'];
    $ref      = trim($_POST['ref']);
    $products = $_POST['products']; 
    
    if( $type <= 0 || $type > 2 || $ref == "" || count($products) == 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    global $CONFIG_OPTIONS;
    
    
    $userOriginalID = (int)$_SESSION['EC_USER']['id'];
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if( $type == 1 ){    
        
        $document_info = cms_fetch_assoc( cms_query("SELECT GROUP_CONCAT(`id`) as ids FROM `_tdocumentos` WHERE `num`='".$ref."' AND `id_user`='".$userOriginalID."'") );
        if( $document_info['ids'] == '' ){
            return serialize(['success' => 0, 'error' => 'Error on create RMA - Document not found']);
        }
        
        foreach($products as $key=>$value){
            
            $has_doc_line = cms_fetch_assoc( cms_query("SELECT tl.`id`, tl.`id_documento` 
                                                          FROM `_tdocumentos_lines` tl
                                                              LEFT JOIN `ec_rmas_lines` rl ON rl.`document_line_id` = tl.`id` 
                                                          WHERE tl.`id_documento` in (".$document_info['ids'].") AND tl.`sku`='".$value['p_ref']."' AND rl.`document_line_id` IS NULL
                                                          LIMIT 1") );
            
            if( (int)$has_doc_line['id'] <= 0 ){
                return serialize(['success' => 0, 'error' => 'Error on create RMA - Product not found']);
            }
            
            $document_info['id'] = $has_doc_line['id_documento'];        
        }
        
          
    }elseif( $type == 2 ){
        
        foreach($products as $key=>$value){
            if($value['i_ref'] == "") continue;
        
            $has_doc_line = cms_fetch_assoc( cms_query("SELECT tl.`id`
                                                          FROM `_tdocumentos_lines` tl 
                                                              INNER JOIN `_tdocumentos` t ON t.`id` = tl.`id_documento` AND t.`id_user`='".$userOriginalID."' AND t.`num`='".$value['i_ref']."'
                                                              LEFT JOIN `ec_rmas_lines` rl ON rl.`document_line_id` = tl.`id` 
                                                          WHERE tl.`sku`='".$ref."' AND rl.`document_line_id` IS NULL
                                                          LIMIT 1") );    
            
            if( (int)$has_doc_line['id'] <= 0 ){
                return serialize(['success' => 0, 'error' => 'Error on create RMA - Product not found']);
            }
            
        }
    
    }
    
    
    
    $rma_created = cms_query("INSERT INTO `ec_rmas` SET `user_id`='".$userOriginalID."'");

    if( !$rma_created ){
        return serialize(['success' => 0, 'error' => 'Error on create RMA']);
    }

    $rma_created_id = cms_insert_id();

    if( (int)$rma_created_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Error on get RMA id']);
    }
    
    $rma_created_ref = 'RMA '.date("y").".".str_pad($rma_created_id, 6, "0", STR_PAD_LEFT);
    cms_query("UPDATE `ec_rmas` SET `ref`='".$rma_created_ref."' WHERE `id`=".$rma_created_id);
    
    $rma_skus = array();
    
    $hasRMALine = 0;
            
    if( $type == 1 ){    

        cms_query("INSERT INTO `ec_rmas_documents` SET `rma_id`='".$rma_created_id."', `document_id`='".$document_info['id']."'");
        
        foreach($products as $key=>$value){
            
            $doc_line = cms_fetch_assoc( cms_query("SELECT tl.*, COUNT(tl.`id`) AS qnt_total, GROUP_CONCAT(tl.`id`) AS doc_line_ids  
                                                      FROM `_tdocumentos_lines` tl
                                                          LEFT JOIN `ec_rmas_lines` rl ON rl.`document_line_id` = tl.`id` 
                                                      WHERE tl.`id_documento`='".$document_info['id']."' AND tl.`sku`='".$value['p_ref']."' AND rl.`document_line_id` IS NULL 
                                                      GROUP BY tl.`sku`") );
            
            if( (int)$CONFIG_OPTIONS['RMAS_ONE_PROD_ONE_QTY'] == 1 ){
                $value['p_qtd'] = 1;
            }

            $go = _createRMA_addRMALine($rma_created_id, $doc_line, $value);
            
            if( $go>0 ){
                $hasRMALine = 1;
            }
            
            
            $rma_skus[ strtolower($doc_line["sku"]) ] = strtolower($doc_line["sku"]);

            if( (int)$CONFIG_OPTIONS['RMAS_ONE_PROD_ONE_QTY'] == 1 ){
                break;
            }
          
        }
          
    }elseif( $type == 2 ){
        
        $rma_skus[ strtolower($ref) ] = strtolower($ref);
        
        foreach($products as $key=>$value){

            if($value['i_ref'] == "") continue;
        
            $doc_line = cms_fetch_assoc( cms_query("SELECT tl.*, COUNT(tl.`id`) AS qnt_total, GROUP_CONCAT(tl.`id`) AS doc_line_ids, t.`num` AS doc_ref, t.`data_doc` AS doc_date
                                                      FROM `_tdocumentos_lines` tl 
                                                          INNER JOIN `_tdocumentos` t ON t.`id` = tl.`id_documento` AND t.`id_user`='".$userOriginalID."' AND t.`num`='".$value['i_ref']."'
                                                          LEFT JOIN `ec_rmas_lines` rl ON rl.`document_line_id` = tl.`id` 
                                                      WHERE tl.`sku`='".$ref."' AND rl.`document_line_id` IS NULL
                                                      GROUP BY tl.`id_documento`, `sku`") );    
            
            
            if( $doc_line['id_documento']<0 ){
                return serialize(['success' => 0,'msg' => 'Error on get RMA document']);
            }
            
            cms_query("INSERT INTO `ec_rmas_documents` SET `rma_id`='".$rma_created_id."', `document_id`='".$doc_line['id_documento']."'");
            
            if( (int)$CONFIG_OPTIONS['RMAS_ONE_PROD_ONE_QTY'] == 1 ){
                $value['p_qtd'] = 1;
            }

            $go = _createRMA_addRMALine($rma_created_id, $doc_line, $value);

            if( $go>0 ){
                $hasRMALine = 1;
            }

            if( (int)$CONFIG_OPTIONS['RMAS_ONE_PROD_ONE_QTY'] == 1 ){
                break;
            }
            
        }

    }
    
    if( !$hasRMALine ){
        return serialize(['success' => 0,'msg' => 'Error on get RMA lines']);
    }
    
    cms_query("UPDATE `ec_rmas` SET `status`='1' WHERE `id`=".$rma_created_id);
    
    cms_query("INSERT INTO `ec_rmas_logs` (`rma_id`, `obs`, `author`, `status`) VALUES(".$rma_created_id.", 'RMA registado', 'Cliente - ação manual em site', '1')");

    $created_rma = cms_fetch_assoc( cms_query( "SELECT `id`, `ref`, `created_at`, `user_id` FROM `ec_rmas` WHERE `id`='".$rma_created_id."'" ) );
    $created_rma_token = md5($created_rma['id']."|||".$created_rma['ref']."|||".$created_rma['created_at']."|||".$created_rma['user_id']);

    return serialize(['success' => 1, 'msg' => 'RMA created successfully', 'rma' => $rma_created_id, 'token' => $created_rma_token]);
    
}

function _createRMA_addRMALine($rma_id, $doc_line, $post_value){
    
    if( $post_value['p_qtd'] > $doc_line['qnt_total'] ) $post_value['p_qtd'] = $doc_line['qnt_total'];
            
    $arr_doc_line_ids = explode(",", $doc_line['doc_line_ids']);
    
    $hasRMALine = 0;
    
    for($i = 0; $i < $post_value['p_qtd']; $i++){
        
        $arr                      = array();
        $arr['rma_id']            = $rma_id;
        $arr['sku']               = $doc_line["sku"];
        $arr['name']              = $doc_line['nome'];
        $arr['price']             = $doc_line['valor'];
        $arr['currency_id']       = $doc_line['moeda'];
        $arr['reason_id']         = $post_value['p_reason_id'];
        $arr['reason_desc']       = $post_value['p_reason'];
        $arr['obs1']              = $post_value['p_report'];
        $arr['obs2']              = $post_value['p_diagnose'];
        $arr['document_line_id']  = $arr_doc_line_ids[$i];

        $f = array();
        $v = array();
        foreach( $arr as $campo=>$valor ){
            $f[] = "`".$campo."`";
            $v[] = "'".safe_value($valor)."'";
        }
        
       $go = cms_query("INSERT INTO `ec_rmas_lines` (".implode(",", $f).") VALUES(".implode(",", $v).")");
       
       if( $go>0 ){
          $hasRMALine = 1;
       }
        
    }
    
    return $hasRMALine;
       
}

?>
