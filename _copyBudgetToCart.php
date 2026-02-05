<?

function _copyBudgetToCart($budget_id=0, $products_to_copy=null){

    if( (int)$budget_id <= 0 ){
        $budget_id = (int)params('budget_id');
    }

    if( is_null($products_to_copy) ){

        if( !empty($_POST) ){
            $products_to_copy = $_POST;
        }else{
            $products_to_copy = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( $budget_id <= 0 || empty($products_to_copy) || is_null($products_to_copy) ){
        return serialize(['success' => 0]);
    }

    $budget_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets` WHERE `id`='.$budget_id) );

    if( !isset( $budget_info['id'] ) ){
        return serialize(['success' => 0]);
    }


    global $eComm, $LG, $db_name_cms;
    

    $currencies_info       = [];
    $countries_info        = [];
    $markets_info          = [];
    
    $page_master = cms_fetch_assoc( cms_query("SELECT `catalogo` FROM `$db_name_cms`.`_trubricas` WHERE `id`=97") );
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addToBasket.php';

    foreach( $products_to_copy as $product_info){

        $product_budget = cms_fetch_assoc( cms_query("SELECT * FROM `budgets_lines` WHERE `budget_id`=".$budget_id." AND `id`=".$product_info['id']) );
        
        if( (int)$product_budget['product_type'] != 1 ){
            continue;
        }
    
        $catalogo_id = 0;
        if( $page_master["catalogo"] > 0 ){
            $catalogo_id = $page_master["catalogo"];
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }
        
        
        preparar_regras_carrinho($catalogo_id);
        
        if((int)$GLOBALS["REGRAS_CATALOGO"]==0) preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);
  
  
        $catalogo_id = (int)$GLOBALS["REGRAS_CATALOGO"];
        
        if( (int)$product_budget['product'] > 0 ){

            if( $budget_info['type'] == 1 ){

                if( !isset( $currencies_info[ $product_budget['currency_id'] ] ) || empty( $currencies_info[ $product_budget['currency_id'] ] ) ){
                    $currencies_info[ $product_budget['currency_id'] ] = cms_fetch_assoc( cms_query("SELECT * FROM `ec_moedas` WHERE `id`=".$product_budget['currency_id']) );
                }

                if( !isset( $countries_info[ $product_budget['country_id'] ] ) || empty( $countries_info[ $product_budget['country_id'] ] ) ){
                    $countries_info[ $product_budget['country_id'] ] = cms_fetch_assoc( cms_query("SELECT `id`, `country_code` FROM `ec_paises` WHERE `id`=".$product_budget['country_id']) );
                }

                if( !isset( $markets_info[ $product_budget['market_id'] ] ) || empty( $markets_info[ $product_budget['market_id'] ] ) ){
                    $markets_info[ $product_budget['market_id'] ] = cms_fetch_assoc( cms_query("SELECT `id`, `deposito` FROM `ec_mercado` WHERE `id`=".$product_budget['market_id']) );
                }
                
                
                $currency_info = $currencies_info[ $product_budget['currency_id'] ];
                $country_info  = $countries_info[ $product_budget['country_id'] ];
                $market_info   = $markets_info[ $product_budget['market_id'] ];
                
                

                $arr = getBudgetProductInfoToCart($product_budget, $currency_info, $country_info, $market_info, $product_budget['quantity'], $catalogo_id, $budget_info['type']);
                if (!empty($arr)) {
                    $eComm->addToBasket($arr);
                }

            }else{
                _addToBasket($page_master['id'], 0, $product_budget['product'], $product_info['qtd'], 0, 0, 0, 0, 0, 0, $catalogo_id);
            }
            
            // $arr = getBudgetProductInfoToCart($product_budget, $currency_info, $country_info, $market_info, $product_info['qtd'], $catalogo_id);
        }/*else{
            $arr = getBudgetServiceInfoToCart($product_budget, $currency_info, $country_info, $market_info, $product_info['qtd'], $catalogo_id);
        }

        if( !empty($arr) ){
            $eComm->addToBasket($arr);
        }*/

    }

    $state_prefix = 'Orçamento copiado';
    if( $budget_info['type'] == 1 ){
        $state_prefix = 'Proposta copiada';  
    }

    $state_observation = $state_prefix.' para o carrinho';

    cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES(".$budget_id.", '".$state_observation."')");
    
    $new_status  = 4;
    $status_info = cms_fetch_assoc( cms_query('SELECT * FROM `budgets_status` WHERE `id`='.$new_status) );

    $update_success = cms_query( "UPDATE `budgets` SET `status`=".$new_status." WHERE `id`=".$budget_id );

    if( !$update_success ){
        return serialize(['success' => false]);
    }

    $state_prefix = 'Orçamento colocado ';
    if( $budget_info['type'] == 1 ){
        $state_prefix = 'Proposta colocada';
    }

    $client_name = $_SESSION['EC_USER']['nome'];
    if( (int)$_SESSION['EC_CLIENTE']['type'] > 0 ){
        $client_name = $_SESSION['EC_CLIENTE']['nome'];
    }
    
    $state_observation = $state_prefix.' no estado: '.$status_info['name'.$LG].' por '.$client_name;

    cms_query("INSERT INTO `budgets_logs` (`budget_id`, `observation`) VALUES(".$budget_id.", '".$state_observation."')");

    $budget_info['status_info'] = $status_info;

    return serialize(['success' => $update_success, 'payload' => $budget_info]);

}


function getBudgetServiceInfoToCart($product_budget, $currency_info, $country_info, $market_info, $qtd=1, $catalogo=0){
    
    global $CHECKOUT_LAYOUT_VERSION, $LG, $sitelocation, $fx;
    
    if( $qtd <= 0 ){
        return [];
    }
    
    $arr                    = array();
    $arr['status']          = 0;
    $arr['page_id']         = 5;
    $arr['page_cat_id']     = $catalogo;

    $arr['data']            = date("Y-m-d");
    $arr['datahora']        = date("Y-m-d H:i:s");

    $arr['id_cliente']      = $_SESSION['EC_USER']['id'];
    $arr['email']           = $_SESSION['EC_USER']['email'];
    $arr['idioma_user']     = $LG;
    
    $service_name_sku       = ucwords(clearVariable($product_budget['product_name']));
    $service_name_sku_parts = explode(' ', $service_name_sku);
    $limit_words_count      = 3;
    $min_word_chars         = 3;
    $service_sku            = '';
    foreach( $service_name_sku_parts as $sku_part ){
        
        if( $limit_words_count == 0 ){
            break;
        }else if( strlen($sku_part) < $min_word_chars ){
            continue;
        }
        
        $service_sku .= $sku_part;
        $limit_words_count--;
        
    }
    
    $arr['pid']                 = $product_budget['id'].'|||';
    $arr['ref']                 = $service_sku;
    $arr['sku_family']          = $service_sku;
    $arr['sku_group']           = $service_sku;
    $arr['nome']                = $product_budget['product_name'];

    $arr['qnt']                 = $qtd;
    $arr['valoruni']            = $product_budget['final_price_uni'];

    $arr['mercado']             = $product_budget['market_id'];

    $arr['pais_cliente']        = $country_info['id'];
    $arr['pais_iso']            = $country_info['country_code'];

    $arr['moeda']               = $currency_info['id'];
    $arr['taxa_cambio']         = (float)$currency_info['cambio'] == 0 ? 1 : $currency_info['cambio'];
    $arr['moeda_simbolo']       = $currency_info['abreviatura'];
    $arr['moeda_prefixo']       = $currency_info['prefixo'];
    $arr['moeda_sufixo']        = $currency_info['sufixo'];
    $arr['moeda_decimais']      = $currency_info['decimais'];
    $arr['moeda_casa_decimal']  = $currency_info['casa_decimal'];
    $arr['moeda_casa_milhares'] = $currency_info['casa_milhares'];

    $arr['iva_taxa_id']         = 0;
    
    $img_selected = $_SERVER['DOCUMENT_ROOT']."/sysimages/no-image4.jpg";
    if( (int)$CHECKOUT_LAYOUT_VERSION == 0 ){
        $img_prd = $fx->makeimage($img_selected,160,200,0,0,2,'FFFFFF','','JPG',0,'','FFFFFF');
    }else{
        $img_prd = $fx->makeimage($img_selected,200,200,0,0,2,'FFFFFF','','JPG',0,'','FFFFFF');
    }
    
    $arr['image'] = $sitelocation.str_ireplace("../", "/", $img_prd);

    #0 - produto normal; 1 - produto configurador avançado; 2 - serviço que não permite desconto de campanha; 3 - produto orçamentado; 6 - serviço orçamentado
    $arr['tipo_linha'] = 6; # tipo de linha descontinuado por estar a ser utilizado para outro fim!
    
    return $arr;

}


function getBudgetProductInfoToCart($product_budget, $currency_info, $country_info, $market_info, $qtd=1, $catalogo=0, $budget_type=0){
    
    global $sslocation, $B2B, $CONFIG_IMAGE_SIZE, $CHECKOUT_LAYOUT_VERSION, $LG;

    if( $qtd <= 0 ){
        return [];
    }
    
    $prod = cms_fetch_assoc( cms_query("SELECT * FROM `registos` WHERE `id`=".$product_budget['product']) );
    $pack = 0;

    $ids_depositos = $market_info['deposito'];
    if((int)$B2B>0){
        if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!='') $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
    }

    # 2021-06-29
    # Unidades de venda
    # Ficha de produto multiplicar preço automaticamente pelas quantidades de venda, mas não para a quantidade
    if((int)$prod['package_price_auto']==3 && $prod['units_in_package']>0 && $prod['units_in_package']!=1 ){
        $pack = 3;
    }
    
    if((int)$prod['package_price_auto']==1 && (int)$prod['units_in_package']>1){
        $qtd *= (int)$prod['units_in_package'];
        $pack = 3;            
    }
    
    


    $cor  = getColor($prod['cor'], $prod['sku']);

    if( $prod['material'] > 0 ){
        $matR = getMaterial($prod['material']);

        $cor['short_name']  = $matR['name'].' '.$cor['short_name'];
        $cor['long_name']   = $matR['name'].' '.$cor['long_name'];

        if($cor['image']['alt']!=''){
            $cor['image']['alt'] = $matR['name'].' '.$cor['image']['alt'];
        }
    }

    $sizes   = getTamanho($prod['tamanho']);
    $imagens = getImagens($prod['sku'], $prod['sku_group'], $prod['sku_family']);

    $img_list_0 = '';
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU/".$prod['sku']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU/".$prod['sku']."_0.jpg";
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU_GROUP/".$prod['sku_group']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU_GROUP/".$prod['sku_group']."_0.jpg";
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU_FAMILY/".$prod['sku_family']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU_FAMILY/".$prod['sku_family']."_0.jpg";

    if($img_list_0!='') {
        $img_selected = $img_list_0;
    } else {
        $img_selected = reset($imagens);
    }

    $arr_stock    = Array();
    $arr_deposito = Array();
    __getStock($prod['sku'], $ids_depositos, $arr_deposito, $arr_stock);

    $arr                    = array();
    $arr['status']          = 0;
    $arr['page_id']         = 5;
    $arr['page_cat_id']     = $catalogo;

    $arr['data']            = date("Y-m-d");
    $arr['datahora']        = date("Y-m-d H:i:s");

    $arr['id_cliente']      = $_SESSION['EC_USER']['id'];
    $arr['email']           = $_SESSION['EC_USER']['email'];
    $arr['idioma_user']     = $LG;

    $arr['pid']             = $prod['id'];
    $arr['ref']             = $prod['sku'];
    $arr['sku_family']      = $prod['sku_family'];
    $arr['sku_group']       = $prod['sku_group'];
    $arr['nome']            = $prod['desc'.$LG];

    $arr['cor_id']          = $cor['id'];
    $arr['cor_cod']         = $cor['color_code'];
    $arr['cor_name']        = $cor['long_name'];
    $arr['largura']         = "";
    $arr['altura']          = "";
    $arr['peso']            = $prod['peso'];
    $arr['tamanho']         = $sizes['nome'];

    if( $arr_stock["produto_digital"] == 1 ){
        $arr['unidade_portes'] = 0;
    }else{
        $arr['unidade_portes'] = (float)$prod['weight'] == 0 ? 1 : (float)$prod['weight'];
    }

    $arr['qnt']         = $qtd;
    $arr['composition'] = "";
    $arr_compositon     = Array();

    if( $prod['sales_unit'] > 0 && $prod['units_in_package'] > 1 ){

        $final_arr_composition = estr(207);

        if( $prod['sales_unit'] != '' ){

            $sql_arr_c = "SELECT nome".$LG." AS nome FROM registos_unidades_venda WHERE id='".$prod['sales_unit']."' AND nome".$LG." != '' LIMIT 1";
            $row_arr_c = cms_fetch_assoc( cms_query($sql_arr_c) );

            if( $row_arr_c['nome'] != '' ){
                $final_arr_composition = $row_arr_c['nome'];
            }

        }

        $arr_compositon[] = estr(206).' '.floatval($prod['units_in_package']).' '.$final_arr_composition;

    }

    if( trim($prod['variante']) != '' ) $arr_compositon[] = $prod['variante'];

    if( $budget_type == 1 ) $arr_compositon[] = estr(715);

    $arr['composition']         = implode(' - ', $arr_compositon);
    $arr['valoruni']            = $product_budget['final_price_uni'];

    $arr['mercado']             = $product_budget['market_id'];

    $arr['pais_cliente']        = $country_info['id'];
    $arr['pais_iso']            = $country_info['country_code'];

    $arr['moeda']               = $currency_info['id'];
    $arr['taxa_cambio']         = (float)$currency_info['cambio']==0 ? 1 : $currency_info['cambio'];
    $arr['moeda_simbolo']       = $currency_info['abreviatura'];
    $arr['moeda_prefixo']       = $currency_info['prefixo'];
    $arr['moeda_sufixo']        = $currency_info['sufixo'];
    $arr['moeda_decimais']      = $currency_info['decimais'];
    $arr['moeda_casa_decimal']  = $currency_info['casa_decimal'];
    $arr['moeda_casa_milhares'] = $currency_info['casa_milhares'];

    $arr['deposito']            = $ids_depositos;

    $arr_cookie_cpn                  = getCookieCPN();
    $arr['tracking_campanha_url_id'] = implode(',', array_keys($arr_cookie_cpn));

    $arr['pack']              = $pack; #0 - produto normal; #1 - só no addPack; #2 - para pack B2B; 3 - para produto com unidades a multiplicar preço
    $arr['novidade']          = $prod['novidade'];
    $arr['servicos']          = $prod['servicos'];
    $arr['width']             = (float)$prod['width'];
    $arr['height']            = (float)$prod['height'];
    $arr['lenght']            = (float)$prod['lenght'];

    $arr['iva_taxa_id']       = $prod['iva'];

    if((int)$CHECKOUT_LAYOUT_VERSION==0){
        $img_prd  = get_image_SRC($img_selected, 160, 200, 2);
    }else{
        $tamanhos = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['regular']);
        $img_prd  = get_image_SRC($img_selected, 200, (200*$tamanhos[1])/$tamanhos[0], 2);
    }

    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    if(trim($prod["composto"])!=""){
        $arr['boxes']       = 1;
        $arr['qtd_extra']   = $arr['tamanho']-1;
    }

    if( (int)$B2B > 0 ){
        $disp_condicionada_temp = array();
        $disp_condicionada_temp["stock"] = $arr_stock['stock'];

        if( $prod['stock_condicionado'] > 0 && $prod['data_condicionada'] > date("Y-m-d") ){
            $disp_condicionada_temp["inventory_conditioned_quantity"] = $prod['stock_condicionado'];
            $disp_condicionada_temp["inventory_conditioned_date"] = $prod['data_condicionada'];
        }

        $arr['disp_condicionada'] = json_encode($disp_condicionada_temp);
    }

    #0 - produto normal; 1 - produto configurador avançado; 2 - serviço que não permite desconto de campanha; 3 - produto orçamentado
    $arr['tipo_linha'] = 3;
    
    return $arr;
    
}

?>
