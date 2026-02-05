<?

function _getCustomization($product_id=0)
{

    global $LG, $MARKET, $userID, $COUNTRY, $MOEDA, $CACHE_HEADER_FOOTER, $CACHE_KEY, $fx, $sslocation;
    
    if ( (int)$product_id > 0 ){
       $product_id = (int)$product_id;       
    }else{
       $product_id = (int)params('product_id');      
    }
    
    $product = call_api_func("get_line_table","registos", "id='".$product_id."'");
    $arr = array();
    $arr["customization"] = array();
    if((int)$product["conjunto_personalizacao"] > 0){
        
        $sql_c = "SELECT id, nome$LG as name, desc$LG as description FROM personalizacao WHERE id='%d' LIMIT 0,1";
        $sql_c = sprintf($sql_c, (int)$product["conjunto_personalizacao"]);
        $res_c = cms_query($sql_c);
        $row_c = cms_fetch_assoc($res_c);

        $arr_g = array();
        $sql_c_g = "SELECT id, nome$LG as name FROM personalizacao_grupo WHERE personalizacao_id='".$row_c["id"]."' AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' ) ORDER BY ordem ASC";
        $res_c_g = cms_query($sql_c_g);
        while($row_c_g = cms_fetch_assoc($res_c_g)){
            
            $price_min = 99999;
            $price_max = 0;

            $arr_lines = array();
            $i = 0;
            $sql_c_lines = "SELECT * FROM personalizacao_grupo_linhas WHERE personalizacao_grupo_id='%d' AND subpagina=0 ORDER BY ordem ASC";
            $sql_c_lines = sprintf($sql_c_lines, (int)$row_c_g["id"]);
            $res_c_lines = cms_query($sql_c_lines);
            while($row_c_lines = cms_fetch_assoc($res_c_lines)){
                
                $arr_subpage = array();
                $sql_c_lines_subp = "SELECT * FROM personalizacao_grupo_linhas WHERE subpagina='%d' ORDER BY ordem ASC";
                $sql_c_lines_subp = sprintf($sql_c_lines_subp, $row_c_lines["id"]);
                $res_c_lines_subp = cms_query($sql_c_lines_subp);
                while($row_c_lines_subp = cms_fetch_assoc($res_c_lines_subp)){
                    $img_sub = array();
                    if (file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/personalizacao_sub_".$row_c_lines_subp['id'].".jpg")) {
                        $image_original = "images/personalizacao_sub_".$row_c_lines_subp['id'].".jpg";
                        $img_sub = call_api_func('OBJ_image', $row_c_lines_subp["nome".$LG], $cont, $image_original);
                    }   

                    $arr_subpage_dep = array();
                    $sql_c_lines_subp_dep = "SELECT * FROM personalizacao_grupo_linhas WHERE subpagina='%d' ORDER BY ordem ASC";
                    $sql_c_lines_subp_dep = sprintf($sql_c_lines_subp_dep, $row_c_lines_subp["id"]);
                    $res_c_lines_subp_dep = cms_query($sql_c_lines_subp_dep);
                    while($row_c_lines_subp_dep = cms_fetch_assoc($res_c_lines_subp_dep)){
                        $img_sub2 = array();
                        if (file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/personalizacao_sub_".$row_c_lines_subp_dep['id'].".jpg")) {
                            $image_original = "images/personalizacao_sub_".$row_c_lines_subp_dep['id'].".jpg";
                            $img_sub2 = call_api_func('OBJ_image', $row_c_lines_subp_dep["nome".$LG], $cont, $image_original);
                        } 
                        
                        $row_c_lines_subp_dep["desc".$LG] = str_ireplace("$", "&dollar;", $row_c_lines_subp_dep["desc".$LG]);
                        $row_c_lines_subp_dep["desc".$LG] = str_ireplace("€", "&euro;", $row_c_lines_subp_dep["desc".$LG]);

                        $arr_subpage_dep[] = array(
                            "id"            => $row_c_lines_subp_dep["id"],
                            "name"          => $row_c_lines_subp_dep["nome".$LG],
                            "description"   => $row_c_lines_subp_dep["desc".$LG],
                            "image"         => $img_sub2
                        );
                    }

                    $sql_price_subp = "SELECT * FROM personalizacao_grupo_linhas_preco WHERE personalizacao_grupo_linhas_id='".$row_c_lines_subp["id"]."' AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' ) ";
                    $res_price_subp = cms_query($sql_price_subp);
                    $row_price_subp = cms_fetch_assoc($res_price_subp);

                    if((int)$row_price_subp['preco'] > 0 && $row_price_subp['preco'] < $price_min) $price_min = $row_price_subp['preco'];
                    if((int)$row_price_subp['preco'] > 0 && $row_price_subp['preco'] > $price_max) $price_max = $row_price_subp['preco'];
                    
                    $row_c_lines_subp["desc".$LG] = str_ireplace("$", "&dollar;", $row_c_lines_subp["desc".$LG]);
                    $row_c_lines_subp["desc".$LG] = str_ireplace("€", "&euro;", $row_c_lines_subp["desc".$LG]);

                    $row_c_lines_subp["bloco".$LG] = str_ireplace("$", "&dollar;", $row_c_lines_subp["bloco".$LG]);
                    $row_c_lines_subp["bloco".$LG] = str_ireplace("€", "&euro;", $row_c_lines_subp["bloco".$LG]);
                        
                    $arr_subpage[] = array(
                        "id"            => $row_c_lines_subp["id"],
                        "name"          => $row_c_lines_subp["nome".$LG],
                        "description"   => $row_c_lines_subp["desc".$LG],
                        "content"       => $row_c_lines_subp["bloco".$LG],
                        "image"         => $img_sub,
                        "childs"        => $arr_subpage_dep,
                        "price"         => call_api_func('OBJ_money',$row_price_subp['preco'], $MOEDA['id']),
                        "image_grid"    => $row_c_lines_subp["layout"]
                    );

                    
                }

                $arr_more_info = array();
                switch ($row_c_lines["tipo"]) {
                    case '0':
                        $arr_more_info = array(
                            "image_grid"    => $row_c_lines["layout"]
                        );
                        break;
                    case '1':
                        $arr_more_info = array(
                            "min"         =>  $row_c_lines["min"],
                            "max"         =>  $row_c_lines["max"],
                            "characters_line"   =>  $row_c_lines["num_caracteres"]
                        );
                        break;
                    case '2':
                        $lista_arquivos = array();
                        
                        if(is_numeric($userID)){
                            $diretorio = $_SERVER['DOCUMENT_ROOT'].'/downloads/client_library/'.$userID;
                            $arquivos = scandir($diretorio);

                            $lista_arquivos = array();
                            foreach ($arquivos as $arquivo) {
                                $caminho_completo = $diretorio . '/' . $arquivo;
                                
                                if (is_file($caminho_completo)) $lista_arquivos[] = array("name" => $arquivo, "file" => $sslocation. '/downloads/client_library/'.$userID .'/'. $arquivo);
                            }
                        }

                        $arr_more_info = array(
                            "files"   =>  $lista_arquivos
                        );
                        break;
                    case '3':
                        $arr_more_info = array(
                            "min"   =>  $row_c_lines["min"],
                            "max"   =>  $row_c_lines["max"]
                        );
                        break;
                    case '4':
                        $arr_more_info = array(
                            "image_grid"    => $row_c_lines["layout"]
                        );
                        break;
                }

                $url = "";
                if((int)$row_c_lines['url'] > 0) $url = '/index.php?id='.$row_c_lines['url'];

                $sql_price = "SELECT * FROM personalizacao_grupo_linhas_preco WHERE personalizacao_grupo_linhas_id='".$row_c_lines["id"]."' AND (paises='' OR CONCAT(',',paises,',') LIKE '%,".$COUNTRY['id'].",%' ) ";
                $res_price = cms_query($sql_price);
                $row_price = cms_fetch_assoc($res_price);

                if((int)$row_price['preco'] > 0 && $row_price['preco'] < $price_min) $price_min = $row_price['preco'];
                if((int)$row_price['preco'] > 0 && $row_price['preco'] > $price_max) $price_max = $row_price['preco'];

                $row_c_lines["desc".$LG] = str_ireplace("$", "&dollar;", $row_c_lines["desc".$LG]);
                $row_c_lines["desc".$LG] = str_ireplace("€", "&euro;", $row_c_lines["desc".$LG]);
                
                $arr_temp = array(
                    "id"            => $row_c_lines["id"],
                    "name"          => $row_c_lines["nome".$LG],
                    "content"       => $row_c_lines["desc".$LG],
                    "type"          => $row_c_lines["tipo"],
                    "url_info"      => $url,
                    "childs"        => $arr_subpage,
                    "price"         => call_api_func('OBJ_money',$row_price['preco'], $MOEDA['id'])
                );

                $arr_lines[$i][] = array_merge($arr_temp, $arr_more_info);
                $i++;
                if(count($arr_subpage) > 1 && $row_c_lines["tipo"] == 4){
                    $arr_lines[$i] = $arr_subpage;
                    $i++;
                }
                
            }
            
            $img = array();
            if (file_exists($_SERVER['DOCUMENT_ROOT']."/"."images/personalizacao_".$row_c_g['id'].".jpg")) {
                $image_original = "images/personalizacao_".$row_c_g['id'].".jpg";
                $img = call_api_func('OBJ_image', $row_c_g["name"], $cont, $image_original);
            }

            if($price_min == 99999) $price_min = 0;
            
            $arr_g[] = array(
                "id"        =>  $row_c_g["id"],
                "name"      =>  $row_c_g["name"],
                "image"     =>  $img,
                "price_min" =>  call_api_func('OBJ_money',$price_min, $MOEDA['id']),
                "price_max" =>  call_api_func('OBJ_money',$price_max, $MOEDA['id']),
                "steps"     =>  $arr_lines
            );
        }

        $arr_group = array(
            "name"      => $row_c["name"],
            "content"   => $row_c["description"],
            "childs"    => $arr_g
        );

    }
    
    return serialize($arr_group);
    
}

?>
