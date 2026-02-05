<?
function _getActiveCampaigns($sku_group=null, $price=0){
    global $userID;
    global $eComm;
    global $LG, $fx;
    global $MARKET, $MOEDA, $CACHE_DETALHE, $COUNTRY;
    global $CONFIG_OPTIONS, $CONFIG_ActiveCampaignsIDS, $CACHE_KEY;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if(is_null($sku_group)){
        $sku_group = params('sku_group');
    }
    
    if((int)$price==0){
        $price = (int)params('price');
    }                                 
    
    $campanhas = array();

    $_cacheid = $CACHE_KEY."CMPATV_".$LG.'_'.$MOEDA["id"].'_'.$MARKET["id"];
    
    $dados = $fx->_GetCache($_cacheid, $CACHE_DETALHE);

    if ($dados!=false && $_GET['nocache']!=1){  
           
        $campanhas = unserialize($dados);   
          
    } else{ 
    
        $mais = '';
        if($CONFIG_ActiveCampaignsIDS!='') $mais = " OR id IN ($CONFIG_ActiveCampaignsIDS)";
    
        $sql = " SELECT *   
                  FROM ec_campanhas 
                  WHERE (det_produto_avisos=1 $mais) 
                      AND automation='0' 
                      AND moeda='".$MOEDA["id"]."'
                      AND deleted='0'
                      AND recuperar_carrinho=0
                      AND welcome_gift=0
                      AND NOW() between CONCAT(data_inicio, ' ',hora_inicio, ':00:00') and CONCAT(data_fim, ' ',hora_fim, ':59:59')
                      AND ( ofer_tipo IN (1,7,8,9) OR (ofer_tipo='3' AND ofer_imediato='1') )
                      AND automation='0'
                      AND (concat(',',crit_mercado,',') like ('%,".$MARKET["id"].",%') or crit_mercado = '')";
                      
                                            
             
        $campDetail_sql = cms_query($sql);
        
        while($res = cms_fetch_assoc($campDetail_sql)){

            $res['vals'] = $eComm->getCatalogoRef($res['crit_catalogo']);
            $res['vals_catalogo_aplicar_desconto'] = $eComm->getCatalogoRef($res['crit_catalogo_aplicar_desconto']);                        
            $campanhas[] = $res;
            
        }
                                                  
        $fx->_SetCache($_cacheid, serialize($campanhas), $CACHE_DETALHE);     
    }  
    
          
    if(count($campanhas)==0){
        return serialize(array("campaigns" => array()));
    }    
    


    
    foreach($campanhas as $k => $v){
    
        if ( $v['crit_tipo_cliente']!="" && $v['automation']!=10){

            $tipo_validos   = explode(",", $v['crit_tipo_cliente']);
            $tipos_cliente  = explode(",", $_SESSION["EC_USER"]['tipo']);

            $exclui = 0;

            $intersect = array_intersect($tipo_validos, $tipos_cliente);

            if(count($intersect)<1) $exclui = 1;

            if( $exclui==1){
                unset($campanhas[$k]);
            }
        }
        
        
        global $SITE_CHANNEL;
        if(trim($v['crit_canal_tipos'])!='' && (int)$SITE_CHANNEL>0){
            $valid_channels = explode(',', $v['crit_canal_tipos']);
            if( !in_array($SITE_CHANNEL, $valid_channels) ){
                unset($campanhas[$k]);
            }
        }
        
        if ( $v['crit_segm_cliente']!="" && $v['automation']!=10){

            $arr_camp = explode(',', $v['crit_segm_cliente']);
            $arr_cl   = explode(',', $_SESSION["EC_USER"]['tipo']);

            $result = array_diff($arr_camp, $arr_cl);

            $exclui = 0;

            if(count($result)>0) $exclui = 1;

            if( $exclui==1){
                unset($campanhas[$k]);
            }
        }
        
        if((int)$v['tipo_utilizador']>0){

            if( ((int)$_SESSION['EC_USER']['id']<1 && (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==0) ){
                unset($campanhas[$k]);
            }
            
            if( $v['tipo_utilizador']!=$_SESSION['EC_USER']['tipo_utilizador']){
                unset($campanhas[$k]);
            }
        }
        
        #apenas para clientes registados
        if ( $v['crit_aplicar']==2  ){
            if( !is_numeric($userID) ) unset($campanhas[$k]);

            if( ((int)$_SESSION['EC_USER']['id']<1 && (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==0) || $_SESSION['EC_USER']['sem_registo']>0){
                unset($campanhas[$k]);
            }
        }
        
        #apenas para lista de clientes
        if ( $v['crit_aplicar']==3  ){

            if( !is_numeric($userID) ) unset($campanhas[$k]);

            $add_email_query = '';
            if($_SESSION['EC_USER']['email']!='' && $_SESSION['EC_USER']['sem_registo']=='0') $add_email_query = "OR email_cliente='".$_SESSION['EC_USER']['email']."' ";

            $sql_c_cli = "select * from ec_campanha_clientes where ( id_cliente='$userOriginalID' $add_email_query) and campanha_id='".$v['id']."' LIMIT 0,1";
            
            $res_c_cli = cms_query($sql_c_cli);
            $existe = cms_fetch_assoc($res_c_cli);
            
            if( (int)$existe['id']<1 ){
                unset($campanhas[$k]);
            }
            
        }    
        
        if($v['crit_aplicar_cod']==1){
            # Limitaçao dos países
            if ( strlen($v['crit_paises'])>0 ){
                $limite_paises = explode(",", $v['crit_paises']);

                if (!in_array($COUNTRY['id'], $limite_paises)){
                    unset($campanhas[$k]);
                }
            }
        }elseif($v['crit_aplicar_cod']==2){

            # Limitaçao por codigo postal
            if ( strlen($v['cod_postal_inicio'])>0 && strlen($v['cod_postal_fim'])>0 ){
                $limite_paises = $v['crit_paises_codigo'];

                if ( $COUNTRY['id']!=$limite_paises ){
                    unset($campanhas[$k]);
                }
                
                $cp_user = $_SESSION["EC_USER"]["cp_promo"];

                if ( $v['cod_postal_inicio'] > $cp_user || $v['cod_postal_fim'] < $cp_user ){
                    unset($campanhas[$k]);
                }
                
                
            }
        } 
    }
 
                                      
    $campanhas_activas = array();
           
           
    foreach($campanhas as $k => $campDetail){

   
        if( trim($campDetail['crit_codigo'])=="" && (!isset($CONFIG_ActiveCampaignsIDS) || !in_array($campDetail['id'], explode(',', $CONFIG_ActiveCampaignsIDS))))
            continue;

           
          
        if ( $campDetail['crit_catalogo']>0  ){
            
            $vals = $campDetail['vals'];
            
            unset($campDetail['vals']);
            
            $priceList = $MARKET['lista_preco'];
            
            $sql = cms_query("SELECT * FROM registos
                                    $vals[JOIN]
                                INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
                                WHERE registos.activo='1' $vals[query_regras]
                                  AND registos.sku_group='".$sku_group."'
                                  AND registos_precos.idListaPreco='".$priceList."'
                                  AND registos_precos.preco>0
                                  AND registos.sku = registos_precos.sku");


            if( cms_num_rows($sql)==0 ){
                continue;
            }
        }
        
        $exps = call_api_func("get_expressions");
        $dates = $exps[297];                                                        
        $dates = str_ireplace("{data_inicio}", $campDetail["data_inicio"], $dates); 
        $dates = str_ireplace("{data_fim}", $campDetail["data_fim"], $dates); 
        
        $campanhas_activas[] = array(
            "id" => $campDetail["id"],
            "title" => $campDetail["nome".$LG],
            "subtitle" => $campDetail["subtitulo".$LG],
            "description" => $campDetail["desc".$LG],
            "dates" => $dates,
            "end_date" => $campDetail["data_fim"]." ".$campDetail["hora_fim"].":59:59",
            "color" => "#".$campDetail["cor_font"],
            "code" => $campDetail["crit_codigo"]
        ); 

 
    }
    
    $campanhas_portes = array();  
    
    foreach($campanhas as $k => $campDetail){
          
        if($campDetail["ofer_tipo"]==9){
            if ( $campDetail['crit_catalogo_aplicar_desconto']>0  ){
            
                $vals = $campDetail['vals_catalogo_aplicar_desconto'];
                
                unset($campDetail['vals_catalogo_aplicar_desconto']);
                
                $priceList = $MARKET['lista_preco'];
                
                $sql = cms_query("SELECT * FROM registos
                                        $vals[JOIN]
                                    INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()
                                    WHERE registos.activo='1' $vals[query_regras]
                                      AND registos.sku_group='".$sku_group."'
                                      AND registos_precos.idListaPreco='".$priceList."'
                                      AND registos_precos.preco>0
                                      AND registos.sku = registos_precos.sku");
      
      
                
                if( cms_num_rows($sql)==0 ){
                    continue;
                }
                
                # Intervalo de Valores
                $sql_int = "SELECT id FROM ec_campanhas_intervalo_valor WHERE ec_campanhas_id='".$campDetail["id"]."' AND vezes_oferta>0 LIMIT 0,1";
                $res_int = cms_query($sql_int);
                $row_int = cms_fetch_assoc($res_int);
                if((int)$row_int["id"]>0){
                    $sql_int_v    = "SELECT id FROM ec_campanhas_intervalo_valor WHERE ec_campanhas_id='".$campDetail["id"]."' AND de_valor<='".$price."' AND a_valor>='".$price."' AND vezes_oferta>0 LIMIT 0,1";
                    $res_int_v    = cms_query($sql_int_v);
                    $row_int_v    = cms_fetch_assoc($res_int_v);
                    if ((int)$row_int_v['id']==0 ){
                        continue;
                    }
                }
                
            }
            
            $campanhas_portes = array(
                "text" => $campDetail["nome".$LG],
                "active" => 1,
                "color" => "#".$campDetail["cor_font"]
            ); 
        }
    }       
    
    if(count($campanhas_portes)==0){
        $campanhas_portes = array(
            "text" => "",
            "active" => 0,
            "color" => ""
        ); 
    }
  
    usort($campanhas_activas, 'date_compare');
    
    $arr = array(); 
    $arr["campaigns"] = $campanhas_activas;
    $arr["free_shipping"] = $campanhas_portes;
    
    return serialize($arr);
}

function date_compare($a, $b)
{
    $t1 = strtotime($a["end_date"]);
    $t2 = strtotime($b["end_date"]);
    return $t1 - $t2;
}    
?>
