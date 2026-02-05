<?

function _getRMADetail($user_id=0, $rma_id=0){
    
    if( (int)$rma_id <= 0 || (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
        $rma_id = (int)params('rma_id');
    }

    if( (int)$rma_id <= 0 || $user_id <= 0 ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }
    
    global $LG;
    
    $response = array();
    
    
    $userOriginalID = $user_id;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    $rma                = cms_fetch_assoc( cms_query('SELECT * FROM `ec_rmas` WHERE `id`='.$rma_id.' AND `user_id`='.$userOriginalID) );
    if( empty($rma) || (int)$rma['id'] <= 0 ){
        return serialize(['success' => 0, 'error' => 'RMA not found']);
    }

    $rma['token']       = md5($rma['id']."|||".$rma['ref']."|||".$rma['created_at']."|||".$rma['user_id']);
    $rma['date']        = date('Y-m-d', strtotime($rma['created_at']) );
    $rma['created_at']  = date("Y-m-d H:i", strtotime($rma['created_at']) );
    
    
    $rmas_status_info_row = cms_fetch_assoc( cms_query('SELECT * FROM `ec_rmas_status` WHERE `id`='.$rma['status']) );
    
    $rmas_status_info['id']           = $rmas_status_info_row['id'];
    $rmas_status_info['name']         = $rmas_status_info_row['name'.$LG];
    $rmas_status_info['description']  = $rmas_status_info_row['description'.$LG];
    $rmas_status_info['class_name']   = $rmas_status_info_row['class_name'];
    
    $rma['status_info'] = $rmas_status_info;
    
    # Logs
    $rma['logs'] = array();
    
    $rmas_logs_res = cms_query("SELECT datetime, obs FROM `ec_rmas_logs` WHERE `rma_id`=".$rma_id." ORDER BY id DESC");
    
    while( $rma_log = cms_fetch_assoc($rmas_logs_res) ){
        
        $rma['logs'][] = $rma_log;             
        
    }
    # Logs

    # Observations
    $rma['observations'] = array();
    
    $rmas_obs_res = cms_query("SELECT `datahora` AS `datetime`, `obs`, `autor` AS `author` FROM `ec_rmas_observacoes` WHERE `rma_id`=".$rma_id." ORDER BY `id` DESC");
    while( $rma_log = cms_fetch_assoc($rmas_obs_res) ){
        $rma['observations'][] = $rma_log;             
    }
    # Observations
    
    # Invoices
    $rma['invoices'] = array();
    
    $rma_invoices_res = cms_query("SELECT t.* 
                                    FROM `ec_rmas_documents` r
                                      INNER JOIN `_tdocumentos` t ON t.`id` = r.`document_id` 
                                    WHERE r.`rma_id`=".$rma['id']);
    
    while( $rma_invoice = cms_fetch_assoc($rma_invoices_res) ){
        
        $rma_invoice_temp = array();
        $rma_invoice_temp['ref']  = $rma_invoice['num'];
        $rma_invoice_temp['date'] = date('Y-m-d', strtotime($rma_invoice['data_doc']));
        
        $rma['invoices'][] = $rma_invoice_temp; 
        
    }
    # Invoices                
    
    # Products
    $sku_files_uploaded = array();
    $rma['products'] = array();
    
    $rma_products_res = cms_query("SELECT `ec_rmas_lines`.*, COUNT(`id`) AS quantity FROM `ec_rmas_lines` WHERE `rma_id`=".$rma_id." GROUP BY sku");
    
    while( $rma_product = cms_fetch_assoc($rma_products_res) ){
        
        $rma_product_temp                           = array();
        $rma_product_temp['sku']                    = $rma_product['sku'];
        $rma_product_temp['name']                   = $rma_product['name'];
        $rma_product_temp['price']                  = call_api_func('OBJ_money', $rma_product['price'], $rma_product['currency_id']);
        $rma_product_temp['reason']                 = $rma_product['reason_desc']; 
        $rma_product_temp['obs1']                   = $rma_product['obs1'];
        $rma_product_temp['obs2']                   = $rma_product['obs2'];
        $rma_product_temp['quantity']               = $rma_product['quantity'];
        $rma_product_temp['uploaded_files_number']  = $rma_product['uploaded_files'];
        
        $rma['products'][] = $rma_product_temp;

        $sku_files_uploaded[ preg_replace("/[^a-zA-Z0-9 .\-_]/", "", $rma_product_temp['sku']) ] = array( 'sku_original' => $rma_product_temp['sku'] );
        
    }
    # Products

    $rma['status_history'] = _getRMADetail_getRMAStatus($rma);
    
    # Uploaded files
    $rma['uploaded_files'] = array();

    $estr_900 = estr2(900); 
    $file_number = 1;
    
    $folders_in_folder = glob($_SERVER["DOCUMENT_ROOT"]."/downloads/rmas/".$rma_id."/*");
    foreach ($folders_in_folder as $folder) {
        
        $files_in_folder = glob($folder."/*");

        $folder_sku = end( explode("/", $folder) );
        
        foreach ($files_in_folder as $value) {
            
            $file = explode(".",$value);

            $file_extension = strtolower(end($file));

            $html_file_extension = call_api_func('getHtmlIconToFileExtension', $file_extension);
            
            $rowFile = array(
                'nome' => $html_file_extension,
                'nome'.$LG => $folder_sku." ".$file_number." ".strtoupper($file_extension), 
                'extension' => $file_extension,
                'download_name' => $folder_sku."_".$file_number
            );
            
            $rma['uploaded_files'][] = call_api_func( 'fileOBJ', $rowFile, str_replace($_SERVER["DOCUMENT_ROOT"]."/", "", $value), 1 );
            $file_number++;

            $sku_files_uploaded[$folder_sku]['number_files'] = (int)$sku_files_uploaded[$folder_sku]['number_files'] + 1;

        }

    }
    
    foreach ( $rma['products'] as $k => $rma_prod ) {
        $rma['products'][$k]['uploaded_files_number'] = (int)$sku_files_uploaded[ preg_replace("/[^a-zA-Z0-9 .\-_]/", "", $rma_prod['sku']) ]['number_files'];
    }
    # Uploaded files
    
    $response['rma'] = $rma;
    
    return serialize(['success' => 1, 'payload' => $response]);
    
}


function _getRMADetail_getRMAStatus($rma){
    
    global $LG, $API_CONFIG_RMA_NOVO_FLUXO;
    
    $status_history = array();
                           
    if($API_CONFIG_RMA_NOVO_FLUXO==1){
        $more_where = "AND `id` NOT IN(30)";
        if( $rma['status'] == 30 ) $more_where = "AND `id` IN(1,30)";
    }else{
        $more_where = "AND `id` NOT IN(60)";
        if( $rma['status'] == 60 ) $more_where = "AND `id` IN(1,60)";
    }
    
    $rmas_status_res = cms_query("SELECT id, name$LG AS name, description$LG AS description, class_name FROM `ec_rmas_status` WHERE `hide`=0 $more_where and id!=10 ORDER BY process_position");
    while( $rmas_status = cms_fetch_assoc($rmas_status_res) ){
        
        if( $rma['status'] != $rmas_status['id'] || trim($rmas_status['description']) == "" ) unset($rmas_status['description']);
        
        if( $rma['status'] == $rmas_status['id'] ) $rmas_status['current_status'] = 1; 
        
        $status_history[] = $rmas_status;
            
    }  
    
    return $status_history;    
    
}

?>
