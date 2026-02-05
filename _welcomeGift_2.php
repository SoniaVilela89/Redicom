<?

function _welcomeGift_2(){
       
    global $COUNTRY, $MOEDA, $LG, $_DOMAIN;
    
    $preview = (int)$_POST['preview'];    
    

    if((int)$_SESSION['EC_USER']['id']>0 && $preview==0) {
        return serialize(array("0"=>"0"));
    }
    
     
            
    $more = "AND (DATEDIFF(NOW(),data_inicio)>=0) AND (DATEDIFF(NOW(),data_fim)<=0) 
            AND (crit_paises='' OR concat(',',crit_paises,',') LIKE '%,".$COUNTRY['id'].",%' ) 
            AND moeda='".$MOEDA["id"]."'";
            
    if($preview>0){
        $more = " and id='$preview' ";
    }

    $q        = cms_query("SELECT * 
                            FROM ec_campanhas 
                            WHERE welcome_gift='1' $more
                            LIMIT 0,1");
                            
    $campanha = cms_fetch_assoc($q);
    
    if((int)$campanha['id']>0){
        /*$ano = date('Y');
        $mes = date('m');
        $dia = date('d');
    
        $sql = "INSERT INTO b2c_campanhas_tracking SET dia='".$dia."',
                                                        mes='".$mes."',
                                                        ano='".$ano."',
                                                        camp_id='".$campanha['id']."',
                                                        user_id='0',
                                                        order_id='0',
                                                        mercado='".$COUNTRY['country_code']."',
                                                        valor_enc='0'";*/
    

        
        #Welcome Gift de código imediato
        if($campanha['automation_retention']==2){
            setcookie('_WCGMSG', base64_encode($campanha['crit_codigo']."|||".htmlentities($campanha['avisos_desc'.$LG])."|||.".$_DOMAIN."|||".$campanha['id']), time()+10000000 , '/', $_DOMAIN, true);
            
            $theme_info = getThemeColorInfo($campanha['discount_msg_theme']);
            
            setcookie('_WCGMSG_INF', base64_encode($campanha['discount_msg_location']."|||".$theme_info['title_color']."|||".$theme_info['background_color']), time()+10000000 , '/', $_DOMAIN, true);
            
            setcookie('_WCG_I', $campanha['id'], time()+10000000, '/', $_DOMAIN, true); #para saber que é o wg imediato
            setcookie('_WCG_F', $campanha['id'], time()+10000000, '/', $_DOMAIN, true); #não voltar a exibir o popup
        }else{
            setcookie('_WCG_F', $campanha['id'], time()+60*60*24*7, '/', $_DOMAIN, true); #7 dias  
        }

    } 

    return serialize(array("0"=>(int)$campanha['id']));
    
}

?>
