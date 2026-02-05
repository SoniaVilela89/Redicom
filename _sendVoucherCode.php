<?
function _sendVoucherCode($user=-1, $data){
    global $fx;
    global $LG;
    global $MARKET, $slocation;
    
    
    
    if($user==-1){
        $UserID = (int)params('user');
        $Info = params('data');
    }else{
        $UserID = (int)$user;
        $Info = $data;
    }
  
    
    $Info = base64_decode($Info);
    $Info = urldecode($Info);
    $Info = gzinflate($Info);
    $Info = gzinflate($Info);
    $Info = unserialize($Info);
    
          
    $User = array();
    if($UserID>0) $User = call_api_func("get_line_table","_tusers", "id='$UserID'");                       

    $temp = get_info_geral_email($LG, $MARKET, '', $User);
    $temp['LINK'] = $slocation;
    
    $y = array_merge($temp, $Info);

        
    $content = cms_real_escape_string(serialize($y));
    saveEmailInBD_Marketing($User['email'], $Info['ASSUNTO'], $content, $User['id'], 0, 'Welcome Gift - Email após registo', 1, 0, '1', $Info['ID_CAMPANHA'], $y['view_online_code']);
    

    $arr    = array();
    $arr[]  = 1;
    return serialize($arr);

}

?>
