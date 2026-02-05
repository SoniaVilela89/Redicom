<?

function _setAccountDownload($download_file=null)
{

    global $userID;
    global $eComm;
    global $LG;

    if(is_null($download_file)){
        $download_file = (int)params('download_file');
    }
    
    $arr = array();
    
    $file_decode = base64_decode($download_file);
    $arr_file_decode = explode("|||", $file_decode);    
    
    if($userID!=$arr_file_decode[0]){
        return serialize(array("0"=>"0"));   
    }
    
    $sql_line = "SELECT * FROM ec_encomendas_lines WHERE id='".$arr_file_decode[1]."' LIMIT 0,1";
    $res_line = cms_query($sql_line);
    $row_line = cms_fetch_assoc($res_line);
    
    $order = $eComm->getOrder($arr_file_decode[0], $row_line['order_id']);
    if(trim($order["pagref"])=="" || trim($order["tracking_status"])==100){
        return serialize(array("0"=>"0"));     
    }
    
    if($userID!=$order["cliente_final"]){
        return serialize(array("0"=>"0"));     
    }
    
    $sql_file = "SELECT * FROM registos_stocks WHERE sku='".$row_line["ref"]."' AND iddeposito IN(".$row_line["deposito"].") AND produto_digital=1 LIMIT 0,1";
    $res_file = cms_query($sql_file);
    $row_file = cms_fetch_assoc($res_file);
    
    if((int)$row_file["id"]==0){
        return serialize(array("0"=>"0"));    
    }
    
    if(file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/DOWNLOADS/".$row_file["id"].".pdf")!=1){
        return serialize(array("0"=>"0"));
    }
    
    header("Content-type:application/pdf");
    header("Content-Disposition:attachment;filename=".$row_line["ref"].".pdf");
    readfile($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/DOWNLOADS/".$row_file["id"].".pdf");
    
    return serialize($arr);

}
?>