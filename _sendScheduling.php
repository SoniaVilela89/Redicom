<?
function _sendScheduling($schedul=null){

    global $fx;
    global $LG;
    
    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }                         
    
    #Para evitar a submissão excessiva de formularios
    $key = md5(base64_encode($_SERVER[REMOTE_ADDR] . "blog"));
    $x = 0 + @apc_fetch($key);
    if ($x>3){
        $x++;
        apc_store($key, $x, 60);
        header('HTTP/1.1 403 Forbidden');
        exit;
    } else {
        $x++;
        apc_store($key, $x, 60);
    }
    
    $DADOS = $_POST;

    if(empty($_POST["hora"]) || empty($_POST["dia"]) || empty($_POST["id_agendamento"])){
        $arr = array();
        $arr[] = 0;
        return serialize($arr);
    }
    $s = "SELECT * FROM agendamentos_registos WHERE id_agendamento = ".$DADOS["id_agendamento"]." AND dia_registo = '".$DADOS["dia"]."' and hora_registo =".$DADOS["hora"]." AND status=0 ";    
    
    $s      = cms_query($s);
    $exist  = cms_fetch_assoc($s);
    if($exist['id'] > 0){     
        $arr = array();
        $arr[] = 0;
        return serialize($arr); 
    }
    
    $today = date("d-m-Y");    
    $time1 = strtotime($today);
    $time2 = strtotime($DADOS["dia"]);

    if($time1 >= $time2){  
        $arr = array();
        $arr[] = 0;
        return serialize($arr); 
    }

    $sql = "INSERT INTO agendamentos_registos  (id_agendamento,user_id,dia_registo,hora_registo) VALUES ('%s','%s','%s','%d')";
     
    $sql_n = sprintf($sql,$DADOS["id_agendamento"],$_SESSION["EC_USER"]["id"],$DADOS["dia"],$DADOS["hora"]);      
    cms_query($sql_n);    
    
    
    $idRegisto = cms_insert_id();  
    
                                                                                             
     
    $sql  = cms_query("SELECT hora FROM agendamentos_horas WHERE id = ".$DADOS['hora']." ");
    $hora = cms_fetch_assoc($sql);         
    $hora = $hora['hora'];
    
    $sql = cms_query("SELECT * FROM agendamentos WHERE id = ".$DADOS['id_agendamento']." ");
    $scheduling = cms_fetch_assoc($sql); 
    
    
    $date = date_create($DADOS["dia"]);
    $date = date_format($date,"d-m-Y");
    
    if($LG=='gb'){
        $date = strftime("%d %B, %Y",strtotime($date));                  
    }else{
        setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
        $date = strftime("%d  %B  %Y",strtotime($date));   
    }  

    #envia email
    $temp_email = call_api_func("get_line_table","email_templates", "id='11'");
    $template   = '';
    if (trim($temp_email['bloco'.$LG])==''){
        $arr = array();
        $arr[] = 1;   
        return serialize($arr);  
    }        

    $SCHEDULING = get_template_scheduling($scheduling, $date, $hora, '');
    
    
    
    if(is_callable('custom_controller_send_scheduling')) {          
        call_user_func_array('custom_controller_send_scheduling', array($idRegisto, $scheduling, $date, $hora, $DADOS, &$SCHEDULING));
    }
    
    

    $template = nl2br($temp_email['bloco'.$LG]);
    $template = str_ireplace("{CLIENT_NAME}", $_SESSION["EC_USER"]["nome"], $template);     
    $template = str_ireplace("{SCHEDULING}", $SCHEDULING, $template); 
 
    
    
    
    $y = get_info_geral_email($LG, $MARKET, '', $user);
    $y['LINK']        = "";
    $y['DESCRICAO']   = $template;
    $y['TITULO']      = $temp_email['assunto'.$LG];
    $y['negar_exp']   = '';
    $y['FINALIZAR']   = "";
                                                     

    $content = cms_real_escape_string(serialize($y)); 
    saveEmailInBD_Marketing($_SESSION["EC_USER"]["email"], $temp_email['assunto'.$LG], $content, $_SESSION["EC_USER"]["id"], 0, "Envio de marcação", 1, 0, 'schedule', 0, $y['view_online_code']);
    saveEmailInBD_Marketing($scheduling["email"], $temp_email['assunto'.$LG], $content, $_SESSION["EC_USER"]["id"], 0, "Envio de marcação", 1, 0, 'schedule', 0, $y['view_online_code']);


    $arr = array();
    $arr[] = 1;
    return serialize($arr); 
}
?>
