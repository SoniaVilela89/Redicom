<?

function _getAccountClientCard($page_id=null)
{
     
    global $userID;
    global $eComm;
    global $LG;
    global $MOEDA;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }


    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    
    $user= call_api_func("get_line_table","_tusers", "id='".$_SESSION["EC_USER"]["id"]."'");
    $cartoes = array();
    $layout = $user["estado_cartao"];
    if($user["estado_cartao"]<1){  
        $layout = 0;  
        $s = "SELECT * FROM ec_cartoes_clientes WHERE hidden = 0 ORDER BY nome$LG";
        $q = cms_query($s);
        while($r = cms_fetch_assoc($q)){
            $cartoes[] = array("id" => $r['id'], "nome" => $r['nome'.$LG]); 
        }
    }
    
    $layout_pending = 0;
    if($layout==1){
        // $page_acc = call_api_func("get_line_table","ec_rubricas", "id='27'");
        // if($page_acc["embform"]==3){
            // $layout_pending = 1;
            // $arr['page'] =  call_api_func('pageOBJ',"29","29",0,"ec_rubricas");
        // }else{
            $arr['page'] =  call_api_func('pageOBJ',"28","28",0,"ec_rubricas");
        // }
    }
    
    if($layout==2){ 
      
      $s = "SELECT id, name$LG as name FROM _tusers_table_headers where active = 1 ";
      $q = cms_query($s);
      while($r = cms_fetch_assoc($q)){
          $data[$r['id']] = $r; 
      }      
 
      $s = "SELECT * FROM _tusers_table_data WHERE user_id='".$_SESSION["EC_USER"]["id"]."'";
      $q = cms_query($s);
      while($r = cms_fetch_assoc($q)){  
          $money = call_api_func('OBJ_money', $r['total'], $r['currency_id']);
          $r['total'] = $money['currency']['prefix'].number_format($money['value'], $money['currency']['number_dec'], $money['currency']['separator_dec'], $money['currency']['separator_mil']).$money['currency']['sufix'];      
          $data[$r['type_header']]['data'][] = $r; 
      }         
         
    }
        
    if($LG=='pt'){
        setlocale(LC_TIME, "pt_pt", "pt_pt.iso-8859-1", "pt_pt.utf-8", "portuguese");
        $date = strftime("%d %B %Y",strtotime($user['data_cartao']));
    }else{
        $date = strftime("%d %B, %Y",strtotime($user['data_cartao']));
    }  

    $points   = $eComm->getTotalPoints($user['id']);
    $credit   = $points * $MOEDA['valuePoint'];
    $money    = call_api_func('OBJ_money', $credit, $MOEDA['id']);
    $credit   = $money['currency']['prefix'].number_format($money['value'], $money['currency']['number_dec'], $money['currency']['separator_dec'], $money['currency']['separator_mil']).$money['currency']['sufix'];      
          
    $arr['mycard'] = array(
      "num_card"                      =>  $user['f_code'],
      "date_card"                     =>  $date,
      "credit_card"                   =>  $credit
    );
    
    $arr['card_data']                 = $data;    
    $arr['cards']                     = $cartoes;    
    $arr["layout_card"]               = $layout;
    $arr["layout_pending"]            = $layout_pending;
    $arr['account_expressions']       = call_api_func('getAccountExpressions');       


    if(is_callable('custom_controller_account_client_card')) {
        call_user_func_array('custom_controller_account_client_card', array(&$arr));
    }

    return serialize($arr);

}

?>
