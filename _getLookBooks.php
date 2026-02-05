<?
function _getLookBooks($page_id=0){

    global $LG;
    global $MARKET;
    global $COUNTRY;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }


    $sql    = cms_query("SELECT * FROM b2c_magazines WHERE
                                      hidden='0' AND
                                      (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%') AND
                                      DATE_FORMAT( concat( dodia, ' ', hora_inicio, ':', minuto_inicio ) , '%Y-%m-%d %H:%i' )<=NOW() AND
                                      aodia>=CURDATE()
                                    ORDER BY ordem,id DESC");

    while($v = cms_fetch_assoc($sql)){
        $childs = array();
        $sql2   = cms_query("select * from b2c_magazines_lines where id='".$v['id']."' order by ordem");

        $imgLB  = array();
        $cam    = "images/shop_look_".$v['id'].".jpg";

        if(file_exists($cam)){
          $imgLB = call_api_func('imageOBJ',$v['nome'.$LG],1,"images/shop_look_".$v['id'].".jpg");
        }

        /*while($v2 = cms_fetch_assoc($sql2)){

            $img = array();
            $cam = "images/lookbook".$v2['unid']."_pt.jpg";
            if(!file_exists($cam)) continue;
            
            $img = call_api_func('imageOBJ',$v2['nome'.$LG],1,"images/lookbook".$v2['unid']."_pt.jpg");
            
            $prods = array();
            if( !empty($v2['art']) ){
                $skus = explode(",", $v2['art']);
                $sqlp = cms_query("select id from registos where sku in ('".implode("','",$skus)."')");
                
                while($vp = cms_fetch_assoc($sqlp)){
                    $prof_temp = call_api_func('get_product',$vp['id']);
                    if($prof_temp['id']>0) $prods[] = $prof_temp;
                }
            
            }

            $childs[] = array(
              "id"              => $v2['unid'],
              "title"           => $v2['nome'.$LG],
              "image"           => $img,
              "short_content"   => base64_encode($v2['desc'.$LG]),
              "childs"          => "",
              "products"        => $prods,
              "downloads"       => "",
              "url"             => $v2['link'],
              "positionV"       => $v2['pos_V_'.$LG],
              "positionH"       => $v2['pos_H_'.$LG],
              "color"           => $v2['text_color'.$LG]
            );
        }*/

        $lookbook[] = array(
          "id"              => $v['id'],
          "title"           => $v['nome'.$LG],
          "subtitle"        => $v['subtitulo'.$LG],
          "image"           => $imgLB,
          "short_content"   => base64_encode($v['desc'.$LG]),
          "childs"          => $childs,
          "products"        => "",
          "downloads"       => "",
          "url"             => $v['link'],
          "banner"          => call_api_func('OBJ_banner',$v['banner']),
          "positionV"       => $v['pos_V'],
          "positionH"       => $v['pos_H'],
          "color"           => $v['text_color']
        );
    }

    $arr = array();
    
    if($INFO_SUBMENU==1){
        $arr['page']  = call_api_func('pageOBJ', 48, 48);
    }else{
        $row          = call_api_func('get_pagina_modulos', 48, "_trubricas");
        $arr['page']  = call_api_func('OBJ_page', $row, 48, 0);
    }

    $caminho                    = call_api_func('get_breadcrumb', $page_id);
    $arr['page']['breadcrumb']  = $caminho;
    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  

    $arr['shop']                = call_api_func('shopOBJ');
    $arr['catalog_items']       = $lookbook;
    $arr['expressions']         = call_api_func('get_expressions',48);

    return serialize($arr);
}
?>
