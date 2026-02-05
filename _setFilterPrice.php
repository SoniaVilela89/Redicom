<?
function _setFilterPrice(){

    global $CONFIG_NOHASH_FILTROS;
    
    if(!isset($_POST['filter']['max']) || !isset($_POST['filter']['min']) ){
        header('HTTP/1.0 400 Bad Request');
        echo "<script>location='$slocation'</script>";
        exit;            
    }

    $_SESSION['filter_active'][$_POST['page_id']]['preco_max'] = $_POST['filter']['max'];
    $_SESSION['filter_active'][$_POST['page_id']]['preco_min'] = $_POST['filter']['min'];

    $arr = array();
    $arr[] = 1;
    
    if($CONFIG_NOHASH_FILTROS==1){
        $arr['filters_encode_raw'] = encodeFiltersRaw($_POST['page_id']);    
    }else{                                                  
        $arr['filters_encode'] = encodeFilters($_POST['page_id']);
    }    
    
    return serialize($arr);
}
?>
