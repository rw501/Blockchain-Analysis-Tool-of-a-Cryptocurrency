Pseudocode
<?
//Blockchainparser
foreach ( block ) {
	get_block_data;
	foreach ( transaction ) {
		get_transaction_data;
		foreach ( input ) {
			get_input_data;
			get_previous_output_data;
		}
		foreach ( output ) {
			get_output_data;
		}
	}
}

//Target Addresses
$address = <address to analyze>
$outgoingTxs = txid FROM input WHERE $address;
foreach ( $outgoingTxs ) {
	$targetAddresses += address FROM output WHERE $outgoingTxs;
}

//Wallet Addresses
foreach ( sent_transaction ) {
	get_inputs_of_tx;
	foreach ( input_of_tx ) {
		get_address_of_input;
	}
}

?>
