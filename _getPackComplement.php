<?php

function _getPackComplement($product_id=0, $qtd=0){

    if ($product_id==0){
        $product_id = (int)params('product_id');
        $qtd = (int)params('qtd');
    }

    $prod = call_api_func('get_line_table', 'registos', "id='".$product_id."'");

    if((int)$prod['id']==0 || trim($prod['pack_complementos']) == ""){
        $arr[] = 0;
        return serialize($arr);
    }

    global $LG, $COUNTRY, $MOEDA;

    $arr_pack_comp = array();
    $sql_pack_comp = "SELECT * FROM registos_pack_grupo WHERE id IN(".$prod['pack_complementos'].") ORDER BY ordem, id";
    $res_pack_comp = cms_query($sql_pack_comp);
    while($row_pack_comp = cms_fetch_assoc($res_pack_comp)){
        
        $products       = array();
        $arr_prods      = array();
        $arr_prods_msg  = array();
        $sql_prods_comp = "SELECT msg_$LG as msg, sku_group FROM registos_pack_grupo_grid WHERE registos_pack_grupo_id='".$row_pack_comp["id"]."'";
        $res_prods_comp = cms_query($sql_prods_comp);
        while($row_prods_comp = cms_fetch_assoc($res_prods_comp)){
            $arr_prods[] = $row_prods_comp['sku_group'];
            $arr_prods_msg[$row_prods_comp['sku_group']] = $row_prods_comp['msg'];
        }
        
        $sql_prods = "SELECT id, units_in_package, sales_unit FROM registos WHERE sku_group in('".implode("','", $arr_prods)."') GROUP BY sku_family,cor";
        $res_prods = cms_query($sql_prods);
        while($row_prods = cms_fetch_assoc($res_prods)){
            $arr_prod_temp = call_api_func('get_product', $row_prods["id"]);
            if($arr_prod_temp["id"] > 0){
                $products[$arr_prod_temp["sku_group"]] = $arr_prod_temp;
                if((int)$row_pack_comp['mensagem_consumo'] == 0 || ((int)$row_pack_comp['mensagem_consumo'] == 2 && trim($arr_prods_msg[$arr_prod_temp["sku_group"]]) == '') ){
                    if($row_prods['units_in_package'] > 0 && $row_prods['sales_unit'] > 0){
                        $exp = get_expressions();
                        $sales_unit = call_api_func('get_classificacao',"nome".$LG, "registos_unidades_venda", "id", $row_prods['sales_unit']);
                        $exp['755'] = str_replace("{QTD}", $row_prods['units_in_package'], $exp['755']);
                        $exp['755'] = str_replace("{UNI_VENDA}", $sales_unit, $exp['755']); 
                        $products[$arr_prod_temp["sku_group"]]['more_info'] = $exp['755'];   

                    }
                }elseif((int)$row_pack_comp['mensagem_consumo'] == 1 || ((int)$row_pack_comp['mensagem_consumo'] == 2 && trim($arr_prods_msg[$arr_prod_temp["sku_group"]]) != '' )){
                    $products[$arr_prod_temp["sku_group"]]['more_info'] = $arr_prods_msg[$arr_prod_temp["sku_group"]];   
                }

                if(count($products) >= 20) break;
            }
        }
        
        $arr_packs = array();
        if(trim($row_pack_comp['packs']) != ""){
            $sql_pack = "SELECT * 
                FROM registos_pack 
                WHERE nome$LG!='' AND `dodia`<=CURDATE() AND `aodia`>=CURDATE() 
                        AND id in (".$row_pack_comp['packs'].")
                        AND type=3
                        AND (pais='' OR CONCAT(',',pais,',') LIKE '%,".$COUNTRY['id'].",%' )
                ORDER BY id";
            $res_pack = cms_query($sql_pack);
            while($row = cms_fetch_assoc($res_pack) ){
                $artigos_pack = array();
        
                $arr_sku_group = array();
                for ($i=1; $i <= 7; $i++) { 
                    if($row['artigo'.$i] != "0" && $row['artigo'.$i] != "" && ($i == 1 || ($i > 1 && $row['ativo'.$i] == 1))){ 
                    $arr_sku_group[] = $row['artigo'.$i];                    
                    $artigos_pack[$row['artigo'.$i]] = array("sku_group" => $row['artigo'.$i], "qtd" => $row['qtd'.$i], "oferta" => $row['oferta'.$i], "multiplicar" => $row['multiplicar_qnt'.$i]);
}
                }

                $arr_prod_packs = array();

                $sql_prods = "SELECT id FROM registos WHERE sku_group in('".implode("','", $arr_sku_group)."') GROUP BY sku_family,cor";
                $res_prods = cms_query($sql_prods);
                while($row_prods = cms_fetch_assoc($res_prods)){
                    $arr_prod_temp = call_api_func('get_product', $row_prods["id"]);
                    if($arr_prod_temp["id"] > 0) $arr_prod_packs[$arr_prod_temp["sku_group"]] = $arr_prod_temp;
                }

                if(count($arr_prod_packs) != count($artigos_pack)) continue;

                $sold_out = 0;
                $price = 0;
                $old_price = 0;
                $final_prod = array();
                foreach($artigos_pack as $k => $v){
                    
                    $qtd_temp = $v['qtd'];
                    if((int)$v['multiplicar'] == 1){
                        $unidades = $qtd * $prod['units_in_package'];
                        $qtd_temp = $v['qtd'] * $unidades;
                    }

                    $qtd_temp = ceil($qtd_temp);
                    
                    if((int)$v['oferta'] == 0)
                        $price += $arr_prod_packs[$k]["price"]["value_original"] * $qtd_temp;
                        
                    $old_price += $arr_prod_packs[$k]["price"]["value_original"] * $qtd_temp;

                    if(!isset($arr_prod_packs[$k])) continue;
                    
                    $final_prod[$k] = $arr_prod_packs[$k];      
                    $final_prod[$k]['pack_product_offer'] = (int)$v['oferta'];
                    $final_prod[$k]['pack_product_qtd'] = $qtd_temp;  
     
                }
                
                if(count($final_prod)<2) continue;

                $valor_desconto = ($price*$row["preco"])/100;
                
                $novo_preco     = number_format($price-$valor_desconto, 2, '.', '');
                $old_price      = number_format($old_price, 2, '.', '');             

                if($old_price <= $novo_preco) $old_price = 0;
                
                $price_discount = 0;
                if((int)$row["preco"]>0) $price_discount = (int)$row["preco"].'%';

                $final_prod = array_values($final_prod);
                
                $arr_packs[] = array(
                    "id"              => $row["id"],
                    "sku"             => $row["sku"],            
                    "name"            => $row["nome".$LG],
                    "content"         => $row["desc".$LG],
                    "price"           => call_api_func('OBJ_money', $novo_preco, $MOEDA['id']),            
                    "products"        => $final_prod,
                    "sold_out"        => $sold_out,
                    "old_price"       => call_api_func('OBJ_money', $old_price, $MOEDA['id']),
                    "price_discount"  => $price_discount
                );
                
            }
 
        }

        $products = array_values($products);

        $arr_pack_comp[] = array(
            "id"        => $row_pack_comp['id'],
            "name"      => $row_pack_comp['nome'.$LG],
            "content"   => $row_pack_comp['desc'.$LG],
            "packs"     => $arr_packs,
            "products"  => $products
        );
        
        
    }

    $arr['packs_complement'] = $arr_pack_comp;
    return serialize($arr); 

}

?>
