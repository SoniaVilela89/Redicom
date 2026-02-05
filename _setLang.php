<?

function _setLang($idioma=null, $paisID=0, $manual=0){
    
    global $userID, $eComm, $LG, $B2B, $BLOQUEAR_LOGIN_SEM_VENDAS;

    
    # 2023-04-27
    # Como é um post efetuado em site tem sempre de ter o HTTP_REFERER do site
    # Esta validação ajuda a detetar posts diretos ao ficheiro nos ataques
    if(isset($_SERVER['HTTP_REFERER']) && trim($_SERVER['HTTP_REFERER'])!='' && strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME']) == true && strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME'].'/') === false) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    
    
    if(is_null($idioma)){
        $idioma   = params('language_code');
        $paisID   = (int)params('country_id');    
        $manual   = (int)params('manual');
    }                   
  
    # quando não é numerico significa que é o codigo do idioma que tem sempre 2 caracteres 
    if(filter_var($idioma, FILTER_VALIDATE_INT) === false && strlen($idioma)!=2){
        ob_clean();
        header("HTTP/1.1 403 Forbidden");
        exit;
    }
    
    # quando é numerico significa que é o id do idioma que no maximo são 3 caracteres
    if(filter_var($idioma, FILTER_VALIDATE_INT) !== false && (strlen($idioma)>3 || $idioma>200)){
        ob_clean();
        header("HTTP/1.1 403 Forbidden");
        exit;
    }
      
    
    if( $paisID==0 ) return serialize(array("error"=>"Invalid Country")); 
    if( $idioma=="" ) return serialize(array("error"=>"Invalid Language")); 
    
    
    
    $LG_anterior        = $_SESSION['LG'];
    $_COUNTRY_anterior = $_SESSION['_COUNTRY'];
     
   
    $lang = array();
       
    if(is_numeric($idioma)){
    
        $s = "SELECT * FROM ec_language WHERE activo='1' AND id='".$idioma."' LIMIT 0,1";
        if($manual==1) $s = "SELECT * FROM `ec_language` WHERE id='".$idioma."' LIMIT 0,1";
       
        $lang_sql = cms_query($s);
        $lang     = cms_fetch_assoc($lang_sql);
    
    }
    
    
    if( empty( $lang ) ){
       $lang_sql  = cms_query("select * FROM ec_language WHERE `activo`='1' and `code`='".$idioma."' ORDER BY id ASC LIMIT 0,1");  
       $lang      = cms_fetch_assoc($lang_sql);
    }

    if( $lang['id']>0 ){
    
        if( $lang['code']=="es" ) $lang['code']="sp";
        if( $lang['code']=="en" ) $lang['code']="gb";
    
        $_SESSION['LG'] = $LG = $lang['code'];
    }
    if( empty( $lang ) )  return serialize(array("error"=>"Invalid Language")); 
   
             
   
    if( $_SESSION['EC_USER']['id']>0 ){
        cms_query("update _tusers set id_lingua='".$lang['id']."' where id='".$_SESSION['EC_USER']['id']."'");      
    }
        
    # 13/02/2020
    # Se for B2B altera uicamente o idioma e não faz mais nada
    if((int)$B2B>0){
        $arr    = array();
        $arr[]  = 1;
        return serialize($arr);
    }

 
    $_COUNTRY = $eComm->countryInfo($paisID);     
    if( empty($_COUNTRY) ) return serialize(array("error"=>"Invalid Country")); 

      
    # 2022-05-10
    # Com esta variavel do api_config a 1, não se permite trocar paises :: Las Kasas
    if($BLOQUEAR_LOGIN_SEM_VENDAS==1 && isset($_SESSION['_COUNTRY'])){
    
        $_SESSION['_MARKET'] = $_MARKET = $eComm->marketInfo($_SESSION['_COUNTRY']['id']);        
        return serialize(array("error"=>"Invalid Country")); 
    }

      
    $_MARKET = $eComm->marketInfo($_COUNTRY['id']);           
    if( empty($_MARKET) ) return serialize(array("error"=>"Invalid Country")); 
    
    
    $_MOEDA  = $eComm->moedaInfo($_MARKET['moeda']);


    if(is_callable('removeCookie')) {
        if($_COUNTRY['id']!=$_SESSION['_COUNTRY']['id']){
            call_user_func('removeCookie', "sh");            
        }              
    } 
            
    
    $_SESSION['_COUNTRY'] = $_COUNTRY;
    $_SESSION['_MARKET']  = $_MARKET;
    $_SESSION['_MOEDA']   = $_MOEDA;   
    
    
    if ($_COUNTRY['redirect']>0){
        $_SESSION['info_popup_pais_redirect'] = $_COUNTRY;
    }


    if($_SESSION['_MARKET']['entidade_faturacao']>0){    
        $entidade_r = $eComm->getLineTable('ec_invoice_companies', "id='".$_SESSION['_MARKET']['entidade_faturacao']."'");  
    }  
        

    if( is_numeric($userID) && $_SESSION['EC_USER']['id']>0){ 
    
    
        # 2024-10-24
        # Para piranha
        if((int)$SETTINGS["nif_validar_vies"] == 1){
            $sql_user = "SELECT nif_validado, tipo_utilizador, nif FROM _tusers WHERE id='".$userID."' LIMIT 0,1";
            $res_user = cms_query($sql_user);
            $row_user = cms_fetch_assoc($res_user);
            if($_SESSION["EC_USER"]["tipo_utilizador"] != $row_user["tipo_utilizador"]){
                $_SESSION['EC_USER']['nif_validado'] = $row_user['nif_validado'];
                $_SESSION['EC_USER']['tipo_utilizador'] = $row_user['tipo_utilizador'];
                $_SESSION['EC_USER']['nif'] = $row_user['nif'];
            }
        }
        
        if($_SESSION['EC_USER']['tipo_utilizador']==1 && $_SESSION['_MARKET']['lista_exclusiva1']>0 && ($entidade_r['id']>0 && $_SESSION['EC_USER']['pais']!=$entidade_r['country'])){            
            $_SESSION['_MARKET']['lista_preco'] = $_SESSION['_MARKET']['lista_exclusiva1'];            
        }
           
        # 2025-05-27
        # A blackandpepper é um b2c que tem disponivel o campo na ficha de cliente porque quer que os cleintes PT fechem mesmo a encomenda com a lista sem IVA   
        if((int)$B2B==0 && $_SESSION['EC_USER']['lista_preco']>0){
            $_SESSION['_MARKET']['lista_preco'] = $_SESSION['EC_USER']['lista_preco'];
        }
    }
    
    
    

    
    if( $_SESSION['EC_USER']['id']>0 ){

        cms_query("update _tusers set pais='$paisID' where id='".$_SESSION['EC_USER']['id']."'");
        $_SESSION['EC_USER']['pais'] = $paisID;

        // cms_query("update ec_moradas set pais='$paisID' where id_user='".$_SESSION['EC_USER']['id']."'");
    }
    


    # atualiza a cookie da mensagem do Welcome Gift
    if(isset($_COOKIE['_WCG_F'])) {
        global $_DOMAIN;
        $s = "SELECT * FROM ec_campanhas WHERE id = '%d' LIMIT 0,1";
        $f = sprintf($s,(int)$_COOKIE['_WCG_F']);
        $campanha = cms_fetch_assoc(cms_query($f));
        if((int)$campanha['id']>0){
            if($campanha['automation_retention']==2){ // Imediato
                setcookie('_WCGMSG', base64_encode($campanha['crit_codigo']."|||".htmlentities($campanha['avisos_desc'.$LG])."|||.".$_DOMAIN."|||".$campanha['id']), time()+10000000 , '/', $_DOMAIN, true);
            }
        }
    }



    # 2025-06-17
    if( (int)$_COOKIE['_WCG_F'] > 0 && ($LG_anterior!=$LG || $_COUNTRY_anterior['id']!=$_SESSION['_COUNTRY']['id']) ){

            setcookie('_WCGMSG', null, -1, '/', $_DOMAIN, true, true);
            setcookie('_WCGMSG_INF', null, -1, '/', $_DOMAIN, true, true);
            setcookie('_WCG_I', null, -1, '/', $_DOMAIN, true, true);
            setcookie('_WCG_F', null, -1, '/', $_DOMAIN, true, true);
            unset($_COOKIE['_WCGMSG']);
            unset($_COOKIE['_WCGMSG_INF']);
            unset($_COOKIE['_WCG_I']);
            unset($_COOKIE['_WCG_F']);

    }

    
    $eComm->removeInsertedChecks($userID);
    #comentada de propósito porque o que está funcão faz tmb é feito na clearTempCampanhas
    #$eComm->cleanVauchers($userID);
    $eComm->clearTempCampanhas($userID);
    
    $eComm->updateCartPrices($userID, $_SESSION['_COUNTRY'], $_SESSION['_MOEDA'], $_SESSION['_MARKET']);


    # 2024-11-19
    # Variavel em sessão para evitar estar sempre a fazer querys
    # Aqui temos de limpar porque as quantidades foram mexidas
    unset($_SESSION['sys_qr_bsk']);
    
    

    $_SESSION['EC_USER']['deposito_express']  = $eComm->getDepositoExpress(preg_replace("/[^0-9]/", "", $_SESSION['EC_USER']['shipping_cp']), $_COUNTRY['id'], $_MARKET);

    $arr    = array();
    $arr[]  = 1;
    return serialize($arr);
}

?>
