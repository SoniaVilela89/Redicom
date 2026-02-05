<?

function _getProductsPack($pack_id=null, $sku_group=null, $position=1)
{

    
    if(is_null($pack_id)){
        $pack_id = params('pack_id');
        $sku_group = params('sku_group');
        $position = params('position');
    }
    
    $q = "SELECT * 
            FROM registos_pack 
            WHERE id='".$pack_id."' 
            LIMIT 0,1";
            
    $res_pack = cms_query($q);
    $row = cms_fetch_assoc($res_pack);
    if((int)$row["type"] == 4 && $position > 1){
        $tmp = array();
        $catalogo_id = $row["artigo$position"];
        
        $order_by = "";
        if($row["artigo".$position."sel"] != "") $order_by = " ORDER BY (sku_group = '".$row["artigo".$position."sel"]."') DESC ";

        $sql_catalogo = "SELECT sku_group FROM prefetch_rcc_produtos_catalogo WHERE FIND_IN_SET($catalogo_id, catalogos) $order_by";
        $res_catalogo = cms_query($sql_catalogo);
        while ($row_catalogo = cms_fetch_assoc($res_catalogo)) {
            $tmp[] = $row_catalogo["sku_group"];
        }
    }else{
        $row["artigo$position"] = ltrim($row["artigo$position"], ',');
        $tmp = explode(",", $row["artigo$position"]);
    }


   
    $arr_prod = array();
    $arr_prod_temp = array();
   
    $sql_registo = "SELECT id, sku_group FROM registos WHERE sku_group IN ('".implode("','", $tmp)."') GROUP BY sku_group ORDER BY FIELD(sku_group, '".implode("','", $tmp)."')"; 
    if((int)$row["type"] == 4 && $position == 1){
        $sql_registo = "SELECT id, sku_group FROM registos WHERE sku_group IN ('".implode("','", $tmp)."') GROUP BY sku_group ORDER BY FIELD(sku_group, '".implode("','", $tmp)."')";     
    }
    
    
    
    $res_registo = cms_query($sql_registo);
    $i=0;
    while($row_registo = cms_fetch_assoc($res_registo) ){ 
        $arr_prod_temp = call_api_func('get_product', $row_registo["id"]);
        if($arr_prod_temp["id"]>0) {
            $arr_prod[] = $arr_prod_temp;
            $i++;
        }
        
        if($i>200) break;
    }
    
    $resp=array();
    $resp["products"] = $arr_prod;
    
    return serialize($resp);

}

?>
