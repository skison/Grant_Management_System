<?php
	/*User validation*/
	include_once(dirname(__FILE__) . "/../include/CAS_login.php");
	
	/*Get DB connection*/
	include_once(dirname(__FILE__) . "/../functions/database.php");
	$conn = connection();
	
	/*Document functions*/
	include_once(dirname(__FILE__) . "/../functions/documents.php");

	/*For sending custom emails*/
	include_once(dirname(__FILE__) . "/../functions/customEmail.php");

/************* FOR APPROVING A REPORT AT ANY TIME - REQUIRES USER TO HAVE PERMISSION TO DO SO ***************/

$approvalReturn = array(); //will be the application data if successful. If unsuccessful, approvalReturn["error"] should be set

if(isset($_POST["appID"]) && isset($_POST["status"]) && isset($_POST["emailAddress"]) && isset($_POST["emailMessage"]))
{
	$appID = $_POST["appID"];
	$status = $_POST["status"];
	$emailAddress = $_POST["emailAddress"];
	$emailMessage = $_POST["emailMessage"];


	if(trim($emailMessage) === '' || $emailMessage == null) {$approvalReturn["error"] = "Email message must not be empty!";}
	else
	{
		/*Verify that user is allowed to approve a report*/
		if(isFollowUpReportApprover($conn, $CASbroncoNetID) || isAdministrator($conn, $CASbroncoNetID))
		{
			try
			{
				if($status === 'Approved') { $approvalReturn["success"] = approveFollowUpReport($conn, $appID); }
				else if($status === 'Denied') { $approvalReturn["success"] = denyFollowUpReport($conn, $appID); }
				else if($status === 'Hold') { $approvalReturn["success"] = holdFollowUpReport($conn, $appID); }
				else { $approvalReturn["error"] = "Invalid status given"; }

				//if everything has been successful so far, send off the email as well
				if(!isset($approvalReturn["error"]))
				{
					$approvalReturn["email"] = customEmail($appID, $emailAddress, $emailMessage, null); //get results of trying to save/send email message
				}
			}
			catch(Exception $e)
			{
				$approvalReturn["error"] = "Unable to approve follow up report: " . $e->getMessage();
			}
		}
		else
		{
			$approvalReturn["error"] = "Permission denied";
		}
	}
}
else
{
	$approvalReturn["error"] = "AppID, status, and/or email is not set";
}

$conn = null; //close connection

echo json_encode($approvalReturn); //return data to the application page!

?>