<?php
require_once(dirname(__FILE__).'/php-smpp-handller.php');
$fn=current(explode('.',basename(__file__)));
$smscinf=GetOperatorInfo(); //an list from smsc info
$first=true;
$idle_time=0;
StartService:
$s = new handller();
$s->debug=false;
$s->bind_transceiver($smscinf['ServiceIP'],$smscinf['ServicePort'],$smscinf['ServiceUname'],$smscinf['ServicePassword']);
	StartRecive($s,$smscinf);
	while(true){
		if($idle_time>=30){
			if(!$first)$s->submit_enquirLink();
			$idle_time=0;
		}
		try{
			$qs=ListOfMsgData();  //replace with any func to list sms
			if(count($qs)>0){
				foreach($qs as $q){
						$msg=$q['Message'];
						$message = iconv('UTF-8','UTF-16BE',$msg);
						$size=strlen($message)+20;
						$mscnt=( $size<160 ? 1 : ceil(strlen($message)/130) );
						$f_arr[]=array(
										$q['clid'], 					// 0:CallerId
										$q['dest'],					    // 1:Target num
										$message,						// 2:converted msg content
										'0',		                    // 3:SmsType  0=>simple , 1=>flush
										$s->seq,						// 4:start seq
                                        $msg                            // 5:not converted msg for log only
                                    );
						$s->seq+=$mscnt;
						if(count($f_arr)>20){
							Execute($f_arr,$s);
							$f_arr=array();
							usleep(300);
						}
				}
				if(count($f_arr)>0){
					Execute($f_arr,$s);
					$f_arr=array();
				}
				$idle_time=0;
			}
			else{
				sleep(1);
				$idle_time++;
			}
			if($s->debug){
				if(count($s->debug_str)>0){
					print_r($s->debug_str);
                    $s->debug_str=array();
				}
			}
		}
		catch(Exception $e){
			sleep(1);
			$idle_time++;
		}
		$first=false;
	}
$s->close();

function Execute($TaskArray,$Sock){
	global $fn;
		$pid = pcntl_fork();
		if ($pid == -1){
			exit(2);
		}
		else if ($pid == 0) {
			pcntl_wait($pid);
		}
		else{
			foreach($TaskArray as $i=> $task){  
                                //$source_addr,$destintation_addr,$short_message,$seq,$utf=0,$flash=0
				$ans=$Sock->send_long($task[0],$task[1],$task[2],$task[4],true,$task[3]);
				if(count($ans)>1)$ans=implode('|',$ans);
				else $ans=$ans[0];
				$sm=str_ireplace("\r\n"," ",str_ireplace("\n"," ",$task[5]));
				$log=array( 
						$task[4],		// UserId
						$task[0],		// CallerId
						$task[1],		// Target
						$sm,			// Message
						$task[3],		// Stype
						$ans,			// Result
						);
                // save log per msg
				file_put_contents(dirname(__FILE__).'/'.$task[4].'_submit_sm_'.$Sock->seq,implode(PHP_EOL,$log));
			}
			exit(0);
		}
}
function StartRecive($Sock,$SmscInf){
	global $fn;
	$pid = pcntl_fork();
	if ($pid == -1){
		exit(2);
	}
	else if ($pid == 0) {
		pcntl_wait($pid);
	}
	else{
		while(true){
			$res="";
			$rcvd_pdu=$Sock->read_socket($res);
			if($rcvd_pdu){
				$res=preg_replace('/[\x00-\x1F\x7F]/', '', str_ireplace('`','',str_ireplace('"','',str_ireplace("'",'',$res))));
				exec('/usr/bin/php -f '.dirname(__FILE__).'/HandleRecive.php "'.implode('|',$rcvd_pdu).'" "'.$res.'" "'.$opr[0]['OprTag'].'" &');
				$result=explode(' ',$res);
				switch($rcvd_pdu['id']){
					case '5':
						$id=explode(":",$result[0]);
						$ds=explode(":",$result[7]);
						$dt=explode(":",$result[6]);
						$snd=$Sock->deliver_sm_resp($rcvd_pdu['seq'],$id[1]);
					break;
					case '2147483652': //submit_sm_resp

					break;
					case '':
						
					break;
				}
			}
		}
		exit(0);
	}
	return $pid;
}

?>

