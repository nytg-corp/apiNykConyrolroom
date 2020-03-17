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

$app->post('/itemspec', function (Request $request, Response $response) {
	// สร้าง route ขึ้นมารองรับการเข้าถึง url
	require './connect_sf5.php';
	$item = $_REQUEST['item'];
	$itemA0 = substr($item, 0, strlen($item) - 2);

	$sql = "SELECT ITEM_CODE, SUBSTR(ITEM_CODE,1,1) ITEM_TYPE, MAX(O_FN_OPEN) O_FN_OPEN , MAX(O_FN_GM) O_FN_GM, MAX(O_FN_TUBULAR) O_FN_TUBULAR
					FROM FMIT_ITEM
					WHERE ITEM_CODE = '".$itemA0."A0' OR  ITEM_CODE = '$item'
					GROUP BY ITEM_CODE, SUBSTR(ITEM_CODE,1,1)";

	$query = oci_parse($conn_sf5, $sql);

	oci_execute($query);

	$resultArray = array();

	$obj = new stdClass();
	while ($dr = oci_fetch_assoc($query)) {
		$obj->ITEM_CODE = $item;
		//$obj->ITEM_CODE1 = $itemA0;
		$obj->O_FN_GM = $dr["O_FN_GM"];
		$obj->O_FN_OPEN = $dr["O_FN_OPEN"];
		$obj->O_FN_TUBULAR = $dr["O_FN_TUBULAR"];
	$obj->ITEM_TYPE= $dr["ITEM_TYPE"];
	}

	oci_close($conn_sf5);

	$response->getBody()->write(json_encode($obj)); // สร้างคำตอบกลับ

	return $response; // ส่งคำตอบกลับ
});

$app->run(); // สั่งให้ระบบทำงาน

?>
