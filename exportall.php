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

$app->post('/list_excel_dry', function (Request $request, Response $response) {


	$OU = $_REQUEST['ou'];

	require './connect_demo.php';

	$sqlDry = "SELECT CREATE_DATE
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_DRY D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '07') AS H07
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_DRY D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '19') AS H19
	FROM (
	SELECT DISTINCT TO_CHAR(M.CREATE_DATE,'YYYY-MM-DD') CREATE_DATE
	FROM CONTROL_HIS_DRY M
	ORDER BY 1 DESC ) M
	";

	$queryDry = oci_parse($conn_omnoi, $sqlDry);

	$resultArrayDry = array();

	oci_execute($queryDry);
	while ($dr = oci_fetch_assoc($queryDry)) {
		array_push($resultArrayDry, $dr);
	}



	$sqlFinishing = "SELECT CREATE_DATE
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_FINISHING D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '07') AS H07
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_FINISHING D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '19') AS H19
	FROM (
	SELECT DISTINCT TO_CHAR(M.CREATE_DATE,'YYYY-MM-DD') CREATE_DATE
	FROM CONTROL_HIS_FINISHING M
	ORDER BY 1 DESC ) M
	";

	$queryFinishing = oci_parse($conn_omnoi, $sqlFinishing);

	$resultArrayFinishing = array();

	oci_execute($queryFinishing);
	while ($dr = oci_fetch_assoc($queryFinishing)) {
		array_push($resultArrayFinishing, $dr);
	}



	$sqlInspection = "SELECT CREATE_DATE
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_INSPECTION D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '07') AS H07
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_INSPECTION D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '19') AS H19
	FROM (
	SELECT DISTINCT TO_CHAR(M.CREATE_DATE,'YYYY-MM-DD') CREATE_DATE
	FROM CONTROL_HIS_INSPECTION M
	ORDER BY 1 DESC ) M ";

	$queryInspection = oci_parse($conn_omnoi, $sqlInspection);

	$resultArrayInspection = array();

	oci_execute($queryInspection);
	while ($dr = oci_fetch_assoc($queryInspection)) {
		array_push($resultArrayInspection, $dr);
	}


	$sqlPreWH = "SELECT CREATE_DATE
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_PRE_WAREHOUSE D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '07') AS H07
	,(SELECT DISTINCT TO_CHAR(D.CREATE_DATE,'DD/MM/YYYY HH24') FROM CONTROL_HIS_PRE_WAREHOUSE D WHERE TO_CHAR(D.CREATE_DATE,'YYYY-MM-DD') = M.CREATE_DATE AND TO_CHAR(D.CREATE_DATE,'HH24') = '19') AS H19
	FROM (
	SELECT DISTINCT TO_CHAR(M.CREATE_DATE,'YYYY-MM-DD') CREATE_DATE
	FROM CONTROL_HIS_PRE_WAREHOUSE M
	ORDER BY 1 DESC ) M ";

	$queryPreWH = oci_parse($conn_omnoi, $sqlPreWH);

	$resultArrayPreWH = array();

	oci_execute($queryPreWH);
	while ($dr = oci_fetch_assoc($queryPreWH)) {
		array_push($resultArrayPreWH, $dr);
	}



	$obj = new stdClass();
	$obj->dry = $resultArrayDry;
	$obj->finishing = $resultArrayFinishing;
	$obj->inspection = $resultArrayInspection;
	$obj->prewh = $resultArrayPreWH;


	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});

$app->post('/exportDry', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$dte = $_REQUEST['dte'];
	

	require './connect_demo.php';


	$resultArray = array();


	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
				, TO_CHAR(CREATE_DATE,'YYYY-MM-DD HH24:MI') CREATE_DATE_TXT
				, SUBSTR(END_DYE_DATE,7,4) || '-' || SUBSTR(END_DYE_DATE,4,2)|| '-' || SUBSTR(END_DYE_DATE,1,2) || ' ' || SUBSTR(END_DYE_DATE,12,5) END_DYE_DATE2
				FROM CONTROL_HIS_DRY M
				WHERE 1 = 1
				AND OU_CODE = '$ou'
                AND TO_CHAR(CREATE_DATE,'DD/MM/YYYY HH24') = '$dte'
			";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	while ($dr = oci_fetch_assoc($query)) {
		$data = new stdClass();


		$data->{'OU_CODE'}= $dr["OU_CODE"];
		$data->{'BATCH_NO'}= $dr["BATCH_NO"];
		$data->{'ITEM_CODE'}= $dr["ITEM_CODE"];
		$data->{'COLOR_CODE'}= $dr["COLOR_CODE"];
		$data->{'COLOR_DESC'}= $dr["COLOR_DESC"];
		$data->{'TOTAL_ROLL'}=  intval($dr["TOTAL_ROLL"]);
		$data->{'TOTAL_QTY'}= doubleval($dr["TOTAL_QTY"]);
		$data->{'END_DRY_DATE'}= $dr["END_DYE_DATE2"];
		$data->{'LEADTIME'}= doubleval($dr["TIME_AGING"]);
		$data->{'LEADTIME_TXT'}= $dr["TIMESPAN"];
		$data->{'FTIME'}= doubleval($dr["FTIME_AGING"]);
		$data->{'FTIME_TXT'}= $dr["FTIME"];
		$data->{'SO_NO'}= $dr["SO_NO"];
		$data->{'CUSTOMER_ID'}= $dr["CUSTOMER_ID"];
		$data->{'CUSTOMER_NAME'}= $dr["CUSTOMER_NAME"];
		$data->{'VI'} = $dr["VI"];
		$data->{'STEP_NO'} = $dr["STEP_NO"];
		$data->{'STEP_NAME'} = $dr["STEP_NAME"];
		$data->{'DATA_CREATE_DATE'}= $dr["CREATE_DATE_TXT"]; 
		$data->{'DATA'}= 'DRY';

        array_push($resultArray,$data);
	}
	

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/exportFinishing', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$dte = $_REQUEST['dte'];


	require './connect_demo.php';


	$resultArray = array();


	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
				, TO_CHAR(CREATE_DATE,'YYYY-MM-DD HH24:MI') CREATE_DATE_TXT
				, SUBSTR(END_DYE_DATE,7,4) || '-' || SUBSTR(END_DYE_DATE,4,2)|| '-' || SUBSTR(END_DYE_DATE,1,2) || ' ' || SUBSTR(END_DYE_DATE,12,5) END_DYE_DATE2
				FROM CONTROL_HIS_FINISHING M
				WHERE 1 = 1
				AND OU_CODE = '$ou'
				AND TO_CHAR(CREATE_DATE,'DD/MM/YYYY HH24') = '$dte'
				
			";


	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	while ($dr = oci_fetch_assoc($query)) {
		$data = new stdClass();


		$data->{'OU_CODE'}= $dr["OU_CODE"];
		$data->{'BATCH_NO'}= $dr["BATCH_NO"];
		$data->{'ITEM_CODE'}= $dr["ITEM_CODE"];
		$data->{'COLOR_CODE'}= $dr["COLOR_CODE"];
		$data->{'COLOR_DESC'}= $dr["COLOR_DESC"];
		$data->{'TOTAL_ROLL'}=  intval($dr["TOTAL_ROLL"]);
		$data->{'TOTAL_QTY'}= doubleval($dr["TOTAL_QTY"]);
		$data->{'END_DRY_DATE'}= $dr["END_DYE_DATE2"];
		$data->{'LEADTIME'}= doubleval($dr["TIME_AGING"]);
		$data->{'LEADTIME_TXT'}= $dr["TIMESPAN"];
		$data->{'FTIME'}= doubleval($dr["FTIME_AGING"]);
		$data->{'FTIME_TXT'}= $dr["FTIME"];
		$data->{'SO_NO'}= $dr["SO_NO"];
		$data->{'CUSTOMER_ID'}= $dr["CUSTOMER_ID"];
		$data->{'CUSTOMER_NAME'}= $dr["CUSTOMER_NAME"];
		$data->{'VI'} = $dr["VI"];
		$data->{'STEP_NO'} = $dr["STEP_NO"];
		$data->{'STEP_NAME'} = $dr["STEP_NAME"];
		$data->{'DATA_CREATE_DATE'}= $dr["CREATE_DATE_TXT"]; 
		$data->{'DATA'}= 'FINISHING';

         // $data->{'FG_FINISH_DATE'}= date("d-M-Y", strtotime($dr["RECEIVE_DATE"])); 

        array_push($resultArray,$data);
	}
	

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/exportInspection', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$dte = $_REQUEST['dte'];

	require './connect_demo.php';


	$resultArray = array();


	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
				, TO_CHAR(CREATE_DATE,'YYYY-MM-DD HH24:MI') CREATE_DATE_TXT
				, SUBSTR(END_LAST_STEP,7,4) || '-' || SUBSTR(END_LAST_STEP,4,2)|| '-' || SUBSTR(END_LAST_STEP,1,2) || ' ' || SUBSTR(END_LAST_STEP,12,5) END_LAST_STEP2
				FROM CONTROL_HIS_INSPECTION M
				WHERE 1 = 1
				AND OU_CODE = '$ou'
                AND TO_CHAR(CREATE_DATE,'DD/MM/YYYY HH24') = '$dte'
				AND (decode(nvl(color_approve,'x'),'clear',1,0)+decode(nvl(qt_approve,'x'),'clear',1,0))=0
			";


	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	while ($dr = oci_fetch_assoc($query)) {
		$data = new stdClass();


		$data->{'OU_CODE'}= $dr["OU_CODE"];
		$data->{'BATCH_NO'}= $dr["BATCH_NO"];
		$data->{'ITEM_CODE'}= $dr["ITEM_CODE"];
		$data->{'COLOR_CODE'}= $dr["COLOR_CODE"];
		$data->{'COLOR_DESC'}= $dr["COLOR_DESC"];
		$data->{'TOTAL_ROLL'}=  intval($dr["TOTAL_ROLL"]);
		$data->{'TOTAL_QTY'}= doubleval($dr["TOTAL_QTY"]);
		$data->{'END_LAST_STEP'}= $dr["END_LAST_STEP2"];
		$data->{'LEADTIME'}= doubleval($dr["TIME_AGING"]);
		$data->{'LEADTIME_TXT'}= $dr["TIMESPAN"];
		$data->{'FTIME'}= doubleval($dr["FTIME_AGING"]);
		$data->{'FTIME_TXT'}= $dr["FTIME"];
		$data->{'SO_NO'}= $dr["SO_NO"];
		$data->{'CUSTOMER_ID'}= $dr["CUSTOMER_ID"];
		$data->{'CUSTOMER_NAME'}= $dr["CUSTOMER_NAME"];
		$data->{'VI'} = $dr["VI"];
		$data->{'STEP_NO'} = $dr["STEP_NO"];
		$data->{'STEP_NAME'} = $dr["STEP_NAME"];
		if($dr["COLOR_APPROVE"]=='done'){
			$data->{'COLOR_APPROVE'} = "APPROVE";
		}elseif ($dr["COLOR_APPROVE"]=='clear') {
			$data->{'COLOR_APPROVE'} = "REJECT";
		} else {
			$data->{'COLOR_APPROVE'} = '';
		}
		$data->{'COLOR_APPROVE_DATE'} = $dr["COLOR_APPROVE_DATE"];
		if($dr["QT_APPROVE"]=='done'){
			$data->{'QT_APPROVE'} = "APPROVE";
		}elseif ($dr["QT_APPROVE"]=='clear') {
			$data->{'QT_APPROVE'} = "REJECT";
		} else {
			$data->{'QT_APPROVE'} = '';
		}
		$data->{'QT_APPROVE_DATE'} = $dr["QT_APPROVE_DATE"];
		$data->{'DATA_CREATE_DATE'}= $dr["CREATE_DATE_TXT"]; 
		$data->{'DATA'}= 'INSPECTION';


         // $data->{'FG_FINISH_DATE'}= date("d-M-Y", strtotime($dr["RECEIVE_DATE"])); 

         array_push($resultArray,$data);
	}
	

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/exportPreWH', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$dte = $_REQUEST['dte'];

	require './connect_demo.php';

	$resultArray = array();


	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
				, TO_CHAR(CREATE_DATE,'YYYY-MM-DD HH24:MI') CREATE_DATE_TXT
				, SUBSTR(END_DYE_DATE,7,4) || '-' || SUBSTR(END_DYE_DATE,4,2)|| '-' || SUBSTR(END_DYE_DATE,1,2) || ' ' || SUBSTR(END_DYE_DATE,12,5) END_DYE_DATE2
				FROM CONTROL_HIS_PRE_WAREHOUSE M
				WHERE 1 = 1
				AND OU_CODE = '$ou'
				AND TO_CHAR(CREATE_DATE,'DD/MM/YYYY HH24') = '$dte'
			";

			//$filter_status


	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	while ($dr = oci_fetch_assoc($query)) {
		$data = new stdClass();


		$data->{'OU_CODE'}= $dr["OU_CODE"];
		$data->{'BATCH_NO'}= $dr["BATCH_NO"];
		$data->{'ITEM_CODE'}= $dr["ITEM_CODE"];
		$data->{'COLOR_CODE'}= $dr["COLOR_CODE"];
		$data->{'COLOR_DESC'}= $dr["COLOR_DESC"];
		$data->{'TOTAL_ROLL'}=  intval($dr["TOTAL_ROLL"]);
		$data->{'TOTAL_QTY'}= doubleval($dr["TOTAL_QTY"]);
		
		$data->{'LEADTIME'}= doubleval($dr["TIME_AGING"]);
		// $data->{'LEADTIME_TXT'}= $dr["TIMESPAN"];
		$data->{'FTIME'}= doubleval($dr["FTIME_AGING"]);
		// $data->{'FTIME_TXT'}= $dr["FTIME"];
		$data->{'SO_NO'}= $dr["SO_NO"];
		$data->{'CUSTOMER_ID'}= $dr["CUSTOMER_ID"];
		$data->{'CUSTOMER_NAME'}= $dr["CUSTOMER_NAME"];
		$data->{'VI'} = $dr["VI"];

		if($dr["STS_NORMAL_DATE"]==null){
			$data->{'NORMAL'} = doubleval($dr["STS_NORMAL"]);
		}else{
			$data->{'NORMAL'} = 0;
		}
		
		if($dr["STS_INACTIVE_DATE"]==null){
			$data->{'INACTIVE'} = doubleval($dr["STS_INACTIVE"]);
		}else{
			$data->{'INACTIVE'} = 0;
		}

		if($dr["STS_SCRAP_DATE"]==null){
			$data->{'SCRAP'} = doubleval($dr["STS_SCRAP"]);
		}else{
			$data->{'SCRAP'} = 0;
		}
		$data->{'DATA_CREATE_DATE'}= $dr["CREATE_DATE_TXT"]; 
		$data->{'DATA'}= 'PRE WH';

         // $data->{'FG_FINISH_DATE'}= date("d-M-Y", strtotime($dr["RECEIVE_DATE"])); 

         array_push($resultArray,$data);
	}
	

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});





$app->run(); // สั่งให้ระบบทำงาน

?>