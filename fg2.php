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


	$sql = "SELECT COUNT(KEY) TOTAL
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $alert THEN 1 ELSE 0 END) STS_ALERT
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - END_DATE) * (24),2) < $alert THEN 1 ELSE 0 END) STS_WARNING
		,SUM(TOTAL_QTY) TOTAL_KGS
		,SUM(TOTAL_ROLL) TOTAL_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $alert THEN TOTAL_ROLL ELSE 0 END) ALERT_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - END_DATE) * (24),2) < $alert THEN TOTAL_ROLL ELSE 0 END) WARNING_ROLLS
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $alert THEN TOTAL_QTY ELSE 0 END) ALERT_KGS
		,SUM(CASE WHEN ROUND((SYSDATE - END_DATE) * (24),2) >= $warning AND ROUND((SYSDATE - END_DATE) * (24),2) < $alert THEN TOTAL_QTY ELSE 0 END) WARNING_KGS
FROM (
SELECT DISTINCT KEY, OU_CODE, BATCH_NO, ITEM_CODE, END_DATE
,ROUND((SYSDATE - END_DATE) * (24),2) AS TIME_AGING
,TIMESTAMP_DIFF(END_DATE, SYSDATE) AS	TIMESPAN
,TO_CHAR(END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
              ,(select TOTAL_ROLL from DFIT_BTDATA b where b.BATCH_NO = J.BATCH_NO and b.OU_CODE = J.OU_CODE) TOTAL_ROLL
              ,(select TOTAL_QTY from DFIT_BTDATA b where b.BATCH_NO = J.BATCH_NO and b.OU_CODE = J.OU_CODE) TOTAL_QTY
FROM (
SELECT DISTINCT M.OU_CODE, M.BATCH_NO,ITEM_CODE,
(SELECT MAX(END_DATE) FROM DFBT_MONITOR MO WHERE MO.OU_CODE = M.OU_CODE AND MO.BATCH_NO = M.BATCH_NO) END_DATE
,OU_CODE || '-' || BATCH_NO AS KEY
FROM (
SELECT H.OU_CODE, H.BATCH_NO, B.ITEM_CODE,
CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END STOCK_TYPE,
    TRUNC(H.SHIPMENT_DATE) SHIPMENT_DATE, SUM(D.QTY) QTY
 FROM DFPL_HEADER H , DFPL_DETAIL D, DFIT_BTDATA B
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
                AND H.OU_CODE='$ou'
                AND H.OU_CODE=B.OU_CODE
                AND H.BATCH_NO = B.BATCH_NO
                AND B.ITEM_CODE NOT LIKE 'C%'
                AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = H.OU_CODE AND BH.BATCH_NO = H.BATCH_NO AND BH.STATUS =2)
    GROUP BY H.OU_CODE, H.BATCH_NO, B.ITEM_CODE, CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END, TRUNC(H.SHIPMENT_DATE)
    ORDER BY H.OU_CODE, H.BATCH_NO) M
    WHERE NOT EXISTS (
    SELECT * FROM 
    (SELECT OU_CODE, BATCH_NO, STOCK_TYPE
FROM (
SELECT H.OU_CODE, H.BATCH_NO, 
CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END STOCK_TYPE,
    TRUNC(H.SHIPMENT_DATE) SHIPMENT_DATE, SUM(D.QTY) QTY
 FROM DFPL_HEADER H , DFPL_DETAIL D, DFIT_BTDATA B
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
                AND H.OU_CODE='$ou'
                 AND H.OU_CODE=B.OU_CODE
                AND H.BATCH_NO = B.BATCH_NO
                AND B.ITEM_CODE NOT LIKE 'C%'
                AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = H.OU_CODE AND BH.BATCH_NO = H.BATCH_NO AND BH.STATUS =2)
    GROUP BY H.OU_CODE, H.BATCH_NO, CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END, TRUNC(H.SHIPMENT_DATE)
    ORDER BY H.OU_CODE, H.BATCH_NO) M
    WHERE SHIPMENT_DATE IS NOT NULL) D
    WHERE M.OU_CODE = D.OU_CODE
    AND M.BATCH_NO = D.BATCH_NO
    AND M.STOCK_TYPE = D.STOCK_TYPE )) J)";


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


	$sql = "
SELECT KEY, OU_CODE, BATCH_NO, ITEM_CODE, END_DATE, RANK() OVER(ORDER BY END_DATE, BATCH_NO) RNK
,ROUND((SYSDATE - END_DATE) * (24),2) AS TIME_AGING
,TIMESTAMP_DIFF(END_DATE, SYSDATE) AS	TIMESPAN
,TO_CHAR(END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE
,CASE WHEN ((SYSDATE - END_DATE) * (24)) > $alert then 'red'
                WHEN ((SYSDATE - END_DATE) * (24)) > $warning then 'yellow'
                ELSE 'light-green-13' END ICON_COLOR	
                				 ,CASE WHEN ((SYSDATE - END_DATE) * (24)) > $alert then 'warning'
                WHEN ((SYSDATE - END_DATE) * (24)) > $warning then 'notification_important'
                ELSE 'flag' END ICON_NAME	
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
              ,'' as COLOR_CODE
              ,'' as COLOR_DESC
              ,'' as CUSTOMER_NAME
              , 0 as TOTAL_ROLL
              , 0 as TOTAL_QTY
              ,null STS_NORMAL, null STS_NORMAL_DTE, null STS_INACTIVE, null STS_INACTIVE_DTE, null STS_SCRAP, null STS_SCRAP_DTE
FROM (
SELECT DISTINCT M.OU_CODE, M.BATCH_NO,ITEM_CODE,
(SELECT MAX(END_DATE) FROM DFBT_MONITOR MO WHERE MO.OU_CODE = M.OU_CODE AND MO.BATCH_NO = M.BATCH_NO) END_DATE
,OU_CODE || '-' || BATCH_NO AS KEY
FROM (
SELECT H.OU_CODE, H.BATCH_NO, B.ITEM_CODE,
CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END STOCK_TYPE,
    TRUNC(H.SHIPMENT_DATE) SHIPMENT_DATE, SUM(D.QTY) QTY
 FROM DFPL_HEADER H , DFPL_DETAIL D, DFIT_BTDATA B
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
                AND H.OU_CODE='$ou'
                AND H.OU_CODE=B.OU_CODE
                AND H.BATCH_NO = B.BATCH_NO
                AND B.ITEM_CODE NOT LIKE 'C%'
                AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = H.OU_CODE AND BH.BATCH_NO = H.BATCH_NO AND BH.STATUS =2)
    GROUP BY H.OU_CODE, H.BATCH_NO, B.ITEM_CODE, CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END, TRUNC(H.SHIPMENT_DATE)
    ORDER BY H.OU_CODE, H.BATCH_NO) M
    WHERE NOT EXISTS (
    SELECT * FROM 
    (SELECT OU_CODE, BATCH_NO, STOCK_TYPE
FROM (
SELECT H.OU_CODE, H.BATCH_NO, 
CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END STOCK_TYPE,
    TRUNC(H.SHIPMENT_DATE) SHIPMENT_DATE, SUM(D.QTY) QTY
 FROM DFPL_HEADER H , DFPL_DETAIL D, DFIT_BTDATA B
				WHERE NVL(H.STATUS,'0') <>'9' 
				AND H.OU_CODE=D.OU_CODE
				AND H.PL_NO=D.PL_NO
                AND H.OU_CODE='$ou'
                 AND H.OU_CODE=B.OU_CODE
                AND H.BATCH_NO = B.BATCH_NO
                AND B.ITEM_CODE NOT LIKE 'C%'
                AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = H.OU_CODE AND BH.BATCH_NO = H.BATCH_NO AND BH.STATUS =2)
    GROUP BY H.OU_CODE, H.BATCH_NO, CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
    ELSE 'NORMAL' END, TRUNC(H.SHIPMENT_DATE)
    ORDER BY H.OU_CODE, H.BATCH_NO) M
    WHERE SHIPMENT_DATE IS NOT NULL) D
    WHERE M.OU_CODE = D.OU_CODE
    AND M.BATCH_NO = D.BATCH_NO
    AND M.STOCK_TYPE = D.STOCK_TYPE ))
	";


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


		$sql = "SELECT M.* 
				FROM DFIT_BTDATA M
				WHERE BATCH_NO = '$batchno'
				AND OU_CODE = '$oucode'";

		$query2 = oci_parse($conn_omnoi, $sql);
		oci_execute($query2);
		while ($dr2 = oci_fetch_assoc($query2)) {
			$dr["COLOR_CODE"] = $dr2["COLOR_CODE"];
			$dr["COLOR_DESC"] = $dr2["COLOR_DESC"];
			$dr["CUSTOMER_NAME"] = $dr2["CUSTOMER_NAME"];
			$dr["TOTAL_ROLL"] = $dr2["TOTAL_ROLL"];
			$dr["TOTAL_QTY"] = $dr2["TOTAL_QTY"];

		}



		$sql = "SELECT SUM(CASE WHEN STOCK_TYPE = 'NORMAL' THEN QTY ELSE 0 END) STS_NORMAL
				,MIN(CASE WHEN STOCK_TYPE = 'NORMAL' THEN SHIPMENT_DATE ELSE NULL END) STS_NORMAL_DTE
				,SUM(CASE WHEN STOCK_TYPE = 'INACTIVE' THEN QTY ELSE 0 END) STS_INACTIVE
				,MIN(CASE WHEN STOCK_TYPE = 'INACTIVE' THEN SHIPMENT_DATE ELSE NULL END) STS_INACTIVE_DTE
				,SUM(CASE WHEN STOCK_TYPE = 'SCRAP' THEN QTY ELSE 0 END) STS_SCRAP
				,MIN(CASE WHEN STOCK_TYPE = 'SCRAP' THEN SHIPMENT_DATE ELSE NULL END) STS_SCRAP_DTE
				FROM (
				SELECT H.OU_CODE, H.BATCH_NO, SUM(D.QTY) QTY,
				CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
				    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
				    ELSE 'NORMAL' END STOCK_TYPE,
				    MIN(TRUNC(H.SHIPMENT_DATE)) SHIPMENT_DATE
				 FROM DFPL_HEADER H , DFPL_DETAIL D
								WHERE NVL(H.STATUS,'0') <>'9' 
								AND H.OU_CODE = D.OU_CODE
								AND H.PL_NO = D.PL_NO
				                AND H.OU_CODE = '$oucode'
				                AND H.BATCH_NO = '$batchno'
				GROUP BY H.OU_CODE, H.BATCH_NO, CASE WHEN SUBSTR(H.PL_NO,1,3) = 'P1D' THEN 'INACTIVE'
				    WHEN SUBSTR(H.PL_NO,1,2) = 'P2' THEN 'SCRAP'
				    ELSE 'NORMAL' END)";
				    

			$query3 = oci_parse($conn_omnoi, $sql);
			oci_execute($query3);
			while ($dr3 = oci_fetch_assoc($query3)) {
				$dr["STS_NORMAL"] = floor($dr3["STS_NORMAL"]);
				$dr["STS_NORMAL_DTE"] = $dr3["STS_NORMAL_DTE"];

				if($dr3["STS_INACTIVE_DTE"]==null){
					$dr["STS_INACTIVE"] = floor($dr3["STS_INACTIVE"]);
					$dr["STS_INACTIVE_DTE"] = $dr3["STS_INACTIVE_DTE"];
				}else{
					$dr["STS_INACTIVE"] = 0;
					$dr["STS_INACTIVE_DTE"] = $dr3["STS_INACTIVE_DTE"];
				}

				if($dr3["STS_SCRAP_DTE"]==null){
					$dr["STS_SCRAP"] = floor($dr3["STS_SCRAP"]);
					$dr["STS_SCRAP_DTE"] = $dr3["STS_SCRAP_DTE"];
				}else{
					$dr["STS_SCRAP"] = 0;
					$dr["STS_SCRAP_DTE"] = $dr3["STS_SCRAP_DTE"];
				}
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



$app->run(); // สั่งให้ระบบทำงาน

?>