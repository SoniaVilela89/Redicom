<?


# ec_sms_logs - tipo > 1: transacionais; 2: pontos (balanco/expirados); 3: SMS Marketing Automation  

function _sendSMSExpirePoints(){

    global $SETTINGS_LOJA, $userID, $LG, $eComm;  
    
    if((int)$SETTINGS_LOJA["pontos"]["campo_1"]>1){
        
        if((int)$SETTINGS_LOJA["pontos"]["campo_10"]<1){
            $arr = array();
            $arr['0'] = 0;
            return serialize($arr);  
        }
        
        $sql_user = "SELECT u.id, u.f_externalPoints, u.f_externalPointsDateExpire, u.f_externalPointsExpire, u.telefone, u.pais_indicativo_tel, u.nome, u.id_lingua, u.pais 
                      FROM _tusers u 
                      WHERE 
                          NOT EXISTS ( SELECT 1 FROM ec_sms_logs sms WHERE sms.user_id = u.id AND sms.autor = 'cj_points_expire' AND sms.datahora >= CURDATE() AND sms.datahora < CURDATE() + INTERVAL 1 DAY AND sms.tipo = '2' ) 
                          AND u.sem_registo = 0 
                          AND u.activo = 1 
                          AND u.telefone != '' 
                          AND u.telefone > '0000000' 
                          AND u.lastlogin != '0000-00-00 00:00:00' 
                          AND u.lastlogin > DATE_SUB(now(), INTERVAL 6 MONTH) 
                      LIMIT 0, 100";
            
        $res_user = cms_query($sql_user);
        while($row_user = cms_fetch_assoc($res_user)){
        
            @cms_query('INSERT INTO ec_sms_logs (autor, user_id, tipo, template_id) VALUES ("cj_points_expire", "'.$row_user["id"].'", 2, 69)');
            $sid  = cms_insert_id();  
        
            $userID = $row_user["id"];
            
            $points_history = call_api_func('get_points_history', $row_user);
            
            $expire_points = (int)$points_history["expire_points"];
      
            if($expire_points==0) continue;
            
            $date_expire = $points_history["date_expire_points"];

            if(strtotime($date_expire)!=strtotime(date('Y-m-d', strtotime("+8 days")))) continue;
            
            if(strtotime($date_expire)<strtotime(date('Y-m-d'))) continue;
            
            

            $id_idioma = $row_user['id_lingua'];
                  
            $LANGUAGE = call_api_func("get_line_table","ec_language", "id='".$id_idioma."'");
                 
            if(trim($LANGUAGE["code"])=='' || strlen($LANGUAGE["code"])!=2) $LANGUAGE["code"] = 'pt';
            
            if($LANGUAGE["code"]=='es') $LANGUAGE["code"]='sp';
            elseif($LANGUAGE["code"]=='en') $LANGUAGE["code"]='gb';
            
            
            $LG = $LANGUAGE["code"];



            
            if((int)$SETTINGS_LOJA["pontos"]["campo_6"]>0){
          
                $mercado  = $eComm->marketInfo($row_user['pais']);
               
                $moeda   = $eComm->moedaInfo($mercado['moeda']);
                
                $expire_points  =  ($expire_points*$moeda["valuePoint"]).$moeda['abreviatura'];
            }
      
      
            #2025-01-29
            $id_pais_prefix = $row_user['pais'];
            if((int)$row_user['pais_indicativo_tel'] > 0) $id_pais_prefix = $row_user['pais_indicativo_tel'];
      
            $arr_data = array(
                "telemovel"       =>  $row_user["telefone"],
                "lg"              =>  $LG,
                "POINTS"          =>  $expire_points,
                "USER_ID"         =>  $userID,
                "DATE_EXPIRE"     =>  $date_expire,
                "CLIENT_NAME"     =>  $row_user["nome"],
                "int_country_id"  =>  $id_pais_prefix
            );  
            
            $encode_data = serialize($arr_data);
            $encode_data = gzdeflate($encode_data,  9);
            $encode_data = gzdeflate($encode_data, 9);
            $encode_data = urlencode($encode_data);
            $encode_data = base64_encode($encode_data);
            
            require_once '_sendSMSGeneral.php';
            _sendSMSGeneral("69", $encode_data);
            
           
            @cms_query('UPDATE ec_sms_logs SET obs="'.cms_escape(serialize($arr_data)).'", numero="'.$row_user["telefone"].'" WHERE id="'.$sid.'" ');

        }
        
    }
    
    $arr = array();
    $arr['0'] = 1;
  
    return serialize($arr);
}
?>
