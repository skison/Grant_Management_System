<?php
//For AJAX access
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

	/*Debug user validation*/
	//include "include/debugAuthentication.php";
	include_once(dirname(__FILE__) . "/include/CAS_login.php");
	
	/*Get DB connection*/
	include_once(dirname(__FILE__) . "/functions/database.php");
	$conn = connection();
	
	/*Verification functions*/
	include_once(dirname(__FILE__) . "/functions/verification.php");
	
	/*Document functions*/
	include_once(dirname(__FILE__) . "/functions/documents.php");

	/*For sending custom emails*/
	include_once(dirname(__FILE__) . "/functions/customEmail.php");


	/*for dept. chair email message*/
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
?>



<!DOCTYPE html>
<html lang="en">
	
	<!-- Page Head -->
	<?php include 'include/head_content.html'; ?>
	<body ng-app="HIGE-app" >
	
		<!-- Shared Site Banner -->
		<?php include 'include/site_banner.html'; ?>

	<div id="MainContent" role="main">
		
		<?php
		
		/*save the current date*/
		$currentDate = DateTime::createFromFormat('Y/m/d', date("Y/m/d"));

		$app = null; //only set app if it exists (if not creating one)

		$submitDate = null; //date this app was submitted, if at all
		
		/*get initial character limits for text fields*/
		$appCharMax = getApplicationsMaxLengths($conn);
		$appBudgetCharMax = getApplicationsBudgetsMaxLengths($conn);

		//echo var_dump($appCharMax);
		
		$maxName = $appCharMax[array_search('Name', array_column($appCharMax, 0))][1]; //name char limit
		$maxDep = $appCharMax[array_search('Department', array_column($appCharMax, 0))][1]; //department char limit
		$maxTitle = $appCharMax[array_search('Title', array_column($appCharMax, 0))][1]; //title char limit
		$maxDestination = $appCharMax[array_search('Destination', array_column($appCharMax, 0))][1]; //destination char limit
		$maxOtherEvent = $appCharMax[array_search('IsOtherEventText', array_column($appCharMax, 0))][1]; //other event text char limit
		$maxOtherFunding = $appCharMax[array_search('OtherFunding', array_column($appCharMax, 0))][1]; //other funding char limit
		$maxProposalSummary = $appCharMax[array_search('ProposalSummary', array_column($appCharMax, 0))][1]; //proposal summary char limit
		$maxDepChairSig = $appCharMax[array_search('DepartmentChairSignature', array_column($appCharMax, 0))][1];//signature char limit
		
		$maxBudgetComment = $appBudgetCharMax[array_search('Comment', array_column($appBudgetCharMax, 0))][1]; //budget comment char limit
		
		
		/*Initialize all user permissions to false*/
		$isCreating = false; //user is an applicant initially creating application
		$isReviewing = false; //user is an applicant reviewing their already created application
		$isAdmin = false; //user is an administrator
		$isAdminUpdating = false; //user is an administrator who is updating the application
		$isCommittee = false; //user is a committee member
		$isChair = false; //user is the associated department chair
		$isChairReviewing = false; //user is the associated department chair, but cannot do anything (just for reviewing purposes)
		$isApprover = false; //user is an application approver (director)
		
		$permissionSet = false; //boolean set to true when a permission has been set- used to force only 1 permission at most
		
		/*User is trying to download a document*/
		if(isset($_GET["doc"]))
		{
			downloadDocs($_GET["id"], $_GET["doc"]);
		}
		/*Get all user permissions. THESE ARE TREATED AS IF THEY ARE MUTUALLY EXCLUSIVE; ONLY ONE CAN BE TRUE!
		For everything besides application creation, the app ID MUST BE SET*/
		if(isset($_GET["id"]))
		{
			//admin updating check
			$isAdminUpdating = (isAdministrator($conn, $CASbroncoNetID) && isset($_GET["updating"])); //admin is viewing page; can edit stuff
			$permissionSet = $isAdminUpdating;

			//admin check
			if(!$permissionSet)
			{
				$isAdmin = isAdministrator($conn, $CASbroncoNetID); //admin is viewing page
				$permissionSet = $isAdmin;
			}
			
			//application approver check
			if(!$permissionSet)
			{
				$isApprover = isApplicationApprover($conn, $CASbroncoNetID); //application approver(director) can write notes, choose awarded amount, and generate email text
				$permissionSet = $isApprover;
			}
			
			//department chair check
			if(!$permissionSet)
			{
				$isChair = isUserAllowedToSignApplication($conn, $CASemail, $_GET['id']); //chair member; can sign application
				$permissionSet = $isChair;
			}

			//department chair reviewing check
			if(!$permissionSet)
			{
				$isChairReviewing = isUserDepartmentChair($conn, $CASemail, $_GET['id']); //chair member; can view the application, but cannot sign
				$permissionSet = $isChairReviewing;
			}
			
			//committee member check
			if(!$permissionSet)
			{
				$isCommittee = isUserAllowedToSeeApplications($conn, $CASbroncoNetID); //committee member; can only view!
				$permissionSet = $isCommittee;
			}
			
			//applicant reviewing check
			if(!$permissionSet)
			{
				$isReviewing = doesUserOwnApplication($conn, $CASbroncoNetID, $_GET['id']); //applicant is reviewing their application
				$permissionSet = $isReviewing;
			}
		}
		//applicant creating check. Note- if the app id is set, then by default the application cannot be created
		if(!$permissionSet && !isset($_GET["id"]))
		{
			$isCreating = isUserAllowedToCreateApplication($conn, $CASbroncoNetID, $CASallPositions, true); //applicant is creating an application (check latest date possible)
			$permissionSet = $isCreating; //will set to true if user is creating a new application
		}
		
		/*Verify that user is allowed to render application*/
		if($permissionSet)
		{
			/*User is trying to download a document*/
			if(isset($_GET["doc"]))
			{
				downloadDocs($_GET["id"], $_GET["doc"]);
			}
			/*User is trying to upload a document (only allowed to if has certain permissions)*/
			if($isCreating || $isReviewing || $isAdminUpdating){
				if(isset($_REQUEST["uploadDocs"]) || isset($_REQUEST["uploadDocsF"]))
				{
					uploadDocs($_REQUEST["updateID"]);
				}
			}



			$P = array();
			$S = array();
			
			/*Initialize variables if application has already been created*/
			if(!$isCreating)
			{
				$idA = $_GET["id"];
				
				$app = getApplication($conn, $idA); //get application Data

				$submitDate = DateTime::createFromFormat('Y-m-d', $app->dateSubmitted);
				

				/*
				$docs = listDocs($idA); //get documents
				for($i = 0; $i < count($docs); $i++)
				{
					if(substr($docs[$i], 0, 1) == 'P')
						array_push($P, "<a href='?id=" . $idA . "&doc=" . $docs[$i] . "' target='_blank'>" . $docs[$i] . "</a>");
					if(substr($docs[$i], 0, 1) == 'S')
						array_push($S, "<a href='?id=" . $idA . "&doc=" . $docs[$i] . "' target='_blank'>" . $docs[$i] . "</a>");
				}
				
				/*Admin wants to update application*/
				if($isAdminUpdating && isset($_POST["cancelUpdateApp"]))
				{
					header('Location: ?id=' . $idA); //reload page as admin
				}

				/*Admin wants to cancel updating this application*/
				if($isAdmin && isset($_POST["updateApp"]))
				{
					header('Location: ?id=' . $idA . '&updating'); //reload page as admin updating
				}
				
				/*User wants to approve this application*/
				if(isset($_POST["approveA"]))
				{
					if(approveApplication($conn, $idA, $_POST["aAw"]))
					{
						customEmail(trim($app->email), nl2br($_POST["finalE"]), "");
						header('Location: index.php'); //redirect
					}
				}
				
				/*User wants to deny this application*/
				if(isset($_POST["denyA"]))
				{
					if(denyApplication($conn, $idA))
					{
						customEmail(trim($app->email), nl2br($_POST["finalE"]), "");
						header('Location: index.php'); //redirect
					}
				}
				/*User wants to HOLD this application*/
				if(isset($_POST["holdA"]))
				{
					if(holdApplication($conn, $idA))
					{
						customEmail(trim($app->email), nl2br($_POST["finalE"]), "");
						header('Location: index.php'); //redirect
					}
				}
				
				/*Check for trying to sign application*/
				if($isChair && isset($_POST["signApp"]))
				{
					signApplication($conn, $idA, $_POST["deptChairApproval"]);
					header('Location: index.php'); //redirect to homepage
				}
			}
		?>
		<!--HEADER-->
		
			<!--BODY-->
			<div class="container-fluid">

				<?php if($isAdmin){ //form for admin updates- start ?>
					<form enctype="multipart/form-data" class="form-horizontal" id="updateForm" name="updateForm" method="POST" action="#">
						<input type="submit" onclick="return confirm ('Are you sure you want to enter update mode? Any unsaved data will be lost.')" class="btn btn-warning" id="updateApp" name="updateApp" value="---UPDATE MODE---" />
					</form>
				<?php }else if($isAdminUpdating){ //form for admin updates- end ?>
					<form enctype="multipart/form-data" class="form-horizontal" id="updateForm" name="updateForm" method="POST" action="#">
						<input type="submit" onclick="return confirm ('Are you sure you want to cancel updating? Any unsaved data will be lost.')" class="btn btn-warning" id="cancelUpdateApp" name="cancelUpdateApp" value="---CANCEL EDITS---" />
					</form>
				<?php } ?>

				<div ng-controller="appCtrl">

					<button ng-click="toggleAdminUpdate()" class="btn btn-warning">TURN {{isAdminUpdating ? "OFF" : "ON"}} ADMIN UPDATE MODE</button>

					<!-- SHOW ERROR/SUCCESS MESSAGES -->
					<div id="messages"></div>

						<!-- application form -->
					<form enctype="multipart/form-data" class="form-horizontal" id="applicationForm" name="applicationForm" ng-submit="processForm()">


						<div class="row">
							<h1 class="title">APPLICATION:</h1>
						</div>
						
						<!--SUBMISSION CYCLE WARNING-->
						<div class="row" ng-show="shouldWarn">
							<h3 class="title warning">WARNING! DO NOT SUBMIT APPLICATION AFTER THE MIDNIGHT OF A CYCLE'S DUE DATE! <br/>
								<br/>If you do, your application will be automatically moved forward by one cycle!</h3>
						</div>
					
						<!--SUBMISSION CYCLE-->
						<div class="row">
							<div class="col-md-4"></div>
							<div class="col-md-4">
								<fieldset>
								<legend>Submission Cycle:</legend>
									<div class="checkbox">
										<p>{{isCreating || isAdminUpdating ? "Current date: "+currentDate : "Date Submitted: "+dateSubmitted}}</p>
										<div class="radio">
										<label><input ng-disabled="!allowedFirstCycle || appFieldsDisabled" type="radio" value="this" ng-model="formData.cycleChoice" name="cycleChoice">Submit For This Cycle ({{thisCycle}})</label>
										</div>
										<div class="radio">
										<label><input ng-disabled="appFieldsDisabled" type="radio" value="next" ng-model="formData.cycleChoice" name="cycleChoice">Submit For Next Cycle ({{nextCycle}})</label>
										</div>
									</div>
								</fieldset>
							</div>
							<div class="col-md-4"></div>
						</div>
					
					
					
						<!--APPLICANT INFO-->
						<div class="row">
							<h2 class="title">Applicant Information:</h2>
						</div>
						
						
						
						<div class="row">
						<!--NAME-->
							<div class="col-md-5">
								<div class="form-group">
									<label for="name">Name{{isCreating || isAdminUpdating ? " ("+(maxName-formData.name.length)+" characters remaining)" : ""}}:</label>
									<input type="text" class="form-control" maxlength="{{maxName}}" ng-model="formData.name" ng-disabled="appFieldsDisabled" id="name" name="name" placeholder="Enter Name" />
								</div>
							</div>
						<!--EMAIL-->
							<div class="col-md-7">
								<div class="form-group">
									<label for="email">Email Address:</label>
									<input type="email" class="form-control" maxlength="{{maxEmail}}" ng-model="formData.email" ng-disabled="appFieldsDisabled" id="email" name="email" placeholder="Enter Email Address" />
								</div>
							</div>
						</div>
						
						
						
						<div class="row">
						<!--DEPARTMENT-->
						<div class="col-md-5">
								<div class="form-group">
									<label for="department">Department{{isCreating || isAdminUpdating ? " ("+(maxDep-formData.department.length)+" characters remaining)" : ""}}:</label>
									<input type="text" class="form-control" maxlength="{{maxDep}}" ng-model="formData.department" ng-disabled="appFieldsDisabled" id="department" name="department" placeholder="Enter Department" />
								</div>
							</div>
						<!--DEPT CHAIR EMAIL-->
							<div class="col-md-7">
								<div class="form-group">
									<label for="deptChairEmail">Department Chair's WMU Email Address:</label>
									<input type="email" class="form-control" maxlength="{{maxDepEmail}}" ng-model="formData.deptChairEmail" ng-disabled="appFieldsDisabled" id="deptChairEmail" name="deptChairEmail" placeholder="Enter Department Chair's Email Address" />
								</div>
							</div>
						</div>
						
						
						
						<!--RESEARCH INFO-->
						<div class="row">
							<h2 class="title">Travel Information:</h2>
						</div>
						
						
						
						<div class="row">
						<!--TRAVEL DATE FROM-->
							<div class="col-md-3">
								<div class="form-group">
									<label for="travelFrom">Travel Date From:</label>
									<input type="date" class="form-control" ng-model="formData.travelFrom" ng-disabled="appFieldsDisabled" id="travelFrom" name="travelFrom" />
								</div>
							</div>
						<!--TRAVEL DATE TO-->
							<div class="col-md-3">
								<div class="form-group">
									<label for="travelTo">Travel Date To:</label>
									<input type="date" class="form-control" ng-model="formData.travelTo" ng-disabled="appFieldsDisabled" id="travelTo" name="travelTo" />
								</div>
							</div>
						<!--ACTIVITY DATE FROM-->
							<div class="col-md-3">
								<div class="form-group">
									<label for="activityFrom">Activity Date From:</label>
									<input type="date" class="form-control" ng-model="formData.activityFrom"ng-disabled="appFieldsDisabled"  id="activityFrom" name="activityFrom" />
								</div>
							</div>
						<!--ACTIVITY DATE TO-->
							<div class="col-md-3">
								<div class="form-group">
									<label for="activityTo">Activity Date To:</label>
									<input type="date" class="form-control" ng-model="formData.activityTo" ng-disabled="appFieldsDisabled" id="activityTo" name="activityTo" />
								</div>
							</div>
						</div>
						
						
						
						<div class="row">
						<!--TITLE-->
							<div class="col-md-4">
								<div class="form-group">
									<label for="title">Project Title{{isCreating || isAdminUpdating ? " ("+(maxTitle-formData.title.length)+" characters remaining)" : ""}}:</label>
									<input type="text" class="form-control" maxlength="{{maxTitle}}" ng-model="formData.title" ng-disabled="appFieldsDisabled" id="title" name="title" placeholder="Enter Title of Research" />
								</div>
							</div>
						<!--DESTINATION-->
							<div class="col-md-4">
								<div class="form-group">
									<label for="destination">Destination{{isCreating || isAdminUpdating ? " ("+(maxDestination-formData.destination.length)+" characters remaining)" : ""}}:</label>
									<input type="text" class="form-control" maxlength="{{maxDestination}}" ng-model="formData.destination" ng-disabled="appFieldsDisabled" id="destination" name="destination" placeholder="Enter Destination" />
								</div>
							</div>
						<!--AMOUNT REQ-->
							<div class="col-md-4">
								<div class="form-group">
									<label for="amountRequested">Amount Requested($):</label>
									<input type="text" class="form-control" ng-model="formData.amountRequested" ng-disabled="appFieldsDisabled" id="amountRequested" name="amountRequested" placeholder="Enter Amount Requested($)" onkeypress='return (event.which >= 48 && event.which <= 57) || event.which == 8 || event.which == 46' />
								</div>
							</div>
						</div>
						
						
						
						<!--PURPOSES-->
						<fieldset>
						<legend>Purpose of Travel:</legend>
						
							<!--PURPOSE:RESEARCH-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.purpose1" ng-disabled="appFieldsDisabled" name="purpose1" type="checkbox" value="purpose1">Research</label>
									</div>
								</div>
							</div>
							<!--PURPOSE:CONFERENCE-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.purpose2" ng-disabled="appFieldsDisabled" name="purpose2" type="checkbox" value="purpose2">Conference</label>
									</div>
								</div>
							</div>
							<!--PURPOSE:CREATIVE ACTIVITY-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.purpose3" ng-disabled="appFieldsDisabled" name="purpose3" type="checkbox" value="purpose3">Creative Activity</label>
									</div>
								</div>
							</div>
							<!--PURPOSE:OTHER-->
							<div class="row">
								<div class="col-md-2">
									<div class="checkbox">
										<label><input ng-model="formData.purpose4OtherDummy" ng-disabled="appFieldsDisabled" name="purpose4OtherDummy" id="purpose4OtherDummy" type="checkbox" value="purpose4OtherDummy">Other, explain.</label>
									</div>
								</div>
								<!-- OTHER PURPOSE TEXT BOX -->
								<div class="col-md-10">
									<div class="form-group">
										<label for="purpose4Other">Explain other purpose{{isCreating || isAdminUpdating ? " ("+(maxOtherEvent-formData.purpose4Other.length)+" characters remaining)" : ""}}:</label>
										<input type="text" class="form-control" maxlength="{{maxOtherEvent}}" ng-model="formData.purpose4Other" ng-disabled="appFieldsDisabled || !formData.purpose4OtherDummy" id="purpose4Other" name="purpose4Other" placeholder="Enter Explanation" />
									</div>
								</div>
							</div>
						
						</fieldset>
						
						
						<!--OTHER FUNDING-->
						<div class="row">
							<div class="col-md-12">
								<div class="form-group">
									<label for="otherFunding">Are you receiving other funding? Who is providing the funds? How much?{{isCreating || isAdminUpdating ? " ("+(maxOtherFunding-formData.otherFunding.length)+" characters remaining)" : ""}}:</label>
									<input type="text" class="form-control" maxlength="{{maxOtherFunding}}" ng-model="formData.otherFunding" ng-disabled="appFieldsDisabled" id="otherFunding" name="otherFunding" placeholder="Explain here" />	
								</div>
							</div>
						</div>
						
						
						
						<!--PROPOSAL SUMMARY-->
						<div class="row">
							<div class="col-md-12">
								<div class="form-group">
									<label for="proposalSummary">Proposal Summary{{isCreating || isAdminUpdating ? " ("+(maxProposalSummary-formData.proposalSummary.length)+" characters remaining)" : ""}} (We recommend up to 150 words):</label>
									<textarea class="form-control" maxlength="{{maxProposalSummary}}" ng-model="formData.proposalSummary" ng-disabled="appFieldsDisabled" id="proposalSummary" name="proposalSummary" placeholder="Enter Proposal Summary" rows="10"> </textarea>
								</div>
							</div>
						</div>
						
						
						
						<!--GOALS-->
						<fieldset>
						<legend>Please indicate which of the prioritized goals of the IEFDF this proposal fulfills:</legend>
						
							<!--GOAL 1-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.goal1" ng-disabled="appFieldsDisabled" name="goal1" type="checkbox" value="goal1">
										Support for international collaborative research and creative activities, or for international research, including archival and field work.</label>
									</div>
								</div>
							</div>
							<!--GOAL 2-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.goal2" ng-disabled="appFieldsDisabled" name="goal2" type="checkbox" value="goal2">
										Support for presentation at international conferences, seminars or workshops (presentation of papers will have priority over posters)</label>
									</div>
								</div>
							</div>
							<!--GOAL 3-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.goal3" ng-disabled="appFieldsDisabled" name="goal3" type="checkbox" value="goal3">
										Support for attendance at international conferences, seminars or workshops.</label>
									</div>
								</div>
							</div>
							<!--GOAL 4-->
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<label><input ng-model="formData.goal4" ng-disabled="appFieldsDisabled" name="goal4" type="checkbox" value="goal4">
										Support for scholarly international travel in order to enrich international knowledge, which will directly
										contribute to the internationalization of the WMU curricula.</label>
									</div>
								</div>
							</div>
						
						</fieldset>
						
						
						<!--BUDGET-->
						<div class="row">
							<h2 class="title">Budget: (please separate room and board calculating per diem)</h2>
						</div>
						
						<div id="exampleBudgetHolder">
							<a id="budgetExampleButton" data-toggle="collapse" class="btn btn-info" data-target="#budgetExample">Click here for an example of how to construct a budget!</a>
							<div id="budgetExample" class="collapse">
								<img src="images/BudgetExample.PNG" alt="Here is an example budget item: Expense: Registration Fee, Comments: Conference Registration, Amount($): 450" class="exampleBudget" />
							</div>
						</div>
						
						
						
						<div class="row">
							<div class="col-md-12">
								<table id="budgetList" class="table table-sm">
								<caption>Current Budget:</caption>
								<!--BUDGET:TABLE HEAD-->
									<thead>
										<tr>
											<th>Expense:</th>
											<th>Comments (up to {{maxBudgetComment}} characters each):</th>
											<th>Amount($):</th>
										</tr>
									</thead>
								<!--BUDGET:TABLE BODY-->
									<tbody>
										<tr class="row" ng-repeat="budgetItem in formData.budgetItems">
										<!--BUDGET:EXPENSE-->
											<td>
												<div class="form-group">
													<select class="form-control" ng-model="budgetItem.expense" ng-disabled="appFieldsDisabled" name="{{budgetItem.expense}}" value="{{budgetItem.expense}}" >
														<option ng-repeat="o in options" value="{{o.name}}">{{o.name}}</option>
													</select>
												</div>
											</td>
										<!--BUDGET:COMMENTS-->
											<td>
												<div class="form-group">
													<input type="text" class="form-control" ng-model="budgetItem.comment" ng-disabled="appFieldsDisabled" maxlength="{{maxBudgetComment}}" name="{{budgetItem.comment}}" placeholder="Explain..." />
												</div>
											</td>
										<!--BUDGET:AMOUNT-->
											<td>
												<div class="form-group">
													<input type="text" class="form-control" ng-model="budgetItem.amount" ng-disabled="appFieldsDisabled" name="{{budgetItem.amount}}" onkeypress='return (event.which >= 48 && event.which <= 57) || event.which == 8 || event.which == 46' />
												</div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						
						
						
						<!--BUDGET:ADD OR REMOVE-->
						<?php if($isCreating || $isAdminUpdating){ //for creating or updating applications only ?>
							<div class="row">
								<div class="col-md-5"></div>
								<div id="budgetButtons" class="col-md-2">
									<p id="addBudget"><i class="fa fa-plus-circle fa-2x" aria-hidden="true" ng-click="addInput()"></i></p>
									<p id="removeBudget"><i class="fa fa-minus-circle fa-2x" aria-hidden="true" ng-click="remInput()"></i></p>
								</div>
								<div class="col-md-5"></div>
							</div>
						<?php } ?>
						
						
						
						<!--BUDGET:TOTAL-->
						<div class="row">
							<div class="col-md-5"></div>
							<div class="col-md-2">
								<h3>Total: ${{ getTotal() }}</h3>
							</div>
							<div class="col-md-5"></div>
						</div>
						
						
						
						<div class="row">
							<h2 class="title">Attachments:</h2>
							<h3>Please Upload Documentation (Proposal Narrative, Conference Acceptance, Letter Of Invitation For Research, Etc.)</h3>
						</div>
						
						
						
						<!--UPLOADS-->
						<div class="row">
							<div class="col-md-6">
								<?php if($isCreating || $isReviewing || $isAdminUpdating){ //for uploading documents; both admins and applicants ?>
									<label for="fD">UPLOAD PROPOSAL NARRATIVE:</label><input type="file" name="fD" id="fD" accept=".txt, .rtf, .doc, .docx, 
									.xls, .xlsx, .ppt, .pptx, .pdf, .jpg, .png, .bmp, .tif"/>
								<?php } //for viewing uploaded documents; ANYONE can ?>
								<p class="title">UPLOADED PROPOSAL NARRATIVE: <?php if(count($P > 0)) { echo "<table>"; foreach($P as $ip) { echo "<tr><td>" . $ip . "</td></tr>"; } echo "</table>"; } else echo "none"; ?> </p>
								</div>
							
							
							<div class="col-md-6">
								<?php if($isCreating || $isReviewing || $isAdminUpdating){ //for uploading documents; both admins and applicants ?>
									<label for="sD">UPLOAD SUPPORTING DOCUMENTS:</label><input type="file" name="sD[]" id="sD" accept=".txt, .rtf, .doc, .docx, 
									.xls, .xlsx, .ppt, .pptx, .pdf, .jpg, .png, .bmp, .tif" multiple />
								<?php } //for viewing uploaded documents; ANYONE can ?>
								<p class="title">UPLOADED SUPPORTING DOCUMENTS: <?php if(count($S > 0)) { echo "<table>"; foreach($S as $is) { echo "<tr><td>" . $is . "</td></tr>"; } echo "</table>"; } else echo "none"; ?> </p>
							</div>
						</div>
						
						
						
						<div class="row">
						<!--DEPARTMENT CHAIR APPROVAL-->
							<div class="col-md-3"></div>
							<div class="col-md-6">
								<h3 class="title">Note: Applications received without the approval of the chair will not be considered.</h3>
								<div class="form-group">
									<label for="deptChairApproval">{{isChair ? "Your Approval ("+(maxProposalSummary-formData.proposalSummary.length)+" characters remaining):" : "Department Chair Approval:"}}</label>
									<input type="text" class="form-control" ng-model="formData.deptChairApproval" ng-disabled="{{!isChair ? true : false}}" id="deptChairApproval" name="deptChairApproval" placeholder="{{isChair ? 'Type Your Full Name Here' : 'Department Chair Must Type Name Here'}}" />
								</div>
							</div>
							<div class="col-md-3"></div>
						</div>


						<?php if($isApprover || $isAdmin) { ?>
						<div class="row">
						<!--EMAIL EDIT-->
							<div class="col-md-12">
								<div class="form-group">
									<label for="finalE">EMAIL TO BE SENT:</label>
									<textarea class="form-control" ng-model="formData.finalE" id="finalE" name="finalE" placeholder="Enter email body, with greetings." rows=20 /></textarea>
								</div>
							</div>
						</div>
						<div class="row">
						<!--AMOUNT AWARDED-->
							<div class="col-md-12">
								<div class="form-group">
									<label for="aAw">AMOUNT AWARDED($):</label>
									<input type="text" class="form-control" ng-model="formData.aAw" id="aAw" name="aAw" placeholder="AMOUNT AWARDED" value="<?php echo $app->amountAwarded; ?>" onkeypress='return (event.which >= 48 && event.which <= 57) 
									|| event.which == 8 || event.which == 46' />
								</div>
							</div>
						</div>
						<?php } ?>
						<br><br>

						<a href="#" id="search-btn" ng-click="processForm()">submit</a>
						
						<div class="row">
							<div class="col-md-2"></div>
						<!--SUBMIT BUTTONS-->
							<div class="col-md-6">
								<?php if($isCreating){ //show submit application button if creating ?>
									<input type="submit" onclick="return confirm ('By submitting, I affirm that this work meets university requirements for compliance with all research protocols.')" class="btn btn-success" id="submitApp" name="submitApp" value="SUBMIT APPLICATION" />
								<?php }else if($isAdminUpdating){ //show submit edits button if editing?>
									<input type="submit" onclick="return confirm ('By submitting, I affirm that this work meets university requirements for compliance with all research protocols.')" class="btn btn-success" id="submitApp" name="submitApp" value="SUBMIT EDITS" />
								<?php }else if($isAdmin || $isApprover){ //show approve, hold, and deny buttons if admin or approver ?>
									<input type="submit" onclick="return confirm ('By confirming, your email will be sent to the applicant! Are you sure you want to approve this application?')" class="btn btn-success" id="approveApp" name="approveA" value="APPROVE APPLICATION" <?php if(isApplicationSigned($conn, $idA) == 0) { ?> disabled="true" <?php } ?> />
									<input type="submit" onclick="return confirm ('By confirming, your email will be sent to the applicant! Are you sure you want to place this application on hold?')" class="btn btn-primary" id="holdApp" name="holdA" value="PLACE APPLICATION ON HOLD" />
									<input type="submit" onclick="return confirm ('By confirming, your email will be sent to the applicant! Are you sure you want to deny this application?')" class="btn btn-danger" id="denyApp" name="denyA" value="DENY APPLICATION" />
								<?php }else if($isChair){ //show sign button if dep chair ?>
									<input type="submit" onclick="return confirm ('By approving this application, you affirm that this applicant holds a board-appointed faculty rank and is a member of the bargaining unit.')" class="btn btn-success" id="signApp" name="signApp" value="APPROVE APPLICATION" />
								<?php }else if($isReviewing){ ?>
									<input type="submit" class="btn btn-primary" id="uploadDocs" name="uploadDocs" value="UPLOAD MORE DOCUMENTS" />
								<?php } ?>
							</div>
							<div class="col-md-2">
								<a href="index.php" <?php if($isCreating || $isAdminUpdating || $isAdmin || $isApprover || $isChair){ ?> onclick="return confirm ('Are you sure you want to leave this page? Any unsaved data will be lost.')" <?php } ?> class="btn btn-info">LEAVE PAGE</a>
							</div>
							<div class="col-md-2"></div>
						</div>
					</form>
					
					<!-- SHOW DATA FROM INPUTS AS THEY ARE BEING TYPED -->
					<pre>
						{{ formData }}
					</pre>
					<pre>
						{{ formData.budgetItems }}
					</pre>

				</div>


				


			</div>
			<!--BODY-->
		
		<?php
		}
		else{
		?>
			<h1>You do not have permission to access an application!</h1>
		<?php
		}
		?>

	</div>	
	</body>
	
	<!-- AngularJS Script -->
	<script>
		
		var myApp = angular.module('HIGE-app', []);
		
		myApp.controller('appCtrl', ['$scope', '$http', '$sce', '$filter', function($scope, $http, $sce, $filter){

			/*Functions*/

			// process the form (AJAX request)
			$scope.processForm = function() {

				$sendData = JSON.parse(JSON.stringify($scope.formData)); //create a deep copy of the formdata to send. This almost formats the dates to a good format, but also tacks on a timestamp such as T04:00:00.000Z, which still needs to be removed.
				//alert("date null? " + ($sendData.travelTo == null));
				//alert($sendData.travelTo);
				
				if($sendData.travelTo != null){		$sendData.travelTo = $sendData.travelTo.substr(0,$sendData.travelTo.indexOf('T'));} //Remove everything starting from 'T'
				if($sendData.travelFrom != null){	$sendData.travelFrom = $sendData.travelFrom.substr(0,$sendData.travelFrom.indexOf('T'));} //Remove everything starting from 'T'
				if($sendData.activityTo != null){	$sendData.activityTo = $sendData.activityTo.substr(0,$sendData.activityTo.indexOf('T'));} //Remove everything starting from 'T'
				if($sendData.activityFrom != null){	$sendData.activityFrom = $sendData.activityFrom.substr(0,$sendData.activityFrom.indexOf('T'));} //Remove everything starting from 'T'
				
				$http({
					method  : 'POST',
					url     : 'http://hige-iefdf-vm.wade.wmich.edu/application_form.php?1=1',
					data    : $.param($sendData),  // pass in data as strings
					headers : { 'Content-Type': 'application/x-www-form-urlencoded' }  // set the headers so angular passing info as form data (not request payload)
				})
				.then(function (response) {
					console.log(response, 'res');
					//data = response.data;
					$scope.message = response;
				},function (error){
					console.log(error, 'can not get data.');
				});

			};

			//Add new budget item
			$scope.addInput = function(expense, comment, amount) {
				if(typeof expense === 'undefined'){expense = "Other";}
				if(typeof comment === 'undefined'){comment = "";}
				if(typeof amount === 'undefined'){amount = 0;}
				$scope.formData.budgetItems.push({
					expense: expense,
					comment: comment,
					amount: amount
				})       
			}

			//Remove last budget item
			$scope.remInput = function() {
				if($scope.formData.budgetItems.length > 1)
					$scope.formData.budgetItems.splice($scope.formData.budgetItems.length - 1, 1);
			}

			//Get total budget cost
			$scope.getTotal = function(){
				var total = 0;
				for(var i = 0; i < $scope.formData.budgetItems.length; i++){
					newVal = parseFloat($scope.formData.budgetItems[i]["amount"]);
					if(!isNaN(newVal)){total += newVal;}
				}
				return (total).toFixed(2);
			}

			//function to turn on/off admin updating
			$scope.toggleAdminUpdate = function(){
				$scope.isAdmin = !$scope.isAdmin; //toggle the isAdmin permission
				$scope.isAdminUpdating = !$scope.isAdmin; //set the isAdminUpdating permission to the opposite of isAdmin
				$scope.appFieldsDisabled = $scope.isAdmin; //update the fields to be editable or non-editable
			}


			/*On startup*/

			// create a blank object to hold our form information
			// $scope will allow this to pass between controller and view
			$scope.formData = {};
			$scope.formData.budgetItems = []; //array of budget items
			//expense types
			$scope.options = [{ name: "Air Travel"}, 
								{ name: "Ground Travel"},
								{ name: "Hotel"},
								{ name: "Registration Fee"},
								{ name: "Per Diem"},
								{ name: "Other"}];

			//current date
			$scope.currentDate = <?php echo json_encode($currentDate->format('Y-m-d')); ?>;

			//init vars false
			$scope.allowedFirstCycle = false;
			$scope.shouldWarn = false;

			//get names of this and next cycle
			$scope.thisCycle = <?php echo json_encode(getCycleName($currentDate, false, true)); ?>;
			$scope.nextCycle = <?php echo json_encode(getCycleName($currentDate, true, true)); ?>;

			/*Get user's email address*/
			$CASemail = <?php echo json_encode($CASemail); ?>;

			/*Get character limits from php code*/				
			$scope.maxName = <?php echo json_encode($maxName); ?>;
			$scope.maxDep = <?php echo json_encode($maxDep); ?>;
			$scope.maxTitle = <?php echo json_encode($maxTitle); ?>;
			$scope.maxDestination = <?php echo json_encode($maxDestination); ?>;
			$scope.maxOtherEvent = <?php echo json_encode($maxOtherEvent); ?>;
			$scope.maxOtherFunding = <?php echo json_encode($maxOtherFunding); ?>;
			$scope.maxProposalSummary = <?php echo json_encode($maxProposalSummary); ?>;
			$scope.maxDepChairSig = <?php echo json_encode($maxDepChairSig); ?>;
			$scope.maxBudgetComment = <?php echo json_encode($maxBudgetComment); ?>;
			
			
			/*Get user permissions from php code*/
			$scope.isCreating = <?php echo json_encode($isCreating); ?>;
			$scope.isReviewing = <?php echo json_encode($isReviewing); ?>;
			$scope.isAdmin = <?php echo json_encode($isAdmin); ?>;
			$scope.isAdminUpdating = <?php echo json_encode($isAdminUpdating); ?>;
			$scope.isCommittee = <?php echo json_encode($isCommittee); ?>;
			$scope.isChair = <?php echo json_encode($isChair); ?>;
			$scope.isChairReviewing = <?php echo json_encode($isChairReviewing); ?>;
			$scope.isApprover = <?php echo json_encode($isApprover); ?>;

			/*If not creating, get app data and populate entire form*/
			if(!$scope.isCreating)
			{
				$app = <?php echo json_encode($app); ?>; //app data from php code
				$scope.formData.updateID = $app.id; //set the update id for the server
				$scope.dateSubmitted = $app.dateSubmitted; //set the submission date

				//overwrite the cycle & nextCycle based off the date submitted, not the current date
				$scope.thisCycle = <?php echo json_encode(getCycleName($submitDate, false, true)); ?>;
				$scope.nextCycle = <?php echo json_encode(getCycleName($submitDate, true, true)); ?>;

				//disable app inputs
				$scope.appFieldsDisabled = true;

				//populate the form with the app data
				$scope.formData.cycleChoice = $app.nextCycle ? "next" : "this";
				$scope.formData.name = $app.name;
				$scope.formData.email = $app.email;
				$scope.formData.department = $app.department;
				$scope.formData.deptChairEmail = $app.deptChairEmail;
				//dates require a bit of extra work to convert properly! Javascript offsets the dates based on timezones, and one way to combat that is by replacing hyphens with slashes (don't ask me why)
				/*alert(new Date($app.travelFrom));
				alert(new Date($app.travelFrom.replace(/-/g, '\/')));*/
				$scope.formData.travelFrom = new Date($app.travelFrom.replace(/-/g, '\/'));
				$scope.formData.travelTo = new Date($app.travelTo.replace(/-/g, '\/'));
				$scope.formData.activityFrom = new Date($app.activityFrom.replace(/-/g, '\/'));
				$scope.formData.activityTo = new Date($app.activityTo.replace(/-/g, '\/'));
				$scope.formData.title = $app.title;
				$scope.formData.destination = $app.destination;
				$scope.formData.amountRequested = $app.amountRequested;
				//check boxes using conditional (saved as numbers; need to be converted to true/false)
				$scope.formData.purpose1 = $app.purpose1 ? true : false;
				$scope.formData.purpose2 = $app.purpose2 ? true : false;
				$scope.formData.purpose3 = $app.purpose3 ? true : false;
				$scope.formData.purpose4OtherDummy = $app.purpose4 ? true : false; //set to true if any value exists
				$scope.formData.purpose4Other = $app.purpose4;
				$scope.formData.otherFunding = $app.otherFunding;
				$scope.formData.proposalSummary = $app.proposalSummary;
				$scope.formData.goal1 = $app.goal1 ? true : false;
				$scope.formData.goal2 = $app.goal2 ? true : false;
				$scope.formData.goal3 = $app.goal3 ? true : false;
				$scope.formData.goal4 = $app.goal4 ? true : false;

				//add the budget items
				for($i = 0; $i < $app.budget.length; $i++) {
					$scope.addInput($app.budget[$i][2], $app.budget[$i][4], $app.budget[$i][3]);
				}
	
				$scope.formData.deptChairApproval = $app.deptChairApproval;
			}
			else //otherwise, only fill in a few fields
			{
				//find out if user is allowed to create application for the first available cycle
				$scope.allowedFirstCycle = <?php echo json_encode(isUserAllowedToCreateApplication($conn, $CASbroncoNetID, $CASallPositions, false)); ?>;
				
				//find out if the submission warning should display
				$scope.shouldWarn = <?php echo json_encode($isCreating && isWithinWarningPeriod($currentDate)); ?>;

				if($scope.allowedFirstCycle)
				{
					$scope.formData.cycleChoice = "this"; //set default cycle to this cycle
				}
				else
				{
					$scope.formData.cycleChoice = "next"; //set default cycle to next cycle
				}
				//by default, set the email field to this user's email
				$scope.formData.email = $CASemail;

				//add a few blank budget items
				$scope.addInput();
				$scope.addInput();
				$scope.addInput();
			}

		}]);
	
	</script>
	<!-- End Script -->
</html>
<?php
	$conn = null; //close connection
?>