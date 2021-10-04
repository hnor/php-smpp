<?php


	$rcvd_pdu=explode('|',$argv[1]);//id-status-seq
	$result=explode(' ',$argv[2]);
	$opr=$argv[3];
	switch($rcvd_pdu[0]){
		case '5':
			$id=explode(":",$result[0]);
			$ds=explode(":",$result[7]);
			$dt=explode(":",$result[6]);
			file_put_contents('path-to-save-deliver_sm',trim(hexdec($id[1])) . PHP_EOL . trim($ds[1]) . PHP_EOL . $dt[1] . PHP_EOL);
		break;
		case '2147483652': //submit_sm_resp
			file_put_contents('path-to-save-submit_sm_resp'.$rcvd_pdu[2],hexdec($argv[2]));
		break;
		case '2147483669': //Enquir_link_resp
		break;
		case '':
			
		break;
	}
?>