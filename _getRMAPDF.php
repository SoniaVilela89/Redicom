<?

function _getRMAPDF($user_id=0, $rma_id=0){
    
    global $pagetitle, $SETTINGS;
    
    if( (int)$rma_id <= 0 || (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
        $rma_id = (int)params('rma_id');
    }
    
              
    if( (int)$rma_id <= 0 || $user_id <= 0){
        exit;
    }
    
    global $LG, $fx, $eComm;
    
    
    

    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }
    
    
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getRMADetail.php';

    $rma_info = unserialize( _getRMADetail($user_id, $rma_id) );
    
    if( $rma_info['success'] === false || empty($rma_info['payload']) ){
        exit;
    }
    
    $rma_info = $rma_info['payload']['rma'];
    
    if( empty($rma_info) || (int)$rma_info['id'] <= 0 ){
        exit;
    }
    
    $x = array();
    $x["response"]["rma"] = $rma_info;
    
    $user = cms_fetch_assoc( cms_query("SELECT `pais`, `nome` FROM `_tusers` WHERE id='".$user_id."' LIMIT 1") );
    
    $x["response"]['user']['name'] = $user['nome'];
    
    $country = $eComm->countryInfo($user['pais']);
    $market  = $eComm->marketInfo($country['id']);
    
    $logo = "images/cab_".$market['entidade_faturacao'].".jpg";
    $x["response"]["logo"] = $logo;
    
    $x["response"]['expressions'] = call_api_func('getAccountExpressions');
    
    $conditions = cms_fetch_assoc( cms_query("SELECT `bloco$LG` AS conditions FROM `ec_rubricas` WHERE id=51 LIMIT 1") );
    $x["response"]['conditions'] = strip_tags($conditions['conditions'],"<b><p><br><i><em>");
    
    $qrcode = generateRMAQRCode($rma_info['id'], $rma_info['ref']);
    if( trim($qrcode) != '' ){
        $x["response"]["rma"]['qrcode'] = "/prints/RMAQRCODE/".$rma_info['id'].".jpg";
    }

    $x["response"]["envelope_window"]['store_name'] = $pagetitle;
    $x["response"]["envelope_window"]['store_address'] = nl2br($SETTINGS['morada']);
    
    if(file_exists("../templates/account_budget_print.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/v2/', false, _ROOT.'/temp_twig/');
    }
    
    $html = $fx->printTwigTemplate("account_rma_print.htm",$x, true, $exp);
    
    $documentTemplate = '
    <!doctype html>      
    <html>
        <body>
            <div id="wrapper">
                '.utf8_encode($html).'
            </div>
        </body>
    </html>';
    // echo $documentTemplate;exit;
    include("lib/mpdf/mpdf.php");   
    
    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 9, 9, 9, 9, 'C');   
    $mpdf->SetDisplayMode('fullpage');  
    $mpdf->WriteHTML($stylesheet,1);          
    $mpdf->WriteHTML($documentTemplate);
    
    $doc_name = "rma_proof_".$rma_id;
    
    $type_output = 'S';
    $save_cam = str_replace('/', '', $doc_name).'.pdf';

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$save_cam.'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    echo $mpdf->Output($save_cam, $type_output);
    
    exit;
    
}


function generateRMAQRCode($rma_id, $qrcode_info){

    include_once $_SERVER['DOCUMENT_ROOT']."/plugins/billing/qrcode/qrlib.php";
    
    $PNG_TEMP_DIR = $_SERVER['DOCUMENT_ROOT'].'/prints/RMAQRCODE';
    
    if( !file_exists($PNG_TEMP_DIR) ) mkdir($PNG_TEMP_DIR);

    $filename = $PNG_TEMP_DIR."/$rma_id.jpg";

    $errorCorrectionLevel = 'M';
    
    $matrixPointSize = 8;

    QRcode::png($qrcode_info, $filename, $errorCorrectionLevel, $matrixPointSize, 0);
    
    return $filename;

}

?>
