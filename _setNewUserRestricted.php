<?

function _setNewUserRestricted(){
  
    global $CONFIG_OPTIONS, $API_CONFIG_PASS_SALT;

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

    $user = call_api_func("get_line_table","_tusers", "id='".$userID."'");

    # Validar se cliente não está já registado  
    $verifica = call_api_func("get_line_table","_tusers", "email='".$DADOS['email']."' AND sem_registo='0'");
    
    if($verifica['id']>0){
        return serialize(array("0"=>"0"));
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

    $sql = "insert into _tusers set
                                nome='%s',
                                email='%s',
                                password='%s',
                                accept_new='1',
                                id_user='%d',
                                cod_utilizador='%s',            
                                b2b_dados_contabilisticos='%d',     
                                impedimento_id='%d',     
                                b2b_only_pvpr='%d',
                                exibir_lista_pvpr='%d',  
                                b2b_markup='%d',
                                b2b_informacao = '%s',   
                                activo = '%d',          
                                ip_client='%s',
                                browser_client='%s',
                                info_after_sale='%d',
                                request_license_plates='%d',
                                create_budgets='%d',
                                create_rmas='%d',
                                b2b_visualizar_encomendas='%d',
                                lista_pvpr='%d',
                                tracking_session_id='%s'";
                                
    $q_user = sprintf($sql, $DADOS['name'], $DADOS['email'], crypt($DADOS['email'].date("Y-m-d H:i:s"), $API_CONFIG_PASS_SALT), $userID,
                        $DADOS['code'], $conta, $impedimento, $only_pvpr, $show_pvpr, $DADOS['markup'], implode(',', $DADOS['information']), $activo,           
                        $_SERVER["HTTP_X_REAL_IP"],$_SERVER["HTTP_USER_AGENT"], $info_after_sale, $request_license_plates, $create_budgets, $create_rmas, 
                        $view_orders, $user['lista_pvpr'], $_SESSION['traffic_tracked_session']);
          
    cms_query($q_user);
    

    $cliente_id = cms_insert_id();                 


    $resp = 0;
    
    if($cliente_id>0) {
        $resp = 1;

        # envio de email a informar da criação de conta
        if($activo == 1){

            $arr = $DADOS;
            $arr['nome'] = $DADOS['name'];

            $detalhes = '<div style="border-bottom: 1px solid lightgray;padding: 7px;margin: 0;text-align: left !important;">'.estr(5).'</div>';
            $detalhes .= generate_email_client($arr,1);

            global $LG, $pagetitle, $sslocation;

            $data = array(
                "lg"                =>  $LG,
                "email_cliente"     =>  $DADOS['email'],
                "id_cliente"        =>  $cliente_id,
                "CLIENT_NAME"       =>  $DADOS['name'],
                "NOME"              =>  $DADOS['name'],
                "DETALHES"          =>  $detalhes,
                "PAGETITLE"         =>  $pagetitle
            );

            $data = serialize($data);
            $data = gzdeflate($data, 9);
            $data = gzdeflate($data, 9);
            $data = urlencode($data);
            $data = base64_encode($data);

            require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/client/client_rest.php';
            $r = new Rest($sslocation . '/api/api.php');
            $ret = $r->get("/sendEmail/4/".$data); # email novo registo efetuado com sucesso
        }

    }



    return serialize(array("0"=>$resp));
    
}

?>
