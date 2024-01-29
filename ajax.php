<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require 'connect.php';
require realpath(dirname(__FILE__)) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as excelObject;

$GLOBALS['token']='';

global $mysqli;

$fp = fopen('errors.txt', 'a');

if ((isset($_POST['type'])) && ($_POST['type']=="getrep")) {
	$arr = ['query_id' => $_POST['query_id'], 'user' => urldecode($_POST['user']), 'hash' => $_POST['hash'], 'auth_date' => $_POST['authdate']];
	$check_hash = (string)$arr['hash'];
	unset($arr['hash']);
	foreach($arr as $k => $v) $check_arr[]=$k.'='.$v;
	@sort($check_arr);
	$string = @implode("\n", $check_arr);
	$secret_key = hex2bin(hash_hmac('sha256', $token, "WebAppData"));
	$hash = hash_hmac('sha256', $string, $secret_key);
	if (strcmp($hash, $check_hash) != 0) {
		fwrite($fp, date("Y-m-d H:i:s").' Error. Hash incorrect!'.PHP_EOL);
	} else {
		fwrite($fp, date("Y-m-d H:i:s").' Hash correct!'.PHP_EOL);
		$user = $_POST['userid'];
		$dateFrom = $_POST['dateFrom'] ? " WHERE date >= '".$_POST['dateFrom']."'" : "";
		$dateTo = $_POST['dateTo'] ? " and date <= '".$_POST['dateTo']."'" : "";
		if (($dateFrom) || ($dateTo)) {
			$where = ' WHERE ';
		}
		$query = "SELECT * FROM sevas_report ".$dateFrom.$dateTo;
		fwrite($fp, date("Y-m-d H:i:s").' query: '.$query.PHP_EOL);
		$result = $mysqli->query($query);
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('AlertBot');
		$sheet->setCellValue('A1', 'Номер');
		$sheet->setCellValue('B1', 'Чат Id');
		$sheet->setCellValue('C1', 'Логин');
		$sheet->setCellValue('D1', 'Телефон');
		$sheet->setCellValue('E1', 'Имя');
		$sheet->setCellValue('F1', 'Местоположение');
		$sheet->setCellValue('G1', 'Описание');
		$sheet->setCellValue('H1', 'Комментарий');
		$sheet->setCellValue('I1', 'Дата');
		$row_cnt = $result->num_rows;
		if ($row_cnt>0) {
			$i=2;
			while ($row = $result->fetch_assoc()) {
				$sheet->setCellValue('A'.$i, $row['id']);
				$sheet->setCellValue('B'.$i, $row['chatId']);
				$sheet->setCellValue('C'.$i, $row['userName']);
				$sheet->setCellValue('D'.$i, $row['telephone']);
				$sheet->setCellValue('E'.$i, $row['firstName']);
				if (strpos('yandex', $row['location']) >0 ) {
					$sheet->setCellValue('F'.$i, $row['location']);
					$spreadsheet->getActiveSheet()->getCell('F'.$i)->getHyperlink()->setUrl(($row['location']));
				} else {
					$sheet->setCellValue('F'.$i, $row['location']);
				}
				$sheet->setCellValue('G'.$i, $row['description']);
				$sheet->setCellValue('H'.$i, $row['file']);
				$sheet->setCellValue('I'.$i, $row['date']);
				$i=$i+1; 
			}
		}
		$sheet-> getColumnDimension('A')->setAutoSize(true);
		$sheet-> getColumnDimension('B')->setAutoSize(true);
		$sheet-> getColumnDimension('C')->setAutoSize(true);
		$sheet-> getColumnDimension('D')->setAutoSize(true);
		$sheet-> getColumnDimension('F')->setAutoSize(true);
		$sheet-> getColumnDimension('G')->setAutoSize(true);
		$sheet-> getColumnDimension('H')->setAutoSize(true);
		$sheet-> getColumnDimension('I')->setAutoSize(true);
		$sheet->getStyle('A'.(1).':I'.($i-1))->applyFromArray([
			'font' => [
				'name' => 'Times New Roman',
				'italic' => false
			],
			'borders' => [
				'allBorders' => [
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
				],
			],
			'alignment' => [
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
				'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
				'wrapText' => true,
			]
		]);
		$writer = new excelObject($spreadsheet);
		$nazv='files/report-'.date('m-d-Y-H-i-s').'.xlsx';
		$writer->save($nazv); 
		$bot = new \TelegramBot\Api\Client($GLOBALS['token']);
		$document = new \CURLFile($nazv);
		if ($bot->sendDocument($user, $document)) echo "correct";
		unlink($nazv);
	}
}
fclose($fp);
?>