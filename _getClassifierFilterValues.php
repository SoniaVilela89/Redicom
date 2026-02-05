<?

function _getClassifierFilterValues($page_id=0,$filter=''){
    global $LG,$GLOBALS;
    
    if( $page_id == 0 ){
        $page_id = trim(params('page_id'));
    }    
    if( $filter == '' ){
        $filter = trim(params('filter'));
    }
    
    if( (int)$page_id <= 0 || $filter == "" ){
        return serialize(["success" => 0, "values" => array()]);
    }
    
    $classifier  = getClassifier($page_id,$filter);  
 
    if( $classifier['field'] == "" ){
        return serialize(["success" => 0, "values" => array()]);
    }
    
    $addsql = $classifier['est_nav_rule'];
    
    if( isset( $_GET ) && !empty( $_GET ) ){
        foreach ($_GET as $key=>$value) {
              $get_classifier  = getClassifier($page_id,$key);  
              //$addsql_arr[] = "$get_classifier[field] = '$value'";
              $addsql_arr[] = "FIND_IN_SET('$value', {$get_classifier['field']}) > 0";
        }
        if( count($addsql_arr)>0 ){
            $addsql .= " AND ".implode(" AND ",$addsql_arr);
        }
    }

    $priceList = $_SESSION['_MARKET']['lista_preco'];    
    if((int)$GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0){
        $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];      
    }

    $filters_sql = "SELECT DISTINCT $classifier[filter].id,$classifier[filter].nome".$LG." as name 
                    FROM registos $classifier[addJoin] ".$classifier['joinCatalog']['JOIN']."   
                    INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE() AND registos_precos.idListaPreco='".$priceList."'                                 
                    WHERE registos.activo='1' AND registos_precos.preco>0 AND registos.nome$LG!='' AND 
                    $classifier[field] IS NOT NULL AND $classifier[field]>0 AND $classifier[field]!='' AND $classifier[filter].nome".$LG." != '' 
                    $addsql ".$classifier['joinCatalog']['query_regras']. "ORDER BY $classifier[filter].ordem,name";

    $filters_res = cms_query($filters_sql);

    $filter_values = [];
    while( $filter_value = cms_fetch_assoc($filters_res) ){
        $filter_values[] = $filter_value;
    }    

    if(is_callable('custom_controller_get_classifier_filter_values')) {
        call_user_func_array('custom_controller_get_classifier_filter_values', array(&$filter_values, $filter));
    }

    return serialize(['success' => true, 'values' => $filter_values]);

}


function getClassifier($page_id=0,$filter){
    
    global $LG;

    #Array que relaciona as tabelas dos classificadores com os campos da tabela regitos 
    $campos   = get_classifier_fields(); #api_functions_products.php

    #Procura a tabela do classificador à custa do filtro
    foreach ($campos as $key=>$value) {
    	 if( $value['field'] == $filter ){
          $classificador = $key;
          break;
       }
    }
    
    $row_classificador = cms_fetch_assoc(cms_query("SELECT catalogo,regras FROM _trubricas WHERE est_nav=1 AND id='".$page_id."'"));
    
    #Regras da estrutura de navegação
    $est_nav_rule = get_rules($row_classificador['regras']);

    #Regras do catalogo  
    $joinCatalog = __getSkusByCatalogo($row_classificador['catalogo'],1,6);  

    $joinCatalog = str_replace("AND nome$LG","AND registos.nome$LG",$joinCatalog);  #Para não ser ambiguo no query
    $joinCatalog = str_replace("OR nome$LG","OR registos.nome$LG",$joinCatalog);    #Para não ser ambiguo no query
    
    #Regra do filtro aplicado 
    $addJoin     = "LEFT JOIN ".$classificador." ON registos.".$campos[$classificador]['field']." = ".$classificador.".id";
    $addField    = $classificador.".nome".$LG." as `".$campos[$classificador]['alias']."`";
  
    #Array de retorno
    $return['filter']       = $classificador;
    $return['field']        = $campos[$classificador]['field'];
    $return['alias']        = $campos[$classificador]['alias'];    
    $return['addJoin']      = $addJoin;  
    $return['addField']     = $addField;    
    $return['joinCatalog']  = $joinCatalog;
    $return['est_nav_rule'] = $est_nav_rule;

    return $return;
    
}


?>
