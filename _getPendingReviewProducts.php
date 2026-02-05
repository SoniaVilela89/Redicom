<?
function _getPendingReviewProducts($user_id=null){
   
    
    if ($user_id > 0){
       $user_id = $user_id;
    }else{
       $user_id = params('user_id');
    }
        
    
    $resp = array();
    $resp['purchases'] = array();     
    
    $q = cms_query('SELECT ecl.*,ec.data as enc_data 
                    FROM ec_encomendas_lines ecl 
                        INNER JOIN ec_encomendas ec ON ecl.order_id=ec.id AND ec.pagref<>"" 
                    WHERE status > 0 and id_cliente="'.$user_id.'" and order_id>0 and pack="0" and egift="0" and review_made="0" AND id_linha_orig<1 AND ref<>"PORTES"
                    ORDER BY id desc LIMIT 0,6');
        
    while($row = cms_fetch_assoc($q)){      

        $pid_final = end( explode("|||", $row['pid']) ); 
        
        $temp                 = array();
        $temp['product']      = call_api_func('get_product', $pid_final, '', 5, 0, 1);
             
        $temp['date']         = $row['enc_data'];    
        
        if($temp['product']['id']>0 && $temp['product']['review_product']['allow_review']>0 ) $resp['purchases'][]  = $temp;
        
        if(count($resp['purchases'])>=3) break;

    }
        
    $resp['shop'] = call_api_func('OBJ_shop_mini');

    return serialize($resp);  
}
?>
