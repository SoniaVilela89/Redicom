<?

function _getAccountMenu()
{
    global $LG, $CACHE_KEY, $fx;

    $arr = array();
    $_Menucacheid = $CACHE_KEY."ACCOUNTMENU".$LG;
              
    $dados = $fx->_GetCache($_Menucacheid);
    if ($dados!=false && !isset($_GET['nocache'])){
        $arr = unserialize($dados);                   
    }else{

        $arr_pages = array();
        $sql_page = "SELECT id, nome$LG as name FROM ec_rubricas 
                            WHERE id IN (11, 10, 12, 13, 51, 56, 43, 50, 68) AND hidden='0' AND hidemenu='0' AND nome$LG!='' 
                            ORDER BY FIELD(id,11, 10, 12, 13, 51, 56, 43, 50, 68)";
                            
        $res_page = cms_query($sql_page);
        
        while ($row_page = cms_fetch_assoc($res_page)) {
            $arr_pages[] = $row_page;
        }
        $arr['account_pages'] =  $arr_pages;
        
        $fx->_SetCache($_Menucacheid, serialize($arr), 1440);
    }
    return serialize($arr);
}
