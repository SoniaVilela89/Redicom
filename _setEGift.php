<?

function _setEGift($order_id=null){

    global $fx;
    global $pagetitle;
    global $slocation;
    global $eComm;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $LG;
    global $_exp;
    global $EGIFT_COM_PRODUTOS;
    global $API_CONFIG_PREF_EGIFT;
    
    $fx->LoadTwig('../lib/Twig/Autoloader.php', '../emails_marketing');

    if ($order_id > 0){
        $order_id = (int)$order_id;
    }else{
        $order_id = (int)params('order_id');
    }
    
    if($order_id==0){
        return serialize(array("0"=>"0"));  
    }
    
    $temp_email = call_api_func("get_line_table","email_templates", "id='5'");
                
    if( empty($temp_email) ){
        return serialize( Array("0"=>"0") );
    }


    # Validar se já foram emitidos egifts para esta encomenda
    $s_exists  = "SELECT id FROM ec_vales WHERE origin_order_id='".$order_id."' AND gift_card_id>0 LIMIT 0,1";
    $q_exists  = cms_query($s_exists);
    $vale_gift = cms_fetch_assoc($q_exists);  
    
    if ((int)$vale_gift['id']>0){
        return serialize(array("0"=>"0"));
    }
    
    
    
    $add_limit = ' LIMIT 1';
    if( (int)$EGIFT_COM_PRODUTOS == 1 ){
        $add_limit = '';
    }
    
    $s       = "SELECT * FROM ec_encomendas_lines WHERE order_id='".$order_id."' AND egift='1' ".$add_limit;
    $q       = cms_query($s);
    $num_enc = cms_num_rows($q);
    
    if( (int)$num_enc <= 0 ){
        return serialize(array("0"=>"0"));
    }
    
    while( $enc = cms_fetch_assoc($q) ){
        
        if (empty($enc)){
            return serialize(array("0"=>"0"));  
        }
            
        $template_file = 'eg';
        $new_version = 0;

        $sql_template = "SELECT campo_3 FROM `b2c_config_loja` WHERE `tipo` LIKE 'base' LIMIT 0,1";
        $res_template = cms_query($sql_template);
        $row_template = cms_fetch_assoc($res_template);

        if((int)$row_template["campo_3"] == 12){
            $template_file = 'eg_12';
            $new_version = 1;
        }
            
        $gift = unserialize($enc['egift_info']);        
        
        $LG   = $enc['idioma_user'];
    
        $template   = $temp_email['bloco'.$LG];
        $_assunto   = $temp_email['assunto'.$LG];
        
    
        $COUNTRY    = $_SESSION['_COUNTRY'] = $eComm->countryInfo($enc['pais_cliente']);
        $MARKET     = $_SESSION['_MARKET'] = $eComm->marketInfo($COUNTRY['id']);
        $MOEDA      = $_SESSION['_MOEDA'] = $eComm->moedaInfo($MARKET['moeda']);
    
    
        $temp_valor = call_api_func("get_line_table","ec_egift_valores", "id='".$gift['value']."'");
        
        $money      = call_api_func('moneyOBJ', $temp_valor['valor'], $temp_valor['moeda_id']);
        $decimais = $_SESSION['_MOEDA']['decimais'];
        if(fmod($money['value'], 1) === 0.00 && $new_version == 1){
            $decimais = 0;
        }

        $price      = $money['currency']['prefix'].number_format($money['value'], $decimais, $_SESSION['_MOEDA']['casa_decimal'], $_SESSION['_MOEDA']['casa_milhares'] ).$money['currency']['sufix'];
        
        $price      = str_replace("€", "&euro;", $price);
        $price      = str_replace("£", "&pound;", $price);
        $price      = str_replace("&euro;", " EUR", $price);
        $price      = str_replace("&pound;", " GBP", $price);    
        
        #Gerar o voucher
        $str            = microtime(true) *  (rand(100000,999999) / 100000);
        $str            = str_replace(".", "", $str);
        
        
        $prefixo = 'eGF';
        
        # 2024-07-22
        # A ser usado por Salsa
        if(trim($API_CONFIG_PREF_EGIFT)!='') $prefixo = $API_CONFIG_PREF_EGIFT;
        
        $code = $prefixo.substr($str,-6).strtoupper(substr(md5(str_replace(".", "", microtime(true) *  (rand(100000,999999) / 100000))),0,4));
    
        if($temp_valor['validade_meses']=='-1') $data_validade = '2999-01-01'; 
        else $data_validade = date("Y-m-d", strtotime("+".$temp_valor['validade_meses']." months"));
        
        $template       = str_ireplace("{DATA}", $data_validade, $template);
        $template       = str_ireplace("{CODIGO}", $code, $template);
        
        $template       = str_ireplace("{GIFT_TO}", $gift['name_address'], $template);
        $template       = str_ireplace("{GIFT_FROM}", $gift['name'], $template);
        $template       = str_ireplace("{GIFT_VALUE}", $price, $template);
        $template       = str_ireplace("{GIFT_CODE}", $code, $template);
        $template       = str_ireplace("{GIFT_MESSAGE}", $gift['message'], $template);
        $template       = str_ireplace("{GIFT_DATE}", $data_validade, $template);
        $template       = str_ireplace("{PAGETITLE}", $pagetitle, $template);
                 
        $egift = array(                   
                        "de"        => $gift['name'],
                        "para"      => $gift['name_address'],
                        "valor"     => $price,
                        "codigo"    => $code,
                        "mensagem"  => $gift['message']
        );
        

        $cartao         = call_api_func("get_line_table","ec_egift_desenho", "id='".$gift['card']."'");
        
 
        if($new_version == 1){
            $img_barcode = create_barcode39($code);
            $imagem_cartao  = _createImage_new_layout($cartao, $egift, $enc['id']);    #Linha da encomenda

            $egift["img_card"] = $imagem_cartao;
 
            $gift['message']      = nl2br($gift['message']);
            $gift['message']      = strip_tags($gift['message']);
            $gift['message']      = str_replace("\\n\\n", " ", $gift['message']);
            $gift['message']      = str_replace("\\r\\n", "\n", $gift['message']);
            $gift['message']      = str_replace("\\n", "\n", $gift['message']);
            $gift['message']      = str_replace("\\r", " ", $gift['message']);
            $gift['message']      = str_replace("\\t", " ", $gift['message']);
            $egift['mensagem']    = $gift['message'];

            $egift["img_barcode"] = $img_barcode;
            $egift["validade"] = $data_validade;
            $egift["msg_template"] = nl2br($template);
            $egift["name_file"] = base64_encode($enc["order_id"]."|||".$enc["id"]);

            $pdf_egift = generateEgiftPDF($egift);

            $open_file_data = array(
                "file" => $pdf_egift, 
                "name" => utf8_encode("E-gift").".pdf",
                "sys"   => 1
            );
    
            $link = $slocation."/api/open_file.php?params=".base64_encode( serialize( $open_file_data ) );        
            
            $exp_finalizar = nl2br(estr(676));

        }else{
            $imagem_cartao  = _createImage($cartao, $egift, $enc['id']);    #Linha da encomenda
            $link = $slocation;
            $exp_finalizar = nl2br(estr(126));
        }
        $go = cms_query("INSERT INTO ec_vales SET gift_card_id='".$gift['value']."',
                                                      codigo='".$code."',
                                                      data_validade='".$data_validade."',
                                                      origin_order_id='".$enc['order_id']."',
                                                      valor='".$temp_valor['valor']."',
                                                      moeda='".$temp_valor['moeda_id']."',
                                                      tipo='1',
                                                      obs='Emitido através da compra do egift na encomenda ".$enc['order_id']."'");
    
                                                            
        $y                = get_info_geral_email($LG, $MARKET, $campanha, $user);
        $y['LINK']        = $link;
        $y['IMAGEM']      = $imagem_cartao;
        $y['TITULO']      = nl2br(estr(204));
        $y['SUBTITULO']   = nl2br(estr(211));
        $y['DESCRICAO']   = $template;
        $y['FINALIZAR']   = $exp_finalizar;
        $y['PAGETITLE']   = $pagetitle;
        
        
        #Variaveis Extra :: Suporte CM #SCM-3416/2018 :: Sónia
        $y['GIFT_TO']       = $gift['name_address'];
        $y['GIFT_FROM']     = $gift['name'];
        $y['GIFT_VALUE']    = $price;
        $y['GIFT_CODE']     = $code;
        $y['GIFT_MESSAGE']  = $gift['message'];
        $y['GIFT_BARCODE']  = $img_barcode;
        $y['GIFT_VALIDATE'] = estr(672)." ".$data_validade;
    
        $content = cms_real_escape_string(serialize($y));
    
        saveEmailInBD_Marketing($gift['email'], $_assunto, $content, $enc['id_cliente'], 0, "E-gift", 1, 0, $template_file, 0, $y['view_online_code']);
    
        if($gift["send_to_adress"]>0){
            $send_to_date = "";
            if((int)$gift["send_time"] > 0 && trim($gift["send_day"]) != ""){
                $send_to_date = $gift["send_day"]." ".$gift["send_hour"].":00";
            }

            saveEmailInBD_Marketing($gift['email_address'], $_assunto, $content, $enc['id_cliente'], 0, "E-gift", 1, 0, $template_file, 0, $y['view_online_code'], 0, 0, '', $send_to_date);
                                    
        }
        
        #cms_query("UPDATE ec_encomendas SET tracking_status='103' WHERE id='".$order_id."'");
        #cms_query("UPDATE ec_encomendas_lines SET status='103', recepcionada='1' WHERE order_id='".$order_id."'");
        
        cms_query("INSERT ec_encomendas_log SET estado_novo='98', autor='API setEGift', encomenda='".$order_id."', obs='Criado vale ".$code."' ");
        
        cms_query("INSERT ec_encomendas_log SET estado_novo='98', autor='API setEGift', encomenda='".$order_id."', obs='EGift enviado para ".$gift['email']."' ");
        
        if($gift["send_to_adress"]>0) 
            cms_query("INSERT ec_encomendas_log SET estado_novo='98', autor='API setEGift', encomenda='".$order_id."', obs='EGift enviado para ".$gift['email_address']."' "); 
        
    }
       
    return serialize(array("0"=>"1"));

}



function _createImage($cartao, $egift, $order_id){


    if(trim($cartao['font'])=='') $cartao['font'] = 'RobotoCondensedRegular';
    if(trim($cartao['font_bold'])=='') $cartao['font_bold'] = 'RobotoBlack';
    
    if($cartao['font_bold']=='RobotoBold') $cartao['font_bold']='RobotoBlack';

    $fontname       = '../plugins/system/woff/'.$cartao['font'].'.ttf';
    $fontname_bold  = '../plugins/system/woff/'.$cartao['font_bold'].'.ttf';
    $quality        = 90;
    
    $orig_image     = "../images/gift_card_email".$cartao['id'].".jpg";
    $file           = $orig_image;
    $final_image    = str_replace(".jpg", "_final_".$order_id.".jpg", $orig_image);
    $im             = imagecreatefromjpeg($file);
    
    $rgb_cor = array('000', '000', '000');
    if($cartao['fundo']==2){
        $rgb_cor = array('255', '255', '255');
    }
    

    $cor = imagecolorallocate($im, $rgb_cor[0], $rgb_cor[1], $rgb_cor[2]);

                    
    imagettftext($im, 10, 0, 25, 30, $cor, $fontname, estr(151)." ".$egift['de'].'');
    imagettftext($im, 10, 0, 25, 50, $cor, $fontname, estr(152)." ".$egift['para']);
    imagettftext($im, 10, 0, 25, 200, $cor, $fontname, estr(153)."");
    imagettftext($im, 18, 0, 25, 225, $cor, $fontname_bold, $egift['valor']);
    imagettftext($im, 10, 0, 25, 250, $cor, $fontname, estr(154)." ".$egift['codigo']);
    
    $message      = $egift['mensagem'];
    $message      = nl2br($message);
    $message      = strip_tags($message);
    $message      = str_replace("\\n\\n", " ", $message);
    $message      = str_replace("\\r\\n", " ", $message);
    $message      = str_replace("\\n", "\n", $message);
    $message      = str_replace("\\r", " ", $message);
    $message      = str_replace("\\t", " ", $message);
    #$message_len  = strlen($message);
    
    $message_f = $message;
    $arr_msg = explode("\n", $message_f);

    $j = 0;
    $z = 0;
    foreach ($arr_msg as $key=>$value) {
        	
        $message = $value;

        
        $start = 0;
        for($i=(0+$z);$i<4;$i++){

            $end = "";        
            if( $i == 3 ){
                $end = "...";
            }
            
            $message_len  = strlen($message);

            if( $start >= $message_len ){
                $z = $i-1;
                $j+=14;
                break;
            }
            $str    = substr($message, $start);
            $_str   = _sentencesBreaker(trim($str), 40, $end);
            $start += strlen($_str);

            imagettftext($im, 10, 0, 25, (95+(14*$i))+$j, $cor, $fontname, $_str);
                   
            
        }

    }

    imagejpeg($im, $final_image, $quality);

    if (is_dir('../../storage-ha')) {
        $location_storage = str_replace("../", "../../storage-ha/", $final_image);
        copy($final_image, $location_storage);
    }
    
    $final_image = str_replace('../', '', $final_image);

    return $final_image;
} 

function _createImage_new_layout($cartao, $egift, $order_id){
    
    if(trim($cartao['font_bold'])=='') $cartao['font_bold'] = 'RobotoBlack';
    if($cartao['font_bold']=='RobotoBold') $cartao['font_bold']='RobotoBlack';

    $fontname       = '../plugins/system/woff/SegoeUI.ttf';
    $fontname_b     = '../plugins/system/woff/SegoeUI-Bold.ttf';
    $fontname_bold  = '../plugins/system/woff/'.$cartao['font_bold'].'.ttf';
    $quality        = 90;

    $orig_image     = "../images/gift_card_email".$cartao['id'].".jpg";
    $file           = $orig_image;
    $final_image    = str_replace(".jpg", "_final_".$order_id.".jpg", $orig_image);
    $im             = imagecreatefromjpeg($file);
    
    $rgb_cor = array('000', '000', '000');
    if($cartao['fundo']==2){
        $rgb_cor = array('255', '255', '255');
    }    

    $cor = imagecolorallocate($im, $rgb_cor[0], $rgb_cor[1], $rgb_cor[2]);

    imagettftext($im, 34, 0, 25, 120, $cor, $fontname_bold, $egift['valor']);
    if(trim($egift['de']) !="") imagettftext($im, 12, 0, 25, 157, $cor, $fontname, estr(151));
    if(trim($egift['para']) !="") imagettftext($im, 12, 0, 25, 180, $cor, $fontname, estr(152));
    if(trim($egift['de']) !="") imagettftext($im, 12, 0, 76, 157, $cor, $fontname_b, $egift['de']);
    if(trim($egift['para']) !="") imagettftext($im, 12, 0, 76, 180, $cor, $fontname_b, $egift['para']);

    imagejpeg($im, $final_image, $quality);

    if (is_dir('../../storage-ha')) {
        $location_storage = str_replace("../", "../../storage-ha/", $final_image);
        copy($final_image, $location_storage);
    }
    
    
    $final_image = str_replace('../', '', $final_image);

    return $final_image;
} 


function _sentencesBreaker($sentence, $len, $str) {
 
    $sentence         = strip_tags ($sentence, "<strong><b><i><br>");
    
    $stream           = "";
    $len_stream       = 0;
    
    $vetor            = explode(" ", $sentence);
    
    $number_of_words  = 0;
    $number_of_words  = count($vetor);
    
    $index = 0;
    while ($len_stream < $len) {
        $stream .= " " . $vetor[$index];
        $len_stream = strlen($stream);
        $index++;
    }
    
    if ($len_stream > $len) $stream .= $str;
    
    return $stream; 
}

function generateEgiftPDF(array $egift){
    
    global $CONFIG_TEMPLATES_PARAMS, $fx, $sslocation;

    $x = array();
    $x["response"]["shop"]          = call_api_func('OBJ_shop_mini');
    $x["response"]["egift"]         = $egift;
    $x["response"]["logo"]          = "sysimages/logo.png";
    if($CONFIG_TEMPLATES_PARAMS['site_version']>23){
        $x["response"]["logo"]          = "images/logo.svg";
    }
    $x["response"]["expressions"]   = call_api_func('getExpressions');
    
    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/plugins/templates_account/'.$CONFIG_TEMPLATES_PARAMS["account_version"], false, _ROOT.'/temp_twig/');
    $html = $fx->printTwigTemplate("account_egift_print.htm", $x, true, $exp);
    
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
    $mpdf->WriteHTML($documentTemplate);
    $mpdf->SetTitle('Egift');
        

    if (!file_exists(_ROOT.'/downloads/egifts/')) {
        mkdir(_ROOT.'/downloads/egifts/', 0777, true);
    }   

    $type_output = 'F';
    $save_cam = _ROOT.'/downloads/egifts/'.$egift["name_file"].'.pdf';                            
    $mpdf->Output($save_cam, $type_output);
    
    return $save_cam;
}



?>
