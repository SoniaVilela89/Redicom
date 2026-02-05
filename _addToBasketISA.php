<?

#Usado no ISA

function _addToBasketISA($sku=null, $cliente=null, $chk=null){

		global $sslocation;
	  global $userID;
    global $CONFIG_OPTIONS;
    global $B2B;
    global $CHECKOUT_LAYOUT_VERSION;
    global $CONFIG_IMAGE_SIZE;
    global $eComm;
    
    if( is_null($sku) ){
        return serialize(array("5"));
    }
    
    
  	$cliente = cms_fetch_assoc(cms_query("SELECT * FROM _tusers WHERE id='$cliente'"));
  	if ( (int)$cliente['id'] == 0 )
  	  return serialize(array("10"));

    //if ($chk != md5($cliente[id].$cliente[email].$cliente[telefone]))
    // return serialize(array("15"));
    $_SESSION['EC_USER']['email'] = $cliente['email'];
    $_SESSION['EC_USER']['tipo']  = $cliente['tipo'];
    $_SESSION['_COUNTRY']['id']   = $cliente['pais'];
    
    $userID = $cliente['id'];
   
   
  	$_SESSION['_MARKET'] = $MARKET = cms_fetch_assoc(cms_query("SELECT * FROM ec_mercado WHERE CONCAT(',',pais,',') LIKE '%,$cliente[pais],%'"));
  	if( (int)$MARKET['id'] == 0 )
  	  return serialize(array("20"));
  	 
  	$_SESSION['_COUNTRY'] = $COUNTRY = cms_fetch_assoc(cms_query("SELECT * FROM ec_paises WHERE id='$cliente[pais]'"));
  	if( (int)$COUNTRY['id'] == 0 )
  	  return serialize(array("21"));
  	 
    $_SESSION['_MOEDA'] = $MOEDA = cms_fetch_assoc(cms_query("SELECT * FROM ec_moedas WHERE id='$MARKET[moeda]'"));
  	if( (int)$MOEDA['id'] == 0 )
  	  return serialize(array("22"));
  	 
		 	 
    $sku = base64_decode($sku);	 
  	 
  	$priceList 		= $MARKET['lista_preco'];
    $catalogo			= $MARKET['catalogo'];
    
        
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
      
    
    
    $LG						= "pt";

    $q = "SELECT registos.*,registos_precos.preco 
          FROM registos
              INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE activo='1' 
            AND  registos.sku='$sku'
            AND registos_precos.idListaPreco='".$priceList."'
            AND registos_precos.preco>0
          GROUP BY registos.id    
          LIMIT 0,1";

    $sql  = cms_query($q);
    $prod = cms_fetch_assoc($sql);
    

    if( (int)$prod['id'] == 0 )
  	  return serialize(array("30"));

    
    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);



    
    $cor  = getColor($prod['cor'], $prod['sku'], "", "", 1);

    if( $prod['material'] > 0 ){
        $matR = getMaterial($prod['material']);

        $cor['short_name'] = $matR['name'].' '.$cor['short_name'];
        $cor['long_name'] = $matR['name'].' '.$cor['long_name'];

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
        $preco = __getPrice($prod['sku'], $priceList, 0, $prod, 0, $sku_configurador_original, $qtd, $id_lote);
    }else{
        $preco = __getPrice($prod['sku'], $priceList, 0, array(), 0, $sku_configurador_original, $original_qtd, $id_lote);
    }
    
    
    
  

    $arr = array();
    $arr['status']          = "0";
    $arr['page_id']         = "1";
    $arr['page_cat_id']     = 0;
    $arr['page_count']      = 0;

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
    $arr['peso']            = $prod['weight'];
    $arr['tamanho']         = $sizes['nome'];

    if( (int)$CONFIG_OPTIONS["SHOW_CHECKOUT_PROD_BRAND"] == 1 ){
        $brand        = call_api_func("get_line_table", "registos_marcas", "id=".$prod['marca']);
        $arr['marca'] = $brand['nome'.$LG];
    }

    $arr['unidade_portes'] = (float)$prod['weight'];
    if( $arr['unidade_portes'] ==0 ) $arr['unidade_portes']=1;  
    
    $arr['qnt'] = 1;

    $arr['composition'] = "";
        
    $arr_compositon = array();
    
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
        $arr_compositon[] = $prod['variante'];
    }

    $arr['composition'] = implode(' - ', $arr_compositon);

    $arr['valoruni']            = $preco['precophp'];
    $arr['valoruni_anterior']   = $preco['preco_riscado'];
    $arr['valoruni_desconto']   = $preco['desconto_valor_php'];

    $arr['datafim_desconto']    = $preco['data'];
    $arr['id_desconto']         = $preco['id_desconto'];   
    
    
    $arr['pvr']                 = $preco['preco_base'];
    $arr['pvr_desconto']        = $preco['desconto_valor_base'];

    $arr['desconto_linha_perc'] = $preco['desconto_base'];
    $arr['promo_perc']          = $preco['desconto'];
    
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

    $arr['deposito'] = $MARKET['deposito'];
    if( trim($preco['deposito']) != "" ){
        $arr['deposito'] = $preco['deposito'];
    }
    
    $arr['lista_preco'] = $priceList;
    if( $preco['promo'] == 1 ){
        $arr['promotion'] = 1;
        if( $preco['list_price'] > 0 ) $arr['lista_preco'] = $preco['list_price'];
    }
    
    $arr['pack']              = "0";
    $arr['novidade']          = $prod['novidade'];
    $arr['servicos']          = $prod['servicos'];
    $arr['width']             = (float)$prod['width'];
    $arr['height']            = (float)$prod['height'];
    $arr['lenght']            = (float)$prod['lenght'];
    
    if($forcar_sem_iva==1) $prod['iva'] = 4;
     
    $arr['iva_taxa_id']       = $prod['iva'];

    if( (int)$CHECKOUT_LAYOUT_VERSION == 0 ){
        if( $B2B > 0 ) $img_prd = get_image_SRC($img_selected, 160, 160, 3);
        else $img_prd = get_image_SRC($img_selected, 160, 200, 2);
    }else{
        $tamanhos = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['regular']);  
        $img_prd = get_image_SRC($img_selected, 200, (200*$tamanhos[1])/$tamanhos[0], 2);
    }
    
    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    if( trim($prod["composto"]) != "" ){
        $arr['boxes']       = 1;      
        $arr['qtd_extra']   = $arr['tamanho']-1;   
    }

    $arr['tipo_linha'] = $tipo_linha;
    
    $eComm->addToBasket($arr);

    return serialize(array("1"));
}

?>
