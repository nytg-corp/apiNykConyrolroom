<?php

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

	$conn_omnoi = oci_connect("DEMO", "DEMO", $dbstr_omnoi,'UTF8'); //AL32UTF8




    $sql= "INSERT INTO CONTROL_HIS_INSPECTION
    SELECT M.* FROM CONTROL_INSPECTION M ";          

//             

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

  $sToken = "kZIBSGI0IUWrnDR8VFCsSKu2wxWd7wYOGMxQbJuOj8s";

  // $sToken = "sPnLRGzBOCKqdDCRuUrcPQPqgABoV21UWfMG6BulykP";

  $chOne = curl_init();
  curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
  curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt( $chOne, CURLOPT_POST, 1);
  curl_setopt( $chOne, CURLOPT_POSTFIELDS, 'message='.'Interface History Inspection Control Room NYK');
  $headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
  curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
  curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
  $result = curl_exec( $chOne );

  curl_close($chOne);

  echo "<pre>$result</pre>";


  ?>