<?php
function _getAccountDocument($docs=null)
{
    if(is_null($docs)){
        $docs   = (int)params('docs');  
    }
    
    global $slocation, $CONFIG_OPTIONS;
    
    $document = $docs;

    $document = base64_decode($document);
    
    $document = explode('|||', $document);
    
    $exist  = cms_fetch_assoc(cms_query("SELECT id,num,validado_sms FROM _tdocumentos WHERE num ='".$document[1]."' AND id_user='".$document[0]."' LIMIT 0,1")); 
    
    if((int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1 && (int)$exist['validado_sms'] == 0) {
        ob_end_clean();
        header("Location: ".$slocation, true, 307);
        exit;
    }

    if($exist['id'] > 0){
        $file = "../downloads/documents/".$exist['num'].".pdf";   
        ob_end_clean();
        header("Content-type: application/pdf"); 
        header("Content-disposition: attachment; filename=".urlencode($exist['num']).".pdf");
        readfile($file);
        ob_clean();
        flush();         
        exit;
    }
    
    ob_end_clean();
    header("Location: ".$slocation, true, 307); 
    exit;

}
?>
