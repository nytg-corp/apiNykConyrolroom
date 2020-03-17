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

	$queryDelete = oci_parse($conn_omnoi, "delete from CONTROL_PRE_WAREHOUSE");

	oci_execute($queryDelete);

	$sqlTime = "SELECT m.* FROM CONTROL_SET_TIME m WHERE PROCESS = 'FG' AND OU_CODE = 'D03'";

	$warning = 4;
	$alert = 8;

	$queryTime = oci_parse($conn_omnoi, $sqlTime);

	oci_execute($queryTime);

	while ($dr = oci_fetch_assoc($queryTime)) {
		$warning = intval($dr["WARNING_MINUTE"])/ 60;
		$alert = intval($dr["ALERT_MINUTE"])/ 60;
	}


	$sql = "INSERT INTO CONTROL_PRE_WAREHOUSE 
SELECT KEY, OU_CODE, BATCH_NO, ITEM_CODE, END_DATE
,ROUND((SYSDATE - END_DATE) * (24),2) AS TIME_AGING
,TIMESTAMP_DIFF(END_DATE, SYSDATE) AS	TIMESPAN
,TO_CHAR(END_DATE,'DD-MM-YYYY HH24:MI') END_DYE_DATE
,CASE WHEN ((SYSDATE - END_DATE) * (24)) > $alert then 'red-13'
                WHEN ((SYSDATE - END_DATE) * (24)) > $warning then 'yellow-13'
                ELSE 'light-green-13' END ICON_COLOR	
                				 ,CASE WHEN ((SYSDATE - END_DATE) * (24)) > $alert then 'warning'
                WHEN ((SYSDATE - END_DATE) * (24)) > $warning then 'notification_important'
                ELSE 'flag' END ICON_NAME	
              ,$warning as WARNINGTIME
              ,$alert as ALERTTIME
              ,'' as COLOR_CODE
              ,'' as COLOR_DESC
                 ,'' as CUSTOMER_ID
              ,'' as CUSTOMER_NAME
              ,'' as VI
              , 0 as TOTAL_ROLL
              , 0 as TOTAL_QTY
              ,0 STS_NORMAL, null STS_NORMAL_DTE, 0 STS_INACTIVE, null STS_INACTIVE_DTE, 0 STS_SCRAP, null STS_SCRAP_DTE, SYSDATE, NULL, NULL, NULL
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




echo "<pre>$sql</pre>";


?>