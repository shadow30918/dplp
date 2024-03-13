<?php
/*
func=CityStoreListByEvent
列出這個活動的城市(如果這個城市沒有櫃點, 或該櫃點可登記數量為0,, 或該櫃點己經登記額滿, 或該櫃點藏就不會列出)及櫃點
output : OK(1=成功,0=失敦), cityRows(縣市資訊), storeRows(櫃點資訊)
=========================================================================
func=SubmitForm
接收登記者資料
input : c_no(縣市編號), s_no(櫃點編號), t_name(登記者姓名), t_mobile (登記者手機)
-------------------------------------------------------------------------
output : OK(0=失敗), status(1=有欄位未填 / 2=有欄位格式不符 / 3=縣市跟櫃點對不起來 / 4=己額滿 / 5=手機己登記,且仍在可領取時間內 / 6=己兌換 / 7=該手機是黑名單 / 8=Email己登記 / 9=不明錯誤)
output : fields(status=1 & 2 時才有, 列出那些欄位未填)
-------------------------------------------------------------------------
output : OK(1=成功), status(1=未登記過的登記者 / 2=手機己登記過,但己超過領取時間,所以可再次證記), t_no(登記者編號), token
=========================================================================
func=SentMms
發送簡訊
input : c_no(縣市編號), s_no(櫃點編號), t_no(登記者編號), token (由 SubmitForm 成功時所得到的 token)
output : OK(0=失敗), status(1=有欄位沒填 / 2=有欄位格式不正確 / 3=token錯誤 / 4=縣市跟櫃點對不起來 / 5=查無該名登記者 / 6=簡訊間隔時間未到)
output : remain_time(status=3時才有, 還剩多少時間才能再發簡訊)
-------------------------------------------------------------------------
output : OK(1=成功)
=========================================================================
func=StoreGetInfo
櫃點兌換查詢
input : sn (透過網址取得)
output : OK(0=失敗), status(1=sn沒有值 / 2=兌換碼比對錯誤,查無此人 / 3=己兌換 / 4=己過兌換時間)
-------------------------------------------------------------------------
output : OK(1=成功),
output : info(array, [OK=0 && (status=3 || 4)] || OK=1) 包含 ==> city(縣市名稱), store(櫃點名稱), name(登記者姓名), mobile(登記者手機), expire_date(兌換失效時間)
=========================================================================
func=StoreSubmit
櫃點兌換確認兌換
input : sn (透過網址取得)
output : OK(0=失敗), status(1=sn沒有值 / 2=兌換碼比對錯誤,查無此人 / 3=己兌換 / 4=己過兌換時間)
-------------------------------------------------------------------------
output : OK(1=成功)
*/

//PHP錯誤顯示設定
ini_set("display_errors", "On"); // 顯示錯誤是否打開( On=開, Off=關 )
error_reporting(E_ALL & ~E_NOTICE);

ini_set('memory_limit', '-1');

session_start();

header("Content-Type:text/html; charset=utf-8");

define("CONFIG_DIR",dirname(__FILE__).'/../');
include_once CONFIG_DIR.'db.inc.php';
include_once CONFIG_DIR.'class/common.class.php';
include_once CONFIG_DIR.'class/trigger.class.php';
include_once CONFIG_DIR.'class/mms.class.php';

//設定時區 並 取得目前時間
date_default_timezone_set("Asia/Taipei");
$nowdate = date('Y/m/d H:i:s');

//class init
$common=new Common();

//variable init
$numberFormat="/[0-9]{1,10}/";
$mobileFormat="/[0-9]{10}/";
$json=null;

//定義這是那個活動
//【百優＋怡麗絲爾】=1
//【東京櫃周年慶】=2
//【心機彩粧(MQ)】=3
//【驅黑淨白】=4
$event_no=4;

//兌換頁活動網址
$exchange_url="https://www.shiseido-event.com/2017melanoreduce/exchange.html";


//if (strcmp($_SERVER['HTTP_HOST'],"www.shiseido-event.com")==0 || strcmp($_SERVER['HTTP_HOST'],"shiseido-event.com")==0) {	//只接受本機傳送資料
	if (isset($_POST['func'])) {
		$func=trim($_POST['func']);

		$db=new Database();

		if ($json==null) {
			switch ($func) {
				case "CityStoreListByEvent":
					$sqlStr='SELECT C.* FROM City C, Store S WHERE c_no=s_c_no AND s_e_no=:event_no AND s_req_total>0 AND (s_req_total-s_req_apply)>0 AND s_show=1 Group by c_name ORDER BY c_no ASC';
					$db->query($sqlStr);
					$db->bind(':event_no', $event_no, PDO::PARAM_INT);
					$cityRows = $db->resultset();

					$sqlStr='SELECT c_no, c_name, s_no, s_code, s_name, s_req_total, (s_req_total-s_req_apply) AS s_req_avail FROM Store, City WHERE c_no=s_c_no AND s_e_no=:event_no AND s_req_total>0 AND (s_req_total-s_req_apply)>0 AND s_show=1 order by s_name ASC';
					$db->query($sqlStr);
					$db->bind(':event_no', $event_no, PDO::PARAM_INT);
					$storeRows = $db->resultset();

					$json=array('OK'=>1,'cityRows'=>$cityRows,'storeRows'=>$storeRows);
					break;
				case "SubmitForm":
					if (isset($_POST['c_no']) && isset($_POST['s_no']) && isset($_POST['t_name']) && isset($_POST['t_mobile']) && isset($_POST['t_email'])) {
						$arrInputFields=array('c_no'=>array('int'), 's_no'=>array('int'), 't_name'=>array('string'), 't_mobile'=>array('string',$mobileFormat), 't_email'=>array('string'));
						$c_no=$common->replaceParameter($_POST['c_no']);
						$s_no=$common->replaceParameter($_POST['s_no']);
						$t_name=$common->replaceParameter($_POST['t_name']);
						$t_mobile=$common->replaceParameter($_POST['t_mobile']);
						$t_email=$common->replaceParameter($_POST['t_email']);

						$return_OK=1;
						$return_status=1;
						$return_fields=array();

						//檢查是否有欄位未填
						foreach ($arrInputFields as $field => $value) {
							if (isEmpty($$field)) {
								$return_OK=0;
								$return_status=1;
								array_push($return_fields,$field);
							}
						}

						//檢查欄位格式
						if ($return_OK==1) {
							foreach ($arrInputFields as $field => $value) {
								if (!checkFormatFit($$field, $value[0], $value[1])) {
									$return_OK=0;
									$return_status=2;
									array_push($return_fields,$field);
								}
							}
						}

						//檢查資料庫 ==> City & Store
						if ($return_OK==1) {
							$sqlStr="SELECT COUNT(s_no) AS haveStore,  (s_req_total-s_req_apply) AS reqAvail FROM Store WHERE s_c_no=:c_no AND s_no=:s_no AND s_e_no=:event_no";
							$db->query($sqlStr);
							$db->bind(':c_no', $c_no, PDO::PARAM_INT);
							$db->bind(':s_no', $s_no, PDO::PARAM_INT);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							if ((int)$rows[0]['haveStore']==0) {
								$return_OK=0;
								$return_status=3;
							} else if ((int)$rows[0]['haveStore']==1) {	//有櫃點
								if ((int)$rows[0]['reqAvail']==0) {	//己額滿
									$return_OK=0;
									$return_status=4;
								}
							} else {
								$return_OK=0;
								$return_status=9;
							}
						}

						//檢查資料庫 ==> 黑名單
						if ($return_OK==1) {
							$sqlStr="SELECT COUNT(b_no) AS haveBlocked FROM Blocked WHERE b_mobile=:t_mobile";
							$db->query($sqlStr);
							$db->bind(':t_mobile', $t_mobile);

							$rows = $db->resultset();
							if ((int)$rows[0]['haveBlocked']>0) {	//這支手機己在黑名單中
								$return_OK=0;
								$return_status=7;
							}
						}

						//檢查資料庫 ==> Tester ==> Mobile Duplicate
						if ($return_OK==1) {
							//檢查單一登記者 (Tester) 是否己過領取期限, 如果己經過期, 就可以設定為己不可領取 (也有可能未登記, 或早己不能領取, 那就沒差)
							fixExpireSingleTester($t_mobile, $event_no, $s_no);

							$sqlStr="SELECT t_no, t_is_exchange, t_is_valid, DATE_ADD(t_apply_date, INTERVAL '.EXCHANGE_EXPIRE_DATE.' DAY) as t_expire_date FROM Tester WHERE t_mobile=:t_mobile AND t_e_no=:event_no";
							$db->query($sqlStr);
							$db->bind(':t_mobile', $t_mobile, PDO::PARAM_INT);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							if (count($rows)>0) {	//己有此登記者
								$return_OK=0;
								if ((int)$rows[0]['t_is_exchange']==0 && (int)$rows[0]['t_is_valid']==1) {	//未領取 且 未過領取期限
									$return_status=5;
								}
								if ((int)$rows[0]['t_is_exchange']==1) {	//己兌換
									$return_status=6;
								}
								if ((int)$rows[0]['t_is_exchange']==0 && (int)$rows[0]['t_is_valid']==0) {	//未領取 且 己過期 ==> 可以再次登記
									$return_OK=1;
									$return_status=2;
								}
							}
						}

						//檢查資料庫 ==> Tester ==> Email Duplicate
						if ($return_OK==1) {
							$sqlStr="SELECT t_no, t_is_exchange, t_is_valid, DATE_ADD(t_apply_date, INTERVAL '.EXCHANGE_EXPIRE_DATE.' DAY) as t_expire_date FROM Tester WHERE t_email=:t_email AND t_e_no=:event_no";
							$db->query($sqlStr);
							$db->bind(':t_email', $t_email);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							if (count($rows)>0) {	//己有此登記者
								$return_OK=0;
								if ((int)$rows[0]['t_is_exchange']==0 && (int)$rows[0]['t_is_valid']==1) {	//未領取 且 未過領取期限
									$return_status=8;
								}
							}
						}

						if ($return_OK==0) {
							if ($return_status==1 || $return_status==2) {
								$json=array('OK'=>'0', 'status'=>$return_status, 'fields'=>$return_fields);
							} else {
								$json=array('OK'=>'0', 'status'=>$return_status);
							}
						} else {	//可以登記
							//新增登登記者
							$sqlStr="INSERT INTO Tester(t_e_no, t_c_no, t_s_no, t_name, t_mobile, t_email, t_apply_ip) VALUES(:event_no, :c_no, :s_no, :t_name, :t_mobile, :t_email, :t_apply_ip)";
							$db->query($sqlStr);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);
							$db->bind(':c_no', $c_no, PDO::PARAM_INT);
							$db->bind(':s_no', $s_no, PDO::PARAM_INT);
							$db->bind(':t_name', $t_name);
							$db->bind(':t_mobile', $t_mobile, PDO::PARAM_INT);
							$db->bind(':t_email', $t_email);
							$db->bind(':t_apply_ip', getIP());

							$db->execute();
							$t_no=$db->lastInsertId();

							//產生登記代碼 & 長網址 & 短網址 及 簡訊內容
							$t_sn_no=generateSN($t_no);
							$t_l_url=$exchange_url.'?sn='.$t_sn_no;
							$t_s_url=generateShortUrl($t_l_url);
							$t_mms_text=Mms::GetMmsText($t_no, $t_sn_no, $t_s_url);

							//更新登記者資料
							$sqlStr="UPDATE Tester SET t_sn_no=:t_sn_no, t_l_url=:t_l_url, t_s_url=:t_s_url, t_mms_text=:t_mms_text, t_mms_by_tester=1 WHERE t_no=:t_no";
							$db->query($sqlStr);
							$db->bind(':t_sn_no', $t_sn_no);
							$db->bind(':t_l_url', $t_l_url);
							$db->bind(':t_s_url', $t_s_url);
							$db->bind(':t_mms_text', $t_mms_text);
							$db->bind(':t_no', $t_no, PDO::PARAM_INT);
							$db->execute();

							//發送簡訊
							Mms::Sent($t_no,0);

							//Trigger
							Trigger::TesterApply('AfterInsert', $event_no, $s_no, 0);

							//產生 token, 並把 token 記到 Session
							$accesstoken=generateToken();	//access_token
							$_SESSION['token']=$accesstoken;

							$json=array('OK'=>'1', 'status'=>$return_status, 't_no'=>$t_no, 't_sn_no'=>$t_sn_no, 'token'=>$accesstoken);
						}
					}
					break;
				case "SentMms":
					if (isset($_POST['c_no']) && isset($_POST['s_no']) && isset($_POST['t_no']) && isset($_POST['token'])) {
						$arrInputFields=array('c_no'=>array('int'), 's_no'=>array('int'), 't_no'=>array('int'), 'token'=>array('string'));
						$c_no=$common->replaceParameter($_POST['c_no']);
						$s_no=$common->replaceParameter($_POST['s_no']);
						$t_no=$common->replaceParameter($_POST['t_no']);
						$token=$common->replaceParameter($_POST['token']);

						$return_OK=1;
						$return_status=0;
						$return_remain_time=0;

						//檢查是否有欄位未填
						foreach ($arrInputFields as $field => $value) {
							if (isEmpty($$field)) {
								$return_OK=0;
								$return_status=1;
								array_push($return_fields,$field);
							}
						}

						//檢查欄位格式
						if ($return_OK==1) {
							foreach ($arrInputFields as $field => $value) {
								if (!checkFormatFit($$field, $value[0], $value[1])) {
									$return_OK=0;
									$return_status=2;
									array_push($return_fields,$field);
								}
							}
						}

						//檢查 token
						if ($return_OK==1) {
							if (strcmp($token, $_SESSION['token'])<>0) {
								$return_OK=0;
								$return_status=3;
							} else {
								$_SESSION['token']=$token;	//怕 session 過期, 再覆寫一次
							}
						}

						//檢查資料庫 ==> City & Store
						if ($return_OK==1) {
							$sqlStr="SELECT COUNT(s_no) AS total FROM Store WHERE s_c_no=:c_no AND s_no=:s_no AND s_e_no=:event_no";
							$db->query($sqlStr);
							$db->bind(':c_no', $c_no, PDO::PARAM_INT);
							$db->bind(':s_no', $s_no, PDO::PARAM_INT);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							if ((int)$rows[0]['total']==0) {
								$return_OK=0;
								$return_status=4;
							}
						}

						//檢查資料庫 ==> Tester
						if ($return_OK==1) {
							$sqlStr="SELECT COUNT(t_no) AS total FROM Tester WHERE t_e_no=:event_no AND t_c_no=:c_no AND t_s_no=:s_no AND t_no=:t_no AND t_is_exchange=0 AND t_is_valid=1";
							$db->query($sqlStr);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);
							$db->bind(':c_no', $c_no, PDO::PARAM_INT);
							$db->bind(':s_no', $s_no, PDO::PARAM_INT);
							$db->bind(':t_no', $t_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							if ((int)$rows[0]['total']==0) {
								$return_OK=0;
								$return_status=5;
							}
						}

						//檢查距離上次發送是不是已經超過 MMS_INTERVAL_TIME 分鐘
						if ($return_OK==1) {
							$sqlStr="SELECT m_c_date FROM Mms WHERE m_t_no=:t_no ORDER BY m_no DESC LIMIT 1";
							$db->query($sqlStr);
							$db->bind(':t_no', $t_no, PDO::PARAM_INT);

							$rows = $db->resultset();
							$olddate=$rows[0]['m_c_date'];

							if (count($rows)==1) {
								$min=(strtotime($nowdate) - strtotime($olddate))/ 60;  //計算相差幾分鐘
								if ($min < MMS_INTERVAL_TIME) {	//還不到可以再發放的時間
									$return_OK=0;
									$return_status=6;
									$return_remain_time=round((MMS_INTERVAL_TIME-$min)*10)/10;
								}
							}
						}

						if ($return_OK==0) {
							if ($return_status==6) {
								$json=array('OK'=>'0', 'status'=>$return_status, 'remain_time'=>$return_remain_time);
							} else {
								$json=array('OK'=>'0', 'status'=>$return_status);
							}
						} else {
							//發送檢訊
							Mms::Sent($t_no,0);

							//更新發送次數
							$sqlStr="UPDATE Tester SET t_mms_by_tester=t_mms_by_tester+1 WHERE t_no=:t_no";
							$db->query($sqlStr);
							$db->bind(':t_no', $t_no, PDO::PARAM_INT);
							$db->execute();

							$json=array('OK'=>'1');
						}
					}
					break;
				case "StoreGetInfo":
					if (isset($_POST['sn'])) {
						$sn_no=$common->replaceParameter($_POST['sn']);

						$return_OK=1;
						$return_status=0;
						$arrInfo=array('name'=>'', 'mobile'=>'', 'city'=>'', 'store'=>'', 'expire_date'=>'');

						if (strlen($sn_no)==0) {
							$return_OK=0;
							$return_status=1;
						}

						//比對兌換碼, 確認兌換狀態
						if ($return_OK==1) {
							$sqlStr='SELECT c_name, s_name, t_no, t_name, t_mobile, t_is_exchange, t_is_valid, DATE_ADD(t_apply_date, INTERVAL '.EXCHANGE_EXPIRE_DATE.' DAY) as t_expire_date FROM Tester, Store, City WHERE c_no=t_c_no AND s_no=t_s_no AND t_e_no=:event_no AND t_sn_no=:sn_no';

							$db->query($sqlStr);
							$db->bind(':sn_no', $sn_no);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();

							if (count($rows)==0) {	//找無此人
								$return_OK=0;
								$return_status=2;
							} else {
								$arrInfo['name']=$rows[0]['t_name'];
								$arrInfo['mobile']=$rows[0]['t_mobile'];
								$arrInfo['city']=$rows[0]['c_name'];
								$arrInfo['store']=$rows[0]['s_name'];
								$arrInfo['expire_date']=$rows[0]['t_expire_date'];

								if ((int)$rows[0]['t_is_exchange']==1) {	//己兌換
									$return_OK=0;
									$return_status=3;
								} else if ((strtotime($nowdate) - strtotime($rows[0]['t_expire_date']))>0) {	//己過兌換期間
									if ((int)$rows[0]['t_is_valid']==1) {	//實際上己過期, 但登記者還處於未過期狀態 ==> fixed it
										fixExpireSingleTester($rows[0]['t_mobile'], $event_no, $rows[0]['s_no']);
									}
									$return_OK=0;
									$return_status=4;
								}
							}
						}

						if ($return_OK==0) {
							if ($return_status<=2) {
								$json=array('OK'=>'0', 'status'=>$return_status);
							} else {
								$json=array('OK'=>'0', 'status'=>$return_status, 'info'=>$arrInfo);
							}
						} else {
							$json=array('OK'=>'1', 'info'=>$arrInfo);
						}
					}
					break;
				case 'StoreSubmit':
					if (isset($_POST['sn'])) {
						$sn_no=$common->replaceParameter($_POST['sn']);

						$return_OK=1;
						$return_status=0;
						$t_no=0;
						$s_no=0;

						if (strlen($sn_no)==0) {
							$return_OK=0;
							$return_status=1;
						}

						//比對兌換碼, 確認兌換狀態
						if ($return_OK==1) {
							$sqlStr='SELECT t_no, t_s_no, t_is_exchange, t_is_valid, DATE_ADD(t_apply_date, INTERVAL '.EXCHANGE_EXPIRE_DATE.' DAY) as t_expire_date FROM Tester WHERE  t_e_no=:event_no AND t_sn_no=:sn_no';

							$db->query($sqlStr);
							$db->bind(':sn_no', $sn_no);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);

							$rows = $db->resultset();

							if (count($rows)==0) {	//找無此人
								$return_OK=0;
								$return_status=2;
							} else {
								$t_no=(int)$rows[0]['t_no'];
								$s_no=(int)$rows[0]['t_s_no'];

								if ((int)$rows[0]['t_is_exchange']==1) {	//己兌換
									$return_OK=0;
									$return_status=3;
								} else if ((strtotime($nowdate) - strtotime($rows[0]['t_expire_date']))>0) {	//己過兌換期間
									if ((int)$rows[0]['t_is_valid']==1) {	//實際上己過期, 但登記者還處於未過期狀態 ==> fixed it
										fixExpireSingleTester($rows[0]['t_mobile'], $event_no, $rows[0]['s_no']);
									}
									$return_OK=0;
									$return_status=4;
								}
							}
						}

						if ($return_OK==0) {
							$json=array('OK'=>'0', 'status'=>$return_status);
						} else {
							//更新兌換狀態
							$sqlStr="UPDATE Tester SET t_is_exchange=1, t_exchange_ip=:t_exchange_ip, t_exchange_date=now() WHERE t_no=:t_no AND t_e_no=:event_no AND t_sn_no=:sn_no";
							$db->query($sqlStr);
							$db->bind(':t_exchange_ip', getIP());
							$db->bind(':t_no', $t_no, PDO::PARAM_INT);
							$db->bind(':event_no', $event_no, PDO::PARAM_INT);
							$db->bind(':sn_no', $sn_no);
							$db->execute();

							//trigger
							Trigger::TesterExchangeAfterUpdate($event_no, $s_no, 0, 1, 0);

							$json=array('OK'=>'1');
						}
					}
					break;
			}
		}
		echo json_encode($json);
	}
//}

function getIP() {
  foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
    if (array_key_exists($key, $_SERVER) === true) {
        foreach (explode(',', $_SERVER[$key]) as $ip) {
           if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
              return $ip;
           }
        }
     }
   }
 }

function isEmpty($param) {
	if (strlen($param)==0) {
		return true;
	} else {
		return false;
	}
}

function checkFormatFit($value, $type, $format=null) {
	global $numberFormat;
	$return_status=null;

	switch ($type) {
		case 'int':
			$return_status=preg_match($numberFormat, $value);
			break;
		case 'string':
			if (strlen($format)>0) {
				$return_status=preg_match($format, $value);
			} else {
				$return_status=true;
			}
			break;
	}

	return $return_status;
}

//檢查單一登記者 (Tester) 尚未領取者, 是否己過領取期限, 如果己經過期, 就可以設定為己不可領取 (也有可能未登記, 或早己不能領取, 那就沒差)
//使用時機 ==> 登記者檢查手機號碼是否己登記前
function fixExpireSingleTester($t_mobile,$e_no, $s_no) {
	global $event_no;
	$db=new Database();

	$sqlStr='UPDATE Tester SET t_is_valid=0 WHERE t_apply_date < DATE_SUB(CURRENT_DATE, INTERVAL '.EXCHANGE_EXPIRE_DATE.' DAY) AND t_is_valid=1 AND t_is_exchange=0 AND t_mobile=:t_mobile AND t_e_no=:event_no';
	$db->query($sqlStr);
	$db->bind(':t_mobile', $t_mobile, PDO::PARAM_INT);
	$db->bind(':event_no', $event_no, PDO::PARAM_INT);
	$db->execute();

	$update_count= $db->rowCount();
	if ($update_count>0) {	//有更新
		//檢查這個登記者, 登記的專櫃的 己登記數量 及 己領取數量
		$sqlStr='SELECT s_no, s_req_apply, s_req_exchange, (SELECT COUNT(t_no) FROM Tester WHERE t_e_no=s_e_no AND s_no=t_s_no AND t_is_valid=1) AS actual_req_apply, (SELECT COUNT(t_no) FROM Tester WHERE t_e_no=s_e_no AND s_no=t_s_no AND t_is_exchange=1) AS actual_req_exchange FROM Store WHERE s_no='.$s_no.' AND s_e_no='.$e_no;
		$db->query($sqlStr);
		$rows = $db->resultset();
		foreach($rows as $key=>$value) {
			$updateFields='';
			if ((int)$value['s_req_apply']<>(int)$value['actual_req_apply']) {
				$updateFields=$updateFields.'s_req_apply='.$value['actual_req_apply'];
			}
			if ((int)$value['s_req_exchange']<>(int)$value['actual_req_exchange']) {
				$updateFields=(strlen($updateFields)>0)? $updateFields.',':$updateFields;
				$updateFields=$updateFields.'s_req_exchange='.$value['actual_req_exchange'];
			}
			if (strlen($updateFields)>0) {	//需要更新
				$sqlStr='UPDATE Store SET '.$updateFields.' WHERE s_no='.$value['s_no'];
				$db->query($sqlStr);
				$db->execute();
			}
		}

		//檢查該活動的 可登記總數 及 己登記數量 及 己領取數量
		$sqlStr='SELECT e_no,e_req_total, e_req_apply, e_req_exchange, (SELECT IFNULL(SUM(s_req_total),0) FROM Store WHERE s_e_no=e_no) AS acutal_req_total, (SELECT IFNULL(SUM(s_req_apply),0) FROM Store WHERE s_e_no=e_no) AS acutal_req_apply, (SELECT IFNULL(SUM(s_req_exchange),0) FROM Store WHERE s_e_no=e_no) AS acutal_req_exchange FROM Event WHERE e_no='.$e_no;
		$db->query($sqlStr);
		$rows = $db->resultset();
		foreach($rows as $key=>$value) {
			$updateFields='';
			if ((int)$value['e_req_total']<>(int)$value['acutal_req_total']) {
				$updateFields=$updateFields.'e_req_total='.$value['acutal_req_total'];
			}
			if ((int)$value['e_req_apply']<>(int)$value['acutal_req_apply']) {
				$updateFields=(strlen($updateFields)>0)? $updateFields.',':$updateFields;
				$updateFields=$updateFields.'e_req_apply='.$value['acutal_req_apply'];
			}
			if ((int)$value['e_req_exchange']<>(int)$value['acutal_req_exchange']) {
				$updateFields=(strlen($updateFields)>0)? $updateFields.',':$updateFields;
				$updateFields=$updateFields.'e_req_exchange='.$value['acutal_req_exchange'];
			}
			if (strlen($updateFields)>0) {	//需要更新
				$sqlStr='UPDATE Event SET '.$updateFields.' WHERE e_no='.$value['e_no'];
				$db->query($sqlStr);
				$db->execute();
			}
		}
	}
}

//產生登記代碼
function generateSN($t_no) {
	return $t_no.'_'.substr(md5(rand()),0,SN_STRING_LEN);
}

//產生短網址
function generateShortUrl($long_url) {
	return file_get_contents( 'http://tinyurl.com/api-create.php?url='.urlencode($long_url) );
}

//產生一長串亂數, 作為 check user 是用同一視窗吏用
function generateToken() {
	return substr(md5(rand()),0,32);
}
?>
