<?
function _removeFromComparator(){

    $pid = params('pid');

    global $userID;

    $add_where = '';
    if($pid>0) {
        $product_temp = call_api_func("get_line_table", "registos", "id='".$pid."'");
        if((int)$product_temp['id'] > 0) $add_where = "AND sku_family='".$product_temp['sku_family']."' ";
        else $add_where = " AND pid='$pid' ";
    }


    cms_query("DELETE FROM registos_comparador WHERE id_cliente='$userID' AND status='0' $add_where ");

    $resp = array();
    $resp[] = 1;
    $resp['comparator'] = OBJ_lines(0, 4);

    return serialize($resp);

}
?>
