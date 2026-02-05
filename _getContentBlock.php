<?    
function _getContentBlock($page_id=0, $content_block_id=null, $show_all=0, $device='', $product_list=0)
{
    global $fx, $LG, $INFO_SUBMENU, $INFO_NAV_PAG, $CACHE_HOME, $COUNTRY, $userID, $CACHE_KEY, $detect, $db_name_cms;
        
        
    if($page_id==0){
        $page_id = (int)params('page_id');
        $content_block_id = (int)params('content_block_id');
        $show_all = (int)params('show_all');
        $device = params('device');
    }
     
                         

    $row = call_api_func('get_pagina', $page_id, "_trubricas"); 
        
    if($content_block_id!=0){
        $row["ContentBlock"] = $content_block_id;
    }            
              

    $bloco = get_line_table_api_obj("ContentBlocks", "id='".$row["ContentBlock"]."'");
    
    if(trim($row['ContentBlock2'])!='' && (int)$product_list == 0){
        
        $sql    = "SELECT * 
                    FROM ContentBlocks 
                    WHERE id IN (".$row['ContentBlock2'].") 
                        AND (Countries='' OR CONCAT(',',Countries,',') LIKE '%,".$COUNTRY["id"].",%' )
                        AND ((StartDate='0000-00-00' OR StartDate<=CURDATE()) AND (EndDate='0000-00-00' OR EndDate>=CURDATE()))   
                    ORDER BY id DESC ";
                    
        $query  = cms_query($sql);
        while($res = cms_fetch_assoc($query)){
            
            if ( $res['Types']!="" ){

                #if(!is_numeric($userID)){ continue; }
                if( trim($_SESSION['EC_USER']['tipo']) == '' ){ continue;}
    
                $tipo_validos   = explode(",", $res['Types']);
                $tipos_cliente  = explode(",", $_SESSION["EC_USER"]['tipo']);
    
                $exclui = 0;
    
                $intersect = array_intersect($tipo_validos, $tipos_cliente);
    
                if(count($intersect)<1) $exclui = 1;
    
                if( $exclui==1){
                    continue;
                }
            }
            
            $row["ContentBlock"] = $res['Id'];
            break;                                                         
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
    
    if($device!='') $mobile = $device;
    
    
    # 2020-03-27 - para promoções limitadas a tipo de cliente
    $tipo_user = 0;
    if(is_numeric($userID)){
        if($_SESSION["EC_USER"]["sem_registo"]==0) $tipo_user = 1;
    }
    
    $PROMOS = getAvailablePromoForUser();    

    $scope                      = array();
    $scope['page_id']           = $page_id;    
    $scope['BLOCK']             = 'CI_'.$row["ContentBlock"]; #Ao mudar esta chave ter em atencao ao ficheiro data_triggers + modules/content_blocks/actions do BO
    $scope['LG']                = $_SESSION['LG'];
    $scope['DEVICE']            = $mobile;       
    $scope['PRICE_LIST']        = $_SESSION['_MARKET']['lista_preco'];
    $scope['PAIS']              = $_SESSION['_COUNTRY']['id'];        
    $scope['TIPO_USER']         = $tipo_user;        
    $scope['PROMO']             = implode(',', $PROMOS["promos"]);
    
    $_HPcacheid = $CACHE_KEY."CBL_".implode('_', $scope);
    
    $dados = $fx->_iGetCache($_HPcacheid, $CACHE_HOME);

    if ($dados!=false && $_GET['nocache']!=1 && $show_all==0){         

        $arr = unserialize($dados); 
         
    } else { 
                    
        $arr = array();
    
        if($page_id>0){
            
            $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);
            
            $caminho = call_api_func('get_breadcrumb', $page_id);
            $arr['selected_page']['breadcrumb'] = $caminho;
        }
        
        if($INFO_NAV_PAG == 1){   
            $pai = $caminho[1]['id_pag'];
            $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
        }   
        
    
        $arr['content_blocks'] = get_content_blocks($row["ContentBlock"], $show_all, $device);
                
                
        $table_style = "`$db_name_cms`.ContentBlocksStyles";                                                                        
        $style = get_line_table_api_obj($table_style, "Id='1'");
    
        $arr['content_blocks_style'] = $style;

        $arr['expressions'] = call_api_func('get_expressions', $page_id);

        $fx->_iSetCache($_HPcacheid, serialize($arr), $CACHE_HOME);
    }
    
    
    if($page_id>0){
        $arr['shop'] = call_api_func('OBJ_shop_mini');  
    }
    
    
    
    # 2025-06-23
    @include(_ROOT.'/api/rcctrackproducts.php');
    
    
          
    
    return serialize($arr);
   
}

?>
