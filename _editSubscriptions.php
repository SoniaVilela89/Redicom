<?
function _editSubscriptions(){
    
    global $B2B;
    
    $DADOS = $_POST;

    foreach( $DADOS as $k => $v ){
        $DADOS[$k] = utf8_decode($v);
    }

    $userID = (int)$_SESSION['EC_USER']['id'];

    $tabela = "_tusers"; 
    if((int)$_SESSION['EC_USER']["type"]>0 && $B2B==1){
        $tabela = "_tusers_sales";
    }
    
    
    
    $s_user = "SELECT * FROM $tabela WHERE id='".$userID."' LIMIT 0,1";
    $q_user = cms_query($s_user);
    $r_user = cms_fetch_assoc($q_user);
    
    

    $sql = "UPDATE $tabela SET
                rejeita_email_marketing='".$DADOS['email']."',
                rejeita_sms_marketing='".$DADOS['sms']."',
                rejeita_email_marketing_vales='".$DADOS['vales']."',
                last_update_rejeita=NOW(),
                last_update=NOW()
              WHERE id='$userID'";
              
    cms_query($sql);
    
    
    if($DADOS['email']==1 && $DADOS['jobid']>0){
        @cms_query('INSERT INTO `ec_sms_listas_externas_remove` (`email`, `origem`, `job_id`) VALUES("' . $_SESSION['EC_USER']['email'] . '", "PAGE_15", "'.$DADOS['jobid'].'")');
    }         
    
    if((int)$DADOS['email']==1){
        $sql = "UPDATE _tnewsletter SET confirmado='-1' WHERE email='".$_SESSION['EC_USER']['email']."'";
        cms_query($sql); 
    }else{
        $sql = "UPDATE _tnewsletter SET confirmado='1' WHERE email='".$_SESSION['EC_USER']['email']."'";
        cms_query($sql); 
    }
    
    
    # 2025-05-07
    if($DADOS['sms']==0 && trim($_SESSION['EC_USER']['telefone'])!=''){
        $phoneNumber  = substr($_SESSION['EC_USER']['telefone'], -9);
        @cms_query("UPDATE `ec_sms_listas_externas_remove` SET deleted=1 WHERE tel LIKE '%$phoneNumber%' AND `deleted`=0 LIMIT 1");    
    }
    
    
     # 2025-04-08
    # Chamar os webhooks da API 
    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/webhooks_api/Webhooks.php");
    $web = new Webhooks();   
    $web->setAction('EDIT_CLIENT', array('id' => $userID, 'user_before_edit' => $r_user));
    
    
    
                     
    $tmp = array();
    $tmp[meio]            = "Área de Cliente";
    $tmp[origem]          = "Próprio Cliente";                     
    
    require_once("../plugins/privacy/Log.php");
    $log = new Log();   
    $log->edit_mkt_settings($_SESSION['EC_USER']['id'], $tmp);       
    
    

    return serialize(array("0"=>1));
}
?>
