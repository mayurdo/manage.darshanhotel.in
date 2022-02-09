<?php
defined('BASEPATH') or exit('No direct script access allowed');
class ApiModel extends CI_Model
{

	public function __construct()
	{
		$this->load->database();
	}
	
	public function updateFcmKey($userId,$token){
	    $sql = "UPDATE `users` SET `fcm_token`='$token' WHERE `user_id`='$userId'";
	    $this->db->query($sql);
	    return true;
	}

	public function getUserInfo($contactNumber = null, $userId = null, $password = null, $type = null,$notType=null)
	{

		$sql = "SELECT * FROM `users` WHERE 1 ";
		if ($userId != null) {
			$sql = $sql . " and `user_id` = '$userId'";
		}
		if ($contactNumber != null) {
			$sql = $sql . " and `contact` = '$contactNumber'";
		}
		if ($password != null) {
			$sql = $sql . " and `password` = SHA1('$password')";
		}

		if ($type != null && $type != 'all') {
			$sql = $sql . " and `type` = '$type'";
		}
		if ($notType != null) {
			$sql = $sql . " and `type` <> '$notType'";
		}

		$sql = $sql . " order by `type`";
		$query = $this->db->query($sql);
		$res =  json_decode(json_encode($query->result()), true);

		if (count($res) > 0) {
			return $res;
		} else {
			return null;
		}
	}



	public function saveUserDetails($name, $contact, $password, $type,$baseSalary)
	{
		$data = array();
		$data['status'] = false;
		$data['message'] = "Cannot create user! Contact Number Already Exists";
		$now = date('Y-m-d H:i:s');
		if ($this->getUserInfo($contact) == null) {
			$sql = "INSERT INTO `users`( `name`, `contact`, `password`,  `type`, `created_on`,`base_salary`) VALUES ('$name','$contact',SHA1('$password'),'$type','$now','$baseSalary')";
			$this->db->query($sql);
			$data['status'] = true;
			$data['message'] = "New User has been created successfully!";
		}
		return $data;
	}

	public function getRoomDetails($roomId = null, $roomNumber = null)
	{
		$sql = "SELECT * FROM `rooms` WHERE 1 ";
		if ($roomId != null) {
			$sql = $sql . " and `room_id` = '$roomId'";
		}
		if ($roomNumber != null) {
			$sql = $sql . " and `room_no` = '$roomNumber'";
		}
		$sql = $sql . " order by `room_no` ASC";


		$query = $this->db->query($sql);
		$res = json_decode(json_encode($query->result()), true);
		
		for($i = 0;$i<count($res);$i++){
		$res[$i]['recentRoomLog']  = $this->getRoomLogForRoomId($res[$i]['room_id'],true);
		if($res[$i]['room_status'] == 'ready'){
		    $res[$i]['readyRoomLog']  = $this->getReadyRoomLog($res[$i]['room_id']);
		    
		}
		}

		if (count($res) > 0) {
			return $res;
		} else {
			return null;
		}
	}



public function getReadyRoomLog($roomId){
    $sql = "SELECT * FROM `room_logs`  INNER JOIN users on users.user_id = `room_logs`.updated_by WHERE `room_id` ='$roomId' AND `status` = 'roomsurvey' limit 1";
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	if(count($res)==0){
	    return null;
	}	
    $roomSurvey = $res[0];
    $lastLogId = $roomSurvey['log_id'];
    
    
    $sql = "SELECT * FROM `room_logs` WHERE `room_id`='$roomId' and `status`='keyin' and `log_id`<'$lastLogId' limit 1";
    
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	if(count($res)==0){
	    return null;
	}	
    $keyIn = $res[0];
    $lastLogId = $keyIn['log_id'];
    $sql = "SELECT * FROM `room_logs` WHERE `room_id`='$roomId' and `status`='keyout' and `log_id`<'$lastLogId' limit 1";
    
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	if(count($res)==0){
	    return null;
	}	
	
	$keyOut = $res[0];
	
// 	print_r("==>KEY IN <==");
// 	print_r($query->result());
// 	print_r("==>KEY IN <==");
	
	$retAry['roomsurveyBy'] = $roomSurvey['name'];
	$retAry['keyIn'] = json_decode($keyIn['meta'],true)['employeeName'];
	$retAry['keyOut'] = json_decode($keyOut['meta'],true)['employeeName'];
	
	
	
	$hoursDifference =0;
			$hoursDifference = round(((strtotime($keyIn['created_on']) - strtotime($keyOut['created_on'])) / 60) / 60, 2);
			/*if($hoursDifference>0.5){
			   $hoursDifference = $hoursDifference-0.5; 
			   $hoursDifference = round($hoursDifference,2);
			}else{
			 $hoursDifference =0;   
			}*/
			$retAry['cleanDelay']=$hoursDifference;
			
// 			print_r($retAry);
			return $retAry;
			

	
    
    
}

public function getRoomLogForRoomId($roomId,$getRecent = false){
    
    $sql="SELECT `room_logs`.`status`,`room_logs`.`meta`, users.name FROM `room_logs` INNER JOIN users on users.user_id = room_logs.updated_by where `room_logs`.`room_id`='$roomId' order by `room_logs`.`created_on` desc";
    
    if($getRecent){
    $sql = $sql." LIMIT 1";   
    }
    
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	if(count($res)>0){
	    if($getRecent){
	        if($res[0]['meta']!=null)
	        $res[0]['meta'] = json_decode($res[0]['meta'],true);
	        if($res[0]['status'] == 'keyin'){
	           $res[0]['meta']['delayBy']  = $this->getRecentKeyInKeyOutDelay($roomId);
	            
	        }
	        
	        if($res[0]['status'] == 'ready'){
	        }
	        return $res[0]  ;
	    }
	    return $res;
	}
	
	return null;
	
    
    
}
public function saveSalary($days_present, $user_id,$base_salary,$bonus,$month,$year){
    $now = date('Y-m-d H:i:s');
    $sql="INSERT INTO `salary_payment`(`user_id`, `days_present`, `base_salary`, `bonus`, `month`, `year`, `created_on`) VALUES ('$user_id',
    '$days_present','$base_salary','$bonus','$month','$year','$now')";
    $this->db->query($sql);
}

public function getRecentKeyInKeyOutDelay($roomId){
    $sql = "SELECT * FROM `room_logs` WHERE `room_id` = '$roomId' and `status` = 'keyout' order by `created_on` desc limit 1";
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	$keyOutTime = $res[0]['created_on'];
	
	$sql = "SELECT * FROM `room_logs` WHERE `room_id` = '$roomId' and `status` = 'keyin' order by `created_on` desc limit 1";
    $query = $this->db->query($sql);
	$res = json_decode(json_encode($query->result()), true);
	
	$keyInTime = $res[0]['created_on'];
	
	$keyOutTime = strtotime($keyOutTime);
	$keyInTime = strtotime($keyInTime);
			
$hoursDifference =0;
			$hoursDifference = round((($keyInTime - $keyOutTime) / 60) / 60, 2);
// 			if($hoursDifference>0.5){
// 			   $hoursDifference = $hoursDifference-0.5; 
// 			}else{
// 			 $hoursDifference =0;   
// 			}
			
			
	
	return $hoursDifference;
		
}
	public function saveNewRoom($roomNumber, $roomStatus = "ready")
	{
		$now = date('Y-m-d H:i:s');
		$response = array();
		$response['status'] = false;
		$response['message'] = "Unable to create room! Room Number already exists";
		if ($this->getRoomDetails(null, $roomNumber) == null) {
			$sql = "INSERT INTO `rooms`(`room_no`, `room_status`,  `created_on`, `last_updated_on`) VALUES ('$roomNumber','$roomStatus','$now','$now')";
			$this->db->query($sql);
			$response['status'] = true;
			$response['message'] = "New Room has been created successfully";
		}
		return $response;
	}

	public function updateRoomStatus($roomId, $status, $employeeId, $employeeName,$managerId)
	{
		$response['status'] = false;
		$response['message'] = "Unable to update the room status";

		if ($this->getRoomDetails($roomId) != null) {
			$now = date('Y-m-d H:i:s');
			
			if($status == 'checkin' || $status == 'checkout'||  $status == 'booking'){
			 $metaInfo = null;
			}
			if ($status == 'keyin' || $status == 'keyout') {
			    $metaInfo = array();
				$metaInfo['employeeName'] = $employeeName;
				$metaInfo['employeeId'] = $employeeId;
				$metaInfo = json_encode($metaInfo,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			}
			if ($status == 'roomsurvey') {
			    $metaInfo = array();
				$FILE_BATHROOM = $_FILES['bathroom'];
				$FILE_BASIN = $_FILES['basin'];
				$FILE_BED = $_FILES['bed'];
				$FILE_TV_UNIT = $_FILES['tvunit'];

				$FILE_BATHROOM = $this->saveFile($FILE_BATHROOM['tmp_name'], "jpg");
				$FILE_BASIN = $this->saveFile($FILE_BASIN['tmp_name'], "jpg");
				$FILE_BED = $this->saveFile($FILE_BED['tmp_name'], "jpg");
				$FILE_TVUNIT = $this->saveFile($FILE_TV_UNIT['tmp_name'], "jpg");

				$metaInfo['file_bathroom'] = $FILE_BATHROOM;
				$metaInfo['file_basin'] = $FILE_BASIN;
				$metaInfo['file_bed'] = $FILE_BED;
				$metaInfo['file_tv_unit'] = $FILE_TVUNIT;
				$metaInfo = json_encode($metaInfo,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			}

			
			
			


			$sql = "INSERT INTO `room_logs`( `room_id`, `status`, `created_on`,`meta`,`updated_by`) VALUES ('$roomId','$status','$now','$metaInfo','$managerId')";
			if($metaInfo == null){
			    $sql = "INSERT INTO `room_logs`( `room_id`, `status`, `created_on`,`meta`,`updated_by`) VALUES ('$roomId','$status','$now',null,'$managerId')";
			}
			$this->db->query($sql);

			$sql = "UPDATE `rooms` SET `room_status`='$status',`last_updated_on`='$now' WHERE `room_id` = '$roomId'";
			$this->db->query($sql);

if($status == 'roomsurvey'){
    $status = "ready";
    $sql = "INSERT INTO `room_logs`( `room_id`, `status`, `created_on`,`meta`,`updated_by`) VALUES ('$roomId','$status','$now',null,'$managerId')";
			$this->db->query($sql);

			$sql = "UPDATE `rooms` SET `room_status`='$status',`last_updated_on`='$now' WHERE `room_id` = '$roomId'";
			$this->db->query($sql);

}

			$response['status'] = true;
			$response['message'] = "Room Status has been updated successfully";
		}

		return $response;
	}



	public function createNewRepetativeTask($taskTitle, $taskDescription, $date, $time, $priority, $formControls, $repeatMode)
	{
		$now = date('Y-m-d H:i:s');

		$sql = "INSERT INTO `repetative_tasks`( `title`, `description`, `date`, `time`, `priority`, `created_on`,`frequency`) VALUES ('$taskTitle','$taskDescription','$date','$time','$priority','$now','$repeatMode')";
		$this->db->query($sql);

		$sql = "SELECT * FROM `repetative_tasks` WHERE `created_on` = '$now' and `title` = '$taskTitle'";
		$query = $this->db->query($sql);
		$res = json_decode(json_encode($query->result()), true);

		$taskId = $res[0]['task_id'];

		for ($i = 0; $i < count($formControls); $i++) {
			$controlTitle = $formControls[$i]['control_title'];
			$controlType = $formControls[$i]['control_type'];
			$meta_data = json_encode($formControls[$i]['meta_data']);
			$sql = "INSERT INTO `repetative_task_form_control`( `control_type`, `control_title`,  `meta_data`, `task_id`) VALUES ('$controlType','$controlTitle','$meta_data','$taskId')";
			$this->db->query($sql);
		}

		/*$now = date('Y-m-d');
		$curTnow = strtotime($now);
		$curTdate = strtotime($date);
		if ($curTnow == $curTdate) {
			$this->createNewTask($taskTitle, $taskDescription, $date, $time, $priority, $formControls, $taskId);
		}*/

		$response['status'] = true;
		$response['message'] = "Task Has been created successfully";

		return $response;
	}

	public function createNewTask($taskTitle, $taskDescription, $date, $time, $priority, $formControls, $rep_task_id, $userId = null)
	{

		$now = date('Y-m-d H:i:s');

		if ($userId == null) {
			$sql = "INSERT INTO `tasks`(`title`, `description`, `date`, `time`, `priority`, `created_on`,`rep_task_id`) VALUES ('$taskTitle','$taskDescription','$date','$time','$priority','$now','$rep_task_id')";
		} else {
			$sql = "INSERT INTO `tasks`( `assign_to`,`title`, `description`, `date`, `time`, `priority`, `created_on`,`rep_task_id`) VALUES ('$userId','$taskTitle','$taskDescription','$date','$time','$priority','$now','$rep_task_id')";
		}
		$this->db->query($sql);

		$sql = "SELECT * FROM `tasks` WHERE `created_on` = '$now' and `title` = '$taskTitle' and `assign_to`='$userId' and `rep_task_id` = '$rep_task_id'";
		$query = $this->db->query($sql);
		$res = json_decode(json_encode($query->result()), true);

		$taskId = $res[0]['task_id'];

		for ($i = 0; $i < count($formControls); $i++) {
			$controlTitle = $formControls[$i]['control_title'];
			$controlType = $formControls[$i]['control_type'];
			$meta_data = json_encode($formControls[$i]['meta_data']);
			$sql = "INSERT INTO `form_controls`( `control_type`, `control_title`,  `meta_data`, `task_id`) VALUES ('$controlType','$controlTitle','$meta_data','$taskId')";
			$this->db->query($sql);
		}



		$response['status'] = true;
		$response['message'] = "Task Has been created successfully";

		return $response;
	}

	public function getAllTasks($forDate, $forUserId = null,$sortBy=null,$sortByUserId=null)
	{
		$sql = "SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to  where 1 ";
		if ($forUserId != null) {
			$sql = $sql . " and `tasks`.`assign_to` = '$forUserId' ";
		}

		if ($forDate != null && $forDate != 'all') {
			$sql = $sql . " and `tasks`.`date` = '$forDate'";
		}
		
		
		
		if ($sortBy != null && $sortBy != 'all' && $sortBy != 'All') {
		    $sql = $sql . " and `tasks`.`assign_to` = '$sortByUserId' ";
		}
		
		

		$query = $this->db->query($sql);
		$allTasks = json_decode(json_encode($query->result()), true);

		for ($i = 0; $i < count($allTasks); $i++) {
			$allTasks[$i]['formControls'] = $this->getFormControlsForTaskId($allTasks[$i]['task_id']);
		}

		return $allTasks;
	}

	public function getFormControlsForTaskId($taskId)
	{
		$sql = "SELECT * FROM `form_controls` WHERE `task_id` = '$taskId'";
		$query = $this->db->query($sql);
		$allControls = json_decode(json_encode($query->result()), true);
		for ($i = 0; $i < count($allControls); $i++) {
			$allControls[$i]['meta_data'] = json_decode($allControls[$i]['meta_data'], true);
		}
		return $allControls;
	}



	public function getAttendanceForUserId($userId, $date)
	{
		$sql = "SELECT * FROM `attendance` WHERE `user_id` = '$userId'  and date = '$date'";
		$query = $this->db->query($sql);
		$attendance = json_decode(json_encode($query->result()), true);
		if (count($attendance) > 0) {
			$attendance[0]['data'] = json_decode($attendance[0]['data'], true);
			return $attendance[0];
		} else {
			return null;
		}
	}
	public function getAttendance($date)
	{
		$users = $this->getUserInfo(null, null, null, $type = "all","admin");
		for ($i = 0; $i < count($users); $i++) {
			$users[$i]['attendance'] = $this->getAttendanceForUserId($users[$i]['user_id'], $date);
		}
		return $users;
	}






	public function saveFile($file, $extension)
	{
		$randNum = mt_rand(100000, 999999);

		$name = round(microtime(true) * 1000) . $randNum  . '.' . $extension;
		$filedest = 'uploads/' . $name;
		move_uploaded_file($file, $filedest);
		$filedestwithhost = base_url($filedest);
		// $info = array();
		// $info['filePath'] = $filedest;
		// $info['filePathUrl'] = $filedestwithhost;

		// // $this->printLog("saveFile ",$info);
		return $filedestwithhost;
	}


	public function saveAttendance($userId, $attendance)
	{

		$response = array();
		$response['status'] = true;
		$response['message'] = "Attendance has been marked Successfully!";
		$nowTimestamp = date('Y-m-d H:i:s');
		$nowDate = date('Y-m-d');






		$tempAttendance = $this->getAttendanceForUserId($userId, $nowDate);
		$attendanceData = array();
		if ($tempAttendance != null) {
			$attendanceData = $tempAttendance['data'];
		}



		$tempObject['attendance'] = $attendance;
		$tempObject['timestamp'] = $nowTimestamp;
		if ($attendance == 'in' || $attendance == 'out') {
			$FILE_DRESS_CODE = $_FILES['dress_code'];
			$tempObject['file'] = $this->saveFile($FILE_DRESS_CODE['tmp_name'], "jpg");
		}

		array_push($attendanceData, $tempObject);

		$attendanceData = json_encode($attendanceData);
		$sql = "";
		if ($tempAttendance == null) {
			$sql = "INSERT INTO `attendance`( `user_id`, `date`, `data`, `last_updated_attendance`, `created_on`) VALUES ('$userId','$nowDate','$attendanceData','$attendance','$nowTimestamp')";
		} else {
			$attendanceId = $tempAttendance['attendance_id'];
			$sql = "UPDATE `attendance` SET `data`='$attendanceData',`last_updated_attendance`='$attendance' WHERE `attendance_id` = '$attendanceId'";
		}
		$this->db->query($sql);

		return $response;
	}


	public function monthlyAttendanceQuery($user_id, $month, $year, $type)
	{

		$sql = "SELECT * FROM `attendance` WHERE `user_id` = '$user_id' and `last_updated_attendance` = '$type' and MONTH(`date`) = '$month' and YEAR (`date`) = '$year'";
		$query = $this->db->query($sql);
		return json_decode(json_encode($query->result()), true);
	}

	public function attendanceReportForUserId($user_id, $month, $year)
	{
		$final_response = array();
		$final_response['total_days_in_month'] = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$final_response['untrack_days'] = count($this->monthlyAttendanceQuery($user_id, $month, $year, 'in'));
		$final_response['absent_days'] = count($this->monthlyAttendanceQuery($user_id, $month, $year, 'abs'));
		$complete_days = $this->monthlyAttendanceQuery($user_id, $month, $year, 'out');
		$final_response['complete_days'] = count($complete_days);
		$total_logged_time  = 0;
		for ($i = 0; $i < count($complete_days); $i++) {
			$hoursWorked = 0;
			$inTime = 0;
			$outTime = 0;
			$logData = json_decode($complete_days[$i]['data'], true);
			for ($l = 0; $l < count($logData); $l++) {
				if ($logData[$l]['attendance'] == 'in') {
					$inTime = strtotime($logData[$l]['timestamp']);
				} else if ($logData[$l]['attendance'] == 'out') {
					$outTime = strtotime($logData[$l]['timestamp']);
				}
			}

			$hoursWorked = round((($outTime - $inTime) / 60) / 60, 2);
			$total_logged_time = $total_logged_time + $hoursWorked;
		}

		$final_response['attendance_not_marked_days'] = $final_response['total_days_in_month'] - ($final_response['complete_days'] + $final_response['absent_days'] + $final_response['untrack_days']);
		$final_response['total_logged_hours'] = round($total_logged_time,2);


		return $final_response;
	}




public function getSalaryEntryForParticularUser($userId,$month,$year){
    $sql = "SELECT * FROM `salary_payment` WHERE `user_id` = '$userId' and `month` = '$month' and `year` = '$year'";
		$query = $this->db->query($sql);
		$res =  json_decode(json_encode($query->result()), true);
		if(count($res)>0){
		    return $res[0];
		}
		return null;
}

public function salaryReport($month,$year ){
    $now = date('Y-m-d');
		$now = strtotime($now);
		if ($month == null) {
			$month = date('m', $now);
		}
		if($year  == null)
		    $year = date('Y', $now);
		    
		$users = $this->getUserInfo(null, null, null, $type = "all","admin");

		for ($i = 0; $i < count($users); $i++) {
		    $complete_days = $this->monthlyAttendanceQuery($users[$i]['user_id'], $month, $year, 'out');
		    $payroll = $this->getSalaryEntryForParticularUser($users[$i]['user_id'],$month,$year);
		    if($payroll == null){
		        $payroll = array();
		        $payroll['pay_id'] = null;
		        $payroll['days_present'] = count($complete_days);
		        $payroll['user_id'] = $users[$i]['user_id'];
		        $payroll['base_salary'] = $users[$i]['base_salary'];
		        $payroll['bonus'] = "0";
		        $payroll['month'] = $month;
		        $payroll['year'] = $year;
		        
		    }
		    
			$users[$i]['payroll'] = $payroll;
		}
		return $users;
}




public function getFcmKey($userId = '')
    {
        $response_array = array();
        $sql = "SELECT `fcm_token` FROM `users`  WHERE `user_id` = '$userId'";
        if ($userId == '') {
            $sql = "SELECT `fcm_token` FROM `users` ";
        }
        $query = $this->db->query($sql);
        $res = json_decode(json_encode($query->result()), true);
        for ($i = 0; $i < count($res); $i++) {
            array_push($response_array, $res[$i]['fcm_token']);
        }

        return $response_array;
    }


    public function sendPushNotification($device_id, $title, $message)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $api_key = 'AAAALEwP_-c:APA91bHwCUoOef9pMkZVSqDyC-VolGnJuJY7C1SQTsus1liX5H8LKmRLAx6sH8nZ69iqwKum-E2alBWKkd4rNobBl-MI1kBmAgs8T2ZTD6imuGB8-nGP7cr2zs4POf0m_NS5A4Cob177'; //Replace with yours

        $target = $device_id;

        $fields = array();
        $fields['priority'] = "high";
        $fields['notification'] = [
            "title" => $title,
            "body" => $message,
            'data' => [
                "message" => $message,
                "title" => $title,
                "body" => $message
            ],
            "sound" => "default"
        ];
        if (is_array($target)) {
            $fields['registration_ids'] = $target;
        } else {
            $fields['to'] = $target;
        }

        //header includes Content type and api key
        $headers = array(
            'Content-Type:application/json',
            'Authorization:key=' . $api_key
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }



	public function monthlyAttendanceReport($month)
	{
		$now = date('Y-m-d');
		$now = strtotime($now);
		if ($month == null) {
			$month = date('m', $now);
		}
		$year = date('Y', $now);
		$users = $this->getUserInfo(null, null, null, $type = "all","admin");

		for ($i = 0; $i < count($users); $i++) {
			$users[$i]['attendance_report'] = $this->attendanceReportForUserId($users[$i]['user_id'], $month, $year);
		}

		return $users;
	}


	public function updateTaskStatus($taskData)
	{
	    $now = date('Y-m-d H:i:s');
		$taskId = $taskData['task_id'];
		$formControls = $taskData['formControls'];
		$sql = "UPDATE `tasks` SET `status`='done', `updated_on`='$now' WHERE `task_id` = '$taskId'";
		$this->db->query($sql);

		for ($i = 0; $i < count($formControls); $i++) {
			$controlType = $formControls[$i]['control_type'];
			$controlId = $formControls[$i]['control_id'];
			$responseValue = $formControls[$i]['response_value'];
			$inputType =  $formControls[$i]['meta_data']['input_type'];
			if ($controlType == 'text' || $controlType == 'yes_no_radio') {
				$sql = "UPDATE `form_controls` SET `response_value`='$responseValue' WHERE `control_id` = '$controlId'";
				$this->db->query($sql);
			} else if ($controlType == 'file') {
				$FILE_TO_BE_INPUT = $_FILES[$controlId];

				$extension = "jpg";
				if ($inputType == 'Video') {
					$extension = "mp4";
				}
				$FILE_TO_BE_INPUT = $this->saveFile($FILE_TO_BE_INPUT['tmp_name'], $extension);
				$sql = "UPDATE `form_controls` SET `response_value`='$FILE_TO_BE_INPUT' WHERE `control_id` = '$controlId'";
				$this->db->query($sql);
			}
		}
		
		
		
		$sql = "SELECT * FROM `tasks` WHERE `task_id` = '$taskId'";
		$query = $this->db->query($sql);
		$taskDetails = json_decode(json_encode($query->result()), true);
        
        $taskDate = $taskDetails[0]['date'];
		
		$sql = "SELECT * FROM `tasks`  WHERE `rep_task_id`= (SELECT `rep_task_id` from tasks  where `task_id` ='$taskId') and `task_id` <> '$taskId' and `date` = '$taskDate' and `status` <> 'done'";
		$query = $this->db->query($sql);
		$unfinishedTasks = json_decode(json_encode($query->result()), true);
		
		for($ui=0;$ui<count($unfinishedTasks);$ui++){
		    for ($i = 0; $i < count($formControls); $i++) {
			    $controlId = $formControls[$i]['control_id'];
			    $this->copyFormControlInformation($controlId,$unfinishedTasks[$ui]['task_id']);
			
		    }
		    $unfinishedTasksId = $unfinishedTasks[$ui]['task_id'];
		    	$sql = "UPDATE `tasks` SET `status`='done', `updated_on`='$now' WHERE `task_id` = '$unfinishedTasksId'";
		        $this->db->query($sql);
		    
		}
        
		
	}
	
	
	public function copyFormControlInformation($controlId,$unfinishedTaskId){
	    $sql = "SELECT * FROM `form_controls` WHERE `control_id` = '$controlId'";
	    $query = $this->db->query($sql);
		$originalControlInformation = json_decode(json_encode($query->result()), true);
		$originalControlInformation = $originalControlInformation[0];
		
		$origControlType = $originalControlInformation['control_type'];
		$origControlTitle = $originalControlInformation['control_title'];
		$origMetaData = $originalControlInformation['meta_data'];
		$origResponseValue = $originalControlInformation['response_value'];
		
		$sql = "SELECT * FROM `form_controls` WHERE `task_id` = '$unfinishedTaskId'  and `control_type` = '$origControlType'  and `control_title` = '$origControlTitle' and `meta_data` = '$origMetaData'";
	    $query = $this->db->query($sql);
		$unfinishedControlInformation = json_decode(json_encode($query->result()), true);
		$unfinishedControlInformation = $unfinishedControlInformation[0];
		
		$unfinishedControlId = $unfinishedControlInformation['control_id'];
		
		
		$sql = "UPDATE `form_controls` SET `response_value`='$origResponseValue' WHERE `control_id` = '$unfinishedControlId'";
		 $this->db->query($sql);
		
		
		
		
	}

	public function cronJobService($sql)
	{
		$nowDate = date('Y-m-d H:i:s');
		$nowTime = date('H:i:s');

		$query = $this->db->query($sql);
		$allTasks = json_decode(json_encode($query->result()), true);


		for ($i = 0; $i < count($allTasks); $i++) {
			$taskTitle = $allTasks[$i]['title'];
			$assign_to = $allTasks[$i]['assign_to'];
			$taskDescription = $allTasks[$i]['description'];
			$created_on = $allTasks[$i]['created_on'];
			$time = $allTasks[$i]['time'];
			$priority = $allTasks[$i]['priority'];
			$rep_task_id = $allTasks[$i]['task_id'];
			$formControls = $this->getFormControlsForRepetativeTaskId($rep_task_id);

			$assign_to = json_decode($assign_to, true);

			for ($assigners = 0; $assigners < count($assign_to); $assigners++) {
				$this->createNewTask($taskTitle, $taskDescription, $nowDate, $time, $priority, $formControls, $rep_task_id, $assign_to[$assigners]);
			}
		}
	}


	public function getFormControlsForRepetativeTaskId($taskId)
	{
		$sql = "SELECT * FROM `repetative_task_form_control` WHERE `task_id` = '$taskId'";
		$query = $this->db->query($sql);
		$allControls = json_decode(json_encode($query->result()), true);
		for ($i = 0; $i < count($allControls); $i++) {
			$allControls[$i]['meta_data'] = json_decode($allControls[$i]['meta_data'], true);
		}
		return $allControls;
	}


	public function updateTaskAssigners($taskId, $assignToId)
	{
		$nowDate = date('Y-m-d');

		$assignToId_json = json_encode($assignToId);
		$sql = "UPDATE `repetative_tasks` SET`assign_to`='$assignToId_json' WHERE `task_id` = '$taskId'";
		$this->db->query($sql);
		
		
		$sql = "SELECT * FROM `tasks` WHERE `rep_task_id`='$taskId'  and DATE(`created_on`) = '$nowDate'";
		$query = $this->db->query($sql);
		$tasks_created = json_decode(json_encode($query->result()), true);
		if(count($tasks_created)>0){
		$taskIdToBeDeleted = $tasks_created[0]['task_id'];
		$sql = "DELETE FROM `tasks` WHERE `rep_task_id` = '$taskId' and DATE(`created_on`) = '$nowDate'";
		$this->db->query($sql);
		
		$sql = "DELETE FROM `form_controls` WHERE `task_id` = '$taskIdToBeDeleted'";
		$this->db->query($sql);
		}
		
		
		

		

		$this->curlRequestForCronJob(base_url('index.php/Api/cronJobsDailyNotRepeat'));
		$this->curlRequestForCronJob(base_url('index.php/Api/cronJobsRepeatEveryday'));
		$this->curlRequestForCronJob(base_url('index.php/Api/cronJobsRepeatEveryWeek'));
		$this->curlRequestForCronJob(base_url('index.php/Api/cronJobsRepeatEveryMonth'));
		$this->curlRequestForCronJob(base_url('index.php/Api/cronJobsRepeatEveryYear'));
	}

	public function curlRequestForCronJob($apiUrl)
	{
		//Initialize cURL.
		$ch = curl_init();

		//Set the URL that you want to GET by using the CURLOPT_URL option.
		curl_setopt($ch, CURLOPT_URL, $apiUrl);

		//Set CURLOPT_RETURNTRANSFER so that the content is returned as a variable.
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		//Set CURLOPT_FOLLOWLOCATION to true to follow redirects.
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		//Execute the request.
		$data = curl_exec($ch);

		//Close the cURL handle.
		curl_close($ch);

		//Print the data out onto the page.
		// echo $data;
	}
	public function getAllTasksFromRepetative($forDate = null,$frequency=null)
	{
		$sql = "SELECT * FROM `repetative_tasks` where 1 ";

		if ($forDate != null && $forDate != 'all') {
			$sql = $sql . " and `date` = '$forDate'";
		}
		
		if ($frequency != null && $frequency != 'All') {
			$sql = $sql . " and `frequency` = '$frequency'";
		}
		

		$query = $this->db->query($sql);
		$allTasks = json_decode(json_encode($query->result()), true);

		for ($i = 0; $i < count($allTasks); $i++) {
			$allTasks[$i]['assign_to'] = json_decode($allTasks[$i]['assign_to'], true);
			$allTasks[$i]['formControls'] = $this->getFormControlsForRepetativeTaskId($allTasks[$i]['task_id']);
		}

		return $allTasks;
	}
	
	
	
	public function getFeedbackReport($type,$date,$roomNumber){
	    $sql="SELECT * FROM `feedbacks` WHERE 1";
	    if(($type == 'Date' || $type == 'Both') && $date!=null){
	        $sql = $sql." and DATE(`created_on`) ='$date'";
	    }
	    
	    if(($type == 'Room No.' || $type == 'Both') && $roomNumber!=null){
	        $sql = $sql." and `room_no` = '$roomNumber'";
	    }
	    $query = $this->db->query($sql);
		    $res = json_decode(json_encode($query->result()), true);
		    return $res;
	}
	
	public function getRoomNumbersForFeedback(){
	    
	    $array = array();
	    $sql = "SELECT DISTINCT(`room_no`) FROM `feedbacks`";
	    $query = $this->db->query($sql);
		$res = json_decode(json_encode($query->result()), true);
		   for($i=0;$i<count($res);$i++){
		       array_push($array,$res[$i]['room_no']);
		   }
		    array_push($array,"302");
		   return $array;
	    
	}
	
	
	public function submitFeedback($roomNo,$managerId,$rateBathroom,$rateBed,$rateManager,$rateOverall,$rateRoomService,$improvements){
	    
	    $FILE_TO_BE_INPUT = $_FILES['selfie'];

				$extension = "jpg";
				
				$FILE_TO_BE_INPUT = $this->saveFile($FILE_TO_BE_INPUT['tmp_name'], $extension);
				
				
				
	    $now = date('Y-m-d H:i:s');
	    $sql = "INSERT INTO `feedbacks`( `room_no`, `rate_bathroom`, `rate_bedroom`, `rate_manager`, `rate_overall`, `rate_roomservice`, `improvements`, `manager_id`, `created_on`,`selfie`) VALUES (
	        '$roomNo','$rateBathroom','$rateBed','$rateManager','$rateOverall','$rateRoomService','$improvements','$managerId','$now','$FILE_TO_BE_INPUT')";
	        $this->db->query($sql);
	}
	
	public function taskDefaulters($start_date,$end_date){
	    $users = $this->getUserInfo(null,  null,  null, "all","admin");
	    
	    
	    for($i=0;$i<count($users);$i++){
	        $userId = $users[$i]['user_id'];
	    $response_array = array();    
	        
	        // all tasks between 2 dates for a user_id
	        $sql = "SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to where 1 and `tasks`.`assign_to` = '$userId' and `date`>='$start_date' and `date`<='$end_date'";
	        $query = $this->db->query($sql);
		    $allTasks = json_decode(json_encode($query->result()), true);
		    $response_array['all_tasks'] = $allTasks ;
		    
		    
		    
		    
		    
		
	        // all tasks between 2 dates which are not started
	        $sql = "SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to where 1 and `tasks`.`assign_to` = '$userId' and `date`>='$start_date' and `date`<='$end_date' and `status`='not_started'";
	      $query = $this->db->query($sql);
		    $allTasks = json_decode(json_encode($query->result()), true);
		    $response_array['un_finished_tasks'] = $allTasks ;
		    
	      
	        
	        // all tasks between 2 dates which are marked as done
	        $sql ="SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to where 1 and `tasks`.`assign_to` = '$userId' and `date`>='$start_date' and `date`<='$end_date' and `status`='done'";
	      $query = $this->db->query($sql);
		    $allTasks = json_decode(json_encode($query->result()), true);
		    $response_array['finished_tasks'] = $allTasks ;
		  
		  
		    
	        //all tasks between 2 dates which are marked as done but with delay
	        $sql = "SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to where 1 and `tasks`.`assign_to` = '$userId' and `date`>='$start_date' and `date`<='$end_date' and `status`='done' and (date(`updated_on`)>`date` OR time(`updated_on`)>`time`)";
            $query = $this->db->query($sql);
		    $allTasks = json_decode(json_encode($query->result()), true);
		    
		    for($j=0;$j<count($allTasks);$j++){
		        
		        $allTasks[$j]['delayed_by'] = $this->calculateDelayInTask($allTasks[$j]['date'],$allTasks[$j]['time'],$allTasks[$j]['updated_on']);
		        
		    }
		    
		    
		    $response_array['finished_tasks_with_delay'] = $allTasks ;
		  
            	 $users[$i]['defaulter']   =    $response_array;
	        
		
	    }
	    return $users;
	}
	
	
	public function calculateDelayInTask($date,$time,$updated_on){
	    
	    $date_create = date_create($date.' '.$time);
	    $dateTime = $now = date_format($date_create,'Y-m-d H:i:s');
					$expectedTime = strtotime($dateTime);
		
					$finishedTime = strtotime($updated_on);
		
		

			return round((($finishedTime - $expectedTime) / 60) / 60, 2);
	    
	}
	
	
	public function unfinishedTasksforUserId($userId){
	    $sql = "SELECT `tasks`.`updated_on`,`tasks`.`task_id`, `tasks`.`rep_task_id`, `tasks`.`assign_to`, `tasks`.`title`, `tasks`.`description`, `tasks`.`date`, `tasks`.`time`, `tasks`.`priority`, `tasks`.`status`, `tasks`.`created_on`,`users`.`name` FROM `tasks` INNER JOIN `users` ON `users`.user_id =`tasks`.assign_to where 1 and `tasks`.`assign_to` = '$userId' and `status`='not_started'";
	     $query = $this->db->query($sql);
		   $allTasks = json_decode(json_encode($query->result()), true);
		   
		for ($i = 0; $i < count($allTasks); $i++) {
			$allTasks[$i]['formControls'] = $this->getFormControlsForTaskId($allTasks[$i]['task_id']);
		}
		   return $allTasks;
		    
	}
	
	
	
	public function getStatusLogsForRoomId($status, $roomId, $date){
	   // and `room_id` = '$roomId'
	    $sql = "SELECT * FROM `room_logs` WHERE `status` = '$status'  and DATE(`created_on`) = '$date'";
	    $query = $this->db->query($sql);
		   $allData = json_decode(json_encode($query->result()), true);
		   return $allData;
	}
	
	
	public function calculateDelaysInCleaning($totalKeyOuts){
	    $totalDelay = 0;
	    
	    $logId = '0';
	    if(count($totalKeyOuts)){
	        $logId = $totalKeyOuts[0]['log_id'];
	    }
	    for($i=0;$i<count($totalKeyOuts);$i++){
	        
	        
	        $roomId = $totalKeyOuts[$i]['room_id'];
	        
	        $sql = "SELECT * FROM `room_logs` WHERE `status`= 'keyin' and `log_id`>'$logId' and `room_id` = '$roomId' LIMIT 1";
	        $query = $this->db->query($sql);
		    $res = json_decode(json_encode($query->result()), true);
		    
		    if(count($res)>0){
		        
		        	
				
					$outTime = strtotime($totalKeyOuts[$i]['created_on']);
				    $inTime = strtotime($res[0]['created_on']);
			

			$hoursDifference = round((($inTime - $outTime) / 60) / 60, 2);
			if($hoursDifference>0.5){
			   $hoursDifference = $hoursDifference-0.5; 
			}
			
			$totalDelay = $totalDelay + $hoursDifference;
			$logId = $res[0]['log_id'];
		    }
	        
	    }
	    
	    return $totalDelay;
	}
	
	
	public function getRoomsReport($date){
	    $rooms = $this->getRoomDetails();
	    $report = array();
	    /*
	    for($i=0;$i<count($rooms);$i++){
	        $roomReportArray = array();
	        
	        
	        
	        $roomId = $rooms[$i]['room_id'];
	        $roomNumber = $rooms[$i]['room_no'];
	        $totalCheckedIns = $this->getStatusLogsForRoomId("checkin", $roomId, $date);
	        $totalCheckedOuts = $this->getStatusLogsForRoomId("checkout", $roomId, $date);
	        $totalKeyIns = $this->getStatusLogsForRoomId("keyin", $roomId, $date);
	        $totalKeyOuts = $this->getStatusLogsForRoomId("keyout", $roomId, $date);
	        $totalSurveys = $this->getStatusLogsForRoomId("roomsurvey", $roomId, $date);
	        $totalDelayForCleaning = $this->calculateDelaysInCleaning($totalKeyOuts);
	        
	        $roomReportArray['roomId'] = $roomId;
	        $roomReportArray['roomNumber'] = $roomNumber;
	        $roomReportArray['checkIns'] = count($totalCheckedIns);
	        $roomReportArray['checkouts'] = count($totalCheckedOuts);
	        $roomReportArray['keyIns'] = count($totalKeyIns);
	        $roomReportArray['keyOuts'] = count($totalKeyIns);
	        $roomReportArray['surveys'] = count($totalSurveys);
	        $roomReportArray['delay'] = $totalDelayForCleaning;
	        array_push($report,$roomReportArray);
	        
	        
	    }*/
	    
	        $totalCheckedIns = $this->getStatusLogsForRoomId("checkin", null, $date);
	        $totalCheckedOuts = $this->getStatusLogsForRoomId("checkout", null, $date);
	        $totalKeyIns = $this->getStatusLogsForRoomId("keyin", null, $date);
	        $totalKeyOuts = $this->getStatusLogsForRoomId("keyout", null, $date);
	        $totalSurveys = $this->getStatusLogsForRoomId("roomsurvey", null, $date);
	       
	       
	       $roomReportArray = array();
	        
	        $roomReportArray['checkIns'] = count($totalCheckedIns);
	        $roomReportArray['checkouts'] = count($totalCheckedOuts);
	        $roomReportArray['keyIns'] = count($totalKeyIns);
	        $roomReportArray['keyOuts'] = count($totalKeyIns);
	        $roomReportArray['surveys'] = count($totalSurveys);
	        
	    return $roomReportArray;
	    
	}
	
	public function feedbackAvgReport($startDate,$endDate){
	    $sql = "SELECT avg(`rate_bathroom`) as rate_bathroom ,avg(`rate_bedroom`) as rate_bedroom ,avg(`rate_manager`) as rate_manager,avg(`rate_overall`) as rate_overall,avg(`rate_roomservice`) as rate_roomservice from feedbacks where DATE(`created_on`) <='$endDate' and DATE(`created_on`) >='$startDate'";
	    $query = $this->db->query($sql);
		  $res = json_decode(json_encode($query->result()), true);
		  
		  return $res[0];
		  
	}
}
