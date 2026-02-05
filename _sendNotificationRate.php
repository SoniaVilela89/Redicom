<?
     
function _sendNotificationRate($order_id=null){

    global $fx;
    global $pagetitle;
    global $slocation;
    global $eComm;
    global $LG;
    global $CONFIG_TEMPLATES_PARAMS;
    global $pagetitle;
                                                            
    if($CONFIG_TEMPLATES_PARAMS["detail_allow_review"]==0) return serialize(array("0"=>"0"));

    $fx->LoadTwig(_ROOT.'/lib/Twig/Autoloader.php', _ROOT.'/emails_marketing', false, _ROOT.'/temp_twig/');

    if ($order_id > 0){
        $order_id = (int)$order_id;
    }else{
        $order_id = (int)params('order_id');
    }
                  

    if($order_id>0){
        $s = "SELECT * FROM ec_encomendas WHERE id='".$order_id."' LIMIT 0,1";
    }else{
        $s = "SELECT ec_encomendas.*
                FROM ec_encomendas
                INNER JOIN ec_encomendas_lines ON ec_encomendas_lines.order_id=ec_encomendas.id AND egift='0' AND ref!='PORTES'
                WHERE cliente_final='1' ORDER BY ec_encomendas.id DESC LIMIT 0,1";
    }

      
    $q    = cms_query($s);
    $enc  = cms_fetch_assoc($q);
    
    if($enc['id']<1){
        return serialize(array("0"=>"0"));
    }
    
    
    
    $email_body   = call_api_func("get_line_table","ec_email_templates", "id='21' and hidden='0'");
    
    if((int)$email_body['id']<1){
        return serialize(array("0"=>"0"));
    }
    
   
    
    $_SESSION['EC_USER']['id'] = $enc['cliente_final'];    
    
    
    if($order_id>0){
    
        # Verificar se o email já foi enviado
        $s    = "SELECT id FROM ec_encomendas_props WHERE order_id='".$enc['id']."' AND property='RID' LIMIT 0,1";
        $q    = cms_query($s);
        $rev  = cms_fetch_assoc($q);
        
        if((int)$rev['id']>0){
            return serialize(array("0"=>"0"));
        }
        
        cms_query("INSERT INTO ec_encomendas_props (order_id, property, property_value) VALUES ('".$enc['id']."', 'RID', NOW()) ");
        
    }    

    #$s      = "SELECT * FROM ec_encomendas_lines WHERE order_id='".$enc['id']."' AND ref!='PORTES'";
    $s      = "SELECT * FROM ec_encomendas_lines WHERE order_id='".$enc['id']."' AND ref!='PORTES' AND qnt > 0 AND recepcionada>0";
    $review = cms_query($s);
    
    $num    = cms_num_rows($review);
    if($num<1){
        return serialize(array("0"=>"0"));
    }
   

    $info_basket              = array();
    $info_basket['products']  = array();

    while ( $arr = cms_fetch_assoc($review) ){
        $info_basket['products'][$arr['ref']] = $arr;
    }


    # MARKET
    $COUNTRY  = $_SESSION['_COUNTRY'] = $eComm->countryInfo($enc['b2c_pais']);
    $MARKET   = $_SESSION['_MARKET']  = $eComm->marketInfo($COUNTRY['id']);
    $MOEDA    = $_SESSION['_MOEDA']   = $eComm->moedaInfo($MARKET['moeda']);
    
    
    $id_cliente   = $enc['cliente_final'];
    $sql_user     = cms_query("SELECT * FROM _tusers WHERE id='$id_cliente' LIMIT 0,1");
    $_usr         = cms_fetch_assoc($sql_user);
    
    
    $userID = $_usr['id'];   
    $_SESSION['EC_USER']['tipo']              = $_usr['tipo'];
    $_SESSION['EC_USER']['tipo_utilizador']   = $_usr['tipo_utilizador'];  
    $_SESSION["EC_USER"]["sem_registo"]       = $_usr['sem_registo'];
    $_SESSION['EC_USER']['registed_at']       = $user['registed_at'];  
    
    
    $segmentos = explode(',', $_SESSION["EC_USER"]['tipo']);    
    asort($segmentos);
    foreach($segmentos as $k => $v){
        if($v>499){
            unset($segmentos[$k]);
        }
    }
    $_SESSION['segmentos'] = implode(",", $segmentos);
    
    
          

    $LANGUAGE = call_api_func("get_line_table","ec_language", "id='".$COUNTRY["idioma"]."'");

    if($LANGUAGE["code"]=='es') $LANGUAGE["code"]='sp';
    elseif($LANGUAGE["code"]=='en') $LANGUAGE["code"]='gb';

    $LG = $_SESSION['LG'] = $LANGUAGE["code"];


    require_once '_getProductSimple.php';

    $produtos = array();
    foreach($info_basket['products'] as $key => $value)
    {       
        
        $pid_final = $value['pid'];
        $arr_pid = explode("|||", $value['pid']);
        if(count($arr_pid)>1){
            $pid_final = $arr_pid[1];
        }
        
        $y    = _getProductSimple(5,0,$pid_final,1);

        $x    = unserialize($y);
        
        $prod = $x['product'];
        
        if( $order_id>0 && $prod["review_product"]["allow_review"] == 0 && $CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1 ) continue;

        get_layout_prod($produtos, $prod, 0);

    }

    if(count($produtos)<1) return serialize(array("0"=>"0"));
    
   
    $base_codigo = base64_encode($campanha['id'].'|||'.$_usr['id'].'|||'.$_usr['email']);
    $more_link .= '&m2code='.$base_codigo;

    $produtos_f = array();
    foreach($produtos as $k => $v){
    
        $path_emails = '';
        if($PATH_EMAILS_LOCATION!='') $path_emails=$PATH_EMAILS_LOCATION;
        
        $v['link'] = $path_emails.$slocation.'/index.php?id=27&pag_id=5&prod_id='.$v['pid'].$more_link;
    
        $produtos_f[] = $v;
    }


    $criptar      = $_usr['id'].$_usr['email'].$_usr['password'];
    $hash         = hash('ripemd160', $criptar);

    $link_review  = $slocation."/api/action_review.php?id=".$id_cliente."&token=".$hash."&enc=".$enc["id"];

   
    
    #Variáveis antigas para compatibilizar - descontinuadas
    $email_body['bloco'.$LG] = str_ireplace("{NOME}", $_usr['nome'], $email_body['bloco'.$LG]);

    $email_body['bloco'.$LG] = str_ireplace("{CLIENT_NAME}", $_usr['nome'], $email_body['bloco'.$LG]);
    $email_body['bloco'.$LG] = str_ireplace("{PAGETITLE}", $pagetitle, $email_body['bloco'.$LG]);


    $extra_link_external = '';
    
    # REVIEW EXTERNAL #
    $rev_sql = cms_query('SELECT * FROM b2c_config_loja WHERE id="8" LIMIT 0,1');
    $rev_row = cms_fetch_assoc($rev_sql);
             

    if($rev_row["campo_1"]==1){
        switch ($rev_row["campo_2"]) {
            case "ekomi":
                $arr_external = reviewEkomi($enc);
                if(trim($arr_external["html"])!=""){
                    $email_body['bloco'.$LG] = utf8_decode($arr_external["html"]);
                }
                if(trim($arr_external["subject"])!=""){
                    $email_body['assunto'.$LG]= utf8_decode($arr_external["subject"]);
                }
                $email_body['bloco'.$LG] = str_ireplace("{vorname}", $enc['nome_cliente'], $email_body['bloco'.$LG]);
                $email_body['bloco'.$LG] = str_ireplace("{nachname}", "", $email_body['bloco'.$LG]);
                $email_body['bloco'.$LG] = str_ireplace("{ekomilink}", $enc["reviews_external"], $email_body['bloco'.$LG]);
                $link_review = $arr_external['linkToEmail'];
                
                $extra_link_external='&utm_medium=email&utm_source=Redicom%20Marketing%20Automation&utm_campaign=reviews';
                
                break;
            case "Opiniões Verificadas":             
                reviewopv($enc);
                exit;
            break;
        }

    }
  
    $y              = get_info_geral_email($LG, $MARKET, $campanha, $_usr, $extra);
    $y['LINK']      = $link_review.$extra_link_external;
    $y['PRODUTOS']  = $produtos_f;
    $y['negar_exp'] = '';
    $y['TITULO']    = $email_body['assunto'.$LG];
    $y['SUBTITULO'] = $email_body['bloco'.$LG];
    $y['FINALIZAR'] = nl2br(estr2(314));
        
    if($order_id>0){
    
        if($rev_row["campo_1"]==1 && $rev_row["campo_2"]=="ekomi"){
        
            $html = $fx->printTwigTemplate("email_rw.html", $y, true, $_exp);

            foreach ($y[PRODUTOS] as $key=>$value) {
                $arr_skus[]=$value[ref];
            }
    
            $enviado = 0;        
            if(trim($link_review) == '')    $enviado = -1;
            
            #Insere numa tabela que é posteriormente enviado por cronjob
            $sql_insert=sprintf("INSERT INTO notification_rate SET
                                  `enviado`  = '%s',
                                  `skus`     = '%s',
                                  `subject`  = '%s',
                                  `html`     = '%s',
                                  `emailto`  = '%s',
                                  `user_id`  = '%d',
                                  `order_id` = '%d',
                                  `datahora` = NOW()",
                              $enviado,implode(",",$arr_skus),$email_body['assunto'.$LG],urlencode($html),$enc['email_cliente'],$_usr['id'],$order_id);
                                         
            cms_query($sql_insert);
            
        }else{
            $content = cms_real_escape_string(serialize($y));
            saveEmailInBD_Marketing($enc['email_cliente'], $email_body['assunto'.$LG], $content, $enc['cliente_final'], 0, "Reviews", 1, 0, 'rw', 0, $y['view_online_code']);
        }
        
    }else{
        $y['path'] = '../../';
        
        $html = $fx->printTwigTemplate("email_rw.html", $y, true, $_exp);
        echo $html;
        exit;
    }

    return serialize(array("0"=>"1"));
}


function reviewEkomi($row){

    $sql        = "SELECT * FROM b2c_config_loja WHERE id='9'";
    $res        = cms_query($sql);
    $row_ekomi  = cms_fetch_assoc($res);
    if($row_ekomi["campo_1"]=="" || $row_ekomi["campo_2"]==""){
        return;
    }


    $arr_prod     = array();
    $sql_detail   = "SELECT * FROM  ec_encomendas_lines WHERE order_id='".$row["id"]."' AND ref!='PORTES'";
    $res_detail   = cms_query($sql_detail);
    while($row_detail = cms_fetch_assoc($res_detail)){
        $params = array(
            "auth"          =>  $row_ekomi["campo_1"]."|".$row_ekomi["campo_2"],
            "interface_id"  =>  $row_ekomi["campo_1"],
            "interface_pw"  =>  $row_ekomi["campo_2"],
            "version"       =>  "cust-1.0.0",
            "type"          =>  "json",
            "carset"        =>  "utf-8",
            "product_id"    =>  $row_detail["ref"],
            "product_name"  =>  $row_detail["nome"]
        );

        $arr_prod[] = $row_detail["ref"];

        $method     = "putProduct";
        $url        = "http://api.ekomi.de/v3/";
        $url        = $url . $method . '?' . http_build_query($params);
        $curl       = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 600);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);

        try {
            $result = curl_exec($curl);
            $result = json_decode($result);

            if($result->done!=1){
                return;
            }
        }
        catch (\Exception $e){
            throw $e;
            return;
        }
    }

    $params = array(
        "auth"          =>  $row_ekomi["campo_1"]."|".$row_ekomi["campo_2"],
        "interface_id"  =>  $row_ekomi["campo_1"],
        "interface_pw"  =>  $row_ekomi["campo_2"],
        "version"       =>  "cust-1.0.0",
        "type"          =>  "json",
        "carset"        =>  "utf-8",
        "email"         =>  $row["email_cliente"],
        "fistname"      =>  $row["nome_cliente"],
        "lastname"      =>  $row["apelido_cliente"],
        "client_id"     =>  $row["cliente_final"]
    );
    $method   = "putClient";
    $url      = "http://api.ekomi.de/v3/";
    $url      = $url . $method . '?' . http_build_query($params);
    $curl     = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 600);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);

    try {
        $result = curl_exec($curl);
        $result = json_decode($result);

        if($result->done!=1){
            return;
        }
    }
    catch (\Exception $e){
        throw $e;
        return;
    }

    $prods  = implode("+", $arr_prod);
    $params = array(
        "auth"          =>  $row_ekomi["campo_1"]."|".$row_ekomi["campo_2"],
        "interface_id"  =>  $row_ekomi["campo_1"],
        "interface_pw"  =>  $row_ekomi["campo_2"],
        "version"       =>  "cust-1.0.0",
        "type"          =>  "json",
        "carset"        =>  "utf-8",
        "order_id"      =>  $row["id"],
        "product_ids"   =>  $prods
    );
    $method   = "putOrder";

    $url      = "http://api.ekomi.de/v3/";
    $url      = $url . $method . '?' . http_build_query($params);
    $curl     = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 600);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);

    try {
        $result = curl_exec($curl);
        $result = json_decode($result);

        if($result->done!=1){
            return;
        }
        $sql = "UPDATE ec_encomendas SET reviews_external='".$result->link."' WHERE id='".$row["id"]."'";        
        cms_query($sql);
        $linkToEmail = $result->link;
    }
    catch (\Exception $e){
        throw $e;
        return;
    }

    $params = array(
        "auth"          =>  $row_ekomi["campo_1"]."|".$row_ekomi["campo_2"],
        "interface_id"  =>  $row_ekomi["campo_1"],
        "interface_pw"  =>  $row_ekomi["campo_2"],
        "version"       =>  "cust-1.0.0",
        "type"          =>  "json",
        "carset"        =>  "utf-8",
        "order_id"      =>  $row["id"],
        "client_id"     =>  $row["cliente_final"]
    );
    $method   = "assignClientOrder";

    $url      = "http://api.ekomi.de/v3/";
    $url      = $url . $method . '?' . http_build_query($params);
    $curl     = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 600);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);

    try {
        $result = curl_exec($curl);
        $result = json_decode($result);
    }
    catch (\Exception $e){
        throw $e;
        return;
    }

    $params = array(
        "auth"          =>  $row_ekomi["campo_1"]."|".$row_ekomi["campo_2"],
        "interface_id"  =>  $row_ekomi["campo_1"],
        "interface_pw"  =>  $row_ekomi["campo_2"],
        "version"       =>  "cust-1.0.0",
        "type"          =>  "json",
        "carset"        =>  "utf-8"
    );
    $method   = "getSettings";

    $url      = "http://api.ekomi.de/v3/";
    $url      = $url . $method . '?' . http_build_query($params);
    $curl     = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 600);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 600);

    try {
        $result = curl_exec($curl);
        $result = json_decode($result);

        $array_return = array(
            "subject"     => $result->mail_subject,
            "html"        => $result->mail_html,
            "linkToEmail" => $linkToEmail
        );
        return $array_return;
        if($result->done!=1){
            return;
        }

    }
    catch (\Exception $e){
        throw $e;
        return;
    }

}

function reviewopv($row){

    global $slocation, $LG, $idiomas_possiveis;
  

    $sql        = "SELECT * FROM b2c_config_loja WHERE tipo='reviews_external_opv' and campo_4 = '".$idiomas_possiveis[$LG]."'";
    $res        = cms_query($sql);
    $row_opv  = cms_fetch_assoc($res);
    
    if($row_opv["campo_1"]=="" || $row_opv["campo_2"]==""){
        
         $sql         = "SELECT * FROM b2c_config_loja WHERE tipo='reviews_external_opv' and campo_4 = 'pt'";
         $res         = cms_query($sql);
         $row_opv     = cms_fetch_assoc($res);
         
         if($row_opv["campo_1"]=="" || $row_opv["campo_2"]==""){
          return; 
         }
         
    }
       
    $ID_WEBSITE = $row_opv["campo_1"]; 
    $SECURE_KEY = $row_opv["campo_2"];     
    $URL_AV     = $row_opv["campo_3"];           

    $arr_prod     = array();
    $sql_detail   = "SELECT * FROM  ec_encomendas_lines WHERE order_id='".$row["id"]."' AND ref!='PORTES'";
    $res_detail   = cms_query($sql_detail);
     
    while($row_detail = cms_fetch_assoc($res_detail)){
      
      $sql_prod = "SELECT ean FROM registos WHERE id='".$row_detail["pid"]."' AND ean!='' LIMIT 0,1";
      $res_prod = cms_query($sql_prod);
      $row_prod = cms_fetch_assoc($res_prod);
      
      $ean = '';
      if(trim($row_prod["ean"])) $ean = $row_prod["ean"];
            
      $poducts[] = array(
        'id_product'           => $row_detail['sku_group'],
        'name_product'         => utf8_encode($row_detail['nome']), //Required - Product Name
        'url_product'          => $slocation.'/index.php?id=5&pid='.$row_detail['pid'],
        'url_product_image'    => $row_detail['image'],
        'GTIN_UPC'             => '',
        'GTIN_EAN'             => $ean,
        'GTIN_JAN'             => '',
        'GTIN_ISBN'            => '',
        'MPN'                  => 'MPN',
        'sku'                  => $row_detail['ref'],
        'brand_name'           => ''
      );      
    
    }

    $descNotification =  array(
            'query'            => 'pushCommandeSHA1',    
            'order_ref'        => $row['id'],         
            'email'            => $row['email_cliente'],        
            'lastname'         => utf8_encode($row['nome_cliente']),                
            'firstname'        => utf8_encode($row['nome_cliente']),          
            'order_date'       => $row['data'],
            'delay'            => '0',           
            'PRODUCTS'         => $poducts,
            'sign'             => '',
    );
    

    $descNotification['sign']=SHA1($descNotification['query'].$descNotification['order_ref'].$descNotification['email'].$descNotification['lastname'].$descNotification['firstname'].$descNotification['order_date'].$descNotification['delay'].$SECURE_KEY);

    $encryptedNotification=http_build_query(
        array(
            'idWebsite' => $ID_WEBSITE,
            'message' => json_encode($descNotification)
        )
    );

    $postNotification = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $encryptedNotification
        )
    );

    $contextNotification = stream_context_create($postNotification);

    $resultNotification = file_get_contents($URL_AV.'?action=act_api_notification_sha1&type=json2', false, $contextNotification);

}

?>
