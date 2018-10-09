<?php
	/*User validation*/
	include_once(dirname(__FILE__) . "/../../include/CAS_login.php");
	
	/*Get DB connection*/
	include_once(dirname(__FILE__) . "/../../functions/database.php");
	$conn = connection();
	
	/*Document functions*/
	include_once(dirname(__FILE__) . "/../../functions/documents.php");

/*This function is used similarly to AJAX calls, but technically it isn't one!

************ FOR ANYONE TO DOWNLOAD AN APPLICATION'S FILE AT ANY TIME - REQUIRES USER TO HAVE PERMISSION TO DO SO ***************/

$uploadReturn = null; //will be the application data if successful. If unsuccessful, uploadReturn["error"] should be set

if(isset($_GET["appID"]) && isset($_GET["filename"]))
{
	$appID = $_GET["appID"];
	$file = $_GET["filename"];

	/*Verify that user is allowed to see this file*/
	if(isUserAllowedToSeeApplications($conn, $CASbroncoNetID) || doesUserOwnApplication($conn, $CASbroncoNetID, $appID) || isUserDepartmentChair($conn, $CASemail, $appID, $CASbroncoNetID))
	{
		$uploadReturn = downloadDoc($appID, $file);
	}
	else
	{
		$uploadReturn["error"] = "Permission denied";
	}
}
else
{
	$uploadReturn["error"] = "AppID and/or filename is not set";
}

$conn = null; //close connection

echo json_encode($uploadReturn); //return data to the application page!

?>