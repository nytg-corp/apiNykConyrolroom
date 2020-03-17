
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


	 $sql = "SELECT * FROM CONTROL_HIS_DRY";




	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$resultArray = array();

	while ($dr = oci_fetch_assoc($query)) {

		array_push($resultArray, $dr);

	}



	oci_close($conn_omnoi);

	


	$file = fopen("history.csv","w");

	foreach ($resultArray as $line) {
	  fputcsv($file, $line);
	}

	fclose($file);

	echo "<pre>result</pre>";








?>