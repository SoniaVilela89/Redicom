<?
function _subscribeNews(){
     
    global $LG, $COUNTRY, $sslocation, $NEWSLETTTER_AUTO, $collect_api;


    if (!isset($_POST['csrf'], $_SESSION['csrf'])) {
        ob_end_clean();
				header("HTTP/1.1 403 Forbidden");
  			header("Content-Type: text/plain");
  			exit;
    }


    $_POST = decode_array_to_UTF8($_POST);
    
    
    if(strlen($_POST['csrf'])<8 || $_SESSION['csrf']!=$_POST['csrf']){               
        ob_end_clean();
				header("HTTP/1.1 403 Forbidden");
  			header("Content-Type: text/plain");
  			exit;
    }
    
    if (!filter_var($_POST['cms_field_31'], FILTER_VALIDATE_EMAIL)) {
        return serialize(array("0"=>"0"));
    }

    #Para evitar a submissão excessiva de formularios
    
    $key2 = 'FORMNEWS_2_'.$_SERVER[REMOTE_ADDR];

    $x2 = 0 + @apc_fetch($key2);
    if ($x2>9)
    {
        $x2++;
        apc_store($key2, $x2, 31536000);  #1 ano
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x2++;
        apc_store($key2, $x2, 3600); #1h
    }
    
    
    $key = 'FORMNEWS_'.$_SERVER[REMOTE_ADDR];

    $x = 0 + @apc_fetch($key);
    if ($x>3)
    {
        $x++;
        apc_store($key, $x, 60*60*24); #1dia
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 180);  #3minutos
    }
    
    
    $campos = $_POST;
    unset($campos['form_id']);
    unset($campos['csrf']);
    unset($campos['cms_field_30']);
    unset($campos['cms_field_31']);
    unset($campos['cms_field_32']);
    unset($campos['cms_field_33']);

    $sql_form = "SELECT id,sendmsg FROM _tforms WHERE submit='subscribeNews' LIMIT 0,1";
    $res_form = cms_query($sql_form);
    $row_form = cms_fetch_assoc($res_form);    
    
    #Valida obrigatórios
    $sql_form_line = cms_query("SELECT unid FROM _tform_lines WHERE id='".$row_form['id']."' AND obrigatorio=1");
    while ( $row_form_line = cms_fetch_assoc($sql_form_line) ) {
        if( empty($_POST['cms_field_'.$row_form_line['unid']]) ){
            return serialize(array("0"=>"0"));
        }
    } 
    
    $s      = "SELECT id, confirmado FROM _tnewsletter WHERE email='".$_POST['cms_field_31']."' LIMIT 0,1";
    $q      = cms_query($s);
    $existe = cms_fetch_assoc($q);
    
    if( $existe['id']>0 && $existe['confirmado'] > 0 ){
        return serialize(array("0"=>"0"));
    }
    
    # O proximo load vai criar un novo obrigatoriameente    
    unset($_SESSION['csrf']);

    $confirmed = 0;
    $date_confirmed = "0000-00-00 00:00:00";
    if((int)$NEWSLETTTER_AUTO==1){
        $confirmed = 1;
        $date_confirmed = date("Y-m-d H:i:s");    
    }
    
    if( $existe['id']>0 ){
        
        $sql = "UPDATE `_tnewsletter` SET pais='".$COUNTRY['id']."',
	                                      nome='".$_POST['cms_field_30']."',
	                                      cidade='".$_POST['cms_field_32']."',
	                                      genero='".$_POST['cms_field_33']."',
	                                      campos='".serialize($campos)."',
	                                      lg='$LG',
	                                      info_sysnc='0',
                                        data_hora_confirmado='".$date_confirmed."',
                                        confirmado='".$confirmed."'
                                        WHERE `id`=".$existe['id'];
        cms_query($sql);
        $id_news = $existe['id'];
    
    }else{
        
        $sql = "INSERT INTO _tnewsletter SET email='".$_POST['cms_field_31']."',
    	                                        pais='".$COUNTRY['id']."',
    	                                        nome='".$_POST['cms_field_30']."',
    	                                        cidade='".$_POST['cms_field_32']."',
    	                                        genero='".$_POST['cms_field_33']."',
                                              genero_system='".$_POST['genero']."',
    	                                        campos='".serialize($campos)."',
    	                                        lg='$LG',      
    	                                        info_sysnc='0',
                                              data_hora_confirmado='".$date_confirmed."',
                                              confirmado='".$confirmed."'";
    
        cms_query($sql); 
        $id_news = cms_insert_id($sql);
        
    } 
    
    if((int)$NEWSLETTTER_AUTO==1){
        #$sql_form = "SELECT * FROM _tforms WHERE submit='subscribeNews' AND sendmsg>0 LIMIT 0,1";
        #$res_form = cms_query($sql_form);
        #$row_form = cms_fetch_assoc($res_form);
             
        if((int)$row_form["id"]>0 && $row_form['sendmsg']>0){
        
            $temp_email = call_api_func("get_line_table","_tmisctext", "id='".$row_form["sendmsg"]."'"); 
            
            $template = nl2br($temp_email['desc'.$LG]);

            if (trim($template)!=''){
                $template = str_ireplace("{CLIENT_NAME}", $_POST['cms_field_30'], $template);
                $send = sendEmail($template, $temp_email['descsys'.$LG], $_POST['cms_field_31'], "", "", "Newsletter confirmada");  
            }
        }        
        
        collectApiNewsletter($_POST['cms_field_31'],$_POST['cms_field_30']);
        
        return serialize(array("0"=>"1"));
    }
    
    $temp_email = call_api_func("get_line_table","email_templates", "id='12' AND hidden=0");    
    if (trim($temp_email['bloco'.$LG])!=''){
        $template = nl2br($temp_email['bloco'.$LG]);
        $template = str_ireplace("{CLIENT_NAME}", $_POST['cms_field_30'], $template);
        $template = str_ireplace("{LINK_NEWS}", $sslocation."/api/action_subscribe.php?client=".base64_encode($id_news), $template);
        
        $send = sendEmail($template, $temp_email['assunto'.$LG], $_POST['cms_field_31'], "", "", "Confirmação de newsletter");
    }
    
    
    if(is_callable('custom_controller_subscribe_news')) {
        call_user_func('custom_controller_subscribe_news', $_POST);
    }     

    return serialize(array("0"=>"1"));
}


function collectApiNewsletter($email,$nome){
    
    global $collect_api;
 
    $y['show_cp_2'] = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] ); 

    if (isset($collect_api) && !is_null($collect_api) && !empty($y) && $y['show_cp_2'] == 1) {
            
            global $idiomas_convertidos;
            
            $user_name_temp  = explode(" ", trim($nome));
            $user_first_name = $user_name_temp[0];
            $user_last_name  = end($user_name_temp);      

            $user_info = [
                'email'            => $email,
                'acceptsMarketing' => 1,
                'firstName'        => $user_first_name,
                'lastName'         => $user_last_name,  
                'country'          => ['country_code'=>$_SESSION['_COUNTRY']['country_code'],'language'=>$idiomas_convertidos[$_SESSION['LG']]], 
                'birthDate'        => $_SESSION['EC_USER']['datan'],
                'gender'           => $_SESSION['EC_USER']['sexo'],//1 - Homem | 2 - Mulher                                               
            ];
            
            try {
                $collect_api->setEvent(CollectAPI::NEWSLETTER, $user_info,null);
            } catch (Exception $e) {}
                           
    }

}



?>
