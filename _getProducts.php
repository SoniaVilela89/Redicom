<?

#Update date: 23/11/2017

function _getProducts($int_page_id=null, $category=null, $offs=null, $pages=1, $page_count=null){

    global $id, $cat, $eComm, $userID, $fx, $LG, $CACHE_LISTA_PRODUTOS, $CONFIG_TEMPLATES_PARAMS, $CONFIG_NOHASH_FILTROS, $CACHE_KEY, $_LISTAGEM_SEM_VARIANTES, $CONFIG_OPTIONS, $B2B_LAYOUT;
    global $slocation, $detect, $collect_api, $SITE_CHANNEL;    

    if ($int_page_id > 0){
         $id          = $page_id = (int)$int_page_id;
         $cat         = (int)$category;
         $offset      = (int)$offs;
         $pages       = (int)$pages;
         $page_count  = (int)$page_count;
    }else{
         $page_id     = (int)params('page_id');
         $cat         = (int)params('cat');
         $offset      = (int)params('offset');
         $pages       = (int)params('pages');
         $page_count  = (int)params('page_count');
         
         
         # 2024-10-15
        # Para impedir ataques de bots
        # na api não existe parametros neste controlador
        /*if(count($_GET)>0){
            ob_end_clean();
            header('HTTP/1.1 403 Forbidden');
            exit;
        }*/
                          
    }


    if ((int)$pages<1) $pages=1;
    
    
    
    
    
    # 2023-04-27
    # Para validar que os bots não fazem acessos diretos com p= a muitas páginas
    if($pages==1){
        $control_page = $page_count;
        if($control_page==0) $control_page = 1;       
        $_SESSION['SYS_CTRPG'][$id] = $control_page;
    }
    
    if($pages>1 && (!isset($_SESSION['SYS_CTRPG'][$id]) || $_SESSION['SYS_CTRPG'][$id]<$pages-1)){   
        ob_end_clean();
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    
    
    
    $PROMO = getAvailablePromoForUser();
        
    $resp = array();
      
       
    if($page_count==0){                
        
        
          $mobile = "DESKTOP";
          if(file_exists('lib/class.mobile_detect.php')){
              if($detect->isMobile() && !$detect->isTablet()){
                  $mobile = 'MOBILE';
              }
          }else{
              if( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'],'Android') ){
                  $mobile = "MOBILE";
              }
          } 
        
        
          $scope            = array();
          $scope['ID']      = $page_id;
          $scope['CAT']     = $cat; 
          $scope['PAIS']    = $_SESSION['_COUNTRY']['id'];
          $scope['LG']      = $_SESSION['LG'];
          $scope['DEVICE']  = $mobile;
          $scope['PROMO']   = implode(',', $PROMO["promos"]);
          
          $_cacheid       = $CACHE_KEY."LISTAGEM_".implode('_', $scope);
      
          $dados = $fx->_GetCache($_cacheid, 1440); #1 dia
        
          if ($dados!=false && !isset($_GET['nocache'])){
          
              $resp = unserialize($dados);
              
              $resp['shop'] = call_api_func('OBJ_shop_mini'); 
                
          }else{  
          
              $resp = array();
                  
              $row                    = call_api_func('get_pagina', $page_id, "_trubricas");
              
              $resp['selected_page']  = call_api_func('OBJ_page', $row ,$page_id, $cat);
              $resp['tipo_destaque']  = $row['tipo_destaque'];
              
              $row_cat = call_api_func('get_pagina', $cat, "_trubricas");
              
              #Aplica o banner da promoção nas páginas que apliquem essa promoção            
              if((int)$PROMO['values']["banner_page"]>0 && $row_cat['sublevel']==54 && in_array($PROMO['values']['id'], explode(',', $row_cat['promocoes']))){
                  $resp['selected_page']["banner"] = array();
                  $resp['selected_page']["banner"][] = call_api_func('OBJ_banner', $PROMO['values']["banner_page"], 0, "default");   
              }
                   
              #Construir o caminho
              $home = call_api_func('get_pagina', 1, "_trubricas");
              $bc   = array();
              $bc[] = array( "name"     => $home['nome'.$LG],
                             "link"     => "index.php",
                             "sublevel" => (int)$v["sublevel"],
                             "without_click" => 0);
                             
              #Paginas estrutura de navegação
              $caminho  = call_api_func('get_breadcrumb', $page_id);
              $bc       = array();
          
              foreach( $caminho as $k => $v ){
              
                  $link = $v['link'];
                  
                  if($v["id_pag"]!=36 && $v["id_pag"]!=41 && $v["id_pag"]!=1 ) {
                    
                      $cat_pag = call_api_func('get_pagina', $cat, "_trubricas");
                      
                      if($cat_pag['sublevel']==54 || ($cat_pag["sublevel"]==30 && $k==1) ){
                          $link = "index.php?id=".$cat;
                      }elseif($cat_pag['usar_titulo_na_url']>0 && $v['subpagina']==0){
                          $link = "index.php?id=".$v["id_pag"]."&cat=".$cat."&u=".$cat_pag['usar_titulo_na_url'];
                      }else{
                          $link = "index.php?id=".$v["id_pag"]."&cat=".$cat;
                      }
                      
                      if($v["subpagina"]==0 && $v["est_nav"]==1) {
                        $pag_menu_superior = call_api_func('get_pagina', $cat, "_trubricas");
                        if($pag_menu_superior['sublevel']==49 || $pag_menu_superior['sublevel']==53)
                            $link = "index.php?id=$cat";
                      }
                    
                 }
                  
                  $bc[] = array(
                      "name" => $v['name'],
                      "link" => $link,
                      "without_click" => $v['without_click']
                  );
              }
          
              $resp['selected_page']['breadcrumb'] = $bc;
          
          
              $caminho_pai                = call_api_func('get_path', $page_id, false, $LG);
              $keys                       =  array_keys($caminho_pai);
              $pai                        = $keys[0];
          
              $row                        = call_api_func('get_pagina', $pai, "_trubricas");
             
              $pag_pai_temp               = call_api_func('OBJ_page', $row, $pai, $cat);              
              $pag_pai_temp['childs']     = get_menu($pai, $page_id, $cat);
                            
              $resp['navigation_pages']   = $pag_pai_temp;
          
          
              $resp['productlist_blocks'] = call_api_func('get_productlist_blocks', $page_id);
              
              $fx->_SetCache($_cacheid, serialize($resp), 1440);
              
              
              $resp['shop'] = call_api_func('OBJ_shop_mini');         
          }      
      
    }   
    
                       
     $user_types = explode(",", $_SESSION['EC_USER']['tipo']);
     
                         
     foreach ($resp['navigation_pages']['childs'] as $k =>$v) {
     
                                        
        if(trim($v['crit_canal_tipos'])!='' && (int)$SITE_CHANNEL>0){
            $valid_channels = explode(',', $v['crit_canal_tipos']);     
            if( !in_array($SITE_CHANNEL, $valid_channels) ){
                unset($resp['navigation_pages']['childs'][$k]);
                continue;
            }
        }
    
        $arr_type = explode(",", $v["type"]);
        if (count(array_intersect($arr_type, $user_types)) === 0 && $v["type"] !== "") {
            unset($resp['navigation_pages']['childs'][$k]);
            continue;
        }
        
        foreach($v["childs"] as $kk => $vv){
        
            if(trim($vv['crit_canal_tipos'])!='' && (int)$SITE_CHANNEL>0){
                $valid_channels = explode(',', $vv['crit_canal_tipos']);     
                if( !in_array($SITE_CHANNEL, $valid_channels) ){
                    unset($resp['navigation_pages']['childs'][$k]["childs"][$kk]);
                    continue;
                }
            }
            
            
            $arr_type = explode(",", $vv["type"]);
            if (count(array_intersect($arr_type, $user_types)) === 0 && $vv["type"] !== "") {
                unset($vv["childs"][$kk]);
                continue;
            }
        
        }
               
    }   
      
      
    # 2021-08-20
    # Aplica a ordenação por defeito definida na estrutura de navegação
    if( (int)$resp['selected_page']['default_ordering'] > 0 && !isset($_SESSION['order_active'][$page_id]) ){
      
        if( (int)$resp['selected_page']['default_ordering'] == 1 ) $_SESSION['order_active'][$page_id]['price'] = "asc";
        if( (int)$resp['selected_page']['default_ordering'] == 2 ) $_SESSION['order_active'][$page_id]['price'] = "desc";
        if( (int)$resp['selected_page']['default_ordering'] == 3 ) $_SESSION['order_active'][$page_id]['name'] = "asc";
        if( (int)$resp['selected_page']['default_ordering'] == 4 ) $_SESSION['order_active'][$page_id]['name'] = "desc";
    
    } 
    
    
    $temp_filters = array_merge_recursive((array)$_SESSION['filter_active'][$page_id], (array)$_SESSION['filter_active']['DRIVEME']);

     
    $scope = array();
    $scope['CAT']         = $cat;
    $scope['OFFSET']      = $offset;
    $scope['PAGES']       = $pages;
    $scope['PAGECOUNT']   = $page_count;
    $scope['PAIS']        = $_SESSION['_COUNTRY']['id'];
    $scope['LG']          = $_SESSION['LG'];
    $scope['ORDER']       = $_SESSION['order_active'][$page_id];
    $scope['FILTERS']     = serialize($temp_filters);
    $scope['PRICE_LIST']  = $_SESSION['_MARKET']['lista_preco'];    
    $scope['deposito']    = $_SESSION['_MARKET']['deposito'];
    $scope['PROMO']       = implode(',', $PROMO["promos"]);
    
    
    if(is_array($_SESSION['EC_USER']['margem']) && count($_SESSION['EC_USER']['margem'])>0){
        $scope['MARGEM']  = md5(base64_encode(serialize($_SESSION['EC_USER']['margem'])));
    }
    
    if((int)$_SESSION['EC_USER']['descontos_exclusivos']>0){
        $scope['DES_EXC'] = $_SESSION['EC_USER']['id']."|||".$_SESSION['EC_USER']['descontos_exclusivos'];
    }
    
    if((int)$_SESSION['EC_USER']['b2b_markup']>0){
        $scope['MARKUP'] = $_SESSION['EC_USER']['b2b_markup'];
    }
    
    if(trim($_GET['term'])!=''){
        $scope['TERM']    = $_GET['term'];    
    }
    
    # Shipping Express
    if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){

        $shipping_express_sublevel = $resp['selected_page']['sublevel'];
        if( empty($resp['selected_page']) ){
            $row = call_api_func('get_pagina', $page_id, "_trubricas");
            $shipping_express_sublevel = $row['sublevel'];
        }
        
        if( (int)$resp['selected_page']['navigation'] == 1 || $row['navigation'] == 1 ){
            $row_cat = call_api_func('get_pagina', $cat, "_trubricas");  
            $shipping_express_sublevel = $row_cat['sublevel'];
        }
        
        if( $shipping_express_sublevel == 74 ) $scope['SHIPPING_EXPRESS'] = $_COOKIE['SYS_EXP_ZIP'];

    }
    
    $_cacheid             = $CACHE_KEY."PLST_".$page_id.'_'.md5(serialize($scope));

    if($CACHE_LISTA_PRODUTOS>0) {
        $resp_cont = $fx->_GetCache($_cacheid, $CACHE_LISTA_PRODUTOS);
    }
             
    if (!$resp_cont || $_GET["nocache"]==1)
    {
            
        $temp_products_info              = call_api_func('get_products',$page_id, $offset, '', $pages, $page_count, $cat);
        $resp_cont['products']           = $temp_products_info['PRODUCTS'];
        $resp_cont['products_count']     = $temp_products_info['COUNT'];
        $resp_cont['filters']            = $temp_products_info['FILTROS'];
        $resp_cont['products_pids']      = $temp_products_info['PIDS'];
        
        $resp_cont['featured_product']   = $temp_products_info['FEATURED_PRODUCT'];
        $resp_cont['featured_template']  = $temp_products_info['FEATURED_TEMPLATE'];
        
        $max_conversions                 = $temp_products_info['MAX_CONVERSIONS'];
              
        $resp_cont['order_by']           = call_api_func('get_order_by', $page_id, $max_conversions);
       

        $resp_cont['pagination_offset']  = $offset;
                   
                     
        if($CONFIG_NOHASH_FILTROS==1){
            $resp_cont['filters_encode_raw'] = encodeFiltersRaw($id);    
        }else{                                                  
            $resp_cont['filters_encode'] = encodeFilters($id);
        }

        global $B2B;
        if( (int)$B2B > 0 ){
            $row_cat = call_api_func('get_pagina', $cat, "_trubricas");            
            if( $row_cat['sublevel'] == 65 ){
                $resp['module_page_tires'] = 1;
                $resp_cont['top_filters'] = $temp_products_info['FILTROS_TOPO'];
            }
        }

                                       
        $tot_filtros = 0;
        foreach($_SESSION['filter_active'][$page_id] as $k => $v){
            $tot_filtros += count($v);
        }
    
        $resp_cont['active_filters']       = ( isset($_SESSION['filter_active'][$page_id]) ) ? 1 : 0;
        $resp_cont['total_active_filters'] = $tot_filtros;
        $resp_cont['active_order_by']      = ( isset($_SESSION['order_active'][$page_id]) ) ? 1 : 0;
        
        $resp_cont['expressions']          = call_api_func('get_expressions');


        if($CACHE_LISTA_PRODUTOS>0) {
            # 2025-03-11
            # Acrescentado o count para só fazer cache se forem enontrados menus 
            if($resp_cont['products_count']>0) {
                $fx->_SetCache($_cacheid,$resp_cont, $CACHE_LISTA_PRODUTOS);
            }
        }     
                               
    }
      
                                                                            
    $resp = array_merge((array)$resp, (array)$resp_cont);                                                                           
    
        
    # Entrega Geograficamente Limitada
    $has_geo_limited_delivery = hasGeoLimitedDelivery();
    if( !empty($has_geo_limited_delivery) ){
        $shipping_express = cms_fetch_assoc( cms_query("SELECT GROUP_CONCAT(id) AS shipping_ids FROM `ec_shipping` WHERE `geo_limited`=1 AND `id` IN(".$_SESSION['_MARKET']['metodos_envio'].")") );
    }
    # Entrega Geograficamente Limitada
        
        
        
    # 2025-03-20    
    $RCC_prods_skus = array(); # a ser usado no rcctrackproducts.php        
    foreach($resp['products'] as $k => $v){
            
        if( count($_SESSION['EC_USER']['deposito_express']['depositos']) > 0 || (int)$has_geo_limited_delivery['id']>0 ){
            
            get_tags_express($resp['products'][$k]);
            
            # Entrega Geograficamente Limitada
            if( $shipping_express['shipping_ids'] != "" ){
            
                $prod = call_api_func("get_line_table_cache_api", "registos", "id='".$v['id']."'");
                if( trim($prod['generico30']) != "" && count( array_intersect( explode(",", $prod['generico30']), explode(",", $shipping_express['shipping_ids']) ) ) > 0 ){
                    $resp['products'][$k]['allow_add_cart'] = 0;
                }
                
            }
            # Entrega Geograficamente Limitada
                        
        }    
        
        if($v["unfolded_product_original"]!="0" && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $slocation) !== false && $_SERVER['HTTP_REFERER']!=$slocation.$_SERVER["REQUEST_URI"] && !isset($_SERVER['HTTP_CACHE_CONTROL'])){
            $sql_update = "INSERT INTO b2c_prods_ord_ctr (pag_id, pid, mercado, desdobrar, impressoes) VALUES('".$page_id."', '".$v["id"]."', '".$_SESSION['_MARKET']['id']."', '".$v["unfolded_product_original"]."', 1) ON DUPLICATE KEY UPDATE impressoes=impressoes+1";
            cms_query($sql_update);
        }
        
        
         $RCC_prods_skus[] = $v['sku_group']; 
    }


                              
    $resp['total_products_comparator'] = 0;
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0) {  
        $resp['total_products_comparator']  = call_api_func('get_total_comparator');
    }    
    
    
    


    global $B2B;
        
    if((int)$B2B>0 && (int)$_LISTAGEM_SEM_VARIANTES==0){
    
        $userOriginalID = $userID;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            $userOriginalID = $_SESSION['EC_USER']['id_original'];
        }
    
     

        $page = call_api_func('get_pagina', $page_id, "_trubricas");
        if($page["catalogo"]>0){
            $catalogo_id = $page["catalogo"];
            #$mercado=0;
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
            #$mercado  = 1;
        }      
    
        preparar_regras_carrinho($catalogo_id);
        
        
        $ids_depositos = $_SESSION['_MARKET']['deposito'];                        
        if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!=''){
            $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
        } 
        
        $priceList = $_SESSION['_MARKET']['lista_preco'];  
        if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
            $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
        } 
                
        
        $resp['id_catalog'] = $catalogo_id;
         
        # Regras para obter as cores     
        $JOIN = '';
        $JOIN_ARRAY = array();
        # 10-09-2019 - no detalhe exibimos todas as cores com base na regra do mercado, as regras da página da listagem são excluídas (PhoneHouse - pag exclusivos)
        $_query_regras = '';
        
        $_query_regras = build_regras(0, $JOIN_ARRAY, $catalogo_id);
                      
        
        if($mercado==1) $_query_regras .= build_regras_mercado($JOIN_ARRAY);
            
        $so_cores_com_stock = 0;                            
        if(isset($JOIN_ARRAY['STOCK'])){                
            unset($JOIN_ARRAY['STOCK']);
            $so_cores_com_stock = 1;
        }

            
        if(count($JOIN_ARRAY)>0){
            $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
        }    
                      
                
        foreach($resp['products'] as $k => $v){
                        
            $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);
            if ((int)$_SESSION['_MARKET']['depositos_condicionados_ativo'] == 1 && trim($_SESSION['_MARKET']['depositos_condicionados']) != '' && has_only_conditioned_stock($v, $ids_depositos, $_SESSION['_MARKET']['depositos_condicionados'])) {

                if (is_null($market_extra_info)) {
                    $market_extra_info = get_market_extra_info($_SESSION['_MARKET']["id"]);
                }

                $resp['products'][$k]['tags'][] = [
                    'title'      => $market_extra_info['dc_etiqueta_nome'],
                    'color'      => '#' . $market_extra_info['dc_etiqueta_cor_fundo'],
                    'color_text' => '#' . $market_extra_info['dc_etiqueta_cor_texto']
                ];
            }
            
            $resp['products'][$k]['matriz'] = $matriz;
            $resp['products'][$k]['warehouse_availability'] = []; #by default no warehouse will be shown
            
            #Layout vertical  
            if(count($v['variants']) == 1 && count($v['available_colors']) == 1 && (int)$v['uncataloged_stock'] == 0 ){
                
                $resp['products'][$k]['discount_levels']        = product_has_discount_levels($userOriginalID, $v['sku']);
                $resp['products'][$k]['warehouse_availability'] = check_warehouses_stock($ids_depositos, $v['sku'], $_SESSION['_MARKET']);
                
                if( $CONFIG_OPTIONS['layout_item_produto'] == 1 || $B2B_LAYOUT['b2b_style_version']==1 ){
                    $qtd = $eComm->getProductQtds($userID, $v['selected_variant']['id'], 0, 0, (int)$GLOBALS["REGRAS_CATALOGO"]);
                      
                    $prod = call_api_func("get_line_table","registos", "id='".$v['id']."'");
                    
                    if($prod['package_price_auto']==1 && (int)$prod['units_in_package']>1) {
                        $qtd /= $prod['units_in_package'];
                        
                        if($resp['products'][$k]['variants'][0]['inventory_quantity']>0 && $resp['products'][$k]['variants'][0]['inventory_quantity']!=99999)
                            $resp['products'][$k]['variants'][0]['inventory_quantity'] /= (int)$prod['units_in_package'];
                    }
                      
                    $resp['products'][$k]['variants'][0]['quantity_in_cart'] = (int)$qtd;
                }
                
            }
      
            #<Quntidade de catalogos onde o produto está adicionado>            
            $resp['products'][$k]['catalog_qty'] = 0;
            if((int)$CONFIG_OPTIONS['SHOW_CATALOG'] > 0){ 
                $catalogs = get_line_table("budgets", "user_id = '".$userID."' AND type = '2' AND status != '2'", "GROUP_CONCAT(id) as catalogs");
  
                if((int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'] == 1) {
                    $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product_sku_group`='" . $resp['products'][$k]['sku_group'] . "'", "COUNT(DISTINCT(`budget_id`)) AS `quantity`");                
                } else {
                    $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product`='" . $resp['products'][$k]['id'] . "'", "COUNT(`id`) AS `quantity`");
                }
                $resp['products'][$k]['catalog_qty'] = (int)$product_in_catalog['quantity'];
            }            
            #</Quntidade de catalogos onde o produto está adicionado>   
                       
        }               
    }

                   


    // Contexto
    $resp['grid_view']        =  $_SESSION['GridView'];     
    $resp['grid_view_mobile'] =  $_SESSION['GridViewMobile'];     

            

    # Wishlist
    if($resp['shop']['wishlist']>0 || $eComm->getNumberLinesWishlist($userID)>0 ){
    
        $refs_wishlist = call_api_func('get_refs_wishlist');
        $arr_sku_family_wishlist = explode(",",$refs_wishlist["sku_family"]);        
     
        foreach($resp['products'] as $k => $v){
            $resp['products'][$k]["wishlist"] = in_array($v['sku_family'], $arr_sku_family_wishlist)? 1:0; 
            
            #<Quntidade de listas onde o produto está adicionado>
            if( (int)$B2B>0 ){
                 $resp['products'][$k]['wishlist_groups_qty'] =  count($refs_wishlist['wishlist_groups'][$v['sku_family']]);
            }
            #</Quntidade de listas onde o produto está adicionado>              
        }
    }
      
      
    # Comparador                                    
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0) {
            foreach($resp['products'] as $k => $v){
                
                if($resp['total_products_comparator']>0){
                    $resp['products'][$k]['add_comparator']  = call_api_func('verify_product_add_comparator',$v['sku_family']);
                }
                
                if(count($resp['products'][$k]['composition'])>0){
                    $resp['products'][$k]['comparator'] = 1;
                }
                
            }  
        }
    
    
    # Reviews
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){
        $arr_sku_family = array();
        foreach($resp['products'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];
        }    
        $arr_review_product            = call_api_func('get_reviews_product_by_sku_familys', $arr_sku_family);
        foreach($resp['products'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $resp['products'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
    }
                                        

    /*if($offset>0){
        if(is_numeric($userID) ){
            // Serafim Costa
						//$sql = "insert into searched set termo='".$page_id."', tipo='1', total='1', id_cliente='".$userID."', lg='".$LG."', id_mercado='".$_SESSION['_MARKET']["id"]."', filtros='' on duplicate key update total=total+1, data=NOW() ";
            //cms_query($sql);
        }else{
           /* $_SESSION["termos_pesquisa"][$term] = array(
                                                     "termo"      => $page_id,
                                                     "tipo"       => "1",
                                                     "lg"         => $LG,
                                                     "id_mercado" => $_SESSION['_MARKET']["id"],
                                                     "filtros"    => ''
                                                 );    
        }
    }*/
    
    
    if(is_callable('custom_controller_product_list')) {
        call_user_func_array('custom_controller_product_list', array(&$resp));
    }
    
    
    
    
    

    # CollectAPI ***************************************************************************************************
    # 2025-10-22 - Este evento não é utilizado por nenhum Publisher
    /*if( $page_count==0 && $resp['shop']['show_cp_2'] == 1 && isset($collect_api) && !is_null($collect_api) ){
        
        $list_info = [ 'list_cat' => $resp['selected_page']['page_title'] ];
        
        $row_cat = call_api_func('get_pagina', $resp['selected_page']['cat'], "_trubricas");
        
        $list_info['list_name'] = ($row_cat['desc'.$LG] != '') ? $row_cat['desc'.$LG] : $row_cat['nome'.$LG];
                
        $req = explode('?', $_SERVER['REQUEST_URI']);
        $list_info['list_url'] = $slocation.$req[0];
        
        try{
            $collect_api->setEvent(CollectAPI::PRODUCT_LIST, $_SESSION['EC_USER'], $list_info);
        }catch(Exception $e){}
        
    }*/
    
    
    
    # 2025-03-20
    if($page_count==0 && $resp_cont['products_count']>0) @include(_ROOT.'/api/rcctrackproducts.php');
    
    if($page_count>1) @include(_ROOT.'/api/rccnextpage.php');        
    
    
    
    
    return serialize($resp);

}

?>
