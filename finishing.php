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

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'FINISHING' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}

	$sql = "SELECT COUNT(BD.BATCH_NO) TOTAL
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $alert THEN 1 ELSE 0 END) STS_ALERT
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - CT.END_DATE) * (24),2) < $alert THEN 1 ELSE 0 END) STS_WARNING
              ,SUM(BD.TOTAL_QTY) TOTAL_KGS
              ,SUM(BD.TOTAL_ROLL) TOTAL_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $alert THEN BD.TOTAL_ROLL ELSE 0 END) ALERT_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - CT.END_DATE) * (24),2) < $alert THEN BD.TOTAL_ROLL ELSE 0 END) WARNING_ROLLS
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $alert THEN BD.TOTAL_QTY ELSE 0 END) ALERT_KGS
              ,SUM(CASE WHEN ROUND((SYSDATE - CT.END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - CT.END_DATE) * (24),2) < $alert THEN BD.TOTAL_QTY ELSE 0 END) WARNING_KGS
              FROM DFIT_BTDATA BD, DFBT_HEADER BH, CT_FIND_DRY_EDATE CT
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BD.OU_CODE = CT.OU_CODE
              AND BD.BATCH_NO = CT.BATCH_NO
              AND BH.STATUS < 8
              AND BD.DYE_EDATE IS NOT NULL
              --AND BD.DYE_EDATE > (TRUNC(SYSDATE) - 30)
              AND EXISTS (SELECT D.*
              FROM (
              SELECT DISTINCT M.OU_CODE, M.BATCH_NO
              FROM DFBT_MONITOR M, DFMS_STEP ST
              WHERE OU_CODE = '$ou'
              AND M.ACTIVE_FLAG = 'Y'
              AND ST.STEP_NO = M.STEP_NO
              AND (ST.GROUP_STEP = '05-Finishing'  OR ST.GROUP_STEP = '06-Special Finishing')
              AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS < 8)) D
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
		$data->total_roll = doubleval($dr["TOTAL_ROLLS"]);
		$data->total_kg = doubleval($dr["TOTAL_KGS"]);
		$data->alert = intval($dr["STS_ALERT"]);
		$data->alert_roll = doubleval($dr["ALERT_ROLLS"]);
		$data->alert_kg = doubleval($dr["ALERT_KGS"]);
		$data->warning = intval($dr["STS_WARNING"]);
		$data->warning_roll = doubleval($dr["WARNING_ROLLS"]);
		$data->warning_kg = doubleval($dr["WARNING_KGS"]);
		
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($data)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_step', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';


	$sql = "SELECT  ST.STEP_NO VALUE , STEP_NAME || '-' || ST.STEP_NO  LABEL, SUBSTR(ST.GROUP_STEP,1,2) GROUP_STEP
			FROM DFMS_STEP ST
			WHERE GROUP_STEP = '05-Finishing' or GROUP_STEP = '06-Special Finishing'
			AND ACTIVE = 'Y'
			ORDER BY ST.GROUP_STEP, STEP_NAME";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$rows = array();
    $nrows = oci_fetch_all($query, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW); //row array

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($rows)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/getBatchsAll', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url
	$ou = $_REQUEST['ou'];
	$page = intval($_REQUEST['page']);
	$stepno = $_REQUEST['stepno'];

	$grpStep = '05-Finishing';

	$filter_step = "";
	if($stepno=='' ){
		$filter_step = ' ';
	}else{
		$filter_step = "AND EXISTS ( SELECT BH1.*
                        FROM  DFBT_MONITOR BH1, DFMS_STEP ST
                        WHERE BH1.OU_CODE = '$ou'
                        AND (ST.GROUP_STEP like '05%' OR ST.GROUP_STEP like '06%')
                        AND ST.STEP_NO = BH1.STEP_NO 
                        AND ($stepno)
                        AND BH1.ACTIVE_FLAG = 'Y'
                        AND BH1.OU_CODE = BD.OU_CODE
                        AND BH1.BATCH_NO = BD.BATCH_NO )";
	}


	// $max3 = ($page*30);
	// $max2 = ($page*30)-10;
	// $max1 = ($page*30)-20;
	// $min = ($page*30)-30;
	$max2 = ($page*20);
	$max1 = ($page*20)-10;
	$min = ($page*20)-20;
		

	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'FINISHING' AND OU_CODE = '$ou'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}

	$sql = "SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY
	,         TO_CHAR(CT.END_DATE,'DD/MM/YYYY HH24:MI')  DYE_EDATE
              ,ROUND((SYSDATE - CT.END_DATE) * (24),2) AS TIME_AGING
			  ,timestamp_diff(CT.END_DATE, sysdate) as	TIMESPAN
			  ,ROUND((SYSDATE - BD.FABRIC_REC_BILL) * (24),2) AS FTIME_AGING
			  ,timestamp_diff(BD.FABRIC_REC_BILL,sysdate) AS FTIME
			  ,TO_CHAR(BD.FABRIC_REC_BILL,'DD-MM-YYYY HH24:MI') FABRIC_REC_BILL
              ,TO_CHAR(BD.WIP_REC_FC_DATE,'DD/MM/YYYY HH24:MI') WIP_REC_FC_DATE
              ,TO_CHAR( CT.END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE
							,BD.SO_NO, BD.CUSTOMER_NAME
 ,CASE WHEN ((SYSDATE - CT.END_DATE) * (24)) > $alert then 'red'
                WHEN ((SYSDATE - CT.END_DATE) * (24)) > $warning then 'yellow'
                ELSE 'light-green-13' END ICON_COLOR	
                				 ,CASE WHEN ((SYSDATE - CT.END_DATE) * (24)) > $alert then 'warning'
                WHEN ((SYSDATE - CT.END_DATE) * (24)) > $warning then 'notification_important'
                ELSE 'flag' END ICON_NAME	
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
							,1 as REFRESH
							,'' as METHOD_NAME,'' as STEP_NAME, '' as START_DATE, '' as MACHINE_NO, '' as STEP_NO
							,RANK() OVER(ORDER BY CT.END_DATE, BD.BATCH_NO) RNK, 0 as SHOWTOOLTIP
              FROM DFIT_BTDATA BD, DFBT_HEADER BH, 
              (SELECT M.OU_CODE, M.BATCH_NO, MAX(S.END_DATE) END_DATE , TO_CHAR( MAX(S.END_DATE),'DD/MM/YYYY HH24:MI') END_DATE_TXT
				FROM CT_FIND_DRY_EDATE M, DFBT_MONITOR S
				WHERE EXISTS (SELECT * FROM CT_FIND_FINISH_NOT_COMPLETE D WHERE M.OU_CODE = D.OU_CODE AND M.BATCH_NO = D.BATCH_NO)
				AND M.OU_CODE = S.OU_CODE AND M.BATCH_NO = S.BATCH_NO
				GROUP BY M.OU_CODE, M.BATCH_NO) CT
              WHERE BD.OU_CODE = '$ou'
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BD.OU_CODE = CT.OU_CODE
              AND BD.BATCH_NO = CT.BATCH_NO
              AND BH.STATUS < 8
              AND BD.DYE_EDATE IS NOT NULL
              --AND BD.DYE_EDATE > (TRUNC(SYSDATE) - 30)
              AND EXISTS (SELECT D.*
              FROM (  SELECT DISTINCT M.OU_CODE, M.BATCH_NO
              FROM DFBT_MONITOR M, DFMS_STEP ST
              WHERE OU_CODE = '$ou'
              AND M.ACTIVE_FLAG = 'Y'
              AND ST.STEP_NO = M.STEP_NO
              AND (ST.GROUP_STEP = '05-Finishing'  OR ST.GROUP_STEP = '06-Special Finishing')
              AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS < 8)) D
              WHERE BD.OU_CODE = D.OU_CODE AND BD.BATCH_NO = D.BATCH_NO
              )
 				$filter_step
              ORDER BY CT.END_DATE";
//             filter_step
              //AND ROUND((SYSDATE - BD.DYE_EDATE) * (24),2) >= 0
              //AND EXISTS (SELECT * FROM DFMS_STEP ST WHERE ST.GROUP_STEP = '04-Dryer' AND ST.STEP_NO = M.STEP_NO $filter_step)
              //,FLOOR(MOD((SYSDATE - CT.END_DATE) * (24),0)) || ':' || LPAD(CEIL(MOD((SYSDATE - CT.END_DATE) * (24),1)*60),2,'0') AS TIMESPAN

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	//$resultArrayAll = array();
	$resultArray1 = array();
	$resultArray2 = array();
	// $resultArray3 = array();
	
	$row = 1;
	while ($dr = oci_fetch_assoc($query)) {



		if(intval($dr["RNK"]) > $min && intval($dr["RNK"])<=$max2){

		$batchno = $dr["BATCH_NO"];
		$oucode = $dr["OU_CODE"];

		$sql2 = "SELECT M.*
				FROM (
				SELECT OU_CODE, BATCH_NO, BH.METHOD_CODE, MT.METHOD_NAME,  BH.STEP_NO, TO_CHAR(BH.START_DATE,'DD/MM/YYYY HH24:MI') START_DATE
				, ST.STEP_NAME, BH.MACHINE_NO, RANK() OVER( ORDER BY BH.METHOD_CONT, BH.SEQ_NO)  STEP_RANK
				FROM  DFBT_MONITOR BH, DFMS_STEP ST, DFMS_METHOD MT
				WHERE BH.STEP_NO = ST.STEP_NO
				AND BH.METHOD_CODE = MT.METHOD_CODE
				AND BH.END_DATE IS NULL
				AND BH.BATCH_NO = '$batchno'
				AND BH.OU_CODE = '$oucode'
				AND (ST.GROUP_STEP = '05-Finishing'  OR ST.GROUP_STEP = '06-Special Finishing')

				AND EXISTS (SELECT H.* FROM DFBT_HEADER H WHERE H.STATUS < 8 AND H.OU_CODE = BH.OU_CODE AND H.BATCH_NO = BH.BATCH_NO)
				) M
				WHERE STEP_RANK = 1";

//                AND BH.METHOD_CONT = 4
//ORDER BY BH.METHOD_CONT, BH.SEQ_NO

				$query2 = oci_parse($conn_omnoi, $sql2);

				oci_execute($query2);
				 while ($dr2 = oci_fetch_assoc($query2)) {

				 	$dr["METHOD_NAME"] = $dr2["METHOD_NAME"];
				 	$dr["STEP_NO"] = $dr2["STEP_NO"];
				 	$dr["STEP_NAME"] = $dr2["STEP_NAME"];
				 	$dr["START_DATE"] = $dr2["START_DATE"];
				 	$dr["MACHINE_NO"] = $dr2["MACHINE_NO"];
				 }
				}

		// array_push($resultArrayAll, $dr);
		// array_push($resultArray1, $dr);
		// array_push($resultArray2, $dr);
		// array_push($resultArray3, $dr);
		if(intval($dr["RNK"]) > $min && intval($dr["RNK"])<=$max1){
			array_push($resultArray1, $dr);
		}elseif (intval($dr["RNK"])>$max1 && intval($dr["RNK"])<=$max2) {
			array_push($resultArray2, $dr);
		}
		// elseif (intval($dr["RNK"])>$max2 && intval($dr["RNK"])<=$max3) {
		// 	array_push($resultArray3, $dr);
		// }
		$row++;
	}


	$page = ceil($row/20);


	oci_close($conn_omnoi);


	// foreach($resultArrayAll as $value){
	//   //echo "Salary: $value<br>";
	// }


	$obj = new stdClass();
	$obj->totalPage =  $page;
	$obj->totalRow =  $row;


	$resultArray = array();
	
	array_push($resultArray, $resultArray1);
	array_push($resultArray, $resultArray2);
	//array_push($resultArray, $resultArray3);

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


$sql = "SELECT M.METHOD_CONT, M.METHOD_CODE, M.ACTIVE_FLAG
, ST.GROUP_STEP, M.STEP_NO, ST.STEP_NAME, M.MACHINE_NO
, TO_CHAR(M.START_DATE,'DD/MM/YYYY HH24:MI') START_DATE
, TO_CHAR(M.END_DATE,'DD/MM/YYYY HH24:MI') END_DATE
              FROM DFBT_MONITOR M, DFMS_STEP ST
              WHERE M.OU_CODE = '$ou'
              AND M.BATCH_NO = '$batch'
              AND (ST.GROUP_STEP = '05-Finishing'  OR ST.GROUP_STEP = '06-Special Finishing')
              AND M.STEP_NO = ST.STEP_NO(+)
              ORDER BY M.METHOD_CONT, M.SEQ_NO";

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