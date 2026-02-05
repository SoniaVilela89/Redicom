<?

function _getAfterSaleDamagePDF($vehicle_id=0, $damage_id=0){
    
    if( (int)$vehicle_id <= 0 || (int)$damage_id <= 0 ){
        $vehicle_id = (int)params('vehicle_id');
        $damage_id = (int)params('damage_id');
    }

    if( (int)$vehicle_id <= 0 || $damage_id <= 0 ){
        exit;
    }
    
    global $LG, $fx, $eComm, $userID;
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getAfterSalesVehicleInfo.php';

    $vehicle_info = unserialize( _getAfterSalesVehicleInfo($vehicle_id) );
    if( (int)$vehicle_info['success'] == 0 || empty($vehicle_info['payload']) ){
        exit;
    }
    
    $vehicle_info = $vehicle_info['payload']['vehicle'];
    if( empty($vehicle_info) || (int)$vehicle_info['id'] <= 0 ){
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getAfterSaleDamageDetail.php';

    $damage_info = unserialize( _getAfterSaleDamageDetail($damage_id) );
    if( (int)$damage_info['success'] == 0 || empty($damage_info['payload']) ){
        exit;
    }
    
    $damage_info = $damage_info['payload']['damage'];
    if( empty($damage_info) || (int)$damage_info['id'] <= 0 ){
        exit;
    }

    
    $x = array();
    
    if( count($vehicle_info['owners']) == 0 ){
        $vehicle_info['owners'][0]                    = array();
        $vehicle_info['owners'][0]['name']            = "-";
        $vehicle_info['owners'][0]['nif']             = "-";
        $vehicle_info['owners'][0]['address']         = "-";
        $vehicle_info['owners'][0]['zip']             = "-";
        $vehicle_info['owners'][0]['city']            = "-";
        $vehicle_info['owners'][0]['country']['name'] = "-";
        $vehicle_info['owners'][0]['phone_number']    = "-";
        $vehicle_info['owners'][0]['email']           = "-";
        $vehicle_info['owners'][0]['date']            = "-";    
    }
    
    if( trim($vehicle_info['registration_number']) == "" ){
        $vehicle_info['registration_number'] = "-";
    }
    
    $x["response"]["vehicle"] = $vehicle_info;
    
    $x["response"]["damage"]  = $damage_info;
    
    $user = cms_fetch_assoc( cms_query("SELECT `pais`, `nome` FROM `_tusers` WHERE id='".$userID."' LIMIT 1") );
    
    $x["response"]['user']['name'] = $user['nome'];
    
    $country = $eComm->countryInfo($user['pais']);
    $market  = $eComm->marketInfo($country['id']);
    
    $logo = "images/cab_".$market['entidade_faturacao'].".jpg";
    $x["response"]["logo"] = $logo;
    
    $x["response"]['expressions'] = call_api_func('getAccountExpressions');
    
    $conditions = cms_fetch_assoc( cms_query("SELECT `bloco$LG` AS conditions FROM `ec_rubricas` WHERE id=53 LIMIT 1") );
    $x["response"]['conditions'] = strip_tags($conditions['conditions'],"<b><p><br><i><em>");
   
    
    if(file_exists("../templates/account_after_sale_damage_print.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/v2/', false, _ROOT.'/temp_twig/');
    }
    
    $html = $fx->printTwigTemplate("account_after_sale_damage_print.htm",$x, true, $exp);
    
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
    
    $doc_name = "damage_proof_".$damage_id;
    
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

?>
