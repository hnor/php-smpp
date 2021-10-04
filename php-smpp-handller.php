<?php

class handller {

	var $socket=0;
	var $seq=0;
	var $debug=0;
	var $data_coding=0;
	var $timeout = 1;
	var $multipart=0;
	var $multipart_len=0;
	var $connection_type=2;
	var $debug_str=array();
	
	//////////////////////////////////////////////////
	function bind_receiver($host,$port,$system_id,$password){
		$this->connection_type=1;
		return $this->open($host,$port,$system_id,$password);
	}
	function bind_transmitter($host,$port,$system_id,$password){
		$this->connection_type=2;
		return $this->open($host,$port,$system_id,$password);
	}
	function bind_transceiver($host,$port,$system_id,$password){
		$this->connection_type=9;
		return $this->open($host,$port,$system_id,$password);
	}
	function unbind(){
		return $this->close();
	}
	function open($host,$port,$system_id,$password) {
		$this->socket = fsockopen($host, $port, $errno, $errstr, $this->timeout);
		if($this->socket===false){
			if($this->debug)$this->debug_str[]="fsockopen error[$errstr ($errno)]";
			return false;
		}
		if(function_exists('stream_set_timeout'))
			stream_set_timeout($this->socket, $this->timeout);
		if($this->debug)$this->debug_str[]="Connected" ;
		$data  = sprintf("%s\0%s\0", $system_id, $password); // system_id, password 
		$data .= sprintf("%s\0%c", "SMPP", 0x34);  // system_type, interface_version
		$data .= sprintf("%c%c%s\0", 0, 0, ""); // addr_ton, addr_npi, address_range 
		$this->seq+=1;
		$ret = $this->send_pdu($this->connection_type, $data,$this->seq);
		if($this->debug)$this->debug_str[]="Bind done!($s)" ;
		return $ret;
	}
	function close(){
		$this->seq+=1;
		$this->send_pdu(6, "",$this->seq );
		fclose($this->socket);
		if($this->debug)$this->debug_str[]="Unbind done!";
	}
	//////////////////////////////////////////////////
	function read_socket(&$res){
		$data = fread($this->socket, 4);
		$tmp = unpack('Nlength', $data);
		$command_length = $tmp['length'];
		if($command_length<12){
			return null;
		}
		// Get response 
		$data = fread($this->socket, $command_length-4);
		$dt=str_ireplace("\x01",'',$data);
		$dt=str_ireplace("\x02",'',$dt);
		$dt=str_ireplace("\x04",'',$dt);
		$regex = <<<'END'
		/
		(
			(?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
			|   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
			|   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
			|   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
			){1,100}                        # ...one or more times
		)
		| .                                 # anything else
	/x
END;
		$res=preg_replace($regex, '$1', $dt);
		$pdu = unpack('Nid/Nstatus/Nseq', $data);
		return $pdu;
	}
	//////////////////////////////////////////////////
	function submit_enquirLink(){
		$this->seq+=1;
		$this->send_pdu(0x15, '',$this->seq);
	}
	function submit_sm($source_addr,$destintation_addr,$short_message,$sequence,$optional='') {
		$simulate=file_get_contents('/var/www/html/Payam/back/sim');
		$simulate=trim(str_ireplace('\r\n','',$simulate));
		if($simulate){
			$w=rand(10000,30000);
			usleep($w);
			return 'sm'.rand(111,999).$w;
		}
		$data  = sprintf("%s\0", ""); // service_type
		$data .= sprintf("%c%c%s\0", 5,0,$source_addr); // source_addr_ton, source_addr_npi, source_addr
		$data .= sprintf("%c%c%s\0", 1,0,$destintation_addr); // dest_addr_ton, dest_addr_npi, destintation_addr
		$data .= sprintf("%c%c%c",$this->multipart,0,0); // esm_class, protocol_id, priority_flag
		$data .= sprintf("%s\0%s\0", "",""); // schedule_delivery_time, validity_period
		$data .= sprintf("%c%c", 1,0); // registered_delivery, replace_if_present_flag
		$data .= sprintf("%c%c", $this->data_coding,0); // data_coding, sm_default_msg_id
		$data .= sprintf("%c", strlen($short_message) + $this->multipart_len );// sm_length, short_message
		$data .= sprintf("%s", $optional.$short_message); // sm_length, short_message
		if($this->debug)$this->debug_str[]="submit_sm PDU[".$data."]";
		$this->send_pdu(4, $data,$sequence);
		if($this->debug)$this->debug_str[]="submit_sm resp[".implode('|',$ret)."]";
		return $sequence;
	}
	function deliver_sm_resp($pdu_seq,$MsgId) {
		$res='';
		if($this->debug)$this->debug_str[]="send deliver_sm_resp PDU";
		$ret = $this->send_pdu_resp(0x80000005,$pdu_seq, $MsgId,$res);
		if($this->debug)$this->debug_str[]="deliver_sm_resp answer[".implode('|',$ret)."] ($res)";
		return $ret;
	}
	//////////////////////////////////////////////////
	function send_pdu($id,$data,$sequence) {
		$pdu = pack('NNNN', strlen($data)+16, $id, 0, $sequence) . $data;
		if($this->debug)$this->debug_str[]="binery data to send [".bin2hex($pdu)."]";
		fputs($this->socket, $pdu);
	}
	function send_pdu_resp($id,$pduseq,$data,&$res) {
		$pdu = pack('NNNN', strlen($data)+16, $id, 0, $pduseq) . sprintf("%s",$data);
		if($this->debug)$this->debug_str[]="binery data to send [".bin2hex($pdu)."]";
		$p=fputs($this->socket, $pdu);
		return $pduseq;
	}
	function send_long($source_addr,$destintation_addr,$short_message,$startseq,$utf=0,$flash=0) {
		$res=array();
		$st_seq=intval($startseq);
		if($utf)
			$this->data_coding=0x08;
		if($flash)
			$this->data_coding=$this->data_coding | 0x10;
		if($this->debug)$this->debug_str[]="send long as [$utf] [$flash]";
		$size = strlen($short_message);
		if($utf) $size+=20;
		if ($size<160) { //
			$res[]=$this->submit_sm($source_addr,$destintation_addr,$short_message,++$st_seq);
			if($this->debug)$this->debug_str[]="one part submit [$source_addr][$destintation_addr][$short_message][$res]";
		}
		else { // Multipart
			$sar_msg_ref_num =  rand(1,255);
			$sar_total_segments = ceil(strlen($short_message)/130);
			for($sar_segment_seqnum=1; $sar_segment_seqnum<=$sar_total_segments; $sar_segment_seqnum++) {
				$this->multipart=0x40;
				$this->multipart_len=6;
				$optional='';
				$part = substr($short_message, 0 ,130);
				$short_message = substr($short_message, 130);
				$optional = pack('C*',0x05,0x00,0x03,$sar_msg_ref_num,$sar_total_segments,$sar_segment_seqnum);
				$res[]=$this->submit_sm($source_addr,$destintation_addr,$part,++$st_seq,$optional);
				if($this->debug)$this->debug_str[]="multipart submit[$sar_segment_seqnum] [$source_addr][$destintation_addr][$short_message][$res]";
			}
		}	
		return $res;
	}
}



?>