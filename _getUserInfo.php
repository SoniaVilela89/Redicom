<?php

function _getUserInfo()
{
    global $userID, $CONFIG_TEMPLATES_PARAMS;
    
    $arr = array();
    
    $arr["shop"] = OBJ_shop_mini(0, 1);
                
    if($CONFIG_TEMPLATES_PARAMS['list_allow_compare']==1){
        $s    = "SELECT * FROM registos_comparador WHERE id_cliente='$userID' AND status='0' ORDER BY id DESC LIMIT 0,3 ";
        $q    = cms_query($s);
        $nr   = cms_num_rows($q);
        
        $arr["shop"]['comparator'] = $nr;
    }
           
    return serialize($arr);
}
?>
