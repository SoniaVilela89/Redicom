<?

function _welcomeGift(){
       
    global $LG, $COUNTRY, $MARKET, $MOEDA, $_DOMAIN, $session_id, $slocation, $fx, $_CHECKOUT_VER;


    if((int)$_SESSION['EC_USER']['id']>0) {
        return serialize(array("0"=>"0"));
    }

    $q        = cms_query("SELECT * FROM ec_campanhas WHERE 
                            (DATEDIFF(NOW(),data_inicio)>=0) AND (DATEDIFF(NOW(),data_fim)<=0) 
                            AND (crit_paises='' OR concat(',',crit_paises,',') LIKE '%,".$COUNTRY['id'].",%' ) 
                            AND welcome_gift='1' 
                            AND moeda='".$_SESSION['_MOEDA']['id']."'
                            LIMIT 0,1");
                            
    $campanha = cms_fetch_assoc($q);


    $sql = "INSERT INTO b2c_campanhas_tracking SET dia=DAY(CURDATE()),
                                                    mes=MONTH(CURDATE()),
                                                    ano=YEAR(CURDATE()),
                                                    camp_id='".$campanha['id']."',
                                                    user_id='0',
                                                    order_id='0',
                                                    mercado='".$COUNTRY['country_code']."',
                                                    valor_enc='0'";

    cms_query($sql);


    #Envio de email
    if($campanha['ofer_tipo']==3){ #Voucher
        if($campanha['ofer_valor']>0){
            $valor = $campanha['ofer_valor'];
            $tipo_oferta = 2;
        }else if($campanha['ofer_perc']>0){
            $valor = $campanha['ofer_perc'];
            $tipo_oferta = 1;
        } else {
            $tipo_oferta = 0;
        }
    }else {
        $tipo_oferta = 0;
    }

    if($tipo_oferta>0){
                   
        #Gerar o voucher ou vale
        $str = microtime(true) *  (rand(100000,999999) / 100000);
        $str = str_replace(".", "", $str);
        $codigo = $campanha['prefixo'].substr($str,-6).strtoupper(substr(md5(str_replace(".", "", microtime(true) *  (rand(100000,999999) / 100000))),0,4));
    
        $data_validade = date("Y-m-d", strtotime("+1 day"));
        
        $go = @cms_query("INSERT INTO `ec_vauchers` SET cod_promo='$codigo',
                                                          tipo='$tipo_oferta',
                                                          valor='".$valor."',
                                                          motivo_emissao='Welcome Gift',
                                                          crit_prod_promo='".$campanha['crit_prod_promo']."',
                                                          data_limite='$data_validade',
                                                          valido_para=1,
                                                          campanha_id='".$campanha['id']."',
                                                          motivo_id='5',  
                                                          cod_cliente='0',                                                        
                                                          created_at=NOW(),
                                                          catalogo_voucher='".$campanha['catalogo_voucher']."'");
    }

    setcookie("_WCG", $codigo, time()+10000000, '/', $_DOMAIN, true);
    return serialize(array("0"=>$codigo));
    
}

?>
