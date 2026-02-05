<?

function _uploadFile(){

    $_POST = decode_array_to_UTF8($_POST);

    # LOGS
    if (!file_exists(_ROOT.'/logs/logs_pos_venda_'.date("Y"))) {
        mkdir(_ROOT.'/logs/logs_pos_venda_'.date("Y"), 0777, true);
    }
    # LOGS


    $type           = (int)$_POST['type'];
    $sku            = trim($_POST['sku']);
    $token          = trim($_POST['token']);
    $id             = (int)$_POST['id'];
    $file_number    = (int)$_POST['file_number'];
    
    global $userID, $CONFIG_OPTIONS;

    $userOriginalID = (int)$_SESSION['EC_USER']['id'];
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    

    # LOGS
    $_handler = fopen(_ROOT.'/logs/logs_pos_venda_'.date("Y").'/'.date("Ymd").'_log_rma_upload_post.txt', 'a+');
    fwrite($_handler, date("Y-m-d H:i:s")."\n");
    fwrite($_handler, print_r("_POST:", true)."\n");
    fwrite($_handler, print_r($_POST, true));
    fwrite($_handler, print_r("user:".$userOriginalID, true)."\n");
    fwrite($_handler, "USER AGENT: ".$_SERVER['HTTP_USER_AGENT']."\n\n");    
    fclose($_handler);    
    # LOGS
        
    if( $type <= 0 || count($_FILES) == 0 || empty($token) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }



    if( $type == 1 ){ # RMA

        # LOGS
        $handler = fopen(_ROOT.'/logs/logs_pos_venda_'.date("Y").'/'.date("Ymd").'_log_rma_uploads.txt', 'a+');
        # LOGS

        $rma = cms_fetch_assoc( cms_query( "SELECT `id`, `ref`, `created_at`, `user_id` FROM `ec_rmas` WHERE `id`='".$id."' AND `user_id`='".$userOriginalID."'" ) );
        if( (int)$rma['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($rma['id']."|||".$rma['ref']."|||".$rma['created_at']."|||".$rma['user_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $rma_folder_dir = $_SERVER['DOCUMENT_ROOT']."/downloads/rmas";
        if( !file_exists($rma_folder_dir) ) mkdir($rma_folder_dir);

        $rma_folder_dir = $_SERVER['DOCUMENT_ROOT']."/downloads/rmas/".$rma['id'];
        if( !file_exists($rma_folder_dir) ) mkdir($rma_folder_dir);

        if( $sku != "" ){

            $sku = preg_replace("/[^a-zA-Z0-9 .\-_]/", "", $sku);

            $rma_folder_dir = $_SERVER['DOCUMENT_ROOT']."/downloads/rmas/".$rma['id']."/".$sku;
            if( !file_exists($rma_folder_dir) ) mkdir($rma_folder_dir);

            $folder_dir = $rma_folder_dir;

        }

        $file_name = $file_number;

    }elseif( $type == 2 ){ # Warranty - pos venda

        # LOGS
        $handler = fopen(_ROOT.'/logs/logs_pos_venda_'.date("Y").'/'.date("Ymd").'_log_warranty_uploads.txt', 'a+');
        # LOGS

        $warranty = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda_avarias` WHERE `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'" ) );
        if( (int)$warranty['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($warranty['id']."|||".$warranty['veiculo_id']."|||".$warranty['data_criacao']."|||".$warranty['utilizador_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $warranty_folder_dir = $_SERVER['DOCUMENT_ROOT']."/downloads/warranties";
        if( !file_exists($warranty_folder_dir) ) mkdir($warranty_folder_dir);

        $warranty_folder_dir = $_SERVER['DOCUMENT_ROOT']."/downloads/warranties/".$warranty['id'];
        if( !file_exists($warranty_folder_dir) ) mkdir($warranty_folder_dir);
        
        $folder_dir = $warranty_folder_dir;

        $file_name = $file_number;
        
    }elseif( $type == 3 ){ # Warranty - pos venda -> avarias -> campo observações

        $warranty = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda_avarias` WHERE `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'" ) );
        if( (int)$warranty['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($warranty['id']."|||".$warranty['veiculo_id']."|||".$warranty['data_criacao']."|||".$warranty['utilizador_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $pasta = $_SERVER['DOCUMENT_ROOT']."/downloads/warranties";
        if( !file_exists($pasta) ) mkdir($pasta);

        $pasta = $_SERVER['DOCUMENT_ROOT']."/downloads/warranties/".$warranty['id'];
        if( !file_exists($pasta) ) mkdir($pasta);
        
        $folder_dir = $pasta;
        $file_name  = "obs_".$file_number;

    } 
    /* 
    # observacoes_veiculos
    ## desenvolvido e a funcionar mas não está a ser usado - comentado caso seja necessário no futuro
    elseif( $type == 4 ){ # Pos venda -> campo observações

        $after_sale = cms_fetch_assoc( cms_query( "SELECT `id`, `veiculo_id`, `data_criacao`, `utilizador_id` FROM `b2b_pos_venda` WHERE `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'" ) );
        if( (int)$after_sale['id'] <= 0 ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Not found']);
        }

        if( $token != md5($after_sale['id']."|||".$after_sale['veiculo_id']."|||".$after_sale['data_criacao']."|||".$after_sale['utilizador_id']) ){
            return serialize(['success' => 0, 'error' => 'Bad Request - Signature error']);
        }

        $pasta = $_SERVER['DOCUMENT_ROOT']."/downloads/vehicles";
        if( !file_exists($pasta) ) mkdir($pasta);

        $pasta = $_SERVER['DOCUMENT_ROOT']."/downloads/vehicles/".$after_sale['id'];
        if( !file_exists($pasta) ) mkdir($pasta);

        $folder_dir = $pasta;
        $file_name  = "obs_".$file_number;

    }*/ 
    else{
        return serialize(['success' => 0, 'error' => 'Bad Request - Unprocessable Entity']);
    }

    $file = reset($_FILES);

    $allowed_extensions = array( 'png', 'jpg', 'jpeg', 'pdf', 'bmp', 'mov', 'rar', '3gp', 'jfif', 
                                    'mp4', 'heic', '3gpp', 'doc', 'zip', 'avi', 'mpg', 'docx');

    $file_extension = explode('.', strtolower($file['name']));
    $file_extension = end($file_extension);

    $mimetype = mime_content_type($file['tmp_name']);

    # LOGS
    fwrite($handler, print_r("\r\n"."-------------------------------------------------------"."\r\n", true));
    fwrite($handler, date("Y-m-d H:i:s"));
    fwrite($handler, print_r("\r\n", true));
    fwrite($handler, print_r($_POST, true));
    fwrite($handler, print_r("\r\n", true));
    fwrite($handler, print_r($_FILES, true));
    fwrite($handler, print_r("\r\n", true));
    fwrite($handler, print_r($file_extension, true));
    fwrite($handler, print_r("\r\n", true));
    fwrite($handler, print_r($mimetype, true));
    fclose($handler);
    # LOGS

    if( empty($folder_dir) || empty($file) || $file['size'] == 0 || !in_array($file_extension, $allowed_extensions) || $mimetype == "text/x-php" || stripos($mimetype,"php") !== false ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Not Acceptable']);
    }
    
    if( empty($file_name) ){
        $file_extension_temp = end( explode('.', $file['name']) );
        $file_name = trim( str_replace(".".$file_extension_temp, "", $file['name']) );
        $file_name = preg_replace("/[^a-zA-Z0-9 ]/", "", $file_name);
    }

    $file_final = $folder_dir."/".$file_name.".".$file_extension;

    unlink($file_final);
    copy($file['tmp_name'], $file_final);

    if( $type == 2 ){
        $show_b2b_pos_venda_avarias = cms_query("UPDATE `b2b_pos_venda_avarias` SET `hidden`='0' WHERE `hidden`='1' AND `id`='".$id."' AND `utilizador_id`='".$userOriginalID."'");
    }

    return serialize(['success' => 1, 'message' => 'File uploaded successfully']);

}

?>
