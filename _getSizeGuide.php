<?

function _getSizeGuide($guide_id=0, $size="", $all=0){
    
    if ($guide_id==0){
       $guide_id  = (int)params('guide_id');
       $size      = params('size');
       $all       = params('all');
    }
    
    global $LG;
    global $COUNTRY;
    
    $arr = array();
        
    $sel = "registos_grelha_tamanhos_lines.*";
    if(trim($COUNTRY["scale_options"])!=""){
        $sel = "registos_grelha_tamanhos_lines.id,registos_grelha_tamanhos_lines.codigo,registos_grelha_tamanhos_lines.".$COUNTRY["scale_options"];
    }
    
    $arr_sizes = array();
    $arr_sizes_code = array();
    
    if($all==1){
    
        $sql = "SELECT ".$sel." 
                  FROM registos_grelha_tamanhos_lines 
                      INNER JOIN registos_tamanhos ON registos_grelha_tamanhos_lines.codigo=registos_tamanhos.codigo 
                  WHERE id_grelha_promo='".$guide_id."' 
                  ORDER BY registos_tamanhos.ordem,registos_tamanhos.nome".$LG." ";
        $res = cms_query($sql);  
        

        while($row = cms_fetch_assoc($res)){
            unset($row["id"]);            
            unset($row["id_grelha_promo"]);
            unset($row["lenght"]);
            unset($row["size"]);
            unset($row["integrado"]);
                        
            $arr_sizes_code[$row['codigo']] = $row;
                                     
                                                 
            $codigo = $row["codigo"];
            unset($row["codigo"]);
            $arr_sizes[$codigo] = $row;
        }
        
        #ksort($arr_sizes, SORT_NATURAL);
        $arr_sizes = array_values($arr_sizes);
                            
        #ksort($arr_sizes_code, SORT_NATURAL);
        $arr_sizes_code = array_values($arr_sizes_code);
        
    }else{
    
        if(strlen($size)>5){
            $size = base64_decode($size);
        }
        
        $sql = "SELECT ".$sel." FROM registos_grelha_tamanhos_lines WHERE id_grelha_promo='".$guide_id."' AND codigo='".$size."' LIMIT 0,1";
        $res = cms_query($sql);
        $row = cms_fetch_assoc($res);
        
        $arr_sizes = $row;
        unset($arr_sizes["id"]);
        unset($arr_sizes["codigo"]);
        unset($arr_sizes["id_grelha_promo"]);
        unset($arr_sizes["lenght"]);
        unset($arr_sizes["size"]);
        unset($arr_sizes["integrado"]);
        foreach($arr_sizes as $k => $v){
            if(trim($v)=="") unset($arr_sizes[$k]);
        }  
    }  
    
    
    $arr["guide_id"] = $guide_id;
    $arr["size_guide_id"] = $row["id"];
    $arr["size_guide_code"] = $row["codigo"];
    $arr["size_guide"] = $arr_sizes;
    $arr["size_guide_code"] = $arr_sizes_code;
    
    
    # 2025-10-09
    # Para a Salsa
    if(is_callable('custom_controller_size_guide')) {
        call_user_func_array('custom_controller_size_guide', array(&$arr));
    }
    
    
    return serialize($arr);
}

?>
