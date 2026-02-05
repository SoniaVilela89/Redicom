<?
function _addPackToBasketCatalog($id=null, $cat=null, $pack_id=0, $ids=null, $qtd=null)
{
    if($pack_id==0){
        $id         = params('id');
        $cat        = params('cat');        
        $pack_id    = (int)params('pack_id');
        $ids        = params('ids');
        $qtd        = params('qtd');
    }    
    
    global $userID;
    global $eComm, $LG, $fx, $sslocation, $B2B;
    global $COUNTRY, $MARKET, $MOEDA;
    
    if((int)$MARKET['hide_venda']>0){
        $resp = array();
        $resp['cart'] = OBJ_cart(true);
        
        $data = serialize($resp['cart']);
        $data = gzdeflate($data,  9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);
        $_SESSION["SHOPPINGCART"] = $data;
        
        return serialize($resp);
    }
    
    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);
    
    $sql_pack = "SELECT * FROM registos_pack WHERE `dodia`<=CURDATE() AND `aodia`>=CURDATE() AND artigo1!='' AND moeda='".$MOEDA["id"]."' AND type=1 AND id='".$pack_id."' LIMIT 0,1";
    $res_pack = cms_query($sql_pack);
    $row_pack = cms_fetch_assoc($res_pack);
    
    if((int)$row_pack['id']==0){
        $resp = array();
        $resp['cart'] = OBJ_cart(true);
        
        $data = serialize($resp['cart']);
        $data = gzdeflate($data,  9);
        $data = gzdeflate($data, 9);
        $data = urlencode($data);
        $data = base64_encode($data);
        $_SESSION["SHOPPINGCART"] = $data;
        
        return serialize($resp);
    }
    
    if( $qtd<=0 ) $qtd=1;
    
    $arr = array();
    $arr['page_id'] = $id;
    $arr['page_cat_id'] = 0;
    $arr['id_cliente'] = $userID;
    $arr['status'] = "0";
    $arr['pid'] = $row_pack['sku'];
    $arr['ref'] = $row_pack['sku'];
    $arr['sku_family'] = $row_pack['sku'];
    $arr['sku_group'] = $row_pack['sku'];
    $arr['nome'] = $row_pack['nome'.$LG];
    $arr['unidade_portes']=1;
    
    $arr_pids = array();
    $arr_skus = array();
    
    $arr_pids = explode(",", $ids);
    foreach($arr_pids as $v){
        $sql_prod = "SELECT sku FROM registos WHERE id='$v' LIMIT 0,1";
        $res_prod = cms_query($sql_prod);
        $row_prod = cms_fetch_assoc($res_prod);
        if(trim($row_prod["sku"])!="") $arr_skus[] = $row_prod["sku"];
    }
    
    $arr['composition'] = implode(' + ', $arr_skus);
    
    $arr['cor_id'] = "";
    $arr['cor_cod'] =  "";
    $arr['cor_name'] = "";
    $arr['peso'] = "";
    $arr['tamanho'] = "";
    if($qtd>999){
        $qtd = 999;
    }
    $arr['qnt'] = $qtd;
    $arr['data'] = date("Y-m-d");
    $arr['datahora'] = date("Y-m-d H:i:s");
    $arr['valoruni'] = $row_pack['preco'];
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
    $arr['lista_preco'] = $MARKET['lista_preco'];
    
    $arr_cookie_cpn = getCookieCPN();
    
    $arr['tracking_campanha_url_id'] = implode(',', array_keys($arr_cookie_cpn));
    $arr['email'] = $_SESSION['EC_USER']['email'];
    $arr['idioma_user'] = $LG;
    $arr['pais_cliente'] = $_SESSION['_COUNTRY']['id'];
    $arr['pack'] = "1";
    
    $row_pack["pids"] = $ids;
    $row_pack["skus"] = implode(",", $arr_skus);
    $arr['info_pack'] = serialize($row_pack);     

    $img_prd = get_image_SRC("sysimages/pack.png", 300, 300, 3);

    $arr['image'] = $sslocation.str_ireplace("../", "/", $img_prd);

    set_status(3,$arr);
    $eComm->addToBasket($arr);



    if((int)$B2B==0){
    
        ob_start();
        $tag_manager = tracking_from_tag_manager('addToCart', $COUNTRY['id'], array("SITE_LANG" => $LG, "UTILIZADOR_EMAIL" => $_SESSION['EC_USER']['email'],  "SKU_PRODUTO" => $row_pack['sku'], "ID_PRODUTO" => $row_pack['id'], "SKU_GROUP" => $row_pack['sku'], "SKU_FAMILY" => $row_pack['sku'], "VALOR_PRODUTO" => $row_pack['preco'], "DESCRICAO_PRODUTO" => "" , "NOME_PRODUTO" => cms_real_escape_string($row_pack['nome'.$LG]), "MOEDA" => $MOEDA['abreviatura']  ));
        
        #$eComm->getRappel();
    } 
    
    
    $resp=array();
    $resp['cart'] = OBJ_cart(true);
    
    $data = serialize($resp['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;
    
    $resp['trackers'] = base64_encode($tag_manager);    
   
    
    
    # Dashboard Tracking ***************************************************************************************************
    require_once '../plugins/tracker/funnel.php';
    $Funnel = new Funnel();
    $Funnel->event(1);

     

    return serialize($resp);

}
