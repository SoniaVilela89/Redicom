<?

function _getQuickBuy($page_id=0)
{

    global $INFO_NAV_PAG, $userID, $CONFIG_OPTIONS;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }
    
    $group_lines_by_family = 1;
    if( (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'] == 1 ){
        $group_lines_by_family = 0;
    }
    
    $arr['quickbuy_restricted_to_sku'] = (int)$CONFIG_OPTIONS['QUICK_BUY_RESTRICTED_TO_SKU'];
    
    $arr['quickbuy'] = "";
    
    if((int)$row["catalogo"] == 0) $row["catalogo"] = $_SESSION['_MARKET']['catalogo'];
        
    preparar_regras_carrinho($row["catalogo"]);

    if($row["catalogo"] != (int)$GLOBALS["REGRAS_CATALOGO"]) $row["catalogo"] = $_SESSION['_MARKET']['catalogo'];

    $arr['quickbuy'] = getLinesQuickBuy($row["catalogo"], $userID, $group_lines_by_family);
        
    $arr['quickbuy_resume'] = (int)$CONFIG_OPTIONS['resumo_compra_rapida'];    
        
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    
    $arr['expressions'] = call_api_func('get_expressions', $page_id);

    return serialize($arr);
    
}

  
function getLinesQuickBuy($catalogo, $userid, $agrupar_por_grupo=1){
    
    $arr_lines = array();
    
    $group_by = "`sku_family`";
    if( (int)$agrupar_por_grupo == 0 ){
        $group_by = "`ref`";
    }
            
                        
    $sql = "SELECT GROUP_CONCAT(`pid`) as `pids`,`nome`,`sku_family`,`pid`,`ref`, `image`, 
              SUM(`qnt`) as `qnt_total`,             
              SUM(`valoruni`*`qnt`) as `valor_final`, 
              AVG(`valoruni`) as `valoruni_avg`,         
              SUM(`valoruni_desconto`*`qnt`) as `valoruni_desconto_final`
            FROM `ec_encomendas_lines`
            WHERE `id_cliente`='".$userid."' AND `status`='0' AND `page_cat_id`='".$catalogo."' AND `id_linha_orig`<1
            GROUP BY $group_by 
            ORDER BY id DESC";
            
                              
    $res = cms_query($sql);    
    while($row = cms_fetch_assoc($res)){
    
        $arr_pids = explode(",", $row["pids"]);
        $arr_pids = array_unique($arr_pids);
        
        $key = $row["sku_family"];
        $prod_name = $row["sku_family"]." - ".$row["nome"];
        if( (int)$agrupar_por_grupo == 0 ){
            $key = $row["ref"];
            $prod_name = $row["ref"]." - ".$row["nome"];
        }

        $arr_lines[$key]["sku"]             = $key;
        $arr_lines[$key]["pid"]             = $row["pid"];
        $arr_lines[$key]["pids"]            = $arr_pids;
        $arr_lines[$key]["name"]            = $prod_name;
        $arr_lines[$key]["qnt"]             = $row["qnt_total"];
        $arr_lines[$key]["price"]           = $row["valoruni_avg"];
        $arr_lines[$key]["price_discount"]  = $row["valoruni_desconto_final"];
        $arr_lines[$key]["total_price"]     = $row["valor_final"];
        $arr_lines[$key]["image"]           = $row["image"];
        
    }

    return $arr_lines;   

}

?>
