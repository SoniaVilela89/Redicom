<?

#Update date: 19/11/2018

function _getHeader($page_id=0, $cat=0){

    global $tabela_paginas, $fx, $pid, $CACHE_HEADER_FOOTER, $MOEDA, $LG, $API_MENU_SEM_LIMITE, $CACHE_KEY, $EXIBIR_MENU_TODOS_PRODUTOS, $row;   
    global $API_VERSION, $SHOW_4_LEVEL_MENU, $ID_MENU_SUPERIOR, $id, $PESQUISA_MIN_LENGTH, $SITE_CHANNEL;
    global $SHIPPING_EXPRESS_COLOR, $SHIPPING_EXPRESS_B_COLOR;
    global $B2B, $CONFIG_OPTIONS;

    # 2022-07-13
    # Um vez que o sublevel no api_index é subtituido por vezes devido a várias validações estamos a guardar para voltar a definir corretamente, 
    # porque neste ficheiro a variavel $row é subtituida na linha 77
            
    $sublevel = $row["sublevel"];  
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $cat = (int)params('cat');
    }

    if( $page_id==0 ) $page_id=1;

  
    if($tabela_paginas=='') $tabela_paginas = "_trubricas";

    $resp = array();
             
    $scope = array();
    $scope['PAIS']  = $_SESSION['_COUNTRY']['id'];
    $scope['LG']    = $_SESSION['LG'];
    $scope['ID']    = $page_id;
    $scope['CAT']   = $cat; 
    $_HDcacheid   = $CACHE_KEY."HD".md5(serialize($scope));  
    
    $dados = $fx->_iGetCache($_HDcacheid);
    
    if ($dados!=false && !isset($_GET['nocache']))
    {  
        $resp = unserialize($dados);        
    } else {

                     


        $menu_id = 2;
        if($ID_MENU_SUPERIOR>0) $menu_id = $ID_MENU_SUPERIOR;

        if((int)$API_MENU_SEM_LIMITE==1){                     
            $resp['menu_pages'] = call_api_func('getMenu', $menu_id, $page_id, $cat);
        }else{                                  
            $resp['menu_pages'] = call_api_func('get_menu', $menu_id, $page_id, $cat);
        }
        

        if((int)$EXIBIR_MENU_TODOS_PRODUTOS==1){
            if((int)$API_MENU_SEM_LIMITE==1){
                $resp['menu_pages_id10'] = call_api_func('pageOBJ', 10, $page_id, $cat);       
            }else{
                $row_page_id10 = call_api_func('get_pagina', 10, "_trubricas");
                if($row_page_id10['hidden']==0 && $row_page_id10['hidemenu']==0){
                    $resp['menu_pages_id10'] = call_api_func('OBJ_page', $row_page_id10, $page_id, $cat);
                    $resp['menu_pages_id10']['childs'] = call_api_func('get_menu', 10, $page_id, $cat);
                } 
            }                                                                        
                        
            if(!empty($resp['menu_pages_id10']) && $resp['menu_pages_id10']['id']>0){            
                $resp['menu_pages'] = array_merge(array($resp['menu_pages_id10']), $resp['menu_pages']);
                unset($resp['menu_pages_id10']);
            }
        }
        
        $row = call_api_func('get_pagina', $page_id, $tabela_paginas);
        $resp['selected_page'] = call_api_func('OBJ_page', $row, $page_id, $cat, 0, $tabela_paginas);
        
        if($cat>0){
            $row_cat = call_api_func('get_pagina', $cat, "_trubricas");
            $resp['selected_page']['with_login'] = (int)$row_cat['login'];
        }
        
        $resp['social_pages'] = call_api_func('get_redes_sociais');
        $resp['expressions'] = call_api_func('get_expressions');
        
        $CACHE_HEADER_FOOTER = getTimeCache($CACHE_HEADER_FOOTER, 'header');
        
        
        # 2025-03-11
        # Acrescentado o if count para só fazer cache se forem enontrados menus 
        if(count($resp['menu_pages'])>0) {
            $fx->_iSetCache($_HDcacheid, serialize($resp), $CACHE_HEADER_FOOTER);
        }
         
    }


    # APP - Marcas menu
    if($SITE_CHANNEL == 4) {

        @include($_SERVER['DOCUMENT_ROOT']."/custom/shared/config_pwa.inc");

        # Marcas em destaque + Nível 1 na vertical
        if($CONFIG_PWA_MENU_LAYOUT == 3) {

            $scope = array();
            $scope['PAIS']  = $_SESSION['_COUNTRY']['id'];
            $scope['LG']    = $_SESSION['LG'];
            
            $_BRAPPcacheid   = $CACHE_KEY."APPB".md5(serialize($scope)); 
            
            $dados = $fx->_GetCache($_BRAPPcacheid);

            if ($dados!=false && !isset($_GET['nocache'])) {
                $marcas = unserialize($dados);
            } else {

                $marcas = array();

                $q = "SELECT `id`, `nome$LG`, `link`, `pwa_link`
                        FROM `registos_marcas`
                        WHERE `hidden` = 0 AND (`paises`='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' )
                        GROUP BY `id`
                        ORDER BY `ordem`, `nome$LG`, `id` DESC";
                $sql = cms_query($q);

                while($v = cms_fetch_assoc($sql)){
                    if(count($marcas)>=10) continue;

                    $img = array();
                    if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/marca_".$v['id'].".jpg") ){
                        $marcas[] = array(
                          "id"              => $v['id'],
                          "title"           => $v['nome'.$LG],
                          "image"           => call_api_func('imageOBJ',$v['nome'.$LG],1,"images/marca_".$v['id'].".jpg"),
                          "url"             => (trim($v['pwa_link']) != '') ? $v['pwa_link'] : $v['link']
                        );
                    }
                }

                $fx->_SetCache($_BRAPPcacheid, serialize($marcas), $CACHE_HEADER_FOOTER);
            }

            $resp['brands'] = $marcas;
        }

    }

    
    
    # 2024-12-09
    # Sessão para no footer não mandar a variavel para HTML para o HTML não duplicar os includes do popup de inicar sessão para aceder a uma página
    if($resp['selected_page']['with_login']>0) $_SESSION['PAGE_WITH_LOGIN'] = 1;
    else unset($_SESSION['PAGE_WITH_LOGIN']);
    
    
    
    
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
    
        # 2024-12-10
        # Sessão que é verificada no controlador footer para passar informação ao HTMl se é para incluir o html do popup de inicar sessão para aceder a uma página
        if($v['with_login']>0 && $_SESSION['PAGE_WITH_LOGIN']==0) $_SESSION['PAGE_WITH_LOGIN'] = 1;
        
        
                                                   
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
            $vv["childs"] = array_values($vv["childs"]); #para reiniciar as chaves para não dar problemas nos menus criados por javascript
        }
        $v["childs"] = array_values($v["childs"]); #para reiniciar as chaves para não dar problemas nos menus criados por javascript
        
        
        # Shipping Express
        if( $v['sublevel'] == 74 ){

            if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ) $v['link_name'] = base64_decode($_COOKIE['SYS_EXP_ZIP']);

            $v['shipping_express']['theme'] = ['background_color' => $SHIPPING_EXPRESS_B_COLOR, 'title_color' => $SHIPPING_EXPRESS_COLOR];    

        }
        
    }
                  
      

    if((int)$API_VERSION < 202202){
        $resp['shop'] = call_api_func('OBJ_shop_mini');
    }else{
        $resp['shop'] = call_api_func('OBJ_shop_mini_base');
    }
   
   
    
    # 2020-03-23 - definido por Serafim que nas landing pages não pode ter popup nenhum
    #if( ($row['sublevel']!=41 && $row['id']!=1) || (int)$_GET['preview-wg']>0 || (int)$_GET['preview-pop']>0) {
    # 2022-04-06 - Alterado para que os welcome gifts com tempo para exibição do popup apareçam na homepage 
    if( $row['sublevel'] != 41 || (int)$_GET['preview-wg'] > 0 || (int)$_GET['preview-pop'] > 0 ) {
        $is_home = ( $id != 1 ) ? 0 : 1;
        $resp['campaigns'] = call_api_func('get_campaigns', $is_home);
    }
    
    
    # 2020-04-09 - Shoppings Tools Expedições
    $resp['verifyExpInfo'] = 0;
    if((int)$pid>0){
        $resp['verifyExpInfo'] = verifyExpeditionInfo();
    }
    
    
    # 2021-07-12 - Click & Collect
    $arr_click_collect = get_click_collect(0);        
    $resp['click_collect'] = $arr_click_collect['click_collect'];
    
    
    if( (int)$pid > 0 ){
        
        $product = call_api_func("get_line_table", "registos", "id='".$pid."'");
        
        if( $product['generico30'] != "" && $product['generico30'] != 0 ){
        
            $row_geo = hasGeoLimitedDelivery();
            if( count( array_intersect( explode(",", $product['generico30']), explode(",", $row_geo['shipping_ids']) ) ) > 0 && (int)$row_geo['id'] > 0 ){
                
                $resp['geo_limited_delivery']["active"] = 1;    
                
                $theme = -1;
                if( (int)$row_geo['theme'] > 0 ) $theme = $row_geo["theme"];
                
                $theme_info = getThemeColorInfo($row_geo["theme"]);
                $resp["geo_limited_delivery"]["theme"] = $theme_info;
    
                if( trim($_COOKIE["USER_ZIP"]) != "" ) $resp["geo_limited_delivery"]['zip'] = base64_decode($_COOKIE["USER_ZIP"]);
                
            }
        
        }
        
    }

    # Shipping Express
    if( trim($_COOKIE['SYS_EXP_ZIP']) != '' ){
        $resp['shipping_express']['theme'] = ['background_color' => $SHIPPING_EXPRESS_B_COLOR, 'title_color' => $SHIPPING_EXPRESS_COLOR];    
    }


    # 2023-12-05
    $resp['search_min_length'] = 3;
    if((int)$PESQUISA_MIN_LENGTH>0) $resp['search_min_length'] = $PESQUISA_MIN_LENGTH;

    # STORE
    $resp['show_popup_address'] = 0;
    if((int)$B2B > 0 && (int)$CONFIG_OPTIONS['SHOW_POPUP_ADDRESSES'] == 1 && is_numeric($userID)){
        $arr = get_pop_up_addresses();
        $resp = array_merge($resp, $arr);
    }



    # 2025-06-11
    # Salsa
    if(is_callable('custom_controller_header')) {
        call_user_func_array('custom_controller_header', array(&$resp));
    }
    
    

    # 2022-07-13
    # Repor valor correto que foi guardado em cima    
    $row["sublevel"] = $sublevel;
    
    return serialize($resp);
}



?>
