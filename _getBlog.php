<?
function _getBlog($page_id=0, $blog_id=0, $term='', $cat_id=0){
    
    if ($page_id==0){
       $page_id = (int)params('page_id');
       $blog = (int)params('blog_id');
       $cat_id = (int)params('cat');
       $term = params('term');
    }
    
    
    $arr = array();
    
    if($page_id>0){
        $arr['page'] = call_api_func('pageOBJ',92,92);
        $arr['page']['cat'] = $cat_id;
        
        $arr['selected_page'] = call_api_func('pageOBJ', $page_id, $page_id);
        $arr['selected_page']['cat'] = $cat_id;
        
        $caminho = call_api_func('get_breadcrumb', $page_id);
        $arr['selected_page']['breadcrumb'] = $caminho;
        
        $arr['featured_articles'] = call_api_func('get_articles_featured', $term);
        $arr['menu'] = call_api_func('get_menu_blog',$page_id, $cat_id);
        
        $tags_safe = array();
        $arr['tags'] = get_tags($blog_id, $tags_safe);
        $arr['tags_safe'] = $tags_safe;
        $arr['shop'] = call_api_func('OBJ_shop_mini');        
        $arr['expressions'] = call_api_func('getExpressions',92);
    }
    
    
    if($blog_id>0){
        $arr['article'] = call_api_func('get_article', $blog_id);
    }else{
        $arr['articles'] = call_api_func('get_all_article', $term, $cat_id);
    }
    

    return serialize($arr);
    
}

function get_tags($blog_id=0, &$tags_safe ){
    global $LG;
 
    
    $arr_tags = array();
    
    $mais_where='';
    if($blog_id>0)  $mais_where=' AND id="'.$blog_id.'"';

    $q = cms_query("SELECT * FROM `blog` WHERE `dodia`<=CURDATE() AND `aodia`>=CURDATE() AND nome$LG!='' $mais_where ");
    while($r = cms_fetch_assoc($q)){
        $temp = array();        
        
        if(trim($r["descritor".$LG])!=""){
            $r["descritor".$LG] = str_replace(', ', ',', $r["descritor".$LG]);
            $tags = preg_split("/[;,]/",$r["descritor".$LG]);
            foreach($tags as $r => $v){
                
                $v = trim($v);
                
                $v_comp = strtoupper($v);
               
                if(!array_key_exists($v_comp, $arr_tags)) {
                    $arr_tags[$v_comp] = $v;
                    $tags_safe[$v_comp] = clearVariable($v); 
                }
            } 
        }
    }  

    return $arr_tags;
}

function get_menu_blog($id, $cat){
    global $LG;
    
    $arr = array();

    $sql = "SELECT id,nome$LG FROM blog_cat";
    $res = cms_query($sql);
    while($row = cms_fetch_assoc($res)){
        $sel = 0;
        if($row["id"] == $cat) $sel = 1;
        $arr[] = array(
            "title" => $row["nome$LG"],
            "url" => "index.php?id=$id&bcat=".$row["id"],
            "selected" => $sel
        );
    } 
    $arr_menu = array(
        "title" => estr(252),
        "url" => "index.php?id=$id",
        "submenu" => $arr 
    );
    
    return $arr_menu;
}


function get_category_blog($id){
    global $LG;

    $row = call_api_func('get_line_table', 'blog_cat', "id='".$id."'");
    
    return $row['nome'.$LG];
}


function get_article($id){
    global $LG, $idiomas_convertidos;
 

    $arr = array();
    
    cms_query("UPDATE `blog` SET clicks=clicks+1 WHERE id='$id'");
    
    # Definido pro Serafim que o blog deve dar sempre para aceder por URL
    //$q = cms_query("SELECT * FROM `blog` WHERE `dodia`<='$today' AND `aodia`>='$today' AND nome$LG!='' AND id='$id' LIMIT 0,1");
    $r = call_api_func('get_line_table', 'blog', "id='".$id."' AND nome$LG!='' ");
    
    
    setlocale(LC_TIME, $idiomas_convertidos[$LG]."_".strtoupper($idiomas_convertidos[$LG]));
    $data = strftime("%d %B, %Y",strtotime($r['data']));
    $ano  = strftime("%Y",strtotime($r['data']));    
    $mes  = ucfirst(strftime("%B",strtotime($r['data'])));    
    $dia  = strftime("%d",strtotime($r['data']));
    
    $banner = array();
    if((int)$r["banner"]>0){
        $banner = call_api_func("OBJ_banner",$r["banner"]);
    }
    
    $cam = "images/blog".$r['id'].".jpg";
    $img = '';
    if(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$cam)){
        $img = call_api_func("OBJ_image",$r['nome'.$LG],1,$cam, 'blogImageOBJ');
    }else{
        return;
    }
    
    $cat = "";
    if((int)$r['cat']>0){
        $cat = call_api_func("get_category_blog",$r['cat']);
    }
    
    
        
    $ordem = ($r['destaque']*-1)."|||".str_pad((strtotime(date('Y-m-d'))-strtotime($r['data'])), 10, "0", STR_PAD_LEFT)."|||".$r['ordem']."|||".($r['id']*-1);

    $arr = array(
        "id"                =>  $r['id'],
        "date"              =>  $data,
        "year"              =>  $ano,
        "month"             =>  $mes,
        "day"               =>  $dia,
        "date_raw"          =>  $r['data'],
        "title"             =>  $r['nome'.$LG],
        "subtitle"          =>  $r['subtitulo'.$LG],
        "content"           =>  base64_encode($r['bloco'.$LG]),
        "category"          =>  $cat,
        "featured"          =>  $r['destaque'],
        "image"             =>  $img,
        "author"            =>  $r['autor'],
        "author_link"       =>  $r['autor_link'],
        "source"            =>  $r['fonte'],
        "source_link"       =>  $r['fonte_link'],
        "banner"            =>  $banner,
        "comment"           =>  $r["comentario"],
        "ContentBlock"      =>  $r['ContentBlock'],
        "order"             =>  $ordem
    );    
    
    return $arr;
}


function get_articles_featured(){
    global $LG, $idiomas_convertidos;

    $arr = array();

    $q = cms_query("SELECT * FROM `blog` WHERE `dodia`<=CURDATE() AND `aodia`>=CURDATE() AND nome$LG!='' order by clicks desc, destaque desc,data desc,ordem,id desc LIMIT 0,5");
    while($r = cms_fetch_assoc($q)){
        
        setlocale(LC_TIME, $idiomas_convertidos[$LG]."_".strtoupper($idiomas_convertidos[$LG]));
        $data = strftime("%d %B, %Y",strtotime($r['data']));
        $ano  = strftime("%Y",strtotime($r['data']));    
        $mes  = ucfirst(strftime("%B",strtotime($r['data'])));    
        $dia  = strftime("%d",strtotime($r['data']));         
        
        $banner = array();
        if((int)$r["banner"]>0){
            $banner = call_api_func("OBJ_banner",$r["banner"]);
        }
        
        $cam = "images/blog".$r['id'].".jpg";
        $img = '';
        if(file_exists($cam)){
            $img = call_api_func("OBJ_image",$r['nome'.$LG],1,$cam, 'blogImageOBJ');
        }else{
            return;
        }
        
        $cat = "";
        if((int)$r['cat']>0){
            $cat = call_api_func("get_category_blog",$r['cat']);
        }
        
        $arr[] = array(
            "id"                =>  $r['id'],
            "date"              =>  $data,
            "year"              =>  $ano,
            "month"             =>  $mes,
            "day"               =>  $dia,
            "date_raw"          =>  $r['data'],
            "title"             =>  $r['nome'.$LG],
            "subtitle"          =>  $r['subtitulo'.$LG],
            "content"           =>  base64_encode($r['bloco'.$LG]),
            "category"          =>  $cat,
            "featured"          =>  $r['destaque'],
            "image"             =>  $img,
            "author"            =>  $r['autor'],
            "author_link"       =>  $r['autor_link'],
            "source"            =>  $r['fonte'],
            "source_link"       =>  $r['fonte_link'],
            "banner"            =>  $banner,
            "comment"           =>  $r["comentario"]
        );  
    }
    
    return $arr;
}

function get_all_article($term=null, $cat_id=null){
    global $LG, $idiomas_convertidos;


    $arr      = array();
    
    $table    = "";
    $in_terms = "";
    
    if(!is_null($term)){
        
        $term = html_entity_decode($term, ENT_QUOTES);
        # 2022-01-10 - Verifica se é necessário fazer o encode, ou não
        $term2 = utf8_decode($term);
                    
        $validUTF8 =! (false === mb_detect_encoding($term2, 'UTF-8', true));
                            
        if( !$validUTF8 ){
            $term = $term2;
        }
        
        $palavras = explode (" ", $term);
        foreach ($palavras as $kk=>$_word) {
            if ( strlen($_word) < 3 ){
                unset( $palavras[$kk] );
            }
        }
        
    
        $sql = "SELECT * FROM blog_cat WHERE nome$LG LIKE '%".cms_escape(implode('%',$palavras))."%'";
        $res = cms_query($sql);
        while($row = cms_fetch_assoc($res)){
            $ids[] = $row["id"];
        }
        if(count($ids)>0){
            $sql_cat = "or cat in ('".implode(",",$ids)."')";
        }
        $in_terms = "AND (nome$LG LIKE '%".cms_escape(implode('%',$palavras))."%' OR subtitulo$LG LIKE '%".cms_escape(implode('%',$palavras))."%' or descritor$LG LIKE '%".cms_escape(implode('%',$palavras))."%' $sql_cat )";
    }
    
    $in_cat = "";
    if((int)$cat_id>0){
        $in_cat = " AND cat='".$cat_id."'";
    }
    
    #echo "SELECT * FROM blog WHERE dodia<='$today' AND aodia>='$today' AND nome$LG!='' $in_terms order by data desc,ordem asc,id desc ";
    $q = cms_query("SELECT * FROM blog WHERE dodia<=CURDATE() AND aodia>=CURDATE() AND nome$LG!='' $in_cat $in_terms order by destaque DESC, data desc,ordem asc,id desc ");
    while($r = cms_fetch_assoc($q)){
        
        setlocale(LC_TIME, $idiomas_convertidos[$LG]."_".strtoupper($idiomas_convertidos[$LG]));
        $data = strftime("%d %B, %Y",strtotime($r['data']));
        $ano  = strftime("%Y",strtotime($r['data']));    
        $mes  = ucfirst(strftime("%B",strtotime($r['data'])));    
        $dia  = strftime("%d",strtotime($r['data']));  
        
        $banner = array();
        if((int)$r["banner"]>0){
            $banner = call_api_func("OBJ_banner",$r["banner"]);
        }
        
        $cam = "images/blog".$r['id'].".jpg";
        $img = '';
        if(file_exists($cam)){
            $img = call_api_func("OBJ_image",$r['nome'.$LG],1,$cam, 'blogImageOBJ');
        }else{
            continue;
        }
        
        $cat = "";
        if((int)$r['cat']>0){
            $cat = call_api_func("get_category_blog",$r['cat']);
        }
        
            
        $arr[] = array(
            "id"            =>  $r['id'],
            "date"          =>  $data,
            "year"          =>  $ano,
            "month"         =>  $mes,
            "day"           =>  $dia,
            "date_raw"      =>  $r['data'],
            "title"         =>  $r['nome'.$LG],
            "subtitle"      =>  $r['subtitulo'.$LG],
            "content"       =>  base64_encode($r['bloco'.$LG]),
            "category"      =>  $cat,
            "featured"      =>  $r['destaque'],
            "image"         =>  $img,
            "author"        =>  $r['autor'],
            "author_link"   =>  $r['autor_link'],
            "source"        =>  $r['fonte'],
            "source_link"   =>  $r['fonte_link'],
            "banner"        =>  $banner,
            "comment"       =>  $r["comentario"]
        );       
    }

    return $arr;
} 

?>
