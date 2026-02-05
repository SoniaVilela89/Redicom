<?php

function _getReviewTranslate($id_review=null){
   
    if( empty($id_review) ){
        $id_review      = params('id_review');
    }

    global $TRADUZIR_REVIEWS, $LG;

    if((int)$TRADUZIR_REVIEWS == 0) return serialize(array("0"=>"0"));

    $sql_review = "SELECT id, titulo, mensagem, lg
            FROM registos_avaliacoes 
            WHERE id='".$id_review."' LIMIT 0,1";
    $res_review = cms_query($sql_review);
    $review = cms_fetch_assoc($res_review);
    
    if((int)$review["id"] == 0) return serialize(array("0"=>"0"));

    $msg_translate = translateReview(($review['titulo']." |||| ".$review['mensagem']), $review["lg"], $LG);
    $arr_translate = explode(" |||| ", $msg_translate);
    $arr_msg["msg_translate"] = $arr_translate[1];
    $arr_msg["title_translate"] = $arr_translate[0];
    
    return serialize($arr_msg);
}
?>
