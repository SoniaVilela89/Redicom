<?

function _getAccountDownload($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $download_orders  = array();
    $download_orders  = getDownloadsOrders($userID);
    
    
    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['download_ordes'] = $download_orders;
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    return serialize($arr);

}

function getDownloadsOrders($userid){   
    
    global $slocation;

    
    $sql = "SELECT ec_l.*, ec_c.order_ref, ec_c.data as data_enc 
                          FROM ec_encomendas_lines ec_l
                                INNER JOIN ec_encomendas ec_c ON ec_l.order_id=ec_c.id AND ec_c.pagref!='' AND ec_c.tracking_status!='100'        
                          WHERE ec_l.id_cliente='".$userid."' AND ec_l.ref!='PORTES' AND ec_l.unidade_portes=0 AND ec_l.egift=0 
                          ORDER BY ec_c.data";

    $_arr_lines = array();
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        $sql_file = "SELECT * FROM registos_stocks WHERE sku='".$row["ref"]."' AND iddeposito IN(".$row["deposito"].") AND produto_digital=1 LIMIT 0,1";
        $res_file = cms_query($sql_file);
        $row_file = cms_fetch_assoc($res_file);
        
        $file_exist = 0;
        if(file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/DOWNLOADS/".$row_file["id"].".pdf")){
           $file_exist = 1; 
        }
        
        $row["product_info"] = call_api_func('get_product', $row["pid"], '',$row["page_id"], 0, 1);  
        $row["link_file"] = base64_encode($userid."|||".$row["id"]."|||".$row["email"]);  
        $row["file_exist"] = $file_exist;         
        $_arr_lines[] = $row;
    }

    return $_arr_lines;
}
?>
