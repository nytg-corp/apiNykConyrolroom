<!DOCTYPE html>
<html>
<header>
	
<script src="https://www.gstatic.com/firebasejs/5.5.5/firebase.js"></script>
<script>
	const config  = {
	  apiKey: "AIzaSyDFTNuL38NNRgYjvddM0tgqrWprgVuoyUc",
	  authDomain: "lytkiluyliulzwfqwfuk.firebaseapp.com",
	  databaseURL: "https://lytkiluyliulzwfqwfuk.firebaseio.com",
	  projectId: "lytkiluyliulzwfqwfuk",
	  storageBucket: "lytkiluyliulzwfqwfuk.appspot.com",
	  messagingSenderId: "1079794935986"
	};

	firebase.initializeApp(config);
</script>

</header>
<body>

<h1>My first PHP page</h1>

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

	$queryDelete = oci_parse($conn_omnoi, "delete from CONTROL_PREPARE");

	oci_execute($queryDelete);

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'PREPARE' AND OU_CODE = 'D03'";
	
	$warning = 480;
	$alert = 240;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);
	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"]);
		$alert = intval($dr["ALERT_MINUTE"]);
	}

		$sql = "SELECT J.JOB_NO
		    , TO_CHAR(J.BATCH_START,'DD/MM/YYYY HH24:MI') BATCH_START
		    , TO_CHAR(J.BATCH_START,'DD/MM/YYYY HH24:MI') END_DYE_DATE
			,timestamp_diff(sysdate,batch_start) as	TIMESPAN
			,ROUND((SYSDATE - BD.FABRIC_REC_BILL) * (24),2) AS FTIME_AGING
            ,timestamp_diff(BD.FABRIC_REC_BILL,sysdate) AS FTIME
            ,TO_CHAR(BD.FABRIC_REC_BILL,'DD-MM-YYYY HH24:MI') FABRIC_REC_BILL
			, STEP.TOTAL_STEP, STEP.STEP_COMPLETE
			,case when (batch_start <= (sysdate + interval '$alert' minute)) then 'timer_off'
      			  when (batch_start > (sysdate + interval '$alert' minute)) and (batch_start <= (sysdate + interval '$warning' minute)) then 'timer'
      			  else 'access_time' END ICON_NAME
			,case when (batch_start <= (sysdate + interval '$alert' minute)) then 'red'
      			when (batch_start > (sysdate + interval '$alert' minute)) and (batch_start <= (sysdate + interval '$warning' minute)) then 'yellow'
      			else 'light-green-13' END ICON_COLOR
			              ,'$warning' AS WARNINGTIME
			              ,'$alert' AS ALERTTIME
			               ,1 as REFRESH
							,'' as METHOD_NAME, '' as START_DATE, '' as MACHINE_NO
							,RANK() OVER(ORDER BY J.BATCH_START,J.JOB_NO, BD.BATCH_NO) RNK
							 ,TO_CHAR(J.BATCH_START,'DD/MM/YYYY HH24:MI')||'-'||BD.OU_CODE || '-' || BD.BATCH_NO AS KEYJOB
			,BD.OU_CODE || '-' || BD.BATCH_NO AS KEY, BD.OU_CODE, BD.BATCH_NO, BD.ITEM_CODE, BD.COLOR_CODE, BD.COLOR_DESC, BD.TOTAL_ROLL, BD.TOTAL_QTY
			,BD.SO_NO, BD.CUSTOMER_NAME,  TO_CHAR(BD.WIP_REC_FC_DATE,'DD/MM/YYYY HH24:MI') WIP_REC_FC_DATE
			, LS.STEP_NO , LS_D.STEP_NAME
			FROM (
				SELECT  J.OU_CODE, J.JOB_NO, MAX(J.BATCH_START) BATCH_START, MAX(J.BATCH_END)BATCH_END
				FROM DFIT_DASHBOARD_FABRIC J
				WHERE 1 = 1  
				AND J.BATCH_START >= (SYSDATE-(1/24))
				GROUP BY J.OU_CODE, J.JOB_NO
				ORDER BY BATCH_START 
			)J , DFIT_BTDATA BD, (SELECT M.OU_CODE, M.BATCH_NO , COUNT(METHOD_CONT) TOTAL_STEP 
			,SUM(CASE WHEN M.END_DATE IS NULL THEN 0 ELSE 1 END) AS STEP_COMPLETE
			          FROM DFBT_MONITOR M, DFMS_STEP ST 
			              WHERE 1 = 1
			               AND ST.STEP_NO = M.STEP_NO
			               AND M.METHOD_CONT = 1
			              GROUP BY M.OU_CODE, M.BATCH_NO
			              ) STEP, ( SELECT LS.* FROM CT_LAST_STEP LS WHERE STEP_RANK = 1) LS, DFMS_STEP LS_D
			WHERE J.JOB_NO = BD.OU_CODE || BD.JOB_NO
			AND BD.OU_CODE = STEP.OU_CODE(+)
			AND BD.BATCH_NO = STEP.BATCH_NO(+)
			AND BD.OU_CODE = LS.OU_CODE(+)
			AND BD.BATCH_NO = LS.BATCH_NO(+)
			AND LS.STEP_NO = LS_D.STEP_NO(+)
			AND BD.STATUS <= 2
			AND BD.JOB_NO IN (SELECT DISTINCT DQ.JOB_NO FROM DFIT_QUEUE DQ WHERE DQ.OU_CODE = BD.OU_CODE AND DQ.JOB_NO = BD.JOB_NO AND DQ.DYE_EDATE IS NULL)
			ORDER BY J.BATCH_START,  J.JOB_NO";

	$query = oci_parse($conn_omnoi, $sql);

	oci_execute($query);

	$row = 1;
	while ($dr = oci_fetch_assoc($query)) {


		$a = $dr["RNK"];
		$b = $dr["JOB_NO"];
		$c = $dr["OU_CODE"];
		$d = $dr["BATCH_NO"];
		$e = $dr["WARNINGTIME"];
		$f = $dr["ALERTTIME"];
		$g = $dr["BATCH_START"];
		$h = $dr["END_DYE_DATE"];
		$i = $dr["TIMESPAN"];
		$j = $dr["FTIME_AGING"];
		$k = $dr["FTIME"];

		$l = $dr["FABRIC_REC_BILL"];
		$m = $dr["TOTAL_STEP"];
		$n = $dr["STEP_COMPLETE"];
		$o = $dr["ICON_NAME"];
		$p = $dr["ICON_COLOR"];
		$q = $dr["REFRESH"];

		$r = $dr["METHOD_NAME"];
		$s = $dr["START_DATE"];
		$t = $dr["MACHINE_NO"];
		$u = $dr["KEYJOB"];
		$v = $dr["KEY"];
		$w = $dr["ITEM_CODE"];
		$x = $dr["COLOR_CODE"];
		$y = $dr["COLOR_DESC"];
		$z = $dr["TOTAL_ROLL"];

		$aa = $dr["TOTAL_QTY"];
		$bb = $dr["SO_NO"];
		$cc = $dr["CUSTOMER_NAME"];
		$dd = $dr["WIP_REC_FC_DATE"];
		$ee = $dr["STEP_NO"];
		$ff = $dr["STEP_NAME"];


		 $sqlInsert = "Insert into DEMO.CONTROL_PREPARE (
		 RNK
		 ,JOB_NO
		 ,OU_CODE
		 ,BATCH_NO
		 ,WARNINGTIME
		 ,ALERTTIME
		 ,BATCH_START
		 ,END_DYE_DATE
		 ,TIMESPAN
		 ,FTIME_AGING
		 ,FTIME
		,FABRIC_REC_BILL
		,TOTAL_STEP
		,STEP_COMPLETE
		,ICON_NAME
		,ICON_COLOR
		,REFRESH
		,METHOD_NAME
		,START_DATE
		,MACHINE_NO
		,KEYJOB
		,KEY
		,ITEM_CODE
		,COLOR_CODE
		,COLOR_DESC
		,TOTAL_ROLL
		,TOTAL_QTY
		,SO_NO
		,CUSTOMER_NAME
		,WIP_REC_FC_DATE
		,STEP_NO
		,STEP_NAME
		 ,CREATE_DATE) 
		 values ($a,'$b','$c','$d','$e','$f',to_date('$g','DD/MM/RRRR HH24:MI'),'$h','$i','$j','$k'
,'$l','$m','$n','$o','$p','$q','$r','$s','$t','$u'
,'$v','$w','$x','$y','$z','$aa','$bb','$cc',to_date('$dd','DD/MM/RRRR HH24:MI'),'$ee','$ff'
		 ,SYSDATE)";




		$queryInsert = oci_parse($conn_omnoi, $sqlInsert);

		oci_execute($queryInsert);

	 	$row++;

	}


	$sToken = "kZIBSGI0IUWrnDR8VFCsSKu2wxWd7wYOGMxQbJuOj8s";

	$chOne = curl_init();
	curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $chOne, CURLOPT_POST, 1);
	curl_setopt( $chOne, CURLOPT_POSTFIELDS, 'message='.'Interface Prepare Control Room NYK');
	$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
	curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
	curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec( $chOne );

	curl_close($chOne);

?>



<script type="text/javascript">
	
//window.onload = function () {
	updfirebase();
    ShowData();
//}

function ShowData() {

    var firebaseRef = firebase.database().ref("controlroom");
    firebaseRef.once('value').then(function (dataSnapshot) {

        dataSnapshot.forEach(function (childSnapshot) {
            var childKey = childSnapshot.key;
            var childData = childSnapshot.val();
            console.log(childData);
        });
    });

}

function updfirebase() {

    var d = new Date();
    firebase.database().ref('controlroom/' + 'last_update').set({
        time: d.toLocaleDateString() + " " + d.toLocaleTimeString()
    });

}

</script>

</body>
</html>