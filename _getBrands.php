<?
  
function _getBrands($page_id=0, $pwa=0){
     
    global $LG, $INFO_SUBMENU, $INFO_NAV_PAG, $COUNTRY, $fx, $CACHE_KEY;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $pwa     = (int)params('pwa');
    }

    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    
    
    parse_str($row['parametros'], $parametros);
    

    $marcas = array();
    $brands = array();
    

    $scope = array();
    $scope['PAIS']        = $_SESSION['_COUNTRY']['id'];
    $scope['LISTA']       = $_SESSION['_MARKET']['lista_preco'];
    $scope['LG']          = $LG;
    $scope['parametros']  = $row['parametros'];
    $scope['pwa']         = $pwa;
    $_cacheid             = $CACHE_KEY."BR_".implode('_', $scope); 


    $dados = $fx->_GetCache($_cacheid, 60);
    
    if ($dados!=false && $_GET['nocache']!=1 )
    {

        $marcas = $dados['marcas'];
        $brands = $dados['brands'];
    } else {
          
        $q = "SELECT registos_marcas.* 
                FROM registos_marcas 
                    INNER JOIN registos ON registos_marcas.id=registos.marca AND registos.activo=1
                    INNER JOIN registos_precos ON registos.sku = registos_precos.sku AND registos_precos.`data`<=CURRENT_DATE()  
                WHERE registos.activo=1 
                    AND registos.nome$LG<>''
                    AND registos_precos.idListaPreco='".$_SESSION['_MARKET']['lista_preco']."'
                    AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) 
                GROUP BY registos_marcas.id
                ORDER BY registos_marcas.ordem, registos_marcas.nome$LG, registos_marcas.id DESC";

        $sql    = cms_query($q);
                                
        while($v = cms_fetch_assoc($sql)){

            if($pwa > 0 && trim($v['pwa_link']) != '') $v['link'] = $v['pwa_link'];
            
            list($domain, $query) = explode('?', $v['link']);
            
            parse_str($query, $resp);
    
            $query = array_merge($resp, $parametros);
            
    
            if(count($query)>0){
                $v['link'] = $domain.'?'.http_build_query($query);
            }
                            
            
            if((int)$v["hidden"]>0) continue;
            
            $letter = "";
            $img = array();
            if( file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/marca_".$v['id'].".jpg") ){
                $img = call_api_func('imageOBJ',$v['nome'.$LG],1,"images/marca_".$v['id'].".jpg");
    
                $marcas[] = array(
                  "id"              => $v['id'],
                  "title"           => $v['nome'.$LG],
                  "image"           => $img,
                  "short_content"   => $v['desc'.$LG],
                  "childs"          => "",
                  "products"        => "",
                  "downloads"       => "",
                  "url"             => $v['link'],
                  "target"          => $v['target']
                );
            }
            $letter = strtoupper(clearVariable($v['nome'.$LG][0]));
            if(is_numeric($letter) || !preg_match('/^[a-zA-Z0-9]+/', $letter)) $letter = "#";
    
            $brands[$letter][] = array(
              "id"              => $v['id'],
              "title"           => $v['nome'.$LG],
              "image"           => $img,
              "short_content"   => $v['desc'.$LG],
              "childs"          => "",
              "products"        => "",
              "downloads"       => "",
              "url"             => $v['link'],
              "target"          => $v['target']
            );
        }
        
        $resp_arr = array();
        $resp_arr["brands"] = $brands;
        $resp_arr["marcas"] = $marcas;
        $fx->_SetCache($_cacheid, $resp_arr, 60);
    
    
    }
    
    
    

    $glossarioBrands = array();
    for($i=65;$i<=90;$i++){
        $glossarioBrands[] = array(
            "letter" => chr($i),
            "catalog_items" => $brands[chr($i)]
        );
    }
    $glossarioBrands[] = array(
        "letter" => "#",
        "catalog_items" => $brands["#"]
    );
    

    $arr = array();
               
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 49, 49);
    }else{
        $row = call_api_func('get_pagina_modulos', 49, "_trubricas");
        $arr['page'] = call_api_func('OBJ_page', $row, 49, 0);
    }

    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb'] = $caminho;
      
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['catalog_items'] = $marcas;
    $arr['glossary_catalog_items'] = $glossarioBrands;
    $arr['expressions'] = call_api_func('get_expressions',49);

    return serialize($arr);
}

?>
