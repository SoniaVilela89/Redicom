<?
function _resetFilters(){
  global $CONFIG_NOHASH_FILTROS, $LG;

  $page_id = (int)params('page_id');
  $field = params('field');
   
  if($page_id==-100){
      $page_id = "DRIVEME";
      $page_driveme = call_api_func('get_line_table', '_trubricas', "nome$LG<>'' AND sublevel=62"); 
      if((int)$page_driveme["id"]>0){
          $page_id_temp = $page_id;
          $page_id = $page_driveme["id"];
          deleteFilters($page_id, $field);
          $page_id = $page_id_temp;                                    
      }        
  }
  
  deleteFilters($page_id, $field);    
  
  $arr = array();
  $arr[] = 1;
  
  if($CONFIG_NOHASH_FILTROS==1){
      $arr['filters_encode_raw'] = encodeFiltersRaw($page_id);    
  }else{                                                  
      $arr['filters_encode'] = encodeFilters($page_id);
  }
  return serialize($arr);
  
} 
  
function deleteFilters($page_id, $field){
    
    if($field!=""){
        if($field=="preco"){
            unset( $_SESSION['filter_active'][$page_id]["preco_max"] );
            unset( $_SESSION['filter_active'][$page_id]["preco_min"] );
        }else{
            unset( $_SESSION['filter_active'][$page_id][$field] );
        } 
    }else{
        unset( $_SESSION['filter_active'][$page_id] );
    }
    
    if( empty( $_SESSION['filter_active'][$page_id] ) )
            unset( $_SESSION['filter_active'][$page_id] );
            
    if( empty( $_SESSION['filter_active'] ) )
        unset( $_SESSION['filter_active'] );

}
  
        
?>
