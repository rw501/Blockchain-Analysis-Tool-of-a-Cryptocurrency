<?php

// #########
// 1st Block Hash: 000005504fa4a6766e854b2a2c3f21cd276fd7305b84f416241fd4431acbd12d
// #########

// Report all errors except E_NOTICE
error_reporting(E_ALL & ~E_NOTICE);

// Report all PHP errors (see changelog)
//error_reporting(E_ALL);

// Report all PHP errors
//error_reporting(-1);

// Same as error_reporting(E_ALL);
//ini_set('error_reporting', E_ALL);


##########
// CONFIGUARTION

$continuation = 1; 	// if set to 1: continue with latest db block-21;
					// if set to 0: start with block 1, db entries will be overwritten, manual deletion of db entries highly recommended!
$startingBlock = 1;	// default 1; sets the starting block height, will be ignored, if $continuation==1
$verbose = 0;	// if set to 0: only display progress and important info
				// if set to 1: display intermediate steps (will spam the cli)
##########

function verbose($msg="") {
	global $verbose;
	if ($verbose == 1) {
		echo($msg."\n\r");
	}
}

function sqlQuery($sql) {
	global $db;
	//$sql = "SET FOREIGN_KEY_CHECKS = 0; SET UNIQUE_CHECKS = 0; SET AUTOCOMMIT = 0; ".$sql." SET UNIQUE_CHECKS = 1; SET FOREIGN_KEY_CHECKS = 1;";
	verbose($sql);
	$db->begin_transaction(MYSQLI_TRANS_START_READ_WRITE); // for mariadb set "version=10.2.19-MariaDB" in /etc/mysql/my.cnf
	$db->query("SET FOREIGN_KEY_CHECKS = 0;") or exit($sql.": ".$db->error . "\n\r"); // if FKC are not disabled, inputs might not be iserted into Database
//	$db->query("SET UNIQUE_CHECKS = 0;") or exit($sql.": ".$db->error . "\n\r"); // actually slows inserts down
// 	$db->query("SET AUTOCOMMIT = 0;") or exit($sql.": ".$db->error . "\n\r");
	$db->query($sql) or exit("ERROR: ".$sql.": ".$db->error . "\n\r");
// 	$db->query("SET UNIQUE_CHECKS = 1;") or exit($sql.": ".$db->error . "\n\r");
	$db->query("SET FOREIGN_KEY_CHECKS = 1;") or exit($sql.": ".$db->error . "\n\r");
	$db->commit() or exit($sql.": ".$db->error . "\n\r");;
	
	//$db->multi_query($sql)  or exit($sql.": ".$db->error . "\n\r");
	//while ($db->next_result()) {;} // flush multi_queries
	//$db->query($sql) or exit($sql.": ".$db->error . "\n\r");
}

##########


// connect to database
$db = new mysqli('localhost', 'pivx', 'pivx', 'pivx');

if ($db->connect_error) {
	exit("ERROR: Unable to connect to database: " . $db->connect_error . "\n");
}

if (TRUE !== $db->set_charset("utf8mb4")) { //latin1_german1_ci
	print_r($db);
	exit("ERROR: Unable to set Charset: $db->errno $db->error\n");
};
sqlQuery("SET collation_connection = utf8mb4_unicode_ci");
sqlQuery("SET character_set_client = utf8");
$db->autocommit(FALSE);
echo ("Successfully connected to database...\n");

// connect to remote or local pivx node
require_once ('easybitcoin.php');

$pivx = new Bitcoin('robertFjEeMOZutm4t2XHC', 'z7b1FycZQXYno0P7EXWW5Uwhwf5d2VRY', '192.168.178.78', '51473'); //try via remote rpc
if($pivx->getinfo()){
	echo ("Successfully connected to pivxd...\n");
} else {
	$pivx = new Bitcoin('admin', 'admin', 'localhost', '51473'); // try via local rpc
	if($pivx->getinfo()){
		echo ("Successfully connected to pivxd...\n");
	} else {
		exit ("Unable to connect to pivxd. Is it running?\n");
	}
}

// print_r($pivx->getblockchaininfo());

$bestBlockHeight = $pivx->getblockchaininfo() ['blocks'];
$bestBlockHash = $pivx->getblockchaininfo() ['bestblockhash'];
echo ("bestBlockHeight: " . $bestBlockHeight . "\n");

// default starting point at block 1
$currentBlockHeight = $startingBlock; // 1

// delete all db entries newer than starting point-21 blocks 
if ( $continuation == 1 ) {
	$sql = "SELECT `height` FROM `block` ORDER BY `height` DESC LIMIT 1"; // get block height from db
	if ( $db->query($sql)->fetch_row()[0] ) {
		$bestDbBlockHeight =  $db->query($sql)->fetch_row()[0] or exit("ERROR: ".$sql.": ".$db->error . "\n\r");
	} else {
		$bestDbBlockHeight = 1;
	}
	if ( $bestDbBlockHeight > 21) { // newBlockHeight must be at least 1
		$newBlockHeight = $bestDbBlockHeight - 21;
	} else {
		$newBlockHeight = 1;
	}
		echo ("bestDbBlockHeight: ".$bestDbBlockHeight."\n");
	$sql="DELETE FROM block WHERE block.height > ". $newBlockHeight .";"; // delete last 21 blocks from db
	sqlQuery($sql);
	$currentBlockHeight = $newBlockHeight; // 1
}

$currentBlockHash = $pivx->getblockhash($currentBlockHeight);
echo ("Starting at Block: " . $currentBlockHeight . " (".($bestBlockHeight - $currentBlockHeight + 1)." blocks behind)\n");

$startTime = microtime(true);
$lastTime = microtime(true);
$txCounter = 0;
$lastTxCounter = 0;

// insert empty output to satisfy constraints
if ($currentBlockHeight == 1) {
	$sql="REPLACE INTO `block` (`hash`, `height`, `time`) VALUES ('0', '0', 0);";
	sqlQuery($sql);
	$sql="REPLACE INTO `transaction` (`txid`, `blockhash`, `time`) VALUES ('0', '0', 0);";
	sqlQuery($sql);
	$sql="REPLACE INTO `output` (`txid`, `n`, `address`, `value`) VALUES ('0', '0', '0','0');";
	sqlQuery($sql);
}

// iterate over each block
while ($currentBlockHeight <= $bestBlockHeight) // $bestBlockHeight; 129
{
	// progress output
	if ( $currentBlockHeight%50 == 0 ) { 
		$lastTxPS = round($lastTxCounter/(microtime(true)-$lastTime),2);
		$txPS = round($txCounter/(microtime(true)-$startTime),2);
		echo ("[INFO] $lastTxPS tx/s (latest); $txPS tx/s (average over ".round((microtime(true)-$startTime),0)."s); Current Block: $currentBlockHeight (".round((100/$bestBlockHeight*$currentBlockHeight),2)."%)      \r");
		verbose();
		$lastTime = microtime(true);
		$lastTxCounter = 0;
	}

	verbose("Block: " . $currentBlockHeight . ": " . $currentBlockHash);
	
	// insert block into db
	$currentBlockTime = $pivx->getblock($currentBlockHash)['time'];
	$sql="REPLACE INTO `block` (`hash`, `height`, `time`) VALUES ('$currentBlockHash', '$currentBlockHeight', '$currentBlockTime');";
	sqlQuery($sql);
	
	
	// iterate over all tx of current block
	foreach($pivx->getblock($currentBlockHash) ['tx'] as $currentTxid) {
		$mined = false;
		$created = 0;
		$stakeIput = 0;
		$rawTx = $pivx->getrawtransaction($currentTxid, 1);
		if (count($rawTx ['vout']) <= 1 && $rawTx ['vout'] [0] ['value'] == 0) { // ignore tx without an txo, e.g. coinbase
			verbose("ignoring Tx: ".$currentTxid);
			continue;
		}
		verbose("Tx: ".$currentTxid);
		$currentTxTime = $rawTx ['time'];
		$sql="REPLACE INTO `transaction` (`txid`, `blockhash`, `time`) VALUES ('$currentTxid', '$currentBlockHash', $currentTxTime);";
		sqlQuery($sql);
		
		
		verbose("Inputs:");
		// insert inputs into db
		$i = 0; // make zeroCoin inputs unique by counting the occurence of zeroCoin inputs
		foreach($rawTx ['vin'] as $vin) { // iterate over each input
			$inputTx = 0;
			$inputN = 0;
			$iAddress = 0;
			$iValue = 0;
			
			if( !empty($vin['txid']) ){ // transaction with input, not e.g. stake? mining tx
				$inputTx = $vin['txid'];
				$inputN = $vin['vout'];
			}
			// detect zerocoin inputs
			if(isset($vin['scriptSig']['asm']) && $vin['scriptSig']['asm'] == 'OP_ZEROCOINSPEND'){
				$iValue = $vin['sequence'];
				$iAddress = "ZEROCOIN";
				$inputTx = 0;
				$inputN = $i;
				$i++;
			}
			// determine input address and value of input via the prev output
			if ( $inputTx !== 0 && $inputTx != "ZEROCOIN" ) { // transaction with input
				$prevTxOutputs = $pivx->getrawtransaction($inputTx, 1) ['vout'];
				foreach( $prevTxOutputs as $prevTxOutput ) {
					if($prevTxOutput ['n'] == $inputN) {
						$iValue = $prevTxOutput ['value'];
						if(!empty($prevTxOutput['scriptPubKey']['addresses'][0])){ // transparent tx
							$iAddress = $prevTxOutput['scriptPubKey']['addresses'][0];
						}
					}
				}
			} else if ( $inputTx === 0 ) { // if tx has no inputs, e.g. mined block
				$mined = true;
			}
			verbose(" ".$inputTx." ".$inputN.": ".$iValue);
			$sql="REPLACE INTO `input` (`txid`, `prevTxid`, `prevN`, `value`, `address`) VALUES ('$currentTxid', '$inputTx', '$inputN','$iValue','$iAddress');";
			sqlQuery($sql);
		}
		
		
		verbose("Outputs:");
		// insert outputs into db
		foreach($rawTx ['vout'] as $vout) { // iterate over each output
			$n = $vout['n'];
			if(!empty($vout['scriptPubKey']['addresses'][0])){ // transparent tx
				$address = $vout['scriptPubKey']['addresses'][0];
			} else { // other type of output, e.g. zeroCoin
				$address = $vout['scriptPubKey']['type'];
			}
			$oValue = $vout['value'];
			if( $mined === true ) {
				$created = $oValue;
			}
			verbose(" ".$oValue." ".$n." to ".$address);
			$sql="REPLACE INTO `output` (`txid`, `n`, `address`, `value`, `created`) VALUES ('$currentTxid', '$n', '$address','$oValue', '$created');";
			sqlQuery($sql);
		}

	$txCounter++;
	$lastTxCounter++;
	}

	$currentBlockHeight++;
	$currentBlockHash = $pivx->getblock($currentBlockHash) ['nextblockhash'];
}

$db->close();
echo("\n\rScript has been fully executed...");

?> 

