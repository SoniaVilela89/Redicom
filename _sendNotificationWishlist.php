<?

# Descontinuado
function _sendNotificationWishlist($promo_id=null, $s_date=null){

    global $pagetitle, $sslocation, $eComm;
    
    if ($promo_id > 0){
        $promo_id = (int)$promo_id;
    }else{
        $promo_id = (int)params('promo_id');
    }
    
    if ($s_data!=""){
        $seg_data = $s_data;
    }else{
        $seg_data = params('s_date');
    }

    
    $s = "SELECT pl.sku, p.crit_tipo_cliente, p.id as id_promo, w.*, t.*, t.id as cliente_id, p.excluir_tipo_cliente
                FROM ec_promocoes_lines_values pl
                    INNER JOIN registos_wishlist w ON pl.sku = w.ref AND pl.id_promocao = '$promo_id' AND w.wishlist_status_id='0'
                    INNER JOIN ec_promocoes p ON pl.id_promocao=p.id AND (p.paises IS NULL OR p.paises = '' OR CONCAT(',',p.paises,',') LIKE CONCAT('%,', w.pais_cliente,',%')) AND p.deleted='0' AND p.is_checkout='0'
                    INNER JOIN _tusers t ON w.id_cliente=t.id and w.id_cliente REGEXP '^-?[0-9]+$'
                    GROUP BY w.id_cliente";

    $q = cms_query($s);        
    while($r = cms_fetch_assoc($q)){


        if ( $r['crit_tipo_cliente']!="" ){

            $tipo_validos   = explode(",", $r['crit_tipo_cliente']);
            $tipos_cliente  = explode(",", $r['tipo']);

            $retirar = 0;
            foreach( $tipo_validos as $k => $v ){
                if(in_array($v, $tipos_cliente) ) $retirar++;
            }

            if($retirar==0){
              continue;
            }
        }
        
        if ( $r['excluir_tipo_cliente'] != "" ){
            
            if( trim($r['tipo']) == '' ){ continue;}

            $tipo_validos   = explode(",", $r['excluir_tipo_cliente']);
            $tipos_cliente  = explode(",", $r['tipo']);

            $intersect = array_intersect($tipo_validos, $tipos_cliente);
            
            if(count($intersect) > 0) continue;
            
        }
        
        $info_wish              = array();
        $info_wish['products']  = array();

        
        $mes          = date("m");
        $ano          = date("Y");
        
        $email        = $r['email'];
        $id_cliente   = $r['cliente_id'];

        $sql_user     = cms_query("SELECT * FROM _tusers WHERE id='$id_cliente' LIMIT 0,1");
        $_usr         =  cms_fetch_assoc($sql_user);
        
        $sql = "SELECT w.*
                    FROM ec_promocoes_lines_values pl
                        INNER JOIN registos_wishlist w ON pl.sku = w.ref AND w.id_cliente='".$r["id_cliente"]."' AND w.wishlist_status_id='0' AND pl.id_promocao='".$r["id_promo"]."'
                    GROUP BY pl.sku";
             
        $res = cms_query($sql);
        while($row = cms_fetch_assoc($res)){
            $info_wish['products'][$row['pid']] = $row['pid'];
            cms_query("UPDATE registos_wishlist SET wishlist_status_id='$wish_promo' WHERE pid='".$row['pid']."' AND id_cliente='".$id_cliente."'");
        }
        

        $COUNTRY  = $_SESSION['_COUNTRY'] = $eComm->countryInfo($r['pais']);
        $MARKET   = $_SESSION['_MARKET']  = $eComm->marketInfo($COUNTRY['id']);
        $MOEDA    = $_SESSION['_MOEDA']   = $eComm->moedaInfo($MARKET['moeda']);
        
        
        $userID = $_usr['id'];   
        $_SESSION['EC_USER']['tipo'] = $_usr['tipo'];  
        
        
        $segmentos = explode(',', $_SESSION["EC_USER"]['tipo']);    
        asort($segmentos);
        foreach($segmentos as $k => $v){
            if($v>499){
                unset($segmentos[$k]);
            }
        }
        $_SESSION['segmentos'] = implode(",", $segmentos);


        $LANG = $r["idioma_user"];
        
        if($LANG=='es') $LANG='sp';
        if($LANG=='en') $LANG='gb';

        $LG = $_SESSION['LG'] = $LANG;


        # Grava o envio de um aviso de promoção wishlist                                            
        $go = cms_query( "INSERT INTO `b2c_wishlist_status` SET
                                `user_id`='$id_cliente',
                                `email`='$email',
                                `pais`='$LG',
                                `mes`='$mes',
                                `ano`='$ano',
                                `promocao_id`='".$promo_id."',
                                `data_2momento`='".$seg_data."'" );

        $wish_promo = cms_insert_id(); 
        
        cms_query("UPDATE ec_promocoes SET email_promo_wishlist='$wish_promo' WHERE id='".$promo_id."'");
        
        require_once '_getProductSimple.php';

        $produtos = array();
        foreach($info_wish['products'] as $key => $value)
        {

            $y    = _getProductSimple(0,0,$value['pid']);

            $x    = unserialize($y);

            $prod = $x['product'];

            get_layout_prod($produtos, $prod);
        }

        if(count($produtos)<1) continue;

        $produtos_f = array();
        foreach($produtos as $k => $v){
            $v['link']    = $sslocation."/api/gtu.php?action=4&idb=".$wish_promo."&url=".base64_encode($v['link']);
            $produtos_f[] = $v;
        }


        $email_body               = call_api_func("get_line_table","email_templates", "id='8'");
        $email_body['bloco'.$LG]  = str_ireplace("{NOME}", $sql_user['nome'], $email_body['bloco'.$LG]);

        $y                = get_info_geral_email($LG, $MARKET, $campanha, $_usr, $extra);
        $y['LINK']        = $sslocation."/api/gtu.php?action=4&idb=".$wish_promo."&url=".base64_encode($sslocation);
        $y['PRODUTOS']    = $produtos_f;
        $y['negar_exp']   = '';
        $y['TITULO']      = $email_body['assunto'.$LG];
        $y['SUBTITULO']   = $email_body['bloco'.$LG];
        $y['FINALIZAR']   = nl2br(estr(220));



        $content  = cms_real_escape_string(serialize($y));
        $id_email = saveEmailInBD_Marketing($r['email'], $email_body['assunto'.$LG], $content, $id_cliente, 0, "Wishlist - 1º Momento", 1, 0, 'wl', 0, $y['view_online_code']);
        cms_query("UPDATE b2c_wishlist_status SET email_queue_id='$id_email' WHERE id='".$wish_promo."'");
    }

    return serialize(array("0"=>"1"));
}
?>
