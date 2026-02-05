<?

require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_emailBuilder.php';


function _emailBuilderEvent($block_id=0, $send_id=0, $user_id=0, $status_id=100, $preview=0){


    if(is_null($block_id)){
        $block_id       = params('block_id');
        $send_id        = params('send_id');
        $user_id        = params('user_id');
        $status_id      = (int)params('status_id');
        $preview        = (int)params('preview');
    }
    
   
   
    if (filter_var($block_id, FILTER_VALIDATE_INT) == false) {
        return serialize(array("0"=>"0"));  
    }
                                            
    if ($send_id>0 && filter_var($send_id, FILTER_VALIDATE_INT) == false) {     
        return serialize(array("0"=>"0"));  
    } 
    
    if ($user_id>0 && filter_var($user_id, FILTER_VALIDATE_INT) == false) {     
        return serialize(array("0"=>"0"));  
    }
    
    if ($preview==0 && (int)$send_id<1) {     
        return serialize(array("0"=>"0"));  
    }
    
    if ($status_id>0 && filter_var($status_id, FILTER_VALIDATE_INT) == false) {     
        return serialize(array("0"=>"0"));  
    }
         
		
		   
		     
         
    global $BLOCO, $BLOCO_LINHAS, $CLIENTE, $PREVIEW, $TBL, $TBL_LINES, $TBL_SENDS, $TBL_STATUS, $TBL_STATUS_ID, $TBL_TYPES, $USER_ID, $QTY_EMAILS, $ENVIO, $fx, $MONGO_DOCUMENTS, $GUARDAR_HTML, $DEFS_EMAIL, $LG, $db_name_cms;


    $TBL            = 'ContentBlocksEmails';
    $TBL_LINES      = 'ContentBlocksLinesEmails';
    $TBL_SENDS      = 'ContentBlocksEmailsSends';
    $TBL_STATUS     = 'ContentBlocksEmailsStatus';
    $TBL_STATUS_ID  = $status_id; 
    $TBL_TYPES      = 'ContentBlocksTypesEmails';
    
    $PREVIEW      = $preview;        
    $USER_ID      = $user_id;
    $QTY_EMAILS   = $qty_emails;
     
    $BLOCO = $BLOCO_LINHAS = $CLIENTE = $ENVIO = $MONGO_DOCUMENTS = array(); 
    
                   
            
    $s = "SELECT * FROM ".$TBL." WHERE id=%u ";     
    $f = sprintf($s, $block_id);
    $q = cms_query($f);
    $BLOCO = cms_fetch_assoc($q);

          
    if((int)$BLOCO['Id']<1) return serialize(array("0"=>"0"));
                   
                   
                             
    if($send_id>0){
    
        $more = 'AND Active=1';
        if($PREVIEW==1) $more = '';
    
        $env_s = "SELECT * FROM $TBL_SENDS WHERE id=%u ".$more;     
        $env_f = sprintf($env_s, $send_id);    
        $env_q = cms_query($env_f);
        $ENVIO = cms_fetch_assoc($env_q);
                                                                                                                   
        if((int)$ENVIO['Id']<1) return serialize(array("0"=>"0"));
    }
               
         
          
     
            
    $bloco_lines_s = "SELECT ln.*, tp.NumberBlocks 
            FROM ".$TBL_LINES." ln
                INNER JOIN ".$TBL_TYPES." tp ON ln.ContentBlocksTypeId=tp.id
            WHERE ContentBlocksId=%u AND (Activo=1 OR Activo_mobile=1)
            ORDER BY ln.ordem ASC, ln.id ASC ";  
            
              
    $bloco_lines_f = sprintf($bloco_lines_s, $BLOCO['Id']);
    $bloco_lines_q = cms_query($bloco_lines_f);        
    $bloco_lines_n = cms_num_rows($bloco_lines_q);                  
       
              
    if((int)$bloco_lines_n<1 && $PREVIEW!=2) {
    
        if($ENVIO['Id']>0){
            $s = "UPDATE ".$TBL_SENDS." SET Status='-100' WHERE id=%u LIMIT 1";
            $f = sprintf($s, $ENVIO['Id']);  
            cms_query($f);
        }
           
        return serialize(array("0"=>"0"));
    }  
    
    
    while($bloco_lines_r = cms_fetch_assoc($bloco_lines_q)){
        $BLOCO_LINHAS[] = $bloco_lines_r;                            
    }
    
       
    
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT, false, _ROOT.'/temp_twig/');
          
    
    $table_style = "`$db_name_cms`.ContentBlocksStylesEmails";      
    $DEFS_EMAIL = call_api_func("get_line_table", $table_style, "Id='1'");
    
             
                        
    if($PREVIEW==0 && $ENVIO['Status']==0){

        $utm_campaign = (trim($ENVIO['Namept'])!='') ? $ENVIO['Namept'] : "Campanha Email Marketing Eventos ".$ENVIO['Id'];  
        cms_query("INSERT INTO `b2c_campanhas_url` (`id`, `fixo`, `nomept`, `nome_parceiro`, `pageviews`, `post_click`, `utm_source`, `utm_medium`, `utm_campaign`) VALUES ( ".(200000+$ENVIO['Id']).", 1, '".cms_escape($ENVIO['Namept'])."', 'Redicom Email Marketing Eventos', 0, 30, 'Redicom Email Marketing Eventos', 'email', '".$utm_campaign."');");
                                          
        $GUARDAR_HTML = 1;
        $HTML = buildEmail();
          
        $HTML = gzcompress($HTML, 9);                
        $HTML = urlencode($HTML);
        
        cms_query("UPDATE $TBL_SENDS SET Status=1, HTML='".$HTML."' WHERE Id='".$ENVIO['Id']."' LIMIT 1");     
        
        return serialize(array("1"=>"1"));            
    }
              
                           
                     
      
                        
    $LISTA_Q = cms_query("SELECT ClientEmail as email, ClientId as id, ClientName as nome, 176 as pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, 0 as sexo, 5 as cliente_tipo, 0 as id_lingua, 0 as rejeita_email_marketing  FROM $TBL_STATUS WHERE Id='".$TBL_STATUS_ID."' AND Status=0 LIMIT 0,100");
                                         
    while($CLIENTE = cms_fetch_assoc($LISTA_Q)){
    
                         
        if(!filter_var($CLIENTE['email'], FILTER_VALIDATE_EMAIL)) {
            cms_query("UPDATE $TBL_STATUS SET Status='-4' WHERE Id='".$TBL_STATUS_ID."' ");        
  	       	continue;
        }
        

        $conta_remove = cms_fetch_assoc(cms_query("SELECT id FROM ec_sms_listas_externas_remove WHERE email='".$CLIENTE['email']."' LIMIT 1"));
				if ($conta_remove['id']>0){
					 	cms_query("UPDATE $TBL_STATUS SET Status='-2' WHERE Id='".$TBL_STATUS_ID."' ");         
  	       	continue;
				}  
           
            
                 
        if($CLIENTE['id']>0){ 
           
            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE id='".$CLIENTE['id']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q); 
                                                           
            if($user_r['id']>0){
            
                if($user_r['rejeita_email_marketing']>0){
                    cms_query("UPDATE $TBL_STATUS SET Status='-6' WHERE Id='".$TBL_STATUS_ID."' ");                
           	        continue;    
                }
                
                $user_r['email'] = $CLIENTE['email'];
                $user_r['var1']  = $CLIENTE['var1'];
                
                $CLIENTE = $user_r;
            }else{
                cms_query("UPDATE $TBL_STATUS SET Status='-5' WHERE Id='".$TBL_STATUS_ID."' ");                
           	    continue;    
            }
        
        }elseif($CLIENTE['id']<0){ # Newsletter
        
            $news_s = "SELECT 0 as id, email, nome, pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, genero_system as sexo, 4 as cliente_tipo, 0 as id_lingua, 0 as rejeita_email_marketing FROM _tnewsletter WHERE id='".abs($CLIENTE['id'])."' AND confirmado='1' AND email!='' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' LIMIT 0,1";
            $news_q = cms_query($news_s);
            $news_r = cms_fetch_assoc($news_q);
                        
            if(trim($news_r['email'])==''){
                cms_query("UPDATE $TBL_STATUS SET Status='-7' WHERE Id='".$TBL_STATUS_ID."' ");                
           	    continue;        
            }
            
            $CLIENTE = $news_r;
            
            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE email='".$CLIENTE['email']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q);
            
            if($user_r['id']>0){
            
                if($user_r['rejeita_email_marketing']>0){
                    cms_query("UPDATE $TBL_STATUS SET Status='-8' WHERE Id='".$TBL_STATUS_ID."' ");                
                    continue;    
                }
                
                $CLIENTE = $user_r;
            }      
            
        }else{

            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE email='".$CLIENTE['email']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q);
            
            if($user_r['id']>0){
            
                if($user_r['rejeita_email_marketing']>0){
                    cms_query("UPDATE $TBL_STATUS SET Status='-3' WHERE Id='".$TBL_STATUS_ID."' ");                      
                    continue;    
                }
                
                $CLIENTE = $user_r;

            }      
        }
          
             
        buildEmailEvent();
        
        saveToAmazonMany();
        saveToMongoManyBackup();  
        
        $MONGO_DOCUMENTS = array();	
           
    }
    
        
    @cms_query("UPDATE $TBL_SENDS SET DateTimeFinish=NOW(),ExternalControlStatus=0 WHERE Id='".$ENVIO['Id']."'");
    
          
    return serialize(array("0"=>"1"));  
    
}   




 
function buildEmailEvent(){
    
    global $fx, $slocation, $cdn_location, $LG, $BLOCO, $BLOCO_LINHAS, $CLIENTE, $PREVIEW, $TBL_STATUS, $TBL_STATUS_ID, $PREVIEW, $ENVIO, $MONGO_DOCUMENTS, $MENUS_EMAIL, $GUARDAR_HTML, $SETTINGS_LOJA, $DEFS_EMAIL, $ATTRS_ESPECIAIS;
             
                  
    ini_client();
    
                 
    if($PREVIEW==0){  
        $utm_campaign = (trim($ENVIO['Namept'])!='') ? $ENVIO['Namept'] : "Campanha Email Marketing ".$ENVIO['Id'];  
        $more_link = '&utm_medium=email&utm_source=Redicom%20Email%20Marketing%20Eventos&utm_campaign='.urlencode($utm_campaign).'&ma=1&cpn='.(200000+$ENVIO['Id']);
    }
    
                          
    $info_block = array();
                       
    foreach($BLOCO_LINHAS as $k => $v){
               
        if((int)$GUARDAR_HTML==0){  # Uusado para guardar o HTML total no fim do envio
                   
            if(trim($v['Segments'])!=''){                          
                $valid = validateUser($v['Segments'], $CLIENTE['tipo']);
                if($valid==false) continue;                                
            }
            
            if(trim($v['Genre'])!='' && $v['Genre']>0 && $CLIENTE['sexo']>0 && $CLIENTE['sexo']!=$v['Genre']){                          
                continue;                                
            }
                       
            if($v['IsClient']==1 && $CLIENTE['cliente_tipo']!=0){
                continue;
            }
        }
             
        $info_block[] = buildBlock($v);   

    }
    
       

    if(count($info_block)==0 && $PREVIEW!=2 ){
        cms_query("UPDATE $TBL_STATUS SET Status='-1' WHERE Id='".$TBL_STATUS_ID."' ");            
    }
    
    
    

    $auto_login = $CLIENTE['id']; 
    
    if($PREVIEW>0 || $GUARDAR_HTML>0){  
        $auto_login = -1;
    }
                               
    $MENUS_EMAIL = $BLOCO['Menus'];          

    $x = get_info_geral_email($LG, $_SESSION['_MARKET'], array('id' => 0), array("id" => $auto_login, "email" => $CLIENTE['email'], "nome" => $CLIENTE['nome']), $more_link);
   
    unset($x['view_online_link']);
        
    $x['path'] = $cdn_location;
     
    $x['content_blocks'] = $info_block;
                                        
      
    

    # Newsletter
    if($CLIENTE['cliente_tipo']==4){
        $x['negar_link'] = $slocation.'/?id=98&leid='.base64_encode('1|||'.$CLIENTE['id'].'|||'.$CLIENTE['email'].'|||'.$CLIENTE['confirmado']);    
    }
    
    # Reforço campanha - cliente de qualquer lado
    if($CLIENTE['cliente_tipo']==5){
        $x['negar_link'] = $slocation.'/?id=98&leid='.base64_encode('5|||'.$CLIENTE['id'].'|||'.$CLIENTE['email'].'|||'.$CLIENTE['confirmado']);    
    }
    
                                                 
    $x['negar_link'] .= '&ji='.$ENVIO['Id'];
    
    
                  
    $x['content_blocks_style'] = $DEFS_EMAIL;        
    
    $x['response']['shop']['CDN'] = $cdn_location;   #caminho das imagens
    
    $x['PREHEADER'] = $ENVIO['PreHeader'.$LG];                   
                                 
    $x['HideHeader'] = $BLOCO["HideHeader"];
    $x['HideFooter'] = $BLOCO["HideFooter"];                                              
    
    
    $_exp                                    = array();
    $_exp['table']                           = "exp";
    $_exp['prefix']                          = "nome";
    $_exp['lang']                            = $LG;
    
                                                                      
    $html = $fx->printTwigTemplate("/plugins/emails_blocks/email.htm", $x, true, $_exp); 
       
      
    
    if($GUARDAR_HTML==1){ #Usado para guardar o HTML quando o envio termina
        
        return $html;
            
    }elseif($PREVIEW==2){
                    
        #sendEmailWithoutBody_Marketing($html, $ENVIO['Subject'.$LG], "sonia.vilela@redicom.pt", "", "", 1, 0);                               
                                        
        echo $html; 
        exit;               
                                                                                                    
    }elseif($PREVIEW==1 || $PREVIEW==3){  
        email_preview($html);
        
        return serialize(array("0"=>"1"));   
    }else{
    
    
        $CLIENTE['nome'] = cms_escape($CLIENTE['nome']);
        
        
    		// Validação
        $x = cms_query("UPDATE $TBL_STATUS SET Status='1' WHERE Id='".$TBL_STATUS_ID."' AND Status=0 ");       
        
        $linhas_afetadas = cms_affected_rows();
        if($linhas_afetadas!=1){
            return;
        }

        
        $htmlBody = utf8_encode($html);
        
                
        $txtBody = preg_replace("/<((?:style)).*>*<\/style>/si", ' ',$htmlBody);
        $txtBody = preg_replace("/<((?:head)).*>*<\/head>/si", ' ',$txtBody);
        
        #$content = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $content);          
        $txtBody = strip_tags($txtBody, '');
        
        $txtBody = str_replace('&nbsp;', ' ', $txtBody);
        $txtBody = preg_replace("/[\r\n]+/", "\n", $txtBody);
        $txtBody = preg_replace("/\s+/", ' ', $txtBody);
        
        
        
        $nome_cliente = explode(' ', ucfirst($CLIENTE['nome']));
        $subject = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $ENVIO['Subject'.$LG]);    
        
        
        
               
        
        $subject = iconv('ISO-8859-1', 'UTF-8', $subject);
        $subject = html_entity_decode($subject, ENT_QUOTES, "UTF-8");

        $preheader = iconv('ISO-8859-1', 'UTF-8', $ENVIO['PreHeader'.$LG]);
        $preheader = html_entity_decode($preheader, ENT_QUOTES, "UTF-8");
        
      
      	$MONGO_DOCUMENTS[] 	= array("domain"		        => $DEFS_EMAIL['domain'],
                                    "siteId"	          => $DEFS_EMAIL['clientid'],  
                                    "fromAddr"	        => $DEFS_EMAIL['fromAddr'], 
                                    "fromName"	        => utf8_encode($DEFS_EMAIL['fromName']), 
                                    "replyToAddr"	      => $DEFS_EMAIL['replyToAddr'], 
                                    "replyToName"	      => utf8_encode($DEFS_EMAIL['replyToName']),
                                    "toAddr"	          => $CLIENTE['email'], 
                                    "toName"	          => "", #Tirou-se porque os acentos não eram aceites e não é necessário    
                                    "subject"	          => $subject, 
                                    "preHeader"	        => $preheader, 
                                    "htmlBody"	        => $htmlBody,
                                    "txtBody"	          => utf8_encode($txtBody),
                                    "sendFlag"	        => 0,
                                    "templateId"	      => $BLOCO['Id'],
                                    "jobId"	            => $ENVIO['Id'],
                                    "internalEmailId"	  => $TBL_STATUS_ID,
                                    "clientId"	        => $CLIENTE['id'],
                                    "clientType"        => $CLIENTE['cliente_tipo'],                                                                        
                                    "demo"              => (int)$ENVIO['Demo'],
                                    "createTimeStamp"   => date('c'),
                                    "sortId"            => (int)date('Ymd'));
                                    
                                    
                
          #sendEmailWithoutBody_Marketing($html, $ENVIO['Subject'.$LG], "sonia.vilela@redicom.pt", "", "", 1, 0);                               
               		                      
    }
                   
    return;
}



  


?>
