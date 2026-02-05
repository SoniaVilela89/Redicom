<?php

function _getSizeGuideTable($prod_id=0){
    
    if ($prod_id == 0 ){
       $guide_id  = (int)params('prod_id');
    }
    
    global $LG;
    global $COUNTRY;

    $resp['product'] = call_api_func('get_product', $prod_id, '', $page_id, 1, 0, 0);

    $body = array();
    $part = array();
    
    $arr_sizes = array();
    foreach($resp['product']['variants'] as $k => $v){

        $body_info = array();
        $part_info = array();

        $sql_guide = "SELECT rgtv.*, rgt.nome$LG as nome, rgt.tipo as tipo FROM registos_guia_tamanho_valores rgtv INNER JOIN registos_guia_tamanho rgt ON rgtv.registos_guia_tamanho_id=rgt.id WHERE sku='".$v["sku"]."' AND (rgtv.cm != '' OR rgtv.polegadas != '')";
        $res_guide = cms_query($sql_guide);
        while($row_guide = cms_fetch_assoc($res_guide)){
            if($row_guide["tipo"] == 0){
                $part_info[] = array(
                    "zone"      => $row_guide["nome"],
                    "cm"        => $row_guide["cm"],
                    "inches"    => $row_guide["polegadas"],
                );
            }else{
                $body_info[] = array(
                    "zone"      => $row_guide["nome"],
                    "cm"        => $row_guide["cm"],
                    "inches"    => $row_guide["polegadas"],
                );
            }
        }

        $arr_sizes = array(
            "id"                    => $v["id"],
            "sku"                   => $v["sku"],
            "size"                  => $v["size"],
            "available"             => $v["available"],
            "stock_real"            => $v["stock_real"],
            "inventory_quantity"    => $v["inventory_quantity"],
        ); 

        if(count($body_info) > 0){
            $body[$v["sku"]] = $arr_sizes;
            $body[$v["sku"]]["info"] = $body_info;
        }

        if(count($part_info) > 0){
            $part[$v["sku"]] = $arr_sizes;
            $part[$v["sku"]]["info"] = $part_info;
        }
    }

    $body = array_values($body);
    $part = array_values($part);

    $sql_obs = "SELECT * FROM registos_guia_tamanho_info WHERE sku_family='".$resp['product']['sku_family']."' AND cor='".$resp['product']['selected_variant']['color']['color_id']."' LIMIT 0,1";
    $res_obs = cms_query($sql_obs);
    $row_obs = cms_fetch_assoc($res_obs);

    $sql_info = "SELECT peca_bloco$LG as peca, corpo_bloco$LG as corpo FROM registos_guia_tamanho_classificador WHERE classificador_id='".$resp['product']['generico20_id']."' LIMIT 0,1";
    $res_info = cms_query($sql_info);
    $row_info = cms_fetch_assoc($res_info);

    $image_corpo = "";
    $cam  = "images/guia_tamanho_corpo_".$resp['product']['generico20_id'].".jpg";
    if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
        $image_corpo = imageOBJ($resp['product']['list_title'], 1, $cam);
    }

    $image_peca = "";
    $cam  = "images/guia_tamanho_peca_".$resp['product']['generico20_id'].".jpg";
    if(file_exists($_SERVER['DOCUMENT_ROOT']."/".$cam)){
        $image_peca = imageOBJ($resp['product']['list_title'], 1, $cam);
    }

    $arr["body"] = $body;
    $arr["part"] = $part;
    $arr["obs"]  = $row_obs["nome".$LG];
    $arr["content_body"] = $row_info["corpo"];
    $arr["image_body"] = $image_corpo;
    $arr["content_part"] = $row_info["peca"];
    $arr["image_part"] = $image_peca;
    
    return serialize($arr);    
}
?>
