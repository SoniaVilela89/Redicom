<?

function _getGeoLimitedDeliveryProductInfo($product_id=0, $zip=''){

    global $LG, $MARKET, $COUNTRY;
    
    if ( (int)$product_id > 0 ){
       $product_id = (int)$product_id;
       $zip = trim( safe_value($zip) );       
    }else{
       $product_id = (int)params('product_id');
       $zip = trim( safe_value( params('zip') ) );       
    }
    
    $product = call_api_func("get_line_table","registos", "id='".$product_id."'");
    
    $response = array();
      
    if( $product['generico30'] != "" ){
    
        $expressions = get_expressions();
        
        if( isset($_COOKIE["USER_ZIP"]) ){
            
            $cookie_zip = base64_decode($_COOKIE["USER_ZIP"]);
            
            $decoded_zip = $cookie_zip;
            if( $zip != '' ) $decoded_zip = $zip;

            $geo_zip = preg_replace("/[^0-9]/", "", $decoded_zip);       
    
            $sql_express = "SELECT ec_exp.id, ec.click_collect_theme AS theme
                              FROM ec_shipping_express ec_exp
                                INNER JOIN ec_shipping ec
                                  ON ec.id=ec_exp.id_shipping 
                                      AND ec.id IN(".$product['generico30'].")
                                      AND ec.id IN(".$MARKET['metodos_envio'].")  
                                      AND ec.geo_limited = 1
                                INNER JOIN registos_stocks rs
                              	  ON rs.iddeposito = ec_exp.id_deposito 
                                      AND rs.sku = '".$product['sku']."' 
                                      AND ((rs.stock-rs.margem_seguranca)>0 OR rs.venda_negativo=1 OR rs.produto_digital=1)
                            WHERE ec_exp.codpostal_inicio <= '".$geo_zip."' 
                                  AND ec_exp.codpostal_fim >= '".$geo_zip."' 
                                  AND ec_exp.id_pais='".$COUNTRY['id']."'
                                  AND ec.tipo_envio=97
                            LIMIT 1";  
            $has_geo_zip = cms_fetch_assoc( cms_query($sql_express) );
        
        }    
        
        if( (int)$has_geo_zip['id'] > 0 ){
                
            $response["info"]["description"]      = $expressions['587'];
            $response["info"]["url_text"]         = $expressions['588'];
            
            $response["available"]                = 1;
            $response["zip"]                      = $cookie_zip;
            
        }else{
            
            $sql_express = "SELECT ec_exp.id, ec.click_collect_theme AS theme
                              FROM ec_shipping_express ec_exp
                                INNER JOIN ec_shipping ec
                                  ON ec.id=ec_exp.id_shipping 
                                      AND ec.id IN(".$product['generico30'].")
                                      AND ec.id IN(".$MARKET['metodos_envio'].")  
                                      AND ec.geo_limited = 1
                            WHERE ec_exp.id_pais='".$COUNTRY['id']."'
                                  AND ec.tipo_envio=97
                            LIMIT 1";          
            $has_geo_zip = cms_fetch_assoc( cms_query($sql_express) );
            
            if( (int)$has_geo_zip['id'] > 0 ){
            
                $response["info"]["description"]      = ( isset($_COOKIE["USER_ZIP"]) ) ? $expressions['593'] : $expressions['585'];
                $response["info"]["url_text"]         = ( isset($_COOKIE["USER_ZIP"]) ) ? $expressions['588'] : $expressions['586'];
                
                $response["available"]                = 0; 
                $response["zip"]                      = ( trim($decoded_zip) != "" ) ? $decoded_zip : $cookie_zip;
                            
            }
             
        }
        
    }
    
    return serialize($response);

}

?>
