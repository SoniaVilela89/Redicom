<?

function _getAfterSalePDF($user_id=0, $after_sale_id=0){
    
    global $userID;
    
    if( (int)$after_sale_id <= 0 || (int)$user_id <= 0 ){
        $user_id = (int)params('user_id');
        $after_sale_id = (int)params('after_sale_id');
    }

    if( (int)$after_sale_id <= 0 || $user_id <= 0 || $userID != $user_id ){
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    $after_sale = cms_fetch_assoc( cms_query("SELECT * FROM `b2b_pos_venda` WHERE `id`='".$after_sale_id."' AND `utilizador_id`='".$user_id."' LIMIT 1") );
    if( (int)$after_sale['id'] <= 0 ){
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    global $LG, $fx, $eComm;
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getAfterSalesVehicleInfo.php';

    $vehicle_info = unserialize( _getAfterSalesVehicleInfo($after_sale['veiculo_id']) );
    if( (int)$vehicle_info['success'] == 0 || empty($vehicle_info['payload']) ){
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    $vehicle_info = $vehicle_info['payload']['vehicle'];
    if( empty($vehicle_info) || (int)$vehicle_info['id'] <= 0 ){
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    $after_sale['date'] = date("Y-m-d", strtotime($after_sale['data_criacao']));
    
    $x = array();
    $x["response"]["after_sale"] = $after_sale;
    
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
    
    $x["response"]["after_sale"]['vehicle'] = $vehicle_info;
    
    $user = cms_fetch_assoc( cms_query("SELECT `pais`, `nome` FROM `_tusers` WHERE id='".$user_id."' LIMIT 1") );
    
    $x["response"]['user']['name'] = $user['nome'];
    
    $country = $eComm->countryInfo($user['pais']);
    $market  = $eComm->marketInfo($country['id']);
    
    $logo = "images/cab_".$market['entidade_faturacao'].".jpg";
    $x["response"]["logo"] = $logo;
    
    $x["response"]['expressions'] = call_api_func('getAccountExpressions');
    
    $conditions = cms_fetch_assoc( cms_query("SELECT `bloco$LG` AS conditions FROM `ec_rubricas` WHERE id=53 LIMIT 1") );
    $x["response"]['conditions'] = $conditions['conditions'];   

    
    if(file_exists("../templates/account_after_sale_print.htm")){
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/templates/', false, _ROOT.'/temp_twig/');
    }else{
        $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/v2/', false, _ROOT.'/temp_twig/');
    }
    
    $html = $fx->printTwigTemplate("account_after_sale_print.htm",$x, true, $exp);
    
    $documentTemplate = '
    <!doctype html>      
    <html>
        <body>
            <div id="wrapper">
                '.utf8_encode($html).'
            </div>
        </body>
    </html>';

    include("lib/mpdf/mpdf.php");   
    
    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 9, 9, 9, 9, 'C');   
    $mpdf->SetDisplayMode('fullpage');  
    $mpdf->WriteHTML($stylesheet,1);          
    $mpdf->WriteHTML($documentTemplate);
    
    $doc_name = "after_sale_proof_".$after_sale_id;
    
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
