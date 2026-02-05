<?
function _updateUserBudgetInfo($user_info=null){

    if( is_null($user_info) || empty($user_info) ){

        if( isset($_POST) ){
            $user_info = $_POST;
        }else{
            $user_info = json_decode( file_get_contents('php://input'), true );
        }

    }

    if( is_null($user_info) || empty($user_info) ){
        return serialize(['success' => 0]);
    }
    
    if($_FILES) {
        $allowed    = Array();
        $allowed[]  = 'png';
        $allowed[]  = 'jpg';
        $allowed[]  = 'jpeg';

        foreach( $_FILES as $key=>$value ){
        
            if( $value['size'] == 0 ){
                return serialize(['success' => 0]);
            }
            
            $ext = explode('.', strtolower($value['name']));
            $ext = array_pop($ext);

            if( !in_array($ext, $allowed) ){
                return serialize(['success' => 0]);
            }

            $mimetype = mime_content_type($value['tmp_name']);
            if( $mimetype == "text/x-php" || stripos($mimetype,"php") !== false ){
                return serialize(['success' => 0]);
            }
            
            if( $value['size'] > 0 ){
                $file_final = "../images/user_".$_SESSION['EC_USER']['id']."_budget_logo.".$ext;
                unlink($file_final);
                copy($value['tmp_name'], $file_final);
                break;
            }
            
        }
        
    }
    
    foreach( $user_info as $campo=>$valor ){
    
        if(strpos($campo, 'budget_') === 0) { #the prefix "budget_" indicates that the field is related to the budgets
            
            $user_info_value = safe_value(utf8_decode($valor));
            
            $f[] = "`".$campo."`='".$user_info_value."'";
            $_SESSION['EC_USER'][ $campo ] = $user_info_value;
        
        }
        
    }

    if( !empty($f) ){
        $update_success = cms_query("UPDATE `_tusers` SET " . implode(",",$f) . " WHERE `id`=".(int)$_SESSION['EC_USER']['id']);
    }else{
        return serialize(['success' => 0]);
    }

    return serialize(['success' => $update_success]);

}

?>
