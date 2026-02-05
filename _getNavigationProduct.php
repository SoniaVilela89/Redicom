<?
  
function _getNavigationProduct($page_id=null, $category=null, $reference=null, $action=null){
    global $LG, $CACHE_KEY, $CACHE_QUERY_PRODUTOS, $fx, $CONFIG_LISTAGEM_QTD;
    global $SUPRESS_REDIRECT_SIZES, $slocation, $CONFIG_SEMANTIC, $SEMANTIC_V2_FIRST_ID, $CONFIG_SEMANTIC_VALUES;
    
    if ($page_id > 0){
       $page_id     = (int)$page_id;
       $cat         = (int)$category;
       $reference   = $reference;
       $action      = (int)$action;
    }else{
       $page_id     = (int)params('page_id');
       $cat         = (int)params('cat');
       $reference   = params('reference');
       $action      = (int)params('action');
    }
        
    $scope = array();
    $scope['pageID']        = $page_id;
    $scope['pagina_mae']    = $cat;
    $scope['priceList']     = $_SESSION['_MARKET']['lista_preco'];
    $scope['_MARKET_ID']    = $_SESSION['_MARKET']['id'];
    $scope['ORDER']         = $_SESSION['order_active'][$page_id];
    $scope['LG']            = $LG;
    $scope['PAIS']          = $_SESSION['_COUNTRY']['id'];
    $scope['FILTERS']       = serialize($_SESSION['filter_active'][$page_id]);
    $_cacheid               = $CACHE_KEY."LIST".md5(serialize($scope));
        
    $dados = $fx->_GetCache($_cacheid, $CACHE_QUERY_PRODUTOS);
     
    if($dados==false){
        return serialize(array("0"=>"0"));  
    }  
    
    $arr_dados = $dados;
    if((int)$arr_dados[$reference]==0){
        return serialize(array("0"=>"0"));  
    }

    $position_product = 1;
    while(key($arr_dados) != $reference){ 
      next($arr_dados);
      $position_product++;
    }
    
    $verify_position = key($arr_dados);
    $count_total_prod = count($arr_dados);
    
    if(trim($verify_position)==""){
        return serialize(array("0"=>"0"));  
    }  
    
    if($action==3){
        $arr_dados_prev = $arr_dados;
        $val_prev = prev($arr_dados_prev);
        if(trim($val_prev)==""){
            $val_prev = end($arr_dados_prev);
        }
        
        $arr_dados_next = $arr_dados;
        $val_next = next($arr_dados_next);
        if(trim($val_next)==""){
            $val_next = reset($arr_dados_next);
        }
        
        
        
        $prod_p         = call_api_func("get_line_table","registos", "id='".$val_prev."'");
        $imagens_p      = getImagens($prod_p['sku'], $prod_p['sku_group'], $prod_p['sku_family']);
        $img_selected_p = call_api_func('imageOBJ',$imagens_p['nome'.$LG],1,reset($imagens_p),'productOBJ');
        
        $prod_n         = call_api_func("get_line_table","registos", "id='".$val_next."'");
        $imagens_n      = getImagens($prod_n['sku'], $prod_n['sku_group'], $prod_n['sku_family']);
        $img_selected_n = call_api_func('imageOBJ',$imagens_n['nome'.$LG],1,reset($imagens_n),'productOBJ');
        
        $arr = array();
        $arr["pid_previous"] = $val_prev;
        $arr["pid_next"] = $val_next;
        $arr["pid_previous_image"] = $img_selected_p;        
        $arr["pid_next_image"] = $img_selected_n;
        return serialize($arr);
    
    }
    
    if($action==1){
      $val = prev($arr_dados);
      $position_product--;
    }else{
      $val = next($arr_dados);
      $position_product++;
    }

    if(trim($val)=="" && $action==2){
      $val = reset($arr_dados);
      $position_product = 1;
    }elseif(trim($val)=="" && $action==1){
      $val = end($arr_dados);
      $position_product = $count_total_prod;
    }
    
    $pc = $position_product/$CONFIG_LISTAGEM_QTD;
    $pc = ceil($pc);
    if((int)$pc<1) $pc = 1;
    
    
    $v = call_api_func('get_line_table', 'registos', "id='".$val."'");
    
    $PID_FINAL = $v['id'];
    if((int)$SUPRESS_REDIRECT_SIZES==0){          
        $PROD_SKUGROUP = call_api_func('get_line_table', 'registos', "sku_family='".cms_escape($v['sku_family'])."' AND cor='".$v['cor']."' ORDER BY id");             
        if((int)$PROD_SKUGROUP['id']>0 && (int)$PROD_SKUGROUP['id']!=$v['id']){            
            $PID_FINAL = $PROD_SKUGROUP['id'];  
        } 
    }
    

    $url                 = "index.php?pid=".$PID_FINAL;
   
    if($CONFIG_SEMANTIC==1){
        global $se, $SEMANTIC_V2_FIRST_ID;
        $url_semantico = '';   
        foreach($CONFIG_SEMANTIC_VALUES as $key => $value){   
            if($v[$key]>0){                    	                   
	            	$string = $se->createSUrl(array($value), $v[$key], $LG);
                    if(trim($string)!='') $url_semantico .= '/'.$string;   
            }
        }  
        
        $string = $se->createSUrl(array("registos"), $v['id'], $LG);
        if(trim($string)!='') $url_semantico .= '/'.$string;

        if((int)$SEMANTIC_V2_FIRST_ID>0 && $SEMANTIC_V2_FIRST_ID<=$PID_FINAL){
            $url=$slocation."/".$idiomas_convertidos[$LG].$url_semantico."_p".$PID_FINAL.".html?";                            
        }else{
            $url=$slocation."/".$idiomas_convertidos[$LG].$url_semantico."/item_".$PID_FINAL.".html?";        
        }
    } 
    $url .= "&id=".$page_id."&cat=".(int)$cat."&pc=".$pc;
    $url = str_replace('?&', '?', $url);
    
    $arr = array();
    $arr["url"] = $url;
    return serialize(array("0"=>$arr));  
    
}

?>
