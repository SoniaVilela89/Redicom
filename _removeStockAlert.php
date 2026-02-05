<?
function _removeStockAlert(){

    $stock_id = params('stock_id');

    global $userID;

    cms_query("UPDATE avisos_stock SET estado='3' WHERE id='$stock_id' AND id_cliente='$userID' AND estado='0'");

    $resp = array();
    $resp[] = 1;
    return serialize($resp);

}
?>
