<?
function _getTestimony($page_id=0){

    global $fx;
    global $LG;
    global $INFO_SUBMENU;
    
    if ($page_id==0){
        $page_id = (int)params('page_id');
    }

    if($INFO_SUBMENU==1){
        $arr['selected_page'] = call_api_func('pageOBJ', $page_id, $page_id);
    }else{
        $row                  = call_api_func('get_pagina', $page_id, "_trubricas");
        $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);
    }

  
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;

    $arr['testimony']                     = call_api_func('__get_testimony');

    $arr['shop']                          = call_api_func('OBJ_shop_mini');

    $arr['expressions']                   = call_api_func('get_expressions',45);

    return serialize($arr);

}


function __get_testimony(){
    global $LG;

    $arr = array();

    $sql = "SELECT * FROM `b2c_testemunhos` WHERE activo=1 order by data desc, id desc";
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        if($LG=='pt'){
            setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
            $data = strftime("%d de %B de %Y",strtotime($row['data']));
            $ano  = strftime("%Y",strtotime($row['data']));
            $mes  = strftime("%B",strtotime($row['data']));
            $dia  = strftime("%d",strtotime($row['data']));
        }else{
            $data = strftime("%d %B, %Y",strtotime($row['data']));
            $ano  = strftime("%Y",strtotime($row['data']));
            $mes  = strftime("%B",strtotime($row['data']));
            $dia  = strftime("%d",strtotime($row['data']));
        }

        $arr[] = array(
            "id"        => $row["id"],
            "date"      => $data,
            "day"       => $dia,
            "month"     => $mes,
            "year"      => $ano,
            "name"      => $row["nome"],
            "message"   => $row["mensagem"],
            "city"      => $row["cidade"]
        );
    }

    return $arr;
}
?>
