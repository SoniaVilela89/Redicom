<?
function _addToBasketRaffle($id=null, $cat=null, $pid=null, $qtd=null, $page_count=null, $idPoint=0, $idCredit=0, $pack=0, $import_cart=0, $sku_configurator=0, $default_catalog_id=0)
{
    
    if(is_null($pid)){
        $id                 = params('id');
        $cat                = params('cat');
        $pid                = params('pid');
        $qtd                = params('qtd');
        $page_count         = params('page_count');
        $idPoint            = params('idPoint');
        $idCredit           = params('idCredit');
        $pack               = (int)params('pack');
        $import_cart        = (int)params('import_cart');
        $sku_configurator   = params('sku_configurator');
        $default_catalog_id = params('default_catalog_id');
    }

    if( $qtd<=0 ) $qtd=1;

    global $userID;
    global $eComm, $LG, $fx, $sslocation, $B2B;
    global $COUNTRY, $MARKET, $MOEDA, $CONF_VAR_PID_REPLACE;
    global $CHECKOUT_LAYOUT_VERSION, $EGIFT_COM_PRODUTOS, $CONFIG_IMAGE_SIZE, $CONFIG_TEMPLATES_PARAMS, $CONFIG_OPTIONS, $session_id;
  
            
            
    
    # Um acesso maior que cliente (vendedor ou gestor) não pode adicionar
    if((int)$_SESSION['EC_USER']['type']>0){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);
    } 
    
    # Se B2B não dá pra adicionar sem login
    #if( (int)$B2B>0 && !is_numeric($userID) ){
    # Produtos de sorteio não dão para adicionar sem login
    if( (int)$B2B>0 && !is_numeric($userID) ){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);    
    }
    
    
    if((int)$MARKET['hide_venda']>0){
        $resp = array();
        $resp['cart'] = OBJ_cart(true);
        
        $data = serialize($resp['cart']);
        $data = gzdeflate($data,  9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);
        $_SESSION["SHOPPINGCART"] = $data;
        
        return serialize($resp);
    }
    

    # Só pode existir uma linha de sorteio
    @cms_query("DELETE FROM `ec_encomendas_lines` WHERE `id_cliente`='$userID' AND `status`='-99' AND `tipo_linha`='8' AND `order_id`='0'"); 


    $priceList = $MARKET['lista_preco'];
    
    $forcar_sem_iva = 0;
    
    # Lista de Empresas
    if($_SESSION['_MARKET']['lista_exclusiva1']>0){
        $mercad = call_api_func('get_line_table', 'ec_mercado', "id='".$_SESSION['_MARKET']["id"]."'");         
        $priceList = $mercad["lista_preco"];
        
        
        if($mercad['entidade_faturacao']>0){    
            $entidade_r = call_api_func('get_line_table', 'ec_invoice_companies', "id='".$mercad["entidade_faturacao"]."'");         
        }
        
        # 2020-09-03
        # Se cliente Empresa com NIF validado, finaliza checkout com lista de preços sem IVA se paisfor diferente da entidade faturadora
        if((int)$_SESSION['EC_USER']['tipo_utilizador']==1 && (int)$_SESSION['EC_USER']['nif_validado']==1 && ($entidade_r['id']>0 && $_SESSION['_COUNTRY']['id']!=$entidade_r['country']) ){
            $priceList = $mercad["lista_exclusiva1"];
            $forcar_sem_iva = 1;              
        }
    }
    
    
    
    # Forçar depósito e lista de preços do sorteio
    $sql_prod_sort = "SELECT sku, sku_group FROM registos WHERE id='".$pid."' LIMIT 0,1";
    $res_prod_sort  = cms_query($sql_prod_sort);
    $row_prod_sort  = cms_fetch_assoc($res_prod_sort);
    
    $raffle = get_raffle($row_prod_sort['sku_group'], $COUNTRY['id']);
    
    $already_participated = cms_num_rows( cms_query("SELECT `id`
                                                        FROM `sorteios_linhas_encomendas` 
                                                        WHERE `sorteios_linhas_id`='".$raffle['raffle_line_id']."'
                                                            AND `utilizador_id`='".$userID."'
                                                            AND `sku`='".$row_prod_sort['sku']."'
                                                        LIMIT 1") );

    if( (int)$raffle['deposito'] <= 0 || (int)$already_participated > 0 ){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);
    }
    
    $ids_depositos = $raffle['deposito'];

    if( (int)$raffle['lista_preco'] > 0 ){
        $priceList = $raffle['lista_preco'];
    }
    # Forçar depósito e lista de preços do sorteio

    $q = "SELECT registos.*,registos_precos.preco, registos_stocks.produto_digital 
          FROM registos          
            $JOIN
            INNER JOIN registos_stocks ON registos_stocks.sku=registos.sku AND registos_stocks.iddeposito IN (".$ids_depositos.")
            INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE activo='1' 
            AND  registos.id='$pid'
            AND registos_precos.idListaPreco='".$priceList."'
            AND (registos_precos.preco>0 OR registos_stocks.produto_digital=1)
          GROUP BY registos.id    
          LIMIT 0,1";
          
    $sql  = cms_query($q);
    $prod = cms_fetch_assoc($sql);
    
    if( (int)$prod['id'] == 0  ){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);
    }
    

    

    $boxes = array();
    if(trim($prod["composto"])!=""){
        $eComm->removeInsertedProducts($userID);    
    }else{
        if((int)$EGIFT_COM_PRODUTOS==0){
            $eComm->removeInsertedProductsEGift($userID);
        }
        $eComm->removeInsertedProductsBoxes($userID);
    }


    $cor  = getColor($prod['cor'],$prod['sku']);

    if($prod['material']>0){
        $matR = getMaterial($prod['material']);

        $cor['short_name']  = $matR['name'].' '.$cor['short_name'];
        $cor['long_name']   = $matR['name'].' '.$cor['long_name'];

        if($cor['image']['alt']!=''){
            $cor['image']['alt'] = $matR['name'].' '.$cor['image']['alt'];
        }
    }

    $sizes = getTamanho($prod['tamanho']);
    
    $tipo_linha = 0;
        
    require_once(__DIR__ . "/_getSingleImage.php");
    $img_selected = _getSingleImage(0, 0, 0, $prod['sku'], 5);


    
    
    # 06-02-2020
    # Unidades de venda
    # Ficha de produto multiplicar preço automaticamente pelas quantidades de venda
    $original_qtd    = $qtd;
    if((int)$prod['package_price_auto']==1 && (int)$prod['units_in_package']>1){
        $qtd *= (int)$prod['units_in_package'];
        $pack = 3;            
    }
    
    # 2021-06-29
    # Unidades de venda
    # Ficha de produto multiplicar preço automaticamente pelas quantidades de venda, mas não para a quantidade
    if((int)$prod['package_price_auto']==3 && $prod['units_in_package']>0 && $prod['units_in_package']!=1){        
        $pack = 3;            
    }


    if( (int)$prod['package_price_auto']==3 && $prod['units_in_package']>0 && $prod['units_in_package'] != 1){
        $preco = __getPrice($prod['sku'], $priceList, 0, $prod);
    }else{
        $preco = __getPrice($prod['sku'], $priceList, 0, array());
    }
    
    
    


    $arr_stock = array();
    $arr_deposito = array();
    __getStock($prod['sku'], $ids_depositos, $arr_deposito, $arr_stock);
    

    $arr                    = array();
    $arr['status']          = "-99";
    $arr['page_id']         = $id;
    $arr['page_cat_id']     = (int)$GLOBALS["REGRAS_CATALOGO"];
    $arr['page_count']      = $page_count;
    
    $arr['data']            = date("Y-m-d");
    $arr['datahora']        = date("Y-m-d H:i:s");
        
    $arr['id_cliente']      = $userID;
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
    $arr['peso']            = $prod['peso'];
    $arr['tamanho']         = $sizes['nome'];
    
    if( (int)$CONFIG_OPTIONS["SHOW_CHECKOUT_PROD_BRAND"] == 1 ){
        $brand        = call_api_func("get_line_table", "registos_marcas", "id=".$prod['marca']);
        $arr['marca'] = $brand['nome'.$LG];
    }    

    $arr['unidade_portes'] = (float)$prod['weight'];
    if( $arr['unidade_portes']==0 ) $arr['unidade_portes']=1;    
    if($arr_stock["produto_digital"]==1) $arr['unidade_portes']=0;
    
    

    if((int)$B2B==0 && $qtd>999){
        $qtd = 999;
    }
    $arr['qnt'] = $qtd;
    
    
    
    $arr['composition'] = "";
        
    $arr_compositon = array();
    
    if( $prod['sales_unit']>0 && $prod['units_in_package']>1){

        $final_arr_composition = estr(207);        

        if($prod['sales_unit'] != '') {
              $sql_arr_c = "SELECT nome".$LG." AS nome FROM registos_unidades_venda WHERE id='".$prod['sales_unit']."' AND nome".$LG." != '' LIMIT 1";
              $row_arr_c = cms_fetch_assoc(cms_query($sql_arr_c));

              if($row_arr_c['nome'] != '') {
                  $final_arr_composition = $row_arr_c['nome'];
              }
        }

        $arr_compositon[] = estr(206).' '.floatval($prod['units_in_package']).' '.$final_arr_composition;
        
    }

    if( trim($prod['variante'])!='' ) $arr_compositon[] = $prod['variante'];
    
    if($MARKET["usePoints"]==1 && $idPoint>0 ){
        $pre = $preco['precophp'];
        if($preco["id_desconto"]>0) $pre = $preco['preco_riscado'];
        
        $alternative_price = call_api_func('get_alternative_price',$prod, $pre, $idPoint);
        
        if((int)$alternative_price[$idPoint]["id"]>0){
            $arr['pid'] = "Pts$idPoint|||".$prod['id'];
            $preco['precophp'] = $alternative_price[$idPoint]["price"]["value"];
            $preco['preco_riscado'] = "";
            $preco['desconto'] = "";
            
            $arr['points'] = $alternative_price[$idPoint]["points"];
        }
    }

    $arr['composition'] = implode(' - ', $arr_compositon);
    
    $arr['valoruni']            = $preco['precophp'];
    $arr['valoruni_anterior']   = $preco['preco_riscado'];
    $arr['valoruni_desconto']   = $preco['desconto_valor_php'];

    $arr['datafim_desconto']    = $preco['data'];
    $arr['id_desconto']         = $preco['id_desconto'];   
    
    
    $arr['pvr']                 = $preco['preco_base'];
    $arr['pvr_desconto']        = $preco['desconto_valor_base'];

    
    $arr['mercado']             = $MARKET['id']; 
    
    $arr['pais_cliente']        = $COUNTRY['id'];
    $arr['pais_iso']            = $COUNTRY['country_code'];
    
    $arr['moeda']               = $MOEDA['id'];  
    $arr['taxa_cambio']         = (float)$MOEDA['cambio']==0 ? 1 : $MOEDA['cambio'];
    $arr['moeda_simbolo']       = $MOEDA['abreviatura'];
    $arr['moeda_prefixo']       = $MOEDA['prefixo'];
    $arr['moeda_sufixo']        = $MOEDA['sufixo'];
    $arr['moeda_decimais']      = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares'] = $MOEDA['casa_milhares'];

    
       
    $arr['deposito'] = $ids_depositos;
    // if(trim($preco['deposito'])!=""){
    //     $arr['deposito'] = $preco['deposito'];
    // }
    
    $arr['lista_preco'] = $priceList;
    if( $preco['promo']==1 ) {
        $arr['promotion'] = 1;
        if($preco['list_price']>0) $arr['lista_preco'] = $preco['list_price'];
    }
    
    $arr_cookie_cpn = getCookieCPN();
    $arr['tracking_campanha_url_id'] = implode(',', array_keys($arr_cookie_cpn));
    
    $arr['pack']              = $pack; #0 - produto normal; #1 - só no addPack; #2 - para pack B2B; 3 - para produto com unidades a multiplicar preço  
    $arr['novidade']          = $prod['novidade'];
    $arr['servicos']          = $prod['servicos'];
    $arr['width']             = (float)$prod['width'];
    $arr['height']            = (float)$prod['height'];
    $arr['lenght']            = (float)$prod['lenght'];
    
    if($forcar_sem_iva==1) $prod['iva'] = 4;
     
    $arr['iva_taxa_id']       = $prod['iva'];

    if((int)$CHECKOUT_LAYOUT_VERSION==0){
        $img_prd = get_image_SRC($img_selected, 160, 200, 2);
    }else{
        $tamanhos = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['regular']);  
        $img_prd = get_image_SRC($img_selected, 200, (200*$tamanhos[1])/$tamanhos[0], 2);
    }
    
    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    if(trim($prod["composto"])!=""){
        $arr['boxes']       = 1;      
        $arr['qtd_extra']   = $arr['tamanho']-1;   
    }
    
    $arr['venda_credito'] = $meses;
    
    $arr['tipo_linha'] = 8;
        
    set_status(3, $arr, '', $id, $page_count);
    if( !is_numeric($userID) ){
        $product                    = array();
        $product["id"]              = $arr["pid"];
        $product["available"]       = 1;
        $product["price"]["value"]  = $arr['valoruni'];
        set_clientes_status($product);
    }
    $eComm->addToBasket($arr);
    
    unset($_SESSION['MAN_VOUCHER_DEL']);
        
    
    $resp=array();
    
    # $import_cart utilizado para importar produtos para o carrinho desde excel no B2B
    if($import_cart==0){

        
        $family = call_api_func('get_line_table', 'registos_familias', "id='".$prod["familia"]."'");
        $categoria = call_api_func('get_line_table', 'registos_categorias', "id='".$prod["categoria"]."'");
       
        $tag_manager = tracking_from_tag_manager('addToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, 
                                                    "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'],
                                                    "ID_UTILIZADOR" => $session_id,  
                                                    "SKU_PRODUTO" => $prod['sku'], 
                                                    "ID_PRODUTO" => $prod['id'], 
                                                    "SKU_GROUP" => $prod['sku_group'], 
                                                    "SKU_FAMILY" => $prod['sku_family'], 
                                                    "FAMILIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $family['nomept']), 
                                                    "CATEGORIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $categoria['nomept']), 
                                                    "URL_PRODUTO" => $sslocation."/?pid=".$prod['id'], 
                                                    "IMAGEM_URL_PRODUTO" => $arr['image'], 
                                                    "VALOR_PRODUTO" => $preco['precophp'], 
                                                    "DESCRICAO_PRODUTO" => "" , 
                                                    "NOME_PRODUTO" => cms_real_escape_string($prod['nome'.$LG]), 
                                                    "MOEDA" => $MOEDA['abreviatura']  ));  
                                                    
                                                    
        $eComm->getRappel((int)$GLOBALS["REGRAS_CATALOGO"]);
    
    
        if($pack!=2){
        
            $resp=array();
            $resp['cart']           = OBJ_cart(true);
    
            if( !empty($dc_info) ){
                $resp['dc_info'] = $dc_info;
            }

            $resp['cart']['product_id_add'] = end( explode("|||", $prod['id']) );
            
            $data = serialize($resp['cart']);
            $data = gzdeflate($data,  9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);
            $_SESSION["SHOPPINGCART"] = $data;
            
            $resp['trackers']       = base64_encode($tag_manager);
            $resp['product_id_add'] = $prod['id'];
            
        }
        
    }else{
        $last_id_insert = cms_insert_id();
                
        if((int)$last_id_insert>0) $resp['status'] = true;
        else $resp['status'] = false;
    }
    
    
    
    # Dashboard Tracking *********************************************************************************************************
    require_once '../plugins/tracker/funnel.php';
    $Funnel = new Funnel();
    $Funnel->event(1);
    


    # Conversão do Facebook por API ***********************************************************************************************
    $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
    if( (int)$CONFIG_TEMPLATES_PARAMS['facebook_pixel_send_all_events'] == 1 && trim($CONFIG_TEMPLATES_PARAMS['facebook_pixel_access_token']) != '' && $show_cp_2 == 1 ){

        $event_id = md5('add_cart'.session_id().time());

        $capi_user_info = get_capi_user_info();
        $event_info     = ['event_time' => time(), 'event_id' => $event_id, 'event' => 'AddToCart', 'user_info' => $capi_user_info, 'custom_info' => $arr];

        setFacebookEventOnRedis("CAPI_EVENT_".$event_id, $event_info);

        $resp['a_id'] = $event_id;

    }   
    
    
    
    
    # Collect API  ***************************************************************************************************************
    global $collect_api;
    if( isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1 ){

        if($prod["familia"] > 0 && (!isset($family) || empty($family)) ){
            $family = call_api_func('get_line_table', 'registos_familias', "id='".$prod["familia"]."'");
        }
            
        if($prod["categoria"]>0 && (!isset($categoria) || empty($categoria))){
            $categoria = call_api_func('get_line_table', 'registos_categorias', "id='".$prod["categoria"]."'");
        }
            
        if($prod['genero']>0 ){
            $gender = call_api_func("get_line_table", "registos_generos", "id=".$prod['genero']);
        }
        
        $arr['family']   = $family;
        $arr['category'] = $categoria;
        $arr['brand']    = $brand;
        $arr['gender']   = $gender;
        
        #<Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>
        include $_SERVER['DOCUMENT_ROOT'] . "/custom/shared/addons_info.php";
        $collectApiExtraClassifier = getCollectApiExtraClassifier();
        $campos                    = $collectApiExtraClassifier['campos'];
        $COLLECT_API_LANG          = $collectApiExtraClassifier['COLLECT_API_LANG'];   
        $num_campos_adicionais     = $collectApiExtraClassifier['num_campos_adicionais'];
        #</Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>  
        
        $arr['family']['name']   = $family['nome'.$COLLECT_API_LANG] != '' ? $family['nome'.$COLLECT_API_LANG] : $family['nomept'];
        $arr['category']['name'] = $categoria['nome'.$COLLECT_API_LANG] != '' ? $categoria['nome'.$COLLECT_API_LANG] : $categoria['nomept'];
        $arr['brand']['name']    = $brand['nome'.$COLLECT_API_LANG] != '' ? $brand['nome'.$COLLECT_API_LANG] : $brand['nomept'];
        $arr['gender']['name']   = $gender['nome'.$COLLECT_API_LANG] != '' ? $gender['nome'.$COLLECT_API_LANG] : $gender['nomept'];

        #<Classificadores adicionais>
        for ($i=1;$i<=$num_campos_adicionais ;$i++ ) {
             $classificador = ${'ADDON_3010_CLS_ADIC_'.$i};
             if( empty($classificador) ){ continue;}

             if($prod[$campos[$classificador]['field']]>0 ){
                $classificador_adicional = call_api_func("get_line_table", $classificador, "id=".$prod[$campos[$classificador]['field']]);
                $arr['extra_classifier']['extra_classifier_'.$i] = $classificador_adicional['nome'.$COLLECT_API_LANG] != '' ? $classificador_adicional['nome'.$COLLECT_API_LANG] : $classificador_adicional['nomept'];
             } 
        }     
        #</Classificadores adicionais> 
        
        $event_info = ['product' => $arr, 'country' => $COUNTRY, 'currency' => $MOEDA];
        $cart_ungrouped = [];

        foreach($resp['cart']['items'] as $line){
            $cart_product = buildCartProductInfoForCollectAPI($line);

            $line_ungrouped = array_fill(0, $line['quantity'], $cart_product); # copy the line "$line['quantity']" times (creates an array with as many lines as it's quantity)
            $cart_ungrouped = array_merge($cart_ungrouped, $line_ungrouped);

        }

        $change_cart_info = ['cart' => ['items' => $cart_ungrouped], 'country' => $COUNTRY, 'currency' => $MOEDA];

        try{
            $collect_api->setEvent(CollectAPI::PRODUCT_ADD, $_SESSION['EC_USER'], $event_info);
			      $collect_api->setEvent(CollectAPI::CART_CHANGE, $_SESSION['EC_USER'], $change_cart_info);
        }catch(Exception $e){
            // Nothing to do here         
        }
        
    }
    

    return serialize($resp);
}



?>
