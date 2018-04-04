<?php


/* HOLDS INFO ABOUT A FU REPORT - SELF EXPLANATORY */
	if(!class_exists('FUReport')){
		class FUReport{
			public $tStart;	//travel start 			(DATE)
			public $tEnd;	//travel end 			(DATE)
			public $aStart;	//event start 			(DATE)
			public $aEnd;	//event end 			(DATE)
			public $pS;		//project summary 		(STRING)
			public $awardedS; //						(DECIMAL)
			
			/*Constructor(for everything except budget); just pass in the application array received from the database call (SELECT * FROM applications ...)*/
			public function __construct($appInfo) {
				$this->tStart = $appInfo[1];
				$this->tEnd = $appInfo[2];
				$this->aStart = $appInfo[3];
				$this->aEnd = $appInfo[4];
				$this->pS = $appInfo[5];
				$this->awardedS = $appInfo[6];
			}

		}
	}

	/* HOLDS INFO ABOUT AN APPLICATION - SELF EXPLANATORY */
	if(!class_exists('Application')){
		class Application{
			public $id; 	//id of application		(INT)
			public $bnid;	//bronco net id 		(STRING)
			public $name;	// 						(STRING)
			public $dateS;	//date submitted 		(DATE)
			public $dept;	// 						(STRING)
			//public $deptM;	//department mailstop 	(INT)
			public $email;	// 						(STRING)
			public $rTitle;	//title of event 		(STRING)
			public $tStart;	//travel start 			(DATE)
			public $tEnd;	//travel end 			(DATE)
			public $aStart;	//event start 			(DATE)
			public $aEnd;	//event end 			(DATE)
			public $dest;	// 						(STRING)
			public $aReq;	//amount request		(DECIMAL)	
			public $pr1;	//is research			(BOOLEAN)
			public $pr2;	//is conference 		(BOOLEAN)
			public $pr3;	//is creative activity 	(BOOLEAN)
			public $pr4;	//is other event text	(STRING)
			public $oF;		//other funding 		(STRING)
			public $pS;		//proposal summary 		(STRING)
			public $fg1;	//goals 				(BOOLEAN)
			public $fg2;	//goals 				(BOOLEAN)
			public $fg3;	//goals 				(BOOLEAN)
			public $fg4;	//goals 				(BOOLEAN)
			public $deptCE;	//dep. chair email 		(STRING)
			public $deptCS;	//dep. chair signature 	(STRING)
			public $budget; //						(ARRAY of Budget Objects)
			public $status;	//true=approved,false=denied,null=pending (BOOLEAN)
			public $awarded; //						(DECIMAL)
			public $onHold; //true = on hold		(BOOLEAN)
			public $nextCycle; //true=submitted for next cycle, false=current (BOOLEAN)
			
			/*Constructor(for everything except budget); just pass in the application array received from the database call (SELECT * FROM applications ...)*/
			public function __construct($appInfo) {
				$this->id = $appInfo[0]; 
				$this->bnid = $appInfo[1];
				$this->name = $appInfo[3];
				$this->dateS = $appInfo[2];
				$this->dept = $appInfo[4];
				//$this->deptM = $appInfo[5];
				$this->email = $appInfo[6];
				$this->rTitle = $appInfo[7];
				$this->tStart = $appInfo[8];
				$this->tEnd = $appInfo[9];
				$this->aStart = $appInfo[10];
				$this->aEnd = $appInfo[11];
				$this->dest = $appInfo[12];
				$this->aReq = $appInfo[13];
				$this->pr1 = $appInfo[14];
				$this->pr2 = $appInfo[15];
				$this->pr3 = $appInfo[16];
				$this->pr4 = $appInfo[17];
				$this->oF = $appInfo[18];
				$this->pS = $appInfo[19];
				$this->fg1 = $appInfo[20];
				$this->fg2 = $appInfo[21];
				$this->fg3 = $appInfo[22];
				$this->fg4 = $appInfo[23];
				$this->deptCE = $appInfo[24];
				$this->deptCS = $appInfo[25];
				$this->status = $appInfo[26];
				$this->awarded = $appInfo[27];
				$this->onHold = $appInfo[28];
				$this->nextCycle = $appInfo[29];
			}
			
			/*Return a text representation of the status boolean*/
			public function getStatus(){
				$currentStatus = "Hold";
				//echo "Real Status: ".$this->status.".";
				
				if($this->onHold == 0)
				{
					if(isset($this->status))
					{
						if($this->status == 0){$currentStatus = "Denied";}
						if($this->status == 1){$currentStatus = "Approved";}
					}
					else
					{
						$currentStatus = "Pending";
					}
				}
				
				return $currentStatus;
			}
		}
	}

?>