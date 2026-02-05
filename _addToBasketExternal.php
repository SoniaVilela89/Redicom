<?

#Usado no oms para desdobrar o pack em linhas de produtos

function _addToBasketExternal($sku=null, $id_enc=null, $id_pack=null, $position_prod=0, $qnt=1)
{

    if(is_null($sku)){
        $sku = params('sku');
        $id_enc = params('id_enc');
        $id_pack = params('id_pack');
        $position_prod = params('position_prod');
        $qnt = params('qnt');
    }
    
    # 03-03-2022 - Utilizado por causa das / nos SKUs da GMS
    if( base64_encode( base64_decode($sku, true) ) === $sku ){
        $sku = base64_decode($sku);
    }
    
    if((int)$qnt<1) $qnt = 1;

    global $userID;
    global $eComm;
    global $LG;
    global $COUNTRY;
    global $MARKET;
    global $fx;
    global $sslocation;
    
    $sql_enc    = "SELECT * FROM ec_encomendas_lines WHERE id='$id_enc' LIMIT 0,1";
    $res_enc    = cms_query( $sql_enc );
    $encomenda  = cms_fetch_assoc($res_enc);

    $priceList  = $encomenda['lista_preco'];

    $q = "SELECT registos.*,registos_precos.preco 
          FROM registos
            $JOIN
            INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
          WHERE registos.sku='$sku'
            AND registos_precos.idListaPreco='".$priceList."'
            AND registos_precos.preco>0
          GROUP BY registos.id    
          LIMIT 0,1";
          

    $sql  = cms_query($q);
    $prod = cms_fetch_assoc($sql);

    $cor  = getColor($prod['cor'],$prod['sku']);

    if($prod['material']>0){
        $matR = getMaterial($prod['material']);

        $cor['short_name'] = $matR['name'].' '.$cor['short_name'];
        $cor['long_name'] = $matR['name'].' '.$cor['long_name'];

        if($cor['image']['alt']!=''){
            $cor['image']['alt'] = $matR['name'].' '.$cor['image']['alt'];
        }
    }

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

   if($encomenda["info_pack"]!=""){
        $pack = unserialize($encomenda["info_pack"]);
    }else{
        $q_pack = "SELECT * FROM registos_pack WHERE id='".$id_pack."' LIMIT 0,1";
        $sql_pack = cms_query($q_pack);
        $pack = cms_fetch_assoc($sql_pack);
    }
    
    if($position_prod>0){
        if($position_prod==1){
            $preco = $pack["preco"]/$pack["qtd1"];
            $preco_old = $pack["preco_old"]/$pack["qtd1"];
        }else{        
            $preco = $pack["preco".$position_prod]/$pack["qtd".$position_prod]; 
            $preco_old = $pack["preco".$position_prod."_old"]/$pack["qtd".$position_prod];
        }       
    }else{
        if($pack["artigo1"]==$sku){
            $preco = $pack["preco"];
        }elseif($pack["artigo2"]==$sku){
            $preco = $pack["preco2"];
        }elseif($pack["artigo3"]==$sku){
            $preco = $pack["preco3"];
        }elseif($pack["artigo4"]==$sku){
            $preco = $pack["preco4"];
        }   
    }
    
    if($pack["type"]==1){
        #$pack["artigo2"] - guarda o número de produtos
        $preco = $pack["preco"]/$pack["artigo2"];
    }

    $arr = array();
    $arr = $encomenda;
    unset($arr['id']);
    unset($arr['deposito_cativado']);
    $arr['page_id'] = $encomenda["page_id"];
    $arr['page_cat_id'] = $encomenda["page_cat_id"];
    $arr['page_count'] = $encomenda["page_count"];
    $arr['id_cliente'] = $encomenda["id_cliente"];
    $arr['status'] = $encomenda["status"];
    
    $arr['pid'] = $prod['id'];
    $arr['ref'] = $prod['sku'];
    $arr['sku_family'] = $prod['sku_family'];
    $arr['sku_group'] = $prod['sku_group'];
    $arr['nome'] = strip_tags($prod['desc'.$LG]);
    $arr['unidade_portes'] = (float)$prod['weight'];
    $arr['unidade_portes'] = $encomenda["unidade_portes"];
    
    $arr['composition'] = "";
    $arr_compositon = array();
    if( $prod['units_in_package']>1 ) $arr_compositon[] = estr(206).' '.$prod['units_in_package'].' '.estr(207); 
    if( trim($prod['variante'])!='' ) $arr_compositon[] = $prod['variante']; 
    $arr['composition'] = implode(' - ', $arr_compositon);

    $arr['cor_id'] = $cor['id'];
    $arr['cor_cod'] =  $cor['color_code'];
    $arr['cor_name'] = $cor['long_name']; 
    $arr['peso'] = $prod['peso'];
    $arr['tamanho'] = $sizes['nome'];
    $arr['qnt'] = "1";


    $arr['data'] = date("Y-m-d");
    $arr['datahora'] = date("Y-m-d H:i:s");

    
    $arr['valoruni'] = $preco;
    $arr['valoruni_anterior']   = $preco_old;

    $valor_desconto = 0;
    if($preco_old > 0) $valor_desconto = $preco_old-$preco;
    $arr['valoruni_desconto']   = $valor_desconto;

    $arr['pais_iso'] = $encomenda["pais_iso"];    
    $arr['moeda'] = $encomenda["moeda"];
    $arr['taxa_cambio'] = $encomenda["taxa_cambio"];
    $arr['moeda_simbolo'] = $encomenda["moeda_simbolo"];
    $arr['moeda_prefixo'] = $encomenda["moeda_prefixo"];
    $arr['moeda_sufixo']  = $encomenda["moeda_sufixo"];

    $arr['moeda_decimais']  = $encomenda['moeda_decimais'];
    $arr['moeda_casa_decimal']  = $encomenda['moeda_casa_decimal'];
    $arr['moeda_casa_milhares']  = $encomenda['moeda_casa_milhares'];

    $arr['mercado'] = $encomenda["mercado"];
    $arr['deposito'] = $encomenda["deposito"];    
    $arr['lista_preco'] = $priceList;
    $arr['promotion'] = $encomenda["promotion"];
    $arr['tracking_campanha_url_id'] = $encomenda["tracking_campanha_url_id"];
    $arr['email'] = $encomenda["email"];
    $arr['idioma_user'] = $encomenda["idioma_user"];
    $arr['pais_cliente'] = $encomenda["pais_cliente"];
    $arr['pack'] = "2";
    
    
    $arr['width'] = (float)$prod['width'];
    $arr['height'] = (float)$prod['height'];
    $arr['lenght'] = (float)$prod['lenght'];
    
    
    if($encomenda['iva_taxa_id']==4) $prod['iva'] = 4;
    
    $arr['iva_taxa_id'] = $prod['iva'];

    $arr['oferta'] = $pack["oferta".$position_prod]; 

    $img_prd = get_image_SRC($img_selected, 300, 300, 3);

    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    
    $TABELA = "ec_encomendas_lines";
    $Record = $arr;
    $i=0;
    for($i=1;$i<=$qnt;$i++)
    {
        $f=array();
        $v=array();

        foreach ($Record as $campo=>$valor){
            $f[] = "$campo";
            $v[] = "'".safe_value($valor)."'";
        }

        $SQL = "INSERT INTO $TABELA (" . implode(",",$f) . ") VALUES (". implode(",",$v) .")";
        
        cms_query($SQL);
    }

    return serialize(array("1"));
}

?>
