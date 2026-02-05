<?php
       
function _getCampaingDetails($id=null){
  
    global $CACHE_KEY, $LG, $fx;
    
    
    if(is_null($id)){
        $id = params('id');
    }
    
    
    $_cacheid = $CACHE_KEY."CMPDTL_".$LG;
    
    $dados = $fx->_GetCache($_cacheid, 60);

    if ($dados!=false && !isset($_GET['nocache'])){
        $campanha = $dados;
    }else{ 

        $sql_campaings = "SELECT id, nome$LG as nome, subtitulo$LG as subtitulo, bloco$LG as bloco 
                            FROM ec_campanhas 
                          WHERE id=$id
                          LIMIT 0,1";

        $res_campaings = cms_query($sql_campaings);
        $campanha = cms_fetch_assoc($res_campaings);
        
        $fx->_SetCache($_cacheid, $campanha, 0);
    }
    
   
    $arr = array(
        "id"        => $campanha["id"],
        "title"     => $campanha["nome"],
        "subtitle"  => $campanha["subtitulo"],
        "block"     => base64_encode($campanha["bloco"])
    );
 
    
    return serialize($arr);

}
