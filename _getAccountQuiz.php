<?

function _getAccountQuiz($page_id=null)
{

    global $userID;
    global $eComm;
    global $LG;

    if(is_null($page_id)){
        $page_id = (int)params('page_id');
    }


    $arr = array();
    $arr['page'] =  call_api_func('pageOBJ',$page_id,$page_id,0,"ec_rubricas");
    $arr['newForm'] = newForm($arr['page']['form']);

    $arr['account_pages'] =  call_api_func('pageOBJ',9,$page_id,0,"ec_rubricas");
    $arr['customer'] = call_api_func('getCustomer');
    $arr['shop'] = call_api_func('OBJ_shop_mini');
    $arr['account_expressions'] = call_api_func('getAccountExpressions');
    $arr['expressions'] = call_api_func('getExpressions');
    return serialize($arr);

}


function newForm($arr_form=array()){

    global $detect;

    $mobile = "DESKTOP";
    if(file_exists('api/lib/class.mobile_detect.php')){
        if($detect->isMobile() && !$detect->isTablet()){
            $mobile = 'MOBILE';
        }
    }else{
        if( strstr($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strstr($_SERVER['HTTP_USER_AGENT'],'Android') ){
            $mobile = "MOBILE";
        }
    }



    $arr_newForm = array();
    foreach($arr_form as $k => $v){
        $required = array();
        if($v["required"]==1){
            $required = array("required"=>true);
        }

        $labelPosition = "";
        if($mobile=="DESKTOP"){
            $labelPosition = "left-left";
        }

        switch ($v["type"]) {
            case 'text':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'textfield',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'date':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field form-datepicker',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'textfield',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'textarea':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'textarea',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'email':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'email',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'select':
                $options = array();
                foreach($v["options"] as $kk => $vv){
                    $options[] = array(
                        "value" =>  utf8_encode(trim($vv)),
                        "label" =>  utf8_encode(trim($vv))
                    );
                }

                $data = array('values' => $options);

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'select',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  utf8_encode(estr(38)),
                  'validate'      =>  $required,
                  'data'          =>  $data,
                  'widget'        =>  'html5',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'file':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'file',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'checkbox':

                $values = array();
                foreach($v["options"] as $kk => $vv){
                    $values[] = array(
                        "value" =>  utf8_encode(trim($vv)),
                        "label" =>  utf8_encode(trim($vv))
                    );
                }

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'selectboxes',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  $values,
                  'inline'        =>  true,
                  'hideLabel'     =>  ''
                );
            break;
            /*
            case 'radio':

                $values = array();
                foreach($v["options"] as $kk => $vv){
                    $values[] = array(
                        "value" =>  utf8_encode($vv),
                        "label" =>  utf8_encode($vv)
                    );
                }

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field content-radio multiple',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'selectboxes',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  $values,
                  'inline'        =>  true,
                  'hideLabel'     =>  ''
                );
            break;
            */
            case 'uni_checkbox':

                $values[] = array(
                    "value" =>  utf8_encode($v["label"]),
                    "label" =>  utf8_encode($v["label"])
                );

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'selectboxes',
                  'label'         =>  '',
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  $values,
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'number':

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'number',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  ''
                );
            break;

            case 'radio':

                $arr_newForm[] = array(
                  'customClass'   =>  'form-field title-form',
                  'type'          =>  'content',
                  'input'         =>  false,
                  'html'          =>  utf8_encode($v["label"]),
                );

                $questions = array();
                foreach($v["options"] as $kk => $vv){
                    $questions[] = array(
                        "value" =>  utf8_encode($vv),
                        "label" =>  utf8_encode($vv)
                    );
                }

                $values = array();
                $x = 1;
                while($x <= 5){
                    $values[] = array(
                        "label" =>  utf8_encode($x),
                        "value" =>  utf8_encode($x)
                    );
                    $x++;
                }
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'survey',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  '',
                  'widget'        =>  '',
                  'values'        =>  $values,
                  'inline'        =>  '',
                  'hideLabel'     =>  true,
                  'questions'     =>  $questions
                );
            break;

            case 'hidden':
                $arr_newForm[] = array(
                  'customClass'   =>  'form-field',
                  'labelPosition' =>  $labelPosition,
                  'labelWidth'    =>  50,
                  'labelMargin'   =>  0,
                  'type'          =>  'hidden',
                  'label'         =>  utf8_encode($v["label"]),
                  'key'           =>  $v["form_field"],
                  'placeholder'   =>  '',
                  'validate'      =>  $required,
                  'data'          =>  utf8_encode($v["default_value"]),
                  'widget'        =>  '',
                  'values'        =>  '',
                  'inline'        =>  '',
                  'hideLabel'     =>  '',
                  'questions'     =>  '',
                  'value'         =>  utf8_encode($v["default_value"])
                );

            break;
        }
    }


    return json_encode($arr_newForm, TRUE);

}
?>
