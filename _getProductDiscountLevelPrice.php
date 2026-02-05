<?

function _getProductDiscountLevelPrice($qty_in_cart, $product, $catalog_id=0){

    global $MOEDA, $B2B;
        
    if ((int)$qty_in_cart == 0){
        $qty_in_cart = (int)params('qty_in_cart');
        $product     = trim(params('product'));
        $catalog_id  = (int)params('catalog_id');
    }

    if((int)$qty_in_cart<0 || trim($product) == ''){
        return serialize(['error' => 1, 'mesage' => 'Invalid parameters'] );
    }

    if ((int)$qty_in_cart == 0){
        $qty_in_cart = 1; #Force to check the base price for the poduct
    }

    $v = call_api_func("get_line_table", "registos", "sku='".$product."'");


    preparar_regras_carrinho($catalogo_id);
    
    
    $preco     = __getPrice($product, 0, 0, $v, 0, '', (int)$qty_in_cart);
    
    $price_rrp = get_price_pvpr($v, $preco['precophp']);

    $valor_unidade = array();
    if($v['sales_unit']>0 && $v['units_in_package']>0){
        $preco_unit = call_api_func('__getPriceUnitSale', $v, $preco);
        if((int)$v['package_price_auto']==1 && (int)$v['units_in_package']>1){
            $preco_unit['precophp'] = $preco['precophp']/(int)$v['units_in_package'];   
        }
        $valor_unidade  = call_api_func('OBJ_money', $preco_unit['precophp'], $MOEDA['id']);
    }

    $arr                             = [];
    // $arr['line_price']               = $preco;
    $arr['price']                    = call_api_func('OBJ_money',$preco['precophp'], $MOEDA['id']);
    $arr['previous_price']           = call_api_func('OBJ_money',$preco['preco_riscado'], $MOEDA['id']);
    $arr['price_discount']           = $preco['desconto'];
    $arr['price_discount_value']     = call_api_func('OBJ_money',$preco['desconto_valor_php'], $MOEDA['id']);
    // $arr['price_discount_init_date'] = $preco['data_inicio'];
    // $arr['price_discount_end_date']  = $preco['data'];
    // $arr['price_discount_is_sales']  = (int)$preco['saldos'];
    // $arr['price_rrp']                = call_api_func('OBJ_money',(float)$price_rrp, $MOEDA['id']);

    // if( (int)$preco['show_countdown'] == 1 ){
    //     $arr['price_discount_show_countdown'] = 1;
    //     $arr['price_discount_end_time'] = $preco['end_time'];
    // }

    if((int)$B2B > 0) $arr['price_base']                = call_api_func('OBJ_money', $preco['preco_base'], $MOEDA['id']);
    $arr['price_discount_base']       = $preco['desconto_base'];
    $arr['price_discount_value_base'] = call_api_func('OBJ_money', $preco['desconto_valor_base'], $MOEDA['id']);
    $arr['price_discount_value_base'] = call_api_func('OBJ_money', $preco['desconto_valor_base'], $MOEDA['id']);
    $arr['unit_value']                = $valor_unidade;
    
    $arr['markup']                    = call_api_func("get_markup_from_price", $preco['precophp'], $price_rrp);

    return serialize($arr);
}


?>
