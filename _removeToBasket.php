<?

function _removeToBasket($pid_product=null, $sku_family=null, $cor_id=0, $catalog_id=0, $b64_encode=0, $unit_store_id=0, $quick_buy=0){

    if(is_null($pid_product)){
        $pid_product   = params('pid_product');
        $sku_family    = params('sku_family');      
        $cor_id        = (int)params('cor_id');
        $catalog_id    = (int)params('catalog_id');
        $b64_encode    = (int)params('encoded');
        $unit_store_id = (int)params('unit_store_id');
        $quick_buy     = (int)params('quick_buy');  
    }   
     
    $pid = utf8_decode($pid_product);   
    
    
    
            
    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    # Aqui temos de limpar porque as quantidades foram mexidas
    unset($_SESSION['sys_qr_bsk']);
            
            
    
    # 11-04-2022 - Utilizado por causa das / nos SKUs da Playup
    if( $b64_encode == 1 && base64_encode( base64_decode($sku_family, true) ) === $sku_family ){
        $sku_family = base64_decode($sku_family);
    }    
    
    global $userID, $fx, $LG, $COUNTRY, $MOEDA, $eComm, $B2B, $session_id, $CONFIG_OPTIONS, $sslocation;

    $nowtime = time();

    if($quick_buy==0){
        $eComm->removeInsertedChecks($userID);
        #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
        #$eComm->cleanVauchers($userID);
        $eComm->clearTempCampanhas($userID);
    }
    
    if((int)$B2B == 0){
    
        # 2022-12-06
        # Comentado porque ao remover do mini carrinho, se tivessemmos mai que um poduto com servicos, iamos ao entrar no chekocut voltar a adicionar os serviços para os retantes produtos
    
        #limpar os serviços adicionados para voltar a recalcular 
        #$sql = "UPDATE `ec_encomendas_lines` SET servico_add='' WHERE id_cliente='$userID' AND status='0' AND id_linha_orig='0'";
        #cms_query($sql);
    }
            
    #$delete_line = "DELETE FROM ec_encomendas_lines WHERE status=0 AND id_cliente='".$userID."' AND id_linha_orig>0";
    #cms_query($delete_line);
    
    $more_cat_sql = '';
    if( $catalog_id > 0 && $B2B == 1 ){
    
        preparar_regras_carrinho($catalog_id);
        
        if((int)$GLOBALS["REGRAS_CATALOGO"]==0) preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);

        $more_cat_sql = ' AND `page_cat_id`='.(int)$GLOBALS["REGRAS_CATALOGO"];
    }
    
    if($unit_store_id>0 && stores_units_active_for_user()){
        $more_cat_sql .= ' AND `col1`="'.(int)$unit_store_id.'"';
    }

    if( (int)$B2B > 0 && (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 ){

        $query_res = cms_query("SELECT id FROM ec_encomendas_lines WHERE ref='".cms_escape($sku_family)."' AND id_cliente='$userID' AND status='0' AND order_id='0' LIMIT 1");
        if( cms_num_rows($query_res) > 0 ){
            $sku = $sku_family;
        }
        
    }
        
    if($cor_id>0){
    
        if( trim($sku) != "" ){
            $sel = "SELECT id FROM ec_encomendas_lines WHERE ref='".cms_escape($sku)."' AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0'".$more_cat_sql;
        }else{
            $sel = "SELECT id FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0'".$more_cat_sql;
        }
        
        $res = cms_query($sel); 
        while($row = cms_fetch_assoc($res)){
        
            if((int)$row["id"]==0) continue;
            
            if((int)$B2B>0){
                @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE id_cliente='$userID' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') AND status='0' ");                
            }else{
                @cms_query("DELETE FROM ec_encomendas_lines WHERE id_cliente='$userID' AND status='0' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') ");
            }            
        }
        
        if( trim($sku) != "" ){
            $pid_sql  = cms_query("SELECT pid FROM ec_encomendas_lines WHERE ref='".cms_escape($sku)."' AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0' "); 
        }else{
            $pid_sql  = cms_query("SELECT pid FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0' "); 
        }
        $prod     = cms_fetch_assoc($pid_sql);
        $pid = $prod['pid'];   
                                                                                                             
        if((int)$B2B>0){
            if( trim($sku) != "" ){
                @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE ref='".cms_escape($sku)."'  AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0'  ".$more_cat_sql);
            }else{
                @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE sku_family='".cms_escape($sku_family)."'  AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0'  ".$more_cat_sql);
            }
        }else{
            cms_query("DELETE FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND cor_id='$cor_id' AND id_cliente='$userID' AND status='0' AND order_id='0' ");        
        } 
               
    }elseif(trim($sku_family)!=""){
    
        $sel = "SELECT id FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND id_cliente='$userID' AND status='0' AND order_id='0'".$more_cat_sql;
        $res = cms_query($sel); 
        while($row = cms_fetch_assoc($res)){
        
            if((int)$row["id"]==0) continue;
            
            if((int)$B2B>0){
                @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE id_cliente='$userID' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') AND status='0' ");                
            }else{
                @cms_query("DELETE FROM ec_encomendas_lines WHERE id_cliente='$userID' AND status='0' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') ");
            }            
        }
        
        $pid_sql  = cms_query("SELECT pid FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND id_cliente='$userID' AND status='0' AND order_id='0' "); 
        $prod     = cms_fetch_assoc($pid_sql);
        $pid = $prod['pid'];   
                                                                                                             
        if((int)$B2B>0){
            @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE sku_family='".cms_escape($sku_family)."' AND id_cliente='$userID' AND status='0' AND order_id='0'  ".$more_cat_sql);
        }else{
            cms_query("DELETE FROM ec_encomendas_lines WHERE sku_family='".cms_escape($sku_family)."' AND id_cliente='$userID' AND status='0' AND order_id='0' ");        
        } 
               
    }elseif(trim($pid)!=""){
        $pack = 0;
        $sel = "SELECT id, pack FROM ec_encomendas_lines WHERE (pid='".cms_escape($pid)."' OR pid LIKE '%|||".cms_escape($pid)."') AND id_cliente='$userID' AND status='0'".$more_cat_sql;
        $res = cms_query($sel); 
        while($row = cms_fetch_assoc($res)){
        
            if((int)$row["id"]==0) continue;
            
            if((int)$row['pack'] == 1) $pack = 1;
            
            if((int)$B2B>0){
                @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE id_cliente='$userID' AND status='0' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') ");                
            }else{
                @cms_query("DELETE FROM ec_encomendas_lines WHERE id_cliente='$userID' AND status='0' AND (id_linha_orig='".$row["id"]."' OR id_linha_orig='-".$row["id"]."') ");
            }
        }      
        
        if((int)$B2B>0){
            @cms_query("UPDATE `ec_encomendas_lines` SET status='-100', order_id='-$nowtime' WHERE pid='".cms_escape($pid)."' AND id_cliente='$userID' AND status='0' AND order_id='0'  ".$more_cat_sql);        
        }else{
            cms_query("DELETE FROM ec_encomendas_lines WHERE (pid='".cms_escape($pid)."' OR pid LIKE '%|||".cms_escape($pid)."') AND id_cliente='$userID' AND status='0' AND order_id='0'");
        }
        
    }

    $arr_pid = explode("|||", $pid);
    $pid     = end($arr_pid);
    
        
    if($quick_buy == 0 && (int)$pack == 0){
    
        list($qtds) = cms_fetch_array(cms_query("SELECT ROW_COUNT() as DelRowCount"));
        
        
        $priceList = $_SESSION['_MARKET']['lista_preco'];
        
        $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
        
        global $collect_api;
        if (isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1) {        
            #<Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>
            include $_SERVER['DOCUMENT_ROOT'] . "/custom/shared/addons_info.php";
            $collectApiExtraClassifier = getCollectApiExtraClassifier();
            $campos                    = $collectApiExtraClassifier['campos'];
            $addJoinArr                = $collectApiExtraClassifier['addJoinArr'];
            $addFields                 = $collectApiExtraClassifier['addFields'];          
            $COLLECT_API_LANG          = $collectApiExtraClassifier['COLLECT_API_LANG'];   
            $num_campos_adicionais     = $collectApiExtraClassifier['num_campos_adicionais'];
            #</Classificadores adicionais - Selecionado no BO em Plugins -> Tracking Collect API>
        }                    
    
        $q = "SELECT registos.*,registos.nome$LG as name, registos_precos.preco,registos_cores.nome$LG as cor_name, registos_marcas.nome$LG as brand ,registos_familias.nome$LG as family, registos_categorias.nome$LG as category, registos_generos.nome$COLLECT_API_LANG as gender
                $addFields
              FROM registos
                INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
                LEFT JOIN registos_cores ON registos.cor = registos_cores.id
                LEFT JOIN registos_marcas ON registos.marca = registos_marcas.id
                LEFT JOIN registos_familias ON registos.familia = registos_familias.id
                LEFT JOIN registos_categorias ON registos.categoria = registos_categorias.id
                LEFT JOIN registos_generos ON registos.genero = registos_generos.id                
                ".implode("\n",$addJoinArr)."
              WHERE activo='1' 
                AND registos.id='$pid'
                AND registos_precos.idListaPreco='".$priceList."'
                AND registos_precos.preco>0
              GROUP BY registos.id                
              LIMIT 0,1";
              
        $sql    = cms_query($q);
        $prod   = cms_fetch_assoc($sql);
        
        $preco  = __getPrice($prod['sku'], 0, 0, $prod);
        
        $family = call_api_func('get_line_table', 'registos_familias', "id='".$prod["familia"]."'");
        $categoria = call_api_func('get_line_table', 'registos_categorias', "id='".$prod["categoria"]."'");

        if((int)$B2B==0){   
            $tag_manager = tracking_from_tag_manager('removeToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, 
                                                        "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'],
                                                        "ID_UTILIZADOR" => $session_id,  
                                                        "SKU_PRODUTO" => $prod['sku'], 
                                                        "ID_PRODUTO" => $prod['id'], 
                                                        "SKU_GROUP" => $prod['sku_group'], 
                                                        "SKU_FAMILY" => $prod['sku_family'], 
                                                        "FAMILIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $family['nomept']), 
                                                        "CATEGORIA_PRODUTO" => preg_replace("/[^a-zA-Z0-9 ]/", "", $categoria['nomept']), 
                                                        "URL_PRODUTO" => $sslocation."/?pid=".$prod['id'], 
                                                        "IMAGEM_URL_PRODUTO" => "", 
                                                        "VALOR_PRODUTO" => $preco['precophp'], 
                                                        "DESCRICAO_PRODUTO" => "" , 
                                                        "NOME_PRODUTO" => cms_real_escape_string($prod['nome'.$LG]), 
                                                        "MOEDA" => $MOEDA['abreviatura']  ));  
            
   

            global $collect_api;
            if (isset($collect_api) && !is_null($collect_api) && $show_cp_2 == 1) {
                
                $sizes = getTamanho($prod['tamanho']);
                $collect_api_prod = [];
                $collect_api_prod['sku_group']        = $prod['sku_group'];
                $collect_api_prod['pid']              = $pid;
                $collect_api_prod['nome']             = $prod['desc'.$LG];
                $collect_api_prod['sku_group']        = $prod['sku_group'];   
                $collect_api_prod['ref']              = $prod['sku'];                               
                $collect_api_prod['valoruni']         = $preco['precophp'];                
                $collect_api_prod['promotion']        = $preco['promo'];
                $collect_api_prod['tamanho']          = $sizes['nome'];                
                $collect_api_prod['family']['name']   = $prod['family'];
                $collect_api_prod['category']['name'] = $prod['category'];
                $collect_api_prod['brand']['name']    = $prod['brand'];
                $collect_api_prod['gender']['name']   = $prod['gender'];

                #<Classificadores adicionais>
                for ($i=1;$i<=$num_campos_adicionais ;$i++ ) {
                     $classificador = ${'ADDON_3010_CLS_ADIC_'.$i};   
                     if( empty($classificador) || $prod[$campos[$classificador]['alias']] == '' ){ continue;}
                     $collect_api_prod['extra_classifier']['extra_classifier_'.$i] = $prod[$campos[$classificador]['alias']];
                }   
                #</Classificadores adicionais>                

                $event_info = ['product' => $collect_api_prod, 'country' => $COUNTRY, 'currency' => $MOEDA];
                $cart_ungrouped = [];

                $y = array();
                $y['shop'] = OBJ_shop_mini();
        
                foreach($y['shop']['cart']['items'] as $line){
                    $cart_product = buildCartProductInfoForCollectAPI($line);
        
                    $line_ungrouped = array_fill(0, $line['quantity'], $cart_product); # copy the line "$line['quantity']" times (creates an array with as many lines as it's quantity)
                    $cart_ungrouped = array_merge($cart_ungrouped, $line_ungrouped);
        
                }
        
                $change_cart_info = ['cart' => ['items' => $cart_ungrouped], 'country' => $COUNTRY, 'currency' => $MOEDA];
        
                try {
                    $collect_api->setEvent(CollectAPI::PRODUCT_REMOVE, $_SESSION['EC_USER'], $event_info);
                    $collect_api->setEvent(CollectAPI::CART_CHANGE, $_SESSION['EC_USER'], $change_cart_info);
                } catch (Exception $e) {}
            }  
             
       }      
        
    }
    
    
    if(!isset($y['shop'])) $y['shop']['cart'] = call_api_func('OBJ_cart', false, $quick_buy); 
    
    $resp             = array();
    $resp['cart']     = $y['shop'];
    
    if((int)$B2B==0){
        $data = serialize($resp['cart']);
        $data = gzdeflate($data,  9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);
        
        $_SESSION["SHOPPINGCART"] = $data;
    }
    
    $resp['trackers'] = base64_encode($tag_manager);
    
    
    
    
    return serialize($resp);

}
?>
