<?

function _raffleCancelEntry(){

    global $sslocation;
    $_POST = decode_array_to_UTF8($_POST);

    $raffle_id      = (int)$_POST['raffle'];
    $order_id       = (int)$_POST['order'];
    $order_token    = $_POST['token'];

    $payload = array();
    $line_processed = 0;
    
    $raffle = cms_fetch_assoc( cms_query("SELECT `id` FROM `sorteios` WHERE `id`='".$raffle_id."' AND `estado`=0") );

    if( (int)$raffle['id'] > 0 ){

        require_once $_SERVER['DOCUMENT_ROOT'].'/api/lib/client/client_rest.php';

        $r = new Rest($sslocation.'/checkout/st/payments');

        cms_query("SET SESSION wait_timeout = 90");

        $order = cms_fetch_assoc( cms_query("SELECT `id`, `token`, `datahora`, `ip_client`, `tracking_status`, `email_cliente`
                                                FROM `ec_encomendas` WHERE `id`='".$order_id."'") );

        $order_token_calculated = md5($order['token']."|||".$order['datahora']."|||".$order['ip_client']);
        if( (int)$order['tracking_status'] == -90 && $order_token == $order_token_calculated ){
            
            $body = array( "order" => $order['id'], "token" => $order_token_calculated );

            $resp = $r->post("/void.php", $body);
            $resp = json_decode($resp, true);

            if( (int)$resp['success'] == 1 ){

                cms_query("UPDATE `sorteios_linhas_encomendas` SET `pagamento_cancelado`=1 WHERE `encomenda_id`='".$order['id']."'");
                $line_processed = 1;

            }      

        }

    }
    
    if( $line_processed ){
        $payload['success'] = 1;
    }else{
        $payload['error'] = 1;
    }

    return serialize($payload);

}

?>