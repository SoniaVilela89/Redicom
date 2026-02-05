<?

function _setUserShippingExpressZipCode(){

    global $COUNTRY, $MARKET, $userID;

    $DATA = $_POST;
    
    foreach( $DATA as $k => $v ){
    
        if( is_array($v) ) continue;
            
        $DATA[$k] = safe_value(utf8_decode($v));
        
    }  
    
    $zip_code = trim($DATA['zip']);
    
    if( trim($zip_code) == '' ){
        return serialize( array( "status" => "0" ) );
    }

    $payload = array( "status" => "0" );

    $sql_shipping = "SELECT `transportadora_id`
                      FROM `ec_entrega_expresso_mercados`
                      WHERE `mercado_id`=".$MARKET['id']."
                      LIMIT 1";  
    $row_shipping = cms_fetch_assoc( cms_query($sql_shipping) );
    if( (int)$row_shipping['transportadora_id'] > 0 ){

        $shipping_express = 0;
        $redirect = 0;

        $express_zip_code = preg_replace("/[^0-9]/", "", $zip_code);

        if( (int)$DATA['pid'] > 0 ){

            $prod = cms_fetch_assoc( cms_query("SELECT `sku` FROM `registos` WHERE `id`='".(int)$DATA['pid']."'") );
            
            $sql_express = "SELECT GROUP_CONCAT( DISTINCT `registos_stocks`.`iddeposito` ) AS `depositos`
                            FROM `registos_stocks`
                                INNER JOIN `ec_depositos_codigos_postais` ON `ec_depositos_codigos_postais`.`deposito_id` = `registos_stocks`.`iddeposito` 
                                    AND `ec_depositos_codigos_postais`.`cod_postal_inicio` <= '".$express_zip_code."'
                                    AND `ec_depositos_codigos_postais`.`cod_postal_fim` >= '".$express_zip_code."' 
                                    AND `ec_depositos_codigos_postais`.`pais_id`='".$COUNTRY['id']."'
                            WHERE `registos_stocks`.`sku` = '".$prod['sku']."' 
                                    AND ((registos_stocks.stock-registos_stocks.margem_seguranca)>0 OR `registos_stocks`.`venda_negativo`=1 OR `registos_stocks`.`produto_digital`=1)
                                    AND `registos_stocks`.`iddeposito` IN (".$MARKET['deposito'].")
                            LIMIT 1"; 
            $row_express_prod = cms_fetch_assoc( cms_query($sql_express) );

            if( trim($row_express_prod['depositos']) != '' ){
                $shipping_express = 1;
            }else{
                $redirect = 1;
            }
        
        }

        if( (int)$shipping_express == 0 ){

            $sql_express = "SELECT `id`
                            FROM `ec_depositos_codigos_postais`
                            WHERE `cod_postal_inicio` <= '".$express_zip_code."' 
                                AND `cod_postal_fim` >= '".$express_zip_code."' 
                                AND `pais_id`='".$COUNTRY['id']."'
                                AND `deposito_id` IN (".$MARKET['deposito'].")
                            LIMIT 1"; 
                            
            $row_express = cms_fetch_assoc( cms_query($sql_express) );
            
            if( (int)$row_express['id'] > 0 ){    
                $shipping_express = 2;  
            }

        }

        if( (int)$shipping_express > 0 ){

            if( trim($_COOKIE['SYS_EXP_ZIP']) != '' && $zip_code != base64_decode($_COOKIE['SYS_EXP_ZIP']) ){
                cms_query("DELETE FROM `ec_encomendas_lines` WHERE `id_cliente`='".$userID."' AND `status`='0' AND `tipo_linha`='7' AND `obs`!='".$zip_code."'");
            }

            createCookie("SYS_EXP_ZIP", base64_encode($zip_code), "31536000");
                
            $payload = array( "status" => "1" );
            if( (int)$redirect == 1 ){
                $payload['status'] = 2;
            }

        }

    }
    
    return serialize($payload);
       
}

?>
