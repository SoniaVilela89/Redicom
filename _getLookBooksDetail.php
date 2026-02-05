<?

function _getLookBooksDetail($page_id=0, $look_id=0){
    
    global $LG;
    global $MARKET;
    global $COUNTRY;
    global $INFO_SUBMENU;
    global $userID;
    global $LOOKBOOK_NO_PRODUCTS;
    global $LOOKBOOK_SEM_STOCK;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $look_id = (int)params('look_id');
    }
    
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    
    
    $more_wehere = " AND hidden='0' ";
    if($row['sublevel']==49){
        $more_wehere = '';
    } 

    $sql = cms_query("SELECT *
                FROM b2c_magazines
                WHERE id='$look_id' $more_wehere AND
                      (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%') AND
                      DATE_FORMAT( concat( dodia, ' ', hora_inicio, ':', minuto_inicio ) , '%Y-%m-%d %H:%i' )<=NOW() AND
                      aodia>=CURDATE()
                LIMIT 0,1");
                
    $v = cms_fetch_assoc($sql);


    $childs = array();
    $sql2   = cms_query("select * from b2c_magazines_lines where id='".$v['id']."' order by ordem, nome$LG ASC, id DESC");

    $imgLB  = array();
    $cam    = "images/shop_look_".$v['id'].".jpg";
    
    if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
        $imgLB = call_api_func('imageOBJ',$v['nome'.$LG],1,"images/shop_look_".$v['id'].".jpg");
    }
    
    
    $look_image = array();
    
    
    if($LOOKBOOK_SEM_STOCK==1) $LOOKBOOK_CATAL_IGNORA_STOCK = 1;

    while($v2 = cms_fetch_assoc($sql2)){

        $img = array();
        $cam = "images/lookbook".$v2['unid']."_pt.jpg";
        if(!file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)) continue;
        
        $img = call_api_func('imageOBJ',$v2['nome'.$LG],1,"images/lookbook".$v2['unid']."_pt.jpg");
        
        $prods = array();
        $arr_pids = array();
        if( !empty($v2['art']) ){
            $skus = explode(",", $v2['art']);
            
            $sqlp = cms_query("select id from registos where sku in ('".implode("','",$skus)."') or sku_group in ('".implode("','",$skus)."') GROUP BY `sku_group`");
            while($vp = cms_fetch_assoc($sqlp)){
                $arr_pids[] = $vp["id"];
                if((int)$LOOKBOOK_NO_PRODUCTS==0){
                    $prof_temp = call_api_func('get_product',$vp['id'], '', $page_id);
                    if($prof_temp['id']>0) {
                        $prof_temp["wishlist"] = call_api_func('verify_product_wishlist', $prof_temp['sku_family'], $userID);
                        $prods[] = $prof_temp;
                    }
                }
            }
        }
        
        if((int)$v2["image1_offset"]>1 && (int)$v2["image2_offset"]>0 && (int)$v2["image2_offset"]!=2){
            foreach ($prods as $key=>$value) {
                if(isset($value["images"][$v2["image1_offset"]-1]) && isset($value["images"][$v2["image2_offset"]-1])){
                    $prods[$key]["featured_image"] = $value["images"][$v2["image1_offset"]-1];
                    $prods[$key]["images"][1] = $value["images"][$v2["image2_offset"]-1];
                }   
            }
        }
        
        $temp = array(
              "id"            => $v2['unid'],
              "title"         => $v2['nome'.$LG],
              "image"         => $img,
              "short_content" => base64_encode($v2['desc'.$LG]),
              "childs"        => "",
              "products"      => $prods,
              "products_ids"  => $arr_pids,
              "downloads"     => "",
              "url"           => $v2['link'],
              "positionV"     => $v2['pos_V_'.$LG],
              "positionH"     => $v2['pos_H_'.$LG],
              "color"         => $v2['text_color'.$LG]
        );
        
        $childs[] = $temp;
        
        if((int)$_GET['idld']>0 && $_GET['idld']==$v2['unid']){
            $look_image = $temp;
        }

    }
    


    $pdf_lookbook = array();
    $cam = "downloads/lookbook/file".$v['id'].".pdf";
    if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
        $rowFile = array('nome' => 'pdf', 'nome'.$LG => $v['nome'.$LG]);
        $pdf_lookbook = call_api_func('fileOBJ',$v, $cam);
    }

    $lookbook[] = array(
      "id"            => $v['id'],
      "title"         => $v['nome'.$LG],
      "subtitle"      => $v['subtitulo'.$LG],
      "image"         => $imgLB,
      "short_content" => base64_encode($v['desc'.$LG]),
      "childs"        => $childs,
      "products"      => "",
      "downloads"     => $pdf_lookbook,
      "url"           => $v['link'],
      "banner"        => call_api_func('OBJ_banner',$v['banner']),
      "positionV"     => $v['pos_V'],
      "positionH"     => $v['pos_H'],
      "color"         => $v['text_color']
    );

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page'] = call_api_func('pageOBJ', 48, 48);
    }else{
        $row          = call_api_func('get_pagina_modulos', 48, "_trubricas");
        $arr['page']  = call_api_func('OBJ_page', $row, 48, 0);
    }   

    $caminho                    = call_api_func('get_breadcrumb', $id);
    $arr['page']['breadcrumb']  = $caminho;
    
    
    if((int)$_GET['idld']>0 && $look_image['id']>0){
    
        $last = end($arr['page']['breadcrumb']);
        
        $temp = array("name" => $lookbook[0]['title'],
                      "link" => $last['link']."&idl=".$look_id,
                      "id_pag" => $last['id_pag'], 
                      "sublevel" => 11,
                      "without_click" => 1);
                      
        $arr['page']['breadcrumb'][] = $temp;            
    }
    

    $arr['shop']                = call_api_func('shopOBJ');
    
    $arr['catalog_items']       = $lookbook;
    $arr['expressions']         = call_api_func('get_expressions',48);
    return serialize($arr);
}
?>
