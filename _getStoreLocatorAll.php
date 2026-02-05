<?php

function _getStoreLocatorAll($prod_id = 0){
    
    global $COUNTRY, $LG;
    
    if ($prod_id == 0){
        $prod_id     = (int)params('prod_id');
    }

    $prod   = call_api_func("get_line_table","registos", "id='".$prod_id."'");

    $arr_prods = array();
    $sql_prods = "SELECT r.id, r.sku, rt.nome$LG as tamanho, rt.ordem FROM registos r
                    INNER JOIN registos_tamanhos rt ON r.tamanho=rt.id
                WHERE r.sku_family='".cms_escape($prod['sku_family'])."' AND r.cor='".$prod['cor']."' AND r.activo='1'";
                
    $res_prods = cms_query($sql_prods);
    
    while($row_prods = cms_fetch_assoc($res_prods)){

        $ordem = $row_prods["ordem"];
        if($row_prods["ordem"] == "") $ordem = $row_prods["tamanho"];

        $arr_prods[] = array(
            "id"            => $row_prods["id"],
            "sku"           => trim($row_prods["sku"]),
            "size"          => $row_prods["tamanho"],
            "order"         => $ordem,
            "stock"         => 0
        );
    }

    aasort($arr_prods, "order");
    $arr_prods = array_values($arr_prods);

    $arr_stores = array();    
    $sql_stores = "SELECT ec_d.id as deposit_id, ec_l.id as store_id, ec_l.nomept as name, ec_l.morada as address, 
                        ec_l.morada as address, ec_l.cidade as city, ec_l.cp as postal_code, ec_l.tel as phone, 
                        ec_l.email as email, ec_l.coordenadas as coordinates, CONCAT(ec_l.horario, ' ', ec_l.horariopt) as schedule
                FROM ec_lojas ec_l 
                INNER JOIN ec_deposito ec_d ON ec_l.id=ec_d.loja
            WHERE ec_l.pais='".$COUNTRY["id"]."' AND ec_l.hidden=0 
            ORDER BY ec_l.ordem,ec_l.nomept";
            
    $res_stores = cms_query($sql_stores);
    
    while($row_store = cms_fetch_assoc($res_stores)){
        
        $arr_prod_final = $arr_prods;
                
        $sql_stock = "SELECT *
                FROM registos_stocks
                WHERE iddeposito='".$row_store["deposit_id"]."' AND sku IN ('".implode("','", array_column($arr_prods, 'sku'))."')";
                
        $res_stock  = cms_query($sql_stock);
        while($row_stock = cms_fetch_assoc($res_stock)){
        
            $key = array_search(trim($row_stock["sku"]), array_column($arr_prods, 'sku'));
            
            $arr_prod_final[$key]["stock"] = $row_stock["stock"];
            $arr_prod_final[$key]["venda_negativo"] = $row_stock["venda_negativo"];
        }
        
        $row_store["products"] = $arr_prod_final;
        $arr_stores[] = $row_store;
    }

    $arr = array(
        "stores" => $arr_stores
    );
    
    
    # 2025-11-11
    # Ferramentas Pro
    if(is_callable('custom_controller_store_locator_all')) {
        call_user_func_array('custom_controller_store_locator_all', array(&$arr));
    }
    

    return serialize($arr);

}

function aasort (&$array, $key) {
    $sorter = array();
    $ret = array();
    reset($array);
    foreach ($array as $ii => $va) {
        $sorter[$ii] = $va[$key];
    }
    asort($sorter, SORT_NATURAL);
    foreach ($sorter as $ii => $va) {
        $ret[$ii] = $array[$ii];
    }
    $array = $ret;
}
?>
