<?

function _getFaqsSearch($page_id=0){
  global $LG;
  $term = $_POST['term'];
  $sql = cms_query("SELECT id, nome$LG as name FROM _tfaqs WHERE (nome$LG LIKE '%$term%' OR desc$LG LIKE '%$term%' OR subtitulo$LG LIKE '%$term%') AND cat in(1)");  
   

  while ($row = cms_fetch_assoc($sql)) {
    $aux[] = array(
  		"titulo" => $row['name'],
  		"link" => $row['id']
    	);	
  }

  #echo json_encode($aux);
  return serialize($aux);
  
}
?>
