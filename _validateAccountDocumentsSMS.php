<?php
# My Account B2B: Controlador para validar por sms o download de notas de crédito

## options
# 1: enviar código sms
# 2: validar código sms + fazer download pdf ou gerar zip
# 3: fazer download pdf
# 4: fazer download zip
## $dados recebe: posição docs (ids documentos separados por |||) + code (código de valição sms)
function _validateAccountDocumentsSMS($option, $dados) {

    global $userID;

    
    $dados = base64_decode($dados);
    $dados = json_decode($dados, true);

    $q = cms_query("SELECT `id`,`nome`,`telefone`,`email`,`pais_indicativo_tel` FROM `_tusers` WHERE `id`=".$userID." AND `activo`=1 AND `sem_registo`=0 LIMIT 0,1");
    $user = cms_fetch_assoc($q);

	$dados['docs_ids'] = str_replace("|||", ",", $dados['docs']);
	$ids_array = array_filter(array_map('intval', explode(',', $dados['docs_ids'])));
    $dados['docs_ids'] = implode(',', $ids_array);


	if((int)$user['id']==0 || trim($user['telefone'])=="" || empty($ids_array)){

		$json = array();
	    $json['success'] = false;
	    $json['errorCode'] = 1;
	    die(json_encode($json));

	} else {

		switch ($option) {
			case '1':
			    $result = __sendSMSCode($dados, $user);
				break;

			case '2':

				$s = "SELECT `id` FROM `_tdocumentos` WHERE `id_user`=%d AND `id` IN (%s) AND `validado_sms`=0 AND `tipo`=2 LIMIT 0,1";
				$s = sprintf($s, $userID, $dados['docs_ids']);
				$q = cms_query($s);
				$r = cms_fetch_assoc($q);

				$result = true;
				if((int)$r['id']>0) {
					$result = __validateCode($dados);
				}

				if($result){
					$result = __downloadFiles($dados);
				}
				break;

			case '3':
				$result = __downloadFilePDF($dados['docs_ids']);
				break;

			case '4':
				$result = __downloadFileZIP($dados);
				break;

			default:
				$result = false;
				break;
		}

	}


	$json = array();

 	if($result == true){
        $json['success'] = true;
    } else {
        $json['success'] = false;
        $json['errorCode'] = 2;
    }

    die(json_encode($json));
}



function __downloadFiles($dados) {
    global $userID, $CONFIG_OPTIONS;

    $documents = explode("|||", $dados['docs']);

	if(count($documents) == 1) {

		$doc_id = (int)$documents[0];

	    if($doc_id > 0){

		    if((int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1) {
		    	$s = "UPDATE `_tdocumentos` SET `validado_sms`=1 WHERE `id_user`=%d AND `id`=%d AND `validado_sms`=0";
		    	$s = sprintf($s, $userID, $doc_id);
		    	cms_query($s);
		    }

	        return __downloadFilePDF($doc_id);
	    }

	} else {

	    $path 		= "../downloads/documents/";
	    $filename 	= "zip_".$userID."_".md5($userID.$dados['docs_ids']).".zip";
	    $filezip 	= $path.$filename;

	    $zip = new ZipArchive();
	    if ($zip->open($filezip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

	    	$s = "SELECT `id`,`num` FROM `_tdocumentos` WHERE `id_user`=%d AND `id` IN (%s)";
	    	$s = sprintf($s, $userID, $dados['docs_ids']);
	    	$r = cms_query($s);
	        while ($doc = cms_fetch_assoc($r)) {

	            $file = $path.$doc['num'].".pdf";

	            if (file_exists($file)) {
	                $zip->addFile($file, $doc['num'].".pdf");
	            }
	        }

	        $zip->close();

	        return true;
	    }

	}

	return false;
}



# Download ficheiro PDF
function __downloadFilePDF($doc_id) {
    global $userID, $CONFIG_OPTIONS;

    $s = "SELECT `id`,`num`,`validado_sms` FROM `_tdocumentos` WHERE `id_user`=%d AND `id`=%d LIMIT 0,1";
	$s = sprintf($s, $userID, (int)$doc_id);
    $doc = cms_fetch_assoc(cms_query($s));

    if((int)$doc['id']==0 || ( (int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1 && (int)$doc['validado_sms'] == 0  && $row_doc['tipo'] == 2) ) {
    	return false;
    }

    $file = "../downloads/documents/".$doc['num'].".pdf";
    ob_end_clean();
    header("Content-type: application/pdf");
    header("Content-disposition: attachment; filename=".urlencode($doc['num']).".pdf");
    readfile($file);
    ob_clean();
    flush();
    exit;
}


# Download ficheiro ZIP
function __downloadFileZIP($dados) {
    global $userID, $CONFIG_OPTIONS;

	$docs_ids = $dados['docs_ids'];

    $path 		= "../downloads/documents/";
    $filename 	= "zip_".$userID."_".md5($userID.$docs_ids).".zip";
    $file 		= $path.$filename;


	if (file_exists($file)) {

		if((int)$CONFIG_OPTIONS['MYACCOUNT_VALIDAR_DOCUMENTOS_SMS'] == 1) {
			$s = "UPDATE `_tdocumentos` SET `validado_sms`=1 WHERE `id_user`=%d AND `id` IN (%s) AND `validado_sms`=0";
			$s = sprintf($s, $userID, $docs_ids);
	    	cms_query($s);
	    }

		ob_end_clean();
		header("Content-Type: application/zip");
		header("Content-Disposition: attachment; filename=\"".$filename."\"");
		header("Content-Length: " . filesize($file));
		readfile($file);
		ob_clean();
		flush();
		exit;
	}

	return false;
}



function __sendSMSCode($dados, $user){

	global $LG, $userID, $slocation;

	if(trim($user['telefone'])=="") return false;


    $s = "SELECT `id` FROM `_tdocumentos` WHERE `id_user`=%d AND `id` IN (%s) AND `validado_sms`=0 LIMIT 0,1";
    $s = sprintf($s, $userID, $dados['docs_ids']);

    $documents = cms_fetch_assoc(cms_query($s));

    if((int)$documents['id']==0) {
		return false;
    }

	$code = getCodigo(4, true, false, false, false, false);

	$data = array(
	  "telemovel"		=> $user['telefone'],
	  "lg"				=> $LG,
	  "EMAIL"			=> $user['email'],
	  "CODE"			=> $code,
	  "CLIENT_NAME"		=> $user['nome'],
	  "int_country_id"	=> $user['pais_indicativo_tel'],
	  "USER_ID"			=> $user['id']
	);

	$data = serialize($data);
	$data = gzdeflate($data, 9);
	$data = gzdeflate($data, 9);
	$data = urlencode($data);
	$data = base64_encode($data);

	require_once $_SERVER["DOCUMENT_ROOT"].'/api/lib/client/client_rest.php';
	$r = new Rest($slocation.'/api/api.php');
	$resp = $r->get("/sendSMSGeneral/112/$data");
	$resp = json_decode($resp, true);

	if ((int)$resp['response'][0]==0){
	  return false;
	}

	$_SESSION['code_download'] = $code;

	return true;
}


function __validateCode($dados){
 	if(isset($_SESSION['code_download']) && $_SESSION['code_download']==$dados['code']){
        unset($_SESSION['code_download']);
        return true;
    }
    return false;
}


?>
