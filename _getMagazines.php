<?
function _getMagazines($page_id=0){
    
    global $LG;
    global $fx; 
    global $CONFIG_HOTSPOT;  
    global $COUNTRY;
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }
    
    
  
    $arr = array();

    $row = call_api_func('get_pagina_modulos', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);


    $mag_row = call_api_func("get_line_table","hot_geral", "id='".$row['hot_spot']."' AND activado!=0 AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' ) ");
    
    $arr_hot = array();
    $arr_hot['id'] = $mag_row['id'];
    $arr_hot['title'] = $mag_row['nome'.$LG];
    $arr_hot['content'] = base64_encode($mag_row['desc'.$LG]);
    $arr_hot['short_content'] = base64_encode( $mag_row['subtitulo'.$LG] );
    
    
    $size_img = explode(",", $CONFIG_HOTSPOT);
    
    $resp = array();
    
    if($page_id>0){
      $sql_hot  = cms_query("SELECT * FROM hotspots WHERE id_geral='".$row['hot_spot']."'");
      while($hot_spot = cms_fetch_assoc($sql_hot)){
        $cam="images/hotspot".$hot_spot['id'].".jpg";
        $ximg='';
        if(file_exists($cam))
        {   
          
          $image = getimagesize($cam);
          $w = $image[0];
          $h = $image[1];  
          
          $fmi = filemtime('../'.$cam);
          $name = $fmi."_".md5($cam).".jpg";        
         
          $img_hotspots = $fx->makeimage($_SERVER['DOCUMENT_ROOT'].'/'.$cam,$size_img['0'],$size_img['1'],0,0000,$size_img['2'],"FFFFFF","",JPG,0,"FFFFFF","FFFFFF", 0, $_SERVER['DOCUMENT_ROOT']."/temp/".$name, '');

          $resp_hot = array();
          $sql_line = cms_query("SELECT * FROM hotspots_lines WHERE unid='".$hot_spot['id']."'");
          while($hot_line = cms_fetch_assoc($sql_line)){

            $produto = call_api_func('get_product', $hot_line['id_produto']);
            
            if($produto['id']<1) continue;
            if($produto['price']['value']<1) continue;
            
            $le = ($hot_line['xposi'] * $size_img['0']) / $w;
            $to = ($hot_line['yposi'] * $size_img['1']) / $h;
    
            $wi = ($hot_line['width'] * $size_img['0']) / $w;
            $he = ($hot_line['height'] * $size_img['1']) / $h;
            
            $resp_hot[] = array(
                "id" => $hot_line['id'],
                "to" => $to,
                "le" => $le,
                "width" => $wi,
                "height" => $he,
                "product" => $produto
                
            );
          }
          
          $imageObj = array(
            "alt" =>$hot_spot['nome'.$LG],
            "position" => 1,
            "source" => $img_hotspots,
            "resource_url" => $img_hotspots,
            "width" => $size_img[0],
            "height" => $size_img[1]           
          );
          
          $resp[] = array(
              "id" => $hot_spot['id'],
              "nome" => $hot_spot['nome'.$LG],
              "image" => $imageObj,
              "hotspots" => $resp_hot
          );
        }
      }
    }
  
    $arr_hot['parts'] = $resp; 
       
    $arr['catalog_items'][] = $arr_hot;
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['expressions'] = call_api_func('get_expressions', $page_id);

    return serialize($arr);
}
?>
