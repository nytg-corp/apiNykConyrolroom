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

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'PREPARE' AND OU_CODE = '$ou'";

	$warning = 480;
	$alert = 240;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	$warning_hrs = 0;
	$alert_hrs = 0;
	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning_hrs = intval($dr["WARNING_MINUTE"])/60;
		$alert_hrs = intval($dr["ALERT_MINUTE"])/60;
	}




			$sql = "SELECT (BD.BATCH_NO) , (BD.TOTAL_QTY) , (BD.TOTAL_ROLL)  
, (CASE WHEN (BATCH_START <= (SYSDATE + INTERVAL '$alert' MINUTE)) THEN 1 ELSE 0 END) STS_ALERT
, (CASE WHEN (BATCH_START <= (SYSDATE + INTERVAL '$alert' MINUTE)) THEN BD.TOTAL_QTY ELSE 0 END) ALERT_KGS
, (CASE WHEN (BATCH_START <= (SYSDATE + INTERVAL '$alert' MINUTE)) THEN BD.TOTAL_ROLL ELSE 0 END) ALERT_ROLLS
, (CASE WHEN (BATCH_START > (SYSDATE + INTERVAL '$alert' MINUTE)) AND (BATCH_START <= (SYSDATE + INTERVAL '$warning' MINUTE)) THEN 1 ELSE 0 END) STS_WARNING
, (CASE WHEN (BATCH_START > (SYSDATE + INTERVAL '$alert' MINUTE)) AND (BATCH_START <= (SYSDATE + INTERVAL '$warning' MINUTE)) THEN  BD.TOTAL_QTY ELSE 0 END) WARNING_KGS
, (CASE WHEN (BATCH_START > (SYSDATE + INTERVAL '$alert' MINUTE)) AND (BATCH_START <= (SYSDATE + INTERVAL '$warning' MINUTE)) THEN BD.TOTAL_ROLL ELSE 0 END) WARNING_ROLLS
			FROM (
			SELECT  J.OU_CODE, J.JOB_NO, MAX(J.BATCH_START) BATCH_START, MAX(J.BATCH_END)BATCH_END
				FROM DFIT_DASHBOARD_FABRIC J
				WHERE OU_CODE = '$ou'  
				AND J.BATCH_START >= (SYSDATE-(1/24)) 
				GROUP BY J.OU_CODE, J.JOB_NO
				ORDER BY BATCH_START 
			)J , DFIT_BTDATA BD
			WHERE J.JOB_NO = BD.OU_CODE || BD.JOB_NO";

	$query = oci_parse($conn_omnoi, $sql);

	 oci_execute($query);

	 // $cnt_job = 0;

	 // while ($dr = oci_fetch_assoc($query)) {
	 // 	$cnt_job++;
	 // }

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
	$data->warningtime = $warning_hrs;
	$data->alerttime = $alert_hrs;

	$cnt_batch = 0;
	$total_kg = 0;
	$total_roll = 0;
	$alert = 0;
	$alert_kg = 0;
	$alert_roll = 0;
	$warning = 0;
	$warning_kg = 0;
	$warning_roll = 0;

	while ($dr = oci_fetch_assoc($query)) {
		
		$cnt_batch++;
		$total_roll = $total_roll + doubleval($dr["TOTAL_ROLL"]);
		$total_kg = $total_kg + doubleval($dr["TOTAL_QTY"]);
		$alert = $alert + intval($dr["STS_ALERT"]);
		$alert_roll = $alert_roll + intval($dr["ALERT_ROLLS"]);
		$alert_kg = $alert_kg + doubleval($dr["ALERT_KGS"]);
		$warning = $warning + intval($dr["STS_WARNING"]);
		$warning_roll = $warning_roll + intval($dr["WARNING_ROLLS"]);
		$warning_kg = $warning_kg + doubleval($dr["WARNING_KGS"]);
	}


	    $data->total = $cnt_batch;
	 	$data->total_roll = $total_roll;
	 	$data->total_kg = $total_kg;
	 	$data->alert = $alert;
	 	$data->alert_roll = $alert_roll;
	 	$data->alert_kg = $alert_kg;
	 	$data->warning = $warning;
	 	$data->warning_roll = $warning_roll;
	 	$data->warning_kg = $warning_kg;

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($data)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/getBatchsAll', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url
	$ou = $_REQUEST['ou'];
	$page = intval($_REQUEST['page']);
	$stepno = $_REQUEST['stepno'];
	$job = strtoupper($_REQUEST['job']);
	$itemcode = strtoupper($_REQUEST['itemcode']);

	//$grpStep = '05-Finishing';

	$filter_step = "";
	if($stepno=='' ){
		$filter_step = ' ';
	}else{
		$filter_step = " AND ($stepno) ";
	}

	$filter_job = "";
	if($job=='' ){
		$filter_job = ' ';
	}else{
		$filter_job = " AND UPPER(J.JOB_NO) like '%$job%' ";
	}

	$filter_item = "";
	if($itemcode=='' ){
		$filter_item = ' ';
	}else{
		$filter_item = " AND UPPER(BD.ITEM_CODE) like '%$itemcode%' ";
	}


	$max2 = ($page*20);
	$max1 = ($page*20)-10;
	$min = ($page*20)-20;
		

	require './connect_demo.php';

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'PREPARE' AND OU_CODE = '$ou'";

	$warning = 480;
	$alert = 240;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]);
		$alert = intval($dr["ALERT_MINUTE"]);
	}


			$sql = "SELECT J.JOB_NO,J.BATCH_START, TO_CHAR(J.BATCH_START,'DD/MM/YYYY HH24:MI') END_DYE_DATE
			,timestamp_diff(sysdate,batch_start) as	TIMESPAN
			,ROUND((SYSDATE - BD.FABRIC_REC_BILL) * (24),2) AS FTIME_AGING
            ,timestamp_diff(BD.FABRIC_REC_BILL,sysdate) AS FTIME
            ,TO_CHAR(BD.FABRIC_REC_BILL,'DD-MM-YYYY HH24:MI') FABRIC_REC_BILL
			, STEP.TOTAL_STEP, STEP.STEP_COMPLETE
			,case when (batch_start <= (sysdate + interval '$alert' minute)) then 'timer_off'
      			  when (batch_start > (sysdate + interval '$alert' minute)) and (batch_start <= (sysdate + interval '$warning' minute)) then 'timer'
      			  else 'access_time' END ICON_NAME
			,case when (batch_start <= (sysdate + interval '$alert' minute)) then 'red'
      			when (batch_start > (sysdate + interval '$alert' minute)) and (batch_start <= (sysdate + interval '$warning' minute)) then 'yellow'
      			else 'light-green-13' END ICON_COLOR
			              ,'$warning' AS WARNINGTIME
			              ,'$alert' AS ALERTTIME
			               ,1 as REFRESH
							,'' as METHOD_NAME, '' as START_DATE, '' as MACHINE_NO
							,RANK() OVER(ORDER BY J.BATCH_START,J.JOB_NO, BD.BATCH_NO) RNK
							 ,TO_CHAR(J.BATCH_START,'DD/MM/YYYY HH24:MI')||'-'||BD.OU_CODE || '-' || BD.BATCH_NO AS KEYJOB
			,BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY
			,BD.SO_NO, BD.CUSTOMER_NAME,  TO_CHAR(BD.WIP_REC_FC_DATE,'DD/MM/YYYY HH24:MI') WIP_REC_FC_DATE
			, LS.STEP_NO , LS_D.STEP_NAME
			FROM (
				SELECT  J.OU_CODE, J.JOB_NO, MAX(J.BATCH_START) BATCH_START, MAX(J.BATCH_END)BATCH_END
				FROM DFIT_DASHBOARD_FABRIC J
				WHERE OU_CODE = '$ou'   
				AND J.BATCH_START >= (SYSDATE-(1/24))
				GROUP BY J.OU_CODE, J.JOB_NO
				ORDER BY BATCH_START 
			)J , DFIT_BTDATA BD, (SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP 
			,SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END) AS STEP_COMPLETE
			          FROM DFBT_MONITOR M, DFMS_STEP ST 
			              WHERE OU_CODE = '$ou'
			               AND ST.STEP_NO = M.STEP_NO
			               AND M.METHOD_CONT = 1
			              GROUP BY M.OU_CODE, M.BATCH_NO
			              ) STEP, ( SELECT LS.* FROM CT_LAST_STEP LS WHERE STEP_RANK = 1) LS, DFMS_STEP LS_D
			WHERE J.JOB_NO = BD.OU_CODE || BD.JOB_NO
			AND BD.OU_CODE = STEP.OU_CODE(+)
			AND BD.BATCH_NO = STEP.BATCH_NO(+)
			AND BD.OU_CODE = LS.OU_CODE(+)
			AND BD.BATCH_NO = LS.BATCH_NO(+)
			AND LS.STEP_NO = LS_D.STEP_NO(+)
			AND BD.STATUS <= 2
			$filter_item
			$filter_job
			AND BD.JOB_NO IN (SELECT DISTINCT DQ.JOB_NO FROM DFIT_QUEUE DQ WHERE DQ.OU_CODE = BD.OU_CODE AND DQ.JOB_NO = BD.JOB_NO AND DQ.DYE_EDATE IS NULL)
			$filter_step
			ORDER BY J.BATCH_START,  J.JOB_NO";



	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray1 = array();
	$resultArray2 = array();

	$resultArrayBatch = array();
	$resultArrayJob = array();
	$resultArrayItemcode = array();
	
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
				AND BH.METHOD_CONT = 2
				) M
				WHERE STEP_RANK = 1";

		 		$query2 = oci_parse($conn_omnoi, $sql2);

		 		oci_execute($query2);
		 		while ($dr2 = oci_fetch_assoc($query2)) {

				 	// $dr["METHOD_NAME"] = $dr2["METHOD_NAME"];
				 	// $dr["STEP_NO"] = $dr2["STEP_NO"];
				 	// $dr["STEP_NAME"] = $dr2["STEP_NAME"];
				 	// $dr["START_DATE"] = $dr2["START_DATE"];
				 	$dr["MACHINE_NO"] = $dr2["MACHINE_NO"];
		 		}
	

	
		 	if(intval($dr["RNK"]) > $min && intval($dr["RNK"])<=$max1){
		 		array_push($resultArray1, $dr);
		 	}
		 	 elseif (intval($dr["RNK"])>$max1 && intval($dr["RNK"])<=$max2) {
		 	 	array_push($resultArray2, $dr);
		 	 }
		 	 // elseif (intval($dr["RNK"])>$max2 && intval($dr["RNK"])<=$max3) {
		 	 // 	array_push($resultArray3, $dr);
		 	 // }
	 	 }

	 	array_push($resultArrayBatch, $dr['BATCH_NO']);
		array_push($resultArrayJob, $dr['JOB_NO']);
		array_push($resultArrayItemcode, $dr['ITEM_CODE']);
		
	 	
	 	$row++;
	 }


	 $page = ceil($row/20);


	 oci_close($conn_omnoi);

	$obj = new stdClass();
	$obj->totalPage =  $page;
	$obj->totalRow =  $row;
	$obj->filter = $filter_step;


	$resultArray = array();
	
	array_push($resultArray, $resultArray1);
	array_push($resultArray, $resultArray2);
	// array_push($resultArray, $resultArray3);

	$obj->batchs = $resultArray;
	$obj->arrayBatchs = $resultArrayBatch;
	$obj->arrayJobs = $resultArrayJob;
	$obj->arrayItemcodes = $resultArrayItemcode;


	//$obj->sql = $sql;
	//$obj->stepno = $stepno;


	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_step', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$sql = "SELECT  ST.STEP_NO VALUE , STEP_NAME || '-' || ST.STEP_NO  LABEL, SUBSTR(ST.GROUP_STEP,1,2) GROUP_STEP
			FROM DFMS_STEP ST
			WHERE GROUP_STEP = '02-Preparation'
			AND ACTIVE = 'Y'
			ORDER BY STEP_NAME";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$rows = array();
    $nrows = oci_fetch_all($query, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW); //row array

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($rows)); // สร้างคำตอบกลับ

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
              AND M.METHOD_CONT = 1
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