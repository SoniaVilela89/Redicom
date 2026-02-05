<?

function _getStoreLocator($country_id=0, $city="", $sku="", $sku_encoded=0){

    if ($country_id==0){
        $country_id     = (int)params('country_id');
        $city           = params('city');
        $sku            = params('sku');
        $sku_encoded    = params('sku_encoded');
    }

    $sku_encoded = (int)$sku_encoded;
    if( $sku_encoded == 1 ){
        $sku = base64_decode($sku);
    }

    $city = utf8_decode($city);
    $sku = utf8_decode($sku);

    $arr = array();
    if(trim($sku)==""){
        $arr["city"]    = call_api_func('get_city_stores_by_country',$country_id);  
        $arr["stores"]  = "";
    }elseif(trim($sku)!="" && trim($city) == '*'){
        
        $num_stores = get_stock_in_store($country_id, $sku);
        
        $arr["stores"]     = "";
        $arr['num_stores'] = $num_stores;
        
        if($num_stores > 0) $arr["city"]    =  call_api_func('get_city_stores_by_country',$country_id);
        
    }else{
        $arr["city"]    =  call_api_func('get_city_stores_by_country',$country_id);
        $arr["stores"]  =  get_inventory_store($country_id, $city, $sku);
    }
    
    
    # 2025-10-08
    # Marques Soares
    if(is_callable('custom_controller_store_locator')) {
        call_user_func_array('custom_controller_store_locator', array(&$arr));
    }
    

    return serialize($arr);

}

function get_stock_in_store($country_id=0, $sku=''){

    $sql = "SELECT ec_l.*, ec_d.id as id_deposit 
                FROM ec_lojas ec_l 
                    INNER JOIN ec_deposito ec_d ON ec_l.id=ec_d.loja AND ec_l.hidden=0
                    INNER JOIN registos_stocks rs ON rs.iddeposito=ec_d.id  AND rs.sku ='".$sku."' AND rs.stock>0
                WHERE ec_l.pais='".$country_id."' AND ec_l.hidden=0
                ORDER BY ec_l.ordem,ec_l.nomept;";
                
    $res  = cms_query($sql);
    $num_row = cms_num_rows($res);
    
    return (int)$num_row;
}

function get_city_stores_by_country($country_id=0){

    $arr_city = array();
    
    
    # 2021-11-12 - knotkits  
    # Inner join para só aparecerem as lojas que têm deposito asociado, proque sem deposito não tem stock obrigatoriamente
    
    $sql = "SELECT ec_l.cidade as city 
                FROM ec_lojas ec_l 
                INNER JOIN ec_deposito ec_d ON ec_l.id=ec_d.loja
            WHERE ec_l.pais='".$country_id."' AND ec_l.hidden=0 
            ORDER BY ec_l.ordem,ec_l.nomept";
                
    $res  = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        $arr_city[$row['city']] = $row['city'];
    }
    
    ksort($arr_city);
    
    return $arr_city;
}

function get_inventory_store($country_id=0, $city="", $sku=""){
    
    global $LG, $MOEDA, $MARKET;
    
    $arr_store = array();
    
    # 2020-09-01
    # Removida validação de só listar lojas associadas aos depositos permitidos para o mercado 
    # AND ec_d.id IN (".$MARKET['deposito'].") 
    
    
    # 2021-11-12 - knotkits  
    # Inner join para só aparecerem as lojas que têm deposito asociado, proque sem deposito não tem stock obrigatoriamente
    
    $sql = "SELECT ec_l.*, ec_d.id as id_deposit 
                FROM ec_lojas ec_l 
                    INNER JOIN ec_deposito ec_d ON ec_l.id=ec_d.loja AND ec_l.hidden=0
                WHERE ec_l.pais='".$country_id."' AND ec_l.cidade LIKE '".$city."' AND ec_l.hidden=0
                ORDER BY ec_l.ordem,ec_l.nomept";
                
    $res  = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
    
        $names = explode(" ", $row['nomept']);
        
        $cam  = "images/loja_".$row['id'].".jpg";
        $cam2 = "sysimages/no-image4.jpg";
        if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
            $image = imageOBJ($row['nomept'],1,$cam);
        }elseif(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam2)){
            $image = imageOBJ($row['nomept'],1, $cam2);
        }
    
        $country = call_api_func('getPais',$row['pais']);
    
        if($row['zona']>0){
            $zona = call_api_func('getZona',$row['zona']);
        }

        $arr_stock = array();
        $sql_stock = "SELECT s.*, e.nome 
                        FROM registos_stocks s 
                            INNER JOIN ec_deposito e ON s.iddeposito=e.id 
                        WHERE sku ='".$sku."' AND iddeposito='".$row["id_deposit"]."' AND stock>0
                        ORDER BY e.ordem, id ASC ";
                        
        $res_stock = cms_query($sql_stock);
        while($row_stock = cms_fetch_assoc($res_stock)){
            $arr_stock[]  = array(
                "iddeposito"      => $row_stock['iddeposito'], 
                "stock"           => $row_stock["stock"],
                "sku"             => $row_stock["sku"],
                "venda_negativo"  => $row_stock['venda_negativo']
            );
        } 
        
        
        if(is_callable('custom_controller_store_locator_stock')){
            $stock = call_user_func('custom_controller_store_locator_stock', $sku, $row["id_deposit"]);
            
            $arr_stock = array();
            if((int)$stock>0){
                $arr_stock[]  = array(
                    "stock" =>  $stock,
                    "sku"   =>  $sku
                );
            }
        }

        $temp = array(
            "id"            => $row['id'],
            "first_name"    => $names[0],
            "last_name"     => ( count($names)>1 ) ? $names[count($names)-1] : "",
            "name"          => $row['nomept'],
            "address1"      => $row['morada'],
            "address2"      => $row['morada2'],
            "street"        => $row['morada']." ".$row['morada2'],
            "zip"           => $row['cp'],
            "city"          => $row['cidade'],
            "country"       => call_api_func('countryOBJ',$country,$LG,$MOEDA['id']),
            "phone"         => $row['tel'],
            "email_address" => $row['email'],
            "fax"           => $row['fax'],
            "schedule"      => $row['horario'],
            "coordinates"   => $row['coordenadas'],
            "image"         => $image,
            "banner"        => call_api_func('OBJ_banner',$row['banner']),
            "website"       => $row['website'],
            "link"          => $row['link'],
            "short_content" => base64_encode($row['desc'.$LG]),
            "stocks"        => $arr_stock
        );      
    
        $arr_store[] = $temp;
     
    }

    return $arr_store;
}

?>
