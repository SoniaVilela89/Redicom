<?

function _getSalesUsers($page_id=0)
{

    global $fx, $LG, $INFO_SUBMENU, $INFO_NAV_PAG;
        
    if ($page_id==0){
       $page_id = (int)params('page_id');
    }

    $arr = array();
    
    $term = "";
    if(isset($_GET["uterm"]) && strlen($_GET["uterm"]) > 2)
        $term = $_GET["uterm"];
        
    $users = array();
    if(trim($term)!=""){
        $s = "SELECT id,nome FROM _tusers WHERE sem_registo=0 AND nome!='' AND 
        (email='".$term."' OR nif='".$term."' OR telefone LIKE '%".$term."%') 
        GROUP BY SUBSTR(nome,1,1) ORDER BY nome DESC, nome";
    }else{
        $s = "SELECT id,nome FROM _tusers WHERE sem_registo=0 AND nome!='' GROUP BY SUBSTR(nome,1,1) ORDER BY nome DESC, nome";
    }

    $q = cms_query($s);
    while($r = cms_fetch_assoc($q)){
    
        $letter = strtoupper($r['nome'][0]);        
                  
        if(is_numeric($letter) || !preg_match('/^[a-zA-Z]+/', $letter)) $letter = "#";
             
        $users[$letter] = $letter;    
    }   
    

    $LETRA = $_GET['ltr'];
    
    if(!isset($_GET['ltr']) || $LETRA=='') {
        $LETRA = reset($users);       
    } 
    
    $glossario = array();
    for($i=65;$i<=90;$i++){
          
        (chr($i)==$LETRA) ?  $selected = "selected" : $selected= '';
        $glossario[] = array(
            "letter"    => chr($i),
            "selected"  => $selected,
            "qtd"       => $users[chr($i)]
        );
    }
    
    ("all"==$LETRA || "#"==$LETRA) ?  $selected = "selected" : $selected= '';

    
    
    $temp = array(
        "letter" => "#",
        "selected"  => $selected,
        "qtd" => $users['#']
    );
    
    array_unshift($glossario, $temp);
    
       
    
     $where  = "";
//     if($LETRA=='all' || $LETRA=="#"){
//         $where  = "(LEFT(nome, 1) IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9')) ";
//     }
    
    
    $resp = array();

    if(trim($term)!=""){
        $s = "SELECT id,nome,email,nif,telefone,cidade,registed_at FROM _tusers 
        WHERE sem_registo=0 AND nome!=''  AND 
        (email='".$term."' OR nif='".$term."' OR telefone LIKE '%".$term."%') $where 
        ORDER BY nome";
    }else{
        $s = "SELECT id,nome,email,nif,telefone,cidade,registed_at FROM _tusers WHERE sem_registo=0 AND nome!='' AND $where ORDER BY nome ";
    }
    
    $q = cms_query($s);
    while($r = cms_fetch_assoc($q)){
        $r['link'] = md5($r['id'].'|'.$r['email'].'|'.$r['registed_at']);
        $resp[] = $r;
    }

    $arr['users'] = $resp;     
    $arr['letters'] = $glossario;   
    $arr['uterm'] = $term;

    
    $row = call_api_func('get_pagina', $page_id, "_trubricas");
    $arr['selected_page'] = call_api_func('OBJ_page', $row, $page_id, 0);  
    
    if(trim($term)!="")
        $arr['selected_page']['page_title'] = $arr['selected_page']['page_title'].": ".utf8_decode($term);
    
    $caminho = call_api_func('get_breadcrumb', $page_id);
    $arr['selected_page']['breadcrumb'] = $caminho;
    
    if($INFO_NAV_PAG == 1){   
        $pai = $caminho[1]['id_pag'];
        $arr['navigation_pages'] = call_api_func('pageOBJ',$pai, $page_id, 0);
    }
        
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    
    $arr['expressions'] = call_api_func('get_expressions', $page_id);

    return serialize($arr);
    
}

?>
