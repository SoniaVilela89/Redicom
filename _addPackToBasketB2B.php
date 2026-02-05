<?
function _addPackToBasketB2B($id=null, $cat=null, $sku_family=null, $cor=null, $pack_id=null, $qtd=null, $unit_store_ids=0)
{

    if(is_null($sku_family)){
        $id             = (int)params('id');
        $cat            = (int)params('cat');
        $sku_family     = params('sku_family');
        $cor            = (int)params('cor');
        $pack_id        = (int)params('pack_id');
        $qtd            = (int)params('qtd');
        $unit_store_ids = params('unit_store_ids');
    }


    if( $qtd<=0 ) $qtd=1;

    global $userID;
    global $eComm;

    # Qualquer alteração ao carrinho limpa os vauchers,vales,campanhas utilizados
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);
    $eComm->removeInsertedProductsEGift($userID);     
    
    
  

    $pack_s = "SELECT * FROM registos_packs_b2b WHERE id='".$pack_id."' LIMIT 0,1";
    $pack_q = cms_query($pack_s);
    $pack_r = cms_fetch_assoc($pack_q);
    
    if((int)$pack_r['id']<1){
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
    
    
    
    $pack_l_s = "SELECT * FROM registos_packs_lines_b2b WHERE pack_id='".$pack_r['id']."' ";
    $pack_l_q = cms_query($pack_l_s);
                        
    if(cms_num_rows($pack_l_q)<1){
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
    
              
    
    require_once $_SERVER['DOCUMENT_ROOT'].'/api/controllers/_addToBasket.php';
    
    while($pack_l_r = cms_fetch_assoc($pack_l_q)){
        
        $prod_s = "SELECT id FROM registos WHERE sku_family='".$sku_family."' AND cor='".$cor."' AND tamanho='".$pack_l_r['tamanho_id']."' AND activo=1 LIMIT 0,1";
        $prod_q = cms_query($prod_s);
        $prod_r = cms_fetch_assoc($prod_q);
        

        if($prod_r['id']>0){ 
            _addToBasket($id, $cat, (int)$prod_r['id'], ((int)$pack_l_r['qtd']*$qtd), 0, 0, 0, 2, 1, 0, 0, $pack_id, $unit_store_ids);
        }
          
    }
    
                

    $resp=array();
    $resp['cart'] = OBJ_cart(true);

    $data = serialize($resp['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;

    return serialize($resp);
}

?>
