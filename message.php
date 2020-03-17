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

$app->post('/message_list', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	//$keys = $_REQUEST['keys'];
	$process = $_REQUEST['process'];
	$ou = $_REQUEST['ou'];

	require './connect_demo.php';

	$sql = "SELECT M.*, TO_CHAR(SEND_TIME,'DD-MM-YYYY HH24:MI') SEND_TIME_TXT
			FROM CONTROL_MSG_ALERT M, DFBT_HEADER BH
			WHERE PROCESS_NAME = '$process'
			AND M.OU_CODE = '$ou'
			AND M.OU_CODE = BH.OU_CODE
			AND M.BATCH_NO = BH.BATCH_NO
			AND BH.STATUS NOT IN (8,9)
			AND RESPONSE_MSG IS NULL 
			ORDER BY SEND_TIME ";

	$obj = new stdClass();

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
	$process = $_REQUEST['process'];
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
              AND BD.OU_CODE = CT.OU_CODE
              AND BD.BATCH_NO = CT.BATCH_NO
              AND CT.KEY_ID = '$keyid'
              AND BD.OU_CODE = '$ou'
              AND BD.BATCH_NO = '$batch'";

              //AND BH.STATUS NOT IN (8,9)
              //AND BD.DYE_EDATE IS NOT NULL

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

$app->post('/listmsg', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$keys = $_REQUEST['keys'];
	$process = $_REQUEST['process'];
	$uid = $_REQUEST['uid'];

	require './connect_demo.php';

	// $sql = "SELECT M.*, TO_CHAR(SEND_TIME,'DD-MM-YYYY HH24:MI:SS') SEND_TIME_TXT
	// 		, TO_CHAR(RESPONSE_TIME,'DD-MM-YYYY HH24:MI:SS') RESPONSE_TIME_TXT,
	// 		NVL((SELECT KEY_ID FROM CONTROL_MSG_ALERT_VIEW D WHERE D.KEY_ID = M.KEY_ID AND D.USERID = '$uid'),0) USERVIEW
	// 		FROM CONTROL_MSG_ALERT M
	// 		WHERE PROCESS_NAME = '$process'
	// 		AND KEYS = '$keys'
	//           ORDER BY SEND_TIME ";

	$obj = new stdClass();

	$sqlAlert = "SELECT KEYS , COUNT(*) CNT_ALERT
								FROM CONTROL_MSG_ALERT 
								WHERE KEYS = '$keys' 
								AND PROCESS_NAME = '$process'
								GROUP BY KEYS";

					$queryAlert = oci_parse($conn_omnoi, $sqlAlert);
					oci_execute($queryAlert);

					while ($dr = oci_fetch_assoc($queryAlert)) {
						$obj->keys = $keys;
						$obj->alert = intval($dr["CNT_ALERT"]);
					}

	
	$sql = "SELECT KEYS, SUM(CNT_RESPONSE) CNT_RESPONSE, SUM(CNT_VIEW) CNT_VIEW
					FROM (
					SELECT M.KEYS, 1 CNT_RESPONSE, CASE WHEN NVL(D.USERID,'X') = 'X' THEN 0 ELSE 1 END CNT_VIEW
					FROM CONTROL_MSG_RESPONSE M, (SELECT D.RESPONSE_ID, D.USERID FROM CONTROL_MSG_ALERT_VIEW D WHERE USERID='$uid') D
					WHERE M.RESPONSE_ID = D.RESPONSE_ID(+)
					AND M.KEYS = '$keys'
					AND M.PROCESS_NAME = '$process'
					AND M.ACTIVE = 'Y'
					) GROUP BY KEYS";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	//$resultArray = array();

	
	while ($dr = oci_fetch_assoc($query)) {
		$obj->response = intval($dr["CNT_RESPONSE"]);
		$obj->view = intval($dr["CNT_VIEW"]);
	}

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});


$app->post('/listchat', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	$keys = $_REQUEST['keys'];
	$process = $_REQUEST['process'];

	require './connect_demo.php';

	$sql = "SELECT M.*, TO_CHAR(SEND_TIME,'DD-MON-YY HH24:MI') SEND_TIME_TEXT FROM (
					SELECT SEND_TIME, SEND_MSG, 'CONTROL ROOM' SENT_BY, 1 SENT, './avataaars.png' AVARTAR
					FROM CONTROL_MSG_ALERT
					WHERE KEYS = '$keys'
					AND PROCESS_NAME = '$process'
					UNION ALL
					SELECT M.RESPONSE_TIME, M.RESPONSE_MSG,  RESPONSE_BY SENT_BY, null SENT, './avataaars2.png' AVARTAR
					FROM CONTROL_MSG_RESPONSE M
					WHERE KEYS = '$keys'
					AND PROCESS_NAME = '$process'
					AND ACTIVE = 'Y') M
					ORDER BY SEND_TIME";

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

$app->post('/sendmsg', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$ou = $_REQUEST['ou'];
	$batch = $_REQUEST['batch'];
	$process = $_REQUEST['process'];
	$msg = $_REQUEST['msg'];
	$keys = $_REQUEST['keys'];

	$keyid = '';
	$sqlRunning = "select CONTROL_MSG.nextval RUNNING FROM DUAL";
	$queryRunning = oci_parse($conn_omnoi, $sqlRunning);
	oci_execute($queryRunning);

	while ($dr = oci_fetch_assoc($queryRunning)) {
		$keyid = $dr['RUNNING'];
	}

	$sql = "INSERT INTO CONTROL_MSG_ALERT (KEY_ID, OU_CODE, BATCH_NO, PROCESS_NAME, SEND_TIME, SEND_MSG, KEYS) VALUES
    ('$keyid', '$ou','$batch','$process',SYSDATE,'$msg','$keys')";

	$query = oci_parse($conn_omnoi, $sql);
	oci_execute($query);

	$obj = new stdClass();
	$obj->success = oci_num_rows($query);
	$obj->running = $keyid;

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});

$app->post('/sendresponse', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$keyid = $_REQUEST['keyid'];
	$responsemsg = $_REQUEST['responsemsg'];

	$sql = "UPDATE CONTROL_MSG_ALERT SET
		RESPONSE_MSG = '$responsemsg',
		RESPONSE_TIME = SYSDATE
    WHERE KEY_ID = '$keyid' ";

	$query = oci_parse($conn_omnoi, $sql);
	oci_execute($query);

	$obj = new stdClass();
	$obj->success = oci_num_rows($query);

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});


$app->post('/sendresponse2', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$keys = $_REQUEST['keys'];
	$responsemsg = $_REQUEST['responsemsg'];
	$responseby = $_REQUEST['responseby'];
	$process = $_REQUEST['process'];


	$sql = "INSERT INTO CONTROL_MSG_RESPONSE (KEYS, PROCESS_NAME, RESPONSE_TIME, RESPONSE_BY, RESPONSE_MSG, ACTIVE) VALUES 
		( '$keys', '$process', SYSDATE, '$responseby', '$responsemsg', 'Y')";

	$query = oci_parse($conn_omnoi, $sql);
	oci_execute($query);

	$obj = new stdClass();
	$obj->success = oci_num_rows($query);

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});

$app->post('/testline', function (Request $request, Response $response) {
	
	$process = $_REQUEST['process'];
	$sToken = '';


	if($process=='DRY'){
		$sToken = "WZrahpGoOrrNS2vK0zgmIPuaUZm4Wom9QCtigCOb3l9";
	}elseif($process=='FINISHING'){
		$sToken = "n6WILjelB0pAS2mDCfUy8ugqWPXa3YyuJWpTvEggeXP";
	}elseif($process=='INSPECTION'){
		$sToken = "jZkaU9MHEFA6Ze3Upm5EmDM9V7pu3oIhWb5Okk2YgWd";
	}elseif($process=='FG'){
		$sToken = "j4tg75jP3LmKTqwSAlvfJP4I6fFghXUfOg0mniciVPI";
	}
	
	$chOne = curl_init();
	curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $chOne, CURLOPT_POST, 1);
	curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$process);
	$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
	curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec( $chOne );


});


$app->post('/sendline', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$ou = $_REQUEST['ou'];
	$process = $_REQUEST['process'];
	$key = $_REQUEST['key'];
	$batch = $_REQUEST['batch'];
	$keyid = $_REQUEST['keyid'];
	$item = $_REQUEST['item'];
	$color = $_REQUEST['color'];
	$color_desc = $_REQUEST['color_desc'];
	$roll = $_REQUEST['roll'];
	$qty = $_REQUEST['qty'];
	$dyefinish = $_REQUEST['dyefinish'];
	$msg = $_REQUEST['msg'];

  $sMessage = "Batch : $key
	Item : $item
	Color : $color
	Color Desc. : $color_desc
	$roll Roll(s) / $qty KG(s)
	Dye Finish : $dyefinish
	$msg
	";
	$sToken = '';
	if($ou=='D03'){

		if($process=='DRY'){
			$sToken = "WZrahpGoOrrNS2vK0zgmIPuaUZm4Wom9QCtigCOb3l9";
		}elseif($process=='FINISHING'){
			$sToken = "n6WILjelB0pAS2mDCfUy8ugqWPXa3YyuJWpTvEggeXP";
		}elseif($process=='INSPECTION'){
			$sToken = "jZkaU9MHEFA6Ze3Upm5EmDM9V7pu3oIhWb5Okk2YgWd";
		}elseif($process=='FG'){
			$sToken = "j4tg75jP3LmKTqwSAlvfJP4I6fFghXUfOg0mniciVPI";
		}
		
	}
	//$sMessage = "มีรายการสั่งซื้อเข้าจ้า....";


	$chOne = curl_init();
	curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $chOne, CURLOPT_POST, 1);
	curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$sMessage);
	$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
	curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec( $chOne );

	//Result error
	if(curl_error($chOne))
	{
		echo 'error:' . curl_error($chOne);
	}
	else {
		$result_ = json_decode($result, true);
	}

	//$url = "http://nytg.nanyangtextile.com/controlroom/#/dryMsg/$keyid/$ou/$batch";
	$url = "http://nytg.nanyangtextile.com/controlroom/#/MessageBatch/$ou/$process/$batch/$keyid";
	// $url = "http://localhost:8080//controlroom/#/MessageBatch/$ou/$process/$batch/$keyid";

	curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $chOne, CURLOPT_POST, 1);
	curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$url);
	$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
	curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec( $chOne );



	curl_close( $chOne );

	$response->getBody()->write(json_encode($result_)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});

$app->post('/updateview', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url

	require './connect_demo.php';

	$keys = $_REQUEST['keys'];
	$uid = $_REQUEST['uid'];

	$sql = "INSERT INTO CONTROL_MSG_ALERT_VIEW
					SELECT M.RESPONSE_ID, '$uid' AS USER_ID
					FROM CONTROL_MSG_RESPONSE M
					WHERE KEYS = '$keys' 
					AND PROCESS_NAME = 'DRY'
					AND NOT EXISTS (SELECT * FROM CONTROL_MSG_ALERT_VIEW D
					WHERE M.RESPONSE_ID = D.RESPONSE_ID
					AND USERID = '$uid')";

	$query = oci_parse($conn_omnoi, $sql);
	oci_execute($query);

	$obj = new stdClass();
	$obj->success = oci_num_rows($query);

	oci_close($conn_omnoi);

	$response->getBody()->write(json_encode($sql)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ

});

$app->run(); // สั่งให้ระบบทำงาน

?>
