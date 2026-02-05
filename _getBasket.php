<?

# 2021-01-21
# Retorna as informações do carrinho

function _getBasket(){

    $response = array();
    
    if(isset($_SESSION["SHOPPINGCART"])){
        
        $data = base64_decode($_SESSION["SHOPPINGCART"]);
        $data = urldecode($data);
        $data = gzinflate($data);
        $data = gzinflate($data);
        $data = unserialize($data);
        $response['cart']  = $data;
        
        return serialize($response);
    } 
    
    $response['cart'] = call_api_func('OBJ_cart');
    
    $data = serialize($response['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;
    
    return serialize($response);
    
}
  
?>