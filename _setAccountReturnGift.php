<?

function _setAccountReturnGift($enc_id=null, $line_id=null,$_LG='pt'){
    
    global $userID, $fx, $slocation, $CUSTOM_PDF_GIFT_CODEBAR,$LG;

    if(is_null($enc_id)){
        $enc_id  = params('enc_id');
        $line_id = params('line_id');
        $_LG     = params('lg');
    }
    
    (int)$enc_id  = base64_decode($enc_id);
    (int)$line_id = base64_decode($line_id);
    $LG           = $_LG;
    
    $sql_enc_line = "SELECT id,ref FROM ec_encomendas_lines WHERE id='".$line_id."' AND order_id='".$enc_id."' LIMIT 0,1";
    $res_enc_line = cms_query($sql_enc_line);
    $row_enc_line = cms_fetch_assoc($res_enc_line);
    
    if((int)$row_enc_line["id"]==0){
        return serialize(array("0"=>"0"));
    }
    
    if($row_enc_line["status"]!=80 || $row_enc_line["return_id"] > 0){
        $sql_enc_line = "SELECT * FROM ec_encomendas_lines WHERE order_id='".$enc_id."' AND ref='".$row_enc_line["ref"]."' AND status='80' AND return_id='0'";
        $res_enc_line = cms_query($sql_enc_line);
        $row_enc_line = cms_fetch_assoc($res_enc_line);
    }
    
    $encomenda = call_api_func("get_line_table", "ec_encomendas", "id='".$enc_id."'");    
    
    require_once $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR."api/lib/mpdf/mpdf.php";   
        
    $mpdf = new mPDF('utf-8', 'A4', 0, '', 9, 9, 9, 9, 9, 9, 'C');   
    $mpdf->SetDisplayMode('fullpage');
    
    $arr_unset = array();

    $x = array();
    $x["response"]["order_ref"]     = $encomenda["order_ref"];
    $x["response"]["return_date"]   = $encomenda["return_max_date"];
    $x["response"]["expressions"]   = call_api_func('getAccountExpressions');
    $x["response"]["line"]          = $row_enc_line;

    # expressões do Apoio ao cliente no talão
    // 2025-03-25 escondido a pedido
    // $x["response"]["expressions_site"][9] = estr(9); # Apoio ao cliente
    // $x["response"]["expressions_site"][67] = estr(67); # Email
    // $x["response"]["expressions_site"][69] = estr(69); # 910000000
    // $x["response"]["expressions_site"][132] = estr(132); # email@example.pt
    // $x["response"]["expressions_site"][170] = estr(170); # 252000000
    // $x["response"]["expressions_site"][361] = estr(361); # Telefone
    // $x["response"]["expressions_site"][661] = estr(661); # Whatsapp
    // $x["response"]["expressions_site"][685] = estr(685); # Chamada para rede fixa nacional

    $x["response"]["shop"]          = call_api_func('OBJ_shop_mini');
    
    $s        = "SELECT * FROM ec_mercado WHERE id='".$encomenda['mercado_id']."' LIMIT 0,1";
    $q        = cms_query($s);
    $mercado  = cms_fetch_assoc($q);
    
    $s    = "SELECT * FROM ec_paises WHERE id='".$encomenda['b2c_pais']."' LIMIT 0,1";
    $q    = cms_query($s);
    $pais = cms_fetch_assoc($q);    
    
    if($encomenda['entidade_faturacao']>0){
        $mercado['entidade_faturacao'] = $encomenda['entidade_faturacao'];    
    }    

    $s        = "SELECT * FROM ec_invoice_companies WHERE id='".$mercado['entidade_faturacao']."' LIMIT 0,1";
    $q        = cms_query($s);
    $entidade = cms_fetch_assoc($q);
    
    $imagem   = "sysimages/logo.png";
    if((int)$entidade['id']>0 && file_exists($_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR."images/cab_".$entidade['id'].".jpg")){
        $imagem   = "images/cab_".$entidade['id'].".jpg";
    }
    
    $x["response"]["logo"]          = $imagem;        
    
    $link_devolucao = $slocation."/account/index.php?id=16&code_order=".base64_encode($encomenda["id"])."&cdln=".base64_encode($row_enc_line["id"]);
   
    require_once($_SERVER["DOCUMENT_ROOT"]."/api/lib/shortener/shortener.php");
    $short_url = short_url($link_devolucao);
    $link_devolucao = $short_url->short_url;
    
    $PNG_TEMP_DIR = _ROOT.'/prints/IMGQRCODE';        
    $filename = $PNG_TEMP_DIR . "/ro_".$row_enc_line["id"].".jpg";
    
    if(!file_exists($filename)){
  
        $BASE_DIR = $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR . "plugins/billing/qrcode" . DIRECTORY_SEPARATOR;
        include_once $BASE_DIR . "qrlib.php";
        
        $errorCorrectionLevel = 'M';  
        $matrixPointSize = 8; 
        QRcode::png($link_devolucao, $filename, $errorCorrectionLevel, $matrixPointSize, 0);
    
    }
          
    $qrcode = "/prints/IMGQRCODE/ro_".$row_enc_line["id"].".jpg";
    $arr_unset[] = $qrcode;
    
    #barcode39
    require_once $_SERVER["DOCUMENT_ROOT"].DIRECTORY_SEPARATOR."api/lib/barcode39/Barcode39.php";
    $code = str_pad($encomenda["id"], 6, '0', STR_PAD_LEFT);
    
    $bc = new Barcode39("*".$code."*");
    $bc->barcode_text = false;
    $bc->barcode_bar_thick = 3;
    $bc->barcode_bar_thin = 1;
    $bc->barcode_height = 26;
    $bc->barcode_padding = 0;
    
    $barcode_name = "/barcode_".$encomenda["id"].".gif";
    $barcode_final_path = _ROOT.'/prints/IMGQRCODE'.$barcode_name;
    
    $bc->draw($barcode_final_path);
    
    $barcode_fac_name = "";
    
    $sql_fatura = "SELECT doc_nr FROM ec_invoices WHERE `order`='".$encomenda["id"]."' AND anulado=0 AND devolucao=0";
    $res_fatura = cms_query($sql_fatura);
    $row_fatura = cms_fetch_assoc($res_fatura);

    if(trim($row_fatura["doc_nr"])!=""){
        $doc_fac    = str_replace(" ", "", str_replace("/", "", $row_fatura["doc_nr"]));
        
        
        if($CUSTOM_PDF_GIFT_CODEBAR==1){
            $row_fatura["doc_nr"] = $doc_fac;    
        }
        
        $bc = new Barcode39("*".$doc_fac."*");
        $bc->barcode_text = false;
        $bc->barcode_bar_thick = 3;
        $bc->barcode_bar_thin = 1;
        $bc->barcode_height = 26;
        $bc->barcode_padding = 0;
        
        $barcode_fac_name = "/barcode_fac_".$encomenda["id"].".gif";
        $barcode_fac_final_path = _ROOT.'/prints/IMGQRCODE'.$barcode_fac_name;
        
        $bc->draw($barcode_fac_final_path);
    }
    
    $x["response"]["barcode"]         = '/prints/IMGQRCODE'.$barcode_name;
    $x["response"]["barcode_fac"]     = '/prints/IMGQRCODE'.$barcode_fac_name; 
    $x["response"]["code_barcode"]    = $code;
    $x["response"]["code_barcode_fac"]= $row_fatura["doc_nr"];
    $x["response"]["qrcode"]          = $qrcode; 
    $x["response"]["url_devolucao"]   = $link_devolucao;      

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/v1', false, _ROOT.'/temp_twig/');
    $html = $fx->printTwigTemplate("account_return_gift.htm", $x, true, $exp);
    
    $documentTemplate = '
    <!doctype html>      
    <html>
        <body>
            <div id="wrapper">
                '.utf8_encode($html).'
            </div>
        </body>
    </html>';

    $mpdf->WriteHTML($documentTemplate);

    $type_output = 'D';
    $save_cam = str_replace('/', '', $encomenda["order_ref"]).'_'.$row_enc_line["id"].'.pdf';
        
       
    $mpdf->Output($save_cam, $type_output);
    exit;    
}
?>
