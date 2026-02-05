<?
function _setTypeClient($type=null){

    $mercado_array = $_SESSION['_MARKET'];
    
    $type = (int)params('type');
    
    if ($type > 0){
        $type = (int)$type;
    }else{
        $type = (int)params('type');
    }
    
    if($type==1){
        if($mercado_array["lista_exclusiva1"]>0){
            $_SESSION['_MARKET']['lista_preco'] = $mercado_array["lista_exclusiva1"];
        }        
    }else{
        $mercado = call_api_func('get_line_table', 'ec_mercado', "id='".$mercado_array["id"]."'");        
        $_SESSION['_MARKET']['lista_preco'] = $mercado["lista_preco"];
    }
    
    $_SESSION["show_type_user"] = 1;
    $_SESSION["type_user"] = $type;
    
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
    
}
?>
