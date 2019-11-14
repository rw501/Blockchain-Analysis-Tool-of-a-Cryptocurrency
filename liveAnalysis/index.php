<?php 

/*
######### Useful Mysql Queries:
### Show transaction details
SELECT t.txid, b.hash, i.prevTxid, i.prevN, o.n, o.address, o.value FROM transaction AS t INNER JOIN block AS b ON t.blockhash=b.hash INNER JOIN input AS i ON t.txid=i.txid INNER JOIN output AS o ON t.txid=o.txid WHERE t.`txid` = '5f96508dcd9ac1dbdfd52b2ea28612a191f07d004b42c70b39fc1ce0158ee150'
### List all transactions of given Block(-height)
SELECT t.txid, b.hash, i.prevTxid, i.prevN, o.n, o.address, o.value FROM transaction AS t INNER JOIN block AS b ON t.blockhash=b.hash INNER JOIN input AS i ON t.txid=i.txid INNER JOIN output AS o ON t.txid=o.txid WHERE `height` = 129
### List all incoming tx with their inputs to given address ~90s (first run) ~30s (consecutive run)
SELECT b.height, b.hash as blockhash, i.prevTxid, i.prevN, t.txid, o.n, o.address, o.value FROM transaction AS t INNER JOIN block AS b ON t.blockhash=b.hash INNER JOIN input AS i ON t.txid=i.txid INNER JOIN output AS o ON t.txid=o.txid WHERE o.address = 'DE6vQh5N7XPi1sv8eHgGYN7EtBrSvxqa5H' 
### List all incoming tx to given address ~11s (consecutive run)
SELECT b.height, t.txid, o.n, o.address, o.value FROM transaction AS t INNER JOIN block AS b ON t.blockhash=b.hash INNER JOIN output AS o ON t.txid=o.txid WHERE o.address = 'DE6vQh5N7XPi1sv8eHgGYN7EtBrSvxqa5H'
### List all Source Addresses to given txid <0.1s (consecutive run)
SELECT DISTINCT `address` FROM `input` WHERE `txid` = '08d63bf53263375947c22636dfa632942ad2447a0289185459db070c75608512'
#########
*/


// Report all errors except E_NOTICE
error_reporting(E_ALL & ~E_NOTICE);
// Report all PHP errors (see changelog)
error_reporting(E_ALL);
// Report all PHP errors
error_reporting(-1);
// Same as error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);

##########
##########

function sqlQuery($sql) {
	global $db;
	$result = $db->query($sql) or logDanger($sql.": ".$db->error);
	if ( $result !== TRUE ) { // only return if $result has content
		return $result;
	}
}

function logDanger($msg, $quiet = FALSE) {
	global $msgDanger;
	$msg = ("[ERROR] " . $msg);
	if ( !$quiet ) { // don't use alert box if quiet==FALSE
		$msgDanger .= ($msg . "<br>");
	}
	jsError($msg);
}

function logSuccess ($msg, $quiet = FALSE) {
	global $msgSuccess;
	if ( !$quiet ) { // don't use alert box if quiet==FALSE
		$msgSuccess .= ($msg . "<br>");
	}
	jsLog($msg);
}

function jsLog($msg) {
	echo("
		<script>
			console.log(\"$msg\");
		</script>
	");
}

function jsError($msg) {
	echo("
		<script>
			console.error(\"$msg\");
		</script>
	");
}

function getReceivingTxPlusAddresses( $address ) {
	// get receiving txs (R) and their value
	$sql = "SELECT `txid`, `n`, `value` FROM `output` WHERE `address` = '$address'";
	$result = sqlQuery($sql);
	$rowsR = $result->fetch_all(MYSQLI_ASSOC);
	foreach ( $rowsR as $kR => $vR) {
		$receivingTxs[$vR['txid']]['output'] = $vR['n'];
		$receivingTxs[$vR['txid']]['value'] = $vR['value'];
		$coinsReceived += $vR['value'];
		// get receiving addresses (RA) / source addresses
		$sql = "SELECT `address`, `value` FROM `input` WHERE `txid` = '" . $vR['txid'] . "'";
		$result = sqlQuery($sql);
		$rowsRA = $result->fetch_all(MYSQLI_ASSOC);
		foreach ( $rowsRA as $kRA => $vRA) {
			$sourceAddressesAssoc[$vRA['address']]['txCnt'] += 1; // count number of txs per address
			$sourceAddressesAssoc[$vRA['address']]['value'] += $vRA['value']; // count value of txs per address
		}
	}
	$receivingTxCount = count( $receivingTxs );
	$receivingOutputCount = count( $rowsR );
	$return = array( "receivingTxs" => $receivingTxs, "sourceAddressesAssoc" => $sourceAddressesAssoc, "receivingTxCount" => $receivingTxCount, "receivingOutputCount" => $receivingOutputCount, "coinsReceived" => $coinsReceived );
	return $return;
}

// get sending Txs and target Addresses of given address and return both arrays in one
function getSendingTxPlusAddresses( $address ) {
	// get sending txs (S)
	$sql = "SELECT `txid`, `value` FROM `input` WHERE `address` = '$address'";
	$result = sqlQuery($sql);
	$rowsS = $result->fetch_all(MYSQLI_ASSOC);
	foreach ( $rowsS as $kS => $vS) {
		if ( isset($sendingTxs[$vS['txid']]['inputCnt']) ) { // count number of inputs per txid
			$sendingTxs[$vS['txid']]['inputCnt']++;
		} else {
			$sendingTxs[$vS['txid']]['inputCnt'] = 1;
		}
		$sendingTxs[$vS['txid']]['value'] += $vS['value'];
		$coinsSent += $vS['value'];
		// get sending addresses (SA) / target addresses
		$sql = "SELECT `address`, `value` FROM `output` WHERE `txid` = '" . $vS['txid'] . "'";
		$result = sqlQuery($sql);
		$rowsSA = $result->fetch_all(MYSQLI_ASSOC);
		foreach ( $rowsSA as $kSA => $vSA) {
			$targetAddressesAssoc[$vSA['address']]['txCnt'] += 1; // count number of txs per address
			$targetAddressesAssoc[$vSA['address']]['value'] += $vSA['value']; // count value of txs per address
		}
	}
	$sendingTxCount = count( $sendingTxs );
	$sendingInputCount = count( $rowsS );
	$return = array( "sendingTxs" => $sendingTxs , "targetAddressesAssoc" => $targetAddressesAssoc, "sendingTxCount" => $sendingTxCount, "sendingInputCount" => $sendingInputCount, "coinsSent" => $coinsSent );
	return $return;
}


// return sorted $addressesAssoc and 3 numeric arrays containing Addresses, Occurence and Value with numeric index corresponding to each other
function sortAddressesByCnt( $addressesAssoc ) {
	// prepare output for sendingAddresses ordered by occurence desc
	$addressesCnt = array_column( $addressesAssoc, 'txCnt' );
	$addressesValue = array_column( $addressesAssoc, 'value' );
	array_multisort( $addressesCnt, SORT_DESC, $addressesValue, SORT_DESC, $addressesAssoc );
	$addressesAddress = array_keys( $addressesAssoc );
	$addressesSortedByCnt = array( "addressesAssoc" => $addressesAssoc, "addressesAddress" => $addressesAddress, "addressesCnt" =>  $addressesCnt, "addressesValue" => $addressesValue );
	return $addressesSortedByCnt;
}

// prepare output for Txs, return a html table sorted by tx value desc
// $txs['txid']['output'or'inputCnt'] number of own inputs of given txid
// $txs['txid']['value'] value of own inputs of given txid
function prepareTxTable( $txs ) {
	array_multisort(array_column($txs, 'value'), SORT_DESC, $txs);
	foreach ( $txs as $k => $v ) {
		$txsString .= ("<tr><td>" . $k ." </td><td> " . (isset($v['output'])?$v['output']:$v['inputCnt']) . "</td><td>" . $v['value'] . "</td></tr>"); // Txid: number of inputs
	}
	return $txsString;
}

function prepareAddressTable( $addressesAssoc ) {
	foreach ( $addressesAssoc as $k => $v ) {
		$addressString .= ("<tr><td>" . $k ." </td><td> " . $v['txCnt'] ." </td><td> " . $v['value'] . "</td></tr>"); // Txid: number of inputs
	}
	return $addressString;
}


function filterAddresses( $addressesAddress, $addressesCnt, $addressesValue, $filterAddresses ) {
	// remove own Addresses from Arrays used for chart
	foreach( $filterAddresses as $currentFilterAddress) {
		$filterAddressKey = array_search( $currentFilterAddress, $addressesAddress );
		if( $filterAddressKey !== false ) {
			unset( $addressesAddress[$filterAddressKey] );
			unset( $addressesCnt[$filterAddressKey] );
			unset( $addressesValue[$filterAddressKey] );
		}
	}
	$addressesAddress = array_values( $addressesAddress );
	$addressesCnt = array_values($addressesCnt );
	$addressesValue = array_values($addressesValue );
	$return = array( "addresses" => $addressesAddress, "cnt" => $addressesCnt, "value" => $addressesValue );
	return $return;
}
##########
##########

// connect to database
$db = new mysqli('localhost', 'pivx', 'pivx', 'pivx');
if ($db->connect_error) {
	logDanger(" Unable to connect to database: " . $db->connect_error);
} else {
	logSuccess("Successfully connected to database...", TRUE);
}
if (TRUE !== $db->set_charset("utf8mb4")) {
	logDanger("Unable to set Charset: $db->errno $db->error");
};
sqlQuery("SET collation_connection = utf8mb4_unicode_ci");
sqlQuery("SET character_set_client = utf8");
$db->autocommit(FALSE);



$pubKeyAnalyze=$_GET['pubKeyAnalyze'];

$sql = "SELECT `height` FROM `block` ORDER BY `height` DESC LIMIT 1"; // get block height from db
$bestDbBlockHeight =  sqlQuery($sql)->fetch_row()[0];

//Analyze $pubKeyAnalyze
if($pubKeyAnalyze){
	
	$receivingTxsString = "";
	$sendingTxsString = "";
	$sourceAddressString = "";
	$targetAddressString = "";
	$sendingOutputCount = 0;
	$targetAddressesCount = 0;
	$receivingTxs = [];	// $receivingTxs['txid']['output'] txo
						// $receivingTxs['txid']['value'] value of given txid
	$sendingTxs = [];	// $sendingTxs['txid']['inputCnt'] number of own inputs of given txid
						// $sendingTxs['txid']['value'] value of own inputs of given txid
	$targetAddressesAssoc = []; //$targetAddressesAssoc['targetAddress']['txCnt'] = # of tx to 'targetAddress'
							//$targetAddressesAssoc['targetAddress']]['value'] = total value sent to 'targetAddress'
	$sourceAddressesAssoc = []; // $sourceAddressesAssoc['sourceAddress']['txCnt'] = # of tx to 'targetAddress'
							// $sourceAddressesAssoc['sourceAddress']]['value'] = total value sent to 'targetAddress'
	$coinsReceived = 0;
	$filterAddresses[] = $pubKeyAnalyze; //Array that contains all addresses of the owner
	
	#####
	// Received Txs and Source Addresses
	$getReceivingTxPlusAddresses = getReceivingTxPlusAddresses( $pubKeyAnalyze );
	$receivingTxs = $getReceivingTxPlusAddresses["receivingTxs"];
	$sourceAddressesAssoc = $getReceivingTxPlusAddresses["sourceAddressesAssoc"];
	$receivingTxCount = $getReceivingTxPlusAddresses["receivingTxCount"];
	$receivingOutputCount = $getReceivingTxPlusAddresses["receivingOutputCount"];
	$coinsReceived = $getReceivingTxPlusAddresses["coinsReceived"];
	
	$receivingTxsString = prepareTxTable( $receivingTxs );
	
	$sortedAddressesByCntReturn = sortAddressesByCnt( $sourceAddressesAssoc );
	$sourceAddressesAssocByCnt = $sortedAddressesByCntReturn["addressesAssoc"];
	$sourceAddressesAddressByCnt = $sortedAddressesByCntReturn["addressesAddress"];
	$sourceAddressesCntByCnt = $sortedAddressesByCntReturn["addressesCnt"];
	$sourceAddressesValueByCnt = $sortedAddressesByCntReturn["addressesValue"];
	
	$sourceAddressString = prepareAddressTable( $sourceAddressesAssocByCnt );
	
	$sourceAddressesTotal = count( $sourceAddressesAssoc );
	
	$sourceAddressesFiltered = filterAddresses( $sourceAddressesAddressByCnt, $sourceAddressesCntByCnt, $sourceAddressesValueByCnt, $filterAddresses );
	$sourceAddressesAddressByCntFiltered = $sourceAddressesFiltered["addresses"];
	$sourceAddressesCntByCntFiltered = $sourceAddressesFiltered["cnt"];
	$sourceAddressesValueByCntFiltered = $sourceAddressesFiltered["value"];
	
	#####
	// Sent Txs and Target Addresses
	$getSendingTxPlusAddresses = getSendingTxPlusAddresses( $pubKeyAnalyze );
	$sendingTxs = $getSendingTxPlusAddresses["sendingTxs"];
	$targetAddressesAssoc = $getSendingTxPlusAddresses["targetAddressesAssoc"];
	$sendingTxCount = $getSendingTxPlusAddresses["sendingTxCount"];
	$sendingInputCount = $getSendingTxPlusAddresses["sendingInputCount"];
	$coinsSent = $getSendingTxPlusAddresses["coinsSent"];

	$sendingTxsString = prepareTxTable( $sendingTxs );
	
	$sortedAddressesByCntReturn = sortAddressesByCnt( $targetAddressesAssoc );
	$targetAddressesAssocByCnt = $sortedAddressesByCntReturn["addressesAssoc"];
	$targetAddressesAddressByCnt = $sortedAddressesByCntReturn["addressesAddress"];
	$targetAddressesCntByCnt = $sortedAddressesByCntReturn["addressesCnt"];
	$targetAddressesValueByCnt = $sortedAddressesByCntReturn["addressesValue"];
	
	$targetAddressString = prepareAddressTable( $targetAddressesAssocByCnt );
	$targetAddressesTotal = count( $targetAddressesAssocByCnt );
	
	$targetAddressesFiltered = filterAddresses( $targetAddressesAddressByCnt, $targetAddressesCntByCnt, $targetAddressesValueByCnt, $filterAddresses );
	$targetAddressesAddressByCntFiltered = $targetAddressesFiltered["addresses"];
	$targetAddressesCntByCntFiltered = $targetAddressesFiltered["cnt"];
	$targetAddressesValueByCntFiltered = $targetAddressesFiltered["value"];
	
	
	$addressBalance = $coinsReceived-$coinsSent;
	
	#####
	// find addresses of the same owner
	if( $_GET['ownerAnalyze'] != 0 ) {
	
		$ownerAddresses = [];
		foreach( $sendingTxs as $sendingTx => $value ) { // für jede gesendete Tx
			$sql = "SELECT DISTINCT `address` FROM `input` WHERE `txid` = '$sendingTx'";
			$currentAddresses = sqlQuery($sql)->fetch_all(MYSQLI_ASSOC);
			foreach( $currentAddresses as $k => $currentAddressRow ) {
				if( !in_array( $currentAddressRow['address'], $ownerAddresses) ) {
					$ownerAddresses[] = $currentAddressRow['address'];
				}
			}
		}
		// create Output String of ownerAdresses
		foreach ($ownerAddresses as $k => $ownerAddress ) {
		$ownerAddressesString .= ("<tr><td>" . ($k+1) ." </td><td> " . $ownerAddress . "</td></tr>");
		}
		$ownerAddressesCount = count($ownerAddresses);
	}
	
	
}

?> 

<html>
<head>
	<title>PIVX Address Analysis</title>
	<meta charset="utf-8">
	
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!-- Optional theme -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" integrity="sha256-Uv9BNBucvCPipKQ2NS9wYpJmi8DTOEfTA/nH2aoJALw=" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.css" integrity="sha256-aa0xaJgmK/X74WM224KMQeNQC2xYKwlAt08oZqjeF0E=" crossorigin="anonymous" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.js" integrity="sha256-arMsf+3JJK2LoTGqxfnuJPFTU4hAK57MtIPdFpiHXOU=" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.css" integrity="sha256-IvM9nJf/b5l2RoebiFno92E5ONttVyaEEsdemDC6iQA=" crossorigin="anonymous" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.bundle.min.js" integrity="sha256-xKeoJ50pzbUGkpQxDYHD7o7hxe0LaOGeguUidbq6vis=" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.bundle.js" integrity="sha256-qSIshlknROr4J8GMHRlW3fGKrPki733tLq+qeMCR05Q=" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="style.css">

</head>
<body>



	<div class="alert alert-success alert-dismissible fade in <?=($msgSuccess?'':'hidden');?>">
		<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
		<strong><?=$msgSuccess?></strong> 
	</div>
		
	<div class="alert alert-warning alert-dismissible fade in <?=($msgWarning?'':'hidden');?>">
	<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
	<strong><?=$msgWarning?></strong> 
	</div>

	<div class="alert alert-danger alert-dismissible fade in <?=($msgDanger?'':'hidden');?>">
	<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
	<strong><?=$msgDanger?></strong> 
	</div>

	<form id="formAnalyze" action="" method="get"> 
		<div class="form-group">
				<label class="col-md-1 col-form-label" for="blockCount"><?= $bestDbBlockHeight ?></label>
			<div class="col-md-9">
				<input id="pubKeyAnalyze" name="pubKeyAnalyze" class="form-control" type="text" placeholder="public key" value="<?=$_GET['pubKeyAnalyze']?>">
				<input id="ownerAnalyze" name="ownerAnalyze" class="form-control hidden" type="text" placeholder="1" value="<?=(isset($_GET['ownerAnalyze'])?$_GET['ownerAnalyze']:'1')?>">
			</div>
			<div class="col-md-2">
				<button type="submit" class="btn btn-primary">Analyze</button>
			</div>
		</div>
	</form>
	
	<div class="chartParent col-md-6" >
		<b> Target Addresses by Occurence (# of Transactions sent): </b>
		<canvas id="chartTargetAddressesCnt"></canvas>
	</div>
	<div class="chartParent col-md-6" >
		<b> Target Addresses by Value (Total Value sent in corresponding tx): </b>
		<canvas id="chartTargetAddressesValue"></canvas>
	</div>
	<div class="chartParent col-md-6" >
		<b> Source Addresses by Occurence (# of Transactions received): </b>
		<canvas id="chartSourceAddressesCnt"></canvas>
	</div>
	<div class="chartParent col-md-6" >
		<b> Source Addresses by Value (Total received in corresponding tx): </b>
		<canvas id="chartSourceAddressesValue"></canvas>
	</div>
	
	<div id="summary" class="<?=($pubKeyAnalyze?'':'hidden');?>" >
		<p>
			<b>Balance:</b> <?= $addressBalance ?>
		</p>
		<p>
			<b>Coins Received:</b> <?= $coinsReceived ?>
		</p>
		<p>
			<b>Coins Sent:</b> <?= $coinsSent ?>
		</p>
		<p>
			<p>
				<b>Target Addresses:</b> <?=$targetAddressesTotal ?>
			</p>
			<p class="scroll">
				<table>
					<tr>
						<th>Target Address</th>
						<th># of Tx Sent</th>
						<th>[Value Sent (in Tx that contained this Address)]</th>
						<?= $targetAddressString ?>
					</tr>
				</table>
			</p>
		</p>
		<p>
			<p>
				<b>Source Addresses:</b> <?= $sourceAddressesTotal ?>
			</p>
			<p class="scroll">
				<table>
					<tr>
						<th>Source Address</th>
						<th># of Tx Received</th>
						<th>[Value Received (in Tx that contained this Address)]</th>
						<?= $sourceAddressString ?>
					</tr>
				</table>
			</p>
		</p>
		<p>
			<p>
				<b>Addresses controlled by the same owner:</b> <?= $ownerAddressesCount ?>
			</p>
			<p class="scroll">
				<table>
					<tr>
						<th>#</th>
						<th>Address</th>
						<?= $ownerAddressesString ?>
					</tr>
				</table>
			</p>
		</p>
		<p>
			<p>
				<b>Sent Transactions (Inputs):</b> <?= $sendingTxCount ?> (<?= $sendingInputCount ?>)
			</p>
			<p class="scroll">
				<table>
					<tr>
						<th>Txid</th>
						<th># of Inputs</th>
						<th>combined Value</th>
						<?= $sendingTxsString ?>
					</tr>
				</table>
			</p>
		</p>
		<p>
			<b>Received Transactions (Inputs):</b> <?= $receivingTxCount ?> (<?= $receivingOutputCount ?>)
		</p>
			<p class="scroll">
			<table>
					<tr>
						<th>Txid</th>
						<th># of Output</th>
						<th> Value</th>
						<?= $receivingTxsString ?>
					</tr>
				</table>
			</p>
		</p>
	</div>

	<div id="debug" class="<?=($debug?'':'hidden');?>">Output of "$debug":
	<pre>
	<?= print_r($debug); ?> 
	</pre>
	</div>

	<script>
		<?php
		
		// convert array to JS
		function getFirstArrayValues( $inputArray , $amount = 1000 ) {
			$i = 0;
			$reducedArray = [];
			while( $i < $amount && isset( $inputArray[$i] ) ) {
				$reducedArray[] = $inputArray[$i];
				$i++;
			}
			return $reducedArray;
		}
		
		$targetAddressesAddressByCntJs = json_encode( getFirstArrayValues( $targetAddressesAddressByCntFiltered ));
		$targetAddressesCntByCntJs = json_encode( getFirstArrayValues( $targetAddressesCntByCntFiltered ));
		$targetAddressesValueByCntJs = json_encode( getFirstArrayValues( $targetAddressesValueByCntFiltered ));
		$sourceAddressesAddressByCntJs = json_encode( getFirstArrayValues( $sourceAddressesAddressByCntFiltered ));
		$sourceAddressesCnByCnttJs = json_encode( getFirstArrayValues( $sourceAddressesCntByCntFiltered ));
		$sourceAddressesValueByCntJs = json_encode( getFirstArrayValues( $sourceAddressesValueByCntFiltered ));
		echo ("
			var targetAddressesAddressByCnt = ".$targetAddressesAddressByCntJs . ";\n
			var targetAddressesCntByCnt = ".$targetAddressesCntByCntJs . ";\n
			var targetAddressesValueByCnt = ".$targetAddressesValueByCntJs . ";\n
			var sourceAddressesAddressByCnt = ". $sourceAddressesAddressByCntJs . ";\n
			var sourceAddressesCntByCnt = ". $sourceAddressesCnByCnttJs . ";\n
			var sourceAddressesValueByCnt = ". $sourceAddressesValueByCntJs . ";\n
			");
		?>
		
		var backgroundColors = ['rgb(204,41,41)','rgb(230,35,23)','rgb(255,31,0)','rgb(179,76,54)','rgb(204,80,41)','rgb(230,86,23)','rgb(255,93,0)','rgb(179,107,54)','rgb(204,120,41)','rgb(230,136,23)','rgb(255,155,0)','rgb(179,137,54)','rgb(204,159,41)','rgb(230,186,23)','rgb(255,216,0)','rgb(179,167,54)','rgb(204,199,41)','rgb(223,230,23)','rgb(232,255,0)','rgb(160,179,54)','rgb(169,204,41)','rgb(173,230,23)','rgb(170,255,0)','rgb(129,179,54)','rgb(130,204,41)','rgb(123,230,23)','rgb(108,255,0)','rgb(99,179,54)','rgb(90,204,41)','rgb(73,230,23)','rgb(46,255,0)','rgb(69,179,54)','rgb(51,204,41)','rgb(23,230,23)','rgb(0,255,15)','rgb(54,179,69)','rgb(41,204,70)','rgb(23,230,73)','rgb(0,255,77)','rgb(54,179,99)','rgb(41,204,110)','rgb(23,230,123)','rgb(0,255,139)','rgb(54,179,129)','rgb(41,204,150)','rgb(23,230,173)','rgb(0,255,201)','rgb(54,179,160)','rgb(41,204,189)','rgb(23,230,223)','rgb(0,247,255)','rgb(54,167,179)','rgb(41,179,204)','rgb(23,186,230)','rgb(0,185,255)','rgb(54,137,179)','rgb(41,140,204)','rgb(23,136,230)','rgb(0,124,255)','rgb(54,107,179)','rgb(41,100,204)','rgb(23,86,230)','rgb(0,62,255)','rgb(54,76,179)','rgb(41,61,204)','rgb(23,35,230)','rgb(0,0,255)','rgb(61,54,179)','rgb(61,41,204)','rgb(61,23,230)','rgb(62,0,255)','rgb(91,54,179)','rgb(100,41,204)','rgb(111,23,230)','rgb(124,0,255)','rgb(122,54,179)','rgb(140,41,204)','rgb(161,23,230)','rgb(185,0,255)','rgb(152,54,179)','rgb(179,41,204)','rgb(211,23,230)','rgb(247,0,255)','rgb(179,54,175)','rgb(204,41,189)','rgb(230,23,198)','rgb(255,0,201)','rgb(179,54,144)','rgb(204,41,150)','rgb(230,23,148)','rgb(255,0,139)','rgb(179,54,114)','rgb(204,41,110)','rgb(230,23,98)','rgb(255,0,77)','rgb(179,54,84)','rgb(204,41,70)','rgb(230,23,48)','rgb(255,0,15)','rgb(179,54,179)'];
		
		// Chart target Addresses Count
		var ctx = document.getElementById('chartTargetAddressesCnt').getContext('2d');
		var myPieChart = new Chart(ctx, {
			type: 'pie',
			data: {
				datasets: [{
					data: targetAddressesCntByCnt,
					backgroundColor: backgroundColors
				}],
				labels: targetAddressesAddressByCnt
			},
			options: {
				legend: {
					display: false
					}
            }
		});
		
		// Chart target Addresses Value
		var ctx = document.getElementById('chartTargetAddressesValue').getContext('2d');
		var myPieChart = new Chart(ctx, {
			type: 'pie',
			data: {
				datasets: [{
					data: targetAddressesValueByCnt,
					backgroundColor: backgroundColors
				}],
				labels: targetAddressesAddressByCnt
			},
			options: {
				legend: {
					display: false
					}
            }
		});
		
		// Chart Source Addresses Count
		var ctx = document.getElementById('chartSourceAddressesCnt').getContext('2d');
		var myPieChart = new Chart(ctx, {
			type: 'pie',
			data: {
				datasets: [{
					data: sourceAddressesCntByCnt,
					backgroundColor: backgroundColors
				}],
				labels: sourceAddressesAddressByCnt
			},
			options: {
				legend: {
					display: false
					}
            }
		});
		
		// Chart Source Addresses Value
		var ctx = document.getElementById('chartSourceAddressesValue').getContext('2d');
		var myPieChart = new Chart(ctx, {
			type: 'pie',
			data: {
				datasets: [{
					data: sourceAddressesValueByCnt,
					backgroundColor: backgroundColors
				}],
				labels: sourceAddressesAddressByCnt
			},
			options: {
				legend: {
					display: false
					}
            }
		});

	</script>

</body>
</html>
