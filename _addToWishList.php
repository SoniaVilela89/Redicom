<?

function _addToWishList($pid=null, $id=null, $cat=null, $page_count=null, $tipo_linha=null, $group_id=null){
        
    if( is_null($pid) ){

        $pid            = params('pid');
        $id             = params('id');
        $cat            = params('cat');
        $page_count     = params('page_count');
        $tipo_linha     = params('tipo_linha');
        $group_id       = params('group_id');

    }
    
    global $userID, $eComm, $LG, $COUNTRY, $MARKET, $MOEDA, $fx, $sslocation, $CONFIG_TEMPLATES_PARAMS, $session_id, $B2B;
    
    #$wishsql = cms_query("select * from registos_wishlist where ref='$sku' and id_cliente='$userID' and status='0' LIMIT 0,1 ");
    #$wish    = cms_fetch_assoc($wishsql);
    #if( $wish['id']>0 ){
    #    return serialize(array("msg"=>"Este produto já se encontra na wishlist."));
    #}
    
    $priceList = $MARKET['lista_preco'];
    
    
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
        }
    }
    

    $q = "SELECT registos.*,registos_precos.preco 
            FROM registos
                INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
            WHERE activo='1'
                AND  registos.id='$pid'
                AND registos_precos.idListaPreco='".$priceList."'
                AND registos_precos.preco>0
                AND (registos_precos.data<=CURDATE() OR registos_precos.data IS NULL)
            GROUP BY registos.id      
            LIMIT 0,1";
            
    $prod = cms_fetch_assoc( cms_query($q) );

    $cor = getColor($prod['cor'],$prod['sku']);
    $sizes = getTamanho($prod['tamanho']);

    require_once(__DIR__ . "/_getSingleImage.php");
    $image = _getSingleImage(0, 0, 0, $prod['sku'], 5);

    if( strpos($image, "no-image") !== false ){

        $img_list_0 = '';
        if( file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU/" . $prod['sku'] . "_0.jpg") ){
            $img_list_0 = "images_prods_static/SKU/" . $prod['sku'] . "_0.jpg";
        }elseif( file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU_GROUP/" . $prod['sku_group'] . "_0.jpg") ){
            $img_list_0 = "images_prods_static/SKU_GROUP/" . $prod['sku_group'] . "_0.jpg";
        }elseif( file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . "images_prods_static/SKU_FAMILY/" . $prod['sku_family'] . "_0.jpg") ){
            $img_list_0 = "images_prods_static/SKU_FAMILY/" . $prod['sku_family'] . "_0.jpg";
        }

    }

    if( $img_list_0 != '' ){
        $img_selected = $img_list_0;
    }else{
        $img_selected = $image;
    }

    $preco = __getPrice($prod['sku'], $priceList, 0, $prod);

    $arr = array();
    $arr['id_cliente'] = $userID;
    $arr['status'] = "0";
    $arr['pid'] = $prod['id'];
    $arr['ref'] = $prod['sku'];
    $arr['sku_family'] = $prod['sku_family'];
    $arr['sku_group'] = $prod['sku_group'];
    $arr['nome'] = $prod['desc'.$LG];
    $arr['unidade_portes'] = $prod['weight'];
    $arr['composition'] = "";
    $arr['cor_id'] = $cor['id'];
    $arr['cor_cod'] =  $cor['color_code'];
    $arr['cor_name'] = $cor['long_name'];
    $arr['largura'] = "";
    $arr['altura'] = "";
    $arr['peso'] = $prod['peso'];
    $arr['tamanho'] = $sizes['nome'];
    $arr['qnt'] = 1;
    
    $today = date("Y-m-d");
    $arr['data'] = $today;

    $arr['valoruni'] = $preco['precophp'];
    $arr['valoruni_anterior'] = $preco['preco_riscado'];
    $arr['valoruni_desconto'] = $preco['desconto_valor_php'];
    $arr['datafim_desconto'] = $preco['data'];
    $arr['id_desconto'] = $preco['id_desconto'];

    $arr['pais_iso'] = $_SESSION['_COUNTRY']['country_code'];
    $arr['moeda'] = $MOEDA['id'];
    $arr['taxa_cambio'] = $MOEDA['cambio'];
    $arr['moeda_simbolo'] = $MOEDA['abreviatura'];
    $arr['moeda_prefixo'] = $MOEDA['prefixo'];
    $arr['moeda_sufixo']  = $MOEDA['sufixo'];

    $arr['moeda_decimais']  = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares']  = $MOEDA['casa_milhares'];

    $arr['mercado'] = $MARKET['id'];
    $arr['deposito'] = $MARKET['deposito'];
    $arr['lista_preco'] = $priceList;

//     if( $preco['desconto_valor']==1 ){
//         $arr['promotion'] = 1;
//     }
    $arr['promotion'] = $preco['promo'];


    $arr['idioma_user'] = $LG;
    $arr['pais_cliente'] = $_SESSION['_COUNTRY']['id'];


    $img_prd = get_image_SRC($img_selected, 300, 300);
    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    #0 - produto normal; 1 - produto configurador avançado; 2 - serviço que não permite desconto de campanha; 3 - produto orçamentado
    $arr['tipo_linha'] = (int)$tipo_linha;

    set_status(4, $arr, '', $id, $page_count);

    if( !empty($group_id) ){

        $arr_groups = explode("||", $group_id);
        foreach( $arr_groups as $group ){
            
            $product_in_list = get_line_table("registos_wishlist", "`id_cliente`='".$userID."' AND `status`='0' AND `wishlist_grupo_id`='".$group."' AND `ref`='".$arr['ref']."'", "`id`");
            if( (int)$product_in_list['id'] > 0 ){
                continue;
            }
            
            $arr['wishlist_grupo_id'] = $group;

            $eComm->AddToWishlist($arr);

        }
        
    }else{
        $eComm->AddToWishlist($arr);
    }
   
    $family = call_api_func('get_line_table', 'registos_familias', "id='".$prod["familia"]."'");
    $categoria = call_api_func('get_line_table', 'registos_categorias', "id='".$prod["categoria"]."'");
       
    $tag_manager = tracking_from_tag_manager('addToWishlist', $COUNTRY['id'], array("SITE_LANG" => $LG, 
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
    
    $wishlist = OBJ_lines(0, 2, 5, $userID, "", false, $group_id);


    # remove registos da wishlist - no máximo a wishlist fica com $wishlist_max mais recentes
    $wishlist_total = count($wishlist);
    $wishlist_max   = 99;
    if($wishlist_total > $wishlist_max) {
        $dif = $wishlist_total - $wishlist_max;
        @cms_query("DELETE FROM registos_wishlist WHERE id_cliente='".$userID."' ORDER BY id ASC LIMIT ".$dif);
        
        $wishlist = OBJ_lines(0, 2, 5, $userID, "", false, $group_id);
        $wishlist_total = count($wishlist);
    }
    

    $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
    
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
        
        if( empty($arr['promotion']) ){$arr['promotion'] = 0;}
        
        #<Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>
        include $_SERVER['DOCUMENT_ROOT'] . "/custom/shared/addons_info.php";
        $collectApiExtraClassifier = getCollectApiExtraClassifier();
        $campos                    = $collectApiExtraClassifier['campos'];
        $COLLECT_API_LANG          = $collectApiExtraClassifier['COLLECT_API_LANG'];   
        $num_campos_adicionais     = $collectApiExtraClassifier['num_campos_adicionais'];
        #<Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>  
        
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

        foreach($wishlist as $line){
            $cart_product = buildCartProductInfoForCollectAPI($line);

            $line_ungrouped = array_fill(0, $line['quantity'], $cart_product); # copy the line "$line['quantity']" times (creates an array with as many lines as it's quantity)
            $cart_ungrouped = array_merge($cart_ungrouped, $line_ungrouped);

        }

        $change_cart_info = ['wishlist' => ['items' => $cart_ungrouped], 'country' => $COUNTRY, 'currency' => $MOEDA];

        try{
            $collect_api->setEvent(CollectAPI::PRODUCT_ADDED_TO_WISHLIST, $_SESSION['EC_USER'], $event_info);
            
            # 2025-10-22 - Este evento não é utilizado por nenhum Publisher
            #$collect_api->setEvent(CollectAPI::WISHLIST_CHANGE, $_SESSION['EC_USER'], $change_cart_info);
            
        }catch(Exception $e){
            // Nothing to do here         
        }
        
    }


    $resp=array();
    $resp['wishlist'] = $wishlist;
    $resp['trackers'] = base64_encode($tag_manager.$html);

    if( (int)$B2B > 0 ){
        $wishlist_groups_qty = cms_fetch_assoc( cms_query("SELECT COUNT(`id`) AS `qty` FROM registos_wishlist WHERE sku_family='".$prod['sku_family']."' AND id_cliente='$userID' AND status='0'") );
        $resp['wishlist_groups_qty'] = (int)$wishlist_groups_qty['qty'];
    }
    
    
    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    $_SESSION['sys_qr_qtw'] = $wishlist_total;

    return serialize($resp);
        
}

?>
