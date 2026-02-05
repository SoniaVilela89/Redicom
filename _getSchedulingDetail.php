 <?

function _getSchedulingDetail($page_id=0, $new_id=0){

    if ($page_id==0){
       $page_id = (int)params('page_id');
       $new_id = (int)params('new_id');
    }

    global $fx;
    global $LG;
    global $COUNTRY;
    global $INFO_SUBMENU;    
    

    $scheduling_actual = array();


    $sql   = cms_query("SELECT * FROM agendamentos
                        WHERE nome$LG<>'' AND
                            dodia<=CURDATE() AND
                            aodia>=CURDATE() AND                             
                            (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY["id"].",%' )
                        ORDER BY agendamento_dodia DESC, ordem, id DESC ");

    
    $schedulings   = array();
    
    while($v = cms_fetch_assoc($sql)){
        $temp         = call_api_func('OBJ_scheduling',$v);
        $temp['url']  = "index.php?id=$page_id&idn=".$v['id'];
        $schedulings[]       = $temp;
        
        if($v['id']==$new_id){
            $scheduling_actual = $temp;
        }
        
    }

    $resp = array();
    
    if($INFO_SUBMENU==1){
        $arr['page']  = call_api_func('pageOBJ', 94, 94);
    }else{
        $row          = call_api_func('get_pagina', 94, "_trubricas");
        $arr['page']  = call_api_func('OBJ_page', $row, 94, 0);
    } 
    
    $resp['selected_scheduling'] = $scheduling_actual;
    
    $caminho    = call_api_func('get_breadcrumb', $page_id);
    $caminho[]  = array(
        "name" => $resp["selected_scheduling"]["title"],
        "link" => $resp["selected_scheduling"]["url"],
        "without_click" => 1
    );
    $resp['page']['breadcrumb'] = $caminho;

    
    $resp['page']['breadcrumb'] = $caminho;
    $resp['articles']           = $schedulings;     
    $resp['scheduling']         = scheduling($scheduling_actual);
    $resp['shop']               = call_api_func('OBJ_shop_mini');
    $resp['expressions']        = call_api_func('get_expressions',94);
    return serialize($resp);
}

function scheduling($scheduling_actual){
    $schedul = array();
    $schedul['week'] = array(
        "segunda"       => get_schedul_days($scheduling_actual['scheduling']['segunda']),
        "terca"         => get_schedul_days($scheduling_actual['scheduling']['terca']),
        "quarta"        => get_schedul_days($scheduling_actual['scheduling']['quarta']),
        "quinta"        => get_schedul_days($scheduling_actual['scheduling']['quinta']),
        "sexta"         => get_schedul_days($scheduling_actual['scheduling']['sexta']),
        "sabado"        => get_schedul_days($scheduling_actual['scheduling']['sabado']),
        "domingo"       => get_schedul_days($scheduling_actual['scheduling']['domingo'])
    );
    
    $schedul['exceptions']  = get_schedul_exceptions($scheduling_actual['id']);
    $schedul['logs']        = get_schedul_logs($scheduling_actual['id']);
                                                  

    return $schedul;
}

#retorna o array dos horarios da semana
function get_schedul_days($ids){

    $temp = array();
    $days = explode(",", $ids);
    foreach ($days as $key=>$value) {
        $s = cms_fetch_assoc(cms_query("SELECT * FROM agendamentos_horas WHERE id = $value "));	
        if($s['id'] !=""){
            $key = (int)str_replace(":","",$s['hora']);
            $temp[$key] = array("id" => $s['id'], "hour" => $s['hora']);
        }
    }
    ksort($temp);

    return $temp;
}

#retorna o array de dias que não tem agendamentos
function get_schedul_exceptions($id){
    $dates = array();
    $sql = "SELECT * FROM agendamentos_excecoes WHERE id_agendamento = $id";
    $result = cms_query($sql);
    while ($ln = cms_fetch_assoc($result)) {
        
        $temp  = _date_range($ln['data_inicio'],$ln['data_fim']);
        $dates = array_unique(array_merge($dates,$temp), SORT_REGULAR);          	
    }
    
    return $dates;
}

#retorna o array de dias com horas ocupadas
function get_schedul_logs($id){
    $sql = "SELECT dia_registo, GROUP_CONCAT( hora_registo ) as horas FROM agendamentos_registos WHERE status=0 AND id_agendamento = $id group by dia_registo";   
    $result = cms_query($sql);
   
    while ($ln = cms_fetch_assoc($result)) {
       $ln['dia_registo'] = date_create($ln['dia_registo']);    
       $ln['dia_registo']  = date_format($ln['dia_registo'],"d-m-Y");
       
       $temp[$ln['dia_registo']] = explode(",",$ln['horas']);    	
    } 
    return $temp; 
}

#retona todas os dias entre duas datas
function _date_range($first, $last, $step = '+1 day', $output_format = 'd-m-Y' ) {

    $dates = array();
    $current = strtotime($first);
    $last = strtotime($last);
    
    while( $current <= $last ) {
        
        $dates[] = date($output_format, $current);
        $current = strtotime($step, $current);
    }
    return $dates;
}


?>
