<?
function _setGridView($view=0, $mobile=0){
   
    $view = (int)params('view');  
    $mobile = (int)params('mobile');    
    
    if((int)$mobile == 1)
        $_SESSION['GridViewMobile'] = $view;
    else
    $_SESSION['GridView'] = $view;
    
    $arr = array();
    $arr[] = 1;
    return serialize($arr);
}
?>
