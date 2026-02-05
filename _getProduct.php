<?

#Update date: 28/07/2016

function _getProduct($int_page_id=null, $category=null, $product_id=null, $page_count=null, $is_page_detail=null, $with_meta=0, $without_stock=0, $catalog_id=0){

    global $LG, $userID, $fx, $eComm, $CONFIG_RELACIONADOS_QTD, $CONFIG_COMBINADOS_QTD, $CONFIG_TEMPLATES_PARAMS, $SEE_ALSO_RANDOM,   
          $CACHE_DETALHE, $CONFIG_COMPLEMENTARES_QTD, $CONFIG_COMPOSTOS_QTD,  $CACHE_KEY, $B2B, $B2B_LAYOUT_VERTICAL, $cat, $pid,
        $DISPLAY_ADVANCED_CONFIGURATOR, $CONFIG_OPTIONS, $detect, $EXIBIR_TODOS_AS_CORES, $collect_api, $SITE_CHANNEL;

    
    if ($int_page_id > 0){
       $page_id         = (int)$int_page_id;
       $cat             = (int)$category;
       $product_id      = (int)$product_id;
       $page_count      = (int)$page_count;
       $is_page_detail  = (int)$is_page_detail;
       $with_meta       = (int)$with_meta; 
       $without_stock   = (int)$without_stock;
       $catalog_id      = (int)$catalog_id;      
    }else{
       $page_id         = (int)params('page_id');
       $cat             = (int)params('cat');
       $product_id      = (int)params('product_id');
       $page_count      = (int)params('page_count');
       $is_page_detail  = (int)params('is_page_detail');
       $with_meta       = (int)params('with_meta');  
       $without_stock   = (int)params('without_stock');
       $catalog_id      = (int)params('catalog_id');      
    }
    
    if( (int)$catalog_id > 0 ) $_GET['ctg'] = (int)$catalog_id;

    
    if(is_callable('custom_controller_product_initial')) {
        call_user_func('custom_controller_product_initial');
    }

    
    $PROMOS = getAvailablePromoForUser();    
    
    
    $resp = array();
    
    $scope = array();
    $scope['lista_preco']     = $_SESSION['_MARKET']['lista_preco'];
    $scope['deposito']        = $_SESSION['_MARKET']['deposito'];
    $scope['PAIS']            = $_SESSION['_COUNTRY']['id'];
    $scope['LG']              = $_SESSION['LG'];
    $scope['page_id']         = $page_id;
    $scope['cat']             = $cat;
    $scope['is_page_detail']  = $is_page_detail;    
    $scope['PROMO']           = implode(',', $PROMOS["promos"]);
    $scope['SEG']             = $_SESSION["segmentos"]; 
     
    if(is_array($_SESSION['EC_USER']['margem']) && count($_SESSION['EC_USER']['margem'])>0){
        $scope['MARGEM']  = md5(base64_encode(serialize($_SESSION['EC_USER']['margem'])));
    }
    
    if((int)$_SESSION['EC_USER']['descontos_exclusivos']>0){
        $scope['DES_EXC'] = $_SESSION['EC_USER']['id']."|||".$_SESSION['EC_USER']['descontos_exclusivos'];
    }
    
    if((int)$_SESSION['EC_USER']['b2b_markup']>0){
        $scope['MARKUP'] = $_SESSION['EC_USER']['b2b_markup'];
    }

    if( $B2B == 1 && isset($_GET['ctg']) && (int)$_GET['ctg'] > 0 ){
        $scope['id_catalog'] = (int)$_GET['ctg'];
    }
    
    
    $_PDcacheid = $CACHE_KEY."PD_".$product_id.'_'.md5(serialize($scope));
              
    if($CACHE_DETALHE>0) $dados = $fx->_GetCache($_PDcacheid, $CACHE_DETALHE);
                                    
                                                               
    if ($dados!=false && !isset($_GET['nocache']))
    {
        $resp = unserialize($dados);  
                         
    } else {
    
                            
        # 2025-02-06
        # Por causa das excepçoes definidas no BO    
        if($page_id==36){
            $row = call_api_func('get_pagina_modulos', $page_id, "_trubricas");     
        }else{
             $row = call_api_func('get_pagina', $page_id, "_trubricas");    
        } 
        
        
        if($row["catalogo"]>0){
            $catalogo_id = $row["catalogo"];
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }
        
        if( $B2B == 1 && isset($_GET['ctg']) && (int)$_GET['ctg'] > 0 ){
            $catalogo_id = (int)$_GET['ctg'];
        }
        
        # Raffles
        if((int)$cat > 0) $row_cat = call_api_func('get_pagina', $cat, "_trubricas");
        
        $resp['raffle'] = 0;
        
        $prod            = call_api_func("get_line_table","registos", "id='".$product_id."'");

        if( ( (int)$row['sublevel'] == 75 || (int)$row_cat['sublevel'] == 75 ) ){
            # Forçar depósito do sorteio
            global $raffle;
            $raffle = get_raffle("", $_SESSION['_COUNTRY']['id']);
        }



        # 2025-09-02
        # Salsa - em APP ignoramos regra de stock para deixar entrar sempre no detalhe do produto
        if($SITE_CHANNEL==4) $without_stock = 1;

        $resp['product'] = call_api_func('get_product', $product_id, '', $page_id, $is_page_detail, 0, 0, $without_stock);
        
        if( ( (int)$row['sublevel'] == 75 || (int)$row_cat['sublevel'] == 75 ) ){
            $resp['raffle'] = 1;
            $resp['raffle_status'] = 1;

            $raffle = get_raffle($resp['product']['sku_group'], $_SESSION['_COUNTRY']['id']);
            
            $resp['raffle_line_id'] = $raffle['raffle_line_id'];
            
            if( (int)$raffle['deposito'] <= 0 ){
                $resp['raffle_status'] = 2;
            }
        }
         
        # 2022-03-31
        # Produtos que já não estão ativos ou sempreço ou sem stock não vale a pensa fazer o resto das querys
        if( (int)$resp['product']['id'] < 1 && !$_GET['preview'] ) return serialize($resp);
        
        
                
        $resp['id_catalog'] = $catalogo_id;


        # Na cloud é o campo registos_genericos_22 e registos_genericos_23 e está a ser enviado directamente no varant_obj
        /*foreach($resp["product"]['dimension'] as $k=>$v ){
        
            if(trim($v['dimension'])=='') continue;
            
            $sql_dm = cms_fetch_assoc(cms_query("SELECT nome$LG as nome FROM registos_genericos_28 WHERE codigo='".cms_escape($v['dimension'])."' LIMIT 0,1"));     
            if (trim($sql_dm['nome'])!=''){
                $resp["product"]['dimension'][$k]['dimension'] = $sql_dm['nome'];
            }        
        }
        
        
        #Trocar o nome à variante de dimensão
        if(trim($resp["product"]["selected_variant"]['dimension'])!=''){       
            $sql_dm = cms_fetch_assoc(cms_query("SELECT nome$LG as nome FROM registos_genericos_28 WHERE codigo='".cms_escape($resp["product"]["selected_variant"]['dimension'])."' LIMIT 0,1"));     
            if (trim($sql_dm['nome'])!=''){
                $resp["product"]["selected_variant"]['dimension'] = $sql_dm['nome'];
            }    
        }*/
        
        
        #Quando Sales obter o stock de cada loja
        if(trim($resp["product"]["selected_variant"]['inventory_quantity'])>0){
            if(strpos($_SERVER['SERVER_NAME'], 'sales') !== false) {       
                $sql = "SELECT SUM(rs.stock-rs.margem_seguranca) as stock, l.nomept,l.id, d.nome
                          FROM registos_stocks rs
                            INNER JOIN ec_deposito d ON d.id=rs.iddeposito 
                            LEFT JOIN ec_lojas l ON l.id=d.loja 
                          WHERE sku='".$resp["product"]["selected_variant"]['sku']."' 
                                AND iddeposito IN (".$_SESSION['_MARKET']['deposito'].")
                          GROUP BY iddeposito
                          ORDER BY l.nomept ";
                              
               $q = cms_query($sql); 
               while($r = cms_fetch_assoc($q)){
                  ($r['id']==$_COOKIE['sales_id']) ? $sel = 'sel' : $sel = '';
                  $resp["product"]["selected_variant"]['all_inventory'][] = array("deposit_name" => ($r["nomept"]!="") ? $r["nomept"] : $r["nome"], "stock" => $r['stock'], "selected" => $sel);     
               } 
           }         
        }
        
                
        #Produtos relacionados
        $see_also = array();
        if( $prod['see_also']!="" && (int)$SEE_ALSO_RANDOM!=5 && (int)$SEE_ALSO_RANDOM!=6 ){
            
            $prod['see_also'] = ltrim($prod['see_also'], ',');
            
            $refs = explode(",", $prod['see_also']);
            $sql  = cms_query("SELECT id FROM registos WHERE activo='1' AND nome$LG!='' AND (sku in ('".implode("','",$refs)."') OR sku_group in ('".implode("','",$refs)."') ) GROUP BY sku_group LIMIT 0,$CONFIG_RELACIONADOS_QTD");
            while($v = cms_fetch_assoc($sql)){
            
                if( $B2B > 0 && (int)$CONFIG_OPTIONS['prod_rel_cat_atual'] == 1 && $row['catalogo']>0){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $row['catalogo']);
                }else{
                    $temp = call_api_func('get_product',$v['id']);
                }
                
                if( $B2B > 0 && $temp['id'] == 0 ){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $catalogo_id);
                }

                if($temp['id']>0) $see_also[] = $temp;
            }
        }
    
        #Produtos relacionados random
        if( empty($see_also) && ((int)$B2B==0 || $slocation=='https://b2b.tiffosi.com' || $slocation=='https://b2b.vilanova.com') && (int)$SEE_ALSO_RANDOM!=9 ){ 
            
            $priceList       = $_SESSION['_MARKET']['lista_preco'];
           
            $JOIN           = '';
            $JOIN_ARRAY     = array();
            
          
            $_query_regras = '';  
            $_query_regras .= build_regras_mercado($JOIN_ARRAY);
                               
            /*if((int)$B2B>0){
                $_query_regras .= build_regras(0, $JOIN_ARRAY, $catalogo_id);
            }*/
            
            $_query_regras  .= build_regras($page_id, $JOIN_ARRAY);
            

            if((int)$SEE_ALSO_RANDOM==0 || (int)$SEE_ALSO_RANDOM==5){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND familia='".$prod['familia']."' AND subfamilia='".$prod['subfamilia']."' AND genero='".$prod['genero']."' AND semestre='".$prod['semestre']."'";
            }elseif($SEE_ALSO_RANDOM==1){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND genero='".$prod['genero']."' AND semestre='".$prod['semestre']."'";
            }elseif($SEE_ALSO_RANDOM==2){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND familia='".$prod['familia']."'";
            }elseif($SEE_ALSO_RANDOM==3){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND categoria='".$prod['categoria']."' AND genero='".$prod['genero']."' AND marca='".$prod['marca']."' ";
            }elseif($SEE_ALSO_RANDOM==4){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND familia='".$prod['familia']."' AND gama='".$prod['gama']."' ";
            }elseif($SEE_ALSO_RANDOM==6){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND familia='".$prod['familia']."' AND categoria='".$prod['categoria']."' AND genero='".$prod['genero']."' AND marca='".$prod['marca']."' AND semestre='".$prod['semestre']."'";
            }elseif($SEE_ALSO_RANDOM==7){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND categoria='".$prod['categoria']."' ";
            }elseif($SEE_ALSO_RANDOM==8){
                $SEE_ALSO_RANDOM =  "AND sku_family!='".$prod['sku_family']."' AND familia='".$prod['familia']."' AND subfamilia='".$prod['subfamilia']."' AND categoria='".$prod['categoria']."' AND ano='".$prod['ano']."' AND genero='".$prod['genero']."' AND gama='".$prod['gama']."'";
            } 

    
            if(count($JOIN_ARRAY)>0){
                $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
            }    
           
       
            # 2020-04-24 - removido indice - dava tempos melhores sem ele
            # USE INDEX (SEE_ALSO_3)
            $sql = @cms_query("SELECT registos.*,registos_precos.preco
                    FROM registos 
                        $JOIN
                        INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
                    WHERE activo='1' AND nome$LG<>''
                        $_query_regras 
                        $SEE_ALSO_RANDOM                      
                        AND registos_precos.idListaPreco='".$priceList."'
                        AND nome$LG!=''
                        AND registos_precos.preco>0
                    GROUP BY sku_family
                    ORDER BY data_novidade DESC, id DESC 
                    LIMIT 0,$CONFIG_RELACIONADOS_QTD");
    
    
            while($v = cms_fetch_assoc($sql)){
                
                $v['id_original'] = $v['id'];
                
                $see_also[] = call_api_func('productOBJ', $v, "", "", 5, 0);
            }
        }
        
                       
        # Combina com
        $combine = array();
        if( $prod['combine_with']!="" ){       
                
            $prod['combine_with'] = ltrim($prod['combine_with'], ',');
            $refs = explode(",", $prod['combine_with']); 
                        
            $sql  = cms_query("SELECT id 
                                FROM registos 
                                WHERE activo='1' 
                                        AND nome$LG!='' 
                                        AND (sku in ('".implode("','",$refs)."') OR sku_group in ('".implode("','",$refs)."') ) 
                                GROUP BY sku_group 
                                LIMIT 0,$CONFIG_COMBINADOS_QTD");
                                                                                   
            while($v = cms_fetch_assoc($sql)){
            
                if( $B2B > 0 && (int)$CONFIG_OPTIONS['prod_rel_cat_atual'] == 1 && $row['catalogo']>0){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $row['catalogo']);
                }else{
                    $temp = call_api_func('get_product',$v['id']);
                } 
                
                if( $B2B > 0 && $temp['id'] == 0 ){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $catalogo_id);
                }
                
                if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
                    $temp['product_configurator'] = check_product_advanced_configurator($temp['sku_group']);
                }
                  
                if($temp['id']>0) $combine[] = $temp;
            }
        }   
                
        
        # Produtos complementares
        $complement = array();
        if( $prod['complement_with']!="" ){       
                
            $prod['complement_with'] = ltrim($prod['complement_with'], ',');
            $refs = explode(",", $prod['complement_with']); 
                        
            $sql  = cms_query("SELECT id 
                                FROM registos 
                                WHERE activo='1' 
                                        AND nome$LG!='' 
                                        AND (sku in ('".implode("','",$refs)."') OR sku_group in ('".implode("','",$refs)."') ) 
                                GROUP BY sku_group 
                                LIMIT 0,$CONFIG_COMPLEMENTARES_QTD");
                                                                
            while($v = cms_fetch_assoc($sql)){
                $temp = call_api_func('get_product',$v['id']);

                if( $B2B > 0 && $temp['id'] == 0 ){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $catalogo_id);
                }

                if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
                    $temp['product_configurator'] = check_product_advanced_configurator($temp['sku_group']);
                }
                
                if($temp['id']>0) $complement[] = $temp;
            }
        } 
        
        
        # Composto por
        $composed = array();
        if( $prod['composto']!="" ){     
                
            $prod['composto'] = ltrim($prod['composto'], ',');
            $refs = explode(",", $prod['composto']);
                        
            $sql  = cms_query("SELECT id 
                                FROM registos 
                                WHERE activo='1' 
                                        AND nome$LG!='' 
                                        AND (sku in ('".implode("','",$refs)."') OR sku_group in ('".implode("','",$refs)."') ) 
                                GROUP BY sku_group 
                                LIMIT 0,$CONFIG_COMPOSTOS_QTD");
                                                                                                
            while($v = cms_fetch_assoc($sql)){
                $temp = call_api_func('get_product',$v['id']);

                if( $B2B > 0 && $temp['id'] == 0 ){
                    $temp = call_api_func('get_product', $v['id'], '', $page_id, $is_page_detail, 0, $catalogo_id);
                }
                
                if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
                    $temp['product_configurator'] = check_product_advanced_configurator($temp['sku_group']);
                }

                if($temp['id']>0) $composed[] = $temp;
            }
        }
    
    
        
        $resp['selected_page']  = call_api_func('OBJ_page', $row, $page_id, $cat);
    
        # Breadcrumbs
        if($page_id>0){
    
            $caminho  = call_api_func('get_breadcrumb', $page_id);
            $bc       = array();
    
            foreach( $caminho as $k => $v ){
            
                $link = $v['link'];
                
                if($v['id_pag']!=1 && $v["id_pag"]!=5) $link = "index.php?id=".$v['id_pag'];
                
                if($v["id_pag"]!=36 && $v["id_pag"]!=41 && $v["id_pag"]!=1 && $v["id_pag"]!=5) {
                  
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
                    "sublevel"  => (int)$v["sublevel"],
                    "without_click" => $v['without_click']
                );
            }
    
            $bc[] = array(
                "name" => $resp['product']['title'],
                "link" => "javascript:void(0);",
                "sublevel"  => "0",
                "without_click" => 1
            );
            $resp['selected_page']['breadcrumb'] = $bc;
        }
    
        $resp['navigation_pages'] = call_api_func('get_menu', 2, $page_id, $cat);
        $resp['related_products'] = $see_also;
        $resp['combine_with']     = $combine;
        $resp['complement_with']  = $complement;
        $resp['composed_by']      = $composed;
        $resp['banners']          = call_api_func('bannerOBJ',$prod['banner']);
        $resp['expressions']      = call_api_func('get_expressions',5);

        if((int)$CONFIG_OPTIONS['review_tamanho'] == 1){
            $resp['expressions_review'] = get_expressions_functionality('1,2,3');
        }
        
        $resp['show_klarna_placement']  = 0;
        $resp['klarna_layout']  = "";
        $resp['klarna_client']  = "";
        $arr_Klarna = array_intersect(array(30,100,103,104,105), explode(",", $_SESSION['_MARKET']['metodos_pagamento']));
        if(count($arr_Klarna) > 0){
        
            $sql_pag = "SELECT MAX(id) as ids, MAX(colocacao_detalhe) as colocacao_detalhe, colocacao_layout, colocacao_client_id, MIN(valor_min_enc) as valor_min_enc, MAX(valor_max_enc) as valor_max_enc FROM ec_pagamentos WHERE id in ('".implode("','", $arr_Klarna)."') ";
            $res_pag = cms_query($sql_pag);
            $row_pag = cms_fetch_assoc($res_pag);
                 
            if((int)$row_pag["colocacao_detalhe"] == 1){
                $resp['show_klarna_placement']  = 1;
                if($row_pag['ids']>30) $resp['show_klarna_placement']  = 2; 
                $resp['klarna_layout']  = ($row_pag["colocacao_layout"] == 0 ? "credit-promotion-badge" : "credit-promotion-auto-size");
                $resp['klarna_client']  = $row_pag["colocacao_client_id"];
                
                if( $row_pag['valor_max_enc']>0 && ( $resp["product"]['price_min']['value'] <= $row_pag['valor_min_enc'] || $resp["product"]['price_min']['value'] > $row_pag['valor_max_enc'] ) ){
                    $resp['show_klarna_placement']  = 0;
                }                  
                
            }
        }

        $resp['show_sequra_placement']  = 0;
        $resp['sequra_merchant']  = "";
        $resp['sequra_assets_key']  = "";
        $resp['sequra_script_uri']  = "";
        $arr_sequra = array_intersect(array(128), explode(",", $_SESSION['_MARKET']['metodos_pagamento']));
        if(count($arr_sequra) > 0){
        
            $sql_pag = "SELECT MAX(id) as ids, MAX(colocacao_detalhe) as colocacao_detalhe, unicre_debug, unicre_merchant_id, colocacao_client_id FROM ec_pagamentos WHERE id in ('".implode("','", $arr_sequra)."') ";
            $res_pag = cms_query($sql_pag);
            $row_pag = cms_fetch_assoc($res_pag);
                 
            if((int)$row_pag["colocacao_detalhe"] == 1){
                
                $accounts_sql = cms_query("SELECT id, paypal_user from ec_pagamentos_contas WHERE ec_pagamentos_id='128' and pais_id='".$_SESSION['_COUNTRY']["id"]."'");
                $accounts     = cms_fetch_assoc($accounts_sql);
                if((int)$accounts["id"]>0 && trim($accounts["paypal_user"]) != '' ){
                    $row_pag["unicre_merchant_id"] = $accounts["paypal_user"];
                }     

                $resp['show_sequra_placement']  = 1;
                $resp['sequra_merchant']  = $row_pag["unicre_merchant_id"];
                $resp['sequra_assets_key']  = $row_pag["colocacao_client_id"];
                $resp['sequra_script_uri']  = ($row_pag["unicre_debug"] == 1 ? "https://sandbox.sequracdn.com/assets/sequra-checkout.min.js" : "https://live.sequracdn.com/assets/sequra-checkout.min.js");

            }
        }

        $resp['scalapay_merchant']  = "";
        $resp['scalapay_debug']  = 0;
        $resp['show_scalapay_placement']  = 0;
        $arr_scalapay = array_intersect(array(131), explode(",", $_SESSION['_MARKET']['metodos_pagamento']));
        if(count($arr_scalapay) > 0){
            
            $sql_pag = "SELECT MAX(id) as ids, MAX(colocacao_detalhe) as colocacao_detalhe, unicre_debug, unicre_contract_number FROM ec_pagamentos WHERE id in ('".implode("','", $arr_scalapay)."') ";
            $res_pag = cms_query($sql_pag);
            $row_pag = cms_fetch_assoc($res_pag);
            if((int)$row_pag["colocacao_detalhe"] == 1 && trim($row_pag["unicre_contract_number"]) != ''){
                $resp['scalapay_debug']             = $row_pag["unicre_debug"];
                $resp['show_scalapay_placement']    = 1; 
                $resp['scalapay_merchant']          = $row_pag["unicre_contract_number"];
            }
             
        }
        
        $resp['show_card_points']  = 0;
        if((int)$_SESSION['_MARKET']["pontos_cartao_valido"] == 1 && (int)$_SESSION['EC_USER']['f_cartao'] == 0 && (int)$_SESSION['EC_USER']['estado_cartao'] == 0) $resp['show_card_points']  = 1;
        
        $resp['more_info_points'] = (int)$_SESSION['_MARKET']["pontos_info_cartao_pag"];
        
        if( (int)$DISPLAY_ADVANCED_CONFIGURATOR > 0 ){
            $resp["product"]['advanced_configurator'] = get_product_advanced_configurator($resp['product']['sku_group'], 1);
            $resp["product"]['advanced_configurator_options'] = array();
            get_options_template($x,'product');

            if((int) $B2B == 1) $x['response']['shop']['TEMPLATES_PARAMS']['productAdvancedConfiguratorVersion'] = 2;

            if((int)$x['response']['shop']['TEMPLATES_PARAMS']['productAdvancedConfiguratorVersion'] == 2){
                $resp["product"]['advanced_configurator_options'] = get_product_advanced_configurator($resp['product']['sku']);
            }
            
        }
        
        /*Para collect API*/
        $n_campos_genericos = 20;
        $prod_fields = Array('subfamilia','subcategoria','ano','semestre','generico21','gama');
        foreach ($prod_fields as $key=>$value) {
            $resp['prod'][$value] = $prod[$value];
        }
        for ($i=1;$i<=$n_campos_genericos;$i++ ) {
            $resp['prod']["generico".$i] = $prod["generico".$i];            
        } 
        /*Para collect API*/
                                                                         
        $CACHE_DETALHE = getTimeCache($CACHE_DETALHE, 'product');
          
              # 23/07/2021 - Serafim Costa - Não pode fazer cache de 404                                                                                                       
              # if($CACHE_DETALHE>0) $fx->_SetCache($_PDcacheid,serialize($resp), $CACHE_DETALHE);
        if($CACHE_DETALHE>0 && (int)$resp['product']['id']>0) $fx->_SetCache($_PDcacheid,serialize($resp), $CACHE_DETALHE);
        
    }
    
    
    
    
    
    $resp['shop'] = call_api_func('OBJ_shop_mini');
     
     
     
        
    if((int)$B2B>0){
        
        $resp['product']['catalog_qty'] = 0;
        if((int)$CONFIG_OPTIONS['SHOW_CATALOG'] > 0){
            $catalogs = get_line_table("budgets", "user_id = '".$userID."' AND type = '2' AND status != '2'", "GROUP_CONCAT(id) as catalogs");

            if((int)$CONFIG_OPTIONS['B2B_CATALOGOS_PRODUTOS_TIPO'] == 1) {
                $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product_sku_group`='" . $resp['product']['sku_group'] . "'", "COUNT(DISTINCT(`budget_id`)) AS `quantity`");                
            } else {
                $product_in_catalog = get_line_table("budgets_lines", "`budget_id` IN (" . $catalogs['catalogs'] . ") AND `product`='" . $resp['product']['id'] . "'", "COUNT(`id`) AS `quantity`");
            }
            $resp['product']['catalog_qty'] = (int)$product_in_catalog['quantity'];

        }

        $resp['user_units_stores'] = get_user_stores_units_from_session($_SESSION['EC_USER']['id'], $catalogo_id, $resp['product']['sku_group']);
        
        $userOriginalID = $userID;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            $userOriginalID = $_SESSION['EC_USER']['id_original'];
        }
        
        $resp['product']['size_guide_all'] = array();
        if((int)$resp['product']['size_guide_id']>0){

            include_once($_SERVER['DOCUMENT_ROOT']."/api/controllers/_getSizeGuide.php");

            $size_guide_all = _getSizeGuide($resp['product']['size_guide_id'], 0, 1);
            $resp['product']['size_guide_all'] = unserialize($size_guide_all);

        }

        
               
        global $page;
        
        
        # 2025-02-06
        # Por causa das excepçoes definidas no BO    
        if($page_id==36){
            $page = call_api_func('get_pagina_modulos', $page_id, "_trubricas");     
        }else{
            $page = call_api_func('get_pagina', $page_id, "_trubricas");    
        }
        
        
        $mercado  = 1;
        if($page["catalogo"]>0){
            $catalogo_id = $page["catalogo"];
            $mercado=0;
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo']; 
        }
        
        if( $B2B == 1 && isset($_GET['ctg']) && (int)$_GET['ctg'] > 0 ){
            $catalogo_id = (int)$_GET['ctg'];
            $mercado=0;
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
        
         

        # Regras para obter as cores     
        $JOIN = '';
        $JOIN_ARRAY = array();
        $_query_regras = '';
        
         
        if((int)$EXIBIR_TODOS_AS_CORES==0){
            $_query_regras .= build_regras(0, $JOIN_ARRAY, $catalogo_id);
        }else{
            $_query_regras .= build_regras(0, $JOIN_ARRAY, $catalogo_id, 1);
        } 
        
        
        
        # 10-09-2019 - no detalhe exibimos todas as cores com base na regra do mercado, as regras da página da listagem são excluídas (PhoneHouse - pag exclusivos)
        #$_query_regras = build_regras($page_id, $JOIN_ARRAY, 0);
       
        if($mercado==1) {
            if((int)$EXIBIR_TODOS_AS_CORES==0){
                $_query_regras .= build_regras_mercado($JOIN_ARRAY);
            }else{
                $_query_regras .= build_regras_mercado($JOIN_ARRAY, 1);
            }
        }
        
       
        $so_cores_com_stock = 0;                            
        if(isset($JOIN_ARRAY['STOCK'])){                
            unset($JOIN_ARRAY['STOCK']);
            $so_cores_com_stock = 1;
        }
            
        if(count($JOIN_ARRAY)>0){
            $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
        }    
                      
        $matriz = get_product_matriz($resp['product'], $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock, 1);
        
        if( (int)$_SESSION['_MARKET']['depositos_condicionados_ativo'] == 1 && trim($_SESSION['_MARKET']['depositos_condicionados']) != '' && has_only_conditioned_stock($resp['product'], $ids_depositos, $_SESSION['_MARKET']['depositos_condicionados']) ){
        
            if( is_null($market_extra_info) ){
                $market_extra_info = get_market_extra_info($_SESSION['_MARKET']["id"]);
            }
        
            $resp['product']['tags'][] = ['title'      => $market_extra_info['dc_etiqueta_nome'],
                                          'color'      => '#'.$market_extra_info['dc_etiqueta_cor_fundo'],
                                          'color_text' => '#'.$market_extra_info['dc_etiqueta_cor_texto']
                                          ];
        }

        $resp['product']["matriz"] = $matriz;      
   
        if( (int)$GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 0 ){
            
            foreach($resp['product']['variants'] as $key=>$value) {
                
                if( $value['price']['value'] == 0 ){
                    $resp['product']['variants'][$key]['inventory_quantity'] = 0;
                    $resp['product']['variants'][$key]['inventory_rule']     = 0;
                    $resp['product']['variants'][$key]['inventory_class']    = "inputInventoryGray";
                }
                    
            }
            
        }
        
        
        
        $resp['product']['layout_grid'] = 2; # horizontal - grelha
               
        if(count($resp['product']["matriz"]['packs'])>0 || $resp['product']['price_min']!=$resp['product']['price_max'] || count($resp['product']["matriz"]['colunas'])>10){
            $resp['product']['layout_grid'] = 1;    # vertical
        }
                            
        if($CONFIG_OPTIONS['grelha_layout_horizontal'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1){
            $resp['product']['layout_grid'] = 2;    # horizontal - grelha
        }
                      
        if((int)$B2B_LAYOUT_VERTICAL==1){
            $resp['product']['layout_grid'] = 1;    # vertical
        }
        
        
        # Se produto unico (uma cor e um tamanho) ou pelos menos um produto é de cotação, então é o layout simples
        if($resp['shop']['b2b_style_version'] == 1 && ( (count($resp['product']["matriz"]['linhas']) == 1 && count($resp['product']["matriz"]['colunas']) == 1) || ($GLOBALS["REQUEST_QUOTE"] == 1 && $matriz['request_quote'] == 1) ) ){        
            $resp['product']['layout_grid'] = 0;       
        }
        
        if(count($resp["product"]['advanced_configurator'])>0 || count($resp['product']['dimension'])>0){
            $resp['product']['layout_grid'] = 3; 
        }
        
        $resp['product']['warehouse_availability'] = []; #by default no warehouse will be shown
        if( count($matriz['colunas']) == 1 && count($matriz['linhas']) == 1 && (int)$resp['product']['uncataloged_stock'] == 0 ){
            $resp['product']['warehouse_availability'] = check_warehouses_stock($ids_depositos, $resp['product']['sku'], $_SESSION['_MARKET']);
        }
        
        $mobile = false;
        if(file_exists('api/lib/class.mobile_detect.php') && !is_null($detect)){
                
            #Sonia - 11/10 - definido por Serafim que em tablet os espaçamentos são os de mobile
            if($detect->isMobile() || $detect->isTablet()){    
                $mobile = true;
            }

        }else{
            if( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'],'Android') ){
                $mobile = true;
            }
        }
        
        $resp['product']['added_to_cart'] = 0;
        $resp['product']['selected_variant']['discount_levels'] = 0;
        $unit_stores_active_for_user = stores_units_active_for_user();
        $user_default_unit_store_id  = 0;

        if($unit_stores_active_for_user){
            $user_default_unit_store_info = get_user_default_unit_store_info();
            if(!empty($user_default_unit_store_info)){
                $user_default_unit_store_id = $user_default_unit_store_info['id'];
                unset($user_default_unit_store_info);
            }
        }
        
        if(!isset($prod)) $prod = call_api_func("get_line_table","registos", "id='".$product_id."'");
            
        $last_used_store = end($_SESSION['EC_USER']['last_used_stores_units']);
        $user_store_temp = ($last_used_store > 0) ? $last_used_store : $user_default_unit_store_id;
        
        # Obter quantidades adicionadas
        if( $mobile || $resp['product']['layout_grid'] == 0 || $resp['product']['layout_grid'] == 1 || $resp['product']['layout_grid'] == 3 ){

            $resp['user_units_stores'] = get_user_stores_units_from_session($_SESSION['EC_USER']['id'], $catalogo_id, '', $resp['product']['sku_group']);

            foreach($resp['product']['variants'] as &$vva){
                loop_variants_b2b($resp, $vva);  
                    }
                    
            foreach($resp['product']['dimension'] as &$vva){                
                loop_variants_b2b($resp, $vva);  
            }

            foreach($resp['product']['dimension_secondary'] as &$vva){                
                loop_variants_b2b($resp, $vva);  
              }

        }else{

            $resp['user_units_stores'] = get_user_stores_units_from_session($_SESSION['EC_USER']['id'], $catalogo_id, $resp['product']['sku_family'], '');
           
            foreach($resp['product']["matriz"]["artigos_info"] as &$vcores){
            
                foreach($vcores as &$vtam){

                    $qtd = 0;
                    $matrix_catalog_id = 0;
                    $vtam['info']['added_to_cart'] = 0;
                     
                    if((int)$vtam['info']['package_price_auto']==1 && (int)$vtam['info']['units_in_package']>1 && $vtam['info']['inventory_quantity']>0 && $vtam['info']['inventory_quantity']!=99999){
                        $vtam['info']['inventory_quantity'] /= (int)$vtam['info']['units_in_package'];
                    }
                                                         
                    if($vtam['info']['inventory_quantity']>0 || $vtam['info']['inventory_rule']>0){
                        
                        if( isset( $GLOBALS["REGRAS_CATALOGO"] ) ){ # Significa que existem regras de catálogo e na BD o "page_cat_id" está potencialmente diferente de 0
                            $matrix_catalog_id = $catalogo_id;
                        }
                    
                        $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, $matrix_catalog_id);
                    }
                    
                    if($qtd > 0){
                        $resp['product']['added_to_cart'] = 1;
                        $vtam['info']['added_to_cart'] = 1;
                    }

                    if($unit_stores_active_for_user && $qtd>0){
                        #Replace total quantity by the quantity added for the default unit store in case then last used store is not defined - $user_store_temp
                        $qtd = $eComm->getProductQtds($userID, $vtam['info']['id'], 0, 0, $matrix_catalog_id, $user_store_temp);
                    }
                    
                    if($vtam['info']['package_price_auto']==1 && (int)$vtam['info']['units_in_package']>1) $qtd /= (int)$vtam['info']['units_in_package'];
                    
                    $vtam['info']['quantity_in_cart'] = (int)$qtd;
                    $vtam['info']['discount_levels'] = product_has_discount_levels($userOriginalID, $vtam['info']['sku']);
                    if((int)$vtam['info']['discount_levels'] == 1){

                        if((int)$qtd>0){
                            require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_getProductDiscountLevelPrice.php';
                            $price_matrix = _getProductDiscountLevelPrice((int)$qtd, $vtam['info']['sku'], $matrix_catalog_id);
                            $price_matrix = unserialize($price_matrix);
                            
                            $vtam['info'] = $price_matrix + $vtam['info'];
                            if(count($matriz['colunas']) == 1){ #If product "Simple" (with Qty box next to the add to cart button) then we need to update the initial product price according to the corresponding level
                                $resp['product'] = $price_matrix + $resp['product'];
                            }
                        }

                        $resp['product']['selected_variant']['discount_levels'] = 1;
                    }

                    if( (int)$CONFIG_OPTIONS['exibir_referencias_substitutas'] == 1 && count($matriz['packs']) == 0 && count($matriz['colunas']) == 1 ){
                        
                        # PRODUTOS SUBSTITUTOS
                        $product_eq = cms_query("SELECT `registos`.`nome".$LG."` as `nome`, `registos`.`id`, `registos`.`sku` FROM `registos_referencias`
                                                    JOIN `registos` ON `registos`.`sku`=`registos_referencias`.`sku` AND `registos`.`nome".$LG."`!=''
                                                    WHERE `registos_referencias`.`sku_orig`='".$vtam['info']['sku']."' AND `registos_referencias`.`sku`!='' AND `registos_referencias`.`tipo`='S'
                                                    LIMIT 0,5");
                        
                        while( $product_row = cms_fetch_assoc( $product_eq ) ){
                            
                            $resp['produtos_substitutos'][ $vtam['info']['sku'] ][] = [ 'id'   => $product_row['id'],
                                                                                        'sku'  => $product_row['sku'],
                                                                                        'nome' => $product_row['nome'],
                                                                                        'url'  => "/index.php?pid=".$product_row['id']."&id=5" ];
                            
                        }
                 
                    }
                                                
                }   
            
            }

        }
 
        foreach($resp['related_products'] as $k => $v){
                        
            $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);
            
            $resp['related_products'][$k]['matriz'] = $matriz;
      
        }
        
        foreach($resp['combine_with'] as $k => $v){
                        
            $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);
            
            $resp['combine_with'][$k]['matriz'] = $matriz;
      
        }

        foreach($resp['complement_with'] as $k => $v){
                        
            $matriz = get_product_matriz($v, $ids_depositos, $priceList, $JOIN, $_query_regras, $so_cores_com_stock);
            
            $resp['complement_with'][$k]['matriz'] = $matriz;
      
        }
        
        
        
        # B2B - Produtos equivalentes
        $products_to_search[$resp['product']['sku']] = $resp['product']['sku'];
        if( (int)$CONFIG_OPTIONS['exibir_referencias_equivalentes'] == 1 && !empty( $products_to_search ) ){
            $resp['equivalent_products'] = get_equivalent_products($products_to_search, $row["catalogo"]);
        }
               
    }
            
       
   
            
    if($B2B==0) {
        $resp['shop']['last_viewed'] = call_api_func('get_last_viewed');
        
        $lojas = call_api_func('getLojas', 1);
        
        $resp['shop']['stores_country'] = $lojas['lojas_pais'];
        $resp['shop']['stores_country_count'] = count($lojas['lojas_pais']);
    }
    
                 
    $resp['product']['wishlist'] = 0;

    if($resp['shop']['wishlist']>0){
    
        $refs_wishlist = call_api_func('get_refs_wishlist');
    
        if(trim($refs_wishlist["sku_family"])!="" || trim($refs_wishlist["refs"])!=""){
        
            $arr_sku_family_wishlist = explode(",",$refs_wishlist["sku_family"]);
                   
            $resp['product']['wishlist'] = in_array($resp['product']['sku_family'], $arr_sku_family_wishlist)? 1:0;
            
            if( (int)$B2B == 1 ){

                $resp['product']['wishlist_groups_qty'] = count($refs_wishlist['wishlist_groups'][$resp['product']['sku_family']]);

            }else{

                $arr_skus_wishlist = explode(",",$refs_wishlist["refs"]); 
                
                foreach($resp['product']['variants'] as $k => $v){
                    $resp['product']['variants'][$k]["wishlist"] = in_array($v['sku'], $arr_skus_wishlist)? 1:0;
                }         
                
                foreach($resp['product']['dimension'] as $k => $v){
                    $resp['product']['dimension'][$k]["wishlist"] = in_array($v['sku'], $arr_skus_wishlist)? 1:0;
                }       
            
                foreach($resp['related_products'] as $k => $v){
                    $resp['related_products'][$k]["wishlist"] = in_array($v['sku_family'], $arr_sku_family_wishlist)? 1:0;
                    
                    foreach($v['variants'] as $k2 => $v2){
                        $resp['related_products'][$k]['variants'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                    foreach($v['dimension'] as $k2 => $v2){
                        $resp['related_products'][$k]['dimension'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                }
                
                foreach($resp['combine_with'] as $k => $v){
                    $resp['combine_with'][$k]["wishlist"] = in_array($v['sku_family'], $arr_sku_family_wishlist)? 1:0;
                    
                    foreach($v['variants'] as $k2 => $v2){
                        $resp['combine_with'][$k]['variants'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                    foreach($v['dimension'] as $k2 => $v2){
                        $resp['combine_with'][$k]['dimension'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                }
                
                foreach($resp['complement_with'] as $k => $v){
                    $resp['complement_with'][$k]["wishlist"] = in_array($v['sku_family'], $arr_sku_family_wishlist)? 1:0;
                    
                    foreach($v['variants'] as $k2 => $v2){
                        $resp['complement_with'][$k]['variants'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                    foreach($v['dimension'] as $k2 => $v2){
                        $resp['complement_with'][$k]['dimension'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                }

                foreach($resp['shop']['last_viewed'] as $k => $v){
                    $resp['shop']['last_viewed'][$k]["wishlist"] = in_array($v['sku_family'], $arr_sku_family_wishlist)? 1:0;
                    
                    foreach($v['variants'] as $k2 => $v2){
                        $resp['shop']['last_viewed'][$k]['variants'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                    foreach($v['dimension'] as $k2 => $v2){
                        $resp['shop']['last_viewed'][$k]['dimension'][$k2]["wishlist"] = in_array($v2['sku'], $arr_skus_wishlist)? 1:0;
                    }
                    
                }                

            }
        
        }
        
    }    

    $resp['total_products_comparator'] = 0;
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0) {  
        $resp['total_products_comparator']  = call_api_func('get_total_comparator');
    }
        
            
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']>0){  
        
        if($resp['total_products_comparator']>0) {
            $resp['product']['add_comparator']  = call_api_func('verify_product_add_comparator',$resp['product']['sku_family']);
        }
        
        if(count($resp['product']['composition'])>0){
            $resp['product']['comparator'] = 1;
        }
    }
    
    # Reviews
    if($CONFIG_TEMPLATES_PARAMS['detail_allow_review']==1){

        $resp['product']['review_product']  = call_api_func('get_reviews_product', $resp['product']['sku_family'], $resp['product']['selected_variant']['color']['color_id']);
        
        $arr_sku_family = array();
        foreach($resp['related_products'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];
        }
        
        foreach($resp['combine_with'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];  
        }
        
        foreach($resp['complement_with'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];  
        }

        foreach($resp['shop']['last_viewed'] as $k => $v){
            $arr_sku_family[$v['sku_family']] = $v['sku_family'];  
        }        
        
        foreach($resp['product']['pack'] as $k => $v){
            foreach($v["products"] as $k1 => $v2){       
                $arr_sku_family[$v2['sku_family']] = $v2['sku_family'];                               
            }  
        }

        $arr_review_product = call_api_func('get_reviews_product_by_sku_familys', $arr_sku_family);
        
        foreach($resp['related_products'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $resp['related_products'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
        
        foreach($resp['combine_with'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $resp['combine_with'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
        
        foreach($resp['complement_with'] as $k => $v){
            if(isset($arr_review_product[$v["sku_family"]])) $resp['complement_with'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
        
        foreach($resp['product']['pack'] as $k => $v){
            foreach($v["products"] as $k1 => $v2){       
                if(isset($arr_review_product[$v2["sku_family"]])) $resp['product']['pack'][$k]["products"][$k1]['review_product'] = $arr_review_product[$v2["sku_family"]];                             
                else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
            }  
        }   
        
        foreach($resp['shop']['last_viewed'] as $k => $v){
           
            if(isset($arr_review_product[$v["sku_family"]])) $resp['shop']['last_viewed'][$k]['review_product'] = $arr_review_product[$v["sku_family"]];
            else $arr_review_product[$v["sku_family"]] = array('review_level'=>0, 'review_counts'=>0);
        }
        
    }
                
                
    if( count($_SESSION['EC_USER']['deposito_express']['depositos']) > 0 || !empty( hasGeoLimitedDelivery() ) ){
        get_tags_express($resp['product']);              
    }
    
        
    if($resp['product']['id']>0){
        if(!$_GET['preview']){
            set_status(1,$resp['product'], '',$page_id,$page_count);
            set_clientes_status($resp['product']);#segmentos de cliente
        }
    }
    
               
    if((int)$with_meta>0){
        $pid = $product_id;
        $resp['metadata'] = call_api_func('OBJ_metadata', $row, "_trubricas");
        
        $request_uri = $_SERVER["REQUEST_URI"];
        global $slocation_relative;
        $_SERVER["REQUEST_URI"] = str_replace($slocation_relative, "", $resp['product']["url"]);
        $ROW_PRODUTO = $resp['product'];
        
        $resp['tag_manager_body'] = call_api_func('getTrackingTagManager',"Body");
        $_SERVER["REQUEST_URI"] = $request_uri;
    }
    
    
    
    # Shipping Express
    if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){

        if( (int)$resp['selected_page']['navigation'] == 1 && empty($cat_pag) ){
            $cat_pag = call_api_func('get_pagina', $cat, "_trubricas");
        }

        if( (int)$resp['selected_page']['sublevel'] == 74 || (int)$cat_pag['sublevel'] == 74 ){    
            global $SHIPPING_EXPRESS_COLOR, $SHIPPING_EXPRESS_B_COLOR;
            $resp['shipping_express']['zip_code']   = base64_decode($_COOKIE['SYS_EXP_ZIP']);
            $resp['shipping_express']['theme']      = ['background_color' => $SHIPPING_EXPRESS_B_COLOR, 'title_color' => $SHIPPING_EXPRESS_COLOR];    
        }
    
    }


    # Raffles
    if(  (int)$resp['raffle_status'] == 1 && is_numeric($userID) ){

        $already_participated = cms_num_rows( cms_query("SELECT `id`
                                                            FROM `sorteios_linhas_encomendas` 
                                                            WHERE `sorteios_linhas_id`='".$resp['raffle_line_id']."'
                                                                AND `utilizador_id`='".$userID."'
                                                                AND `sku`='".$resp['product']['sku']."'
                                                            LIMIT 1") );
    
        if( (int)$already_participated > 0 ){
            $resp['raffle_status'] = 2;
        }
    }

    

    if(is_callable('custom_controller_product')) {
        call_user_func_array('custom_controller_product', array(&$resp));
    }
    
    
    
    # Conversão do Facebook por API ***************************************************************************************************
    if ((int)$CONFIG_TEMPLATES_PARAMS['facebook_pixel_send_all_events'] == 1 && trim($CONFIG_TEMPLATES_PARAMS['facebook_pixel_access_token']) != '' && $resp['shop']['show_cp_2'] == 1) {
    
        $event_id           = md5($resp['product']['id'] . session_id() . time());
        
        $product_info_event = ['sku_group' => $resp['product']['sku_group'], 
                                'family' => $resp['product']['family'], 
                                'brand' => $resp['product']['brand'], 
                                'price' => $resp['product']['price']];
                                
        $capi_user_info     = get_capi_user_info();
        
        $event_info         = ['event_time' => time(), 'event_id' => $event_id, 'event' => 'ViewContentProduct', 'user_info' => $capi_user_info, 'custom_info' => $product_info_event];
    
        $resp['capi']['viewcontent']['event_id'] = $event_id;
    
        setFacebookEventOnRedis("CAPI_EVENT_".$event_id, $event_info);
    }
    
    
    # CollectAPI *********************************************************************************************************************
    if( $resp['shop']['show_cp_2'] == 1 && isset($collect_api) && !is_null($collect_api) ){
        
        if(!isset($prod)) $prod = $resp['prod'];
        
        $arr['promotion'] = ($preco['promo']==1) ? 1: 0;
        
        $product_info = [ 'sku_group'       => $resp['product']['sku_group'], 
                          'product_type'    => 'Product',
                          'price'           => $resp['product']['price_min']['value_original'], 
                          'family'          => ['name' => $resp['product']['family']],
                          'category'        => ['name' => $resp['product']['category']],
                          'brand'           => ['name' => $resp['product']['brand']['name']],
                          'country'         => $_SESSION['_COUNTRY']['country_code'], 
                          'currency'        => $_SESSION['_MOEDA']['abreviatura'], 
                          'sku'             => $resp['product']['sku'],
                          'title'           => $resp['product']['description'],
                          'promotion'       => $resp['product'][line_price][promo]
                        ];

        #<Classificadores adicionais>
        include $_SERVER['DOCUMENT_ROOT'] . "/custom/shared/addons_info.php";
        $collectApiExtraClassifier = getCollectApiExtraClassifier();
        $campos                    = $collectApiExtraClassifier['campos'];
        $addJoinArr                = $collectApiExtraClassifier['addJoinArr'];
        $addFields                 = $collectApiExtraClassifier['addFields'];
        $COLLECT_API_LANG          = $collectApiExtraClassifier['COLLECT_API_LANG'];   
        $num_campos_adicionais     = $collectApiExtraClassifier['num_campos_adicionais'];   
    
        $classificador_gender = call_api_func('get_line_table', 'registos_generos', "id='".$resp['product']['gender_id']."'");
        $product_info['gender']['name'] = $classificador_gender['nome'.$COLLECT_API_LANG];    
    
        for ($i=1;$i<=$num_campos_adicionais ;$i++ ) {
             $classificador = ${'ADDON_3010_CLS_ADIC_'.$i};
             if( empty($classificador) ){ continue;}
             if($prod[$campos[$classificador]['field']]>0 ){
                $classificador_adicional = call_api_func("get_line_table", $classificador, "id=".$prod[$campos[$classificador]['field']]);
                $product_info['extra_classifier']['extra_classifier_'.$i] = $classificador_adicional['nome'.$COLLECT_API_LANG] != '' ? $classificador_adicional['nome'.$COLLECT_API_LANG] : $classificador_adicional['nomept'];
             } 
        }     
        #</Classificadores adicionais>  
        try {
            $collect_api->setEvent(CollectAPI::PRODUCT_VIEW, $_SESSION['EC_USER'], $product_info);
        } catch (Exception $e) {}
        
    }     
    
    
    
    # 2024-05-20
    # Acrescentado aqui para ser usado na APP
    # Campanha MA de recomendação de produtos em site - 9200
    $camp_r = verifyRecommendationCampaign(50);
    
    $resp['recommendation_campaign_active'] = 0; 
    if((int)$camp_r['automation']==50 || (int)$_GET['preview-ma']==9200) {
        $resp['recommendation_campaign_active'] = 1;
    }
    
    
    # Campanha MA de recomendação de produtos em site - 9250
    $camp_r = verifyRecommendationCampaign(55);
    
    $resp['detail_recommendation_campaign_active'] = 0; 
    if((int)$camp_r['automation']==55 || (int)$_GET['preview-ma']==9250) {
        $resp['detail_recommendation_campaign_active'] = 1;
    }
    
    
    # 2025-02-21
    # Como na APP não se faz um getHeader é preciso esta função aqui para saberem se está o ativo ou não
    if($SITE_CHANNEL==4){
        $pid = $product_id;
        $arr_click_collect = get_click_collect(0);        
        $resp['click_collect'] = $arr_click_collect['click_collect'];
    }
    
        
    
    # 2025-03-20
    @include(_ROOT.'/api/rcctrackproducts.php');
      
     
         
    return serialize($resp);

}

?>
