<?

function _setReview(){

    global $LG, $CONFIG_TEMPLATES_PARAMS, $CONFIG_OPTIONS;
  
    if($_SESSION['EC_USER']['id']<1){
        return serialize(array("0"=>"0"));  
    }

    $avaliacoes_tipos = array();

    $more_where = "";
    $more_where_ec_encomendas_lines = "";
    if( (int)$CONFIG_TEMPLATES_PARAMS['review_by_sku_family_color'] == 1 && (int)$_POST['color'] > 0){
        $more_where = "AND `cor`='".(int)$_POST['color']."'";
        $more_where_ec_encomendas_lines = "AND `cor_id`='".(int)$_POST['color']."'";
    }
   
    #Verificar se o cliente já fez alguma avaliação para aquele SKU_FAMILY
    $sql_ava    = 'SELECT * FROM registos_avaliacoes WHERE sku_family= "'.$_POST['sku_family'].'" and user_id="'.$_SESSION['EC_USER']['id'].'" '.$more_where;
    $res_ava    = cms_query($sql_ava);
    $row_ava    = cms_fetch_assoc($res_ava);
    if((int)$row_ava["id"]>0){
        return serialize(array("0"=>"0"));  
    }
    
    $query  = cms_query('SELECT * FROM ec_encomendas_lines WHERE status NOT IN (0,100,48) and sku_family= "'.$_POST['sku_family'].'" and id_cliente="'.$_SESSION['EC_USER']['id'].'" and order_id>0 '.$more_where_ec_encomendas_lines.' ORDER BY id desc LIMIT 0,1');
    $sql    = cms_fetch_assoc($query);
    
    if($sql["id"]>0){
        $q = cms_query("SELECT * FROM b2c_avaliacoes_tipos WHERE compra_confirmada='1' AND nome".$LG."!='' AND deleted='0'");
        while($row = cms_fetch_assoc($q)){
            $avaliacoes_tipos[$row["id"]] = $row["peso"];                                
        }
    }
    
    if(count($avaliacoes_tipos)==0){
        $s = "SELECT * FROM b2c_avaliacoes_tipos WHERE compra_confirmada='0' AND nome".$LG."!='' AND deleted='0'";
        $q = cms_query($s);
        while($row = cms_fetch_assoc($q)){
            $avaliacoes_tipos[$row["id"]] = $row["peso"];                                
        }
    }
    
    if(count($avaliacoes_tipos)<1){
        return serialize(array("0"=>"0"));  
    }   

    $avaliacao      = 0;
    foreach($_POST['reviews_field'] as $k => $v){
        $t1         = $v*($avaliacoes_tipos[$k]/100);
        $avaliacao += $t1;        
    }
    
    if($avaliacao>5) $avaliacao=5; #O máximo da pontuação é 5.

    $avaliacao = number_format($avaliacao,2);

    $_POST['anonimo']=='on' ? $_POST['anonimo'] = 1 : $_POST['anonimo']=0;
    

            
    $cor_id = 0;
    $review_size = 0;
    $review_width = 0;
    if( (int)$CONFIG_TEMPLATES_PARAMS['review_by_sku_family_color'] == 1 && (int)$_POST['color'] > 0){
        $cor_id = (int)$_POST['color'];
    }
        
    if((int)$CONFIG_OPTIONS['review_tamanho'] == 1){
        $review_size  = (int)$_POST['review_size'];
        if((int)$CONFIG_OPTIONS['review_largura'] == 1){
            $review_width  = (int)$_POST['review_width'];
        }
    }

    $s = "INSERT registos_avaliacoes (sku_family, familia, data, avaliacao, anonimo, user_id, username, status, titulo, mensagem, lg, pais, mercado, outras1, outras2, cor, tamanho, largura) 
                VALUES ('%s', '%d', NOW(), ".$avaliacao.",'%d','%d','%s',0, '%s', '%s', '".$LG."', '".$_SESSION['_COUNTRY']['id']."', '".$_SESSION['_MARKET']['id']."', '%d', '%d', '%s', '%d', '%d') ";

    $q= sprintf($s, utf8_decode($_POST['sku_family']), utf8_decode($_POST['familia']), $_POST['anonimo'], $_SESSION['EC_USER']['id'], $_SESSION['EC_USER']['nome'],
                safe_value(utf8_decode($_POST['titulo'])), safe_value(utf8_decode($_POST['mensagem'])), $_POST['outras1'], $_POST['outras2'], $cor_id, $review_size, $review_width);

    
    cms_query($q);
    $LAST_ID = cms_insert_id();
    
    if( $_FILES && (int)$LAST_ID > 0 ){
        $allowed    = Array();
        $allowed[]  = 'png';
        $allowed[]  = 'jpg';
        $allowed[]  = 'jpeg';
        $img_count  = 1;

        foreach( $_FILES as $file ){
        
            if( !empty( $file['tmp_name'] ) ){
            
                $num_files = count( $file['tmp_name'] );
                
                for( $i=0; $i<$num_files; $i++){
        
                    if( $file['size'][$i] <= 0 ){
                        continue;
                    }
                    
                    $ext = explode('.', strtolower($file['name'][$i]));
                    $ext = array_pop($ext);
        
                    if( !in_array($ext, $allowed) ){
                        continue;
                    }
        
                    $mimetype = mime_content_type($file['tmp_name'][$i]);
                    if( $mimetype == "text/x-php" || stripos($mimetype,"php") !== false ){
                        continue;
                    }
                    
                    $file_final = _ROOT."/images/review_".$LAST_ID."_i".$img_count.".jpg";
                    unlink($file_final);
                    copy($file['tmp_name'][$i], $file_final);
                                        
                    if (is_dir('../../storage-ha')) {
                        copy($file_final, "../../storage-ha/images/review_".$LAST_ID."_i".$img_count.".jpg");
                    }                    
                    
                    $img_count++;
                
                }
                
            }
            
        }
        
    }

    foreach($_POST['reviews_field'] as $k => $v){
        $s = 'INSERT INTO registos_avaliacoes_lines (avaliacao_id, tipo, valor, peso) VALUES("'.$LAST_ID.'", "'.$k.'", "'.$v.'", "'.$avaliacoes_tipos[$k].'" ) ';                    
        cms_query($s);       
    }
    
    $s = "UPDATE ec_encomendas_lines SET review_made='1' WHERE id_cliente='".$_SESSION['EC_USER']['id']."' AND sku_family='".utf8_decode($_POST['sku_family'])."' $more_where_ec_encomendas_lines";
    cms_query($s);
    
  	
    # CollectAPI ***************************************************************************************************
  	$show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
  	global $collect_api;
  	if( isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1 ){
  		
    		$rating_info = [ 'sku_group' => $_POST['sku_family'], 'rating_given' => $avaliacao*100 ];
    			
    		if( $_POST['anonimo'] == 0 ){
    			 $user_info = $_SESSION['EC_USER'];
    		}else{
    			 $user_info = [];
    		}
    			
    		try{
    		    $collect_api->setEvent(CollectAPI::PRODUCT_RATING, $user_info, $rating_info);
    		}catch(Exception $e){}
  		
  	}
    
    if(is_callable('custom_controller_set_review')) {
        call_user_func('custom_controller_set_review', $LAST_ID, utf8_decode($_POST['sku_family']));
    }  
        
    return serialize(array("0"=>"1"));
        
}

?>
