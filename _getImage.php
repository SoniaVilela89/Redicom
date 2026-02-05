<?

function _getImage(){
       
    global $fx, $sslocation, $cdn_location, $API_QUALIDADE_IMG, $CONFIG_IMAGE_SIZE;
    
       
    $imageLoc = params("src");
    
    $image = explode("/api/api.php", $_SERVER['REQUEST_URI']);
    $image = "api/api.php".$image[1];
                       
    $imageLoc = str_ireplace(".jpg", "", $imageLoc);
    
    $width    = (int)params("width");
    $height   = (int)params("height");
    $crop     = (int)params("crop");
    $file     = base64_decode($imageLoc);
    
    
    $POS     = (int)params("pos");
   
    $ext = explode('.', $file);
    $ext = array_reverse($ext);
    
    
    
    $compressao = true;
    if(trim($API_QUALIDADE_IMG)!='') $compressao = false; 
    

    $fmi = filemtime('../'.$file);
    $name = $fmi."_".urlencode(md5($image)).".".$ext[0];


    if( $width==0 && $height==0 ){
        $width=200;
    }
    
    if( $width==-1 && $height==-1 ){
        list($width, $height, $crop) = explode(',', $CONFIG_IMAGE_SIZE['productOBJ']['small']);
    }
     
    $image_extension = exif_imagetype($_SERVER['DOCUMENT_ROOT'].'/'.$file);
      
    if($image_extension==1 || $crop==4){
    
        ob_clean();
        /*header('Content-Type: image/gif');
        header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 60*500));
        header('Cache-Control: max-age=3600');
        header('Pragma: private');
        readfile($_SERVER['DOCUMENT_ROOT'].'/'.$file);*/
        
        header('Content-Type: image/gif');
        header("Location: ".$cdn_location.$file, true, 301);
        exit;      
    
    }/*elseif($image_extension==3 || $crop==4){

        ob_clean();
        
        #header('Content-Type: image/png');
        #header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 60*500));
        #header('Cache-Control: max-age=3600');
        #header('Pragma: private');
        #readfile($_SERVER['DOCUMENT_ROOT'].'/'.$file);
        
        header("Location: ".$sslocation.'/'.$file, true, 301);
        exit;
    } */

    
    if($crop==0) $crop=2;
     
     
    $path_final = $_SERVER['DOCUMENT_ROOT']."/temp/";  
    
    $url_img = $cdn_location."temp/".$name;                   
    
    # No POS já não se tem acesso à pasta custom/temp_pos para escrever lá as imagens 
    /*if($POS==1) {
        $path_final = $_SERVER['DOCUMENT_ROOT'].'/custom/temp_pos/';
        $url_img = $cdn_location."custom/temp_pos/".$name;  
    }*/
            
            
    $myimage = $fx->makeimage($_SERVER['DOCUMENT_ROOT'].'/'.$file,$width,$height,0,0,$crop,'FFFFFF','',1,0,'FFFFFF','FFFFFF',0, $path_final.$name, '', false, $compressao);

       
    ob_clean();
    header('Content-Type: image/jpeg');
    header("Location: ".$url_img, true, 301);
    

    /*
    header('Content-Type: image/jpeg');
    //header('Content-Disposition: attachment; filename='.basename($myimage));
    header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 60*500));
    header('Cache-Control: max-age=3600');
    header('Pragma: private');
    readfile($myimage); */

    exit;
}

 
?>
