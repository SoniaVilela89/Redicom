<?

function _getAccountPoints($page_id=null)
{

    global $userID;
    global $LG;
    global $SETTINGS_LOJA, $MOEDA;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }

    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');    
    $arr['fidelization'] = call_api_func('get_fidelization', $arr['page'], $arr['points']["historic_points"]);
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    
    $points_history = call_api_func('get_points_history');
    
    $points_history["expire_points_value"] = $points_history["expire_points"];
    
    if((int)$SETTINGS_LOJA["pontos"]["campo_6"]>0){
        
        $points_history["total_points"]   =  $MOEDA['prefixo'].number_format($points_history["total_points"]*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];
        $points_history["expire_points"]  =  $MOEDA['prefixo'].number_format($points_history["expire_points"]*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];
        foreach($points_history["historic_points"] as $k => $v){
            $points_history["historic_points"][$k]["debit_points_value"]     =  $points_history["historic_points"][$k]["debit_points"]*$MOEDA["valuePoint"];
            $points_history["historic_points"][$k]["credit_pointos_value"]   =  $points_history["historic_points"][$k]["credit_pointos"]*$MOEDA["valuePoint"];
            $points_history["historic_points"][$k]["available_points_value"] =  $points_history["historic_points"][$k]["available_points"]*$MOEDA["valuePoint"];
            
            $points_history["historic_points"][$k]["debit_points"]           =  $MOEDA['prefixo'].number_format($points_history["historic_points"][$k]["debit_points"]*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];
            $points_history["historic_points"][$k]["credit_pointos"]         =  $MOEDA['prefixo'].number_format($points_history["historic_points"][$k]["credit_pointos"]*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];
            $points_history["historic_points"][$k]["available_points"]       =  $MOEDA['prefixo'].number_format($points_history["historic_points"][$k]["available_points"]*$MOEDA["valuePoint"],  $MOEDA['decimais'], $MOEDA['casa_decimal'], $MOEDA['casa_milhares']).$MOEDA['sufixo'];
        }
        $arr['account_expressions']['408'] = "";
    }
    
    $arr['points'] = $points_history;
    return serialize($arr);

}

function get_fidelization($pages, $history=array()){

    global $_SERVIDOR, $sslocation, $CONFIG_OPTIONS;
        
    
    $url_shared = "";
    if(trim($_SESSION["EC_USER"]["hash_ma"])!=""){
        
        require_once(_ROOT."/api/lib/shortener/shortener.php");
        
        $LINK       = $sslocation."/?pf=".$_SESSION["EC_USER"]["hash_ma"];
    
        $_SERVIDOR  = $_SERVER["SERVER_NAME"];

        $short_url = short_url($LINK , $_SERVIDOR);
      
        $short_url = explode("rdc.la/", $short_url->short_url);
        
        $url_shared = $short_url[1];

    }
    
    
    $arr_f =  array();
    $arr_f["fidelizacao"]       = $CONFIG_OPTIONS[fidelizacao];
    $arr_f["url_shared"]        = $url_shared;
    
    $arr_f["page"]["title"]     = $pages['childs'][0]["link_name"];
    $arr_f["page"]["subtitle"]  = $pages['childs'][0]["short_content"];
    $arr_f["page"]["content"]   = $pages['childs'][0]["content"];
    
    $arr_f["pages"]             = array();
    $i = 0;
    foreach($pages['childs'][0]['childs'] as $k => $v){
        $arr_f["pages"][$i] = array(
                                  "id"      => $v["id"],
                                  "title"   => $v["link_name"],
                                  "content" => $v["content"]
                                  );
        $i+=2;
    }
    
    if($CONFIG_OPTIONS[fidelizacao_avaliacao]>0){
        $temp[] = array(
                      "title"   =>  estr2(499), 
                      "content" =>  $CONFIG_OPTIONS[fidelizacao_avaliacao]." ".estr2(350),
                      );
    }
    
    if($CONFIG_OPTIONS[fidelizacao_partilha_encomenda]>0){
        $temp[] = array(
                      "title"   =>  estr2(500), 
                      "content" =>  $CONFIG_OPTIONS[fidelizacao_partilha_encomenda]." ".estr2(350),
                      );
    }
    
    $arr_f["pages"][1] = array(
                                  "id"        => estr2(498),
                                  "title"     => estr2(498),
                                  "content"   => "",
                                  "benefits"  => $temp
                                  );
    ksort($arr_f["pages"]);
    
    $points_purchase  = 0;
    $count_purchase   = 0;
    $points_review    = 0;
    $count_review     = 0;
    $points_share     = 0;
    $total_share      = 0;

    
    foreach($history as $k => $v){
        if($v["type"]==1){
            $count_purchase++;
            $points_purchase += $v["credit_pointos"];
        }
        
        if($v["type"]==3){
            $count_review++;
            $points_review += $v["credit_pointos"];
        }
        
        if($v["type"]==4){
            $total_share++;
            $points_share += $v["credit_pointos"];
        }
    
    }
    
    $arr_f["total_purchase"]  = $count_purchase;
    $arr_f["points_purchase"] = $points_purchase;
    $arr_f["total_review"]    = $count_review;
    $arr_f["points_review"]   = $points_review;
    $arr_f["points_share"]    = $points_share;
    $arr_f["total_share"]     = $total_share;

    
    return $arr_f;
}

?>
