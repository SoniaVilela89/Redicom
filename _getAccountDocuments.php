<?

function _getAccountDocuments($page_id=null, $page_number=1, $filters = "", $search = ""){

    global $userID;
    global $eComm;
    global $LG;
    
    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }
    
    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    
    $documents = get_documents($userID, $page_number, $filters, $search);
    $arr['documents']   = $documents["documents"];
    $arr['balance']     = $documents["balance"];
    $arr['saldo_exp']   = $documents["saldo_exp"]." ".date('Y-m-d');
    $arr['pagination']  = $documents["pagination"];
    $arr['filters']     = $documents["filters"];
    $arr['search']      = $documents["search"];
    

    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');

    return serialize($arr);   
    
}

function get_documents($user_id, $page_number, $filters, $search){

    global $LG, $slocation;
    global $CONFIG_OPTIONS, $API_CONFIG_B2B_ALL_DOCUMENTS;
    
    if( ( (int)$CONFIG_OPTIONS['VENDEDOR_CARRINHO_PROPRIO'] == 1 || (int)$CONFIG_OPTIONS['RESTRICTED_USER_PRIVATE_CART'] == 1 ) && (int)$_SESSION['EC_USER']['id_original'] > 0 ){
        $user_id = $_SESSION['EC_USER']['id_original'];
    }

    $arr_docs = array(); 

    $more = " AND _tdocumentos.valor!=0 ";  
    if((int)$API_CONFIG_B2B_ALL_DOCUMENTS>0) $more = "";
    
    $more_filtros = "";
    $more_date = "";


    # Filtros
    $filtros = array();
    if($filters != '') {
        $filters = base64_decode($filters);
        if($filters !== false) {
            $arr_filters = explode("&", $filters);

            foreach ($arr_filters as $v) {
                $field = explode("=", $v);
                if($field[1] != '' && $field[0] != 'id') {
                    $filtros[$field[0]] = str_replace("_", ",", $field[1]);
                }
            }

            # Data
            if($filtros['date_min'] != '' && $filtros['date_max'] != '') {
                $more_date = " AND (_tdocumentos.data_doc >= '".$filtros['date_min']."' AND _tdocumentos.data_doc <= '".$filtros['date_max']."')";
                $more_filtros = $more_date;
            }

            # Tipo
            if($filtros['type_filter'] != '') {
                $more_filtros .= " AND tipo IN(".$filtros['type_filter'].")";
            }

            # Estado
            if($filtros['status'] != '') {
                $status = explode(",", $filtros['status']);
                $more_status = array();
                foreach ($status as $v) {
                    switch ($v) {
                        case '1': # Em aberto
                            $more_status[] = "(valor_pago < valor AND '".date("Y-m-d")."' <= data_vencimento)";
                            break;
                        case '2': # Vencido
                            $more_status[] = "(valor_pago < valor AND '".date("Y-m-d")."' > data_vencimento)";
                            break;
                        case '3': # Fechado
                            $more_status[] = "valor_pago >= valor";
                            break;
                    }
                }
                $more_filtros .= " AND (".implode(" OR ", $more_status).")";
            }
        }

        $more .= " AND _tdocumentos.data_doc > DATE_SUB(NOW(),INTERVAL 3 YEAR)";
    } else {
        $more .= " AND _tdocumentos.data_doc > DATE_SUB(NOW(),INTERVAL 6 MONTH)";
    }


    # Pesquisa
    if($search != '') {
        $pesq = " AND num LIKE '%%%s%%' ";
        $more_filtros .= sprintf($pesq, urldecode($search));
    }


    if((int)$API_CONFIG_B2B_ALL_DOCUMENTS==1){
        $query_where = "_tdocumentos.id_user='".$user_id."' $more $more_filtros ";
        $query_where_semfiltros = "_tdocumentos.id_user='".$user_id."' $more $more_date ";
    }else{
        $query_where = "_tdocumentos.id_user='".$user_id."' $more $more_filtros AND _tdocumentos.num NOT LIKE '%recibo%'";
        $query_where_semfiltros = "_tdocumentos.id_user='".$user_id."' $more $more_date AND _tdocumentos.num NOT LIKE '%recibo%'";        
    }


    # paginação
    $max_por_pag = 10;
    $total_docs = cms_fetch_assoc(cms_query("SELECT COUNT(id) as total FROM _tdocumentos WHERE $query_where"));
    $total_rows = $total_docs['total'];
    $pagination = paginate_list($max_por_pag, $total_rows, $page_number);
    $page_position = (($page_number-1) * $max_por_pag);

    $sql_doc = "SELECT *
                FROM _tdocumentos
                WHERE $query_where
                ORDER BY data_doc DESC, id DESC
                LIMIT ".$page_position.",".$max_por_pag;

    $res_doc = cms_query($sql_doc);

    $moeda = "";
    
    while($row_doc = cms_fetch_assoc($res_doc)){

        if($row_doc['valor_pago'] >= $row_doc['valor']){
            $status_name =  estr2(278);
            $status_id   = 3;
            $status_class = "rdc-state-05";
        }else{
            $status_name  =  estr2(524);
            $status_id    = 1;
            $status_class = "rdc-state-02";
            if( strtotime(date("Y-m-d")) > strtotime($row_doc['data_vencimento']) ){
                $status_name  =  estr2(523);
                $status_id    = 2;
                $status_class = "rdc-state-04";
            }
        }
        
        $file = "";
        $external_file = 0;
        
        if( file_exists($_SERVER['DOCUMENT_ROOT']."/downloads/documents/".$row_doc['num'].".pdf") ){
            $file = $slocation."/api/api.php/getAccountDocument/".base64_encode($user_id."|||".$row_doc['num']);
        }elseif( trim($row_doc['url']) ){
            $file           = trim($row_doc['url']);
            $external_file  = 1;
        }

        $doc_type = get_documents_type($row_doc["tipo"]);
        $link_detalhe = "";
        
        if( (int)$CONFIG_OPTIONS['allow_documents_details'] == 1 ){
            $link_detalhe = "onclick=\"location='?id=38&idd=".$row_doc['id']."'\"";
        }

        $arr_docs[] = array(
            "id"            =>  $row_doc["id"],
            "type"          =>  $doc_type["nome".$LG],
            "number"        =>  $row_doc['num'],
            "description"   =>  $row_doc['descricao'],
            "date_doc"      =>  $row_doc['data_doc'],
            "date_exp"      =>  $row_doc['data_vencimento'],
            "qtd"           =>  $row_doc['qtd'],
            "status"        =>  $status,
            "valor"         =>  call_api_func('moneyOBJ', $row_doc['valor'], $row_doc['moeda']),
            "valor_pago"    =>  call_api_func('moneyOBJ', $row_doc['valor_pago'], $row_doc['moeda']),
            "debito"        =>  call_api_func('moneyOBJ', $row_doc['debito'], $row_doc['moeda']),
            "credito"       =>  call_api_func('moneyOBJ', $row_doc['credito'], $row_doc['moeda']),
            "saldo"         =>  call_api_func('moneyOBJ', $row_doc['saldo'], $row_doc['moeda']),
            "file"          =>  $file,
            "external_file" =>  $external_file,
            "link_detail"   =>  $link_detalhe,
            "status_id"     =>  $status_id,
            "status_name"   =>  $status_name,
            "class_name"    =>  $status_class
        );
    }


    # obter saldo corrente
    $s = "SELECT saldo, moeda FROM _tdocumentos WHERE $query_where_semfiltros ORDER BY data_doc DESC, id DESC LIMIT 1";
    $r = cms_fetch_assoc(cms_query($s));
    $saldo = $r['saldo'];    
    $moeda = $r['moeda'];

    $saldo_exp = estr2(630);
    if($saldo<0) $saldo_exp = estr2(629);


    # filtros
    $filters = array();
    $filters['status'] = get_documents_filters_status($query_where_semfiltros, $filtros['status']);
    $filters['types']  = get_documents_filters_types($query_where_semfiltros, $filtros['type_filter']);
    $filters['date_min'] = $filtros['date_min'];
    $filters['date_max'] = $filtros['date_max'];


    $arr_return = array(
        "documents" => $arr_docs,
        "balance"   => call_api_func('moneyOBJ', abs($saldo), $moeda),
        "saldo_exp" => $saldo_exp,
        "pagination" => $pagination,
        "filters" => $filters
    );
    
    return $arr_return;
    
}



# obter os filtros de estado
function get_documents_filters_status($where, $filtros) {

    $filter = array();
    $selecionados = explode(",", $filtros);

    # estado - Fechado
    $s = "SELECT id FROM _tdocumentos WHERE $where AND valor_pago>=valor LIMIT 1";
    $r = cms_fetch_assoc(cms_query($s));

    if((int)$r['id'] > 0) {
        $sel = (in_array("3", $selecionados)) ? 1 : 0;
        $filter[] = array("id" => "3", "name" => estr2(278), "class" => "rdc-state-05", "selected" => $sel); # Fechado
    }

    # estado - Vencido / Em aberto
    $s = "SELECT id, min(data_vencimento) as min_data_vencimento, max(data_vencimento) as max_data_vencimento FROM _tdocumentos WHERE $where AND valor_pago<valor";
    $r = cms_fetch_assoc(cms_query($s));

    if((int)$r['id']>0) {
        if( strtotime(date("Y-m-d")) > strtotime($r['min_data_vencimento']) ){
            $sel = (in_array("2", $selecionados)) ? 1 : 0;
            $filter[] = array("id" => "2", "name" => estr2(523), "class" => "rdc-state-04", "selected" => $sel); # Vencido
        }

        if( strtotime(date("Y-m-d")) <= strtotime($r['max_data_vencimento']) ){
            $sel = (in_array("1", $selecionados)) ? 1 : 0;
            $filter[] = array("id" => "1", "name" => estr2(524), "class" => "rdc-state-02", "selected" => $sel); # Em aberto
        }
    }

    return $filter;
}


# obter os filtros de tipo
function get_documents_filters_types($query_where, $filtros) {

    global $LG;

    $filter = array();
    $selecionados = explode(",", $filtros);

    $s = "SELECT DISTINCT `_tdocumentos_tipos`.`id`, `_tdocumentos_tipos`.`nome$LG` FROM `_tdocumentos_tipos` INNER JOIN `_tdocumentos` ON `_tdocumentos_tipos`.`id`=`_tdocumentos`.`tipo` WHERE $query_where";
    $q = cms_query($s);

    while($r = cms_fetch_assoc($q)){
        $sel = (in_array($r['id'], $selecionados)) ? 1 : 0;
        $filter[] = array("id" => $r['id'], "name" => $r['nome'.$LG], "class" => "", "selected" => $sel);
    }

    return $filter;
}



?>
