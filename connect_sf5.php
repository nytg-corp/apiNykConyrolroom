<?php
	ini_set('display_errors', 1);
	error_reporting(~0);

	$dbstr_sf5 ="(DESCRIPTION=
    (ADDRESS=
      (PROTOCOL=TCP)
      (HOST=172.16.6.74)
      (PORT=1521)
    )
    (CONNECT_DATA=
      (SERVER=dedicated)
      (SERVICE_NAME=NYTG)
    )
  )";

	//$conn = oci_connect("WEBCONTROL", "WEBCONTROL", "BISHO.WORLD",'AL32UTF8');

	$conn_sf5 = oci_connect("SF5", "OMSF5", $dbstr_sf5,'UTF8'); //AL32UTF8




?>
