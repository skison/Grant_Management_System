<?php
?>

<div role="banner" class="page-header container-fluid">

	<a id="mainContentLink" href="#MainContent">Jump To Main Content</a>
	
	<div class="row">
		<div class="col-md-4">
			<img src="images/WMU.png" alt="WMU Logo" class="logo" />
			<h1 id="HomeText">Haenicke Institute for Global Education</h1>
		</div>
		<div class="col-md-4"> 
		</div>
		<div class="col-md-4">
			<a href="/" id="HomeLink" class="btn btn-info">Home</a>
			<form id="logoutForm" method="post" action="?logout=">
				<input type="hidden" name="logoutUser" value="logout" /> 
				<input type="submit" class="btn btn-info" id="logoutSub" name="logoutSub" value="Logout" />
			</form>
		</div>
	</div>
	
</div>