<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Api extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->database();
		$this->load->library('session');
		$this->load->model('ApiModel');
	}
	
	public function demo(){
	       $this->db->select("*");
   $this->db->from("users");
   $query = $this->db->get();        
   print_r( json_encode($query->result(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
	}

	public function createUser()
	{
		/**
		 * {
		 * name : 
		 * contactNumber:
		 * password:
		 * type:
		 * baseSalary:
		 * }
		 */

		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);
		$name = $data['name'];
		$contact = $data['contactNumber'];
		$password = $data['password'];
		$type = $data['type'];
		$baseSalary = $data['baseSalary'];
		$response = $this->ApiModel->saveUserDetails($name, $contact, $password, $type,$baseSalary);

		echo json_encode($response);
	}


	public function authenticate()
	{
		/**
		 * {
		 * contact:
		 * password:
		 * type:
		 * }
		 */
		$response = array();
		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);
		$contact = $data['contact'];
		$password = $data['password'];
		$type = $data['type'];
		$user = $this->ApiModel->getUserInfo($contact, null, null, $type);
		if ($user == null) {
			$response['status'] = false;
			$response['message'] = "The credentials you have entered is incorrect!";
		} else {
			$response['status'] = true;
			$response['message'] = "You have been logged in successfully";
			$response['data'] = $user[0];
		}

		echo json_encode($response);
	}


	public function createRoom()
	{
		/**
		 * {
		 * roomNumber:,
		 * roomStatus:
		 * }
		 *  */
		$response = array();
		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);
		$roomNumber = $data['roomNumber'];
		// $roomStatus = $data['roomStatus'];
		$response = $this->ApiModel->saveNewRoom($roomNumber);

		echo json_encode($response);
	}

	public function getRooms()
	{
		$response = array();
		$response['status'] = false;
		$response['message'] = "No Rooms Found";
		$rooms = $this->ApiModel->getRoomDetails();
		if ($rooms != null) {
			$response['status'] = true;
			$response['message'] = "Rooms Found";
			$response['data'] = $rooms;
		}
		echo json_encode($response);
	}

	public function getUsers()
	{
		$type = $this->input->get("type");
		$response['status'] = false;
		$response['message'] = "Unable to find the user. Please try again";
		$users = $this->ApiModel->getUserInfo(null, null, null, $type);
		if ($users != null) {
			$response['status'] = true;
			$response['message'] = "Users found";
			$response['data'] = $users;
		}
		echo json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}


	public function updateRoomStatus()
	{
		/**
		 * roomId
		 * status
		 * employeeId
		 * employeeName
		 * managerId
		 * 
		 */

		$data = json_decode($this->input->post("data"), true);

		$roomId = $data['roomId'];
		$status = $data['status'];
        $managerId = $data['managerId'];
		$employeeId = "";
		$employeeName = "";


		if ($status == 'keyin' || $status == 'keyout') {
			$employeeId = $data['employeeId'];
			$employeeName = $data['employeeName'];
		}
		
		
		

		$response = $this->ApiModel->updateRoomStatus($roomId, $status, $employeeId, $employeeName,$managerId);

		echo json_encode($response);
	}

	public function createNewTask()
	{
		/**
		 * {"taskTitle"
		 * "taskDescription"
		 * "date"
		 * "time"
		 * "priority"
		 * "frequency"
		 * 
		 * "formControls"{
		 * 			"controlType"
		 * 			"controlTitle"
		 * 			"meta_data"{
		 * 					"input_type"
		 * 					"required"
		 * 						}
		 * 				}
		 * }
		 */




		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);



		$date = $data['date'];
		$time = $data['time'];
		$taskTitle = $data['title'];
		$taskDescription = $data['description'];
		$priority = $data['priority'];
		$repeatMode = $data['frequency'];
		$formControls = $data['formControls'];

		echo json_encode($this->ApiModel->createNewRepetativeTask($taskTitle, $taskDescription, $date, $time, $priority, $formControls, $repeatMode));
	}

	public function getTasks()
	{
		$forDate = $this->input->post("forDate");
		$forUserId = $this->input->post("forUserId");
        $sortBy = $this->input->post("sortBy");
        $sortByUserId = $this->input->post("userId");
		echo json_encode($this->ApiModel->getAllTasks($forDate,$forUserId,$sortBy,$sortByUserId));
	}


	public function getAttendance()
	{
		$forDate = $this->input->post("forDate");

		print_r(json_encode($this->ApiModel->getAttendance($forDate)));
	}


	public function markAttendance()
	{
		$userId = $this->input->post("userId");
		$attendance = $this->input->post("attendance");
		echo json_encode($this->ApiModel->saveAttendance($userId, $attendance));
	}


	public function attendanceReport()
	{
		$month = $this->input->post("month");

		$users = $this->ApiModel->monthlyAttendanceReport($month);
		echo json_encode($users);
	}


	public function updateTaskStatus()
	{
		$response['status'] = true;
		$response['message'] = "Task has been updated successfully";
		$data = json_decode($this->input->post("data"), true);
		$this->ApiModel->updateTaskStatus($data);
		echo json_encode($response);
	}





	public function assignUsersToTask()
	{
		/**
		 * {
		 * "taskId":""
		 * "userIds":[1,2,3,4,5,6]
		 * }
		 */

		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);
		

		$taskId = $data['taskId'];
		$userIds = $data['userIds'];
		

		$this->ApiModel->updateTaskAssigners($taskId, $userIds);
		
		$response['status'] = true;
		$response['message'] = "Task has been updated successfully";
		
		echo json_encode($response);


	}
	
	
	public function savePayment()
	{
		/**
		 * {
		 * "days_present":""
		 * "user_id":"",
		 * "base_salary"
		 * "bonus"
		 * "month"
		 * "year"
		 * }
		 */

		$obj = file_get_contents('php://input');
		$data = json_decode($obj, true);
		

		$days_present = $data['days_present'];
		$user_id = $data['user_id'];
		$base_salary = $data['base_salary'];
		$bonus = $data['bonus'];
		$month = $data['month'];
		$year = $data['year'];
		

		$this->ApiModel->saveSalary($days_present, $user_id,$base_salary,$bonus,$month,$year);
		
		$response['status'] = true;
		$response['message'] = "Salary has been saved successfully";
		
		echo json_encode($response);


	}




	public function getRepetativeTasks()
	{
		$forDate = $this->input->post("forDate");
		$frequency = $this->input->post("frequency");

		echo json_encode($this->ApiModel->getAllTasksFromRepetative($forDate,$frequency));
	}


public function getPendingTasks(){
 
 $forUserId = $this->input->post("forUserId");
 echo json_encode($this->ApiModel->unfinishedTasksforUserId($forUserId));
}




public function taskDefaulters(){
    $start_date = $this->input->post("start_date");
    $end_date = $this->input->post("end_date");
    echo json_encode($this->ApiModel->taskDefaulters($start_date,$end_date));
}



public function submitFeedback(){
    /**
		 * {
		 * "roomNo":""
		 * "managerId":""
		 * "rateBathroom":""
		 * "rateBed":"",
		 * "rateManager":""
		 * "rateOverall":"",
		 * "rateRoomService":"",
		 * "improvements":""
		 * }
		 */

// 		$obj = file_get_contents('php://input');
// 		$data = json_decode($obj, true);
		
        $data = json_decode($this->input->post("data"), true);
		$roomNo = $data['roomNo'];
		$managerId = $data['managerId'];
		$rateBathroom = $data['rateBathroom'];
		$rateBed = $data['rateBed'];
		$rateManager = $data['rateManager'];
		$rateOverall = $data['rateOverall'];
		$rateRoomService = $data['rateRoomService'];
		$improvements = $data['improvements'];
		
		$this->ApiModel->submitFeedback($roomNo,$managerId,$rateBathroom,$rateBed,$rateManager,$rateOverall,$rateRoomService,$improvements);
		$response['status'] = true;
		$response['message'] = "Feedback has been submitted successfully";
		
		echo json_encode($response);

}


public function getFeedbackAvgRpt(){
    $startDate = $this->input->post("startDate");
    $endDate = $this->input->post("endDate");
    echo json_encode($this->ApiModel->feedbackAvgReport($startDate,$endDate));
}


public function getFeedbackReport(){
    $type= $this->input->post("type");
    $date= $this->input->post("date");
    $roomNumber= $this->input->post("roomNumber");
    
    echo json_encode($this->ApiModel->getFeedbackReport($type,$date,$roomNumber));
    
}

public function updateFCMToken(){
    $userId= $this->input->post("userId");
    $token= $this->input->post("token");
    echo json_encode($this->ApiModel->updateFcmKey($userId,$token));
       
}



public function sendNotification(){
    
    if (time() >= strtotime("17:00:0") && time() <= strtotime("17:20:0") ) {
//   if (true ) {

    $keys = $this->ApiModel->getFcmKey();
    $resp = $this->ApiModel->sendPushNotification($keys, "Reminder!", "You might have some pending tasks. Please check task list!");
    }
}

public function getSalaryReport($month = null){
    $now = date('Y-m-d');
    $now = strtotime($now);
    $currentMonth = date('m', $now);
    $year = null;
    if($month == 12 && $currentMonth ==1){
        $currentYear = date('Y', $now);
        $year = $currentYear-1;
    }
    
    
    echo json_encode($this->ApiModel->salaryReport($month,$year));
    
}

public function getRoomNumbersForFeedback(){
    
    echo json_encode($this->ApiModel->getRoomNumbersForFeedback());
    
}
public function getRoomsReport(){
    $date = $this->input->post("date");
    
    
    echo json_encode($this->ApiModel->getRoomsReport($date));
}


	public function cronJobsDailyNotRepeat()
	{
		$now = date('Y-m-d');
		$sql = " SELECT * from repetative_tasks where `date` = '$now' and `frequency` = 'One Time Only' and `task_id` NOT IN (SELECT tasks.rep_task_id from tasks where tasks.date = '$now')";
		$this->ApiModel->cronJobService($sql);
	}

	public function cronJobsRepeatEveryday()
	{
		$now = date('Y-m-d');
		$sql = "SELECT * FROM repetative_tasks WHERE  `frequency` = 'Every Day' AND `task_id` NOT IN( SELECT tasks.rep_task_id FROM tasks WHERE tasks.date = '$now' )";
		$this->ApiModel->cronJobService($sql);
	}

	public function cronJobsRepeatEveryWeek()
	{
		$now = date('Y-m-d');
		$sql = "SELECT * FROM repetative_tasks WHERE DAYNAME(`date`) = DAYNAME('$now') AND `frequency` = 'Every Week' AND `task_id` NOT IN( SELECT tasks.rep_task_id FROM tasks WHERE tasks.date = '$now' )";
		$this->ApiModel->cronJobService($sql);
	}

	public function cronJobsRepeatEveryMonth()
	{
		$now = date('Y-m-d');
		$sql = "SELECT * FROM repetative_tasks WHERE DAY(`date`) = DAY('$now') AND `frequency` = 'Every Month' AND `task_id` NOT IN( SELECT tasks.rep_task_id FROM tasks WHERE tasks.date = '$now' )";
		$this->ApiModel->cronJobService($sql);
	}

	public function cronJobsRepeatEveryYear()
	{
		$now = date('Y-m-d');
		$sql = "SELECT * FROM repetative_tasks WHERE MONTH(`date`) = MONTH('$now') AND DAY(`date`) = DAY('$now') AND `frequency` = 'Every Year' AND `task_id` NOT IN( SELECT tasks.rep_task_id FROM tasks WHERE tasks.date = '$now' )";
		$this->ApiModel->cronJobService($sql);
	}
}
