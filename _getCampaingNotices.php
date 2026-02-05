<?php

function _getCampaingNotices(){
    global $COUNTRY, $MARKET, $MOEDA, $CACHE_KEY, $LG, $fx;

    $arr_temp_campaings = array();

    $scope                  = array();
    $scope['COUNTRY_ID']    = $COUNTRY['id'];
    $scope['MARKET_ID']     = $MARKET['id']; 
    $scope['LG']            = $LG;
    
    $_cacheid       = $CACHE_KEY."CAMPAINGNOTICES_".implode('_', $scope);
    
    $dados = $fx->_GetCache($_cacheid);

    if ($dados!=false && !isset($_GET['nocache'])){
        $arr_temp_campaings = unserialize($dados);
    }else{ 

        $sql_campaings = "SELECT id, crit_tipo_cliente, tipo_utilizador, nome$LG as name, desc$LG as description, bask_avisos, avisos_desc$LG as notice 
                            FROM ec_campanhas 
                        WHERE NOW() between CONCAT(data_inicio, ' ',hora_inicio, ':00:00') and CONCAT(data_fim, ' ',hora_fim, ':59:59')
                            AND deleted='0' 
                            AND (crit_paises='' OR concat(',',`crit_paises`,',') LIKE '%,".$COUNTRY['id'].",%' ) 
                            AND (crit_mercado='' OR concat(',',`crit_mercado`,',') LIKE '%,".$MARKET['id'].",%' )
                            AND moeda='".$MOEDA["id"]."'
                            AND crit_codigo=''
                            AND recuperar_carrinho=0
                            AND welcome_gift=0
                            AND automation=0";

        $res_campaings = cms_query($sql_campaings);
        while($row_campaings = cms_fetch_assoc($res_campaings)){
            $arr_temp_campaings[] = $row_campaings;
        }

        $fx->_SetCache($_cacheid, serialize($arr_temp_campaings), 30);
    }
    
    $i = 0;
    $arr_campaings = array();
    foreach($arr_temp_campaings as $k => $v){
        
        if($i >= 5) continue;

        if ( $v['crit_tipo_cliente']!="" ){

            $tipo_validos   = explode(",", $v['crit_tipo_cliente']);
            $tipos_cliente  = explode(",", $_SESSION["EC_USER"]['tipo']);

            $exclui = 0;

            $intersect = array_intersect($tipo_validos, $tipos_cliente);

            if(count($intersect)<1) $exclui = 1;

            if( $exclui==1){
                continue;
            }
        }

        if((int)$v['tipo_utilizador']>0 && $v['tipo_utilizador']!=$_SESSION["EC_USER"]['tipo_utilizador']){
            continue;
        }

        $file = "images/ec_campanhas_mi_".$v["id"]."_".$LG.".jpg";
        if(!file_exists($_SERVER['DOCUMENT_ROOT']."/".$file)){
            $file = "images/ec_campanhas_mi_".$v["id"].".jpg";   
            if(!file_exists($_SERVER['DOCUMENT_ROOT']."/".$file)) continue;
        }
        
        
        if(trim($v["name"]) == "" || (($v["bask_avisos"] = 0 && trim($v["description"]) == "") || ($v["bask_avisos"] == 1 && trim($v["notice"]) == "")) ) continue;

        $subtitle = $v["description"];
        if($v["bask_avisos"] == 1) $subtitle = $v["notice"];

        $subtitle    = str_ireplace("$", "&dollar;", $subtitle);
        $subtitle    = str_ireplace("€", "&euro;", $subtitle);    

        $i++;
        $arr_campaings[] = array(
            "id"        => $v["id"],
            "title"     => $v["name"],
            "subtitle"  => $subtitle,
            "image"     => call_api_func('OBJ_image', $v["name"], 1, $file)   
        );

    }

    $arr["campaings"] = $arr_campaings;
    
    return serialize($arr);

}
