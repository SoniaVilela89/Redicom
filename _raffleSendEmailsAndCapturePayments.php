<?

function _raffleSendEmailsAndCapturePayments(){

    global $sslocation;

    $_POST = decode_array_to_UTF8($_POST);

    $raffle_id = (int)$_POST['raffle'];
    $raffle_token = $_POST['token'];

    $payload = array();

    $raffle = cms_fetch_assoc( cms_query("SELECT `id`, `data_inicio`, `data_fim`, `data_finalizacao` FROM `sorteios` WHERE `id`='".$raffle_id."' AND `estado`=1") );

    if( (int)$raffle['id'] > 0 && md5($raffle['data_inicio']."|||".$raffle['data_fim']."|||".$raffle['data_finalizacao']) == $raffle_token ){

        require_once $_SERVER['DOCUMENT_ROOT'].'/api/lib/client/client_rest.php';

        $r = new Rest($sslocation.'/checkout/st/payments');

        cms_query("SET SESSION wait_timeout = 90");

        $total_lines = 0;
        $lines_processed = 0;
        
        $query = cms_query("SELECT `sorteios_linhas_encomendas`.`encomenda_id`, `sorteios_linhas_encomendas`.`premiado`,
                                `sorteios_linhas_encomendas`.`pagamento_capturado`, `sorteios_linhas_encomendas`.`pagamento_cancelado`,
                                `sorteios_linhas_encomendas`.`id`
                            FROM `sorteios_linhas_encomendas`
                                INNER JOIN `sorteios_linhas` ON `sorteios_linhas`.`id`=`sorteios_linhas_encomendas`.`sorteios_linhas_id` 
                                    AND `sorteios_linhas`.`sorteio_id`='".$raffle['id']."'
                            WHERE `sorteios_linhas_encomendas`.`email_enviado`=0");

        while($raffle_order = cms_fetch_assoc($query)){

            $order = cms_fetch_assoc( cms_query("SELECT `id`, `token`, `datahora`, `ip_client`, `tracking_status`, `email_cliente`
                                                    FROM `ec_encomendas` WHERE `id`='".$raffle_order['encomenda_id']."'") );
            if( (int)$order['tracking_status'] == -90 ){

                $total_lines++;

                $body = array( "order" => $order['id'], "token" => md5($order['token']."|||".$order['datahora']."|||".$order['ip_client']) );

                if( (int)$raffle_order['premiado'] == 1 && (int)$raffle_order['pagamento_capturado'] == 0 ){

                    $resp = $r->post("/capture.php", $body);
                    $resp = json_decode($resp, true);

                    if( (int)$resp['success'] == 1 ){
                        
                        cms_query("UPDATE `sorteios_linhas_encomendas` SET `pagamento_capturado`=1 WHERE `id`='".$raffle_order['id']."'");
                        $raffle_order['pagamento_capturado'] = 1;

                    }

                }elseif( (int)$raffle_order['premiado'] == 0 && (int)$raffle_order['pagamento_cancelado'] == 0 ){

                    $resp = $r->post("/void.php", $body);
                    $resp = json_decode($resp, true);

                    if( (int)$resp['success'] == 1 ){
                        
                        cms_query("UPDATE `sorteios_linhas_encomendas` SET `pagamento_cancelado`=1 WHERE `id`='".$raffle_order['id']."'");
                        $raffle_order['pagamento_cancelado'] = 1;

                    }

                }

                if( (int)$raffle_order['pagamento_capturado'] == 1 || (int)$raffle_order['pagamento_cancelado'] == 1 ){

                    $template = ( (int)$raffle_order['pagamento_capturado'] == 1 ) ? 77 : 76;
                    
                    #require_once _ROOT.'/api/controllers/_sendEmailGest.php';
                    #if( _sendEmailGest($order["id"], $template, 0, $order["email_cliente"], 0, "") ){
                    require_once _ROOT.'/api/controllers/_sendEmailConfirmationEnc.php';
                    if( _sendEmailConfirmationEnc($order["id"], $order["email_cliente"], $template) ){
                        cms_query("UPDATE `sorteios_linhas_encomendas` SET `email_enviado`=1 WHERE `id`='".$raffle_order['id']."'");
                        $lines_processed++;
                    }

                }

            }

        }

        if( $total_lines == $lines_processed ){
            $payload['success'] = 1;
        }else{
            $payload['error'] = 1;
        }

    }

    return serialize($payload);

}

?>
