<?
function _addToComparator(){

    $pid = params('pid');

    global $userID;
    global $eComm;
    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $MOEDA;
    global $fx;
    global $sslocation;
    global $COMPARATOR_RESTRICTION_KEY;

    $prods_added = [];

    if( trim( $COMPARATOR_RESTRICTION_KEY ) != '' ){
        $s = "SELECT `registos_comparador`.`pid`, `registos`.`".$COMPARATOR_RESTRICTION_KEY."` as `comparator_key` FROM `registos_comparador` JOIN `registos` ON `registos_comparador`.`pid`=`registos`.`id` WHERE `id_cliente`='".$userID."' and `status`='0'";
    }else{
        $s = "SELECT * FROM `registos_comparador` WHERE `pid`='".$pid."' and `id_cliente`='".$userID."' and `status`='0' LIMIT 1";
    }
    
    
    $q = cms_query($s);
    while( $prod_comp = cms_fetch_assoc($q) ){
        if( $prod_comp['pid'] == $pid ){
            return serialize(array("msg"=>"-1"));
        }

        $prods_added[] = $prod_comp;

    }


    $priceList = $MARKET['lista_preco'];
    
    
    # Lista de Empresas
    if($_SESSION['_MARKET']['lista_exclusiva1']>0){
        $mercad = call_api_func('get_line_table', 'ec_mercado', "id='".$_SESSION['_MARKET']["id"]."'");         
        $priceList = $mercad["lista_preco"];
        
        
        if($mercad['entidade_faturacao']>0){    
            $entidade_r = call_api_func('get_line_table', 'ec_invoice_companies', "id='".$mercad["entidade_faturacao"]."'");         
        }
        
        # 2020-09-03
        # Se cliente Empresa com NIF validado, finaliza checkout com lista de preços sem IVA se paisfor diferente da entidade faturadora
        if((int)$_SESSION['EC_USER']['tipo_utilizador']==1 && (int)$_SESSION['EC_USER']['nif_validado']==1 && ($entidade_r['id']>0 && $_SESSION['_COUNTRY']['id']!=$entidade_r['country']) ){
            $priceList = $mercad["lista_exclusiva1"];           
        }
    }
    
    $or_price_quote = "";    
    if( (int)$GLOBALS["REQUEST_QUOTE"] == 1 ){        
        $or_price_quote = " OR registos_precos.preco=0";    
    }
    


    $q = "SELECT registos.*,registos_precos.preco 
              FROM registos
                  INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
              WHERE activo='1'
                AND registos.id='$pid'
                AND registos_precos.idListaPreco='".$priceList."'
                AND (registos_precos.preco>0 $or_price_quote)
                AND (registos_precos.data<=CURDATE() OR registos_precos.data IS NULL)
              GROUP BY registos.id    
              LIMIT 1";

    $sql  = cms_query($q);
    $prod = cms_fetch_assoc($sql);

    if( trim( $COMPARATOR_RESTRICTION_KEY ) != '' && count( $prods_added ) > 0 ){
        
        foreach( $prods_added as $prod_comparator ){
            if( $prod_comparator['comparator_key'] != $prod[ $COMPARATOR_RESTRICTION_KEY ] ){
                return serialize(array("msg"=>"-2"));
            }
        }

    }


    $cor = getColor($prod['cor'],$prod['sku']);
    $sizes = getTamanho($prod['tamanho']);
    $imagens = getImagens($prod['sku'], $prod['sku_group'], $prod['sku_family']);

    $img_list_0 = '';
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU/".$prod['sku']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU/".$prod['sku']."_0.jpg";
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU_GROUP/".$prod['sku_group']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU_GROUP/".$prod['sku_group']."_0.jpg";
    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images_prods_static/SKU_FAMILY/".$prod['sku_family']."_0.jpg") ) $img_list_0 = "images_prods_static/SKU_FAMILY/".$prod['sku_family']."_0.jpg";

    if($img_list_0!='') {
        $img_selected = $img_list_0;
    } else {
        $img_selected = reset($imagens);
    }

    $preco = __getPrice($prod['sku'], $priceList, 0, $prod);


    $arr = array();
    $arr['id_cliente'] = $userID;
    $arr['status'] = "0";
    $arr['pid'] = $prod['id'];
    $arr['ref'] = $prod['sku'];
    $arr['sku_family'] = $prod['sku_family'];
    $arr['sku_group'] = $prod['sku_group'];
    $arr['nome'] = $prod['desc'.$LG];
    $arr['unidade_portes'] = $prod['weight'];
    $arr['composition'] = "";
    $arr['cor_id'] = $cor['id'];
    $arr['cor_cod'] =  $cor['color_code'];
    $arr['cor_name'] = $cor['long_name'];
    $arr['largura'] = "";
    $arr['altura'] = "";
    $arr['peso'] = $prod['peso'];
    $arr['tamanho'] = $sizes['nome'];
    $arr['qnt'] = 1;
    $arr['valoruni'] = $preco['precophp'];
    $arr['pais_iso'] = $_SESSION['_COUNTRY']['country_code'];
    $arr['moeda'] = $MOEDA['id'];
    $arr['taxa_cambio'] = $MOEDA['cambio'];
    $arr['moeda_simbolo'] = $MOEDA['abreviatura'];
    $arr['moeda_prefixo'] = $MOEDA['prefixo'];
    $arr['moeda_sufixo']  = $MOEDA['sufixo'];

    $arr['moeda_decimais']  = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares']  = $MOEDA['casa_milhares'];

    $arr['mercado'] = $MARKET['id'];
    $arr['deposito'] = $MARKET['deposito'];
    $arr['lista_preco'] = $priceList;
    if( $preco['desconto_valor']==1 )
    $arr['promotion'] = 1;


    $img_prd = get_image_SRC($img_selected, 300, 300);


    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);


    $eComm->addToComparator($arr);

    $resp=array();
    $resp['comparator'] = OBJ_lines(0, 4);

    return serialize($resp);


}

?>
