<?
function _addPackToBasket($id=null, $cat=null, $pid=null, $qtd=null, $pid1=0, $pid2=0, $pid3=0, $get_price=0, $prod_id=0, $qtd_add_basket=0, $pid4=0, $pid5=0, $pid6=0, $pid7=0)
{

    if(is_null($pid)){
        $id = params('id');
        $cat = params('cat');
        $pid = params('pid');
        $qtd = params('qtd');
        $pid1 = params('pid1');
        $pid2 = params('pid2');
        $pid3 = params('pid3');
        $get_price = params('get_price');
        $prod_id = params('prod_id');
        $qtd_add_basket = params('qtd_add_basket');
        $pid4 = params('pid4');
        $pid5 = params('pid5');
        $pid6 = params('pid6');
        $pid7 = params('pid7');
    }
    
    if( $qtd<=0 ) $qtd=1;

    
    global $userID;
    global $eComm;
    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $fx;
    global $sslocation;
    global $EGIFT_COM_PRODUTOS;
    global $CONFIG_TEMPLATES_PARAMS, $B2B, $CONFIG_IMAGE_SIZE, $API_CONFIG_IMAGE_CART;
    
    


    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);
    
    if((int)$EGIFT_COM_PRODUTOS==0){
        $eComm->removeInsertedProductsEGift($userID);
    }

    $priceList  = $_SESSION['_MARKET']['lista_preco']; 
    $ids_depositos = $_SESSION['_MARKET']['deposito'];

    if((int)$B2B>0){
    
        $page = call_api_func('get_pagina', $id, "_trubricas");
        if($page["catalogo"]>0){
            $catalogo_id = $page["catalogo"];
        }else{
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }
    
        preparar_regras_carrinho($catalogo_id);
        
        if((int)$GLOBALS["REGRAS_CATALOGO"]==0) preparar_regras_carrinho($_SESSION['_MARKET']['catalogo']);
        
        if($GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"]>0) $priceList = $GLOBALS["LISTA_PRECO_REGRAS_CATALOGO"];
        
        if($GLOBALS["DEPOSITOS_REGRAS_CATALOGO"]!='') $ids_depositos = $GLOBALS["DEPOSITOS_REGRAS_CATALOGO"];
        
    }
 

    $q          = "SELECT *,(preco+preco2+preco3+preco4) as preco_final FROM registos_pack WHERE nome$LG!='' AND `dodia`<=CURDATE() AND `aodia`>=CURDATE() AND id='$pid' ORDER BY id";
    $res_pack   = cms_query($q);
    $row        = cms_fetch_assoc($res_pack);  
    
    $arr = array();
    $arr['page_id'] = $id;
    $arr['page_cat_id'] = (int)$GLOBALS["REGRAS_CATALOGO"];
    $arr['id_cliente'] = $userID;
    $arr['status'] = "0";
    $arr['pid'] = $row['sku'];
    $arr['ref'] = $row['sku'];
    $arr['sku_family'] = $row['sku'];
    $arr['sku_group'] = $row['sku'];
    $arr['nome'] = $row['nome'.$LG];
    $arr['unidade_portes']=1;
    
    if($row['artigo3'] == "0") $row['artigo3'] = '';
    if($row['artigo4'] == "0") $row['artigo4'] = '';
    
    $artigos = array();
    if($row['artigo1']!="0" and $row['artigo1']!="") $artigos[] = $row['artigo1'];
    if($row['artigo2']!="0" and $row['artigo2']!="") $artigos[] = $row['artigo2'];
    if($row['artigo3']!="0" and $row['artigo3']!="") $artigos[] = $row['artigo3'];
    if($row['artigo4']!="0" and $row['artigo4']!="") $artigos[] = $row['artigo4'];
    
    $pid_tamanho = "";
    if($row["type"]==2 || $row["type"] == 3){
        $artigos = array();
        $arr_qnt = array();
      
        $row["price_discount_pack"] = $row["preco"];
        $price_old = 0;
        $price_1 = 0;

        if($row["type"] == 3) $prod = call_api_func('get_line_table', 'registos', "id='".$prod_id."'");
      
        if((int)$pid1>0 ){
            $row["pid1"] = $pid1;

            #$arr_prod1 = get_product($pid1,"", $id, 0, 1, $cat);
            $arr_prod1 = get_product($pid1,"", 5); 
                        
                      
            $arr_p = explode(",", $row['artigo1']);
            if(in_array($arr_prod1["sku_group"], $arr_p)){
          
                $qtd1 = 1;
                if((float)$row['qtd1'] > 1) $qtd1 = $row['qtd1'];

                if((int)$row['multiplicar_qnt1'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd1'] = $qtd1 = $row['qtd1'] * $unidades;
                }
              
                $qtd1 = ceil($qtd1);

                $price_1 = $arr_prod1["price"]["value_original"] * $qtd1;
                $price_1_old = $arr_prod1["previous_price"]["value_original"] * $qtd1;
                
                $price_old += $arr_prod1["price"]["value_original"] * $qtd1;
                
                $valor_desconto = ($price_1*$row["price_discount_pack"])/100;
                $price_1        = $price_1-$valor_desconto; 

                if((int)$arr_prod1["selected_variant"]["dimension_id"] > 0) $pid_tamanho .= $arr_prod1["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod1["selected_variant"]["size_id"];

                $artigos[] = $arr_prod1["sku"]; 
                
                $arr_qnt[$arr_prod1["sku"]] += $qtd1;
              
                $row['qtd1'] = ceil($row['qtd1']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
                
        }elseif((int)$pid1 == 0 && (int)$row["type"] < 3 && trim($row['artigo1']) != ""){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }

        $row["preco"] = number_format($price_1, 2, '.', '');
        $row["preco_old"]  = $price_1_old;
        
        $price_2 = 0;
        $price_2_old = 0;
        if((int)$pid2>0){
            $row["pid2"] = $pid2;
            
            //$arr_prod2 = get_product($pid2,"", $id, 0, 0, $cat);
            #$arr_prod2 = get_product($pid2,"", $id, 0, 1, $cat);     
            $arr_prod2 = get_product($pid2,"", 5);     
            
            # Para quando o produto 2 não está preenchido e o 3 está
            if( (int)$pid3 == 0 && $row['artigo2'] == "" && $row['artigo3'] != "" ){
                $arr_p = explode(",", $row['artigo3']);
            }else{
                $arr_p = explode(",", $row['artigo2']);
            }
                
            if(in_array($arr_prod2["sku_group"], $arr_p)){
            
                $qtd2 = 1;
                if((float)$row['qtd2'] > 1) $qtd2 = $row['qtd2'];

                if((int)$row['multiplicar_qnt2'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd2'] = $qtd2 = $row['qtd2'] * $unidades;
                }
            
                $qtd2 = ceil($qtd2);
            
                if((int)$row['oferta2'] == 0){
                    $price_2 = $arr_prod2["price"]["value_original"] * $qtd2;
                    $price_2_old = $arr_prod2["previous_price"]["value_original"] * $qtd2;
                }
                $price_old += $arr_prod2["price"]["value_original"] * $qtd2;

                $valor_desconto = ($price_2*$row["price_discount_pack"])/100;
                $price_2        = $price_2-$valor_desconto;
                
                if((int)$arr_prod2["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod2["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod2["selected_variant"]["size_id"];

                $artigos[] = $arr_prod2["sku"];
                
                $arr_qnt[$arr_prod2["sku"]] += $qtd2;
                
                $row['qtd2'] = ceil($row['qtd2']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
        }elseif((int)$pid2 == 0 && (int)$row["type"] < 3 && trim($row['artigo2']) != ""){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }

        $row["preco2"] = number_format($price_2, 2, '.', '');
        $row["preco2_old"]  = $price_2_old;
            
        $price_3 = 0;
        $price_3_old = 0;
        if((int)$pid3>0){
            $row["pid3"] = $pid3;
            
            //$arr_prod3 = get_product($pid3,"", $id, 0, 0, $cat);
            //$arr_prod3 = get_product($pid3,"", $id, 0, 1, $cat);
            $arr_prod3 = get_product($pid3,"", 5);
            
            
                
            $arr_p = explode(",", $row['artigo3']);
            if(in_array($arr_prod3["sku_group"], $arr_p)){
              
                $qtd3 = 1;
                if((float)$row['qtd3'] > 1) $qtd3 = $row['qtd3'];

                if((int)$row['multiplicar_qnt3'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd3'] = $qtd3 = $row['qtd3'] * $unidades;
                }
          
                $qtd3 = ceil($qtd3);
          
                if((int)$row['oferta3'] == 0){
                    $price_3 = $arr_prod3["price"]["value_original"] * $qtd3;
                    $price_3_old = $arr_prod3["previous_price"]["value_original"] * $qtd3;
                }
                $price_old += $arr_prod3["price"]["value_original"] * $qtd3;
                
                $valor_desconto = ($price_3*$row["price_discount_pack"])/100;
                $price_3        = $price_3-$valor_desconto;
                
                if((int)$arr_prod3["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod3["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod3["selected_variant"]["size_id"];

                $artigos[] = $arr_prod3["sku"];
                
                $arr_qnt[$arr_prod3["sku"]] += $qtd3;

                $row['qtd3'] = ceil($row['qtd3']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
              
        }elseif((int)$pid3 == 0 && (int)$row["type"] < 3 && trim($row['artigo3']) != "" ){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }
        $row["preco3"]      = number_format($price_3, 2, '.', '');
        $row["preco3_old"]  = $price_3_old;
            
        $price_4 = 0;
        $price_4_old = 0;
        if((int)$pid4>0){
            $row["pid4"] = $pid4;
            
            //$arr_prod3 = get_product($pid3,"", $id, 0, 0, $cat);
            //$arr_prod4 = get_product($pid4,"", $id, 0, 1, $cat);
            $arr_prod4 = get_product($pid4,"", 5);
                
            $arr_p = explode(",", $row['artigo4']);
            if(in_array($arr_prod4["sku_group"], $arr_p)){
              
                $qtd4 = 1;
                if((float)$row['qtd4'] > 1) $qtd4 = $row['qtd4'];

                if((int)$row['multiplicar_qnt4'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd4'] = $qtd4 = $row['qtd4'] * $unidades;
                }
          
                $qtd4 = ceil($qtd4);
          
                if((int)$row['oferta4'] == 0){
                    $price_4 = $arr_prod4["price"]["value_original"] * $qtd4;
                    $price_4_old = $arr_prod4["previous_price"]["value_original"] * $qtd4;
                }
                $price_old += $arr_prod4["price"]["value_original"] * $qtd4;
                
                $valor_desconto = ($price_4*$row["price_discount_pack"])/100;
                $price_4        = $price_4-$valor_desconto;
                
                if((int)$arr_prod4["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod4["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod4["selected_variant"]["size_id"];

                $artigos[] = $arr_prod4["sku"];
                
                $arr_qnt[$arr_prod4["sku"]] += $qtd4;

                $row['qtd4'] = ceil($row['qtd4']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
              
        }
        $row["preco4"]      = number_format($price_4, 2, '.', '');
        $row["preco4_old"]  = $price_4_old;

        $price_5 = 0;
        $price_5_old = 0;
        if((int)$pid5>0){
            $row["pid5"] = $pid5;
            
            //$arr_prod3 = get_product($pid3,"", $id, 0, 0, $cat);
            //$arr_prod5 = get_product($pid5,"", $id, 0, 1, $cat);
            $arr_prod5 = get_product($pid5,"", 5);
                
            $arr_p = explode(",", $row['artigo5']);
            if(in_array($arr_prod5["sku_group"], $arr_p)){
              
                $qtd5 = 1;
                if((float)$row['qtd5'] > 1) $qtd5 = $row['qtd5'];

                if((int)$row['multiplicar_qnt5'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd5'] = $qtd5 = $row['qtd5'] * $unidades;
                }
          
                $qtd5 = ceil($qtd5);
          
                if((int)$row['oferta5'] == 0){
                    $price_5 = $arr_prod5["price"]["value_original"] * $qtd5;
                    $price_5_old = $arr_prod5["previous_price"]["value_original"] * $qtd5;
                }
                $price_old += $arr_prod5["price"]["value_original"] * $qtd5;
                
                $valor_desconto = ($price_5*$row["price_discount_pack"])/100;
                $price_5        = $price_5-$valor_desconto;
                
                if((int)$arr_prod5["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod5["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod5["selected_variant"]["size_id"];

                $artigos[] = $arr_prod5["sku"];
                
                $arr_qnt[$arr_prod5["sku"]] += $qtd5;

                $row['qtd5'] = ceil($row['qtd5']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
              
        }
        $row["preco5"]      = number_format($price_5, 2, '.', '');
        $row["preco5_old"]  = $price_5_old;

        $price_6 = 0;
        $price_6_old = 0;
        if((int)$pid6>0){
            $row["pid6"] = $pid6;
            
            
            //$arr_prod3 = get_product($pid3,"", $id, 0, 0, $cat);
            //$arr_prod6 = get_product($pid6,"", $id, 0, 1, $cat);
            $arr_prod6 = get_product($pid6,"", 5);
                
            $arr_p = explode(",", $row['artigo6']);
            if(in_array($arr_prod6["sku_group"], $arr_p)){
              
                $qtd6 = 1;
                if((float)$row['qtd6'] > 1) $qtd6 = $row['qtd6'];

                if((int)$row['multiplicar_qnt6'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd6'] = $qtd6 = $row['qtd6'] * $unidades;
                }
          
                $qtd6 = ceil($qtd6);
          
                if((int)$row['oferta6'] == 0){
                    $price_6 = $arr_prod6["price"]["value_original"] * $qtd6;
                    $price_6_old = $arr_prod6["previous_price"]["value_original"] * $qtd6;
                }
                $price_old += $arr_prod6["price"]["value_original"] * $qtd6;
                
                $valor_desconto = ($price_6*$row["price_discount_pack"])/100;
                $price_6        = $price_6-$valor_desconto;
                
                if((int)$arr_prod6["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod6["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod6["selected_variant"]["size_id"];

                $artigos[] = $arr_prod6["sku"];
                
                $arr_qnt[$arr_prod6["sku"]] += $qtd6;

                $row['qtd6'] = ceil($row['qtd6']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
              
        }
        $row["preco6"]      = number_format($price_6, 2, '.', '');
        $row["preco6_old"]  = $price_6_old;

        $price_7 = 0;
        $price_7_old = 0;
        if((int)$pid7>0){
            $row["pid7"] = $pid7;
            
            //$arr_prod3 = get_product($pid3,"", $id, 0, 0, $cat);
            //$arr_prod7 = get_product($pid7,"", $id, 0, 1, $cat);
            $arr_prod7 = get_product($pid7,"", 5);
            
                
            $arr_p = explode(",", $row['artigo7']);
            if(in_array($arr_prod7["sku_group"], $arr_p)){
              
                $qtd7 = 1;
                if((float)$row['qtd7'] > 1) $qtd7 = $row['qtd7'];

                if((int)$row['multiplicar_qnt7'] == 1){
                    $unidades = $qtd_add_basket * $prod['units_in_package'];
                    $row['qtd7'] = $qtd7 = $row['qtd7'] * $unidades;
                }
          
                $qtd7 = ceil($qtd7);
          
                if((int)$row['oferta7'] == 0){
                    $price_7 = $arr_prod7["price"]["value_original"] * $qtd7;
                    $price_7_old = $arr_prod7["previous_price"]["value_original"] * $qtd7;
                }
                $price_old += $arr_prod7["price"]["value_original"] * $qtd7;
                
                $valor_desconto = ($price_7*$row["price_discount_pack"])/100;
                $price_7        = $price_7-$valor_desconto;
                
                if((int)$arr_prod7["selected_variant"]["dimension_id"] > 0)  $pid_tamanho .= $arr_prod7["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod7["selected_variant"]["size_id"];

                $artigos[] = $arr_prod7["sku"];
                
                $arr_qnt[$arr_prod7["sku"]] += $qtd7;

                $row['qtd7'] = ceil($row['qtd7']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
              
        }
        $row["preco7"]      = number_format($price_7, 2, '.', '');
        $row["preco7_old"]  = $price_7_old;
            
        $row['preco_final'] = $price_1+$price_2+$price_3+$price_4+$price_5+$price_6+$price_7;
        
        $row["skus"]        = implode(',', $artigos);
        $row["quantidades"] = $arr_qnt;
        
        $valor_desconto = $price_old-$row['preco_final'];  
    }elseif($row["type"] == 4){

        $artigos = array();
        $arr_qnt = array();

        $row["price_discount_pack"] = $row["preco"];
        $price_old = 0;
        $price_1 = 0;
        
        if((int)$pid1 > 0 ){
            $row["pid1"] = $pid1;

            $arr_prod1 = get_product($pid1,"", 5); 
                      
            $arr_p = explode(",", $row['artigo1']);
            if(in_array($arr_prod1["sku_group"], $arr_p)){
                $price_1 = $arr_prod1["price"]["value_original"];
                $price_1_old = $arr_prod1["previous_price"]["value_original"];
                
                $price_old += $arr_prod1["price"]["value_original"];
                
                if((int)$arr_prod1["selected_variant"]["dimension_id"] > 0) $pid_tamanho .= $arr_prod1["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod1["selected_variant"]["size_id"];

                $artigos[] = $arr_prod1["sku"]; 
                
                $arr_qnt[$arr_prod1["sku"]] += 1;
              
                $row['qtd1'] = ceil($row['qtd1']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }    
        }

        $price_2 = 0;
        $price_2_old = 0;
        if((int)$pid2 > 0 && trim($row['artigo2']) != ""){
            $row["pid2"] = $pid2;

            $arr_prod2 = get_product($pid2,"", 5);

            $sql_catalogo = "SELECT id FROM prefetch_rcc_produtos_catalogo WHERE FIND_IN_SET(".$row["artigo2"].", catalogos) AND sku_group='".$arr_prod2["sku_group"]."' LIMIT 0,1";
            $res_catalogo = cms_query($sql_catalogo);
            $row_catalogo = cms_fetch_assoc($res_catalogo);
            if((int)$row_catalogo["id"] > 0){
                
                $price_2 = $arr_prod2["price"]["value_original"];
                $price_2_old = $arr_prod2["previous_price"]["value_original"];
                
                $price_old += $arr_prod2["price"]["value_original"];
                
                if((int)$arr_prod2["selected_variant"]["dimension_id"] > 0) $pid_tamanho .= $arr_prod2["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod2["selected_variant"]["size_id"];

                $artigos[] = $arr_prod2["sku"]; 
                
                $arr_qnt[$arr_prod2["sku"]] += 1;
              
                $row['qtd2'] = ceil($row['qtd2']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }

            
        }elseif((int)$pid2 == 0 &&  trim($row['artigo2']) != ""){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }

        $price_3 = 0;
        $price_3_old = 0;
        if((int)$pid3 > 0 && trim($row['artigo3']) != ""){
            $row["pid3"] = $pid3;

            $arr_prod3 = get_product($pid3,"", 5);

            $sql_catalogo = "SELECT id FROM prefetch_rcc_produtos_catalogo WHERE FIND_IN_SET(".$row["artigo3"].", catalogos) AND sku_group='".$arr_prod3["sku_group"]."' LIMIT 0,1";
            $res_catalogo = cms_query($sql_catalogo);
            $row_catalogo = cms_fetch_assoc($res_catalogo);
            if((int)$row_catalogo["id"] > 0){

                $price_3 = $arr_prod3["price"]["value_original"];
                $price_3_old = $arr_prod3["previous_price"]["value_original"];
                
                $price_old += $arr_prod3["price"]["value_original"];
                
                if((int)$arr_prod3["selected_variant"]["dimension_id"] > 0) $pid_tamanho .= $arr_prod3["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod3["selected_variant"]["size_id"];

                $artigos[] = $arr_prod3["sku"]; 
                
                $arr_qnt[$arr_prod3["sku"]] += 1;
              
                $row['qtd3'] = ceil($row['qtd3']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
        }elseif((int)$pid3 == 0 &&  trim($row['artigo3']) != ""){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }

        $price_4 = 0;
        $price_4_old = 0;
        if((int)$pid4 > 0 && trim($row['artigo4']) != "" && $row['artigo4'] != "0"){
            $row["pid4"] = $pid4;

            $row["qtd4"] = 1;

            $arr_prod4 = get_product($pid4,"", 5);

            $sql_catalogo = "SELECT id FROM prefetch_rcc_produtos_catalogo WHERE FIND_IN_SET(".$row["artigo4"].", catalogos) AND sku_group='".$arr_prod4["sku_group"]."' LIMIT 0,1";
            $res_catalogo = cms_query($sql_catalogo);
            $row_catalogo = cms_fetch_assoc($res_catalogo);
            if((int)$row_catalogo["id"] > 0){

                $price_4 = $arr_prod4["price"]["value_original"];
                $price_4_old = $arr_prod4["previous_price"]["value_original"];
                
                $price_old += $arr_prod4["price"]["value_original"];
                
                if((int)$arr_prod4["selected_variant"]["dimension_id"] > 0) $pid_tamanho .= $arr_prod4["selected_variant"]["dimension_id"];
                else $pid_tamanho .= $arr_prod4["selected_variant"]["size_id"];

                $artigos[] = $arr_prod4["sku"]; 
                
                $arr_qnt[$arr_prod4["sku"]] += 1;
              
                $row['qtd4'] = ceil($row['qtd4']);
            }else{
                return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
            }
        }elseif((int)$pid4 == 0 && trim($row['artigo4']) != "" && trim($row['artigo4']) != "0"){
            return serialize(array("0" => "0", "error" => "1", "error_desc" => "Product not found"));
        }

        if((int)$row["tipo_preco"] == 0){
            $valor_desconto1 = ($price_1*$row["price_discount_pack"])/100;
            $price_1        = $price_1-$valor_desconto1;

            $valor_desconto2 = ($price_2*$row["price_discount_pack"])/100;
            $price_2        = $price_2-$valor_desconto2;

            $valor_desconto3 = ($price_3*$row["price_discount_pack"])/100;
            $price_3        = $price_3-$valor_desconto3;

            $valor_desconto4 = ($price_4*$row["price_discount_pack"])/100;
            $price_4        = $price_4-$valor_desconto4;

            $row['preco_final'] = $price_1+$price_2+$price_3+$price_4;
        }else{
            $row['preco_final'] = $row["preco2"];

            $valor_pack = $price_1+$price_2+$price_3+$price_4;  
                 
            $valor_pack_desejado = $row["preco2"];
        
            $fator = $valor_pack_desejado / $valor_pack;
            $total_diferenca = 0;

            $price_1 = round($price_1 * $fator, 2);
            $total_diferenca += $price_1;

            if($price_3 > 0){
                $price_2 = round($price_2 * $fator, 2);
                $total_diferenca += $price_2;
                if($price_4 > 0){
                    $price_3 = round($price_3 * $fator, 2);
                    $total_diferenca += $price_3;

                    $price_4 = $valor_pack_desejado - $total_diferenca; 
                }else{
                    $price_3 = $valor_pack_desejado - $total_diferenca; 
                }

            }else{
                $price_2 = $valor_pack_desejado - $total_diferenca; 
            }

            $descontoPercent = (($valor_pack - $valor_pack_desejado) / $valor_pack) * 100;

            $row["price_discount_pack"] = round($descontoPercent, 2);
            //$desconto_percentagem = (($price_old - $row['preco_final']) / $price_old) * 100;
        }       
        
        $row["preco"] = number_format($price_1, 2, '.', '');
        $row["preco_old"]  = $price_1_old;
        $row["preco2"] = number_format($price_2, 2, '.', '');
        $row["preco2_old"]  = $price_2_old;     
        $row["preco3"] = number_format($price_3, 2, '.', '');
        $row["preco3_old"]  = $price_3_old;
        $row["preco4"] = number_format($price_4, 2, '.', '');
        $row["preco4_old"]  = $price_4_old;
        $row["skus"]        = implode(',', $artigos);
        $row["quantidades"] = $arr_qnt;

        $valor_desconto = $price_old-$row['preco_final'];

        if($row['preco_final'] >= $price_old) $price_old = 0;
        
    } 
    
    if((int)$get_price>0){
        
        $price_discount = 0;
        if((int)$row["price_discount_pack"]>0) $price_discount = (int)$row["price_discount_pack"].'%';
        
        $resp=array();
        $resp["price"]  = call_api_func('OBJ_money', $row['preco_final'], $MOEDA['id']);
        $resp["old_price"]  = call_api_func('OBJ_money', $price_old, $MOEDA['id']);
        $resp["price_discount"]  = $price_discount;
        
        return serialize($resp);
    }
    
    if(trim($pid_tamanho)!="") $arr['pid'] = $pid_tamanho."|||".$row['sku'];   
    $arr['composition'] = implode(' + ', $artigos);

    if($price_old == $row['preco_final']){
        $price_old = 0;
    }

    $arr['cor_id'] = "";
    $arr['cor_cod'] =  "";
    $arr['cor_name'] = "";
    $arr['largura'] = "";
    $arr['altura'] = "";
    $arr['peso'] = "";
    $arr['tamanho'] = "";
    if($qtd>999){
        $qtd = 999;
    }
    $arr['qnt'] = $qtd;
    $arr['data'] = date("Y-m-d");
    $arr['datahora'] = date("Y-m-d H:i:s");
    $arr['valoruni'] = $row['preco_final'];
    $arr['valoruni_anterior']   = $price_old;
    $arr['valoruni_desconto']   = $valor_desconto;
    
    $arr['pais_iso'] = $_SESSION['_COUNTRY']['country_code'];
    $arr['moeda'] = $MOEDA['id'];
    $arr['taxa_cambio'] = $MOEDA['cambio'];
    $arr['moeda_simbolo'] = $MOEDA['abreviatura'];
    $arr['moeda_prefixo'] = $MOEDA['prefixo'];
    $arr['moeda_sufixo']  = $MOEDA['sufixo'];

    $arr['moeda_decimais']  = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares']  = $MOEDA['casa_milhares'];

    $arr['mercado'] = $MARKET['id'];
    $arr['deposito'] = $ids_depositos;
    $arr['lista_preco'] = $priceList;
    
    $arr_cookie_cpn = getCookieCPN();
    
    $arr['tracking_campanha_url_id'] = implode(',', array_keys($arr_cookie_cpn));
    $arr['email'] = $_SESSION['EC_USER']['email'];
    $arr['idioma_user'] = $LG;
    $arr['pais_cliente'] = $_SESSION['_COUNTRY']['id'];
    $arr['pack'] = "1";

    $row = array_map(function($v){
        return is_string($v) ? utf8_encode(strip_tags($v)) : $v;
    }, $row);

    $arr['info_pack'] = serialize($row);     



    $tamanhos = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['regular']);  
        
    $cal = (200*$tamanhos[1])/$tamanhos[0]; 
    if((int)$API_CONFIG_IMAGE_CART>0) $img_prd = get_image_SRC("sysimages/pack.png", 200, $API_CONFIG_IMAGE_CART, 3);
    else $img_prd = get_image_SRC("sysimages/pack.png", 200, $cal, 3);
            

    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);
    
    set_status(3,$arr);
    $eComm->addToBasket($arr);


    if((int)$B2B==0){
    
        ob_start();
        $string = tracking_from_tag_manager('addToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'],  "SKU_PRODUTO" => $row['sku'], "SKU_GROUP" => $arr['sku_group'], "SKU_FAMILY" => $arr['sku_family'], "VALOR_PRODUTO" => $row['preco'], "DESCRICAO_PRODUTO" => "" , "NOME_PRODUTO" => cms_real_escape_string($row['nome'.$LG]) ));
        echo $string;
        ob_clean();
        
        # Conversão do Facebook por API ***************************************************************************************************
        $show_cp_2 = $_SESSION['EC_USER']['id']>0 ? $_SESSION['EC_USER']['cookie_publicidade'] : (!isset($_SESSION['plg_cp_2']) ? '1' : $_SESSION['plg_cp_2'] );
        if( (int)$CONFIG_TEMPLATES_PARAMS['facebook_pixel_send_all_events'] == 1 && trim($CONFIG_TEMPLATES_PARAMS['facebook_pixel_access_token']) != '' && $show_cp_2 == 1 ){
            
            $event_id = md5('add_cart'.session_id().time());
    
            $capi_user_info = get_capi_user_info();
            $event_info     = ['event_time' => time(), 'event_id' => $event_id, 'event' => 'AddToCart', 'user_info' => $capi_user_info, 'custom_info' => $arr];
        
            setFacebookEventOnRedis("CAPI_EVENT_".$event_id, $event_info);
    
            $resp['a_id'] = $event_id;
    
        }
        
    }
    

    $resp=array();
    $resp['cart'] = OBJ_cart();
    $resp['cart']['product_id_add'] = $arr['pid'];
    
    $resp['product_id_add'] = $row['sku']; #usado para o getRecommendation
    
    $data = serialize($resp['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;
    
    
    # Dashboard Tracking ***************************************************************************************************
    require_once '../plugins/tracker/funnel.php';
    $Funnel = new Funnel();
    $Funnel->event(1);
    
    return serialize($resp);
}

?>
