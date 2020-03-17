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

$app->post('/summary', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'AFTER DYE' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}

	$sql = "SELECT COUNT(BD.BATCH_NO) TOTAL
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $alert THEN 1 ELSE 0 END) STS_ALERT
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) < $alert THEN 1 ELSE 0 END) STS_WARNING
              ,SUM(BD.TOTAL_QTY) TOTAL_KGS
              ,SUM(BD.TOTAL_ROLL) TOTAL_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $alert THEN BD.TOTAL_ROLL ELSE 0 END) ALERT_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) < $alert THEN BD.TOTAL_ROLL ELSE 0 END) WARNING_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $alert THEN BD.TOTAL_QTY ELSE 0 END) ALERT_KGS
              ,SUM(CASE WHEN ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) < $alert THEN BD.TOTAL_QTY ELSE 0 END) WARNING_KGS
              FROM DFIT_BTDATA BD, DFBT_HEADER BH
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BH.STATUS NOT IN (8,9)
              AND BD.DYE_EDATE IS NOT NULL
              AND BD.DYE_EDATE > (TRUNC(SYSDATE) - 30)
              AND EXISTS (SELECT D.*
              FROM (
              SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP , SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END) AS START_STEP
              FROM DFBT_MONITOR M
              WHERE OU_CODE = '$ou'
              AND METHOD_CONT = 4
              GROUP BY M.OU_CODE, M.BATCH_NO
              HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END)) = COUNT(METHOD_CONT)) D
              WHERE BD.OU_CODE = D.OU_CODE AND BD.BATCH_NO = D.BATCH_NO ) ";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$data = new stdClass();
	$data->total = 0;
	$data->warning = 0;
	$data->alert = 0;
	$data->total_kg = 0;
	$data->warning_kg = 0;
	$data->alert_kg = 0;
	$data->total_roll = 0;
	$data->warning_roll = 0;
	$data->alert_roll = 0;
	$data->warningtime = $warning;
	$data->alerttime = $alert;

	while ($dr = oci_fetch_assoc($query)) {
		$data->total = intval($dr["TOTAL"]);
		$data->warning = intval($dr["STS_WARNING"]);
		$data->alert = intval($dr["STS_ALERT"]);
		$data->total_kg = doubleval($dr["TOTAL_KGS"]);
		$data->warning_kg = doubleval($dr["WARNING_KGS"]);
		$data->alert_kg = doubleval($dr["ALERT_KGS"]);
		$data->total_roll = doubleval($dr["TOTAL_ROLLS"]);
		$data->warning_roll = doubleval($dr["WARNING_ROLLS"]);
		$data->alert_roll = doubleval($dr["ALERT_ROLLS"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($data)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});



$app->post('/batch_status', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$batch = $_REQUEST['batch'];

	require './connect_demo.php';

$obj = new stdClass();

$sql = "SELECT M.*
FROM (
SELECT BH.METHOD_CODE, MT.METHOD_NAME,  BH.STEP_NO, ST.STEP_NAME, BH.MACHINE_NO, RANK() OVER( ORDER BY BH.METHOD_CONT, BH.SEQ_NO)  STEP_RANK
FROM  DFBT_MONITOR BH, DFMS_STEP ST, DFMS_METHOD MT
WHERE OU_CODE = '$ou'
AND BATCH_NO = '$batch'
AND BH.STEP_NO = ST.STEP_NO(+)
AND BH.METHOD_CODE = MT.METHOD_CODE
AND END_DATE IS NULL
ORDER BY BH.METHOD_CONT, BH.SEQ_NO) M
WHERE STEP_RANK = 1";

$query = oci_parse($conn_omnoi, $sql);

oci_execute($query);


while ($dr = oci_fetch_assoc($query)) {
	$obj->step1 = new stdClass();
	$obj->step1->method_code = $dr["METHOD_CODE"];
	$obj->step1->method_name = $dr["METHOD_NAME"];
	$obj->step1->step_no = $dr["STEP_NO"];
	$obj->step1->step_name = $dr["STEP_NAME"];
	$obj->step1->mcno = $dr["MACHINE_NO"];
}


$sql = "SELECT M.*
FROM (
SELECT BH.METHOD_CODE, MT.METHOD_NAME,  BH.STEP_NO, ST.STEP_NAME, BH.MACHINE_NO, RANK() OVER( ORDER BY BH.METHOD_CONT, BH.SEQ_NO)  STEP_RANK
FROM  DFBT_MONITOR BH, DFMS_STEP ST, DFMS_METHOD MT
WHERE OU_CODE = '$ou'
AND BATCH_NO = '$batch'
AND BH.STEP_NO = ST.STEP_NO(+)
AND BH.METHOD_CODE = MT.METHOD_CODE
AND END_DATE IS NULL
ORDER BY BH.METHOD_CONT, BH.SEQ_NO) M
WHERE STEP_RANK = 2";

$query2 = oci_parse($conn_omnoi, $sql);

oci_execute($query2);

$obj->step2 = new stdClass();

while ($dr = oci_fetch_assoc($query2)) {

	$obj->step2->method_code = $dr["METHOD_CODE"];
	$obj->step2->method_name = $dr["METHOD_NAME"];
	$obj->step2->step_no = $dr["STEP_NO"];
	$obj->step2->step_name = $dr["STEP_NAME"];
	$obj->step2->mcno = $dr["MACHINE_NO"];
}



oci_close($conn_omnoi);

$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

return $response; // ส่งคำตอบกลับ
});


$app->post('/wip', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'AFTER DYE' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}

	$sql = "SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY, BD.DYE_EDATE
              ,ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) AS TIME_AGING
							,FLOOR(MOD((SYSDATE - BD.DYE_EDATE) * (24),0)) || ':' || LPAD(CEIL(MOD((SYSDATE - BD.DYE_EDATE) * (24),1)*60),2,'0') AS TIMESPAN
              ,TO_CHAR( bd.dye_edate,'DD-MM-YYYY HH24:MI') END_DYE_DATE
							,BD.SO_NO, BD.CUSTOMER_NAME
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
              FROM DFIT_BTDATA BD, DFBT_HEADER BH
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BH.STATUS NOT IN (8,9)
              AND BD.DYE_EDATE IS NOT NULL
              AND BD.DYE_EDATE > (TRUNC(SYSDATE) - 30)
              AND EXISTS (SELECT D.*
              FROM (
              SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP , SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END) AS START_STEP
              FROM DFBT_MONITOR M
              WHERE OU_CODE = '$ou'
              AND METHOD_CONT = 4
              GROUP BY M.OU_CODE, M.BATCH_NO
              HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END)) = COUNT(METHOD_CONT)) D
              WHERE BD.OU_CODE = D.OU_CODE AND BD.BATCH_NO = D.BATCH_NO
              )
              AND ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= $warning
              ORDER BY BD.DYE_EDATE";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/view_message', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$batch = $_REQUEST['batch'];
	$keyid = $_REQUEST['keyid'];
	require './connect_demo.php';

$sql = "SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY, BD.DYE_EDATE
              ,ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) AS TIME_AGING
              , TO_CHAR( bd.dye_edate,'DD-MM-YYYY HH24:MI') END_DYE_DATE, CT.SEND_MSG, CT.RESPONSE_MSG, CT.KEY_ID
              FROM DFIT_BTDATA BD, DFBT_HEADER BH, CONTROL_MSG_ALERT CT
              WHERE 1 = 1
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BH.STATUS NOT IN (8,9)
              AND BD.DYE_EDATE IS NOT NULL
              AND BD.OU_CODE = CT.OU_CODE
              AND BD.BATCH_NO = CT.BATCH_NO
              AND CT.KEY_ID = '$keyid'
              AND BD.OU_CODE = '$ou'
              AND BD.BATCH_NO = '$batch'";

							$query = oci_parse($conn_omnoi, $sql);

							oci_execute($query);

							$resultArray = array();

							while ($dr = oci_fetch_assoc($query)) {
								array_push($resultArray, $dr);
							}


	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
	});


$app->run(); // สั่งให้ระบบทำงาน

?>
