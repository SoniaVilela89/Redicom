<?

function _setUserGeoLimitedZipCode(){

    global $COUNTRY, $MARKET, $userID;

    $DATA = $_POST;
    
    foreach( $DATA as $k => $v ){
    
        if( is_array($v) ) continue;
            
        $DATA[$k] = safe_value(utf8_decode($v));
        
    }  
    
    $pid = (int)$DATA['pid'];
    
    $zip_code = trim($DATA['zip']);
    
    $remove_lines = (int)$DATA['rm'];
    
    $prod = call_api_func("get_line_table", "registos", "id='".$pid."'");
    
    if( trim($zip_code) != '' && $prod['generico30'] != "" ){
    
        $geo_zip = preg_replace("/[^0-9]/", "", $zip_code);
    
        $sql_express = "SELECT ec_exp.id
                          FROM ec_shipping_express ec_exp
                            INNER JOIN ec_shipping ec
                                ON ec.id=ec_exp.id_shipping
                                  AND ec.id IN(".$prod['generico30'].") 
                                  AND ec.id IN(".$MARKET['metodos_envio'].") 
                                  AND ec.express=1 
                                  AND ec.geo_limited = 1
                            INNER JOIN registos_stocks rs
                              	ON rs.iddeposito = ec_exp.id_deposito 
                                  AND rs.sku = '".$prod['sku']."' 
                                  AND ((rs.stock-rs.margem_seguranca)>0 OR rs.venda_negativo=1 OR rs.produto_digital=1)
                          WHERE ec_exp.codpostal_inicio <= '".$geo_zip."' 
                              AND ec_exp.codpostal_fim >= '".$geo_zip."' 
                              AND ec_exp.id_pais='".$COUNTRY['id']."'
                              AND ec.tipo_envio=97
                          LIMIT 1";  
        $has_geo_zip = cms_fetch_assoc( cms_query($sql_express) );
        if( (int)$has_geo_zip['id'] > 0 ){
            
            $errors = 0;
            
            $res_lines_basket = cms_query("SELECT id, pid
                                            FROM ec_encomendas_lines 
                                            WHERE id_cliente='".$userID."' 
                                              AND status='0' 
                                              AND tipo_linha=4
                                            GROUP BY pid");  
            if( cms_num_rows($res_lines_basket) > 0 ){
                
                while( $row = cms_fetch_assoc($res_lines_basket) ){
                    
                    $pid = end( explode("|||", $row['pid']) );
        
                    $prod = call_api_func("get_line_table", "registos", "id='".$pid."'");
        
                    $sql_express = "SELECT ec_exp.id
                                      FROM ec_shipping_express ec_exp
                                        INNER JOIN ec_shipping ec
                                          ON ec.id=ec_exp.id_shipping 
                                              AND ec.id IN(".$prod['generico30'].")
                                              AND ec.id IN(".$MARKET['metodos_envio'].")  
                                              AND ec.geo_limited = 1
                                        INNER JOIN registos_stocks rs
                                      	  ON rs.iddeposito = ec_exp.id_deposito 
                                            AND rs.sku = '".$prod['sku']."' 
                                            AND ((rs.stock-rs.margem_seguranca)>0 OR rs.venda_negativo=1 OR rs.produto_digital=1)
                                    WHERE ec_exp.codpostal_inicio <= '".$geo_zip."' 
                                          AND ec_exp.codpostal_fim >= '".$geo_zip."' 
                                          AND ec_exp.id_pais='".$COUNTRY['id']."'
                                          AND ec.tipo_envio=97
                                    LIMIT 1";  
                    $has_geo_zip = cms_fetch_assoc( cms_query($sql_express) );
                    if( (int)$has_geo_zip['id'] == 0 ){
                        
                        if( $remove_lines == 1 ){
                            cms_query("DELETE FROM ec_encomendas_lines WHERE pid = '".$row['pid']."' AND id_cliente='".$userID."' AND status='0' AND tipo_linha=4");    
                        }else{
                            $errors++;
                        }
                        
                    }
                        	
                }    
                                
            }
                
            if( $errors == 0 ){    
                
                createCookie("USER_ZIP", base64_encode($zip_code), "31536000");
                
                $response = array( "status" => "1" );
                if( $remove_lines == 1 ) $response = array( "status" => "2" );
                
                return serialize( $response );  
            
            }else{
            
                return serialize( array( "status" => "-1", "errors" => $errors ) );
                 
            }
            
        }
    
    }
    
    return serialize( array( "status" => "0" ) );
    
}

?>
