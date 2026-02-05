<?


function _emailBuilder($block_id=0, $send_id=0, $user_id=0, $qty_emails=100, $preview=0, $workers_tot=0, $worker=0){


    if(is_null($block_id)){
        $block_id       = params('block_id');
        $send_id        = params('send_id');
        $user_id        = params('user_id');
        $qty_emails     = (int)params('qty_emails');
        $preview        = (int)params('preview');
        $workers_tot    = (int)params('workers_tot');
        $worker         = (int)params('worker');
    }
    
     
    if($qty_emails<1) $qty_emails=100;
    
        
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
         
		
		     
    global $BLOCO, $BLOCO_LINHAS, $CLIENTE, $PREVIEW, $TBL, $TBL_LINES, $TBL_SENDS, $TBL_STATUS, $TBL_TYPES, $USER_ID, $QTY_EMAILS, $ENVIO, $TOTAL_SIZE, $fx, $MONGO_DOCUMENTS, $GUARDAR_HTML, $DEFS_EMAIL, $LG, $db_name_cms;


    $TBL          = 'ContentBlocksEmails';
    $TBL_LINES    = 'ContentBlocksLinesEmails';
    $TBL_SENDS    = 'ContentBlocksEmailsSends';
    $TBL_STATUS   = 'ContentBlocksEmailsStatus';
    $TBL_TYPES    = 'ContentBlocksTypesEmails';
    $TOTAL_SIZE   = 0;
    
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
    
                   
    # Lista de Contactos            
    if( ($ENVIO['Type']==2 || $ENVIO['FinalType']==4) && (int)$ENVIO['ImportList']<1) {
        cms_query("UPDATE $TBL_SENDS SET Status='-200' WHERE Id='".$ENVIO['Id']."' LIMIT 1");
        return serialize(array("0"=>"0"));  
    }
        
            
    # Reforço de campanha
    if( $ENVIO['FinalType']==5 && (int)$ENVIO['CampaignId']<1) {
        cms_query("UPDATE $TBL_SENDS SET Status='-300' WHERE Id='".$ENVIO['Id']."' LIMIT 1");
        return serialize(array("0"=>"0"));  
    }
               
    
    
    
    
                                                       
    $LISTA_F = getSQLUsers($workers_tot, $worker);  
                           
    $LISTA_Q = cms_query($LISTA_F);                                                                      
    $LISTA_N = cms_num_rows($LISTA_Q); 
    
  
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT, false, _ROOT.'/temp_twig/');
          
    
    $table_style = "`$db_name_cms`.ContentBlocksStylesEmails";      
    $DEFS_EMAIL = call_api_func("get_line_table", $table_style, "Id='1'");
               
          
    if((int)$LISTA_N<1){
                         
        if($PREVIEW==0){  
            #Se o envio estava a iniciar e não há clientes então é marcado como erro
            if($ENVIO['Status']==0) cms_query("UPDATE $TBL_SENDS SET Status=-2 WHERE Id='".$ENVIO['Id']."' LIMIT 1");
            
            #Quando o envio termina porque não há mais clientes
            if($ENVIO['Status']==1) {
                
                $sta_s = "SELECT count(*) as enviados FROM $TBL_STATUS WHERE JobId='".$ENVIO['Id']."' ";     
                $sta_f = sprintf($sta_s, $send_id);    
                $sta_q = cms_query($sta_f);
                $COUNT = cms_fetch_assoc($sta_q);
                
                                                                  
                $GUARDAR_HTML = 1;
                $HTML = buildEmail();
                
               
              
                $HTML = gzcompress($HTML, 9);                
                $HTML = urlencode($HTML);
                
                cms_query("UPDATE $TBL_SENDS SET Status=2,Active=0,TotalEmails='".$COUNT['enviados']."',TotalEmailsSend='".$COUNT['enviados']."',HTML='".$HTML."' WHERE Id='".$ENVIO['Id']."' LIMIT 1");
            }
        }
         
        return serialize(array("0"=>"0"));
    }            
          
             
                        
    if($PREVIEW==0 && $ENVIO['Status']==0){
        setTotalEmails();     
        cms_query("UPDATE $TBL_SENDS SET Status=1 WHERE Id='".$ENVIO['Id']."' LIMIT 1");
        
        $utm_campaign = (trim($ENVIO['Namept'])!='') ? $ENVIO['Namept'] : "Campanha Email Marketing ".$ENVIO['Id'];  
        cms_query("INSERT INTO `b2c_campanhas_url` (`id`, `fixo`, `nomept`, `nome_parceiro`, `pageviews`, `post_click`, `utm_source`, `utm_medium`, `utm_campaign`) VALUES ( ".(200000+$ENVIO['Id']).", 1, '".cms_escape($ENVIO['Namept'])."', 'Redicom Email Marketing', 0, 30, 'Redicom Email Marketing', 'email', '".$utm_campaign."');");
          
    }
              
                           
       
                                             
    while($CLIENTE = cms_fetch_assoc($LISTA_Q)){
        
        
        if(!filter_var($CLIENTE['email'], FILTER_VALIDATE_EMAIL)) {
            cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-4')");        
  	       	continue;
        }
        

        $conta_remove = cms_fetch_assoc(cms_query("SELECT id FROM ec_sms_listas_externas_remove WHERE email='".$CLIENTE['email']."' LIMIT 1"));
				if ($PREVIEW==0 && $conta_remove['id']>0){
					 	cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-2')");        
  	       	continue;
				}  
           
                 
        # Lista de clientes externa  
        if($CLIENTE['cliente_tipo']==1 && $CLIENTE['is_cliente']==1 && $CLIENTE['cliente_id']>0){
            
            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE id='".$CLIENTE['cliente_id']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q); 
                                                           
            if($user_r['id']>0){
            
                if($PREVIEW==0 && $user_r['rejeita_email_marketing']>0){
                    cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-6')");        
  	       	        continue;    
                }
                
                $user_r['email'] = $CLIENTE['email'];
                $user_r['var1']  = $CLIENTE['var1'];
                
                $CLIENTE = $user_r;
            }else{
                cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-5')");        
  	       	    continue;    
            }
        }
        
        
        # Reforço de campanha
        if($CLIENTE['cliente_tipo']==5 && $CLIENTE['cliente_id']>0){
            
            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE id='".$CLIENTE['cliente_id']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q); 
                                                           
            if($user_r['id']>0){
            
                if($PREVIEW==0 && $user_r['rejeita_email_marketing']>0){
                    cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-7')");        
  	       	        continue;    
                }
                
                $CLIENTE = $user_r;
            }else{
                cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-8')");        
  	       	    continue;    
            }
        }
        
        
        # Newsletter
        if($CLIENTE['cliente_tipo']==2 || $CLIENTE['cliente_tipo']==4){
        
            $user_s = "SELECT id, email, nome, pais, tipo, tipo_utilizador, lista_preco, deposito, sexo, 0 as cliente_tipo, id_lingua, registed_at, rejeita_email_marketing FROM _tusers WHERE email='".$CLIENTE['email']."' AND sem_registo='0' AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' AND id_user=0 AND pais>0 LIMIT 0,1";      
            $user_q = cms_query($user_s);
            $user_r = cms_fetch_assoc($user_q);
            
            if($user_r['id']>0){
            
                if($PREVIEW==0 && $user_r['rejeita_email_marketing']>0){
                    cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-3')");        
  	       	        continue;    
                }
                
                $CLIENTE = $user_r;
 
            }      
        }
                
         
      
                     
        buildEmail();
        
                        
        if($TOTAL_SIZE>50){  #2023-09-19        
            
            if($DEFS_EMAIL['byAmazon']==1){
                saveToAmazonMany();
                saveToMongoManyBackup();
            }else{
                saveToMongoMany();
                saveToMongoManyBackup();
            }
            
            $MONGO_DOCUMENTS = array();	
            $TOTAL_SIZE = 0;      
        }    
    }
    
       
            
    
    if($DEFS_EMAIL['byAmazon']==1){
        saveToAmazonMany();    
        saveToMongoManyBackup();    
    }else{
        saveToMongoMany();
        saveToMongoManyBackup();
    }
    
    
    @cms_query("UPDATE $TBL_SENDS SET DateTimeFinish=NOW(),ExternalControlStatus=0 WHERE Id='".$ENVIO['Id']."'");
    
    
//     $tempo_carregamento = mktime() - $_SERVER['REQUEST_TIME'];
//     echo $tempo_carregamento;
//     exit;
      
    return serialize(array("0"=>"1"));  

}   




function saveToAmazonMany(){

    global $MONGO_DOCUMENTS;
    
    if(count($MONGO_DOCUMENTS)<1) return;   
    
    $headers = ['Content-Type: application/json'];
    
    	 						
    $request 	= array("dataSource" => "Cluster0", 
     									"database"	 => "email_marketing",  
     									"collection" => "queue", 
     									"documents"	 =>	$MONGO_DOCUMENTS);		
    									 												
                                     
    $data_string 	= json_encode($request, JSON_UNESCAPED_UNICODE);
         
    		 
    $ch = curl_init("https://private-services.redicom.net/aws-ses/insertMany.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($ch);
    #var_dump($return);

    curl_close($ch);
 
    return;
}



function saveToMongoMany(){

    global $MONGO_DOCUMENTS;
    
    if(count($MONGO_DOCUMENTS)<1) return;
    
    $headers   = ['Content-Type: application/json','Access-Control-Request-Headers: *','api-key: FJMMNS3pu7Fp4ExQTzpVUpQa6jzzkRtQMhiL6pVSgwOzJvzkP92LyhqTQvT35cOT'];    
    
    	 						
    $request 	= array("dataSource" => "Cluster0", 
     									"database"	 => "email_marketing",  
     									"collection" => "queue", 
     									"documents"	 =>	$MONGO_DOCUMENTS);		
    									 												
                                     
    $data_string 	= json_encode($request, JSON_UNESCAPED_UNICODE);
         
    		 
    $ch = curl_init("https://data.mongodb-api.com/app/data-hsqlh/endpoint/data/v1/action/insertMany");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($ch);
    #var_dump($return);
    
    curl_close($ch);
 
    return;
			
}


function saveToMongoManyBackup(){

    global $MONGO_DOCUMENTS;
    
    if(count($MONGO_DOCUMENTS)<1) return;
    
    $headers   = ['Content-Type: application/json'];    
           
    $data_string 	= json_encode($MONGO_DOCUMENTS, JSON_UNESCAPED_UNICODE);
    		 
    $ch = curl_init("https://mongo-server.redicom.net/email-repository/index.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($ch);
    curl_close($ch);
    return;
			
}



function setTotalEmails(){

    global $ENVIO, $TBL_SENDS;
           
           
   
                   
    # Lista de Contactos      
    if( ($ENVIO['Type']==2 && $ENVIO['FinalType']==0 ) || $ENVIO['FinalType']==4){
        
               
        $s = "SELECT COUNT(u.id) as total
                FROM ec_sms_listas_externas_contactos u
                WHERE u.lista=%u     
                    AND u.id>0
                    AND email!=''
                    AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                    AND rejeita_email_marketing='0'"; 
        
        $f = sprintf($s, $ENVIO['ImportList']);         
      
        $q = cms_query($f);
        $r = cms_fetch_assoc($q);
  
        cms_query("UPDATE $TBL_SENDS SET TotalEmails=".$r['total']." WHERE Id='".$ENVIO['Id']."' LIMIT 1");                 
 
        return;
    }
    
    $more_where = '';
    
    
    
    # Descontinuado
    # Newsletter
    if($ENVIO['Type']==3 && $ENVIO['FinalType']==0){
        
        if(trim($ENVIO['Countries'])!='') {
            $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
        }
                
        $s = "SELECT COUNT(u.id) as total
                FROM _tnewsletter u       
                WHERE u.id>0
                    AND email!=''
                    AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                    AND confirmado='1'
                    $more_where"; 
        
        $q = cms_query($s);
        $r = cms_fetch_assoc($q);
  
        cms_query("UPDATE $TBL_SENDS SET TotalEmails=".$r['total']." WHERE Id='".$ENVIO['Id']."' LIMIT 1");                 
        
        return;    
    }
    
    
    
    # Reforço de Campanha
    if( (int)$ENVIO['FinalType']==5){
        
        $s = "SELECT COUNT(u.id) as total
              FROM ContentBlocksEmailsStatus u        
              WHERE u.`View`=0 AND u.JobId='".$ENVIO['CampaignId']."' AND u.Status=0 ";
                             
        $q = cms_query($s);
        $r = cms_fetch_assoc($q);
        
        cms_query("UPDATE $TBL_SENDS SET TotalEmails=".$r['total']." WHERE Id='".$ENVIO['Id']."' LIMIT 1");      
  
        return;
    
    }  
              
      
    # CLientes + Newsletter
    if( ( ($ENVIO['Type']==4 && $ENVIO['FinalType']==0) || $ENVIO['Type']==0) && (int)$USER_ID==0){
           
           
        if(trim($ENVIO['Countries'])!='') {
            $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
        }
        
        $more_where_only_users = '';
        if(trim($ENVIO['Segments'])!='') {
            $segs = explode(',', $ENVIO['Segments']);
                     
            $more_where_only_users .= " AND ( CONCAT(',',u.tipo,',') LIKE '%%,".implode(",%%' OR CONCAT(',',u.tipo,',') LIKE '%%,", $segs).",%%' )";
        }
        
        
        if(trim($ENVIO['SegmentsNot'])!='') {
            $segs = explode(',', $ENVIO['SegmentsNot']);
                     
            $more_where_only_users .= " AND ( CONCAT(',',u.tipo,',') NOT LIKE '%%,".implode(",%%' AND CONCAT(',',u.tipo,',') NOT LIKE '%%,", $segs).",%%' )";
        }
        
        
        
        $more_where_only_news = '';   
        if((int)$ENVIO['Genre']>0) { 
            $more_where_only_users .= " AND ( u.sexo=0 OR u.sexo='".$ENVIO['Genre']."' )";
            $more_where_only_news  .= " AND ( u.genero_system=0 OR u.genero_system='".$ENVIO['Genre']."' )";
        }
        
        if(trim($ENVIO['Cities'])!='') { 
            $cidades = explode(";", trim($ENVIO['Cities']));
            $more_where_only_users .= " AND ( u.cidade LIKE '%".implode("%' OR u.cidade LIKE '%", $cidades)."%' )";                                 
        }
        
              
               
        if( ($ENVIO['Type']==4 && $ENVIO['WithSubscribers']==1) || ($ENVIO['Type']==0 && $ENVIO['FinalType']==1)){   
         
            $s = "SELECT id 
                    FROM
                    ((SELECT id,email
                            FROM _tnewsletter u            
                            WHERE u.id>0
                                AND email!=''
                                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                                AND confirmado='1'
                                $more_where $more_where_only_news)
                    UNION ALL
                    (SELECT id,email
                      FROM _tusers u               
                      WHERE u.id>0
                          AND sem_registo='0'
                          AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                          AND id_user=0 
                          AND pais>0
                          AND activo=1 
                          AND rejeita_email_marketing='0'
                          $more_where $more_where_only_users )) as tbl
                    GROUP BY tbl.email"; 
                    
            
                    
        }elseif($ENVIO['WithSubscribers']==0 || $ENVIO['FinalType']==2){    
                
            $s = "SELECT id
                  FROM _tusers u          
                  WHERE u.id>0
                      AND sem_registo='0'
                      AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                      AND id_user=0 
                      AND pais>0
                      AND activo=1 
                      AND rejeita_email_marketing='0'
                      $more_where $more_where_only_users ";
                                          
                         
        }elseif($ENVIO['WithSubscribers']==2 || $ENVIO['FinalType']==3){ 
           
            $s = "SELECT id
                      FROM _tnewsletter u          
                      WHERE u.id>0
                            AND email!=''
                            AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                            AND confirmado='1'
                          $more_where $more_where_only_news";                
        }         
       

       
        $q = cms_query($s);
        $r = cms_num_rows($q);
  
        cms_query("UPDATE $TBL_SENDS SET TotalEmails=".$r." WHERE Id='".$ENVIO['Id']."' LIMIT 1");                 
      
        return;    
    }
        


    # Descontinuado
    
    if($ENVIO['Type']==1){
    
        if(trim($ENVIO['Segments'])!='') {
            $segs = explode(',', $ENVIO['Segments']);
          
            $more_where .= " AND ( CONCAT(',',u.tipo,',') LIKE '%%,".implode(",%%' OR CONCAT(',',u.tipo,',') LIKE '%%,", $segs).",%%' )";
        }
        
        if(trim($ENVIO['SegmentsNot'])!='') {
            $segs = explode(',', $ENVIO['SegmentsNot']);
                     
            $more_where .= " AND ( CONCAT(',',u.tipo,',') NOT LIKE '%%,".implode(",%%' AND CONCAT(',',u.tipo,',') NOT LIKE '%%,", $segs).",%%' )";
        }
    }           
    
    if(trim($ENVIO['Countries'])!='') {
        $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
    }
    
    $s = "SELECT COUNT(u.id) as total
            FROM _tusers u          
            WHERE u.id>0
                AND sem_registo='0'
                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                AND id_user=0 
                AND pais>0
                AND activo=1 
                AND rejeita_email_marketing='0'
                $more_where";
              

    $q = cms_query($s);
    $r = cms_fetch_assoc($q);
    
    cms_query("UPDATE $TBL_SENDS SET TotalEmails=".(int)$r['total']." WHERE Id='".$ENVIO['Id']."' LIMIT 1");
    
    
    return;
}



function getSQLUsers($workers_tot=0, $worker=0){
                      
    global $ENVIO, $USER_ID, $QTY_EMAILS, $TBL_STATUS;
    

    $more_where_par_impar = "";
    if($workers_tot>0) $more_where_par_impar = " AND MOD(u.id, $workers_tot) = $worker ";

     
                 
    # Lista de Contactos Externa
    if(( ($ENVIO['Type']==2 && $ENVIO['FinalType']==0) || $ENVIO['FinalType']==4) && (int)$USER_ID==0){
        
        $lista_cont = call_api_func("get_line_table","ec_sms_listas_externas", "id='".$ENVIO['ImportList']."'");
        
        $mercado = call_api_func("get_line_table", "ec_mercado", "CONCAT(',',pais,',') LIKE ('%,".$lista_cont['pais'].",%') ");
              
        $s = "SELECT 0 as id, u.email, u.nome, ".$lista_cont['pais']." as pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, 0 as sexo, 1 as cliente_tipo, u.is_cliente, u.cliente_id, u.lista, 0 as id_lingua, u.variavel_1 as var1
            FROM ec_sms_listas_externas_contactos u
                LEFT JOIN ".$TBL_STATUS." st ON u.email=st.ClientEmail AND st.JobId=%u                
            WHERE st.id IS NULL
                AND u.lista=%u     
                AND email!=''
                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                AND rejeita_email_marketing='0'
                $more_where_par_impar    
            LIMIT 0,".$QTY_EMAILS; 
        
        $f = sprintf($s, $ENVIO['Id'], $ENVIO['ImportList']);
         
        return $f;    
    }
    
    

    # Descontinuado
    # Newsletter
    if($ENVIO['Type']==3 && $ENVIO['FinalType']==0 && (int)$USER_ID==0){
        
        $more_where = '';      
        if(trim($ENVIO['Countries'])!='') {
            $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
        }
                
        $s = "SELECT 0 as id, u.email, u.nome, u.pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, genero_system as sexo, 2 as cliente_tipo, u.confirmado, 0 as id_lingua
            FROM _tnewsletter u
                LEFT JOIN ".$TBL_STATUS." st ON u.email=st.ClientEmail AND st.JobId=%u                
            WHERE st.id IS NULL 
                AND email!=''
                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                AND confirmado='1'
                $more_where $more_where_par_impar
            LIMIT 0,".$QTY_EMAILS; 
        
        $f = sprintf($s, $ENVIO['Id']); 
      
        return $f;    
    }
    
    
    
    # Reforço de Campanha
    if( (int)$ENVIO['FinalType']==5){
        
        $s = "SELECT 0 as id, u.ClientEmail as email, u.ClientName as nome, 0 as pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, 0 as sexo, 5 as cliente_tipo, 0 as is_cliente, u.ClientId as cliente_id, 0 as lista, 0 as id_lingua
            FROM ContentBlocksEmailsStatus u 
                LEFT JOIN ".$TBL_STATUS." st ON u.ClientEmail=st.ClientEmail AND st.JobId=%u             
            WHERE st.id IS NULL
                AND u.`View`=0
                AND u.JobId='".$ENVIO['CampaignId']."'     
                AND u.Status=0   
                $more_where_par_impar            
            LIMIT 0,".$QTY_EMAILS;
               
                               
        $f = sprintf($s, $ENVIO['Id']); 
  
        return $f;
    
    }     
    
    
    
    # CLientes + Newsletter
    if( ( ($ENVIO['Type']==4 && $ENVIO['FinalType']==0) || $ENVIO['Type']==0)  && (int)$USER_ID==0){
        
        $more_where = '';     
        if(trim($ENVIO['Countries'])!='') {
            $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
        }
  
  
        if( (int)$ENVIO['WithSubscribers']!=2 && (int)$ENVIO['FinalType']!=3){
        
            $more_where_only_users = '';
            if(trim($ENVIO['Segments'])!='') {
                $segs = explode(',', $ENVIO['Segments']);
              
                $more_where_only_users .= " AND ( CONCAT(',',u.tipo,',') LIKE '%%,".implode(",%%' OR CONCAT(',',u.tipo,',') LIKE '%%,", $segs).",%%' )";
            }
            
            if(trim($ENVIO['SegmentsNot'])!='') {
                $segs = explode(',', $ENVIO['SegmentsNot']);
                         
                $more_where_only_users .= " AND ( CONCAT(',',u.tipo,',') NOT LIKE '%%,".implode(",%%' AND CONCAT(',',u.tipo,',') NOT LIKE '%%,", $segs).",%%' )";
            }
            
            if((int)$ENVIO['Genre']>0) { 
                $more_where_only_users .= " AND ( sexo=0 OR sexo='".$ENVIO['Genre']."' )";
            }
            
            if(trim($ENVIO['Cities'])!='') { 
                $cidades = explode(";", trim($ENVIO['Cities']));
                $more_where_only_users .= " AND ( u.cidade LIKE '%".implode("%' OR u.cidade LIKE '%", $cidades)."%' )";                                 
            }
            
                
          
                
            $s = "SELECT u.id, u.email, u.nome, u.pais, u.tipo, u.tipo_utilizador, u.lista_preco, u.deposito, u.sexo, 0 as cliente_tipo, u.id_lingua, u.registed_at
                FROM _tusers u
                    LEFT JOIN ".$TBL_STATUS." st ON u.email=st.ClientEmail AND st.JobId='".$ENVIO['Id']."'                
                WHERE st.id IS NULL
                    AND sem_registo='0'
                    AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                    AND id_user=0 
                    AND pais>0
                    AND u.id>0
                    AND u.activo=1 
                    AND rejeita_email_marketing='0'
                    $more_where $more_where_only_users $more_where_par_impar
                ORDER BY FIELD(u.pais, 176, 247, 253) DESC    
                LIMIT 0,".$QTY_EMAILS;
                   
                                   
            $q = cms_query($s);
            $n = cms_num_rows($q);
            
            if($n>0 || ( $ENVIO['Type']==4 && (int)$ENVIO['WithSubscribers']==0) || ($ENVIO['Type']==0 && (int)$ENVIO['FinalType']==2) ) return $s;
        
        }   
       
        
        
        $more_where_only_news = '';   
        if((int)$ENVIO['Genre']>0) { 
            $more_where_only_news  .= " AND ( genero_system=0 OR genero_system='".$ENVIO['Genre']."' )";
        }
        
        $s = "SELECT 0 as id, u.email, u.nome, u.pais, '' as tipo, 0 as tipo_utilizador, 0 as lista_preco, '' as deposito, genero_system as sexo, 4 as cliente_tipo, u.confirmado, 0 as id_lingua
            FROM _tnewsletter u
                LEFT JOIN ".$TBL_STATUS." st ON u.email=st.ClientEmail AND st.JobId=%u                
            WHERE st.id IS NULL 
                AND email!=''
                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                AND confirmado='1'
                $more_where $more_where_only_news $more_where_par_impar
            LIMIT 0,".$QTY_EMAILS; 
        
        $f = sprintf($s, $ENVIO['Id']); 
 
        return $f;    
    }
    
    
    # Descontinuado     
    
    $more_where = " AND rejeita_email_marketing='0' ";     
                
    if($USER_ID>0) $more_where = "AND u.id='".$USER_ID."'";

    if($ENVIO['Type']==1){
        
        if(trim($ENVIO['Segments'])!='') {
            $segs = explode(',', $ENVIO['Segments']);
      
            $more_where .= " AND ( CONCAT(',',u.tipo,',') LIKE '%%,".implode(",%%' OR CONCAT(',',u.tipo,',') LIKE '%%,", $segs).",%%' )";
        }
        
        if(trim($ENVIO['SegmentsNot'])!='') {
            $segs = explode(',', $ENVIO['SegmentsNot']);
                     
            $more_where .= " AND ( CONCAT(',',u.tipo,',') NOT LIKE '%%,".implode(",%%' AND CONCAT(',',u.tipo,',') NOT LIKE '%%,", $segs).",%%' )";
        }
    }
    
    
    if(trim($ENVIO['Countries'])!='') {
        $more_where .= "AND u.pais IN (".$ENVIO['Countries'].") ";
    }
    
    $s = "SELECT u.id, u.email, u.nome, u.pais, u.tipo, u.tipo_utilizador, u.lista_preco, u.deposito, u.sexo, 0 as cliente_tipo, u.id_lingua, u.registed_at
            FROM _tusers u
                LEFT JOIN ".$TBL_STATUS." st ON u.email=st.ClientEmail AND st.JobId=%u                
            WHERE st.id IS NULL
                AND sem_registo='0'
                AND email REGEXP '^[^@]+@[^@]+\.[^@]{2,}$' 
                AND id_user=0 
                AND pais>0                         
                AND u.id>0
                AND u.activo=1 
                $more_where $more_where_par_impar 
            ORDER BY FIELD(u.pais, 176, 247, 253) DESC        
            LIMIT 0,".$QTY_EMAILS;
              
    $f = sprintf($s, $ENVIO['Id']); 
    
    return $f;
}      

 
function buildEmail(){
    
    global $fx, $slocation, $cdn_location, $LG, $BLOCO, $BLOCO_LINHAS, $CLIENTE, $PREVIEW, $TBL_STATUS, $PREVIEW, $ENVIO, $TOTAL_SIZE, $MONGO_DOCUMENTS, $MENUS_EMAIL, $GUARDAR_HTML, $SETTINGS_LOJA, $DEFS_EMAIL, $ATTRS_ESPECIAIS;
             
                  
    ini_client();
    
                 
    if($PREVIEW==0){  
        $utm_campaign = (trim($ENVIO['Namept'])!='') ? $ENVIO['Namept'] : "Campanha Email Marketing ".$ENVIO['Id'];  
        $more_link = '&utm_medium=email&utm_source=Redicom%20Email%20Marketing&utm_campaign='.urlencode($utm_campaign).'&ma=1&cpn='.(200000+$ENVIO['Id']);
    }
    
      
    
    if($CLIENTE['cliente_tipo']==0){
    
          $q = cms_query("SELECT campo FROM _tusers_special_attributes");
          while($row = cms_fetch_assoc($q)){
              $CLIENTE[$row['campo']] = '';
              $ATTRS_ESPECIAIS[] = $row['campo'];
          }

          $q = cms_query("SELECT * FROM _tusers_special_attributes_lines WHERE id_cliente='".$CLIENTE['id']."' ");
          while($row = cms_fetch_assoc($q)){
              if(trim($row['valor'])=='' || trim($row['campo'])=='') continue;
              
              $CLIENTE['ATTR_ESP_'.$row['campo']] = $row['valor'];
          }
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
        cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientName, ClientEmail, JobId, ContentBlocksEmailsId, Date, Status) VALUES ('".$CLIENTE['id']."', '".cms_escape($CLIENTE['nome'])."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW(), '-1')");            
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
                                        
      
    
                                        
    # Lista externa                                          
    if($CLIENTE['cliente_tipo']==1 && $CLIENTE['is_cliente']!=1){
        $x['negar_link'] = $slocation.'/?id=98&leid='.base64_encode('0|||'.$CLIENTE['id'].'|||'.$CLIENTE['email'].'|||'.$CLIENTE['lista']);    
    }    
    
    # Newsletter
    if($CLIENTE['cliente_tipo']==2 || $CLIENTE['cliente_tipo']==4){
        $x['negar_link'] = $slocation.'/?id=98&leid='.base64_encode('1|||'.$CLIENTE['id'].'|||'.$CLIENTE['email'].'|||'.$CLIENTE['confirmado']);    
    }
    
    # Reforço campanha
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
        $x = cms_query("INSERT INTO $TBL_STATUS (ClientId, ClientName, ClientEmail, JobId, ContentBlocksEmailsId, Date) VALUES ('".$CLIENTE['id']."', '".$CLIENTE['nome']."', '".cms_escape($CLIENTE['email'])."', '".$ENVIO['Id']."', '".$BLOCO['Id']."', NOW())");
        
        if ((int)$x == 0)
        	return;
				
				$id_email_internal = cms_insert_id();
        
        $htmlBody = utf8_encode($html);
        
        $TOTAL_SIZE++;
        
        $txtBody = preg_replace("/<((?:style)).*>*<\/style>/si", ' ',$htmlBody);
        $txtBody = preg_replace("/<((?:head)).*>*<\/head>/si", ' ',$txtBody);
        
        #$content = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $content);          
        $txtBody = strip_tags($txtBody, '');
        
        $txtBody = str_replace('&nbsp;', ' ', $txtBody);
        $txtBody = preg_replace("/[\r\n]+/", "\n", $txtBody);
        $txtBody = preg_replace("/\s+/", ' ', $txtBody);
        
        
        
        $nome_cliente = explode(' ', ucfirst($CLIENTE['nome']));
        $subject = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $ENVIO['Subject'.$LG]);    
        
        
        #2023-02-06 - Usado pela Glammy
        
        if(strpos($subject, "{PONTOS_CLIENTE}") !== false){
            
            $sem_substituicao = 1;
            
            if($_SESSION['_MARKET']["usePoints"]==1 && $CLIENTE['cliente_tipo']==0){
                        
                global $userID;
                  
                $userID = $CLIENTE["id"];       
                $res = get_points_history();
                
                $total_pontos = (int)$res['total_points'];
                 
                if($total_pontos>0){
            
                    if((int)$SETTINGS_LOJA['pontos']['campo_6']>0){
                        #$string = $_SESSION['_MOEDA']['prefixo'].number_format($total_pontos*$_SESSION['_MOEDA']["valuePoint"], $_SESSION['_MOEDA']['decimais'], $_SESSION['_MOEDA']['casa_decimal'], $_SESSION['_MOEDA']['casa_milhares']).$_SESSION['_MOEDA']['sufixo'];
                        $string = number_format($total_pontos*$_SESSION['_MOEDA']["valuePoint"], $_SESSION['_MOEDA']['decimais'], $_SESSION['_MOEDA']['casa_decimal'], $_SESSION['_MOEDA']['casa_milhares']).''.$_SESSION['_MOEDA']['abreviatura'];
                                                                                                
                    }else{
                        $string = $total_pontos.estr2(350);
                    }
                       
                    $subject = str_replace("{PONTOS_CLIENTE}", $string, $subject);  
                    
                    $sem_substituicao = 0;
                }  
                
            }
            
            if($sem_substituicao==1){
                $subject = str_replace("{PONTOS_CLIENTE}", "0", $subject);                   
            }    
        }
        
        
               
        
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
                                    "internalEmailId"	  => $id_email_internal,
                                    "clientId"	        => $CLIENTE['id'],
                                    "clientType"        => $CLIENTE['cliente_tipo'],                                                                        
                                    "demo"              => (int)$ENVIO['Demo'],
                                    "createTimeStamp"   => date('c'),
                                    "sortId"            => (int)date('Ymd'));
                                    
                                    
                
          #sendEmailWithoutBody_Marketing($html, $ENVIO['Subject'.$LG], "sonia.vilela@redicom.pt", "", "", 1, 0);                               
               		                      
    }
                   
    return;
}



  
function ini_client(){
    
    global $COUNTRY, $MARKET, $MOEDA, $LG, $eComm, $userID, $B2B, $CLIENTE;
          
    unset($_SESSION['EC_USER']);            
      
    $userID                                   = $CLIENTE['id'];   
    $_SESSION['EC_USER']['tipo']              = $CLIENTE['tipo'];  
    $_SESSION['EC_USER']['tipo_utilizador']   = $CLIENTE['tipo_utilizador'];  
    $_SESSION["EC_USER"]["sem_registo"]       = 0;
    $_SESSION['EC_USER']['registed_at']       = $CLIENTE['registed_at']; 
                 
    
    $segmentos = explode(',', $_SESSION["EC_USER"]['tipo']);    
    asort($segmentos);
    foreach($segmentos as $k => $v){
        if($v>499){
            unset($segmentos[$k]);
        }
    }
    $_SESSION['segmentos'] = implode(",", $segmentos);
    
    
    apiUserDiscountsAndPrices($CLIENTE["id"]);
    
    
    if((int)$CLIENTE['pais']==0) $CLIENTE['pais'] = 176;
        
      
    $_SESSION['_COUNTRY'] = $eComm->countryInfo($CLIENTE['pais']);
    $_SESSION['_MARKET']  = $eComm->marketInfo($_SESSION['_COUNTRY']['id']);
    $_SESSION['_MOEDA']   = $eComm->moedaInfo($_SESSION['_MARKET']['moeda']);   
    
    if($_SESSION['_MARKET']['entidade_faturacao']>0){    
        $entidade_r = $eComm->getLineTable('ec_invoice_companies', "id='".$_SESSION['_MARKET']['entidade_faturacao']."'");  
    }  
          
    if((int)$B2B==0 && $_SESSION['EC_USER']['tipo_utilizador']==1 && $_SESSION['_MARKET']['lista_exclusiva1']>0 && ($entidade_r['id']>0 && $_SESSION['EC_USER']['pais']!=$entidade_r['country'])){
        $_SESSION['_MARKET']['lista_preco'] = $_SESSION['_MARKET']['lista_exclusiva1'];
    }else if( (int)$B2B==1 && $_SESSION['EC_USER']['tipo_utilizador']>0 && $_SESSION['_MARKET']['lista_exclusiva'.$_SESSION['EC_USER']['tipo_utilizador']]>0){
        $_SESSION['_MARKET']['lista_preco'] = $_SESSION['_MARKET']['lista_exclusiva'.$_SESSION['EC_USER']['tipo_utilizador']];
    }
    
    
    if((int)$CLIENTE['lista_preco']>0) $_SESSION['_MARKET']["lista_preco"] = $CLIENTE['lista_preco'];
    
    if($CLIENTE['deposito']!='') $_SESSION['_MARKET']["deposito"] = $CLIENTE['deposito'];
    
    $COUNTRY  = $_SESSION['_COUNTRY'];
    $MARKET   = $_SESSION['_MARKET'];
    $MOEDA    = $_SESSION['_MOEDA'];
    
    
    $id_idioma = $COUNTRY["idioma"];
    if($CLIENTE['id_lingua']>0){
        $id_idioma = $CLIENTE['id_lingua'];
    }        
  
    
    $LANGUAGE = call_api_func("get_line_table","ec_language", "id='".$id_idioma."'");
    
    
    $url_lang = strtolower($LANGUAGE["code"]);        
    if(trim($url_lang)=='' || strlen($url_lang)!=2) {
        $url_lang = $LANGUAGE["code"] = 'pt';
    }
    
    if($LANGUAGE["code"]=='es') $LANGUAGE["code"]='sp';
    elseif($LANGUAGE["code"]=='en') $LANGUAGE["code"]='gb';
    
    $LG = $_SESSION['LG'] = $LANGUAGE["code"];
    
} 


     
function validateUser($segments, $user_seg){
        
    if(trim($user_seg)=='') return false;
    
    $arr_segments = explode(',', $segments);
    
    $arr_user_segments = explode(',', $user_seg);

    $intersecao = array_intersect($arr_segments, $arr_user_segments);

    if(count($intersecao)>0) return true;

    return false;        
      
}


    
function buildBlock($row_block){
 
    global $LG, $EMAILS_MARKETING_LAST_UNIT, $CLIENTE, $ENVIO, $ATTRS_ESPECIAIS;
    
    
    $EMAILS_MARKETING_LAST_UNIT = false;
    
    
                    
    $padding = array(
        "top"     => $row_block["PaddingTop"],
        "right"   => $row_block["PaddingRight"],
        "bottom"  => $row_block["PaddingBottom"],
        "left"    => $row_block["PaddingLeft"]
    );
    
    $padding_moblie = array(
        "top"     => $row_block["PaddingTop_mobile"],
        "right"   => $row_block["PaddingRight_mobile"],
        "bottom"  => $row_block["PaddingBottom_mobile"],
        "left"    => $row_block["PaddingLeft_mobile"]
    );
                   
                   
    
    $nome_cliente = explode(' ', ucfirst($CLIENTE['nome']));              
                                  
                                  
    $i = 0;                                               
    $y = 1;                                              
    while($i<$row_block["NumberBlocks"]){                               
                   
        $background = "";
        if($row_block["BackgroundColor".$y]!="") $background = "#".$row_block["BackgroundColor".$y];
           
        if ($row_block["ContentBlocksType".$y]==16){ # Countdown
        
            $row_block["Blocks_tit_".$y.$LG] = $row_block["Blocks_tit_".$y.'pt'];
            $row_block["Blocks_TextButton".$y.$LG] = $row_block["Blocks_TextButton".$y.'pt'];
            $row_block["Blocks_2TextButton".$y.$LG] = $row_block["Blocks_2TextButton".$y.'pt'];
            
        }elseif ($row_block["ContentBlocksType".$y]==9){ # Produtos
                           
            if($row_block['Catalog'.$y]<1 && (int)$row_block['MostrarControl'.$y] == 0) {
                $i++;$y++;
                continue;
            }
                       
            global $CONFIG_LISTAGEM_QTD;
            $temp                = $CONFIG_LISTAGEM_QTD;
            $CONFIG_LISTAGEM_QTD = $row_block["ProductsNumber"]+10; 
            
            $validar_stock = 1;                                                 
                                                
            if($row_block['MostrarControl'.$y] == 1){
            
                $validar_stock = 0;  
                
                $products = array();
                
                $sql_prods = "SELECT r.id 
                                FROM ContentBlocksProductsIncludeEmails cb 
                                    INNER JOIN registos r ON cb.SkuGroup = r.sku_group 
                                WHERE ContentBlocksId='".$row_block["id"]."' AND ContentBlocksTab='$y' 
                                GROUP BY r.sku_group
                                ORDER BY cb.id ASC";
          
                $res_prods = cms_query($sql_prods);
                while($row_prods = cms_fetch_assoc($res_prods)){ 
                                           
                    #$temp_prod = get_product($row_prods["id"]);
                    $temp_prod = get_product($row_prods["id"], "", 5, 0, 0, 0, 0, 0, $LG);    
                    
                    if((int)$temp_prod["id"] > 0 ) $products['PRODUCTS'][] = $temp_prod;
                    
                    if(count($products['PRODUCTS']) == $row_block["ProductsNumber"] ) break;
                }

            }else{
                unset($_SESSION['filter_active'][$row_block['Catalog'.$y]]);
                $products = get_products($row_block['Catalog'.$y], 0, '', 1, 0, 0, 1);
            }
                        
            if(count($products['PRODUCTS'])==0){
                $i++;$y++;
                continue;    
            } 
                          
                                              
            $CONFIG_LISTAGEM_QTD = $temp;
            $produtos = array();              
                            
            #set_include_path(_ROOT);
                
            $more_link = link_get_codigo();

                                                                          
            foreach($products['PRODUCTS'] as $k => $PROD){
          
                if(count($produtos)>=$row_block["ProductsNumber"]) break;
                
                if((int)$PROD['id']<1) {
                    continue;
                }
                                
                if(strpos($PROD['selected_variant']['image']['source'], 'no-image') !== false) {
                    continue;
                }
                
                if(trim($PROD['price']['value'])=='') {
                    continue;
                }
                                                                                                                                    
                get_layout_prod($produtos, $PROD, $validar_stock, 1, $more_link, 1);


                # Bloco Email 23 - Ocultar preços dos produtos
                if($row_block['ContentBlocksTypeId'] == 23 && (int)$row_block['Blocks_subtit_2pt'] > 0) {
                    foreach ($produtos as $key => $value) {
                        $produtos[$key]['preco']            = "";
                        $produtos[$key]['preco_anterior']   = "";
                        $produtos[$key]['desconto']         = "";
                    }
                }
            }     
           
            if(count($produtos)==0){
                $i++;$y++;
                continue;    
            }                   
           
            buildLink($row_block["LinkButton".$y], $row_block['id']);   
                       
                
            $tabs[] = array(  "type_block"        => $row_block["ContentBlocksType".$y],
                            "productsNumber"    => $row_block["ProductsNumber"],
                            "title"             => $row_block["Blocks_tit_".$y.$LG],
                            "products"          => $produtos,
                            "button"            => $row_block["TextButton".$y.$LG],
                            "button_link"       => $row_block["LinkButton".$y],
                            "button_margin_top" => $row_block["MarginTopButton".$y] );
               
             
            $i++;$y++;
            continue;
            
        }
            
            
          
          
        $arr_img = "";
        if($row_block["ContentBlocksType".$y]==6){
            $cam_img = "images/em_block".$y."_".$row_block['id'].".gif";         
            if(file_exists($_SERVER['DOCUMENT_ROOT']."/images/em_block".$y."_".$row_block['id']."_".$LG.".gif")){
                $cam_img = "images/em_block".$y."_".$row_block['id']."_".$LG.".gif";                    
            }  
                                 
        } elseif ($row_block["ContentBlocksType".$y]==8 && file_exists($_SERVER['DOCUMENT_ROOT']."/images/em_block".$y."_".$row_block['id'].".gif") ){
            $cam_img = "images/em_block".$y."_".$row_block['id'].".gif";                
        } elseif( $row_block["ContentBlocksType".$y]==8 ){
            $cam_img = "images/em_block".$y."_".$row_block['id'].".jpg";
        } else {
        
            $cam_img = "images/em_block".$y."_".$row_block['id']."_".$LG.".jpg";
            if(!file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam_img)){
                $cam_img = "images/em_block".$y."_".$row_block['id'].".jpg";
            }                   
        }    
        
                                
        if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam_img)){
            $arr_img = array('source' => $cam_img. "?". filemtime($_SERVER['DOCUMENT_ROOT'].'/'.$cam_img));
        }

  
        if(($row_block['ContentBlocksTypeId']==29 && $y>1) || ($row_block['ContentBlocksTypeId']==30 && $y>1) || ($row_block['ContentBlocksTypeId']==31 && $y==1) || ($row_block['ContentBlocksTypeId']==32 && $y==1)){     
                $arr_img = ""; 
        }


         
        $bt         = "";
        $bt_link    = "";
        $bt_margin  = "";
        if($row_block["NumberBlocks"]<=3){
            if($row_block["TextButton".$y.$LG]!="" && $row_block["LinkButton".$y]!=""){
                $bt = $row_block["TextButton".$y.$LG];
                $bt_link = $row_block["LinkButton".$y];
                $bt_margin = $row_block["MarginTopButton".$y];
            }
        }
        
        

        buildLink($bt_link, $row_block['id'], ($y-1));
        buildLink($row_block["Link".$y], $row_block['id'], $y);  #Link de rodapé
         
        buildLink($row_block["Blocks_LinkButton".$y], $row_block['id'], 1); #Botão 1
        buildLink($row_block["Blocks_2LinkButton".$y], $row_block['id'], 2); #Botão 2
        
        
        
        $row_block["Blocks_tit_".$y.$LG]    = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $row_block["Blocks_tit_".$y.$LG]);
        $row_block["Blocks_subtit_".$y.$LG] = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $row_block["Blocks_subtit_".$y.$LG]);
        $row_block["Blocks_desc_".$y.$LG]   = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $row_block["Blocks_desc_".$y.$LG]);
        $row_block["Blocks_desc2_".$y.$LG]  = str_replace("{NOME_CLIENTE}", $nome_cliente[0], $row_block["Blocks_desc2_".$y.$LG]);
        
        
        if($ENVIO['Type']==2 || $ENVIO['FinalType']==4){
            $row_block["Blocks_tit_".$y.$LG]    = str_replace("{VARIAVEL_1}", $CLIENTE['var1'], $row_block["Blocks_tit_".$y.$LG]);
            $row_block["Blocks_subtit_".$y.$LG] = str_replace("{VARIAVEL_1}", $CLIENTE['var1'], $row_block["Blocks_subtit_".$y.$LG]);
            $row_block["Blocks_desc_".$y.$LG]   = str_replace("{VARIAVEL_1}", $CLIENTE['var1'], $row_block["Blocks_desc_".$y.$LG]);
            $row_block["Blocks_desc2_".$y.$LG]  = str_replace("{VARIAVEL_1}", $CLIENTE['var1'], $row_block["Blocks_desc2_".$y.$LG]);   
        }
        
            
        if(count($ATTRS_ESPECIAIS)>0){
            
            foreach($ATTRS_ESPECIAIS as $key){
                $row_block["Blocks_tit_".$y.$LG]    = str_replace("{".$key."}", $CLIENTE['ATTR_ESP_'.$key], $row_block["Blocks_tit_".$y.$LG]);
                $row_block["Blocks_subtit_".$y.$LG] = str_replace("{".$key."}", $CLIENTE['ATTR_ESP_'.$key], $row_block["Blocks_subtit_".$y.$LG]);
                $row_block["Blocks_desc_".$y.$LG]   = str_replace("{".$key."}", $CLIENTE['ATTR_ESP_'.$key], $row_block["Blocks_desc_".$y.$LG]);
                $row_block["Blocks_desc2_".$y.$LG]  = str_replace("{".$key."}", $CLIENTE['ATTR_ESP_'.$key], $row_block["Blocks_desc2_".$y.$LG]);  	   
            }
        }
                
                          
        $tmp_banner_info = array(
            "type_block"              => $row_block["ContentBlocksType".$y],
            "html_background"         => $background,
            "image"                   => $arr_img, 
            "image_alt"               => $row_block["Blocks_alt_".$y.$LG],
            "link"                    => $row_block["Link".$y],
            "button"                  => $bt,
            "button_link"             => $bt_link,
            "button_margin_top"       => $bt_margin,
            "title"                   => $row_block["Blocks_tit_".$y.$LG],
            "subtitle"                => $row_block["Blocks_subtit_".$y.$LG],
            "description"             => nl2br($row_block["Blocks_desc_".$y.$LG]),
            "description2"            => nl2br($row_block["Blocks_desc2_".$y.$LG]),
            "textColor"               => "#".$row_block["Blocks_txt_color_".$y],
            "buttonBGColor"           => "#".$row_block["Blocks_url_gb_".$y],
            "buttonBRColor"           => "#".$row_block["Blocks_url_br_tit_".$y],
            "buttonTXColor"           => "#".$row_block["Blocks_url_tx_".$y],                
            "linkColor"               => "#".$row_block["Blocks_link_color_".$y],
            "footerButtonBGColor"     => "#".$row_block["Roda_Blocks_url_gb_".$y],
            "footerButtonBRColor"     => "#".$row_block["Roda_Blocks_url_br_tit_".$y],
            "footerButtonTXColor"     => "#".$row_block["Roda_Blocks_url_tx_".$y],
            "typeURL"                 => $row_block["Blocks_TypeURL".$y],
            "textURL"                 => $row_block["Blocks_TextButton".$y.$LG],
            "linkURL"                 => $row_block["Blocks_LinkButton".$y],
            "textURL2"                => $row_block["Blocks_2TextButton".$y.$LG],
            "linkURL2"                => $row_block["Blocks_2LinkButton".$y],
            "textPosition"            => $row_block["Text_position".$y],    
            "textAlign"               => $row_block["Align_position".$y], 
            "textVAlign"              => $row_block["AlignV_position".$y],    
            "TextMinHeight"           => $row_block["TextMinHeight"],
            "list_product"            => $list_product                                                                        
        );
             
        if( $row_block["ContentBlocksType".$y]==16 ){ #CountDown
            $tmp_banner_info['legendColor']       = (trim($row_block['Blocks_txt_color_2'])!="" ? "#".$row_block['Blocks_txt_color_2'] : '');
            $tmp_banner_info['descriptionColor']  = (trim($row_block['BackgroundColor2'])!="" ? "#".$row_block['BackgroundColor2'] : '');

            $n_LG = $LG;
            if($n_LG == "gb") $n_LG = "en";
            elseif($n_LG == "sp") $n_LG = "es";

            $tmp_banner_info['link'] = "https://rdc.sx/timer/?dt=".$row_block['Blocks_tit_1pt']."/".$row_block['Blocks_TextButton1pt'].":".$row_block['Blocks_2TextButton1pt'].":00&bg=".$row_block['BackgroundColor2']."&fg=".$row_block['Blocks_txt_color_1']."&fgl=".$row_block['Blocks_txt_color_2']."&italic=".$row_block['Blocks_subtit_1pt']."&lang=".$n_LG;
        }
                  
        
        $arr[] = $tmp_banner_info;

        $i++;$y++;
           
    }        
      
              
    $arr_block = array(
        "id"                      => $row_block["id"],
        "template"                => $row_block["ContentBlocksTypeId"].".htm",
        "padding"                 => $padding,
        "padding_moblie"          => $padding_moblie,
        "paddingColumn"           => $row_block["Spacing"],
        "paddingColumnMobile"     => $row_block["Spacing_mobile"],
        "auto_height"             => $row_block['ImgAutoHeight'],
        "bgcolor"                 => "#".$row_block['GeralBackgroundColor'],            
        "index_full_image"        => $index_full_image,
        "banners"                 => $arr,
        "tabs"                    => $tabs,
        "activemobile"            => $row_block['Activo_mobile'],
        "activedesktop"           => $row_block['Activo'],
        "not_break_mobile"        => (int)$row_block['Blocks_subtit_4pt']            
    );
    
            
    return $arr_block;
    
}  


            
function buildLink(&$link, $idb, $pos=0){   
    
    global $sslocation, $PREVIEW;
     
    if(trim($link)=='') return;  


    if(str_starts_with($link, 'index.php')){
        $link = $sslocation.'/'.$link;    
    }
    
    if(str_starts_with($link, '/?pid')){
        $link = $sslocation.'/'.$link;    
    }
    
    if(($PREVIEW==0 || $PREVIEW==3) && str_starts_with($link, $sslocation)){
    
        if(strpos($link, '?') === false ){ 
            $link .= '?';
        }
            
        $more_link = link_get_codigo();
        $link .= $more_link;
             
    }
    
} 
    
    
                 
function str_starts_with($haystack, $needle) {
    return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}               

 
 
function link_get_codigo(){
    
    global $CLIENTE, $BLOCO, $LG, $ENVIO; 
    

    $utm_campaign = (trim($ENVIO['Namept'])!='') ? $ENVIO['Namept'] : "Campanha Email Marketing ".$ENVIO['Id'];  
    
    $more_link = '&cpn='.(200000+$ENVIO['Id']).'&utm_medium=email&utm_source=Redicom%20Email%20Marketing&utm_campaign='.urlencode($utm_campaign).'&ma=1';

    //if($CLIENTE['cliente_tipo']==0){
     
    $base_codigo = base64_encode('0|||'.$CLIENTE['id'].'|||'.$CLIENTE['email']);
    $more_link .= '&m2code='.$base_codigo;    
   
    //}    

    return $more_link;
}  



function email_preview($html){
    
    global $BLOCO, $CLIENTE, $PREVIEW, $ENVIO, $LG, $DEFS_EMAIL;
   

    $htmlBody = utf8_encode($html);
    
    $TOTAL_SIZE++;
    
    $txtBody = preg_replace("/<((?:style)).*>*<\/style>/si", ' ',$htmlBody);
    $txtBody = preg_replace("/<((?:head)).*>*<\/head>/si", ' ',$txtBody);
    
    #$content = preg_replace('/(<[^>]*) style=("[^"]+"|\'[^\']+\')([^>]*>)/i', '$1$3', $content);          
    $txtBody = strip_tags($txtBody, '');
    
    $txtBody = str_replace('&nbsp;', ' ', $txtBody);
    $txtBody = preg_replace("/[\r\n]+/", "\n", $txtBody);
    $txtBody = preg_replace("/\s+/", ' ', $txtBody);
    
      
    $subject = iconv('ISO-8859-1', 'UTF-8', $BLOCO['Namept']);
    $subject = html_entity_decode($subject, ENT_QUOTES, "UTF-8");
    
    
    if($PREVIEW==3 || ($PREVIEW==1 && trim($ENVIO['Subject'.$LG])!='')){
        $subject = iconv('ISO-8859-1', 'UTF-8', $ENVIO['Subject'.$LG]);
        $subject = html_entity_decode($subject, ENT_QUOTES, "UTF-8");
    }
  
      
         
    $MONGO_DOCUMENT 	= array("domain"		          => $DEFS_EMAIL['domain'],
                                "siteId"	          => $DEFS_EMAIL['clientid'],  
                                "fromAddr"	        => $DEFS_EMAIL['fromAddr'], 
                                "fromName"	        => utf8_encode($DEFS_EMAIL['fromName']), 
                                "replyToAddr"	      => $DEFS_EMAIL['replyToAddr'], 
                                "replyToName"	      => utf8_encode($DEFS_EMAIL['replyToName']),
                                "toAddr"	          => $CLIENTE['email'], 
                                "toName"	          => "", #Tirou-se porque os acentos não eram aceites e não é necessário  
                                "subject"	          => $subject, 
                                "preHeader"	        => "", 
                                "htmlBody"	        => $htmlBody,
                                "txtBody"	          => utf8_encode($txtBody),
                                "sendFlag"	        => 0,
                                "templateId"	      => $BLOCO['Id'],
                                "jobId"	            => 0,
                                "internalEmailId"	  => 0,
                                "clientId"	        => $CLIENTE['id'],
                                "clientType"        => $CLIENTE['cliente_tipo'],                                                                        
                                "demo"              => (int)$ENVIO['Demo'],
                                "createTimeStamp"   => date('c'));
                                  
    
    
    
     if($DEFS_EMAIL['byAmazon']==1){
        $headers = ['Content-Type: application/json'];
    
    	 						
        $request 	= array("dataSource" => "Cluster0", 
         									"database"	 => "email_marketing",  
         									"collection" => "queue", 
         									"documents"	 =>	array($MONGO_DOCUMENT));		
        									 												
                                         
        $data_string 	= json_encode($request, JSON_UNESCAPED_UNICODE);
             
        		 
        $ch = curl_init("https://private-services.redicom.net/aws-ses/insertMany.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $return = curl_exec($ch);
        #var_dump($return);
        
        curl_close($ch);
        
        return;
    }  
      
                                               

    
    
    
    $headers   = ['Content-Type: application/json','Access-Control-Request-Headers: *','api-key: FJMMNS3pu7Fp4ExQTzpVUpQa6jzzkRtQMhiL6pVSgwOzJvzkP92LyhqTQvT35cOT'];    
    
 						
    $request 	= array("dataSource" => "Cluster0", 
     									"database"	 => "email_marketing",  
     									"collection" => "queue", 
     									"document"	 =>	$MONGO_DOCUMENT);		
    									 												                                          
    $data_string 	= json_encode($request);
    					 
    $ch = curl_init("https://data.mongodb-api.com/app/data-hsqlh/endpoint/data/v1/action/insertOne");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($ch);

    curl_close($ch);

    return;
    
}


?>
