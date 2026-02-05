<?

function _getPreSaleComparator($page_id=0, $store_id=0){
    
    global $userID, $LG;


    $response = array();

    $row = call_api_func('get_pagina', $page_id, "_trubricas"); 
     
    $response['groups'] = array();    
    
                                                            
    if($row['sublevel']==60 && $row['catalogo']>0 && $row['catalogo_comparativo']>0 && (int)$userID>0){
                                                                       
        $qGv = cms_query("SELECT id, nomept, nome$LG as nome, nivel1_campo, nivel1_valor FROM b2b_comparador_prevenda GROUP BY nivel1_campo, nivel1_valor, nomept ORDER BY ordem ASC, id ASC");
        if(cms_num_rows($qGv) > 0){
          $store     = 0;
          $arrStores = array();
         
          while($fGv = cms_fetch_assoc($qGv)){ 

            $gruposN2  = array();
             
            if($row['catalogo']==$row['catalogo_comparativo']){
                $arrCompC = getComparadorPreVenda(0, $row['catalogo_comparativo'], $fGv, $gruposN2, $row['data_inicio_comparativo'], $row['data_fim_comparativo']);
                $arrComp  = getComparadorPreVenda($row['catalogo'], 0, $fGv, $gruposN2, $row['data_inicio_comparativo'], $row['data_fim_comparativo']);
                
                $arrComp  = array_merge($arrComp, $arrCompC);
                        
            }else{
                $arrComp  = getComparadorPreVenda($row['catalogo'], $row['catalogo_comparativo'], $fGv, $gruposN2, $row['data_inicio_comparativo'], $row['data_fim_comparativo']);
            }
            
            
            foreach ($gruposN2 as $k=>$fPv) {
            
                $IDString = "i".$fGv['id'];
                $response['groups'][$IDString]['name']                   = $fPv['nome'];
                $response['groups'][$IDString]['values']                 = array("catalog1"=>array("value"=>0), "catalog2"=>array("value"=>0), "compare"=>array("value"=>0));  
                $response['groups'][$IDString]['lines']["n2".$fPv['id']] = array("name" => $fPv['nome2'], "values"=> array("catalog1" => array(),"catalog2" => array(), "compare" => array())); 	
            
                     
                if(!empty($arrComp)){
                  foreach ($arrComp as $key=>$value) {
                  
                    if($value['grupo']!=$fPv['id']) continue;
                        
                    //Lojas                   
                    $lj = (int)$value['col1'];  
                    if(!isset($arrStores[$lj]) && $lj!=0){
                      $fStore   = cms_fetch_assoc(cms_query("SELECT id, nome as name FROM _tusers_lojas WHERE id='".$lj."' "));
                      $arrStores[$fStore['id']] = $fStore;
                    }
                    
                    if($store_id>0 && $lj!=$store_id) continue;
                    if($store==0)                     $store = $lj;
                    if($store>0 && $lj!=$store)       continue;
                    
                    if($row['catalogo']==$value['catalogo']){
                   	    $response['groups'][$IDString]['lines']["n2".$fPv['id']]['values']['catalog1']['value']    += number_format($value['total'], 2, '.', '');
                        $response['groups'][$IDString]['lines']["n2".$fPv['id']]['values']['catalog1']['quantity'] += $value['qnt'];
                    }else{
                        $response['groups'][$IDString]['lines']["n2".$fPv['id']]['values']['catalog2']['value']    += number_format($value['total'], 2, '.', '');
                        $response['groups'][$IDString]['lines']["n2".$fPv['id']]['values']['catalog2']['quantity'] += $value['qnt'];
                    }
                    
                  }
                  
                }
               
                                                             
                foreach ($response['groups'][$IDString]['lines'] as $key=>$value) {
                  $response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value']          = number_format($response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value'], 2, '.', '');
                  
               
                  $response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value']          = number_format($response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value'], 2, '.', '');   
                  
                  $response['groups'][$IDString]['lines'][$key]['values']['compare']['total']           = $response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value']+$response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value'];
                  $response['groups'][$IDString]['lines'][$key]['values']['compare']['value_diff']      = $response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value']-$response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value'];
                  $response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage']      = (($response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value']*100)/$response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value']);
                  
                  if(is_nan($response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage']))         $response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage'] = 0;
                  if(is_infinite($response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage']))    $response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage'] = 100;
                  
                  $response['groups'][$IDString]['lines'][$key]['values']['compare']['value_diff'] = number_format($response['groups'][$IDString]['lines'][$key]['values']['compare']['value_diff'], 2, '.', '');
                  $response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage'] = number_format($response['groups'][$IDString]['lines'][$key]['values']['compare']['percentage'], 0, '.', '');
                
                  $response['groups'][$IDString]['values']['catalog1']['value']    += $response['groups'][$IDString]['lines'][$key]['values']['catalog1']['value'];
                  $response['groups'][$IDString]['values']['catalog2']['value']    += $response['groups'][$IDString]['lines'][$key]['values']['catalog2']['value'];
                  $response['groups'][$IDString]['values']['compare']['quantity']  += $response['groups'][$IDString]['lines'][$key]['values']['catalog1']['quantity'];
                  $response['groups'][$IDString]['values']['compare']['quantity']  += $response['groups'][$IDString]['lines'][$key]['values']['catalog2']['quantity'];
                } 
            
            
                $response['groups'][$IDString]['values']['compare']['value'] = $response['groups'][$IDString]['values']['catalog1']['value']-$response['groups'][$IDString]['values']['catalog2']['value'];
                $response['groups'][$IDString]['values']['compare']['total'] = $response['groups'][$IDString]['values']['catalog1']['value']+$response['groups'][$IDString]['values']['catalog2']['value']; 
            
           
            }   
          }
        }
          
                          
        $response['stores']                 = $arrStores;
        $response['store_selected']         = $store; 
        
                                                                      
        $response['shop']                   = call_api_func('OBJ_shop_mini');     
        $response['account_expressions']    = call_api_func('getAccountExpressions');
    } 
                                         
    return serialize($response); 
}
  
  

function getComparadorPreVenda($catalogo, $catalogoComparativo, $grupo, &$gruposN2 = array(), $dataI='0000-00-00', $dataF='9999-01-01'){
  
  global $LG, $userID;
  
  $arrComp = array();
  
  
  $sqlCase = "";
  $qPv = cms_query("SELECT id, nome$LG as nome, nome2$LG as nome2, nivel2_campo, nivel2_valor FROM b2b_comparador_prevenda WHERE nomept='".$grupo['nomept']."' AND nivel1_campo='".$grupo['nivel1_campo']."' AND nivel1_valor='".$grupo['nivel1_valor']."' ORDER BY ordem ASC, id ASC");
  if(cms_num_rows($qPv)>0){
    $sqlCase = "CASE";  
    while($fPv = cms_fetch_assoc($qPv)){
        $gruposN2[] = $fPv;  
        if(trim($fPv['nivel2_valor'])!=''){
          $arrN2   =  explode("|", $fPv['nivel2_campo']);    
          $sqlCase .= " WHEN ".$arrN2[1]." IN (".$fPv['nivel2_valor'].") THEN ".$fPv['id'];
        }
    }
    $sqlCase .=" END";
  }
  
  if($sqlCase=='') return $arrComp;  
  
  
  $arrN1  = explode("|", $grupo['nivel1_campo']);
  
      
  if($catalogo>0)             $addWhere = " AND ec_encomendas_lines.page_cat_id='".$catalogo."'  AND ec_encomendas_lines.data>=DATE_ADD(NOW(), INTERVAL -30 DAY)";
  if($catalogoComparativo>0)  $addWhere = " AND ec_encomendas_lines.page_cat_id='".$catalogoComparativo."' AND ec_encomendas_lines.data>='".$dataI."' AND ec_encomendas_lines.data<='".$dataF."'";               
  
  if($catalogo>0 && $catalogoComparativo>0) $addWhere = " AND ( (ec_encomendas_lines.page_cat_id='".$catalogoComparativo."' AND ec_encomendas_lines.data>='".$dataI."' AND ec_encomendas_lines.data<='".$dataF."') OR
                                                           (ec_encomendas_lines.page_cat_id='".$catalogo."'  AND ec_encomendas_lines.data>=DATE_ADD(NOW(), INTERVAL -30 DAY)) )";
  
  
//   $sql = "SELECT SUM(
//                       CASE
//                       WHEN ec_encomendas_lines.valoruni_sem_iva > 0 THEN  ec_encomendas_lines.valoruni_sem_iva
//                       ELSE ec_encomendas_lines.valoruni
//                   	END
//                     ) AS total,
//                     ".$arrN1[1]." AS n1,
//                     ($sqlCase) AS grupo,  
//                     SUM(ec_encomendas_lines.qnt) AS qnt,
//                     ec_encomendas_lines.page_cat_id AS catalogo,
//                     ec_encomendas_lines.col1 
//                   FROM ec_encomendas_lines 
//                   INNER JOIN registos ON registos.id=ec_encomendas_lines.pid
//               WHERE ec_encomendas_lines.id_cliente='".$userID."' AND ec_encomendas_lines.status>=0 AND ec_encomendas_lines.status NOT IN (100) 
//                       AND ".$arrN1[1]." IN(".$grupo['nivel1_valor'].") ".$addNivel2." ".$addWhere." 
//               GROUP BY ec_encomendas_lines.page_cat_id, grupo, col1
//               ORDER BY ec_encomendas_lines.id DESC";

  $sql = "SELECT  SUM(
                    CASE
                      WHEN ec_encomendas_lines.valoruni_sem_iva > 0 THEN  ec_encomendas_lines.valoruni_sem_iva*ec_encomendas_lines.qnt
                      ELSE ec_encomendas_lines.valoruni*ec_encomendas_lines.qnt
                  	END
                    ) AS total,
                    ".$arrN1[1]." AS n1,
                    ($sqlCase) AS grupo,  
                    SUM(ec_encomendas_lines.qnt) AS qnt,
                    ec_encomendas_lines.page_cat_id AS catalogo,
                    ec_encomendas_lines.col1 
                  FROM ec_encomendas_lines 
                  INNER JOIN registos ON registos.id=ec_encomendas_lines.pid
              WHERE ec_encomendas_lines.id_cliente='".$userID."' AND ec_encomendas_lines.status>=0 AND ec_encomendas_lines.status NOT IN (100) 
                      AND ".$arrN1[1]." IN(".$grupo['nivel1_valor'].") ".$addNivel2." ".$addWhere." 
              GROUP BY ec_encomendas_lines.page_cat_id, grupo, col1
              ORDER BY ec_encomendas_lines.id DESC"; 


                             
  $qOrder = cms_query($sql);       
  
  
  while($fOrder = cms_fetch_assoc($qOrder)){
      
      if($catalogo==0)    $fOrder['catalogo'] = 999999;

      $arrComp[] = $fOrder; 
  }

  return $arrComp; 
} 

?>
