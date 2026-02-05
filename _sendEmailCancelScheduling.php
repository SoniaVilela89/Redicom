<?php

function _sendEmailCancelScheduling($scheduling_id=null){
    global $eComm;

    if ($scheduling_id > 0){
        $scheduling_id = (int)$scheduling_id;
     }else{
        $scheduling_id = (int)params('scheduling_id');
     }

     if($scheduling_id>0) $scheduling = call_api_func("get_line_table","agendamentos_registos", "id='".$scheduling_id."' AND status=0");
    
    if((int)$scheduling["id"]==0){
        $arr = array();
        $arr['0'] = 1;
    
        return serialize($arr);    
    }

    # Não enviar emails para clientes com esta opção ativa
    if( is_numeric($scheduling['user_id']) && (int)$scheduling['user_id'] > 0 ){
        $user = call_api_func("get_line_table","_tusers", "id='".$scheduling['user_id']."'");
        if( (int)$user['impedir_envio_emails'] == 1 ){
            $arr = array();
            $arr['0'] = 1;
        
            return serialize($arr);
        }
    }

    $lingua = call_api_func("get_line_table","ec_language", "id='".$user['id_lingua']."'");
    $_lg = strtolower($lingua['code']);
    if( trim($_lg)=="" ) $_lg = "pt";
    if( $_lg=="en" ) $_lg = "gb";
    if( $_lg=="es" ) $_lg = "sp";
    $LG = $_lg;

    $userID  = $user['cliente_final'];
    $COUNTRY = $eComm->countryInfo($user['pais']);
    $MARKET  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA   = $eComm->moedaInfo($MARKET['moeda']);
    
    $_exp = array();
    $_exp['table'] = "exp";
    $_exp['prefix'] = "nome";
    $_exp['lang'] = $LG;

    #envia email
    $temp_email = call_api_func("get_line_table","email_templates", "id='15'");
    $template   = '';
    if (trim($temp_email['bloco'.$LG])==''){
        $arr = array();
        $arr[] = 1;   
        return serialize($arr);  
    } 
    
    $sql  = cms_query("SELECT hora FROM agendamentos_horas WHERE id = ".$scheduling['hora_registo']." ");
    $hora = cms_fetch_assoc($sql);         
    $hora = $hora['hora'];
    
    $sql = cms_query("SELECT * FROM agendamentos WHERE id = ".$scheduling['id_agendamento']." ");
    $_scheduling = cms_fetch_assoc($sql);    
    
    $date = date_create($scheduling["dia_registo"]);
    $date = date_format($date,"d-m-Y");
    
    if($LG=='gb'){
        $date = strftime("%d %B, %Y",strtotime($date));                  
    }else{
        setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
        $date = strftime("%d  %B  %Y",strtotime($date));   
    } 
    
    $SCHEDULING = get_template_scheduling($_scheduling, $date, $hora, '');
    
    $template = nl2br($temp_email['bloco'.$LG]);
    $template = str_ireplace("{CLIENT_NAME}", $user["nome"], $template);     
    $template = str_ireplace("{SCHEDULING}", $SCHEDULING, $template);

    $y = get_info_geral_email($LG, $MARKET, '', $user);
    $y['LINK']        = "";
    $y['DESCRICAO']   = $template;
    $y['TITULO']      = $temp_email['assunto'.$LG];
    $y['negar_exp']   = '';
    $y['FINALIZAR']   = "";
                                                     

    $content = cms_real_escape_string(serialize($y)); 
    saveEmailInBD_Marketing($user["email"], $temp_email['assunto'.$LG], $content, $user["id"], 0, "Envio de cancelamento de marcação", 1, 0, 'schedule', 0, $y['view_online_code']);
    saveEmailInBD_Marketing($_scheduling["email"], $temp_email['assunto'.$LG], $content, $user["id"], 0, "Envio de cancelamento de marcação", 1, 0, 'schedule', 0, $y['view_online_code']);


    $arr = array();
    $arr[] = 1;
    return serialize($arr);

}
