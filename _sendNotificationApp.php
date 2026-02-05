<?
# Envia notificações para a APP mobile
## Existe limite de envios imposto pelo WonderPush
## Envia para o token de um cliente, e usa os textos definidos nas campanhas

## WonderPush
## Firebase (2025-10-16 para a Salsa)


# recebe id da campanha/email, id do cliente e o tipo
## type=1 - campanhas
## type=2 - templates de email
function _sendNotificationApp($template_id, $user_id, $type=1){

    global $LG, $APP_WONDERPUSH_APIKEY, $APP_WONDERPUSH_ACCESS, $APP_FIREBASE_ACCOUNT_KEY_JSON, $PWA, $APP;

    if((int)$PWA != 2 && (int)$APP != 2) return serialize(["success" => 0, "errorCode" => 100]); # verifica se o site tem APP

    if(($APP_WONDERPUSH_APIKEY == "" || $APP_WONDERPUSH_ACCESS == "") && ($APP_FIREBASE_ACCOUNT_KEY_JSON == "")) return serialize(["success" => 0, "errorCode" => 101]);

    if($template_id > 0){
        $type           = (int)$type;
        $template_id    = (int)$template_id;
        $user_id        = (int)$user_id;
    } else {
        $type           = (int)params('type');
        $template_id    = (int)params('template_id');
        $user_id        = (int)params('user_id');
    }


    if($type == 2) {
        $dados = call_api_func("get_line_table", "ec_email_templates", "id='".$template_id."'");
    } else {
        $dados = call_api_func("get_line_table", "ec_campanhas", "id='".$template_id."'");
    }

    if((int)$dados['id']<1) return serialize(["success" => 0, "errorCode" => 102]);
    if((int)$dados['app_ativo']==0) return serialize(["success" => 0, "errorCode" => 103]);



    $user_data = call_api_func("get_line_table", "_tusers_pwa_data", "cliente_id='".$user_id."'");
    if(trim($user_data['notificacoes_token'] == '')) return serialize(["success" => 0, "errorCode" => 104]);

    $user = call_api_func("get_line_table", "_tusers", "id='".$user_id."'");
    $user['pwa_data'] = $user_data;

    if((int)$user['id_lingua'] > 0){
        $language_id = (int)$user['id_lingua'];
    } else {
        $country = call_api_func("get_line_table", "ec_paises", "id='".(int)$user['pais']."'");
        $language_id = $country['idioma'];
    }

    if((int)$language_id > 0){
        $language = call_api_func("get_line_table", "ec_language", "id='".$language_id."'");
        if( $language["code"] == 'es' )     $language["code"] = 'sp';
        elseif( $language["code"] == 'en' ) $language["code"] = 'gb';

        $LG = $language['code'];
    } else {
        $LG = "pt";
    }


    $notificacao['titulo'] = utf8_encode(trim($dados['app_nome'.$LG]));
    $notificacao['texto']  = utf8_encode(trim($dados['app_desc'.$LG]));

    if($notificacao['titulo'] == '' && $notificacao['texto'] == '') return serialize(["success" => 0, "errorCode" => 105]);


    # envio notificação por WonderPush
    if($APP_WONDERPUSH_APIKEY != "" && $APP_WONDERPUSH_ACCESS != "") {
        $result = __sendNotificationWonderPush($notificacao, $user);
    }

    # envio notificação por Firebase
    if($APP_FIREBASE_ACCOUNT_KEY_JSON != ""){
        $result = __sendNotificationFirebase($notificacao, $user);
    }

    # 2025-10-16
    # Salsa - usado para o Bloomreach
    if(is_callable('custom_app_send_notification_after')) {
        $continue = call_user_func('custom_app_send_notification_after', $notificacao, $user);
        return $continue;
    }


    if($result){
        return serialize(["success" => 1]);
    }

    return serialize(["success" => 0, "errorCode" => 106]);

}




# envia a notificação pelo WonderPush
function __sendNotificationWonderPush($notificacao, $user){

    global $APP_WONDERPUSH_APIKEY, $APP_WONDERPUSH_ACCESS;

    $token = $user['pwa_data']['notificacoes_token'];


    require_once($_SERVER['DOCUMENT_ROOT']."/plugins/app_mobile/wonderpush/require_wonderpush.php");

    $titulo = $notificacao['titulo'];
    $texto  = $notificacao['texto'];

    $wonderpush = new \WonderPush\WonderPush($APP_WONDERPUSH_ACCESS, $APP_WONDERPUSH_APIKEY);

    $response = $wonderpush->deliveries()->create(\WonderPush\Params\DeliveriesCreateParams::_new()
        ->setTargetDeviceIds([$token])
        ->setNotification(\WonderPush\Obj\Notification::_new()
        ->setAlert(\WonderPush\Obj\NotificationAlert::_new()
            ->setTitle($titulo)
            ->setText($texto)
            ->setAndroid(\WonderPush\Obj\NotificationAlertAndroid::_new()
                ->setPriority("high")
                ->setSound("default")
                ->setVibrate(true)
            )
            ->setIos(\WonderPush\Obj\NotificationAlertIos::_new()
                ->setSound("default")
            )
        ))
    );


    if($response->isSuccess() == "1") {
        return true;
    }

    return false;
}



function __sendNotificationFirebase($notificacao, $user) {

    global $APP_FIREBASE_ACCOUNT_KEY_JSON;

    $token = $user['pwa_data']['notificacoes_token'];

    # token do firebase tem mais de 40 caracteres
    if(strlen($token) < 40) return false;

    $serviceAccount = json_decode($APP_FIREBASE_ACCOUNT_KEY_JSON, true);
    if (!$serviceAccount) {
        return ['success' => false, 'error' => "JSON inválido"];
    }

    $projectId = $serviceAccount['project_id'];
    $clientEmail = $serviceAccount['client_email'];
    $privateKey = str_replace("\\n", "\n", $serviceAccount['private_key']);

    # 1. Criar JWT
    $header = base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $iat = time();
    $exp = $iat + 3600;
    $claimSet = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $iat,
        'exp' => $exp
    ];

    $headerB64 = str_replace(['+', '/', '='], ['-', '_', ''], $header);
    $claimB64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($claimSet)));
    $unsignedJwt = $headerB64 . '.' . $claimB64;

    openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $signatureB64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $unsignedJwt . '.' . $signatureB64;

    # 2. Obter access token
    $ch = curl_init("https://oauth2.googleapis.com/token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        return ['success' => false, 'error' => $tokenData];
    }
    $accessToken = $tokenData['access_token'];

    # 3. Payload da notificação
    $payload = [
        'message' => [
            'token' => trim($token),
            'notification' => [
                'title' => $notificacao['titulo'],
                'body' => $notificacao['texto']
            ]
        ]
    ];


    # 4. Enviar notificação
    $ch = curl_init("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);


    if($httpCode >= 200 && $httpCode < 300){
        return true;
    }
    
    return false;
}


?>
