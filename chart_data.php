<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: GET,POST");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json;charset=utf-8');

use \Psr\Http\Message\ResponseInterface as Response; // ไลบราลี้สำหรับจัดการคำร้องขอ
use \Psr\Http\Message\ServerRequestInterface as Request; // ไลบราลี้สำหรับจัดการคำตอบกลับ

require './vendor/autoload.php'; // ดึงไฟ์ autoload.php เข้ามา

$app = new \Slim\App; // สร้าง object หลักของระบบ

$app->post('/chart1', function (Request $request, Response $response) {

	//$ou = $_REQUEST['ou'];
	require './connect_demo.php';

	$resultArray = array();

	$sql = "SELECT OU_CODE, BATCH_NO, TO_CHAR(BD.DYE_EDATE,'YYYY-MM-DD HH24:MI') DYE_EDATE_TXT
,TO_CHAR(TO_DATE(TO_CHAR(BD.DYE_EDATE,'YYYY-MM-DD HH24'),'YYYY-MM-DD HH24:MI'),'YYYY-MM-DD HH24:MI') DYE_EDATE_HOUR
,BD.KNITTING_TYPE_NAME, TOTAL_ROLL, TOTAL_QTY, MC_DYE, EFF_FABRIC, PRODUCT_TYPE, METERIAL_GROUP,
(SELECT TO_CHAR(BT.END_DATE,'DD-MM:HH24')  AS QCD_END_DATE FROM DFBT_MONITOR BT WHERE BT.BATCH_NO = BD.BATCH_NO AND BT.OU_CODE = BD.OU_CODE AND STEP_NO = 'QCD') QCD_END_DATE
,(SELECT MPS_MAX_QTY FROM DFMS_MACHINE MC WHERE MC.MACHINE_NO = BD.MC_DYE) MPS_MAX_QTY
,ROUND((NVL((SELECT BT.END_DATE AS QCD_END_DATE FROM DFBT_MONITOR BT WHERE BT.BATCH_NO = BD.BATCH_NO AND BT.OU_CODE = BD.OU_CODE AND STEP_NO = 'QCD'),SYSDATE) - BD.DYE_EDATE) * (24),4)*100 AS AGING
FROM DFIT_BTDATA BD
WHERE mc_dye <> 'DL30011'
AND OU_CODE = 'D03'
AND BD.DYE_EDATE >= TO_DATE('2019/01/01 08:00','YYYY/MM/DD HH24:MI')
AND BD.DYE_EDATE < TO_DATE('2020/01/01 08:00','YYYY/MM/DD HH24:MI')";



	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	

	while ($dr = oci_fetch_assoc($query)) {

		 $row = array();
		 array_push($row, $dr["METERIAL_GROUP"]);
		 array_push($row, $dr["MPS_MAX_QTY"]);
		 array_push($row, intVal($dr["TOTAL_QTY"]));
		 array_push($resultArray, $row);

		 // $row = array();
		 // array_push($row, $dr["MPS_MAX_QTY"]);
		 // array_push($row, $dr["MC_DYE"]);
		 // array_push($row, intVal($dr["TOTAL_QTY"]));
		 // array_push($resultArray, $row);

		 // $row = array();
		 // array_push($row, $dr["MC_DYE"]);
		 // array_push($row, $dr["QCD_END_DATE"]);
		 // array_push($row, intVal($dr["TOTAL_QTY"]));
		 // array_push($resultArray, $row);

	}


	$sql = "SELECT OU_CODE, BATCH_NO, TO_CHAR(BD.DYE_EDATE,'YYYY-MM-DD HH24:MI') DYE_EDATE_TXT
,TO_CHAR(TO_DATE(TO_CHAR(BD.DYE_EDATE,'YYYY-MM-DD HH24'),'YYYY-MM-DD HH24:MI'),'YYYY-MM-DD HH24:MI') DYE_EDATE_HOUR
,BD.KNITTING_TYPE_NAME, TOTAL_ROLL, TOTAL_QTY, MC_DYE, EFF_FABRIC, PRODUCT_TYPE, REPLACE(METERIAL_GROUP,' ','/') METERIAL_GROUP,
(SELECT TO_CHAR(BT.END_DATE,'DD-MM:HH24')  AS QCD_END_DATE FROM DFBT_MONITOR BT WHERE BT.BATCH_NO = BD.BATCH_NO AND BT.OU_CODE = BD.OU_CODE AND STEP_NO = 'QCD') QCD_END_DATE
,(SELECT MPS_MAX_QTY FROM DFMS_MACHINE MC WHERE MC.MACHINE_NO = BD.MC_DYE) MPS_MAX_QTY
,ROUND((NVL((SELECT BT.END_DATE AS QCD_END_DATE FROM DFBT_MONITOR BT WHERE BT.BATCH_NO = BD.BATCH_NO AND BT.OU_CODE = BD.OU_CODE AND STEP_NO = 'QCD'),SYSDATE) - BD.DYE_EDATE) * (24),4)*100 AS AGING
FROM DFIT_BTDATA BD
WHERE mc_dye <> 'DL30011'
AND OU_CODE = 'D03'
AND BD.DYE_EDATE >= TO_DATE('2019/01/01 08:00','YYYY/MM/DD HH24:MI')
AND BD.DYE_EDATE < TO_DATE('2020/01/01 08:00','YYYY/MM/DD HH24:MI')";



	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	

	while ($dr = oci_fetch_assoc($query)) {

		 // $row = array();
		 // array_push($row, $dr["METERIAL_GROUP"]);
		 // array_push($row, $dr["MPS_MAX_QTY"]);
		 // array_push($row, intVal($dr["TOTAL_QTY"]));
		 // array_push($resultArray, $row);

		 $row = array();
		 array_push($row, $dr["MPS_MAX_QTY"]);
		 array_push($row, $dr["MC_DYE"]);
		 array_push($row, intVal($dr["TOTAL_QTY"]));
		 array_push($resultArray, $row);

		 // $row = array();
		 // array_push($row, $dr["MC_DYE"]);
		 // array_push($row, $dr["QCD_END_DATE"]);
		 // array_push($row, intVal($dr["TOTAL_QTY"]));
		 // array_push($resultArray, $row);

	}



	oci_close($conn_omnoi);


	$file = fopen("contacts.csv","w");

	foreach ($resultArray as $line) {
	  fputcsv($file, $line);
	}

	fclose($file);

	//$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	//return $response; // ส่งคำตอบกลับ
});

$app->run(); // สั่งให้ระบบทำงาน

?>