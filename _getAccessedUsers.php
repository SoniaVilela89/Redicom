<?
  
function _getAccessedUsers(){
     
    global $LG, $INFO_SUBMENU, $INFO_NAV_PAG;
    

    $users = array();

    foreach($_COOKIE['ACCESSED_USERS'] as $k => $v){
        $v = base64_decode($v);
        $u = unserialize($v);
                                             
        $s    = "SELECT id,nome,email,registed_at FROM _tusers WHERE id='%d' LIMIT 0,1";
        $f    = sprintf($s, $u['user_id']);
        $q    = cms_query($f);
        $user = cms_fetch_assoc($q);
        
        $arr_nome = explode(" ", $user['nome']);
        
        if(count($arr_nome)==1){
            $abr = substr($arr_nome[0], 0, 2);
        }else{
            $abr = substr($arr_nome[0], 0, 1).substr($arr_nome[count($arr_nome)-1], 0, 1);        
        }        
        $abr = strtoupper($abr);
        
        $date = time_ago($u['date']);
        
        $link = md5($user['id'].'|'.$user['email'].'|'.$user['registed_at']);

        $users[$u['date']] = array("nome" => $user['nome'], "date" => $date, "abreviatura" => $abr, "link" => $link, "id" => $user["id"]);     
    }

    krsort($users);

    $arr = array();
    $arr['users'] = $users;
    
    return serialize($arr);
}


function time_ago($ptime){

    $etime  = time() - strtotime($ptime);

    $time   = time();
    $p_time = strtotime(date("Y-m-01", strtotime($ptime)));
    $a_time = strtotime(date("Y-m-01", $time));
    $n_time = date("Y-m-d",strtotime(date("Y-m-d", $time)));
 
    if ($etime < 60)
    {
        return "Agora mesmo";
    }
    
    $etime = round($etime / 60,0);
    
    if ($etime < 60)
    {
        if($etime=="1") return $etime." minuto atrás";
        else return $etime." minutos atrás";
    }
    $etime = round($etime / 60,0);
    
    if ($etime < 48)
    {
        if($etime=="1") return $etime." hora atrás";
        else return $etime." horas atrás";
    } 
 
    $etime = round($etime / 24,0);
    
    if ($etime < 30)
    {
        if($etime=="1") return $etime." dia atrás";
        else return $etime." dias atrás";
    }
    
    if ($ptime == date("Y-m-d", strtotime("-1 days", $time)))
        return "Ontem";
    
    for ($i=2;$i<=6;$i++)
    {
      if ($ptime == date("Y-m-d", strtotime("-$i days", $time)))
        return date("l", strtotime("-$i days", $time));
    }
    for ($i=7;$i<=13;$i++)
    {
      if ($ptime == date("Y-m-d", strtotime("-$i days", $time)))
        return "Há 1 semana";
    }
     if (date("Y-m", strtotime($ptime)) == date("Y-m", $time))
        return "Este mês";

    if (date("Y-m", strtotime($ptime)) == date("Y-m", strtotime("-1 months", $a_time)))
        return "Mês passado";
    for ($i=2;$i<=23;$i++)
    {
      if (date("Y-m", strtotime($ptime)) == date("Y-m", strtotime("-$i months", $a_time))){
        if($i=="1") return $i." mês atrás";
        else return $i." meses atrás";
      }
    }

    for ($i=2;$i<=20;$i++)
    {
      if (date("Y", strtotime($ptime)) == date("Y", strtotime("-$i years", $a_time))){
        if($i=="1") return $i." ano atrás";
        else return $i." anos atrás";
      }
    }

    return "Nunca";
}

?>
