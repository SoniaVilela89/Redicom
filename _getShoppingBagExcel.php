<?

function _getShoppingBagExcel($catalogID = null, $unitStore=0)
{

    global $userID, $LG;
    
    

    if (is_null($catalogID)) {
        $catalogID = (int)params('catalog_id');
        $unitStore = (int)params('unit_store');
    }

    $client        = call_api_func("get_line_table", "_tusers", "id='" . $userID . "'");
    $clientCountry = call_api_func("get_line_table", "ec_paises", "id='" . $client["pais"] . "'");

    /*$language = 0;
    if ((int)$clientCountry["idioma"] > 0) {
        $language = $clientCountry['idioma'];
    }

    $idioma = call_api_func("get_line_table", "ec_language", "id='" . $language . "'");

    if ($idioma['code'] == "es") $idioma['code'] = "sp";
    if ($idioma['code'] == "en") $idioma['code'] = "gb";

    $LG = $idioma['code'];*/
    

        
    $moreSql = '';
    if($unitStore>0){
        $moreSql = " AND `ec_enc_l`.`col1`='".$unitStore."' ";
    }
        

    $sql = "SELECT  `registos`.`nome" . $LG . "` as `prod_nome`,
                    `registos`.`desc" . $LG . "` as `prod_desc`,
                    `registos`.`ean` as `prod_ean`,
                    `ec_enc_l`.*,
                    SUM(`ec_enc_l`.`qnt`) as `qnt_total`,
                    /*SUM(((`ec_enc_l`.valoruni-`ec_enc_l`.`valoruni_desconto`)/`ec_enc_l`.`taxa_cambio`)*`ec_enc_l`.`qnt`) as `valor_final`*/
                    SUM((`ec_enc_l`.valoruni/`ec_enc_l`.`taxa_cambio`)*`ec_enc_l`.`qnt`) as `valor_final`
            FROM `ec_encomendas_lines` as `ec_enc_l`
            LEFT JOIN `registos` ON `registos`.`id`=`ec_enc_l`.`pid`
            WHERE `ec_enc_l`.id_cliente='" . $userID . "' AND `ec_enc_l`.status='0' AND `ec_enc_l`.id_linha_orig<1 AND `ec_enc_l`.page_cat_id='" . $catalogID . "'" . $moreSql . "
            GROUP BY `ec_enc_l`.`pid`
            ORDER BY `ec_enc_l`.sku_family ASC, `ec_enc_l`.cor_name ASC, `ec_enc_l`.ref ASC"; #Esta ordenação tem de bater certo com a ordenação do carrinho

    $res = cms_query($sql);
    $i   = 1;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/Classes/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/Classes/PHPExcel/IOFactory.php';

    $objPHPExcel = new PHPExcel();

    $objPHPExcel->getDefaultStyle()
        ->getFont()
        ->setName('Arial')
        ->setSize(10);

    $ACTIVE_SHEET = $objPHPExcel->setActiveSheetIndex(0);

    #################################################################
    ######################## FOLHA 1 ################################
    #################################################################

    $ACTIVE_SHEET->getStyle('A1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('B1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('C1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('D1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('E1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('F1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('G1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('H1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('I1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('J1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('K1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('L1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('M1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('N1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('O1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas
    $ACTIVE_SHEET->getStyle('P1')->getFont()->setSize(11)->setBold(true); #Definir estilos nas celulas

    $ACTIVE_SHEET->setCellValue('A1', utf8_encode(estr2(897))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('B1', utf8_encode(estr2(898))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('C1', utf8_encode(utf8_encode(estr(25)))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('D1', utf8_encode("EAN")); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('E1', utf8_encode(estr(66))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('F1', utf8_encode(estr(505))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('G1', utf8_encode(estr(489))); #colocar um valor numa celula
    $ACTIVE_SHEET->mergeCells('G1:I1'); #unir celulas
    $ACTIVE_SHEET->setCellValue('J1', utf8_encode(estr(8))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('K1', utf8_encode(estr2(896))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('L1', utf8_encode(estr(432))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('M1', utf8_encode(estr2(131))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('N1', utf8_encode(estr(660))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('O1', utf8_encode(estr(415))); #colocar um valor numa celula
    $ACTIVE_SHEET->setCellValue('P1', utf8_encode(estr(471))); #colocar um valor numa celula

    $ACTIVE_SHEET->getRowDimension(1)->setRowHeight(20); #aumentar tamanho das linhas

    while ($v = cms_fetch_assoc($res)) {
        $comp = explode(' - ', $v['composition']);
        $v['composition'] = $comp[0];
        
        if( $v['pack'] == 1 ){
            $v['prod_nome'] = $v['nome'];
            $v['prod_desc'] = $v['composition'];
        }
        
        
        
        $i++;

        $ACTIVE_SHEET->setCellValue('A' . $i, utf8_encode($v['sku_family']));
        $ACTIVE_SHEET->setCellValue('B' . $i, utf8_encode($v['sku_group']));
        $ACTIVE_SHEET->setCellValue('C' . $i, utf8_encode($v['ref']));
        $ACTIVE_SHEET->setCellValue('D' . $i, utf8_encode($v['prod_ean']));
        $ACTIVE_SHEET->setCellValue('E' . $i, utf8_encode($v['prod_nome']));
        $ACTIVE_SHEET->setCellValue('F' . $i, utf8_encode($v['prod_desc']));
        $ACTIVE_SHEET->setCellValue('G' . $i, utf8_encode($v['cor_id']));
        $ACTIVE_SHEET->setCellValue('H' . $i, utf8_encode($v['cor_cod']));
        $ACTIVE_SHEET->setCellValue('I' . $i, utf8_encode($v['cor_name']));
        $ACTIVE_SHEET->setCellValue('J' . $i, utf8_encode($v['tamanho']." ".$comp[1]));
        $ACTIVE_SHEET->setCellValue('K' . $i, utf8_encode($v['page_cat_id']));

        if ($v['valoruni_anterior'] > 0) {
            $ACTIVE_SHEET->setCellValue('L' . $i, $v['valoruni_anterior']);
            if( $v['valoruni_desconto'] == 0 ){
                $v['valoruni_desconto'] = $v['valoruni_anterior'] - $v['valoruni'];
            }
        } else {
            $ACTIVE_SHEET->setCellValue('L' . $i, $v['valoruni']);
        }

        $ACTIVE_SHEET->setCellValue('M' . $i, $v['valoruni_desconto']);
        $ACTIVE_SHEET->setCellValue('N' . $i, $v['valoruni']);
        $ACTIVE_SHEET->setCellValue('O' . $i, utf8_encode($v['qnt_total']));
        $ACTIVE_SHEET->setCellValue('P' . $i, $v['valor_final']);
    }

    $ACTIVE_SHEET->getColumnDimension("A")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("B")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("C")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("D")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("E")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("F")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("G")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("H")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("I")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("J")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("K")->setAutoSize(true);
    $ACTIVE_SHEET->getColumnDimension("L")->setAutoSize(true);

    $objPHPExcel->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment;filename=export.xls");
    header('Cache-Control: max-age=0');

    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    ob_end_clean();
    $objWriter->save('php://output');
    exit;
}
