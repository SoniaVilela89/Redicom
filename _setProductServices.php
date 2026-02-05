<?

function _setProductServices($sku=null, $services=null, $opcoes_servico=null, $service_msg=null, $sku_encoded=0){

    global $LG, $userID;
    
    if( is_null($sku) ){
       $sku             = params('sku');
       $services        = params('services');
       $opcoes_servico  = params('opcoes_servico');
       $service_msg     = params('service_msg');
       $sku_encoded     = params('sku_encoded');
    }

    $sku_encoded = (int)$sku_encoded;
    if( $sku_encoded == 1 ){
        $sku = base64_decode($sku);
    }
    
    $pid = __add_services($sku, $services, $opcoes_servico, $service_msg);
    
    #adicionado 19/07
    $resp           =array();
    $resp['cart']   = OBJ_cart();
    
    $resp['cart']['product_id_add'] = $pid;
    
    $resp['product_id_add'] = end( explode("|||", $pid) ); #usado para o getRecommendation
    
    $data = serialize($resp['cart']);
    $data = gzdeflate($data,  9);
    $data = gzdeflate($data, 9);
    $data = urlencode($data);
    $data = base64_encode($data);
    $_SESSION["SHOPPINGCART"] = $data;
    
    $resp['0'] = 1;    
    return serialize($resp);
    
}


function __add_services($sku, $services, $opcoes_servico=0, $service_msg=''){
    
    global $userID;
    
    $arr_servicos = explode(",", $services);
    
    $service_msg = base64_decode($service_msg);

    $arr_service_msg = explode("|||", $service_msg);

    $sql = "SELECT * FROM ec_encomendas_lines WHERE id_cliente='".$userID."' 
            AND status='0' AND egift='0' AND id_linha_orig<1 AND servico_add='' AND servicos!='' AND ref='".$sku."'
            ORDER BY pid ASC";
       
    $res = cms_query($sql);
    while($v = cms_fetch_assoc($res)){
        $pid = $v['pid'];
        $arr_services = array();
        $arr_services = get_services($v["servicos"], $v["valoruni"]);
        
        $gravacao = "";
        $pid_complementar = array();
        
        foreach($arr_services as $kk => $vv){
            
            $arr_s = array();
            $i = 0;
            $z = 0;
            foreach($vv["service"] as $kkk => $vvv){
                
                if($opcoes_servico>0){
                    if(in_array($vvv["id"], $arr_servicos)){
                         $z++;
                         $gravacao = $arr_service_msg[array_search($vvv["id"], $arr_servicos)];
                    } 
                    if(in_array($vvv["id"], $arr_servicos) || $i==0){
                        $arr_s = $vvv;
                        $arr_s["qtd"] = 1;
                    }
                    $i++;
                }else{
                    if($i==0 || $vvv["predefined"]==1){
                        $arr_s = $vvv;
                        $arr_s["qtd"] = 1;
                        if($i==0){
                            $arr_s["qtd"] = 0;   
                        } 
                          
                    }                
                    $i++;
                }                
                
            }

            #caso a gravação venha a vazio e o tipo de serviço seja gravacao nao adiciona o servico
            if($arr_s["type"]==1 && trim($gravacao)=="") continue;
            
            $arr_s["id_group"] = $vv["id"];
  
            $pid_complementar[] = $arr_s["id"];

            $pid_serv = $v["pid"].",".$arr_s["id_group"];      
            $sql_verify = "SELECT id FROM ec_encomendas_lines WHERE id_cliente='".$userID."' AND status='0' AND pid='".$pid_serv."'";
            $res_verify = cms_query($sql_verify);
            $rows_verify = cms_num_rows($res_verify);  
            if($vv["unique"]==1 && $rows_verify>0)
                continue; 
                           
            add_service($v, $arr_s, $gravacao);
            
            if($opcoes_servico>0){
                if($z==0 && $vv["required"]==0){
                    $update_line = "UPDATE ec_encomendas_lines SET qnt='0', sku_family='0'  WHERE pid='".$pid_serv."' and sku_family='".$arr_s["id"]."' AND id_cliente='".$userID."' AND status='0' ";
                    cms_query($update_line);
                } 
            }else{
                if(!in_array($vv["id"], $arr_servicos) && $vv["required"]==0){
                    $update_line = "UPDATE ec_encomendas_lines SET qnt='0', sku_family='0'  WHERE pid='".$pid_serv."' and sku_family='".$arr_s["id"]."' AND id_cliente='".$userID."' AND status='0' ";
                    cms_query($update_line);
                }
            }
            

        }
        
        $id_complementar = "";
        if(trim($gravacao)!="") $id_complementar = $v["id"];
        
        $pid_completed = $services.$id_complementar."|||".$v["pid"];
        $update_line_prod = "UPDATE ec_encomendas_lines SET pid='".$pid_completed."'  WHERE id='".$v["id"]."' AND id_cliente='".$userID."' AND status='0' ";
        cms_query($update_line_prod);
        
        $pid_completed_serv = $services.$id_complementar."|||";
        $update_line_prod = "UPDATE ec_encomendas_lines SET pid=concat('".$pid_completed_serv."',pid)  WHERE id_linha_orig='".$v["id"]."' AND id_cliente='".$userID."' AND status='0' ";
        cms_query($update_line_prod);

    }
    
    return $pid_completed;
}

?>
