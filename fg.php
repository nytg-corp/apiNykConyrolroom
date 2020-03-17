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

	$ou = $_REQUEST['ou'];
	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'FG' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"])/ 60;
		$alert = intval($dr["ALERT_MINUTE"])/ 60;
	}


	$sql = "SELECT COUNT(BD.BATCH_NO) TOTAL
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $alert THEN 1 ELSE 0 END) STS_ALERT
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.END_DATE) * (24),2) < $alert THEN 1 ELSE 0 END) STS_WARNING
		,SUM(BD.TOTAL_QTY) TOTAL_KGS
		,SUM(BD.TOTAL_ROLL) TOTAL_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $alert THEN BD.TOTAL_ROLL ELSE 0 END) ALERT_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.END_DATE) * (24),2) < $alert THEN BD.TOTAL_ROLL ELSE 0 END) WARNING_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $alert THEN BD.TOTAL_QTY ELSE 0 END) ALERT_KGS
		,SUM(CASE WHEN ROUND((SYSDATE - BD.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - BD.END_DATE) * (24),2) < $alert THEN BD.TOTAL_QTY ELSE 0 END) WARNING_KGS
FROM (
SELECT RO.END_DATE, TIMESTAMP_DIFF(RO.END_DATE,SYSDATE) AS	TIMESPAN
, BD.*
FROM DFIT_BTDATA BD, DFBT_HEADER BH,
(
SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP , SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END) AS END_STEP,
MAX(M.END_DATE) END_DATE
              FROM DFBT_MONITOR M, DFMS_STEP ST 
              WHERE OU_CODE = '$ou'
               AND ST.STEP_NO = M.STEP_NO
              AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS =2)
              GROUP BY M.OU_CODE, M.BATCH_NO
              HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END)) = 0) RO
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BD.DYE_EDATE IS NOT NULL
              AND BD.OU_CODE = RO.OU_CODE
               AND BD.BATCH_NO = RO.BATCH_NO) BD";

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



$app->post('/getBatchsAll', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url
	$ou = $_REQUEST['ou'];
	$page = intval($_REQUEST['page']);
	$stepno = $_REQUEST['stepno'];

	$grpStep = '';

	$filter_step = "";
	if($stepno=='ALL' || $stepno=='undefined' || $stepno == '' ){
		$filter_step = ' ';
	}else{
		$filter_step = "AND EXISTS ( SELECT BH1.*
                        FROM  DFBT_MONITOR BH1, DFMS_STEP ST
                        WHERE BH1.OU_CODE = '$ou'
                        AND ST.GROUP_STEP = '05-Finishing' 
                        AND ST.STEP_NO = BH1.STEP_NO 
                        AND BH1.STEP_NO = '$stepno'
                        AND BH1.ACTIVE_FLAG = 'Y'
                        AND BH1.OU_CODE = BD.OU_CODE
                        AND BH1.BATCH_NO = BD.BATCH_NO )";
	}


	$max3 = ($page*30);
	$max2 = ($page*30)-10;
	$max1 = ($page*30)-20;
	$min = ($page*30)-30;
		

	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'FG' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}

// $sql = "SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC,
// BD.TOTAL_ROLL, BD.TOTAL_QTY , TO_CHAR(RO.END_DATE,'DD/MM/YYYY HH24:MI') DYE_EDATE  ,ROUND((SYSDATE -
// RO.END_DATE) * (24),2) AS TIME_AGING ,timestamp_diff(RO.END_DATE, sysdate) as TIMESPAN  ,TO_CHAR(
// RO.END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE ,BD.SO_NO, BD.CUSTOMER_NAME ,CASE WHEN ((SYSDATE
// - RO.END_DATE) * (24)) > 16 then 'red'  WHEN ((SYSDATE - RO.END_DATE) * (24)) > 8 then 'yellow'  ELSE
// 'light-green-13' END ICON_COLOR  ,CASE WHEN ((SYSDATE - RO.END_DATE) * (24)) > 16 then 'warning'  WHEN
// ((SYSDATE - RO.END_DATE) * (24)) > 8 then 'notification_important'  ELSE 'flag' END ICON_NAME  ,8 as
// WARNINGTIME  ,16 as ALERTTIME ,1 as REFRESH ,'' as METHOD_NAME,'' as STEP_NAME, ''
// as START_DATE, '' as MACHINE_NO, '' as STEP_NO ,RANK() OVER(ORDER BY RO.END_DATE, BD.BATCH_NO)
// RNK FROM DFIT_BTDATA BD, DFBT_HEADER BH,(SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP ,
// SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END) AS END_STEP, max(M.END_DATE) END_DATE  FROM DFBT_MONITOR M,
// DFMS_STEP ST   WHERE OU_CODE = 'D03'  AND ST.STEP_NO = M.STEP_NO AND EXISTS (SELECT * FROM DFBT_HEADER BH
// WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS =2)  GROUP BY M.OU_CODE, M.BATCH_NO 
// HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END)) = 0) RO  WHERE BD.OU_CODE =
// 'D03'  AND BD.OU_CODE = BH.OU_CODE  AND BD.BATCH_NO = BH.BATCH_NO  AND BD.DYE_EDATE IS NOT NULL  AND
// BD.OU_CODE = RO.OU_CODE  AND BD.BATCH_NO = RO.BATCH_NO  ORDER BY RO.END_DATE";

$sql = "SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY
,         TO_CHAR(RO.END_DATE,'DD/MM/YYYY HH24:MI')  DYE_EDATE
              ,ROUND((SYSDATE - RO.END_DATE) * (24),2) AS TIME_AGING
							,timestamp_diff(RO.END_DATE, sysdate) as	TIMESPAN
              ,TO_CHAR( RO.END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE
							,BD.SO_NO, BD.CUSTOMER_NAME

,CASE WHEN ((SYSDATE - RO.END_DATE) * (24)) > $alert then 'red'
                WHEN ((SYSDATE - RO.END_DATE) * (24)) > $warning then 'yellow'
                ELSE 'light-green-13' END ICON_COLOR	
                				 ,CASE WHEN ((SYSDATE - RO.END_DATE) * (24)) > $alert then 'warning'
                WHEN ((SYSDATE - RO.END_DATE) * (24)) > $warning then 'notification_important'
                ELSE 'flag' END ICON_NAME	
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
							,1 as REFRESH
							,'' as METHOD_NAME,'' as STEP_NAME, '' as START_DATE, '' as MACHINE_NO, '' as STEP_NO
							,RANK() OVER(ORDER BY RO.END_DATE, BD.BATCH_NO) RNK
FROM DFIT_BTDATA BD, DFBT_HEADER BH,
(
SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP , SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END) AS END_STEP,
max(M.END_DATE) END_DATE
              FROM DFBT_MONITOR M, DFMS_STEP ST 
              WHERE OU_CODE = '$ou'
               AND ST.STEP_NO = M.STEP_NO
              AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS =2)
              GROUP BY M.OU_CODE, M.BATCH_NO
              HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END)) = 0) RO
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BD.DYE_EDATE IS NOT NULL
              AND BD.OU_CODE = RO.OU_CODE
               AND BD.BATCH_NO = RO.BATCH_NO
               ORDER BY RO.END_DATE";



	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	//$resultArrayAll = array();
	$resultArray1 = array();
	$resultArray2 = array();
	$resultArray3 = array();
	
	$row = 1;
	while ($dr = oci_fetch_assoc($query)) {



		 if(intval($dr["RNK"]) > $min && intval($dr["RNK"])<=$max3){

		$batchno = $dr["BATCH_NO"];
		$oucode = $dr["OU_CODE"];

		$sql2 = "SELECT B.TOTAL_ROLL, B.TOTAL_QTY,
				(SELECT NVL(SUM(D.QTY),0) 
				FROM DFPL_HEADER H , DFPL_DETAIL D
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
				AND H.BATCH_NO = B.BATCH_NO
				AND H.OU_CODE = B.OU_CODE) PACK_QTY,
				(SELECT NVL(SUM(D.QTY),0) 
				FROM DFPL_HEADER H , DFPL_DETAIL D
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.SHIP_TO_WH = 'WH'
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
				AND H.BATCH_NO = B.BATCH_NO
				AND H.OU_CODE = B.OU_CODE) WH_QTY
				FROM DFIT_BTDATA B
				WHERE B.STATUS='2'
				AND B.OU_CODE = '$oucode'
				AND B.BATCH_NO = '$batchno'";




		 		$query2 = oci_parse($conn_omnoi, $sql2);

		 		oci_execute($query2);
				 while ($dr2 = oci_fetch_assoc($query2)) {

				 	
		 		 	$dr["STEP_NAME"] = $dr2["TOTAL_QTY"]; //$dr2["TOTAL_ROLL"].'/'.;
		 		 	$dr["START_DATE"] = $dr2["PACK_QTY"].' / '.$dr2["WH_QTY"] ; //$dr2["PL_ROLL"].'/'.;
		 		 }
		
		}
				


				if(intval($dr["RNK"]) > $min && intval($dr["RNK"])<=$max1){
					array_push($resultArray1, $dr);
				}elseif (intval($dr["RNK"])>$max1 && intval($dr["RNK"])<=$max2) {
					array_push($resultArray2, $dr);
				}elseif (intval($dr["RNK"])>$max2 && intval($dr["RNK"])<=$max3) {
					array_push($resultArray3, $dr);
				 }
		// 		}
		$row++;
	}


	$page = ceil($row/30);


	oci_close($conn_omnoi);





	$obj = new stdClass();
	$obj->totalPage =  $page;
	$obj->totalRow =  $row;


	$resultArray = array();
	
	array_push($resultArray, $resultArray1);
	array_push($resultArray, $resultArray2);
	array_push($resultArray, $resultArray3);

	$obj->batchs = $resultArray;
	$obj->sql = $filter_step;
	$obj->stepno = $stepno;


	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/batch_seq', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$batch = $_REQUEST['batch'];

	require './connect_demo.php';


// $sql = "SELECT M.METHOD_CONT, M.METHOD_CODE, M.ACTIVE_FLAG
// , ST.GROUP_STEP, M.STEP_NO, ST.STEP_NAME, M.MACHINE_NO
// , TO_CHAR(M.START_DATE,'DD/MM/YYYY HH24:MI') START_DATE
// , TO_CHAR(M.END_DATE,'DD/MM/YYYY HH24:MI') END_DATE
//               FROM DFBT_MONITOR M, DFMS_STEP ST
//               WHERE M.OU_CODE = '$ou'
//               AND M.BATCH_NO = '$batch'
//               --AND M.METHOD_CONT >5
//               AND M.STEP_NO = ST.STEP_NO(+)
//               ORDER BY M.METHOD_CONT, M.SEQ_NO";


$sql = "SELECT M.*
FROM (
SELECT M.METHOD_CONT, M.METHOD_CODE, M.ACTIVE_FLAG
, ST.GROUP_STEP, M.STEP_NO, ST.STEP_NAME, M.MACHINE_NO
, TO_CHAR(M.START_DATE,'DD/MM/YYYY HH24:MI') START_DATE
, TO_CHAR(M.END_DATE,'DD/MM/YYYY HH24:MI') END_DATE
, RANK() OVER( ORDER BY END_DATE DESC, SEQ_NO DESC)  STEP_RANK
              FROM DFBT_MONITOR M, DFMS_STEP ST
              WHERE M.OU_CODE = '$ou'
              AND M.BATCH_NO = '$batch'
              AND M.STEP_NO = ST.STEP_NO(+)) M
WHERE STEP_RANK = 1";


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