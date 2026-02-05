<?
function _sendSMSBalancePoints($user_id=null)
{
    if(is_null($user_id)){
        $user_id = (int)params('user_id');
    }
    
    global $idiomas_possiveis, $eComm, $SETTINGS_LOJA, $userID, $LG;
    
    $userID = $user_id;
    
    if((int)$SETTINGS_LOJA["pontos"]["campo_1"]<2){
        $arr = array();
        $arr['0'] = 0;
        return serialize($arr);  
    }
    
    if((int)$SETTINGS_LOJA["pontos"]["campo_10"]<1){
        $arr = array();
        $arr['0'] = 0;
        return serialize($arr);  
    }
    
    $sql_sms_send = "SELECT * FROM ec_sms_logs WHERE autor='cj_points_remaining' and user_id='".$userID."' AND DATE(datahora) = '".date("Y-m-d")."' AND tipo=2 LIMIT 0,5";
    $res_sms_send = cms_query($sql_sms_send);
    $num_sms_send = cms_num_rows($res_sms_send);

    if($num_sms_send>4){
        $arr = array();
        $arr['0'] = 0;
        return serialize($arr);
    }
    
//     $sql_user = "SELECT t.*, e.idioma_user, e.moeda_id, e.id as enc_id FROM _tusers t INNER JOIN ec_encomendas e ON t.id=e.cliente_final WHERE t.id='".$userID."' GROUP BY t.id";
//     $res_user = cms_query($sql_user);
//     $row_user = cms_fetch_assoc($res_user);



    $sql_user = "SELECT id, f_externalPoints, f_externalPointsDateExpire, f_externalPointsExpire, pais, telefone, nome, pais_indicativo_tel FROM _tusers WHERE id='".$userID."' LIMIT 1";
    $res_user = cms_query($sql_user);
    $row_user = cms_fetch_assoc($res_user);
    
    if((int)$row_user["id"]==0 || $row_user["pais"]==0){
        $arr = array();
        $arr['0'] = 0;
        return serialize($arr);      
    }
    
    
    $sql_enc = "SELECT id, idioma_user, moeda_id FROM ec_encomendas WHERE cliente_final='".$userID."' ORDER BY id DESC LIMIT 1";
    $res_enc = cms_query($sql_enc);
    $row_enc = cms_fetch_assoc($res_enc);
    
    $LG = $row_enc["idioma_user"];
    
    if((int)$row_enc["id"]==0){
    
        $sql_pais = "SELECT id,idioma FROM ec_paises WHERE id='".$row_user['pais']."' LIMIT 1";
        $res_pais = cms_query($sql_pais);
        $row_pais = cms_fetch_assoc($res_pais);
        
        if((int)$row_pais["idioma"]==0){
            $arr = array();
            $arr['0'] = 0;
            return serialize($arr);
        }
        
        $sql_lang = "SELECT code FROM ec_language WHERE id='".$row_pais['idioma']."' LIMIT 1";
        $res_lang = cms_query($sql_lang);
        $row_lang = cms_fetch_assoc($res_lang);
    
        $lang = strtolower($row_lang["code"]);        
        if(trim($lang)=='' || strlen($lang)!=2) {
            $lang = 'pt';
        }

        if($lang=='es') $lang='sp';
        elseif($lang=='en') $lang='gb';
        
        $LG = $lang;
        
        $row_enc["moeda_id"] = '7';
             
    }


    $points_history = call_api_func('get_points_history', $row_user);

    $total_points = (int)$points_history["total_points"];
    $date_expire = $points_history["date_expire_points"];
    
    if((int)$total_points==0){
        $arr = array();
        $arr['0'] = 0;
    
        return serialize($arr);
    }
    
    if((int)$SETTINGS_LOJA["pontos"]["campo_6"]>0){
  
        $MOEDA = call_api_func("get_line_table","ec_moedas", "id='".$row_enc["moeda_id"]."'"); 
 
        $total_points  =  ($total_points*$MOEDA["valuePoint"]).$MOEDA['abreviatura'];
    }


    # 2025-01-29
    $id_pais_prefix = $row_user['pais'];
    if((int)$row_user['pais_indicativo_tel'] > 0) $id_pais_prefix = $row_user['pais_indicativo_tel'];

    $arr_data = array(
        "telemovel"       =>  $row_user["telefone"],
        "lg"              =>  $LG,
        "POINTS"          =>  $total_points,
        "DATE_EXPIRE"     =>  $date_expire,
        "USER_ID"         =>  $user_id,
        "ORDER_ID"        =>  $row_enc['id'],
        "CLIENT_NAME"     =>  $row_user["nome"],
        "int_country_id"  =>  $id_pais_prefix
    );  

    $data = serialize($arr_data);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    
    require_once '_sendSMSGeneral.php';
    _sendSMSGeneral("68", $data);
    
    @cms_query('INSERT INTO ec_sms_logs (autor, obs, user_id, tipo, numero, template_id) VALUES ("cj_points_remaining", "'.cms_escape(serialize($arr_data)).'", "'.$userID.'", 2, "'.$row_user["telefone"].'", 68)');
    
    $arr = array();
    $arr['0'] = 1;

    return serialize($arr);
}
?>
