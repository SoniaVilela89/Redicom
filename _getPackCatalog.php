<?

function _getPackCatalog($pack=0)
{                 
    global $LG, $CONFIG_ORDEM, $MOEDA, $MARKET, $LONG_CACHE, $CONFIG_LISTAGEM_QTD;
        
    if ($pack==0){
       $pack = (int)params('pack');
    }  
    
    
    $arr = array();
    
    $sql_pack = "SELECT * FROM registos_pack WHERE `dodia`<=CURDATE() AND `aodia`>=CURDATE() AND artigo1!='' AND moeda='".$MOEDA["id"]."' AND type=1 AND id='".$pack."' LIMIT 0,1";
    $res_pack = cms_query($sql_pack);
    $row_pack = cms_fetch_assoc($res_pack);

    if((int)$row_pack["id"]==0){
        $arr[] = 0;
        return serialize($arr);
    }
    
    $page_id = 5;
    $JOIN = '';
    $JOIN_ARRAY = array();
    $_query_regras = build_regras($page_id, $JOIN_ARRAY, $row_pack["artigo1"]);
    
    $_query_regras .= build_regras_mercado($JOIN_ARRAY);
    
    $CONFIG_ORDEM  = str_replace('{LG}', $LG, $CONFIG_ORDEM);
    $order_by = $CONFIG_ORDEM;
    $order_by .= " LIMIT 0,30";
    
    if((int)$LONG_CACHE==1 && !isset($JOIN_ARRAY['STOCK'])){ 
        $JOIN_ARRAY['STOCK'] = " LEFT JOIN registos_stocks ON registos.sku=registos_stocks.sku AND registos_stocks.iddeposito IN (".$ids_depositos.") ";   
    }                 
                            
    if(count($JOIN_ARRAY)>0){
        $JOIN = ' '.implode(' ', $JOIN_ARRAY).' ';
    } 

    $_query_regras_final        = $_query_regras;
    $LISTAGEM_DESAGRUPAR_CORES  = 0;
    $priceList                  = $_SESSION['_MARKET']['lista_preco'];
   
    $temp = return_products_list($page_id, $JOIN, $_query_regras_final, $priceList, $order_by);
    
    $prods_final_original = $prods_final = $temp['prods'];
    
    
    $pos_inicial = 0;
    foreach($prods_final as $k => $v){
        $prods_final_variants[$v[id]] = $v[id];
        $chavecomposta = $v['sku_family'].'-'.$v['cor'];
        
        $ordem = "70000";
         
        if($array_pids_ordem[$chavecomposta]!='70000') $ordem = $array_pids_ordem[$chavecomposta];
             
        $v['ORDERM_PAG'] = $ordem;
        $v['ORDEM_INICIAL'] = $pos_inicial;
        
        $chavecomposta = $v['sku_family'];
               
        if(array_key_exists($chavecomposta, $prods_final_temp)){
            #if($ordem!='-1' && ($ordem>$prods_final_temp[$chavecomposta]["ORDERM_PAG"])){
            if($ordem!='70000' && ($ordem<$prods_final_temp[$chavecomposta]["ORDERM_PAG"])){
                $prods_final_temp[$chavecomposta] =  $v;
            }
        }else{
            $prods_final_temp[$chavecomposta] =  $v;
        }

        $pos_inicial++;
    }
    
    $prods_final      = $prods_final_temp;
    
  
    $i    = 0;
    $aux  = 1;
    foreach($prods_final as $k => $v){

        $i++;
        
        $temp = call_api_func("get_line_table", "registos", "id='".$v['id']."'");
                    
        $v = array_merge($v, $temp);
        
        $v['id_original'] = $v['id'];        
                           
        $pc = $page_count;        
        if($pc==0){
            $c = $CONFIG_LISTAGEM_QTD*$aux;
            if($i>$c){
                $aux++;
            }
            $pc = $aux;                    
        }        
          
        $product = call_api_func('productOBJ',$v, "", "", $pageID, $pc, $is_gestor, 0, 1, $is_home);
        # Destaque de produto definido em getsor no detalhe ou no VM
        get_product_enhance($product, $pageID);
                

        if( !empty($product) )
            $result['PRODUCTS'][] = $product;

    }

    $arr["products"] = $result['PRODUCTS'];
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    
    $arr['expressions'] = call_api_func('get_expressions', $page_id);
    
    return serialize($arr);

}
