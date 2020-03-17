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

	$OU = $_REQUEST['ou'];
	require './connect_demo.php';

	$sql = "SELECT COUNT(*) TOTAL, SUM(TOTAL_ROLL) TOTAL_ROLL, SUM(TOTAL_QTY) TOTAL_QTY 
			,SUM(CASE WHEN ICON_COLOR = 'red-13' THEN 1 ELSE 0 END) ALERT
			,SUM(CASE WHEN ICON_COLOR = 'red-13' THEN TOTAL_ROLL ELSE 0 END) ALERT_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'red-13' THEN TOTAL_QTY ELSE 0 END) ALERT_QTY
			,SUM(CASE WHEN ICON_COLOR = 'yellow-13' THEN 1 ELSE 0 END) WARNING
			,SUM(CASE WHEN ICON_COLOR = 'yellow-13' THEN TOTAL_ROLL ELSE 0 END) WARNING_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'yellow-13' THEN TOTAL_QTY ELSE 0 END) WARNING_QTY
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN 1 ELSE 0 END) NORMAL
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN TOTAL_ROLL ELSE 0 END) NORMAL_ROLL
			,SUM(CASE WHEN ICON_COLOR = 'light-green-13' THEN TOTAL_QTY ELSE 0 END) NORMAL_QTY
			,MAX(WARNINGTIME) WARNING_HRS
			,MAX(ALERTTIME) ALERT_HRS
			FROM CONTROL_PRE_WAREHOUSE M
			WHERE OU_CODE = '$OU'";



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
	$itemcode = strtoupper($_REQUEST['itemcode']);
	$so = strtoupper($_REQUEST['so']);
	$batch = strtoupper($_REQUEST['batch']);
	$customer = strtoupper($_REQUEST['customer']);
	$color = strtoupper($_REQUEST['color']);
	$vi = $_REQUEST['vi'];
	$whstatus = $_REQUEST['whstatus'];

	$page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;

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

	$filter_color = "";
	if($color=='' ){
		$filter_color = ' ';
	}else{
		$filter_color = " AND UPPER(COLOR_CODE) like '%$color%' ";
	}


	$filter_status = "";
	if($whstatus == 'N'){
		$filter_status = "and STS_NORMAL > 0 and STS_NORMAL_DATE is null ";
	}elseif($whstatus == 'I'){
		$filter_status = "and ((STS_INACTIVE > 0 and STS_INACTIVE_DATE is null) OR (STS_SCRAP > 0 and STS_SCRAP_DATE is null))";
	}


	require './connect_demo.php';

	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
			FROM CONTROL_PRE_WAREHOUSE M
			WHERE OU_CODE = '$ou' 
			$filter_item 
			$filter_so
			$filter_batch
			$filter_customer
			$filter_vi
			$filter_color
			$filter_status
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
	$obj->sql = $sql;


	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/export', function (Request $request, Response $response) {

	$ou = $_REQUEST['ou'];
	$itemcode = strtoupper($_REQUEST['itemcode']);
	$so = strtoupper($_REQUEST['so']);
	$batch = strtoupper($_REQUEST['batch']);
	$customer = strtoupper($_REQUEST['customer']);
	$color = strtoupper($_REQUEST['color']);
	$vi = $_REQUEST['vi'];
	$whstatus = $_REQUEST['whstatus'];

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

	$filter_color = "";
	if($color=='' ){
		$filter_color = ' ';
	}else{
		$filter_color = " AND UPPER(COLOR_CODE) like '%$color%' ";
	}


	$filter_status = "";
	if($whstatus == 'N'){
		$filter_status = "and STS_NORMAL > 0 and STS_NORMAL_DATE is null ";
	}elseif($whstatus == 'I'){
		$filter_status = "and ((STS_INACTIVE > 0 and STS_INACTIVE_DATE is null) OR (STS_SCRAP > 0 and STS_SCRAP_DATE is null))";
	}


	require './connect_demo.php';

	$sql = "SELECT  RANK() OVER(ORDER BY TIME_AGING DESC, BATCH_NO) NEW_RNK, M.*
			FROM CONTROL_PRE_WAREHOUSE M
			WHERE OU_CODE = '$ou' 
			$filter_item 
			$filter_so
			$filter_batch
			$filter_customer
			$filter_vi
			$filter_color
			$filter_status
			";

			
	$resultArray = array();

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
		
         array_push($resultArray,$data);
	}
	

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($resultArray)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});


$app->post('/chart', function (Request $request, Response $response) { // สร้าง route ขึ้นมารองรับการเข้าถึง url

     $dts = $request->getParam('dts');
     $dte = $request->getParam('dte');
     $ou = $request->getParam('ou');
     

    require './connect_demo.php';

    $ChartA = array();
	$ChartB = array();
	$ChartC = array();
	$ChartD = array();

	$Header = array('Date', '<6', '6-10', '>10');

	array_push($ChartA,$Header);
	array_push($ChartB,$Header);
	array_push($ChartC,$Header);
	array_push($ChartD,$Header);


			$sqlA = "SELECT DTE, SHIFT
			,COUNT(BATCH_NO) BATCH_NO
			,SUM(G1_BATCH) G1_BATCH
			,SUM(G2_BATCH) G2_BATCH
			,SUM(G3_BATCH) G3_BATCH
			,ROUND(SUM(G1_BATCH)*100/COUNT(BATCH_NO),2) G1_BATCH_P
			,ROUND(SUM(G2_BATCH)*100/COUNT(BATCH_NO),2) G2_BATCH_P
			,ROUND(SUM(G3_BATCH)*100/COUNT(BATCH_NO),2) G3_BATCH_P
			,SUM(TOTAL_QTY) TOTAL_QTY
			,SUM(G1_QTY) G1_QTY
			,SUM(G2_QTY) G2_QTY
			,SUM(G3_QTY) G3_QTY
			,ROUND(SUM(G1_QTY)*100/SUM(TOTAL_QTY),2) G1_QTY_P
			,ROUND(SUM(G2_QTY)*100/SUM(TOTAL_QTY),2) G2_QTY_P
			,ROUND(SUM(G3_QTY)*100/SUM(TOTAL_QTY),2) G3_QTY_P
			FROM (
			SELECT TO_CHAR(CREATE_DATE,'DD-MM') DTE, 'A' AS SHIFT, BATCH_NO, TOTAL_QTY,
			CASE WHEN TIME_AGING <= 6 THEN TOTAL_QTY ELSE 0 END G1_QTY,
			CASE WHEN TIME_AGING > 6 AND  TIME_AGING <= 10 THEN TOTAL_QTY ELSE 0 END G2_QTY,
			CASE WHEN TIME_AGING > 10 THEN TOTAL_QTY ELSE 0 END G3_QTY,
			CASE WHEN TIME_AGING <= 6 THEN 1 ELSE 0 END G1_BATCH,
			CASE WHEN TIME_AGING > 6 AND  TIME_AGING <= 10 THEN 1 ELSE 0 END G2_BATCH,
			CASE WHEN TIME_AGING > 10 THEN 1 ELSE 0 END G3_BATCH
			FROM CONTROL_HIS_PRE_WAREHOUSE
			WHERE TO_CHAR(CREATE_DATE,'HH24') = '07' 
			AND TO_CHAR(CREATE_DATE,'YYYY/MM/DD') >= '$dts'
			AND TO_CHAR(CREATE_DATE,'YYYY/MM/DD') <= '$dte'
			AND OU_CODE = '$ou')
			GROUP BY DTE, SHIFT
			ORDER BY DTE ";


	$queryA = oci_parse($conn_omnoi, $sqlA);

	oci_execute($queryA);

	while ($dr = oci_fetch_assoc($queryA)) {

		$data = array($dr["DTE"], doubleval($dr["G1_BATCH_P"]), doubleval($dr["G2_BATCH_P"]), doubleval($dr["G3_BATCH_P"]));
		array_push($ChartA,$data);

		$data1 = array($dr["DTE"], doubleval($dr["G1_QTY_P"]), doubleval($dr["G2_QTY_P"]), doubleval($dr["G3_QTY_P"]));
		array_push($ChartC,$data1);

	}



		$sqlB = "SELECT DTE, SHIFT
			,COUNT(BATCH_NO) BATCH_NO
			,SUM(G1_BATCH) G1_BATCH
			,SUM(G2_BATCH) G2_BATCH
			,SUM(G3_BATCH) G3_BATCH
			,ROUND(SUM(G1_BATCH)*100/COUNT(BATCH_NO),2) G1_BATCH_P
			,ROUND(SUM(G2_BATCH)*100/COUNT(BATCH_NO),2) G2_BATCH_P
			,ROUND(SUM(G3_BATCH)*100/COUNT(BATCH_NO),2) G3_BATCH_P
			,SUM(TOTAL_QTY) TOTAL_QTY
			,SUM(G1_QTY) G1_QTY
			,SUM(G2_QTY) G2_QTY
			,SUM(G3_QTY) G3_QTY
			,ROUND(SUM(G1_QTY)*100/SUM(TOTAL_QTY),2) G1_QTY_P
			,ROUND(SUM(G2_QTY)*100/SUM(TOTAL_QTY),2) G2_QTY_P
			,ROUND(SUM(G3_QTY)*100/SUM(TOTAL_QTY),2) G3_QTY_P
			FROM (
			SELECT TO_CHAR(CREATE_DATE,'DD-MM') DTE, 'B' AS SHIFT, BATCH_NO, TOTAL_QTY,
			CASE WHEN TIME_AGING <= 6 THEN TOTAL_QTY ELSE 0 END G1_QTY,
			CASE WHEN TIME_AGING > 6 AND  TIME_AGING <= 10 THEN TOTAL_QTY ELSE 0 END G2_QTY,
			CASE WHEN TIME_AGING > 10 THEN TOTAL_QTY ELSE 0 END G3_QTY,
			CASE WHEN TIME_AGING <= 6 THEN 1 ELSE 0 END G1_BATCH,
			CASE WHEN TIME_AGING > 6 AND  TIME_AGING <= 10 THEN 1 ELSE 0 END G2_BATCH,
			CASE WHEN TIME_AGING > 10 THEN 1 ELSE 0 END G3_BATCH
			FROM CONTROL_HIS_PRE_WAREHOUSE
			WHERE TO_CHAR(CREATE_DATE,'HH24') = '19' 
			AND TO_CHAR(CREATE_DATE,'YYYY/MM/DD') >= '$dts'
			AND TO_CHAR(CREATE_DATE,'YYYY/MM/DD') <= '$dte'
			AND OU_CODE = '$ou')
			GROUP BY DTE, SHIFT
			ORDER BY DTE ";


	$queryB = oci_parse($conn_omnoi, $sqlB);

	oci_execute($queryB);

	while ($dr = oci_fetch_assoc($queryB)) {

		$data = array($dr["DTE"], doubleval($dr["G1_BATCH_P"]), doubleval($dr["G2_BATCH_P"]), doubleval($dr["G3_BATCH_P"]));
		array_push($ChartB,$data);

		$data1 = array($dr["DTE"], doubleval($dr["G1_QTY_P"]), doubleval($dr["G2_QTY_P"]), doubleval($dr["G3_QTY_P"]));
		array_push($ChartD,$data1);

	}




	oci_close($conn_omnoi);

	$data = new stdClass();
	$data->shiftA = $ChartA;
	$data->shiftB = $ChartB;
	$data->shiftC = $ChartC;
	$data->shiftD = $ChartD;

    $response->getBody()->write(json_encode($data)); // สร้างคำตอบกลับ

    return $response; // ส่งคำตอบกลับ
});


$app->run(); // สั่งให้ระบบทำงาน

?>