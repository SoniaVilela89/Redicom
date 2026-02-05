<?

function _getAccountOrderRepurchase($order_id=null)
{

    global $userID, $eComm, $LG, $CONFIG_OPTIONS, $MARKET;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    } 
    
    if(is_null($order_id)){
        $order_id = (int)params('order_id');
    } 
    
    $encomenda  = $eComm->getOrder($userOriginalID, $order_id);
    if(empty($encomenda['id'])){
        return serialize(array("0"=>"0"));  
    }

    $page_id_default = 5;
    $enc_lines       = $eComm->getOrderLines($order_id, 1);
    $lines           = array();
                                                      
    foreach ($enc_lines as $v) {

        if($v['id_linha_orig']>0){
            continue;
        }

        #$page_id = $v['page_id']; #Comentado para que o produto seja sempre exibido, independentemente do catálogo ou da página já não existir
        $page_id = $page_id_default;

        #$cat = $v['page_cat_id']; #Comentado para que o produto seja sempre exibido, independentemente do catálogo ou da página já não existir
        if ($v['page_cat_id'] > 0) {
            $catalogo_id = $v['page_cat_id'];
        } else {
            $catalogo_id = $_SESSION['_MARKET']['catalogo'];
        }
        
        if( $v['pack'] == 0 || $v['pack'] == 3 ){

            call_api_func('preparar_regras_carrinho', $catalogo_id);

            if ((int)$GLOBALS["REGRAS_CATALOGO"] == 0 && $catalogo_id != $_SESSION['_MARKET']['catalogo'])
                call_api_func('preparar_regras_carrinho', $_SESSION['_MARKET']['catalogo']);

            $cat  = (int)$GLOBALS["REGRAS_CATALOGO"];
            $prod = call_api_func("get_line_table","registos", "sku='".$v['ref']."'");
            $var  = variantOBJ($prod, $page_id, $cat);
             
            if( (int)$var['package_price_auto'] == 1 && (int)$var['units_in_package'] > 1 ){
                $v['qnt_total'] /= $var['units_in_package'];
            }

            $line_status = 0;
            if( (int)$var['available'] == 0 || $var['price']['value'] <= 0 ) $line_status = 1;

            $line_title = $prod['nome'.$LG];

        }else{

            $info_pack = unserialize($v['info_pack']);
            
            $pack = call_api_func("get_line_table", "`registos_pack`", "`id`=".$info_pack['id']." AND `dodia`<=CURDATE() AND `aodia`>=CURDATE()", "`id`");
            if( (int)$pack['id'] == $info_pack['id'] ){

                $stock_min = 9999;
                $stock_rule = array();

                foreach( $info_pack['quantidades'] as $sku => $qnt ){

                    $arr_stock        = array();
                    $regra_validacao  = array();
                    $depositos_express = "";

                    $stock = __getStock($sku, $MARKET['deposito'], $depositos_express, $arr_stock, $regra_validacao);

                    if( $stock < $stock_min ){
                        $stock_min = $stock;
                        $stock_rule = $regra_validacao;
                    }
                    
                }

                $line_status = 0;

                $var['inventory_quantity']  = $stock_min;
                $var['inventory_rule']      = $stock_rule['inventory_rule'];
                $var['inventory_class']     = $stock_rule['inventory_class'];

            }else{

                $line_status = 1;

                $var['inventory_quantity']  = 0;
                
            }
            
            $var['sku']                     = $v['ref'];
            $var['color']['short_name']     = "";

            $line_title = $v['nome'];
            $line_composition = $v['composition'];

        }

        if( (int)$var['id'] < 1 ){
            $line_status = 1;
            $var['inventory_quantity']  = 0;
        }

        $line                       = [];
        $line['pid']                = $var['id'];
        $line['sku']                = $var['sku'];
        $line['qnt']                = $v['qnt_total'];
        $line['title']              = $line_title;
        $line['image']              = $v['image'];
        $line['color']              = $var['color'];
        $line['size']               = $var['size'];
        $line['size_code']          = $var['size_code'];
        $line['url']                = $var['url'];
        $line['status']             = $line_status;
        $line['inventory_quantity'] = $var['inventory_quantity'];
        $line['inventory_rule']     = $var['inventory_rule'];
        $line['inventory_class']    = $var['inventory_class'];
        $line['replacement_time']   = $var['replacement_time'];
        $line['unit_store']         = (int)$var['col1'];
        $line['composition']        = $line_composition;

        $lines[] = $line;
        
    }
    
    $arr           = [];
    $arr['lines']  = $lines;
    
    $arr['exp497'] = estr(497);
    $arr['exp425'] = estr(425);
    $arr['exp494'] = estr(494);
    $arr['exp495'] = estr(495);
    $arr['exp433'] = estr(433);
    $arr['exp496'] = estr(496);
    $arr['exp434'] = estr(434);
    $arr['exp212'] = estr(212);
    $arr['exp507'] = estr(507);
    $arr['exp576'] = estr(576);
    $arr['exp734'] = estr(734);
    
    if(is_callable('custom_controller_account_order_repurchase')) {
        call_user_func_array('custom_controller_account_order_repurchase', array(&$arr));
    }

    return serialize($arr);

}

?>
