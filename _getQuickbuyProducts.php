<?php

#$search_type =1 é usado na pequisa das peças nas avarias
function _getQuickbuyProducts($int_page_id=null, $term=null, $search_type=0, $term_base64=0){

    global $CACHE_LISTA_PRODUTOS, $fx, $LG, $CACHE_KEY, $CONFIG_OPTIONS, $userID;

    if ($int_page_id > 0){
         $page_id       = (int)$int_page_id;
         $search_type   = (int)$search_type;
    }else{
         $page_id       = (int)params('page_id');
         $term          = params('term');
         $search_type   = (int)params('search_type');
         $term_base64   = (int)params('term_base64');
    }
    
    
    if(!is_numeric($userID)){
        return serialize( array("0" => "-1"));    
    }
    
    if( $term_base64 ){
        $term = base64_decode($term);
    }
        
    $_GET['term'] = $term;

    if( (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 && $search_type != 1 ){
        $search_type = 1;
    }

    $scope = array();
    $scope['ID']          = $page_id;  
    $scope['PAIS']        = $_SESSION['_COUNTRY']['id'];
    $scope['LG']          = $_SESSION['LG'];
    $scope['TERM']        = $term;
    $scope['STYPE']       = $search_type;

    $_cacheid             = $CACHE_KEY."QB".md5(serialize($scope));
         
    if($CACHE_LISTA_PRODUTOS>0) {
        $resp = $fx->_GetCache($_cacheid, $CACHE_LISTA_PRODUTOS);
    }

    if (!$resp || $_GET["nocache"]==1){
                                                              
            $arr_query_prod = prepare_query_products($page_id, 0);
                     
            if( (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 ){
                $arr_query_prod['_query_regras_final'] = "AND `registos`.`sku` LIKE '".cms_escape($term)."%' ";
            }

            $priceList    = $_SESSION['_MARKET']['lista_preco'];    
            
            if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
                $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
            }
        
            // $page                       = $arr_query_prod["page"];
            $JOIN                       = $arr_query_prod["JOIN"];
            $_query_regras              = $arr_query_prod["_query_regras_final"];
            // $order_by                   = $arr_query_prod["order_by"];
            // $LISTAGEM_DESAGRUPAR_CORES  = $arr_query_prod["LISTAGEM_DESAGRUPAR_CORES"];
            
            #SCM-2331/2021 - Comentado dia 21/09/2021 esta a demorar o processamento do QuickBuy, penso que nao seja necessario                    
            #$temp = return_products_list($pageID, $JOIN, $_query_regras_final, $priceList, $order_by);
     
            $group_by = "sku_family";
            if( $search_type == 1 ){
                $group_by = "sku";
            }
            
            //                    ORDER BY sku_family

            $q = "SELECT registos.sku_family, registos.id, registos.id, nome$LG, registos.sku, registos.sku_group, registos.cor, registos.tamanho, registos.units_in_package, registos.package_price_auto, registos.sales_unit, registos.package_type
                    FROM registos
                        $JOIN
                        INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()    
                    WHERE activo='1' $_query_regras
                      AND nome$LG<>''
                      AND registos_precos.idListaPreco='".$priceList."'
                      AND registos_precos.preco>0
                    GROUP BY $group_by
                    LIMIT 0,10";
                    
            $sql = cms_query($q);
 
            while($v = cms_fetch_assoc($sql)){

                $units_in_package   = 1;
                $label              = $v['nome'.$LG];
                $label_default      = $v['nome'.$LG];
                $packaging          = array("name" => "", "label" => "");

                if((int)$v['units_in_package'] > 1) {

                    $units_in_package = (int)$v['units_in_package'];

                    $unidvenda = cms_fetch_assoc( cms_query("SELECT nome$LG as nome FROM `registos_unidades_venda` WHERE `id`='".$v['sales_unit']."'") );
                    $embalagem = cms_fetch_assoc( cms_query("SELECT nome$LG as nome FROM `registos_embalagens` WHERE `id`='".$v['package_type']."'") );

                    if((int)$v['package_price_auto'] == 1) {
                        if($unidvenda['nome'] != '') {
                            $label = $label." (".$units_in_package." ".$unidvenda['nome'].")";
                        }
                        
                        if($embalagem['nome'] != '') {
                            $packaging['name'] = $embalagem['nome'];
                        }
                    }

                    if((int)$v['package_price_auto'] == 3) {
                        $unidvenda = cms_fetch_assoc(cms_query("SELECT nome$LG as nome FROM `registos_unidades_venda` WHERE `id`='".$v['sales_unit']."'"));
                        $label = $label_default." (".$embalagem['nome']." ".(int)$v['units_in_package']." ".$unidvenda['nome'].")";
                    }

                }

                if( $search_type == 1 ){
                    $resp[] = array(
                        "pid" => $v['id'],
                        "label" => $v['sku'].' - '.$label,
                        "label_default" => $v['sku'].' - '.$label_default,
                        "units_in_package" => $units_in_package,
                        "packaging" => $packaging,
                        "package_price_auto" => $v['package_price_auto']
                    );
                }else{
                    $resp[] = array(
                        "pid" => $v['id'],
                        "label" => $v['sku_family'].' - '.$label,
                        "label_default" => $v['sku_family'].' - '.$label_default,
                        "label_packaging" => $label_packaging,
                        "units_in_package" => $units_in_package,
                        "packaging" => $packaging,
                        "package_price_auto" => $v['package_price_auto']
                    );
                }

            }

            if($CACHE_LISTA_PRODUTOS>0) $fx->_SetCache($_cacheid, $resp, $CACHE_LISTA_PRODUTOS);

    }

    return serialize($resp);

}

?>
