<?php
  ini_set('display_errors', 1);
  error_reporting(~0);

  $dbstr_omnoi ="(DESCRIPTION=
    (ADDRESS=
      (PROTOCOL=TCP)
      (HOST=172.16.6.76)
      (PORT=1521)
    )
    (CONNECT_DATA=
      (SERVER=dedicated)
      (SERVICE_NAME=NYTG)
    )
  )";

  //$conn = oci_connect("WEBCONTROL", "WEBCONTROL", "BISHO.WORLD",'AL32UTF8');
  
  $conn_omnoi = oci_connect("DEMO", "DEMO", $dbstr_omnoi,'UTF8'); //AL32UTF8


  

?>