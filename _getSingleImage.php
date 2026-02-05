<?

function _getSingleImage($width, $height, $crop, $sku, $return=0, $idx=0, $base64=0, $all_images=0){
       
    global $fx, $sslocation, $cdn_location, $API_QUALIDADE_IMG, $CONFIG_IMAGE_SIZE, $CONFIG_EXTENSOES_IMGS;
    
    if ($sku == ""){
        $sku          = params("src");
        $width    	  = (int)params("width");
        $height   	  = (int)params("height");
        $crop     	  = (int)params("crop");
        $return       = (int)params("return");
        $idx     	    = (int)params("idx");
        $base64       = (int)params("base64");
        $all_images   = (int)params("all_images");
    }   
    
    if((int)$base64 == 1) $sku = base64_decode($sku);
                  
    $row = cms_fetch_assoc(cms_query("SELECT id, sku_family, sku_group, sku FROM registos WHERE sku='$sku' OR sku_group='$sku'"));

    if ( trim($row['sku']) != '' ) $sku = $row['sku'];
    
    $sku_family = $row['sku_family'];
    $sku_group 	= $row['sku_group'];
    
    $sku 		= str_replace('/', '-', $sku);
    $sku_family = str_replace('/', '-', $sku_family);
    $sku_group 	= str_replace('/', '-', $sku_group);
    
    
    
    $caminho  = 	$_SERVER['DOCUMENT_ROOT'].'/';
    $pasta_img  = 	"images_prods_static";     
    
       
  
   
    
    # 2022-07-05
    # Como é usado nos emails primeiro deve ser a ordem desde o SKU até ao mais geral 
    
    if ($idx == 0){
		 
        if( file_exists($caminho.$pasta_img."/SKU/$sku".".jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU/$sku".".jpg";
        } elseif ( file_exists($caminho.$pasta_img."/SKU/$sku"."_1.jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU/$sku"."_1.jpg";
        } elseif( file_exists($caminho.$pasta_img."/SKU/$sku".".JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU/$sku".".JPG";
        } elseif ( file_exists($caminho.$pasta_img."/SKU/$sku"."_1.JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU/$sku"."_1.JPG";
        } elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group".".jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group".".jpg";
        } elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group"."_1.jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group"."_1.jpg";    
        } elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group".".JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group".".JPG";
        } elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group"."_1.JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group"."_1.JPG";
        } elseif( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family".".jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family".".jpg";
        } elseif ( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_1.jpg") ) {
        	$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_1.jpg";  
        } elseif( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family".".JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family".".JPG";
        } elseif ( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_1.JPG") ) {
        	$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_1.JPG";
        } else {
        	$src_img = $caminho."/sysimages/no-image4.jpg";
        }                    
  
  	} else {
  		
    		if ( file_exists($caminho.$pasta_img."/SKU/$sku"."_$idx.jpg") ) {
    			$src_img = $caminho.$pasta_img."/SKU/$sku"."_$idx.jpg";
    		} elseif ( file_exists($caminho.$pasta_img."/SKU/$sku"."_$idx.JPG") ) {
    			$src_img = $caminho.$pasta_img."/SKU/$sku"."_$idx.JPG";
    		} elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group"."_$idx.jpg") ) {
    			$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group"."_$idx.jpg";    
    		} elseif( file_exists($caminho.$pasta_img."/SKU_GROUP/$sku_group"."_$idx.JPG") ) {
    			$src_img = $caminho.$pasta_img."/SKU_GROUP/$sku_group"."_$idx.JPG";
    		} elseif ( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_$idx.jpg") ) {
    			$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_$idx.jpg";  
    		} elseif ( file_exists($caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_$idx.JPG") ) {
    			$src_img = $caminho.$pasta_img."/SKU_FAMILY/$sku_family"."_$idx.JPG";
    		} else {
    			$src_img = $caminho."/sysimages/no-image4.jpg";
    		}
  		
  	}     
  
  
  
    if($src_img==$caminho."/sysimages/no-image4.jpg" && $all_images==1){
        
        $ext = $CONFIG_EXTENSOES_IMGS;
        if($ext == "") {
            $ext = array('jpg', 'png');
        }
    
        $img_all = "";


        # Pesquisa ao nível do SKU
        foreach($ext as $key => $value)
        {
            for($i=2;$i<11; $i++)
            {
                $cam = $caminho.$pasta_img.'/SKU/'.$sku.'_'.$i.'.'.$value;
                if(file_exists($cam)) {
                    $img_all = $cam;
                    break;
                }
            }
            
            if(trim($img_all)!='') break;
        }
      
    
        # Pesquisa ao nível do SKU Group
        if(trim($img_all)=='' && trim($sku_group)!=''){  
            foreach($ext as $key => $value)
            {
                for($i=2;$i<11; $i++)
                {
        			      $cam = $caminho.$pasta_img.'/SKU_GROUP/'.$sku_group.'_'.$i.'.'.$value;
                    if(file_exists($cam)) {  
                        $img_all = $cam;
                        break;
                    }
                }
                
                if(trim($img_all)!='') break;
            }
            
            
        }
            
        # Pesquisa ao nível do SKU Family
        if(trim($img_all)=='' && trim($sku_family)!=''){
            foreach($ext as $key => $value)
            {
                for($i=2;$i<11; $i++)
                {
        			      $cam = $caminho.$pasta_img.'/SKU_FAMILY/'.$sku_family.'_'.$i.'.'.$value;
                    if(file_exists($cam)) {
                        $img_all = $cam;
                        break;
                    }
                }
                
                if(trim($img_all)!='') break;
            }
            
           
        }
    
        if(trim($img_all)!='') $src_img = $img_all;
             
    }
    
    
         

    if( $return == 5 ){
        return str_replace($caminho, "", $src_img);
    }
	
    if( $width==0 && $height==0 ){
        $width=200;
    }
    
    
    if($crop==0) 
		$crop=2;
  
                      
    $formato = 1;
    
  
    
    $myimage = $fx->makeimage($src_img,$width,$height,0,0,$crop,'FFFFFF','',$formato);
    
 
    if($return==1){
        return $myimage; 
    }
    
    if($return==2){
        $arr['image'] = $myimage;
        return serialize($arr); 
    }


		// Para utilização em BO (Serafim Costa)
    if($return==3){
        ob_clean();
		    header('Content-Type: image/jpeg');
		    readfile($myimage); 
		    exit;
    }
    
		// Para utilização em BO (Serafim Costa)
    if($return==4){
        ob_clean();
        if ($cdn_location)
		    echo $sslocation . "/" . str_ireplace("..", "", $myimage);
		    exit;
    }  
    
    $url_img = $cdn_location . "/" . $myimage;  
    

    ob_clean();
    header('Content-Type: image/jpeg');
    header("Location: ".$url_img, true, 301);

    exit;

}
 
?>
