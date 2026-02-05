<?

function _getClickCollectProductInfo($product_id=0){

    global $LG, $MARKET, $userID, $COUNTRY, $CACHE_HEADER_FOOTER, $CACHE_KEY, $fx;
    
    if ( (int)$product_id > 0 ){
       $product_id = (int)$product_id;       
    }else{
       $product_id = (int)params('product_id');      
    }
    
    
    $product = call_api_func("get_line_table","registos", "id='".$product_id."'");
    
    $loja_pref_id = 0;
    if( (int)$_SESSION["EC_USER"]["loja_pref_id"] > 0 ){
        $loja_pref_id = (int)$_SESSION["EC_USER"]["loja_pref_id"];
    }elseif( isset($_COOKIE["USER_STORE"]) ){
        $loja_pref_id = (int)base64_decode($_COOKIE['USER_STORE']);
    }
    
    if( $loja_pref_id > 0 ){
        
        $arr_market_warehouses = explode(",", $MARKET['deposito']);
    
        $arr_depositos_lojas = array();
        $sql_loja_deposito = "SELECT * FROM ec_deposito WHERE loja='".$loja_pref_id."'";
        $res_loja_deposito = cms_query($sql_loja_deposito);
        while( $row_loja_deposito = cms_fetch_assoc($res_loja_deposito) ){
            
            if( in_array($row_loja_deposito["id"], $arr_market_warehouses) ){
                
                $arr_depositos_lojas[] = $row_loja_deposito["id"];
                
            }
            
        }
        
    }
        
    $without_stock = 0;        
    $resp = array("status" => 1);
    
    if( (int)$product["id"] > 0 && count($arr_depositos_lojas) > 0 ){
        
        $query_stock = cms_query("SELECT * 
                                    FROM registos_stocks 
                                    WHERE sku='".$product['sku']."' AND iddeposito IN (".implode(",",$arr_depositos_lojas).")
                                        AND ((stock-margem_seguranca)>0 OR venda_negativo=1) 
                                    LIMIT 0,1");           
        $stock = cms_fetch_assoc($query_stock);
        
        if( (int)$stock['id'] == 0 ){   
            $without_stock = 1;
        }
                 
    }elseif( (int)$product["id"] > 0 ){
        $without_stock = 1;   
    }
    
    
    $scope = array();
    $scope['MERCADO']       = $_SESSION['_MARKET']['id'];
    $scope['LG']            = $LG;     
    
    $cacheid = $CACHE_KEY."HD_CC_".implode('_', $scope);
    
    $dados = $fx->_GetCache($cacheid, $CACHE_HEADER_FOOTER);
        
    if( $dados!=false && !isset($_GET['nocache']) ){
    
        $arr_click_collect = unserialize($dados);
         
    }else{

        $sql_cc = "SELECT user_test, id, click_collect_nome$LG AS name,
                        click_collect_desc$LG AS description, click_collect_btt$LG AS btt,
                        click_collect_layout AS layout, click_collect_force_popup_mobile AS force_popup_mobile,
                        click_collect_theme AS theme, click_collect_stores AS stores,
                        click_collect_bloco$LG AS info                        
                    FROM ec_shipping 
                    WHERE id IN (".$_SESSION['_MARKET']['metodos_envio'].") 
                        AND click_collect = 1
                        AND click_collect_nome$LG != '' 
                        AND click_collect_desc$LG != ''
                        AND click_collect_btt$LG != ''
                        AND click_collect_bloco$LG != '' 
                    ORDER BY id DESC 
                    LIMIT 0,1";
                    
        $query_cc           = cms_query($sql_cc);
        $arr_click_collect  = cms_fetch_assoc($query_cc);
        
        $fx->_SetCache($cacheid, serialize($arr_click_collect), $CACHE_HEADER_FOOTER);
        
    }

    if( (int)$arr_click_collect['id'] > 0 ){
        
        if( $without_stock == 0 && trim($arr_click_collect['stores']) != "" && !in_array( $loja_pref_id, explode(",", $arr_click_collect['stores']) ) ) $without_stock = 1;
            
        if( $without_stock == 0 ){
            
            $arr_click_collect['info'] = nl2br($arr_click_collect['info']);
            
            $resp = $arr_click_collect;   
            
        }else{
            
            $arr_store = array();
            
            $more_where = "";
            if( trim($arr_click_collect['stores']) != "" ) $more_where = " AND ec_l.id IN(".$arr_click_collect['stores'].")";
            
            $sql = "SELECT ec_l.nomept 
                        FROM ec_lojas ec_l 
                        INNER JOIN ec_deposito ec_d ON ec_l.id=ec_d.loja
                        INNER JOIN registos_stocks s ON s.iddeposito=ec_d.id
                    WHERE ec_l.pais='".$COUNTRY['id']."' AND ec_l.hidden=0 AND s.sku='".$product["sku"]."' AND (s.stock-s.margem_seguranca)>0 $more_where
                    ORDER BY ec_l.ordem,ec_l.nomept";
            
            $res  = cms_query($sql);
            while($row = cms_fetch_assoc($res)){
                
                $arr_store[ $row['nomept'] ] = $row['nomept'];
                
            }
            
            $resp['stores'] = $arr_store;   
            
        }
    
    }
   
    
    return serialize($resp);
    
}

?>
