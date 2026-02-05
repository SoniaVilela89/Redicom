<?

function _addEGiftToBasket(){

    if( empty($_POST) ){
        return serialize( array(0) );
    }

    foreach( $_POST as $k => $v ){
        $_POST[$k] = safe_value(utf8_decode($v));
    }

    global $fx;
    global $LG;
    global $EGIFT_COM_PRODUTOS;
    global $userID;
    global $eComm;
    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $fx;
    global $sslocation;
    
    
    
    # Para egifts adicionados pelo ISA
    if( (int)$_POST['isa'] == 1 ){
        
        $user = get_line_table("`_tusers`", "`id`='" . (int)$_POST['user'] . "'", "`id`,`email`,`pais`");
        if( (int)$user['id'] < 1 ){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "User not found"));
        }

        $MARKET = get_line_table("`ec_mercado`", "CONCAT(',',`pais`,',') LIKE '%," . $user['pais'] . ",%'");
        if( (int)$MARKET['id'] < 1 ){
            return serialize(array("0" => "0", "error" => "2", "error_desc" => "Market not found"));
        }

        $COUNTRY = get_line_table("`ec_paises`", "`id`='" . $user['pais'] . "'");
        if( (int)$COUNTRY['id'] < 1 ){
            return serialize(array("0" => "0", "error" => "3", "error_desc" => "Country not found"));
        }

        $MOEDA = get_line_table("`ec_moedas`", "`id`='" . $MARKET['moeda'] . "'");
        if( (int)$MOEDA['id'] < 1 ){
            return serialize(array("0" => "0", "error" => "4", "error_desc" => "Currency not found"));
        }

        $userID = $user['id'];
        $_SESSION['EC_USER']['email'] = $_POST['email'] = $user['email'];

    }

    if( !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) || (int)$_POST['card'] < 1 ){
        return serialize(array("0"=>"0"));
    }

    // COMENTADO 2022-12-07 - Para compatibilizar com novo template. O frontend já esta a validar
    // if ($_POST['send_to_adress']>0){ 
    //     if(!filter_var($_POST['email_address'], FILTER_VALIDATE_EMAIL) || !filter_var($_POST['confirm_email_address'], FILTER_VALIDATE_EMAIL) || $_POST['email_address']!=$_POST['confirm_email_address']) {
    //         return serialize(array("0"=>"0"));
    //     }
    // }

    #Para evitar a submissão excessiva de formularios

    
    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);     
    
    if((int)$EGIFT_COM_PRODUTOS==0){
        $eComm->removeInsertedProducts($userID);
    }else{
        $go = @cms_query("DELETE FROM `ec_encomendas_lines` WHERE id_cliente='$userID' AND order_id='0' AND status='0' AND egift=1 ");  
    }

    $priceList  = $MARKET['lista_preco'];
    
    $row        = cms_fetch_assoc( cms_query("SELECT * FROM ec_egift_valores WHERE id='" . $_POST['value'] . "' LIMIT 0,1") );
    if( $row['id'] < 1 ){
        $row  = cms_fetch_assoc( cms_query("SELECT * FROM ec_egift_valores ORDER BY valor ASC LIMIT 0,1") );
    }

    $row_card = cms_fetch_assoc( cms_query("SELECT * FROM ec_egift_desenho WHERE id='" . $_POST['card'] . "' LIMIT 0,1") );  

    $arr                              = array();
    $arr['page_id']                   = $_POST['id'];
    $arr['page_cat_id']               = 0;
    $arr['id_cliente']                = $userID;
    $arr['status']                    = "0";
    $arr['pid']                       = $row['ref'];
    $arr['ref']                       = $row['ref'];
    $arr['sku_family']                = $row['ref'];
    $arr['sku_group']                 = $row['ref'];
    $arr['nome']                      = 'E-gift: '.$row_card['nome'.$LG];
    $arr['unidade_portes']            = 0;
    
    if( trim($_POST['name_address']) != "" ){
        $arr['composition']           = estr(131) . ': ' . $_POST['name_address'];
    }
    
    $arr['cor_id']                    = "";
    $arr['cor_cod']                   =  "";
    $arr['cor_name']                  = "";
    $arr['peso']                      = "";
    $arr['tamanho']                   = "";
    $arr['qnt']                       = 1;
    $arr['data']                      = date("Y-m-d");
    $arr['datahora']                  = date("Y-m-d H:i:s");
    $arr['valoruni']                  = $row['valor'];
    $arr['pais_iso']                  = $COUNTRY['country_code'];
    $arr['moeda']                     = $MOEDA['id'];
    $arr['taxa_cambio']               = $MOEDA['cambio'];
    $arr['moeda_simbolo']             = $MOEDA['abreviatura'];
    $arr['moeda_prefixo']             = $MOEDA['prefixo'];
    $arr['moeda_sufixo']              = $MOEDA['sufixo'];

    $arr['moeda_decimais']            = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']        = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares']       = $MOEDA['casa_milhares'];

    $arr['mercado']                   = $MARKET['id'];
    $arr['deposito']                  = $MARKET['deposito'];
    $arr['lista_preco']               = $priceList;
    
    $arr_cookie_cpn                   = getCookieCPN();
    
    $arr['tracking_campanha_url_id']  = implode(',', array_keys($arr_cookie_cpn));
    $arr['email']                     = $_SESSION['EC_USER']['email'];
    $arr['idioma_user']               = $LG;
    $arr['pais_cliente']              = $COUNTRY['id'];
    $arr['egift']                     = 1;      
    $arr['egift_info']                = serialize($_POST);     
    
    $arr['iva_taxa_id']               = 4;
    
    $img_prd        = get_image_SRC("images/gift_card_min".$row_card['id'].".jpg", 300, 300, 3);

    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    set_status(3,$arr);
    $eComm->addToBasket($arr);

    ob_start();
    $string = tracking_from_tag_manager('addToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'], "SKU_PRODUTO" => $arr['ref'], "SKU_GROUP" => $arr['sku_group'], "SKU_FAMILY" => $arr['sku_family'], "VALOR_PRODUTO" => $arr['valoruni'], "DESCRICAO_PRODUTO" => "" , "NOME_PRODUTO" => cms_real_escape_string($row_card['nome'.$LG]) ));
    echo $string;
    ob_clean();
    
    
    

    return serialize( array(1) );

}

?>
