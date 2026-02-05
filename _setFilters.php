<?
function _setFilters(){
    
    global $userID, $LG, $MARKET, $CONFIG_NOHASH_FILTROS;
    
    
    if(count($_POST["filters"])<1 && count($_POST["filter"])<1){
        header('HTTP/1.0 400 Bad Request');
        echo "<script>location='$slocation'</script>";
        exit;            
    }
    
    if($_POST['page_id']==-100){ 
        $_POST['page_id'] = "DRIVEME";

        $page_driveme = call_api_func('get_line_table', '_trubricas', "nome$LG<>'' AND sublevel=62"); 
        if((int)$page_driveme["id"]>0){
            $page_id_temp = $_POST['page_id'];
            $_POST['page_id'] = $page_driveme["id"];
            applyFilters($_POST);             
            $_POST['page_id'] = $page_id_temp;
        }  
                       
    }
    
    
    if(is_callable('custom_setFilters')) {
        call_user_func('custom_setFilters');
    }
    
    
            
    
    applyFilters($_POST);
    
    
    /*if($_POST['page_id']!="DRIVEME"){    
        if(is_numeric($userID) ){
            $sql = "insert into searched set termo='".$_POST['page_id']."', tipo='1', total='1', id_cliente='".$userID."', lg='".$LG."', id_mercado='".$MARKET["id"]."', filtros='".serialize($_SESSION['filter_active'][$_POST['page_id']])."' on duplicate key update total=total+1, data=NOW(), filtros='".serialize($_SESSION['filter_active'][$_POST['page_id']])."' ";
            cms_query($sql);
        }else{
            $_SESSION["termos_pesquisa"][$term] = array(
                                                     "termo" =>$_POST['page_id'],
                                                     "tipo" =>"1",
                                                     "lg" => $LG,
                                                     "id_mercado" => $MARKET["id"],
                                                     "filtros"=> serialize($_SESSION['filter_active'])
                                                 );
        }
    }*/
    
    
    
    
    
    $arr = array();
    $arr[] = 1;
    
    if($CONFIG_NOHASH_FILTROS==1){
        $arr['filters_encode_raw'] = encodeFiltersRaw($_POST['page_id']);    
    }else{                                                  
        $arr['filters_encode'] = encodeFilters($_POST['page_id']);
    }
    
    
    
    

    return serialize($arr);
}   


function applyFilters($POST){
    
    #if( $_SESSION['filter_active'][$_POST['page_id']][$_POST['filter']['field']][$_POST['filter']['values']]>0 ){
    if( isset($_SESSION['filter_active'][$POST['page_id']][$POST['filter']['field']][$POST['filter']['values']]) ){
        unset( $_SESSION['filter_active'][$POST['page_id']][$POST['filter']['field']][$POST['filter']['values']] );

        if( empty( $_SESSION['filter_active'][$POST['page_id']][$POST['filter']['field']] ) )
            unset( $_SESSION['filter_active'][$POST['page_id']][$POST['filter']['field']] );

        if( empty( $_SESSION['filter_active'][$POST['page_id']] ) )
            unset( $_SESSION['filter_active'][$POST['page_id']] );

        if( empty( $_SESSION['filter_active'] ) )
            unset( $_SESSION['filter_active'] );
            
         
         # 2021-12-09
         # Se despicamos um filtro, então limpamos a sessão do último clicado, para que a seguir volte a ter opções desabilitadas    
         $_SESSION['filter_last_click'][$POST['page_id']] = $POST['filter']['field']; 
         
         
         if(count($_SESSION['filter_active'][$POST['page_id']][$POST['filter']['field']])==0){
            unset($_SESSION['filter_disabled'][$POST['page_id']][$POST['filter']['field']]);
            $_SESSION['filter_reiniciar'][$POST['page_id']] = $POST['filter']['field'];  
         }
         
    }else{
        if($POST['filter']['field']!="" && $POST['filter']['values']!=""){
            $_SESSION['filter_active']
                [$POST['page_id']]
                    [$POST['filter']['field']]
                        [$POST['filter']['values']] = $POST['filter']['values'];
                        
            # 2021-12-09
            # Se picamos um filtro, então guardamos a sessão do último clicado, para sabermos que neste filtro não se desabilitada opções                
            $_SESSION['filter_last_click'][$POST['page_id']] = $POST['filter']['field'];
            
            unset($_SESSION['filter_reiniciar'][$POST['page_id']]); 
        }
    }
    
 
    if(count($POST["filters"])>0){
    
        foreach($POST["filters"] as $k => $v){
            
            unset( $_SESSION['filter_active'][$POST['page_id']][$v['field']] );
            foreach($v["values"] as $k2 => $v2){
                $_SESSION['filter_active']
                    [$POST['page_id']]
                        [$v['field']]
                            [$v2['value']] = $v2['value'];
            }
            
            $_SESSION['filter_last_click'][$POST['page_id']] = $v['field'];
        }
        
        unset($_SESSION['filter_reiniciar'][$POST['page_id']]); 
    }
        
}
?>
