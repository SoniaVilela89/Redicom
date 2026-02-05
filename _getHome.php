<?

function _getHome($page_id=0, $home_id=0){

    global $fx, $detect, $MOEDA, $CACHE_HOME, $API_MENU_SEM_LIMITE, $HOMEPAGE_ISA, $CACHE_KEY, $userID, $B2B, $USAR_CACHE_PROD_PROMO, $EXIBIR_MENU_TODOS_PRODUTOS, $LG, $COUNTRY, $CONFIG_OPTIONS;
    global $API_VERSION;
    global $SHIPPING_EXPRESS_COLOR, $SHIPPING_EXPRESS_B_COLOR;
    global $SITE_CHANNEL, $db_name_cms;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $home_id = (int)params('home_id');
    }
    
    
    # 2020-03-27 - para promoções limitadas a tipo de cliente
    $tipo_user = 0;
    if(is_numeric($userID)){
        if($_SESSION["EC_USER"]["sem_registo"]==0) $tipo_user = 1;
    }
    
    
    
    #2024-12-13
    # Qundo estamos em APP as promoções são as de site (omnicanal)    
    if($SITE_CHANNEL==4) $SITE_CHANNEL=1;     
         
            
    $scope_promo = array();
    $scope_promo['PAIS']          = $_SESSION['_COUNTRY']['id'];
    $scope_promo['SEG']           = (string)$_SESSION['segmentos'];
    $scope_promo['TIPO_USER']     = $tipo_user;
    $scope_promo['SITE_CHANNEL']  = $SITE_CHANNEL;
    
    if((int)$_SESSION['EC_USER']['tipo_utilizador']>0){
        $scope_promo['TYPE'] = $_SESSION['EC_USER']['tipo_utilizador'];
    }
    
    $_HP_PROMO_cacheid   = $CACHE_KEY."HPC2_".implode('_', $scope_promo); 
    
    $dados_promo = $fx->_GetCache($_HP_PROMO_cacheid, 5);

    $banner       = array();
    $banner['id'] = 0;
    
    
    if ($dados_promo!=false && $_GET['nocache']!=1 )
    {         
        $banner           = $dados_promo["banner"];
        $promo["promos"]  = $dados_promo["promos"];
        $promo["info"]    = $dados_promo["info"];
        
    } else { 
    
        $promo           = array();
        $promo['promos'] = array();
        $promo['values'] = array();
        $promo['info']   = array();
        $promo['cached'] = 1;
          
          
        $select_promo = " AND crit_tipo_registo=0";    
        if($tipo_user==1) $select_promo = "";     
                          

        $sel_promo = "SELECT * 
                      FROM ec_promocoes 
                      WHERE NOW() >= CONCAT(data_inicio, ' ',hora_inicio, ':00:00')
                        AND NOW() < CONCAT(data_fim, ' ',hora_fim, ':59:59')
                        AND (paises LIKE '' OR paises IS NULL OR CONCAT(',',paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%')
                        AND (crit_mercado='' OR CONCAT(',',crit_mercado,',') LIKE '%,".$_SESSION['_MARKET']['id'].",%' )
                        AND deleted='0'
                        AND is_checkout=0
                        $select_promo                        
                      ORDER BY ordem ASC, id DESC";
                                                    
        $res_promo = cms_query($sel_promo);
        while($promocao = cms_fetch_assoc($res_promo))
        {

            if ( $promocao['crit_tipo_cliente']!="" ){

                /*if(!is_numeric($_SESSION["EC_USER"]['id'])){ 
                    continue;
                }*/
                if( trim($_SESSION['segmentos']) == '' ){ continue;}
    
                $tipo_validos   = explode(",", $promocao['crit_tipo_cliente']);
                $tipos_cliente  = explode(",", $_SESSION['segmentos']);
    
                $exclui = 0;
    
                $intersect = array_intersect($tipo_validos, $tipos_cliente);
    
                if(count($intersect)<1) {
                    continue;
                }
            }
            
            
            if ( $promocao['excluir_tipo_cliente'] != "" ){
            
                if( trim($_SESSION['segmentos']) == '' ){ continue;}
    
                $tipo_validos   = explode(",", $promocao['excluir_tipo_cliente']);
                $tipos_cliente  = explode(",", $_SESSION['segmentos']);
    
                $intersect = array_intersect($tipo_validos, $tipos_cliente);
                
                if(count($intersect) > 0) continue;
                
            }
          
            if(trim($promocao['crit_canal_tipos'])!='' && (int)$SITE_CHANNEL>0){
                $valid_channels = explode(',', $promocao['crit_canal_tipos']);
                if( !in_array($SITE_CHANNEL, $valid_channels) ){
                    continue;
                }
            }
            

            $promo['promos'][$promocao['id']] = $promocao['id'];
            $promo['info'][]                  = $promocao; 
            
                                               
            if( (int)$banner['id']<1 && ($promocao['banner1']>0 || $promocao['banner2']>0) ){
            
                $banner1 = call_api_func("get_line_table", "banners", "id='".$promocao['banner1']."'");
                $banner2 = call_api_func("get_line_table", "banners", "id='".$promocao['banner2']."'");
                
                if((int)$banner1['id']==0) continue;
                
                $banner = array();
                $banner['id']                   = $promocao['id'];
                $banner['banner1']              = (int)$banner1['id'];
                $banner['banner2']              = (int)$banner2['id'];
                $banner['layout_type']          = $promocao['layout_type'];
                $banner['layout_type_mobile']   = $promocao['layout_type_mobile'];
                $banner['tipo']                 = 1;    
            }             
        } 
        


        if((int)$banner['id']<1){
            $sel = "SELECT * 
                    FROM ec_campanhas 
                    WHERE moeda='".$MOEDA["id"]."'
                        AND deleted='0'
                        AND NOW() between CONCAT(data_inicio, ' ',hora_inicio, ':00:00') and CONCAT(data_fim, ' ',hora_fim, ':59:59')
                        AND (crit_aplicar_cod='0' OR ((crit_aplicar_cod='1' or crit_aplicar_cod='2') AND CONCAT(',',crit_paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%'))
                        AND recuperar_carrinho='0'
                        AND welcome_gift='0'
                        AND automation='0'
                        AND (banner1>0 OR banner2>0)
                    ORDER BY id DESC";
                    
            $res      = cms_query($sel);
            while($campanha = cms_fetch_assoc($res))
            {
    
                if ( $campanha['crit_tipo_cliente']!="" ){
    
                    /*if(!is_numeric($_SESSION["EC_USER"]['id'])){ 
                        continue;
                    }*/
                    if( trim($_SESSION['segmentos']) == '' ){ continue;}
        
                    $tipo_validos   = explode(",", $campanha['crit_tipo_cliente']);
                    $tipos_cliente  = explode(",", $_SESSION['segmentos']);
        
                    $exclui = 0;
        
                    $intersect = array_intersect($tipo_validos, $tipos_cliente);
        
                    if(count($intersect)<1) {
                        continue;
                    }
                }
                
                if ((int)$campanha['tipo_utilizador']>0){
                    if(!is_numeric($_SESSION["EC_USER"]['id'])){ 
                        continue;
                    }
                    
                    if($campanha['tipo_utilizador']!=$_SESSION["EC_USER"]['tipo_utilizador']){ 
                        continue;
                    }    
                }
                                
                if((int)$banner['id']<1 && ($campanha['banner1']>0 || $campanha['banner2']>0) ){
                
                    $banner1 = call_api_func("get_line_table", "banners", "id='".$campanha['banner1']."'");
                    $banner2 = call_api_func("get_line_table", "banners", "id='".$campanha['banner2']."'");
                    
                    if((int)$banner1['id']==0) continue;
                
                    $banner = array();
                    $banner['id']                 = $campanha['id'];
                    $banner['banner1']            = (int)$banner1['id'];
                    $banner['banner2']            = (int)$banner2['id'];
                    $banner['layout_type']        = $campanha['layout_type'];
                    $banner['layout_type_mobile'] = $campanha['layout_type_mobile'];
                    $banner['tipo']               = 2;    
                    break;
                }     
            }
        }

        $resp_arr = array();
        $resp_arr["banner"] = $banner;
        $resp_arr["promos"] = $promo["promos"];
        $resp_arr["info"]   = $promo["info"];
        $fx->_SetCache($_HP_PROMO_cacheid, $resp_arr, 5);
    }
    
    
    
    # 2020-03-27 - Validar se a promoção está limitada a tipo de clientes
    if(is_numeric($userID)){
    
        foreach ( $promo['info'] as $k => $v ) {  
                    
           if($v['crit_tipo_registo']==1){
              $data_promocao = strtotime($v['data_inicio'].' '.$v['hora_inicio'].':00:00');
              $data_registo = strtotime($_SESSION['EC_USER']['registed_at']);
              if($data_registo > $data_promocao){ 
                  unset($promo['promos'][$v['id']]); 
              }
           }
           
        }
        
    }
    
    
    
    $mobile = "DESKTOP";
    if(file_exists('lib/class.mobile_detect.php')){
        if($detect->isMobile() && !$detect->isTablet()){
            $mobile = 'MOBILE';
        }
    }else{
        if( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'],'Android') ){
            $mobile = "MOBILE";
        }
    }
  
  
    $scope = array();
    $scope['PAIS']        = $_SESSION['_COUNTRY']['id'];
    $scope['HOME_ID']     = $home_id;
    $scope['LG']          = $_SESSION['LG'];
    $scope['DEVICE']      = $mobile;
    
    $scope['PROMO']       = implode(',', $promo["promos"]); 
    
    $scope['PRICE_LIST']  = $_SESSION['_MARKET']['lista_preco'];
    
    if((int)$_SESSION['EC_USER']['tipo_utilizador']>0){
        $scope['TYPE'] = $_SESSION['EC_USER']['tipo_utilizador'];
    }
    
    if(is_array($_SESSION['EC_USER']['margem']) && count($_SESSION['EC_USER']['margem'])>0){
        $scope['MARGEM'] = md5(base64_encode(serialize($_SESSION['EC_USER']['margem'])));
    }
    
    if((int)$_SESSION['EC_USER']['descontos_exclusivos']>0){
        $scope['DES_EXC'] = $_SESSION['EC_USER']['id']."|||".$_SESSION['EC_USER']['descontos_exclusivos'];
    }

    if((int)$_SESSION['EC_USER']['b2b_markup']>0){
        $scope['MARKUP'] = $_SESSION['EC_USER']['b2b_markup'];
    }
       
    if((int)$B2B>0){
        $scope['EC_USER_TIPO'] = $_SESSION['EC_USER']['tipo'];
    }
    
    $_HPcacheid         = $CACHE_KEY."HP_".implode('_', $scope);
    
        
    $dados = $fx->_iGetCache($_HPcacheid, $CACHE_HOME);

    if ($dados!=false && $_GET['nocache']!=1 )
    {       
        $resp = unserialize($dados);

    } else {
    
        $page_id = 1;
        
        
        if((int)$B2B>0){
            $s = "SELECT id,tipo FROM b2c_templatesHome WHERE (crit_paises='' OR CONCAT(',',crit_paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%') AND dodia<=CURDATE() AND aodia>=CURDATE() ORDER BY id DESC";
            $q = cms_query($s);
    
            while($home = cms_fetch_assoc($q))
            { 
                if ( $home['tipo']!="" ){
    
                    #if(!is_numeric($userID)){ continue;}
                    if( trim($_SESSION['EC_USER']['tipo']) == '' ){ continue;} 
        
                    $tipo_validos   = explode(",", $home['tipo']);
                    $tipos_cliente  = explode(",", $_SESSION["EC_USER"]['tipo']);
        
                    $exclui = 0;
        
                    $intersect = array_intersect($tipo_validos, $tipos_cliente);
        
                    if(count($intersect)<1) $exclui = 1;
        
                    if( $exclui==1){
                        continue;
                    }
                }
                
                $page_id = $home['id'];
                break;  
            } 
              
        }else{

            $table_home = "`$db_name_cms`.b2c_templatesHome"; 
            $home = get_line_table_api_obj($table_home, "(crit_paises='' OR CONCAT(',',crit_paises,',') LIKE '%,".$_SESSION['_COUNTRY']['id'].",%') AND dodia<=CURDATE() AND aodia>=CURDATE() ORDER BY id DESC");    
            if($home['id']>0) $page_id = $home['id'];
        }
    
        if( $home_id>0 ) $page_id = $home_id;
       
        $resp = array();
        
        
        if((int)$API_VERSION < 202202){
        
            if((int)$API_MENU_SEM_LIMITE==1){                     
                $resp['menu_pages'] = call_api_func('getMenu', 2, 1, 0);
            }else{                                  
                $resp['menu_pages'] = call_api_func('get_menu', 2, 1);
            }
        
        
            if((int)$EXIBIR_MENU_TODOS_PRODUTOS==1){
                if((int)$API_MENU_SEM_LIMITE==1){
                    $resp['menu_pages_id10'] = call_api_func('pageOBJ', 10, 1, 0);       
                }else{
                    $row_page_id10 = call_api_func('get_pagina', 10, "_trubricas");
                    if($row_page_id10['hidden']==0 && $row_page_id10['hidemenu']==0){
                        $resp['menu_pages_id10'] = call_api_func('OBJ_page', $row_page_id10, 1, 0);
                        $resp['menu_pages_id10']['childs'] = call_api_func('get_menu', 10, 1, 0);
                    } 
                }                        
                
                if(!empty($resp['menu_pages_id10']) && $resp['menu_pages_id10']['id']>0){            
                    $resp['menu_pages'] = array_merge(array($resp['menu_pages_id10']), $resp['menu_pages']);
                    unset($resp['menu_pages_id10']);
                }
            }
        }
                 
        $row = call_api_func('get_pagina', $page_id, "b2c_templatesHome");
        $resp['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0, "b2c_templatesHome");        
        
        if($mobile=="MOBILE"){
            $resp['layout_banner_type'] = $row['layout_type_mobile'];
        } else {
            $resp['layout_banner_type'] = $row['layout_type'];
        }
        
        
        if((int)$banner["id"] > 0 || (int)$banner['banner2'] > 0){
            if($banner['banner2']>0){
                
                if($mobile=="MOBILE"){
                    $temp = call_api_func('OBJ_banner',$banner['banner2'],0,"bannerHomepage");
                    if(count($temp[parts])>0){
                      $resp['selected_page']['banner'] = array();
                      $resp['selected_page']['banner'][0] = $temp;
                      $resp['layout_banner_type'] = $banner['layout_type_mobile'];
                    }     
                }else{
                    $temp = call_api_func('OBJ_banner',$banner['banner1'],0,"bannerHomepage"); 
                    if(count($temp[parts])>0){
                      $resp['selected_page']['banner'][0] = $temp;
                      $resp['layout_banner_type'] = $banner['layout_type'];
                    }
                }
            }elseif($banner['banner1']>0){

                $temp = call_api_func('OBJ_banner',$banner['banner1'],0,"bannerHomepage");
                
                if(count($temp[parts])>0){
                    $resp['selected_page']['banner'] = array();
                    $resp['selected_page']['banner'][0] = $temp;
                    $resp['layout_banner_type'] = $banner['layout_type'];
                    
                    if($mobile=="MOBILE"){
                        $resp['layout_banner_type'] = $banner['layout_type_mobile'];
                    }
                }  
                
            }
        }

        
        # Descontinuado - 2020-08-18               
        #$resp['modules'] = call_api_func('get_home_modules',$page_id);
                                    
                                        
        $row["ContentBlock"] = gmp_strval(gmp_neg($page_id));
        
        if(strpos($_SERVER['SERVER_NAME'], 'sales') !== false) {
            $row["ContentBlock"] = $HOMEPAGE_ISA;
        }
                
        if((int)$_SESSION['EC_USER']['tipo_utilizador']==1 && (int)$row['company_block_id']>0){
            $row["ContentBlock"] = $row['company_block_id'];
        }
    
                                         
        $resp['content_blocks'] = get_content_blocks($row["ContentBlock"], 0);

                 
        $table_style = "`$db_name_cms`.ContentBlocksStyles";                                                                        
        $style = get_line_table_api_obj($table_style, "Id='1'");
        
        $resp['content_blocks_style'] = $style;



        if((int)$API_VERSION < 202202){
        
            $marcas = array();       
            
            $q = "SELECT registos_marcas.id, registos_marcas.ordem, registos_marcas.nome$LG, registos_marcas.desc$LG, registos_marcas.link, registos_marcas.target, registos_marcas.hidden
                                    FROM registos_marcas 
                                    WHERE registos_marcas.hidden = 0
                                        AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) 
                                    GROUP BY registos_marcas.id
                                    ORDER BY registos_marcas.ordem, registos_marcas.nome$LG, registos_marcas.id DESC";
                                    
            $sql    = cms_query($q);
    
            while($v = cms_fetch_assoc($sql)){
                if(count($marcas)>=12) continue;
    
                $img = array();
                if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/marca_".$v['id'].".jpg") ){
                    $img = call_api_func('imageOBJ',$v['nome'.$LG],1,"images/marca_".$v['id'].".jpg");
                    $marcas[] = array(
                      "id"              => $v['id'],
                      "title"           => $v['nome'.$LG],
                      "image"           => $img,
                      "short_content"   => $v['desc'.$LG],
                      "url"             => $v['link'],
                      "target"          => $v['target']
                    );
                }
            }
    
            $resp['brands'] = $marcas;
            
            $resp['footer_pages']      = call_api_func('get_menu', 3, 1);
            $resp['social_pages']      = call_api_func('get_redes_sociais');
            $resp['tag_manager_body']  = call_api_func('getTrackingTagManager',"Body");
        }
        
        $resp['expressions'] = call_api_func('get_expressions', 1);
        

        $fx->_iSetCache($_HPcacheid,serialize($resp), $CACHE_HOME);
    }
    
  
    
    
    if((int)$API_VERSION < 202202){           
         
        $user_id = (int)$_SESSION['EC_USER']['id'];
        $user_type = (int)$_SESSION['EC_USER']['type'];
        $client_id = (int)$_SESSION['EC_CLIENTE']['id'];                                                       
        $user_types = explode(",", $_SESSION['EC_USER']['tipo']);
        $site_channel = (int)$SITE_CHANNEL;
    
        foreach ($resp['menu_pages'] as $k => &$v) {

            #Remove Compra Rápida (Venda assistida) - Se 'Apenas exibir PVP Recomendado'
            if( $v['sublevel'] == 60 && $_SESSION['EC_USER']['exibir_lista_pvpr']==1 && $_SESSION['EC_USER']['b2b_only_pvpr']==1 ){
                unset($resp['menu_pages'][$k]);
                continue;           
            }          
        
            if(!is_channel_valid($v['crit_canal_tipos'], $site_channel)){
                unset($resp['menu_pages'][$k]);
                continue;
            }
        
            if ($v['users'] > 0 && !is_user_allowed($v['users'], $user_id, $user_type, $client_id)) {
                unset($resp['menu_pages'][$k]);
                continue;
            }
        
            $arr_type = explode(",", $v["type"]);
            if (count(array_intersect($arr_type, $user_types)) === 0 && $v["type"] !== "") {
                unset($resp['menu_pages'][$k]);
                continue;
            }
            
            #Verificar se subpaginas estão restritas a segmentos
            foreach ($v["childs"] as $kk => &$vv) {
                if (!is_channel_valid($vv['crit_canal_tipos'], $site_channel)) {
                    unset($v["childs"][$kk]);
                    continue;
                }
        
                $arr_type = explode(",", $vv["type"]);
                if (count(array_intersect($arr_type, $user_types)) === 0 && $vv["type"] !== "") {
                    unset($v["childs"][$kk]);
                    continue;
                }
        
                foreach ($vv["childs"] as $kkk => &$vvv) {
                    if (!is_channel_valid($vvv['crit_canal_tipos'], $site_channel)) {
                        unset($vv["childs"][$kkk]);
                        continue;
                    }
        
                    $arr_type = explode(",", $vvv["type"]);
                    if (count(array_intersect($arr_type, $user_types)) === 0 && $vvv["type"] !== "") {
                        unset($vv["childs"][$kkk]);
                    }
                }
            }
            


            # Shipping Express
            if( $v['sublevel'] == 74 ){

                if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ) $v['link_name'] = base64_decode($_COOKIE['SYS_EXP_ZIP']);
  
                $v['shipping_express']['theme'] = ['background_color' => $SHIPPING_EXPRESS_B_COLOR, 'title_color' => $SHIPPING_EXPRESS_COLOR];    

            }
            
        }
        
        $resp['footer_pages'] = remove_strict_menu_pages( $resp['footer_pages'] );
   

        # 2021-07-12 - Click & Collect
        $arr_click_collect = get_click_collect(1);
        $resp['click_collect'] = $arr_click_collect['click_collect'];
        
        
        
        #2020-03-06 - Definido por Serafim que não há overlays na homepage nem sequer welcome gift
        # IF para ser possivel pre-visualizar o welcome gift
        # 2022-04-06 - Alterado para que os welcome gifts com tempo para exibição do popup apareçam na homepage
        #if((int)$_GET['preview-wg']>0 || (int)$_GET['preview-pop']>0){
            $resp['campaigns'] = call_api_func('get_campaigns', 1);
        #}
        
        
        
        # 2020-05-26 - Campanha MA de recomendação de produtos em site - 9200
        $camp_r = verifyRecommendationCampaign(50);
        
        $resp['recommendation_campaign_active'] = 0; 
        if((int)$camp_r['automation']==50 || (int)$_GET['preview-ma']==9200) {
            $resp['recommendation_campaign_active'] = 1;
        }

        # Shipping Express
        if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){
            $resp['shipping_express']['theme'] = ['background_color' => $SHIPPING_EXPRESS_B_COLOR, 'title_color' => $SHIPPING_EXPRESS_COLOR];    
        }
    
    }
    

    # B2B - verifica pagamentos pendentes
    if((int)$B2B > 0 && is_numeric($userID) && $_SESSION['EC_USER']['b2b_dados_contabilisticos']>0){

        $sql_payment = "SELECT count(ec_m.id) as total 
                    FROM ec_pagamentos_emitidos ec_m
                    LEFT JOIN ec_encomendas_lines ec_l ON ec_m.id=ec_l.pid AND ec_l.tipo_linha=6 AND (ec_l.status>=0 AND ec_l.status!=100) AND ec_l.order_id!=0
                    WHERE ec_m.activo='1' AND ec_m.deleted='0' AND ec_m.estado_pagamento='0' AND ec_m.encomenda_id='0' AND ec_m.valor > 0 AND ec_m.cliente_id='".$userID."' AND ec_l.id is NULL";
        $res_payment = cms_query($sql_payment);
        $row_payment = cms_fetch_assoc($res_payment);

        $resp['notification_payment'] = (int)$row_payment["total"];
        $resp['notification_payment_url'] = "/account/index.php?id=56";
        $resp['notification_payment_expression'] = $resp['expressions'][683];


        # verifica pagamento dos documentos financeiros
        if((int)$CONFIG_OPTIONS['allow_payment_documents'] > 0 && (int)$resp['notification_payment'] == 0) {

            $sql_payment = "SELECT count(_tdocumentos.id) as total 
                            FROM _tdocumentos
                            LEFT JOIN ec_encomendas_lines ON _tdocumentos.id=ec_encomendas_lines.pid AND ec_encomendas_lines.tipo_linha=6 AND (ec_encomendas_lines.status>=0 AND ec_encomendas_lines.status!=100)
                            WHERE _tdocumentos.num NOT LIKE 'NC%' AND _tdocumentos.debito>0
                                AND _tdocumentos.estado_pagamento='0'
                                AND _tdocumentos.encomenda_id='0'
                                AND _tdocumentos.valor > _tdocumentos.valor_pago
                                AND _tdocumentos.id_user='".$userID."'
                                AND NOW() > _tdocumentos.data_vencimento
                                AND ec_encomendas_lines.id is NULL;";
            $res_payment = cms_query($sql_payment);
            $row_payment = cms_fetch_assoc($res_payment);

            $resp['notification_payment'] = (int)$row_payment["total"];
            $resp['notification_payment_url'] = "/account/index.php?id=43";
            $resp['notification_payment_expression'] = $resp['expressions'][765];

        }


    }
    
    $resp['shop'] = call_api_func('OBJ_shop_mini', 1);

    # STORE
    $resp['show_popup_address'] = 0;
    if((int)$B2B > 0 && (int)$CONFIG_OPTIONS['SHOW_POPUP_ADDRESSES'] == 1 && is_numeric($userID) ){
        $arr = get_pop_up_addresses();
        $resp = array_merge($resp, $arr);
    }
    
    
    
    # 2025-03-20
    @include(_ROOT.'/api/rcctrackproducts.php');
   

    return serialize($resp);


}

?>
