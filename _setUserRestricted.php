<?

function _setUserRestricted(){
  
    global $CONFIG_OPTIONS;
  
    $DADOS = $_POST;

    foreach( $DADOS as $k => $v ){
        if( is_array($v) ) continue;
            
        $DADOS[$k] = safe_value(utf8_decode($v));
    }
    

    $userID = (int)$_SESSION['EC_USER']['id'];
    
    if((int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==1 && (int)$_SESSION['EC_USER']['id_original']>0){
        $userID = $_SESSION['EC_USER']['id_original'];
    }
    
    # É necessário login para avançar
    if(!is_numeric($userID) || $userID < 1){
        return serialize(array("0"=>"0", "error"=>"1"));
    }

    $utilizador_q = cms_query("SELECT * FROM _tusers WHERE id='".$DADOS['id']."' AND id_user='".$userID."' LIMIT 0,1");
    $utilizador_n = cms_num_rows($utilizador_q);
    
    if($utilizador_n<1){
        return serialize(array("0"=> "0"));
    }
    

    
    $DADOS['active']=="on" ?  $activo = 1 : $activo = 0;
    
    $DADOS['accounting_data']=="on" ?  $conta = 1 : $conta = 0; 

    $DADOS['only_pvpr']=="on" ?  $only_pvpr = 1 : $only_pvpr = 0;

    ( $DADOS['markup'] > 0 || $only_pvpr > 0 ) ?  $show_pvpr = 1 : $show_pvpr = 0;
        
    $DADOS['info_after_sale']=="on" ?  $info_after_sale = 1 : $info_after_sale = 0;
    
    $DADOS['request_license_plates']=="on" ?  $request_license_plates = 1 : $request_license_plates = 0;
    
    $DADOS['create_budgets']=="on" ?  $create_budgets = 1 : $create_budgets = 0;

    $DADOS['create_rmas']=="on" ?  $create_rmas = 1 : $create_rmas = 0;

    $DADOS['view_orders']=="on" ?  $view_orders = 1 : $view_orders = 2;

    # Colocação de encomendas
    $impedimento = 2; # Bloqueada - Cliente impedido de colocar encomendas
    if($DADOS['do_orders']=="on") {
        if($user['impedimento_id'] == 0) $impedimento = 0; # Ativa - Sem limitações
        else $impedimento = 1; # Automática - Cliente limitado ao limite de crédito
    }

    
    $sql = "UPDATE _tusers SET
              nome='".$DADOS['name']."',
              cod_utilizador='".$DADOS['code']."',            
              b2b_dados_contabilisticos='".$conta."',     
              impedimento_id='".$impedimento."',     
              b2b_only_pvpr='".$only_pvpr."',  
              exibir_lista_pvpr='".$show_pvpr."',
              b2b_markup='".$DADOS['markup']."',
              b2b_informacao = '".implode(',', $DADOS['information'])."',   
              activo = '".$activo."',
              info_after_sale='".$info_after_sale."',
              request_license_plates='".$request_license_plates."',
              create_budgets='".$create_budgets."',
              create_rmas='".$create_rmas."',
              b2b_visualizar_encomendas='".$view_orders."'
            WHERE id='".$DADOS['id']."' 
            LIMIT 1"; 
            
    cms_query($sql);
 
    return serialize(array("0"=>"1"));
    
}

?>
