<?

function _addCustomizationToBasket(){

    if( empty($_POST) ){
        return serialize(['success' => 0, 'error' => 'Bad Request - Missing Data']);
    }

    global $LG, $COUNTRY, $userID;
    
    

    $qtd = (int)$_POST["qtd"];
    if($qtd == 0) $qtd = 1;

    $arr_linhas = array();
    $arr_linhas_id = array();
    $max_id = 0;
    $sql_linha = "SELECT *, MAX(id) as max_id FROM ec_encomendas_lines WHERE pid='".$_POST["prod_id"]."' AND status=0 AND id_cliente='".$userID."' AND id_linha_orig<1 ORDER BY id DESC LIMIT 0,$qtd";
    $res_linha = cms_query($sql_linha);
    while($row_linha = cms_fetch_assoc($res_linha)){
        $arr_linhas[] = $row_linha;
        $arr_linhas_id[] = $row_linha["id"];
        if($max_id == 0) $max_id = $row_linha["max_id"];
    }
    
    $sql_linha_orig = "SELECT GROUP_CONCAT(DISTINCT(id_linha_orig)) as id_lines_orig FROM ec_encomendas_lines WHERE id_linha_orig in (".implode(",",$arr_linhas_id).") AND status=0 AND id_cliente='".$userID."' AND sku_group='customization'";
    $res_linha_orig = cms_query($sql_linha_orig);
    $row_linha_orig = cms_fetch_assoc($res_linha_orig);

    if($row_linha_orig["id_lines_orig"] != ""){
        $arr_lines_orgi = explode(",", $row_linha_orig["id_lines_orig"]);
        
        $arr_linhas_id = array_diff($arr_lines_orgi, $arr_linhas_id);
        foreach ($arr_lines_orgi as $key => $value) {

            foreach ($arr_linhas as $k => $entry) {
                if ($entry['id'] == $value) {
                    unset($arr_linhas[$k]);
                    break; 
                }
            }
        }
    }

    if(count($arr_linhas) == 0) return serialize(['success' => 0, 'error' => 'Bad request - line invalid']);

    $pid_final = $arr_linhas[0]['pid'];
    $arr_pid = explode("|||", $arr_linhas[0]['pid']);
    if(count($arr_pid)>1){
        $pid_final = $arr_pid[1];
    }

    $prod            = call_api_func("get_line_table","registos", "id='".$pid_final."'");

    if((int)$prod["id"] == 0 || (int)$prod["conjunto_personalizacao"] == 0)  return serialize(['success' => 0, 'error' => 'Product invalid']);
    
    foreach($_POST["add_customization"] as $k => $v){
        $v = json_decode(html_entity_decode($v), true);
        $sql_group = "SELECT * FROM personalizacao_grupo WHERE id='".$v["group_id"]."' AND personalizacao_id='".$prod["conjunto_personalizacao"]."' AND (paises='' OR CONCAT(',',paises,',') LIKE '%%,".$COUNTRY["id"].",%%' ) LIMIT 0,1";
        $res_group = cms_query($sql_group);
        $row_group = cms_fetch_assoc($res_group);
        
        if((int)$row_group["id"] == 0 || count($v["steps"]) == 0) continue;

        $arr_group_service = array(
            "id"    => $row_group["id"],
            "nome"  => $row_group["nome".$LG],
            "preco" => "0"
        );

        $arr_add_service = array();
        $arr_add_service_obs = array();
        $arr_add_service_image = array();
        $arr_add_service_image_bib = array();
        foreach($v["steps"] as $kk => $vv){
            $texto = array();
            $letras = array();

            $sql_step = "SELECT * FROM personalizacao_grupo_linhas WHERE id='".$vv["step_id"]."' LIMIT 0,1";
            $res_step = cms_query($sql_step);
            $row_step = cms_fetch_assoc($res_step);
            
            if((int)$row_step["id"] == 0 ) continue;

            $arr_group_service["id"] .= "-".$row_step["id"];

            $sql_step_price = "SELECT * FROM personalizacao_grupo_linhas_preco WHERE personalizacao_grupo_linhas_id='".$row_step["id"]."' AND (paises='' OR CONCAT(',',paises,',') LIKE '%%,".$COUNTRY["id"].",%%' )  LIMIT 0,1";
            $res_step_price = cms_query($sql_step_price);
            $row_step_price = cms_fetch_assoc($res_step_price);            
            
            switch ($row_step["tipo"]) {
                case 1: #Texto livre
                    if(count($vv["lines"]) == 0 ) continue;  #nº de linhas

                    $texto = $vv["lines"];

                    $primeirasPalavras = array_map(function($frase) {
                        $palavras = explode(" ", $frase);
                        $primeirasTresPalavras = array_slice($palavras, 0, 3);
                        if(count($palavras) > 3) return implode(" ", $primeirasTresPalavras)."...";
                        return implode(" ", $primeirasTresPalavras);
                    }, $texto);

                    $arr_group_service["preco"] += $row_step_price["preco"];
                    
                    $arr_add_service[] = implode(" - ", $primeirasPalavras);
                    $arr_add_service_obs[] = "ID - ".$row_step["id"]." ".implode(" - ", $texto);
                    break;

                case 2: # Biblioteca

                    $arr_add_service[] = "img.jpg";
                    $arr_add_service_image_bib[$row_group["id"]."-".$row_step["id"]] = $vv["image"];
                    $arr_add_service_image[] = $row_group["id"]."-".$row_step["id"];
                    
                    $arr_add_service_obs[] = "ID - ".$row_step["id"]." {img".$row_group["id"]."-".$row_step["id"]."}" ;
                    $arr_group_service["preco"] += $row_step_price["preco"];
                    break;

                case 3: #Letras
                    
                    if(count($vv["letters"]) == 0 ) continue; #nº de letras
                    
                    $letras = $vv["letters"];
                    $arr_add_service[] = implode("-", $letras);
                    $arr_add_service_obs[] = "ID - ".$row_step["id"]." ".implode(" - ", $letras);
                    $arr_group_service["preco"] += $row_step_price["preco"];
                    break;

                case 4: #Conjunto de imagens

                    $sql_image = "SELECT * FROM personalizacao_grupo_linhas WHERE id='".$vv["image_id"]."' AND subpagina='".$vv["step_id"]."' LIMIT 0,1";
                    $res_image = cms_query($sql_image);
                    $row_image = cms_fetch_assoc($res_image);

                    if((int)$row_image["id"] == 0) continue;

                    //$arr_add_service[] = $row_image["nome".$LG];
                    $arr_add_service_obs[] = "ID - ".$row_image["id"]." ".$row_image["nome".$LG];

                    $sql_image = "SELECT * FROM personalizacao_grupo_linhas WHERE id='".$vv["child_id"]."' AND subpagina='".$vv["image_id"]."' LIMIT 0,1";
                    $res_image = cms_query($sql_image);
                    $row_image = cms_fetch_assoc($res_image);
                    
                    if((int)$row_image["id"] == 0) continue;

                    $sql_step_price = "SELECT * FROM personalizacao_grupo_linhas_preco WHERE personalizacao_grupo_linhas_id='".$row_image["id"]."' LIMIT 0,1";
                    $res_step_price = cms_query($sql_step_price);
                    $row_step_price = cms_fetch_assoc($res_step_price);
                    
                    $arr_group_service["preco"] += $row_step_price["preco"];

                    $arr_add_service[] = $row_image["nome".$LG];
                    $arr_add_service_obs[] = "ID - ".$row_image["id"]." ".$row_image["nome".$LG];
                    break;

                default:  #0  Imagem
                
                    $sql_image = "SELECT * FROM personalizacao_grupo_linhas WHERE id='".$vv["image_id"]."' AND subpagina='".$vv["step_id"]."' LIMIT 0,1";
                    $res_image = cms_query($sql_image);
                    $row_image = cms_fetch_assoc($res_image);

                    if((int)$row_image["id"] == 0) continue;

                    $sql_step_price = "SELECT * FROM personalizacao_grupo_linhas_preco WHERE personalizacao_grupo_linhas_id='".$row_image["id"]."' LIMIT 0,1";
                    $res_step_price = cms_query($sql_step_price);
                    $row_step_price = cms_fetch_assoc($res_step_price);
                    
                    $arr_group_service["preco"] += $row_step_price["preco"];

                    $arr_add_service[] = $row_image["nome".$LG];
                    $arr_add_service_obs[] = "ID - ".$row_step["id"]." ".$row_image["nome".$LG];
                    break;

            }
            
        }

        if(count($v["steps"]) != count($arr_add_service)) continue;

        $arr_group_service["services"] = $arr_add_service;
        $arr_group_service["obs"] = $arr_add_service_obs;
        $arr_group_service["image"] = $arr_add_service_image;
        $arr_group_service["image_bib"] = $arr_add_service_image_bib;

        foreach ($arr_linhas as $key => $value) {
            add_customization($arr_group_service, $value);
        }
        
    }

    $sql_update_line = "UPDATE `ec_encomendas_lines` SET pid=CONCAT('".$max_id."', '|||', pid) WHERE id IN (".implode(",",$arr_linhas_id).") AND status=0 AND id_cliente='".$userID."'";
    cms_query($sql_update_line);
    
  
    
    
    return serialize(['success' => 1, 'message' => 'Customization added successfully']);

}

function add_customization($arr_add_service, $row_linha){
    global $MOEDA;
    global $MARKET;
    global $LG;
    global $ssitelocation, $sslocation;
    global $fx;
    global $B2B;
    global $COUNTRY;
        
    $arr = array();
            
    $arr['idioma_user']         = $LG;
    $arr['email']               = $row_linha['email'];
    $arr['id_cliente']          = $row_linha["id_cliente"];
    $arr['mercado']             = $MARKET['id']; 
    
    $arr['pais_cliente']        = $COUNTRY['id'];
    
    $arr['pais_iso']            = $COUNTRY['country_code'];
    
    $arr['moeda']               = $MOEDA['id'];  
    $arr['taxa_cambio']         = (float)$MOEDA['cambio']==0 ? 1 : $MOEDA['cambio'];
    $arr['moeda_simbolo']       = $MOEDA['abreviatura'];
    $arr['moeda_prefixo']       = $MOEDA['prefixo'];
    $arr['moeda_sufixo']        = $MOEDA['sufixo'];
    $arr['moeda_decimais']      = $MOEDA['decimais'];
    $arr['moeda_casa_decimal']  = $MOEDA['casa_decimal'];
    $arr['moeda_casa_milhares'] = $MOEDA['casa_milhares'];

    $arr['status']              = "0";
    $arr['pid']                 = $row_linha["pid"].",".$arr_add_service["id"];
    $arr['ref']                 = $arr_add_service["nome"];
    $arr['sku_family']          = $arr_add_service["nome"];
    $arr['sku_group']           = "customization";
    $arr['nome']                = $arr_add_service["nome"];
    $arr['unidade_portes']      = 1;        
    $arr['composition']         = implode("; ", $arr_add_service["services"]);  
    
    $arr['cor_id']              = "";
    $arr['cor_cod']             = "";
    $arr['cor_name']            = "";
    $arr['largura']             = "";
    $arr['altura']              = "";
    $arr['peso']                = "";
    $arr['tamanho']             = "";
    $arr['qnt']                 = 1;
    
    $arr['data']                = date("Y-m-d");
    $arr['datahora']            = date("Y-m-d H:i:s");

    $arr['valoruni']            = $arr_add_service['preco'];
    $arr['valoruni_anterior']   = "";
    $arr['valoruni_desconto']   = "";

    $arr['datafim_desconto']    = "";
    $arr['id_desconto']         = "";

    $arr['deposito']            = "";
    $arr['lista_preco']         = "";
    
    $arr['promo_perc']          = 0;
    $arr['desconto']            = 0;

    $arr['promotion']           = 0;
    $arr['novidade']            = 0;
    $arr['id_linha_orig']       = $row_linha["id"];
    $arr['servico_obrigatorio'] = "1";
    $arr['servicos']            = "";
    $arr['servico_add']         = "";
    $arr['servico_qnt_unica']   = 1;
    
    $arr['iva_taxa_id']         = (int)$row_linha['iva'];

    $arr['page_id']             = $row_linha['page_id'];
    $arr['page_cat_id']         = $row_linha['page_cat_id'];

    $separator_start = 1;

    // Mapear os elementos e adicionar os incrementos nos separadores
    $modified_array = array_map(function($value) use (&$separator_start) {
        return "Passo ".$separator_start++.": ".$value;
    }, $arr_add_service['obs']);

    $arr['obs']                 = implode(" \n ", $modified_array);
    
    $img = $_SERVER['DOCUMENT_ROOT'].'/plugins/system/sysimgs/img-services.jpg';

    $path_final = $sslocation;
    if(strlen($ssitelocation)>3) $path_final = $ssitelocation;

    $fx->settemppath($_SERVER['DOCUMENT_ROOT'].'/temp'); 
    $img_prd = $fx->makeimage($img, 150, 150, 0, 0, 3, 'FFFFFF','', 'JPG', 0, '', 'FFFFFF');
    $fx->settemppath($_SERVER['DOCUMENT_ROOT'].'/temp');     
   
    $path_final = $sslocation;
    if(strlen($ssitelocation)>3) $path_final = $ssitelocation;
       
    $arr['image'] = str_ireplace($_SERVER['DOCUMENT_ROOT'], $path_final, $img_prd);            
    
    $TABELA =  "ec_encomendas_lines";
    $Record =  $arr;
    
    $f  = array();
    $v  = array();

    foreach ($Record as $campo=>$valor){
        $f[] = "$campo";
        $v[] = "'".safe_value($valor)."'";
    }

    $SQL = "INSERT INTO $TABELA (" . implode(",",$f) . ") VALUES (". implode(",",$v) .")";      
    cms_query($SQL);

    $LAST_ID  = cms_insert_id();
    
    if(count($arr_add_service["image"]) > 0){
    
        if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/downloads/client_library')) {
            mkdir($_SERVER['DOCUMENT_ROOT'].'/downloads/client_library', 0777, true);
        }

        if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/downloads/customization_library')) {
            mkdir($_SERVER['DOCUMENT_ROOT'].'/downloads/customization_library', 0777, true);
        }
        
        if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/downloads/client_library/'.$row_linha["id_cliente"])) {
            mkdir($_SERVER['DOCUMENT_ROOT'].'/downloads/client_library/'.$row_linha["id_cliente"], 0777, true);
        }

        foreach($arr_add_service["image"] as $key => $value){

            $ext = explode(".",$_FILES['image-'.$value]['name']);
            if($_FILES['image-'.$value]['size'] > 0){

                if(is_numeric($row_linha["id_cliente"])){

                    $diretorio = $_SERVER['DOCUMENT_ROOT'].'/downloads/client_library/'.$row_linha["id_cliente"];
                    $arquivos = scandir($diretorio);

                    $lista_arquivos = array();
                    foreach ($arquivos as $arquivo) {
                        $caminho_completo = $diretorio . '/' . $arquivo;
                        
                        if (is_file($caminho_completo)) $lista_arquivos[$arquivo] = filemtime($caminho_completo);
                    }
                    
                    if(count($lista_arquivos) >= 10){
                        asort($lista_arquivos);
                        unlink('../downloads/client_library/'.$row_linha["id_cliente"].'/'.key($lista_arquivos));
                    }

                    $file_final = "../downloads/client_library/".$row_linha["id_cliente"]."/".$LAST_ID."--".$value.".".end($ext);
                    unlink($file_final);
                    copy($_FILES['image-'.$value]['tmp_name'], $file_final);

                }
                
                $file_final = "../downloads/customization_library/".$LAST_ID."--".$value.".".end($ext);
                unlink($file_final);
                copy($_FILES['image-'.$value]['tmp_name'], $file_final);

                $location_file = $sslocation.str_replace("../", "/", $file_final);
                
                $obs = str_replace("{img".$value."}", "<a href='".$location_file."' target='_blank'>Imagem</a>", $arr['obs']);
                $SQL_UPDATE = "UPDATE $TABELA SET obs='".cms_escape($obs)."' WHERE id='".$LAST_ID."'";      
                cms_query($SQL_UPDATE);

            }elseif($arr_add_service["image_bib"][$value] != ""){
                $file_user = $_SERVER['DOCUMENT_ROOT'].'/downloads/client_library/'.$row_linha["id_cliente"]."/".$arr_add_service["image_bib"][$value];
               
                if(file_exists($file_user)){
                
                    $ext = explode(".", $arr_add_service["image_bib"][$value]);

                    $file_final = "../downloads/customization_library/".$LAST_ID."--".$value.".".end($ext);
                    unlink($file_final);
                    copy($file_user, $file_final);

                    $location_file = $sslocation.str_replace("../", "/", $file_final);

                    $obs = str_replace("{img".$value."}", "<a href='".$location_file."' target='_blank'>Imagem</a>", $arr['obs']);
                    $SQL_UPDATE = "UPDATE $TABELA SET obs='".cms_escape($obs)."' WHERE id='".$LAST_ID."'";      
                    cms_query($SQL_UPDATE);

                }

            }
        }
    }
            
}
