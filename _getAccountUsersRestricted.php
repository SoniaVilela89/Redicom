<?

function _getAccountUsersRestricted($id=null, $uid=null)
{

    global $userID;
    global $LG;
    global $CONFIG_OPTIONS;
    
    $userOriginalID = $userID;
    if((int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO']==1 && (int)$_SESSION['EC_USER']['id_original']>0){
        $userOriginalID = $_SESSION['EC_USER']['id_original'];
    }
    
    if(is_null($id)){
        $id = (int)params('id');
        $uid = (int)params('uid');
    }

    $arr = array();
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    if($page_id>0){
        $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
        $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
        $arr['customer'] = call_api_func('getCustomer');
        $arr['shop'] = call_api_func('OBJ_shop_mini');
        $arr['account_expressions'] = call_api_func('getAccountExpressions');
        
        
        $arr['informations'] = array();
        
        $docs_s = "select id, nome$LG as nome from downloadcat ORDER BY nome$LG";
        $docs_q = cms_query($docs_s);        
        while($docs_r = cms_fetch_assoc($docs_q)){
            $arr['informations'][] = array("id" => $docs_r['id'], "name" => $docs_r['nome']);        
        }
        
    }

  
    $more = '';    
    if($uid>0){
        $more = " AND id='".$uid."' ";        
    } 
  
    $s = "SELECT * FROM _tusers WHERE id_user='".$userOriginalID."' $more ORDER BY id DESC";
    $q = cms_query($s);    
    
    $users = array();     
    while($user = cms_fetch_assoc($q)){
    
        ($user['impedimento_id']==0) ? $permite_encomendas = 1 : $permite_encomendas = 0;
     
        $lastlogin = ($user['lastlogin']=='0000-00-00 00:00:00' ) ? '--' : date('Y-m-d H:i', strtotime($user['lastlogin']));
        
        $registed_at = ($user['registed_at']=='0000-00-00 00:00:00' ) ? '--' : date('Y-m-d', strtotime($user['registed_at']));

        $users[] = array( "id"                      => $user['id'],
                          "name"                    => $user['nome'],
                          "email"                   => $user['email'],
                          "code"                    => $user['cod_utilizador'],
                          "do_orders"               => $permite_encomendas,
                          "registed_at"             => $registed_at,
                          "last_login"              => $lastlogin,
                          "active"                  => $user['activo'],
                          "accounting_data"         => $user['b2b_dados_contabilisticos'],                          
                          "information"             => $user['b2b_informacao'],
                          "only_pvpr"               => $user['b2b_only_pvpr'],
                          "markup"                  => $user['b2b_markup'],
                          "info_after_sale"         => $user['info_after_sale'],
                          "request_license_plates"  => $user['request_license_plates'],
                          "create_budgets"          => $user['create_budgets'],
                          "create_rmas"             => $user['create_rmas'],
                          "view_orders"             => ($user['b2b_visualizar_encomendas'] == 2) ? 0 : 1 );
    
    }    
       
    $arr['users'] = $users;       
                                
    return serialize($arr);

}

?>
