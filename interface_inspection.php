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

	$queryDelete = oci_parse($conn_omnoi, "delete from CONTROL_INSPECTION");

	oci_execute($queryDelete);

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'INSPECTION' AND OU_CODE = 'D03'";
	
	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);
	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]) / 60;
		$alert = intval($dr["ALERT_MINUTE"]) / 60;
	}


    $sql = "INSERT INTO CONTROL_INSPECTION
    SELECT BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY
	,TO_CHAR( CT.END_DATE,'DD-MM-YYYY HH24:MI')  END_LAST_STEP
              ,ROUND((SYSDATE - CT.END_DATE) * (24),2) AS TIME_AGING
              ,timestamp_diff(CT.END_DATE, sysdate) as	TIMESPAN
                ,ROUND((SYSDATE - BD.FABRIC_REC_BILL) * (24),2) AS FTIME_AGING
			  ,timestamp_diff(BD.FABRIC_REC_BILL,sysdate) AS FTIME
							,BD.SO_NO, BD.CUSTOMER_ID, BD.CUSTOMER_NAME
                             ,CASE WHEN SUBSTR(BD.CUSTOMER_ID,1,4) = '9999' THEN 'Y' ELSE 'N' END VI
			   ,CASE WHEN ((SYSDATE - CT.END_DATE) * (24)) > $alert then 'red-13'
                WHEN ((SYSDATE - CT.END_DATE) * (24)) > $warning then 'yellow-13'
                ELSE 'light-green-13' END ICON_COLOR	
                				 ,CASE WHEN ((SYSDATE - CT.END_DATE) * (24)) > $alert then 'warning'
                WHEN ((SYSDATE - CT.END_DATE) * (24)) > $warning then 'notification_important'
                ELSE 'flag' END ICON_NAME	
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
              ,'' as METHOD_NAME, LS.STEP_NO, LS_D.STEP_NAME, '' as START_DATE, '' as MACHINE_NO
              ,'' as COLOR_APPROVE, '' as COLOR_APPROVE_DATE,  '' as QT_APPROVE, '' as QT_APPROVE_DATE, SYSDATE
              FROM DFIT_BTDATA BD, DFBT_HEADER BH, CT_FIND_FINISH_EDATE CT, ( SELECT LS.* FROM CT_LAST_STEP LS WHERE STEP_RANK = 1) LS, DFMS_STEP LS_D
              WHERE BD.OU_CODE IN ('D02','D03','D06')
              AND BD.OU_CODE = BH.OU_CODE
              AND BD.BATCH_NO = BH.BATCH_NO
              AND BD.OU_CODE = CT.OU_CODE
              AND BD.BATCH_NO = CT.BATCH_NO
              AND BD.OU_CODE = LS.OU_CODE(+)
			AND BD.BATCH_NO = LS.BATCH_NO(+)
			AND LS.STEP_NO = LS_D.STEP_NO(+)
              AND BH.STATUS < 8
              AND BD.DYE_EDATE IS NOT NULL
              AND EXISTS (SELECT D.*
              FROM (  SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP , SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END) AS START_STEP
              FROM DFBT_MONITOR M, DFMS_STEP ST 
              WHERE 1 = 1
               AND ST.STEP_NO = M.STEP_NO
               AND ST.GROUP_STEP IN ('07-QT','08-QA','09-Inspection')
              AND EXISTS (SELECT * FROM DFBT_HEADER BH WHERE BH.OU_CODE = M.OU_CODE AND BH.BATCH_NO = M.BATCH_NO AND BH.STATUS < 8)
              GROUP BY M.OU_CODE, M.BATCH_NO
              HAVING (COUNT(METHOD_CONT) - SUM(CASE WHEN M.START_DATE IS NULL THEN 0 ELSE 1 END)) > 0) D
              WHERE BD.OU_CODE = D.OU_CODE AND BD.BATCH_NO = D.BATCH_NO
              )
 			";          


	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);



// 	$row = 1;
// 	while ($dr = oci_fetch_assoc($query)) {


		
// 		$b = $dr["JOB_NO"];
// 		$c = $dr["OU_CODE"];
// 		$d = $dr["BATCH_NO"];
// 		$e = $dr["WARNINGTIME"];
// 		$f = $dr["ALERTTIME"];
// 		$g = $dr["BATCH_START"];
// 		$h = $dr["END_DYE_DATE"];
// 		$i = $dr["TIMESPAN"];
// 		$j = $dr["FTIME_AGING"];
// 		$k = $dr["FTIME"];

// 		$l = $dr["FABRIC_REC_BILL"];
// 		$m = $dr["TOTAL_STEP"];
// 		$n = $dr["STEP_COMPLETE"];
// 		$o = $dr["ICON_NAME"];
// 		$p = $dr["ICON_COLOR"];
// 		$q = $dr["REFRESH"];

// 		$r = $dr["METHOD_NAME"];
// 		$s = $dr["START_DATE"];
// 		$t = $dr["MACHINE_NO"];
// 		$u = $dr["KEYJOB"];
// 		$v = $dr["KEY"];
// 		$w = $dr["ITEM_CODE"];
// 		$x = $dr["COLOR_CODE"];
// 		$y = $dr["COLOR_DESC"];
// 		$z = $dr["TOTAL_ROLL"];

// 		$aa = $dr["TOTAL_QTY"];
// 		$bb = $dr["SO_NO"];
// 		$cc = $dr["CUSTOMER_NAME"];
// 		$dd = $dr["WIP_REC_FC_DATE"];
// 		$ee = $dr["STEP_NO"];
// 		$ff = $dr["STEP_NAME"];
// 		$gg = $dr["CUSTOMER_ID"];
// 		$hh = $dr["VI"];


// 		 $sqlInsert = "Insert into DEMO.CONTROL_DRY (
// 		 JOB_NO
// 		 ,OU_CODE
// 		 ,BATCH_NO
// 		 ,WARNINGTIME
// 		 ,ALERTTIME
// 		 ,BATCH_START
// 		 ,END_DYE_DATE
// 		 ,TIMESPAN
// 		 ,FTIME_AGING
// 		 ,FTIME
// 		,FABRIC_REC_BILL
// 		,TOTAL_STEP
// 		,STEP_COMPLETE
// 		,ICON_NAME
// 		,ICON_COLOR
// 		,REFRESH
// 		,METHOD_NAME
// 		,START_DATE
// 		,MACHINE_NO
// 		,KEYJOB
// 		,KEY
// 		,ITEM_CODE
// 		,COLOR_CODE
// 		,COLOR_DESC
// 		,TOTAL_ROLL
// 		,TOTAL_QTY
// 		,SO_NO
// 		,CUSTOMER_ID
// 		,CUSTOMER_NAME
// 		,WIP_REC_FC_DATE
// 		,STEP_NO
// 		,STEP_NAME
// 		,VI
// 		 ,CREATE_DATE) 
// 		 values ('$b','$c','$d','$e','$f',to_date('$g','DD/MM/RRRR HH24:MI'),'$h','$i','$j','$k'
// ,'$l','$m','$n','$o','$p','$q','$r','$s','$t','$u'
// ,'$v','$w','$x','$y','$z','$aa','$bb', '$gg', '$cc',to_date('$dd','DD/MM/RRRR HH24:MI'),'$ee','$ff','$hh'
// 		 ,SYSDATE)";




// 		$queryInsert = oci_parse($conn_omnoi, $sqlInsert);

// 		oci_execute($queryInsert);

// 	 	$row++;

// 	}


	// $url = "https://lytkiluyliulzwfqwfuk.firebaseio.com/controlroom/last_update/.json";

	// date_default_timezone_set("Asia/Bangkok");
	// $obj = new stdClass();
	// $obj->time =  date("Y-m-d H:i:s");

	// $ch = curl_init( $url );
	// $payload = json_encode( $obj);
	// curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	// curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
	// curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	// curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	// # Send request.
	// $result1 = curl_exec($ch);
	// curl_close($ch);
	// # Print response.
	


	// $sToken = "kZIBSGI0IUWrnDR8VFCsSKu2wxWd7wYOGMxQbJuOj8s";

	// $chOne = curl_init();
	// curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
	// curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
	// curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
	// curl_setopt( $chOne, CURLOPT_POST, 1);
	// curl_setopt( $chOne, CURLOPT_POSTFIELDS, 'message='.'Interface Inspection Control Room NYK');
	// $headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	// curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
	// curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
	// $result = curl_exec( $chOne );

	// curl_close($chOne);

	// echo "<pre>$result</pre>";
	// echo "<pre>$result1</pre>";
	
	

	



?>