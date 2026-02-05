<?php
function _getNotificationBar(){

    global $LG, $fx, $CACHE_KEY, $COUNTRY;
    
    $key = $CACHE_KEY."pushBarNot_".$COUNTRY["id"]."_".$LG;
    $dados = $fx->_GetCache($key);
    
    $return = array();
    
    if(isset($_COOKIE["notifbarshow"])) return serialize($arr); 

    if ($dados!=false && !isset($_GET['nocache']) ){
        $return = unserialize($dados);  
    }else{  
        
        $max_limit = 5;
        $var_count = 0;
        $var_position = '';
        $return_position = '';
        $sql_config = cms_query("SELECT * FROM b2c_push_bar WHERE active=1 AND (countries = '' OR CONCAT(',',countries,',') LIKE '%,".$COUNTRY['id'].",%') AND (dodia='0000-00-00' OR dodia<=CURDATE()) AND (aodia='0000-00-00' OR aodia>=CURDATE()) ORDER BY (ordem = ''), ordem, id DESC LIMIT ".($max_limit*2));
            
        while ( $row_config = cms_fetch_assoc($sql_config) ) {
       
            if( $var_count >= $max_limit ) continue;
          
            if( $row_config["id"]==0 || strlen($row_config[message]) == 0 ) continue;

            if( strlen($var_position)>0 && $var_position != $row_config['position'] ) continue;
            
            $var_position = $row_config['position']; //Só exibe da mesma posição 
            $var_count++;
            
            if (strlen(trim($row_config["url"]))>0) $row_config["url"]  = str_ireplace("index.php", "/index.php", $row_config["url"]);
            
            if (strlen($row_config["message$LG"])>0) $row_config["message"] = str_replace("'", "´", $row_config["message$LG"]);
            
            if( (int)$row_config['theme'] >= 0 ){
                $theme_info = getThemeColorInfo($row_config['theme']);
                $row_config["forecolor"] = $theme_info['title_color'];
                $row_config["backcolor"] = $theme_info['background_color'];
           }

           $arr[] = array(
                //"position"          => $row_config["position"],  #Passou para fora do array para retrocompatibilidade 
                "background_color"  => "#".$row_config["backcolor"],
                "text_color"        => "#".$row_config["forecolor"],
                "url"               => $row_config["url"],
                "message"           => $row_config["message"],
                "marquee"           => $row_config['horizontal_movement']
           );
           
           if( empty($return_position) ){
              $return_position = $row_config["position"];
           }
 
        } 
        
        if( $return_position == '' ){
            return;
        }
        
        $return = Array(
          "position" => $return_position,
          "content" => $arr
        );
        
        $fx->_SetCache($key, serialize($return), 60); 
    }
    
    return serialize($return);
    
}
?>
