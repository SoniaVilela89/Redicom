<?

function _getProductServiceDetail($service_id=0, $extra=0){
    
    global $LG;
    
    if ($service_id > 0){
       $service_id = (int)$service_id;
       $extra = (int)$extra;       
    }else{
       $service_id = (int)params('service_id');     
       $extra = (int)params('extra');     
    }
    
    if((int)$extra == 1){
        $service = call_api_func("get_line_table","registos_configurador_extra", "id='".$service_id."'");
    
        $resp = array();
        
        if((int)$service["id"]==0){
            $resp["extra_configurator"]["answer"] = "";
            return serialize($resp);
        }
        
    
        $resp["extra_configurator"]["answer"] = $service["bloco".$LG];
        return serialize($resp);
    }
    
    $service = call_api_func("get_line_table","registos_servicos_grupo", "id='".$service_id."'");
    
    $resp = array();
    
    if((int)$service["id"]==0){
        $resp["service"]["condition"] = "";
        return serialize($resp);
    }
    

    $resp["service"]["condition"] = $service["bloco".$LG];
    return serialize($resp);
}

?>
