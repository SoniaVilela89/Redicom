<?

function _getAccountBilling($page_id=null)
{

    global $userID, $LG, $B2B, $SETTINGS, $CONFIG_OPTIONS, $MOEDA, $HIDE_ZIPCODE, $COUNTRY;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions'); 
    $arr['account_birthday_required'] = $SETTINGS["nascRQ"]; 
    
    # 2020-09-02
    # Definido por Serafim se cliente é do tipo Empresa e se tem NIF associado não pode alterar   
    $arr['user_particular_empresa']  = (int)$_SESSION['EC_USER']['tipo_utilizador'];
    $arr['NIF_particular_obrigatorio'] = (int)$SETTINGS['nif_particular_obrigatorio'];

    $arr['show_nif'] = 1;
    if($COUNTRY['hide_nif'] == 1) $arr['show_nif'] = 0;
    
    $arr['show_cae'] = 0;
    if($SETTINGS['registarCAE'] == 1)  $arr['show_cae'] = 1;
    
    $arr['no_edit_validate_vies'] = 0;
    if((int)$_SESSION['EC_USER']['nif_validado'] == 1 && $SETTINGS['validacao_nif_empresa'] == 1 && $arr['user_particular_empresa'] == 1) $arr['no_edit_validate_vies'] = 1;

    $arr['require_nif'] = 0;
    if($SETTINGS['solicitar_nif_particular'] == 2) $arr['require_nif'] = 1;
    
    $arr['DISPLAY_sexo'] = (int)$SETTINGS['nao_exibir_sexo'];
    $arr['DISPLAY_phone'] = (int)$SETTINGS['telfSN'];    
    $arr['allow_telf'] = (int)$SETTINGS['telf_fixo'];
    
    if((int)$_SESSION['EC_USER']['id_utilizador_restrito']>0){

        $sql  = cms_query("SELECT * FROM _tusers WHERE id='".$_SESSION['EC_USER']['id_utilizador_restrito']."' LIMIT 0,1");
        $_usr = cms_fetch_assoc($sql);
        
        $arr['customer']["b2b_contacto"]      = $_usr['nome'];
        $arr['customer']["b2b_email"]         = $_usr['email'];
    }


    $arr['customer']["vendedor_b2b_vendedor"] = (int)$_SESSION['EC_USER']['vendedor'];

    if((int)$_SESSION['EC_USER']['vendedor']>0){

        $sql  = cms_query("SELECT * FROM _tusers_sales WHERE id='".(int)$_SESSION['EC_USER']['vendedor']."' LIMIT 0,1");
        $_usr = cms_fetch_assoc($sql);
 
        $arr['customer']["vendedor_b2b_nome"]      = $_usr['nome'];
        $arr['customer']["vendedor_b2b_contacto"]  = $_usr['telefone'];
        $arr['customer']["vendedor_b2b_email"]     = $_usr['email'];
    }
    
    if( (int)$CONFIG_OPTIONS['MYACCOUNT_PLAFOND_LAYOUT'] == 1 ){
        
        $arr['plafond_layout_bar'] = 1;
        
        $userOriginalID = $userID;
        if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
            $userOriginalID = $_SESSION['EC_USER']['id_original'];
        }
        
        $plafond_data = cms_fetch_assoc(cms_query("SELECT `limite_credito`, `credito_utilizado`, `valor_vencido`, `responsabilidade_carteira` 
                                                    FROM `_tusers` 
                                                    WHERE `id`='".$userOriginalID."' 
                                                    LIMIT 1"));

        $plafond_limit                = (float)$plafond_data['limite_credito'];
        $plafond_used                 = (float)$plafond_data['credito_utilizado'];
        $plafond_balance              = $plafond_limit-$plafond_used;
        $plafond_overdue              = (float)$plafond_data['valor_vencido'];
        $plafond_portfolio_liability  = (float)$plafond_data['responsabilidade_carteira'];
        
        $arr['plafond_data']['portfolio_liability'] = api_money_format($plafond_portfolio_liability, $MOEDA['id']);
        $arr['plafond_data']['credit_used']         = api_money_format($plafond_used, $MOEDA['id']);
        $arr['plafond_data']['open_credit']         = api_money_format($plafond_balance, $MOEDA['id']); 
        $arr['plafond_data']['open_balance']        = api_money_format($plafond_overdue, $MOEDA['id']);         
            
    }
    
    if( (int)$B2B == 0 && (int)$HIDE_ZIPCODE == 1 ){
        $arr['shop']['country']['mask_cp'] = '00000';
        $arr['HIDE_zipcode'] = 1;   
    }
                                                           
    $arr['country_tel_code'] = get_country_tel_code($arr['customer']['pais_indicativo_tel']);

                                                           
    return serialize($arr);

}

?>
