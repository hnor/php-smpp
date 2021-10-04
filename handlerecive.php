<?php


	$rcvd_pdu=explode('|',$argv[1]);//id-status-seq
	$result=explode(' ',$argv[2]);
	$opr=$argv[3];
	switch($rcvd_pdu[0]){
		case '5':
			switch($opr){
				case 'Mci':
					$id=explode(":",$result[0]);
					$ds=explode(":",$result[7]);
					$dt=explode(":",$result[6]);
					file_put_contents('/home/Payam/log/hist/'.$opr.'_deliver_sm_'.$rcvd_pdu[2],trim(hexdec($id[1])) . PHP_EOL . trim($ds[1]) . PHP_EOL . $dt[1] . PHP_EOL);
				break;
				case 'Mtn':
					$id=explode(":",$result[0]);
					$ds=explode(":",$result[5]);
					$dt=explode(":",$result[4]);
					file_put_contents('/home/Payam/log/hist/'.$opr.'_deliver_sm_'.$rcvd_pdu[2],floatval($id[1]) . PHP_EOL . trim($ds[1]) . PHP_EOL . $dt[1] . PHP_EOL);
				break;
				case 'Rightel':
					$id=explode(":",$result[0]);
					$ds=explode(":",$result[7]);
					$dt=explode(":",$result[6]);
					file_put_contents('/home/Payam/log/hist/'.$opr.'_deliver_sm_'.$rcvd_pdu[2],$id[1] . PHP_EOL . trim($ds[1]) . PHP_EOL . $dt[1] . PHP_EOL);
				break;
			}
		break;
		case '2147483652': //submit_sm_resp
			switch($opr){
				case 'Mci':
					file_put_contents('/home/Payam/log/hist/'.$opr.'_submitsm_resp_'.$rcvd_pdu[2],preg_replace('/[^0-9]/', '',$argv[2]));
				break;
				case 'Mtn':
				case 'Rightel':
					file_put_contents('/home/Payam/log/hist/'.$opr.'_submitsm_resp_'.$rcvd_pdu[2],hexdec($argv[2]));
				break;
				// case 'Rightel':
					// file_put_contents('/home/Payam/log/hist/'.$opr.'_submitsm_resp_'.$rcvd_pdu[2],preg_replace('/[^0-9]/', '',$argv[2]));
				// break;
			}
		break;
		case '2147483669': //Enquir_link_resp
		break;
		case '':
			
		break;
	}
?>