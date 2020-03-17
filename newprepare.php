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

	$sql = "SELECT COUNT(*) TOTAL, SUM(TOTAL_ROLL) TOTAL_ROLL, SUM(TOTAL_QTY) TOTAL_QTY 
			,SUM(CASE WHEN ICON_COLOR = 'red' THEN 1 ELSE 0 END) ALERT
			,SUM(CASE WHEN ICON_COLOR = 'red' THEN TOTAL_ROLL ELSE 0 END) ALERT_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'red' THEN TOTAL_QTY ELSE 0 END) ALERT_QTY
			,SUM(CASE WHEN ICON_COLOR = 'yellow' THEN 1 ELSE 0 END) WARNING
			,SUM(CASE WHEN ICON_COLOR = 'yellow' THEN TOTAL_ROLL ELSE 0 END) WARNING_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'yellow' THEN TOTAL_QTY ELSE 0 END) WARNING_QTY
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN 1 ELSE 0 END) NORMAL
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN TOTAL_ROLL ELSE 0 END) NORMAL_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN TOTAL_QTY ELSE 0 END) NORMAL_QTY
			,MAX(WARNINGTIME)/60 WARNING_HRS
			,MAX(ALERTTIME)/60 ALERT_HRS
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$ou'";



	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);


	$data = new stdClass();
	$data->total = 0;
	$data->warning = 0;
	$data->alert = 0;
	$data->normal = 0;
	$data->total_kg = 0;
	$data->warning_kg = 0;
	$data->alert_kg = 0;
	$data->normal_kg = 0;
	$data->total_roll = 0;
	$data->warning_roll = 0;
	$data->alert_roll = 0;
	$data->normal_roll = 0;
	$data->warningtime = 0;
	$data->alerttime = 0;

	while ($dr = oci_fetch_assoc($query)) {
		$data->total = intval($dr["TOTAL"]);
	 	$data->total_roll = intval($dr["TOTAL_ROLL"]);
	 	$data->total_kg = doubleval($dr["TOTAL_QTY"]);
	 	$data->alert = intval($dr["ALERT"]);
	 	$data->alert_roll = intval($dr["ALERT_ROLL"]);
	 	$data->alert_kg = doubleval($dr["ALERT_QTY"]);
	 	$data->warning = intval($dr["WARNING"]);
	 	$data->warning_roll = intval($dr["WARNING_ROLL"]);
	 	$data->warning_kg = doubleval($dr["WARNING_QTY"]);
		$data->normal = intval($dr["NORMAL"]);
	 	$data->normal_roll = intval($dr["NORMAL_ROLL"]);
	 	$data->normal_kg = doubleval($dr["NORMAL_QTY"]);


	 	$data->warningtime = $dr["WARNING_HRS"];
		$data->alerttime = $dr["ALERT_HRS"];
	}


	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($data)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/getBatchsAll', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$stepno = $_REQUEST['stepno'];
	$job = strtoupper($_REQUEST['job']);
	$itemcode = strtoupper($_REQUEST['itemcode']);
	$so = strtoupper($_REQUEST['so']);
	$machine = strtoupper($_REQUEST['machine']);
	$batch = strtoupper($_REQUEST['batch']);
	$customer = strtoupper($_REQUEST['customer']);
	$vi = $_REQUEST['vi'];

	$page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

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
		$filter_job = " AND UPPER(JOB_NO) like '%$job%' ";
	}


	$filter_item = "";
	if($itemcode=='' ){
		$filter_item = ' ';
	}else{
		$filter_item = " AND UPPER(ITEM_CODE) like '%$itemcode%' ";
	}

	$filter_so = "";
	if($so=='' ){
		$filter_so = ' ';
	}else{
		$filter_so = " AND UPPER(SO_NO) like '%$so%' ";
	}

	$filter_machine = "";
	if($machine=='' ){
		$filter_machine = ' ';
	}else{
		$filter_machine = " AND UPPER(MACHINE_NO) like '%$machine%' ";
	}

	$filter_batch = "";
	if($batch=='' ){
		$filter_batch = ' ';
	}else{
		$filter_batch = " AND UPPER(BATCH_NO) like '%$batch%' ";
	}

	$filter_customer = "";
	if($customer=='' ){
		$filter_customer = ' ';
	}else{
		$filter_customer = " AND UPPER(CUSTOMER_ID) = '$customer' ";
	}

	$filter_vi = "";
	if($vi=='Yes'){
		$filter_vi = " AND VI = 'Y' ";
	}
	if($vi=='No'){
		$filter_vi = " AND VI = 'N' ";
	}


	require './connect_demo.php';

	$sql = "SELECT  RANK() OVER(ORDER BY BATCH_START, JOB_NO, BATCH_NO) NEW_RNK, M.*
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$ou'
			$filter_step
			$filter_job
			$filter_item
			$filter_so
			$filter_machine
			$filter_batch
			$filter_customer
			$filter_vi
			";


	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);


	$max2 = ($page*20);
	$max1 = ($page*20)-10;
	$min = ($page*20)-20;

	$data = new stdClass();

	$resultArray1 = array();
	$resultArray2 = array();

	$row = 1;

	while ($dr = oci_fetch_assoc($query)) {

		if(intval($dr["NEW_RNK"]) > $min && intval($dr["NEW_RNK"])<=$max2){

			if(intval($dr["NEW_RNK"]) > $min && intval($dr["NEW_RNK"])<=$max1){
		 		array_push($resultArray1, $dr);
		 	}
		 	 elseif (intval($dr["NEW_RNK"])>$max1 && intval($dr["NEW_RNK"])<=$max2) {
		 	 	array_push($resultArray2, $dr);
		 	}
		}
		$row++;
	}

	$resultArray = array();
	
	array_push($resultArray, $resultArray1);
	array_push($resultArray, $resultArray2);

	$obj = new stdClass();
	$obj->totalPage =  ceil($row/20);
	$obj->totalRow =  $row;
	$obj->batchs = $resultArray;
	// $obj->sql = $sql;


	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/getBatchsMobile', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$ou = $_REQUEST['ou'];
	$stepno = $_REQUEST['stepno'];
	$job = strtoupper($_REQUEST['job']);
	$itemcode = strtoupper($_REQUEST['itemcode']);
	$so = strtoupper($_REQUEST['so']);
	$machine = strtoupper($_REQUEST['machine']);
	$batch = strtoupper($_REQUEST['batch']);
	$customer = strtoupper($_REQUEST['customer']);
	$vi = $_REQUEST['vi'];


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
		$filter_job = " AND UPPER(JOB_NO) like '%$job%' ";
	}


	$filter_item = "";
	if($itemcode=='' ){
		$filter_item = ' ';
	}else{
		$filter_item = " AND UPPER(ITEM_CODE) like '%$itemcode%' ";
	}

	$filter_so = "";
	if($so=='' ){
		$filter_so = ' ';
	}else{
		$filter_so = " AND UPPER(SO_NO) like '%$so%' ";
	}

	$filter_machine = "";
	if($machine=='' ){
		$filter_machine = ' ';
	}else{
		$filter_machine = " AND UPPER(MACHINE_NO) like '%$machine%' ";
	}

	$filter_batch = "";
	if($batch=='' ){
		$filter_batch = ' ';
	}else{
		$filter_batch = " AND UPPER(BATCH_NO) like '%$batch%' ";
	}

	$filter_customer = "";
	if($customer=='' ){
		$filter_customer = ' ';
	}else{
		$filter_customer = " AND UPPER(CUSTOMER_ID) = '$customer' ";
	}

	$filter_vi = "";
	if($vi=='Yes'){
		$filter_vi = " AND VI = 'Y' ";
	}
	if($vi=='No'){
		$filter_vi = " AND VI = 'N' ";
	}

	require './connect_demo.php';

	
	$filter_item = "";
	if($itemcode=='' ){
		$filter_item = ' ';
	}else{
		$filter_item = " AND UPPER(ITEM_CODE) like '%$itemcode%' ";
	}


	$resultArray1 = array();
	$resultArray2 = array();
	$resultArray3 = array();

	$sqlAlert = "SELECT  RANK() OVER(ORDER BY BATCH_START, JOB_NO, BATCH_NO) NEW_RNK, M.*
				FROM CONTROL_PREPARE M
				WHERE UPPER(ICON_COLOR) = 'RED'
				AND OU_CODE = '$ou'
				$filter_step
				$filter_job
				$filter_item
				$filter_so
				$filter_machine
				$filter_batch
				$filter_customer
				$filter_vi
			";


	$queryAlert = oci_parse($conn_omnoi, $sqlAlert);

	oci_execute($queryAlert);

	while ($dr = oci_fetch_assoc($queryAlert)) {
		array_push($resultArray1, $dr);
	}
	

	$sqlWarning = "SELECT  RANK() OVER(ORDER BY BATCH_START, JOB_NO, BATCH_NO) NEW_RNK, M.*
				FROM CONTROL_PREPARE M
				WHERE UPPER(ICON_COLOR) = 'YELLOW'
				AND OU_CODE = '$ou'
				$filter_step
				$filter_job
				$filter_item
				$filter_so
				$filter_machine
				$filter_batch
				$filter_customer
				$filter_vi
			";


	$queryWarning = oci_parse($conn_omnoi, $sqlWarning);

	oci_execute($queryWarning);

	while ($dr = oci_fetch_assoc($queryWarning)) {
		array_push($resultArray2, $dr);
	}

	$sqlNormal = "SELECT  RANK() OVER(ORDER BY BATCH_START, JOB_NO, BATCH_NO) NEW_RNK, M.*
				FROM CONTROL_PREPARE M
				WHERE UPPER(ICON_COLOR) like '%GREEN%'
				AND OU_CODE = '$ou'
				$filter_step
				$filter_job
				$filter_item
				$filter_so
				$filter_machine
				$filter_batch
				$filter_customer
				$filter_vi
			";


	$queryNormal = oci_parse($conn_omnoi, $sqlNormal);

	oci_execute($queryNormal);

	while ($dr = oci_fetch_assoc($queryNormal)) {
		array_push($resultArray3, $dr);
	}
	

	

	$obj = new stdClass();
	$obj->alerts = $resultArray1;
	$obj->warnings = $resultArray2;
	$obj->normals = $resultArray3;


	oci_close($conn_omnoi);

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
			OR  ST.STEP_NO IN ('P02')
			ORDER BY STEP_NAME";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$rows = array();
    $nrows = oci_fetch_all($query, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW); //row array

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($rows)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/master_item', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	$sql = "SELECT DISTINCT ITEM_CODE
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$OU'
			ORDER BY 1";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr["ITEM_CODE"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_job', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	$sql = "SELECT DISTINCT JOB_NO
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$OU'
			ORDER BY 1";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr["JOB_NO"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_batch', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	$sql = "SELECT DISTINCT BATCH_NO
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$OU'
			ORDER BY 1";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr["BATCH_NO"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_machine', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	$sql = "SELECT DISTINCT MACHINE_NO
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$OU'
			ORDER BY 1";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr["MACHINE_NO"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_so', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	$sql = "SELECT DISTINCT SO_NO
			FROM CONTROL_PREPARE M
			WHERE OU_CODE = '$OU'
			ORDER BY 1";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {
		array_push($resultArray, $dr["SO_NO"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->post('/master_customer', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$OU = $_REQUEST['ou'];

	require './connect_demo.php';

	$sql = "SELECT  distinct CUSTOMER_NAME LABEL, CUSTOMER_ID VALUE 
			FROM CONTROL_PREPARE
			WHERE OU_CODE = '$OU'
			ORDER BY CUSTOMER_NAME";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$rows = array();
    $nrows = oci_fetch_all($query, $rows, null, null, OCI_FETCHSTATEMENT_BY_ROW); //row array

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($rows)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});



$app->run(); // สั่งให้ระบบทำงาน

?>