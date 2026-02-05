<?

function _getAccountOrders($page_id=null)
{

    global $userID, $eComm, $LG, $ESTADOS_ENCOMENDAS;
    global $CONFIG_OPTIONS, $ENCS_OMNI;
    
    $userOriginalID = $userID;
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    
    if(trim($ESTADOS_ENCOMENDAS)==''){
        $ESTADOS_ENCOMENDAS = "1,10,40,42,45,50,70,80,103,100";
    }

    $search_term = null;
    if( trim($_GET['te']) != "" && (int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] == 0 ){
        $search_term = base64_decode($_GET['te']);
    }

    $orders = array();
    #$encomendas = $eComm->getOrders($userID, $ESTADOS_ENCOMENDAS);
    $encomendas = $eComm->getOrders($userOriginalID, 0, 0, $search_term);
    
    
    if((int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] == 1){
        require_once _ROOT."/api/api_external_functions.php";

        $bdi_orders = getBDiOrders($userOriginalID);
        
        $encomendas = array_merge($encomendas, $bdi_orders);
    }
            
    
    if((int)$ENCS_OMNI == 1){
        require_once _ROOT."/api/api_external_functions.php";

        $omni_orders = getOMNIOrders($userOriginalID);       
                           
        $encomendas = array_merge($encomendas, $omni_orders);    
    }
    
    
    foreach( $encomendas as $k => $v ){
        
        # Pagamentos parciais
        if((int)$v['percentagem_parcial'] > 0 && $v['valor_anterior'] > 0 && $v['tracking_status'] <= 1){
            $previous_value_temp = $v['valor'];
            $v['valor'] = $v['valor_anterior'];
            $v['valor_anterior'] = $previous_value_temp;  
        }
        

        $orders[strtotime($v['datahora']).".".$v['id']] = call_api_func('orderOBJ',$v, 0);
    }


    if((int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] == 1 || (int)$ENCS_OMNI==1){
        krsort($orders);
    }
    

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['orders'] =  $orders;
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');

    if( (int)$CONFIG_OPTIONS['HISTORICO_EMCOMENDAS_COM_BDI'] != 1 ){
        $arr['search_datatable'] = 1;
    }

    if( trim($search_term) != "" && strlen($search_term) > 2 ){
        $arr['search_term'] = $search_term;
    }

    return serialize($arr);

}
?>
