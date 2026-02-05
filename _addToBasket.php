<?

# import_cart : 1- importação de excel no checkout ou em matriz sem ser no ultimo produto, 2- importação de excel para loja no checkout, 3 - adição desde a compra rápida, ou em matriz no ultimo produto

# $ais : 2 - venda em isa de produtos que o cliente leva logo embora

function _addToBasket($id=null, $cat=null, $pid=null, $qtd=null, $page_count=null, $idPoint=0, $idCredit=0, $pack=0, $import_cart=0, $sku_configurator=0, $default_catalog_id=0, $id_conjunto=0, $unit_store_ids=0, $MA=0, $id_lote=0, $ais=0)
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
        $id_conjunto        = params('id_conjunto');
        $unit_store_ids     = params('unit_store_ids');
        $MA                 = params('ma');
        $id_lote            = params('id_lote');
        
    }

    if( $qtd<=0 ) $qtd=1;

    global $userID;
    global $eComm, $LG, $sslocation, $B2B, $B2B_CATEGORIA;
    global $COUNTRY, $MARKET, $MOEDA, $CONF_VAR_PID_REPLACE;
    global $CHECKOUT_LAYOUT_VERSION, $EGIFT_COM_PRODUTOS, $CONFIG_IMAGE_SIZE, $CONFIG_TEMPLATES_PARAMS, $CONFIG_OPTIONS, $session_id, $SETTINGS_SITE, $API_CONFIG_IMAGE_CART;
      
      
      
    
    # Um acesso maior que cliente (vendedor ou gestor) não pode adicionar
    if((int)$_SESSION['EC_USER']['type']>0){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);
    } 
    
    # Se B2B não dá pra adicionar sem login
    if( (int)$B2B>0 && !is_numeric($userID) && $B2B_CATEGORIA!=3 ){
        $resp = array();
        $resp['error'] = 1;
        
        return serialize($resp);    
    }
    
    
    if((int)$MARKET['hide_venda']>0){
        $resp = array();
        $resp['cart'] = OBJ_cart(false, 1);
        
        $data = serialize($resp['cart']);
        $data = gzdeflate($data,  9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);
        $_SESSION["SHOPPINGCART"] = $data;
        
        return serialize($resp);
    }
    
    
    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    if( $import_cart == 0 || $import_cart == 3 ){
        $eComm->removeInsertedChecks($userID);
        #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
        #$eComm->cleanVauchers($userID);
        $eComm->clearTempCampanhas($userID);
    }
    
    
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
        
        # 2025-05-27
        # A blackandpepper é um b2c que tem disponivel o campo na ficha de cliente porque quer que os cleintes PT fechem mesmo a encomenda com a lista sem IVA   
        if((int)$B2B==0 && $_SESSION['EC_USER']['lista_preco']>0){
            $priceList = $_SESSION['EC_USER']['lista_preco'];
        }
    }
    

    
    $ids_depositos = $MARKET['deposito'];
 
    if((int)$B2B>0){
    
        if($id==36){
            $page = call_api_func('get_pagina_modulos', $id, "_trubricas");     
        }else{
             $page = call_api_func('get_pagina', $id, "_trubricas");    
        }
        
        # 2025-02-06
        # Por causa das excepçoes definidas no BO    
        if($page["catalogo"]>0){
            $catalogo_id = $page["catalogo"];
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }   
        
        if( $default_catalog_id > 0 ){
            $catalogo_id = $default_catalog_id;
        }
    
        preparar_regras_carrinho($catalogo_id);
        
        if((int)$GLOBALS["REGRAS_CATALOGO"]==0) preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);
        
        if($GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0) $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];
        
        if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!='') $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
    }

    
    # 2020-02-05 - decenio
    if((int)$CONF_VAR_PID_REPLACE==1){
        $s_prod = "SELECT pid_replace FROM registos WHERE id='$pid' LIMIT 0,1";
        $q_prod  = cms_query($s_prod);
        $r_prod = cms_fetch_assoc($q_prod);
        
        if((int)$r_prod['pid_replace']>0) $pid = $r_prod['pid_replace'];
    }
    
    
    $q = "SELECT registos.*,registos_precos.preco, registos_stocks.produto_digital 
          FROM registos          
            LEFT JOIN registos_stocks ON registos_stocks.sku=registos.sku
            INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE activo='1' 
            AND  registos.id='$pid'
            AND registos_precos.idListaPreco='".$priceList."'
            AND (registos_precos.preco>0 OR registos_stocks.produto_digital=1)
          GROUP BY registos.id  
          LIMIT 0,1";

    if((int)$B2B>0){
        $q = "SELECT registos.*,registos_precos.preco, registos_stocks.produto_digital, SUM(registos_stocks.stock_condicionado) as stock_condicionado, MAX(data_condicionada) as data_condicionada
              FROM registos          
                LEFT JOIN registos_stocks ON registos_stocks.sku=registos.sku
                INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
              WHERE activo='1' 
                AND  registos.id='$pid'
                AND registos_precos.idListaPreco='".$priceList."'
                AND (registos_precos.preco>0 OR registos_stocks.produto_digital=1)
              GROUP BY registos.id    
              LIMIT 0,1";
    }


    $sql  = cms_query($q);
    $prod = cms_fetch_assoc($sql);
    if( (int)$prod['id'] < 1 ){
        return serialize( array( 'error' => 1 ) );    
    }
    
    
    
    $PID_ORIGINAL = $prod['id'];

    # Delayed Sale
    if( (int)$prod['venda_retardada'] == 1 && strtotime($prod['venda_retardada_data']) > time() ){
        return serialize( array( 'error' => 1 ) );
    }
    
  
    
    if( $import_cart == 0 ){
        $boxes = array();
        if(trim($prod["composto"])!=""){
            $eComm->removeInsertedProducts($userID);    
        }else{
            if((int)$EGIFT_COM_PRODUTOS==0){
                $eComm->removeInsertedProductsEGift($userID);
            }
            $eComm->removeInsertedProductsBoxes($userID);
        }
    }


    $cor  = getColor($prod['cor'], $prod['sku'], "", "", 1);

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

    if( (int)$prod['ignorar_descontos'] == 1 ){
        $tipo_linha = 9;
    }
    
    
    # Configurador Avançado
    $sku_configurador_original = '';
    $sku_group_prod = $prod['sku_group'];
    if( $sku_configurator != '' && $sku_configurator != '0' ){
        
        $aray_configuration = array();
        
        $row_configurator = call_api_func("get_line_table", "registos_configurador_avancado", "sku_final='".$sku_configurator."' AND (idioma = '$LG' || idioma = '*')");
        if( (int)$row_configurator['id'] > 0 ){
            
            $sku_group_prod = $sku_configurador_original = $prod['sku'];
            $prod['id'] = $row_configurator['id']."|||".$prod['id'];
            $prod['sku'] = $sku_configurator;
            
            
            # 2024-05-20
            # Definido para a Piranha que se o produto do configurador avançado tiver valores definidos eles ganham
            if($row_configurator['width']>0)    $prod['width']  = $row_configurator['width'];
            if($row_configurator['height']>0)   $prod['height'] = $row_configurator['height'];
            if($row_configurator['lenght']>0)   $prod['lenght'] = $row_configurator['lenght'];
            if($row_configurator['weight']>0)   $prod['weight'] = $row_configurator['weight'];
            
            
            $tipo_linha = 1;
            
            for ($i=1; $i<=5; $i++) {
            
                if( (int)$row_configurator['grupo'.$i] == 0 || empty( trim($row_configurator['valor'.$i]) ) ) break;
                
                $aray_configuration[] = $row_configurator['valor'.$i];
                
            } 
        }
        
    }
    # Configurador Avançado
    
    
    
    
    
    
    
    #$imagens = getImagens($prod['sku'], $prod['sku_group'], $prod['sku_family']);
        
    require_once(__DIR__ . "/_getSingleImage.php");
    $img_selected = _getSingleImage(0, 0, 0, $prod['sku'], 5);
    
    # 2025-10-31
    # Cante
    if($img_selected=="/sysimages/no-image4.jpg"){
        $img_selected = _getSingleImage(0, 0, 0, $prod['sku'],5,2); #Procura a imagem 2
    }

    # 2025-04-09
    # Se o produto configurado não tem imagem vamos procurar no produto base
    if($img_selected=="/sysimages/no-image4.jpg" && $sku_configurador_original!=''){
        $img_selected = _getSingleImage(0, 0, 0, $sku_configurador_original, 5);    
    }  




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
        $preco = __getPrice($prod['sku'], $priceList, 0, $prod, 0, $sku_configurador_original, $qtd, $id_lote);
    }else{
        $preco = __getPrice($prod['sku'], $priceList, 0, array(), 0, $sku_configurador_original, $original_qtd, $id_lote);
    }
    
    
    
    
    
    $arr_stock = array();
    $arr_deposito = array();
    
    __getStock($prod['sku'], $ids_depositos, $arr_deposito, $arr_stock);
    
    
    
    
    


    $arr                    = array();
    $arr['status']          = "0";
    $arr['page_id']         = $id;
    $arr['page_cat_id']     = (int)$GLOBALS["REGRAS_CATALOGO"];

    # 22-02-2023 - Reutilizado para construir o link do produto no checkout
    #$arr['page_count']      = $page_count;
    $arr['page_count']      = $cat;

    $arr['data']            = date("Y-m-d");
    $arr['datahora']        = date("Y-m-d H:i:s");
        
    $arr['id_cliente']      = $userID;
    $arr['email']           = $_SESSION['EC_USER']['email'];
    $arr['idioma_user']     = $LG;
    
    
    
    $arr_compositon = array();
    
    # LOTES
    if((int)$id_lote > 0 && (int)$SETTINGS_SITE['lotes'] == 1){
        $prod['id'] = $id_lote."|||".$prod['id'];
        
        $arr['col2'] = $id_lote;
        
        $sql_lote = "SELECT nome$LG FROM registos_lotes WHERE id='".$id_lote."'";
        $res_lote = cms_query($sql_lote);
        $row_lote = cms_fetch_assoc($res_lote);
        
        if($row_lote['nome'.$LG] != '') {
            $arr_compositon[] = $row_lote['nome'.$LG];
        }
    }
    
    
    
    $arr['pid']             = $prod['id'];
    $arr['ref']             = $prod['sku'];
    $arr['sku_family']      = $prod['sku_family'];
    $arr['sku_group']       = $sku_group_prod;
    $arr['nome']            = $prod['desc'.$LG];
    
    $arr['cor_id']          = $cor['id'];
    $arr['cor_cod']         = $cor['color_code'];
    $arr['cor_name']        = $cor['long_name'];
    $arr['peso']            = $prod['weight'];
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
        
    

    if( $prod['sales_unit']>0 && $prod['units_in_package']!=1 && $prod['units_in_package']>0){

        $final_arr_composition = estr(207);        

        if($prod['sales_unit'] != '') {
              $sql_arr_c = "SELECT nome".$LG." AS nome FROM registos_unidades_venda WHERE id='".$prod['sales_unit']."' AND nome".$LG." != '' LIMIT 1";
              $row_arr_c = cms_fetch_assoc(cms_query($sql_arr_c));

              if($row_arr_c['nome'] != '') {
                  $final_arr_composition = $row_arr_c['nome'];
              }
        }
        
        $inicial_arr_composition = estr(206);        
        if($prod['package_type'] != ''){
            
            $sql_arr_p = "SELECT nome".$LG." AS nome FROM registos_embalagens WHERE id='".$prod['package_type']."' AND nome".$LG." != '' LIMIT 1";
            $row_arr_p = cms_fetch_assoc(cms_query($sql_arr_p));

            if($row_arr_p['nome'] != '') {
                $inicial_arr_composition = $row_arr_p['nome'];    
            }                      

        }
        
        if((int)$prod['package_price_auto']==1 && (int)$prod['units_in_package']>1){
            if((int)$B2B > 0) {
                $arr_compositon[] = $original_qtd.'x '.$inicial_arr_composition;
            }else{
                $arr_compositon[] = estr(206).''.$inicial_arr_composition;  
            }
            
        }else{                 
            $arr_compositon[] = $inicial_arr_composition.' '.floatval($prod['units_in_package']).' '.$final_arr_composition;
        }
        
        
    }

    if( trim($prod['variante'])!='' ) {
        $sql_dm = $categoria = call_api_func('get_line_table', 'registos_genericos_22', "codigo='".cms_escape($prod['variante'])."'");             
        if (trim($sql_dm['nome'.$LG])!=''){
            $prod['variante'] = $sql_dm['nome'.$LG];
        }

        if( trim($prod['variante2']) != '' ) {
                        
            $prod['variante'] .= " / ";
            
            $sql_dm = $categoria = call_api_func('get_line_table', 'registos_genericos_23', "codigo='".cms_escape($prod['variante2'])."'");           
            if (trim($sql_dm['nome'.$LG])!=''){
                $prod['variante'] .= $sql_dm['nome'.$LG];
            }else{
                $prod['variante'] .= $prod['variante2'];
            }
          
        }
        
        
        # 2024-10-17 
        # Para garantir que no checkout b2b o campo é escrito no mais info que não pode ser na posição 0
        if($B2B>0 && count($arr_compositon)==0) 
            $arr_compositon[] = "";
            
            
        $arr_compositon[] = trim($prod['variante']);
    }
    
    
    
    
    
    
    # Configurador Avançado
    if( $sku_configurator != '' && count($aray_configuration) > 0 ){
        $arr_compositon[] = implode(", ", $aray_configuration);    
    }
    # Configurador Avançado
    
    
    
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
    
  
    $meses = 0;    
    if($idCredit>0){
        #Para compatibilizar a base de dados :: 13/12/2018
        $row_col = cms_fetch_assoc(cms_query("SHOW COLUMNS FROM ec_encomendas_lines WHERE Field='valor_credito'"));
        if($row_col["Field"]=="valor_credito"){
            $price_credit = call_api_func('get_credit_price',$prod['sku'], $preco);
            foreach ($price_credit as $key=>$value) {
                if($idCredit==$value["id"]){ 
                    $meses = $value["name"];
                    $price = $value["date_line"];
                }
            }
            
            $p_mens = number_format($price['precophp']*$meses, 2, ".", "");
            $valor_credito = $p_mens-$preco['precophp'];           
            
            if($price["descontophp"]==0 && (int)$preco["descontophp"]>0 && $preco["preco_riscado"]>0){
                
                $p_mensalidade = number_format($price['precophp']*$meses, 2, ".", "");                    
                $valor_credito = $p_mensalidade-$preco['preco_riscado'];
                
                $preco['desconto_valor_php'] = 0;
                $preco['data'] = "";
                $preco['id_desconto'] = 0;
                        
                if($valor_credito>=0 && $valor_credito<1){ 
                    $preco['precophp'] = $price['precophp']*$meses;                        

                    $preco['preco_riscado'] = $price['preco_riscado']*$meses;
                    $preco['desconto'] = $price['desconto'];
                    
                }
            }
            if($valor_credito<0) $valor_credito = "0.00"; 
            
            if($valor_credito>0 && $price["preco_riscado"]>0){
                
                $Preco_original = $preco["preco"];
                if($preco["preco_riscado"]>0){
                    $Preco_original = $preco["preco_riscado"];
                }
            
                $Preco_original_desc = ($Preco_original*$price["descontophp"])/100;                                                               
                $Preco_original -= $Preco_original_desc;
                
                $preco['precophp'] = $price['precophp']*$meses;
                
                $valor_credito = $preco['precophp']-$Preco_original;
                    
                if($preco['precophp']>$Preco_original){
                    $preco['precophp'] -= $valor_credito;    
                }                                

                $preco['preco_riscado'] = $price['preco_riscado']*$meses;
                $preco['desconto'] = $price['desconto'];
                
                if($valor_credito<0) $valor_credito = "0.00"; 
                
            }
            
            $preco['promo']=0;
            if($price["preco_riscado"]>0 && $price["preco_riscado"]!=$price['precophp']){
                $preco['promo']=1;
            }
            
            
            # 2020-09-15 
            # Colocada regra de que até 1€ não é diferimento                               
            if($valor_credito>1){
                $arr['valor_credito'] = $valor_credito;
            }
            
            
            
            $arr['pid'] = "M$idCredit|||".$prod['id'];  
            
            $arr_compositon[] = estr(376).': '.$meses.' x '.$MOEDA['prefixo'].number_format($price['precophp'], $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares'] ).$MOEDA['sufixo'];
        }  
    }
    
    $arr['composition'] = implode(' - ', $arr_compositon);
    
    # 2025-08-19
    # Salsa - venda em isa de produtos que o cliente leva logo
    if($ais==2)  $arr['composition'] = "ENTREGA IMEDIATA";    

    
    $arr['valoruni']            = $preco['precophp'];
    $arr['valoruni_anterior']   = $preco['preco_riscado'];
    $arr['valoruni_desconto']   = $preco['desconto_valor_php'];

    $arr['datafim_desconto']    = $preco['data'];
    $arr['id_desconto']         = $preco['id_desconto'];   
    
    
    $arr['pvr']                 = $preco['preco_base'];
    $arr['pvr_desconto']        = $preco['desconto_valor_base'];

    $arr['desconto_linha_perc'] = $preco['desconto_base'];
    $arr['promo_perc']          = $preco['desconto'];
    
    
    # 2025-07-22
    if($MA=='9210'){
        $campaign = call_api_func('get_line_table', 'ec_campanhas', "id='$MA'");
        
        if((int)$campaign['automation']==60 && $campaign['ofer_perc']>0){
            $arr['valoruni_anterior'] = $arr['valoruni'];            
            $arr['valoruni_desconto'] = ($preco['precophp']*$campaign['ofer_perc'])/100;
            $arr['valoruni']          = $arr['valoruni_anterior']-$arr['valoruni_desconto'];
            $arr['id_desconto']       = $MA;
            $arr['info_pack']         = $campaign['automation_bg']."|".$campaign['automation_color'];
            $arr['promo_perc']        = number_format($campaign['ofer_perc']).'%';
            $tipo_linha = 10;
            
            
            @cms_query("DELETE FROM `ec_encomendas_lines` WHERE `id_cliente`='".$userID."' AND `tipo_linha`='10' AND `status`='0' AND id_desconto='9210'");
            
        }
    }
    
    
    
    
    $price_rrp                  = get_price_pvpr($prod, $preco['precophp']);
    $arr['pvpr']                = $price_rrp;
    $arr['markup']              = get_markup_from_price($preco['precophp'], $price_rrp);
    
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
    if(trim($preco['deposito'])!=""){
        $arr['deposito'] = $preco['deposito'];
    }
    
    $arr['promotion'] = 0;
    $arr['lista_preco'] = $priceList;
    if( $preco['promo']==1 ) {
        $arr['promotion'] = 1;
        if($preco['list_price']>0) $arr['lista_preco'] = $preco['list_price'];
    }
    
    $arr_cookie_cpn = getCookieCPN();
    
    # 2023-08-22
    if($tipo_linha==10) $arr_cookie_cpn[$MA] = 100000+$MA;
    
    
    $arr['tracking_campanha_url_id'] = implode(',', array_keys($arr_cookie_cpn));

    
    #CONFIGURADOR EXTRA
    if(trim($prod['configurador_extra']) != ""){
        $arr_service_config = array();
        $prod['configurador_extra'] = ltrim($prod['configurador_extra'], ',');
        $arr_conf = explode(",", $prod["configurador_extra"]);
        foreach ($arr_conf as $key => $value) {
            
            $sql_conf_extra_serv_group = "SELECT id FROM registos_servicos_grupo WHERE id_configurador_extra='".$value."' AND nome$LG!='' AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' ) ";
            $res_conf_extra_serv_group = cms_query($sql_conf_extra_serv_group);
            while($row_conf_extra_serv_group = cms_fetch_assoc($res_conf_extra_serv_group)){
                $arr_service_config[] = $row_conf_extra_serv_group["id"];
            }
        }
        $arr_service = array();
        
        if(trim($prod['servicos']) != "") $arr_service = explode(",", $prod['servicos']); 
        $result = array_merge($arr_service, $arr_service_config);
        
        $prod['servicos'] = implode(",", $result);

    }
    
    
    
    $arr['pack']              = $pack; #0 - produto normal; #1 - só no addPack; #2 - para pack B2B; 3 - para produto com unidades a multiplicar preço  
    $arr['novidade']          = $prod['novidade'];
    $arr['servicos']          = $prod['servicos'];
    $arr['width']             = (float)$prod['width'];
    $arr['height']            = (float)$prod['height'];
    $arr['lenght']            = (float)$prod['lenght'];
    

    
    # 2024-08-13
    # Excecoes de taxa de iva por paises - garrafeira soares
    $taxa_r = call_api_func('get_line_table', 'registos_taxas_excecoes', "sku_family='".$prod['sku_family']."' AND CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' ORDER BY id DESC");           
    
    if((int)$taxa_r['id']>0) $prod['iva'] = $taxa_r['taxa_iva'];
    
    
    
    if($forcar_sem_iva==1) $prod['iva'] = 4;
    
    
     
    $arr['iva_taxa_id']       = $prod['iva'];

    if((int)$CHECKOUT_LAYOUT_VERSION==0 && (int)$CONFIG_OPTIONS['CHECKOUT_LAYOUT_VERSION']==0){
        if($B2B>0) $img_prd = get_image_SRC($img_selected, 160, 160, 3);
        else $img_prd = get_image_SRC($img_selected, 160, 200, 2);
    }else{
        $tamanhos = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['regular']);  
        
        $cal = (200*$tamanhos[1])/$tamanhos[0]; 
        if((int)$API_CONFIG_IMAGE_CART>0) $img_prd = get_image_SRC($img_selected, 200, $API_CONFIG_IMAGE_CART, 2);
        else $img_prd = get_image_SRC($img_selected, 200, $cal, 2);
    }
    
    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    if(trim($prod["composto"])!=""){
        $arr['boxes']       = 1;      
        $arr['qtd_extra']   = $arr['tamanho']-1;   
    }
    
    $arr['venda_credito'] = $meses;
    
    $dc_info = [];


    if( (int)$B2B > 0 ){

        $disp_condicionada_temp = array();
        $disp_condicionada_temp["stock"] = $arr_stock['stock'];
        
        if( (int)$CONFIG_OPTIONS['SUM_REPLACEMENT_PERIOD_TO_PENDING_QUANTITIES'] == 1 && (int)$prod['ReplacementTime'] > 0 ){
            $disp_condicionada_temp["replacement_time"] = $prod['ReplacementTime'];
        }
        
        if( (int)$CONFIG_OPTIONS['stock_condicionado_multi'] == 1 ){
            $disp_condicionada_temp["inventory_conditioned_arr"] = [];
            
            $prod['stock_condicionado']  = 0;
            $prod['data_condicionada']   = '0000-00-00';

            $prod_sc_res = cms_query("SELECT SUM(`stock_condicionado`) as `stock_condicionado`, `data_condicionada` FROM `registos_stocks_condicionados` WHERE `sku`='" . $prod['sku'] . "' AND `iddeposito` IN (".$ids_depositos.") AND `data_condicionada`>NOW() GROUP BY `data_condicionada`");
            while($prod_sc_row = cms_fetch_assoc($prod_sc_res)){

                if( (int)$CONFIG_OPTIONS['hide_conditioned_stock_date'] == 1 ){
                    $prod_sc_row['data_condicionada'] = "9999-99-99";
                }

                $disp_condicionada_temp["inventory_conditioned_arr"][] = $prod_sc_row;

            }

        }

        if( $prod['stock_condicionado'] > 0 && $prod['data_condicionada'] > date("Y-m-d") ){

            if( (int)$CONFIG_OPTIONS['hide_conditioned_stock_date'] == 1 ){
                $prod['data_condicionada'] = "9999-99-99";
            }
            
            $disp_condicionada_temp["inventory_conditioned_quantity"] = $prod['stock_condicionado'];
            $disp_condicionada_temp["inventory_conditioned_date"] = $prod['data_condicionada'];

        }
        
        
        if( (int)$MARKET['depositos_condicionados_ativo'] == 1 && trim($ids_depositos) != '' && trim($MARKET['depositos_condicionados']) != ''
            && $arr_stock['venda_negativo'] == 0 && $arr_stock['produto_digital'] == 0 && (int)$GLOBALS["STOCK_REGRAS_CATALOGO"] <= 0 ){
            
            $ids_depositos_cond = explode(",", $MARKET['depositos_condicionados']);
            $ids_depositos_real = explode(",", $ids_depositos);
            $ids_depositos_cond = array_intersect($ids_depositos_cond, $ids_depositos_real);
            
            $arr_stock_cond = Array();
            $arr_deposito_cond = Array();
            __getStock($prod['sku'], implode(',', $ids_depositos_cond), $arr_deposito_cond, $arr_stock_cond);
            
            $stock_real = $disp_condicionada_temp["stock"] = $arr_stock['stock_real'] - $arr_stock_cond['stock_real'];
            
            if( $arr_stock_cond['stock_real'] > 0 && $qtd > $stock_real ){
                
                $stock_cond = $disp_condicionada_temp["inventory_conditioned_quantity"] = $qtd - $stock_real;
                
                $dc_info = ['ref'                => $arr['ref'],
                            'cor_name'           => $arr['cor_name'],
                            'tamanho'            => $arr['tamanho'],
                            'stock_normal'       => $stock_real,
                            'stock_condicionado' => $stock_cond ];
            }
            
        }
        
        $arr['disp_condicionada'] = json_encode($disp_condicionada_temp);    
    }
    
    # Entrega Geograficamente Limitada
    if( isset($_COOKIE["USER_ZIP"]) && trim($prod['generico30']) != "" ){
        
        $geo_zip = preg_replace( "/[^0-9]/", "", base64_decode($_COOKIE["USER_ZIP"]) );
        
        $sql_express = "SELECT ec_exp.id, GROUP_CONCAT( DISTINCT ec_exp.id_deposito) AS depositos
                        FROM ec_shipping_express ec_exp
                          INNER JOIN ec_shipping ec
                              ON ec.id=ec_exp.id_shipping 
                                  AND ec.id IN(".$prod['generico30'].")
                                  AND ec.id IN(".$MARKET['metodos_envio'].")  
                                  AND ec.geo_limited = 1
                          INNER JOIN registos_stocks rs
                          	  ON rs.iddeposito = ec_exp.id_deposito 
                                  AND rs.sku = '".$prod['sku']."' 
                                  AND ((rs.stock-rs.margem_seguranca)>0 OR rs.venda_negativo=1 OR rs.produto_digital=1)
                        WHERE ec_exp.codpostal_inicio <= '".$geo_zip."' 
                              AND ec_exp.codpostal_fim >= '".$geo_zip."' 
                              AND ec_exp.id_pais='".$COUNTRY['id']."'
                              AND ec.tipo_envio=97
                        LIMIT 1"; 
        $geo_zip = cms_fetch_assoc( cms_query($sql_express) );
        if( (int)$geo_zip['id'] > 0 ){
            
            $tipo_linha = 4;
            
            if( trim($geo_zip['depositos']) != "" ) $arr['deposito'] = $geo_zip['depositos']; 
            
            $arr['obs'] = base64_decode($_COOKIE["USER_ZIP"]);
            
        }else{
            
            $sql_express = "SELECT id
                              FROM ec_shipping
                              WHERE id IN(".$prod['generico30'].")
                                AND id IN(".$MARKET['metodos_envio'].")  
                                AND geo_limited = 1
                                AND tipo_envio=97
                              LIMIT 1";  
            
            $has_geo = cms_fetch_assoc( cms_query($sql_express) );    
            if( (int)$has_geo['id'] > 0 ){
                return serialize( array( "error" => 1 ) );
            }
            
        }
        
    }
    
    
    # Shipping Express
    if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){
        
        $page = call_api_func('get_pagina', $id, "_trubricas");
        if( $page['sublevel'] == 74 ){
        
            $express_zip_code = preg_replace( "/[^0-9]/", "", base64_decode($_COOKIE['SYS_EXP_ZIP']) );
        
            $sql_express = "SELECT GROUP_CONCAT( DISTINCT `registos_stocks`.`iddeposito` ) AS depositos
                            FROM `registos_stocks`
                              INNER JOIN `ec_depositos_codigos_postais` ON `ec_depositos_codigos_postais`.`deposito_id` = `registos_stocks`.`iddeposito` 
                                  AND `ec_depositos_codigos_postais`.`cod_postal_inicio` <= '".$express_zip_code."'
                                  AND `ec_depositos_codigos_postais`.`cod_postal_fim` >= '".$express_zip_code."' 
                                  AND `ec_depositos_codigos_postais`.`pais_id`='".$COUNTRY['id']."'
                            WHERE `registos_stocks`.`sku` = '".$prod['sku']."' 
                                  AND ((registos_stocks.stock-registos_stocks.margem_seguranca)>0 OR `registos_stocks`.`venda_negativo`=1 OR `registos_stocks`.`produto_digital`=1)
                                  AND `registos_stocks`.`iddeposito` IN (".$MARKET['deposito'].")
                            LIMIT 1"; 
            $row_express = cms_fetch_assoc( cms_query($sql_express) );
            if( trim($row_express['depositos']) != '' ){
                $tipo_linha = 7;
                $arr['deposito'] = $row_express['depositos'];
                $arr['obs'] = base64_decode($_COOKIE["SYS_EXP_ZIP"]);
            }else{
                return serialize( array( "error" => 1 ) );
            }
            
        }

    }
    
    
    # 0 - produto normal; 
    # 1 - produto configurador avançado; 
    # 2 - serviço que não permite desconto de campanha; 
    # 3 - produto orçamentado;
    # 4 - produto com entrega geograficamente limitada; 
    # 5 - subscrições prime;
    # 6 - Não atualiza preços e stocks - Pagamentos emitidos / Propostas;
    # 7 - Entrega expresso;
    # 8 - Produto de sorteio;
    # 9 - para que este produto seja ignorado na aplicação, ou cálculo de descontos (campanhas, vouchers, e desconto de modalidade de pagamento);
    # 10 - preço forçado para campanha de MA
    # 11 - POS > produtos do catalogo exclusivo de loja
    # 12 - POS > arranjos
    $arr['tipo_linha'] = $tipo_linha;
    
    if((int)$id_conjunto > 0){
        $arr['obs'] = $id_conjunto; 
        $arr['info_pack'] = $id_conjunto;
    }    
    
    
    set_status(3, $arr, '', $id, $page_count);
    
    if( !is_numeric($userID) ){
        $product                    = array();
        $product["id"]              = $arr["pid"];
        $product["available"]       = 1;
        $product["price"]["value"]  = $arr['valoruni'];
        set_clientes_status($product);
    }

    if(trim($unit_store_ids)!='' && $unit_store_ids!='0' && stores_units_active_for_user()){
        $unit_store_ids_arr = array_unique(explode('||', base64_decode($unit_store_ids)));
        $_SESSION['EC_USER']['last_used_stores_units'] = $unit_store_ids_arr;

        foreach($unit_store_ids_arr as $unit_store_id){
            $arr['col1'] = $unit_store_id;
            if ($import_cart == 2) {
                return serialize(["prod_data" => base64_encode(serialize($arr))]);
            }
            $eComm->addToBasket($arr);
        }
        
    }else{
        if ($import_cart == 2) {
            return serialize( ["prod_data" => base64_encode( serialize($arr) )] );
        }
        $eComm->addToBasket($arr);
    }
    
    unset($_SESSION['MAN_VOUCHER_DEL']);
        
    
    $resp = array();
    
    if( !empty($dc_info) ){
        $resp['dc_info'] = $dc_info;
    }
    
    
    
    # $import_cart utilizado para importar produtos para o carrinho desde excel no B2B
    if($import_cart==0){

        
        $family = call_api_func('get_line_table', 'registos_familias', "id='".$prod["familia"]."'");
        $categoria = call_api_func('get_line_table', 'registos_categorias', "id='".$prod["categoria"]."'");
       
        if((int)$B2B==0){
            $tag_manager = tracking_from_tag_manager('addToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, 
                                                        "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'],
                                                        "ID_UTILIZADOR" => $session_id,  
                                                        "SKU_PRODUTO" => $prod['sku'], 
                                                        "ID_PRODUTO" => $prod['id'], 
                                                        "SKU_GROUP" => $prod['sku_group'], 
                                                        "SKU_FAMILY" => $prod['sku_family'], 
                                                        "FAMILIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $family['nomept']), 
                                                        "CATEGORIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $categoria['nomept']), 
                                                        "URL_PRODUTO" => $sslocation."/?pid=".$PID_ORIGINAL, 
                                                        "IMAGEM_URL_PRODUTO" => $arr['image'], 
                                                        "VALOR_PRODUTO" => $preco['precophp'], 
                                                        "DESCRICAO_PRODUTO" => "" , 
                                                        "NOME_PRODUTO" => cms_real_escape_string($prod['nome'.$LG]), 
                                                        "MOEDA" => $MOEDA['abreviatura']  ));  
        }                                            
                                                    
        $eComm->getRappel((int)$GLOBALS["REGRAS_CATALOGO"], $unit_store_id);
    
    
        if($pack!=2){
        

            $resp['cart'] = OBJ_cart(false);              
            
            $resp['cart']['product_id_add'] = $prod['id'];     # usado para o frontend saber qual o produto que acabou de ser adicionado, e não se baralhar com produtos configurados 

            $data = serialize($resp['cart']);
            $data = gzdeflate($data,  9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);
            $_SESSION["SHOPPINGCART"] = $data;
            
            $resp['trackers']       = base64_encode($tag_manager);
            
            $resp['product_id_add'] = $PID_ORIGINAL; #usado para o getRecommendation
            
            
            
            # 2020-05-26 - Campanha MA de recomendação de produtos em site - 9200     
            if(isset($_SESSION['MA_CARTREC'][$prod['sku_family']])){
               
                $linhas_inseridas_s = "SELECT id FROM ec_encomendas_lines WHERE status='0' AND id_cliente='$userID' AND pid='".$prod['id']."' ";
                
                $linhas_inseridas_q = cms_query($linhas_inseridas_s);
                while($linhas_inseridas_r = cms_fetch_assoc($linhas_inseridas_q)){
                    $SQL = "INSERT INTO ec_encomendas_lines_props (order_line_id, property, property_value) VALUES (".$linhas_inseridas_r['id'].", 'MA_CARTREC', '".date('Y-m-d H:i:s')."')";
                    cms_query($SQL);
                }
            
            }
            
            # 2020-06-25 - Campanha MA de recomendação de produtos em site - 9250     
            if(isset($_SESSION['MA_PRODREC'][$prod['sku_family']])){
               
                $linhas_inseridas_s = "SELECT id FROM ec_encomendas_lines WHERE status='0' AND id_cliente='$userID' AND pid='".$prod['id']."' ";
                
                $linhas_inseridas_q = cms_query($linhas_inseridas_s);
                while($linhas_inseridas_r = cms_fetch_assoc($linhas_inseridas_q)){
                    $SQL = "INSERT INTO ec_encomendas_lines_props (order_line_id, property, property_value) VALUES (".$linhas_inseridas_r['id'].", 'MA_PRODREC', '".date('Y-m-d H:i:s')."')";
                    cms_query($SQL);
                }
            
            }
            
            # 2021-10-25 - Campanha MA de bloco de produtos em site - 9280     
            if(isset($_SESSION['MA_BLOCKREC'][$prod['sku_family']])){
               
                $linhas_inseridas_s = "SELECT id FROM ec_encomendas_lines WHERE status='0' AND id_cliente='$userID' AND pid='".$prod['id']."' ";
                
                $linhas_inseridas_q = cms_query($linhas_inseridas_s);
                while($linhas_inseridas_r = cms_fetch_assoc($linhas_inseridas_q)){
                    $SQL = "INSERT INTO ec_encomendas_lines_props (order_line_id, property, property_value) VALUES (".$linhas_inseridas_r['id'].", 'MA_9280', '".date('Y-m-d H:i:s')."')";
                    cms_query($SQL);
                }
            
            }
            
            #2025-07-22 - Campanha MA de recomendação de produtos no resumo d carrinho - 9210     
            if(isset($_SESSION['MA_CARTSUG'][$prod['sku_group']]) && $MA=='9210'){
               
                $linhas_inseridas_s = "SELECT id FROM ec_encomendas_lines WHERE status='0' AND id_cliente='$userID' AND pid='".$prod['id']."' ";
                
                $linhas_inseridas_q = cms_query($linhas_inseridas_s);
                while($linhas_inseridas_r = cms_fetch_assoc($linhas_inseridas_q)){
                    $SQL = "INSERT INTO ec_encomendas_lines_props (order_line_id, property, property_value) VALUES (".$linhas_inseridas_r['id'].", 'MA_CARTSUG', '".date('Y-m-d H:i:s')."')";
                    cms_query($SQL);
                }
            
            }                       
            
        }
        
    }else{
        $last_id_insert = cms_insert_id();
        
        if($import_cart==3) $resp['cart'] = OBJ_cart(false);
                
        if((int)$last_id_insert>0) $resp['status'] = true;
        else $resp['status'] = false;
        
        
        if((int)$B2B>0 && $resp['status']){
        
            # Dashboard Tracking *********************************************************************************************************
            require_once '../plugins/tracker/funnel.php';
            $Funnel = new Funnel();
            $Funnel->event(1);
        }
        

        return serialize($resp);
    }
    
    
    
    # Dashboard Tracking *********************************************************************************************************
    require_once '../plugins/tracker/funnel.php';
    $Funnel = new Funnel();
    $Funnel->event(1);
        
        
    if((int)$B2B==0){

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
                
            if($prod['marca']>0 && (!isset($brand) || empty($brand) )){
                $brand = call_api_func("get_line_table", "registos_marcas", "id=".$prod['marca']);
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
    }
    
    
    

    return serialize($resp);
}

?>
