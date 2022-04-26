<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * @author          Sandeep Kumawat
 * @email          	s.kumawat@digitalindia.gov.in
 * @Date          	06-08-2019
 * @Last Modified   23-09-2019 
 * @updated added by sunil notification on 31-05-2020
 */

class Dashboard extends MY_Controller {

    function __construct() {
        parent::__construct();
        $_CI = & get_instance();
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->library('session');
        $this->KIBANA_DATA = $this->config->item('KIBANA_DATA');
        $this->load->library('Partner_API');
        $this->load->library('Zipkin_lib');
        $this->load->library('Nb_lib');
        $this->load->library('Nehbr_auth');
        $this->bucket_name = $this->config->item('objectStorage_minio_bucketName');
        $this->topic_usernotifications = 'institutionnotifications';
        $this->publish_url = ($this->config->item('publish_url'))? $this->config->item('publish_url') :"";
        $this->redis_uri = $this->config->item('redis_uri');
        $this->redis_uri_password = ($this->config->item('redis_uri_password')) ? $this->config->item('redis_uri_password') :"";

       if (!$this->nehbr_auth->is_logged_in()) {
           $this->nb_lib->no_cache();  // logged in
           redirect('/sessiontimeout', 'refresh');
       }
       Common::checkUgcUser();
    }
	
	
    function index() {
       $org_id = $this->session->userdata("org_id");
		//Added by Anupriya to add doctype_assigned value in session for skill	when login by super admin
		if($this->session->userdata("institution_type")=='Skill')
		{
			$docType = 'SKCER';
			$this->session->set_userdata(array(
				'doctype_assigned' => $docType
			));
		}

		user_tracker_log(array('module' => 'LI', 'gate_no' => 1));
        $zipkin_context = $this->zipkin_lib->init_frontend_trace("Dashboard-index");
//        $this->add_notification();
        $this->check_isApproved();
        $this->email_notification();

        $headerdata = array();
        $viewdata = array();
        $year = '';
        $UrlYear = '';  
        $yearwiseData = [];          
        $allYears = $this->stats_years();
        if($allYears){
            $allYears = array_column($allYears,'_id');
            rsort($allYears);
        }
        $viewdata['stats_years'] = $allYears; 
        if(!empty($this->input->get('Year')))
        {
           $year = $this->input->get('Year');
		   
        }else{
            $year = $allYears[0] ;
        }
        //call yearwise data fetch for org id
        $this->load->model("report/Report_model");
        $access_token = $this->Report_model->curl_authToken();
       // prx ($this->session);
      if ($access_token && $org_id) {
        $authorization = "Authorization: Bearer ".$access_token;


        //Indian Navy check
        if($this->session->userdata("institution_type")=='govt_department'){
            $url = 	ELK_API_BASEURL.'indiannavy';
           // $url = 	'http://nadbusinessapi.com/Disability';
            $apiDataYearwise = $this->Report_model->curl_getData($url, $authorization, ['ORG_ID'=>$org_id,'Year'=>$year]);
            $apiData = json_decode($apiDataYearwise, true);

            //summary data
            if($apiData['status'] == "success" && isset($apiData['data'])){
                $summaryData = $apiData['data'];
                if($summaryData) {
                    $viewdata["total_summary_count"] = $summaryData['Total_Awards'];
                    $viewdata["total_process_degree"] = $summaryData['Total_Disability_Certificate'];
                    $viewdata["total_process_marksheet"] = $summaryData['Total_UID_Card'];
                    $viewdata['stats'][0] = ['ORG_TYPE'=>'govt_department'];
                }else{
                    $viewdata["total_summary_count"] =0;
                    $viewdata["total_process_degree"] = 0;
                    $viewdata["total_process_marksheet"] = 0;
                }
            }

            //yearwise data
            if($apiData['status'] == "success" && isset($apiData['yearwise_data'])){
                $yearwiseData = $apiData['yearwise_data'];
                if($yearwiseData) {
                    $viewdata['DisabilityStats'] = [ 'ORG_TYPE'=>'govt_department',
                                                        'total_awards'=> $yearwiseData['Total_Awards'],
                                                        'total_certificate'=>$yearwiseData['Total_Disability_Certificate'], 
                                                        'total_UID'=>$yearwiseData['Total_UID_Card'] ];
                }
            }
          //   prx($viewdata);
        } //govt_department Checks ends 


        else{
            $url = 	ELK_API_BASEURL.'getOrganisationSummaryData';
            $url1 = 	ELK_API_BASEURL.'getOrganisationDataYearwise';
            $apiData = $this->Report_model->curl_getData($url, $authorization, ['org_id'=>$org_id]);
            $apiDataYearwise = $this->Report_model->curl_getData($url1, $authorization, ['org_id'=>$org_id,'year'=>$year]);
           // echo $org_id;
           // echo  "<pre>";
           // print_r(["getOrganisationSummaryData"=>$apiData]);
            //prx(["getOrganisationDataYearwise"=>$apiDataYearwise]);
            $apiData = json_decode($apiData, true);
            $apiDataYearwise = json_decode($apiDataYearwise, true);
            //for summary data
            if($apiData['status'] == "success" && isset($apiData['data'])){
                $summaryData = $apiData['data'];
                if($summaryData) {
                    $viewdata["total_summary_count"] = $summaryData['total_records'];
                    $viewdata["total_process_degree"] = $summaryData['total_process_degree'];
                    $viewdata["total_process_diploma"] = $summaryData['total_process_diploma'];
                    $viewdata["total_process_marksheet"] = $summaryData['total_process_marksheet'];
                    $viewdata["total_record_only_upload"] = $summaryData['total_record_only_upload'];
                
                }else{
                    $viewdata["total_summary_count"] =0;
                    $viewdata["total_process_degree"] = 0;
                    $viewdata["total_process_diploma"] = 0;
                    $viewdata["total_process_marksheet"] = 0;
                    $viewdata["total_record_only_upload"] = 0;
                }    
            }
            if($apiDataYearwise['status'] == "success" && isset($apiDataYearwise['data'])) {
                $yearwiseData = $apiDataYearwise['data'];
                if($yearwiseData){
                    $viewdata['stats'][0] = $yearwiseData;
                }
            }else{
                $viewdata['stats'][0] = []; 
            }

        }
                 
    }
        // if($apiDataYearwise['status'] == "success" && isset($apiDataYearwise['data'])) {
        //     $yearwiseData = $apiDataYearwise['data'];
        //     if($yearwiseData){
        //         $viewdata['stats'][0] = $yearwiseData;
        //     }
        // }else{
        //     $viewdata['stats'][0] = []; 
        // }  
        		
        $viewdata['UrlYear'] = $year;
        $this->load->model('dashboard_model');
        $pulldoc_data = $this->dashboard_model->pulldoc_stats($org_id);
        if(!empty($pulldoc_data)) {
            $dashboard = $pulldoc_data[0];
            $viewdata['totalpulldoc'] = $dashboard['total_pull_requests'];
            $viewdata['totalpulldocsuccess'] = $dashboard['total_pull_success'];
            $viewdata['totalpulldocfail'] = $dashboard['total_pull_errors_users'] + $dashboard['total_pull_errors_api'];
            $viewdata['pullapifail'] = $dashboard['total_pull_errors_api'];
            $viewdata['pulluserfail'] = $dashboard['total_pull_errors_users'];
        }
        //------------------------------
        $UrlStandard = '';
        /* $viewdata['stats_standard'] = $this->stats_standard(); */
        $headerdata["username"] = $this->session->userdata("username");
        $headerdata["designation"] = $this->session->userdata("designation");
        $headerdata['universityname'] = $this->session->userdata("universityname");
        //maneesh start
        $headerdata["role_name"] = $this->session->userdata("role_name");
        //maneesh end
        $headerdata["titleName"] = "Dashboard";
       
		// code to get actions and notifications
		$activitiesNotifications = $this->getActivititesNotifications($org_id);
		$viewdata['notifications'] = $activitiesNotifications['notifications'];
		$viewdata['activities'] = $activitiesNotifications['activities'];
		$this->zipkin_lib->end_frontend_trace($zipkin_context);
        //prx($viewdata);
         if(!$this->session->userdata('abcpopup')) {
             $viewdata['issuer_id'] = $this->abc_isNadApproved();
         }

        $this->zipkin_lib->end_frontend_trace($zipkin_context);
       // $viewdata['stats'] = $this->dashboard_stats($year);
        $this->load->view('../../__inc/header', $headerdata);
        $this->load->view('../../__inc/header_top');
        //$this->load->view('../../../../assets/_inc/head-signin');
        //$this->load->view('../../../../assets/_inc/navbar-static-signin', $headerdata);
        $this->load->view('dashboard', $viewdata);
        $this->load->view('../../__inc/footer');
       // $this->load->view('../../../../assets/_inc/footer-static');

    }
    
    private function dashboard_stats($year)
    {
        $org_id = $this->session->userdata("org_id");
        $this->load->model("admin/admin_model");
        $where = array('ORG_ID' => $org_id, 'INSERT_DATE' => date('Y-m-d'), "YEAR" => (int)$year);
        // $where = array('ORG_ID' => $org_id, "YEAR" => (int)$year);
        return $this->admin_model->stats_group_by($where);
    }
    
    private function stats_years(){
        $org_id = $this->session->userdata("org_id");
        $this->load->model("admin/admin_model");
        $where = [
            ['$match' => [ 'ORG_ID' => $org_id]],
            ['$group' => ['_id' => '$YEAR']]
        ];
        $allYears = [];
        $years = $this->admin_model->stats_all_years($where);        
        foreach($years as $year)
        {
            $allYears[] = $year;
        }
        return $allYears;
    }
    
    private function stats_standard(){
        $org_id = $this->session->userdata("org_id");
        $this->load->model("admin/admin_model");
        $where = [
            ['$match' => ['INSERT_DATE' => date('Y-m-d'), 'ORG_ID' => $org_id]],
            ['$group' => ['_id' => '$STANDARD']]
        ];
        $allStandard = [];
        $Standard = $this->admin_model->stats_all_years($where);
        foreach($Standard as $std)
        {
            $allStandard[] = $std;
        }
        return $allStandard;
    }
    
    function welcome() {
//        $this->add_notification();
        $this->check_isApproved();
        $headerdata = array();
        $viewdata = array();
        $this->load->model('dashboard_model');
       
        $headerdata["username"] = $this->session->userdata("username");
        $headerdata["designation"] = $this->session->userdata("designation");
        $headerdata['universityname'] = $this->session->userdata("universityname");
         //maneesh start
         $headerdata["role_name"] = $this->session->userdata("role_name");
         //maneesh end
         $headerdata["titleName"] = "Dashboard";
		
        $viewdata['is_Approved'] = $this->session->userdata("is_Approved");
         if(!$this->session->userdata('abcpopup')) {
             $viewdata['issuer_id'] = $this->abc_isNadApproved();
         }
        $headerdata["titleName"] = "Welcome";
 //maneesh start
 $headerdata["role_name"] = $this->session->userdata("role_name");
 //maneesh end
        $this->load->view('../../__inc/header', $headerdata);
       
        $this->load->view('../../__inc/header_top',$headerdata);
        $this->load->view('welcome', $viewdata);
        $this->load->view('../../__inc/footer');
        
    }


	function graph() {
            
        //** UPDATED BY SRISHTI TASKID@17115 DASHBOARD ACCESSIBILITY FOR CALL CENTRE(100) AND SUPER USER(99) ROLE CREATED **/
        $role_array = $this->config->item('role');
        if(!in_array($this->session->userdata("role_id"),$role_array))
        {
           redirect('dashboard');
        }
        //** END OF TASK **/
		$headerdata = array();
		$viewdata = array();
		$headerdata["username"] = $this->session->userdata("username");
		$headerdata["designation"] = $this->session->userdata("designation");
		$headerdata['universityname'] = $this->session->userdata("universityname");
         //maneesh start
		$headerdata["role_name"] = $this->session->userdata("role_name");
		//maneesh end

		$viewdata['is_Approved'] = $this->session->userdata("is_Approved");
        $viewdata['apiData'] = $this->graph_api_data();
		$headerdata["titleName"] = "Dashboard";
		$this->load->view('../../__inc/header', $headerdata);
		$this->load->view('../../__inc/header_top',$headerdata);
		$this->load->view('graph', $viewdata);
		$this->load->view('../../__inc/footer');
	}

    /**** Start Curl to get Authorization token from API   ******/
	private function curl_authToken()
	{
        $error_msg = "";
		$proxyUrl = $this->config->item('proxyUrl');
        $partner_api_proxy = $this->config->item('partner_api_proxy');
        $is_prod = $this->config->item('is_prod');
		//To generate the authorisation token
        //$customer_id = 'QumfXfzEpfSY';
       // $customer_id = 'NivnxvPSWvb3';
        $customer_id = 'Ki4FMMvcXZTB';
        $customer_secret_key = '12345';
        //$post_arr['customer_id'] = $this->session->userdata("customer_id");
        //$post_arr['customer_secret_key'] = $this->session->userdata("customer_secret_key");
        $fields_string = 'customer_id=' . $customer_id .  '&customer_secret_key=' . $customer_secret_key;
        $auth_url 		= 	ELK_API_BASEURL.'oauth';
        $auth_ch = curl_init();
        curl_setopt($auth_ch, CURLOPT_URL, $auth_url);
        curl_setopt($auth_ch, CURLOPT_POST, true);
        curl_setopt($auth_ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($auth_ch, CURLOPT_HTTPHEADER, array("Content-Type:  application/x-www-form-urlencoded"));
        if ($is_prod == true) {
            curl_setopt($auth_ch, CURLOPT_PROXY, $proxyUrl);
        }
        curl_setopt($auth_ch, CURLOPT_RETURNTRANSFER, true);
        $auth_output = curl_exec($auth_ch);
        // echo "<pre>";
        // print_r($auth_output);
        // die;
        $arr_output = json_decode($auth_output);
        $authorization = '';
        if(isset($arr_output->access_token))
        {
            $access_token = $arr_output->access_token;
        }
        $auth_httpcode = curl_getinfo($auth_ch);
        if (curl_errno($auth_ch)) {
			$error_msg = curl_error($auth_ch);
		}
        curl_close($auth_ch);
        if($error_msg) {
			echo $error_msg;
		}
		else {
			return $access_token;
		}
	}
	/**** End Curl to get Authorization token from API   ******/

    /**** Start Curl to get data from API   ******/
	private function curl_getData($url, $authorization, $postArray=array())
	{
        $error_msg = "";
        $is_prod = $this->config->item('is_prod');
        if ($is_prod == true) {
		    $proxyUrl = $this->config->item('proxyUrl');
            $partner_api_proxy = $this->config->item('partner_api_proxy');
        } else {
            $proxyUrl = '';
            $partner_api_proxy = '';
        }
        // $is_prod = $this->config->item('is_prod');
		// //To get data
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_POST, true);
        // if(!empty($postArray))
        // {
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postArray));
        // }
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:  application/json", $authorization));
        // if ($is_prod == false) {
        //     echo "in_proxy";
        //     curl_setopt($ch, CURLOPT_PROXY, $proxy['https']);
        // }
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $output = curl_exec($ch);
        // $httpcode = curl_getinfo($ch);
        // if (curl_errno($ch)) {
		// 	$error_msg = curl_error($ch);
		// }
        // echo "<pre>";
        // var_dump($output);
        // echo "===============================>";
        // var_dump(curl_errno($ch));
        // echo "===============================>";
        // var_dump($httpcode);
        // die;
        // curl_close($ch);
        // if($error_msg) {
		// 	echo $error_msg;
		// }
		// else {
		// 	return $output;
		// }
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_PROXY => $proxyUrl,
        CURLOPT_HTTPHEADER => array($authorization),
        ));
        $response = curl_exec($curl);
        //var_dump(curl_errno($curl));
        curl_close($curl);
        //var_dump($response);
        return $response;

	}
	/**** End Curl to get data from API   ******/

	private function graph_api_data()
    {
        $access_token = $this->curl_authToken();
        if($access_token)
        {
            $authorization = "Authorization: Bearer ".$access_token;

            //to get total count for institution type
            $urlTotalUniversity 		= 	ELK_API_BASEURL.'getTotalCountForUniversityForCurrentDate'; 
            $universityTotalCount = $this->curl_getData($urlTotalUniversity, $authorization);
            $apiData['universityTotalCount'] = $universityTotalCount;

            //To get university count
            $url 		= 	ELK_API_BASEURL.'countbyuniversitytype'; 
            $universityCount = $this->curl_getData($url, $authorization);
            $apiData['universityCount'] = $universityCount;
            
            //To get statewise count
            $stateurl 		= 	ELK_API_BASEURL.'statsByState'; 
            $stateCount = $this->curl_getData($stateurl, $authorization);
            $apiData['stateCount'] = $stateCount;

            //To get total awards launched
            // $totalAwardsLaunchedurl = $elk_baseurl.'totalAwardsLaunched'; 
            // $totalAwardsLaunched = $this->curl_getData($totalAwardsLaunchedurl, $authorization);       
            // $apiData['totalAwardsLaunched'] = $totalAwardsLaunched;

            //Total institutions registered
            // $totalInstitutionsurl = $elk_baseurl."totalInstitutions";
            // $totalInstitutions = $this->curl_getData($totalInstitutionsurl, $authorization);       
            // $apiData['totalInstitutions'] = $totalInstitutions;

            //Major Contributors
            $majorContributorsurl = ELK_API_BASEURL."majorContributorsNew";
            $majorContributors = $this->curl_getData($majorContributorsurl, $authorization); 
            $apiData['majorContributors'] = $majorContributors;

            //Total institutions university type
            $getUniversityTypeurl = ELK_API_BASEURL."getUniversityTypeNew";
            $getUniversityType = $this->curl_getData($getUniversityTypeurl, $authorization);   
            $apiData['getUniversityType'] = $getUniversityType;

            //Major Contributors month wise
            $majorContributorsMonthurl = ELK_API_BASEURL."activeParticipantsOfCurrentMonth";
            $majorContributorsMonth = $this->curl_getData($majorContributorsMonthurl, $authorization);       
            $apiData['activeParticipantsOfMonth'] = $majorContributorsMonth;

            //Not approved universities
            $notApprovedUniversitiesurl = ELK_API_BASEURL."notApprovedInstitutionsNew";
            $notApprovedUniversities = $this->curl_getData($notApprovedUniversitiesurl, $authorization);       
            $apiData['notApprovedUniversities'] = $notApprovedUniversities;

        } else {
            $apiData = array();
        }
        
        return $apiData; 
	}    
    
    function information() {
//        $this->add_notification();
        $this->check_isApproved();
        $headerdata = array();
        $viewdata = array();
        $headerdata["username"] = $this->session->userdata("username");
        $headerdata["designation"] = $this->session->userdata("designation");
        $headerdata['universityname'] = $this->session->userdata("universityname");
         //maneesh start
		$headerdata["role_name"] = $this->session->userdata("role_name");
		//maneesh end
        $headerdata["titleName"] = "Dashboard";
        $this->load->view('../../__inc/header', $headerdata);
        $this->load->view('../../__inc/header_top',$headerdata);
        $this->load->view('information', $viewdata);
        $this->load->view('../../__inc/footer');
    }
    
    
    public function add_notification(){ 
     $org_id = $this->session->userdata('org_id');
        
        
      $this->load->model('dashboard_model'); 
      $data = array(
                'notifications_master_id'=>1,
                'status'=> "A",
                'priority'=>1,
                'read_status'=>'N',
                'visible'=>1,
                'created_on'=>date('Y-m-d H:i:s'),
                'university_id'=>(int) $this->session->userdata('univ_id'),
                'read_on'=>'',
                'org_id'=> $org_id
                
            );   
        $url = $this->publish_url.'api/institute_notifications/add_notification';
        //$redis = $this->dashboard_model->isRedisExist();
        if($this->publish_url && $org_id){            
          //  if ($redis && !$redis->get($key_notification_masterid1)) {
           //     $redis->set($key_notification_masterid1,json_encode($data));
                $fields_string = json_encode($data);    
                $headers = array('Content-Type: application/json');                
               Common::curlRequests($url, $headers, 'POST', array('post_data' => $fields_string, 'curl_timeout' => 30));
                          
          //  }
                  
        }
    }
    
    /* check redis exists or not */
//    private function isRedisExist() {
//        try {
//            if ($this->redis_uri_password) {
//                $options = ['cluster' => 'redis', 'parameters' => ['password' => $this->redis_uri_password]];
//                $redis = new Predis\Client($this->redis_uri, $options);
//            } else {
//                $redis = new Predis\Client($this->redis_uri);
//            }
//            if ($redis) {
//                return $redis;
//            } else {
//                return false;
//            }
//        } catch (Exception $exc) {
//            /* redis  not exits */
//            $exc->getMessage();
//            return false;
//        }
//    }
    
    
    function check_isApproved(){                
        $org_id = $this->session->userdata("org_id");
        $is_Approved_session = $this->session->userdata('is_Approved');         
        $digilocker_id = $this->session->userdata('user_id');
        if($org_id && !is_null($org_id)){
            $this->load->model('dashboard_model');        
            $is_approve_status = $this->dashboard_model->is_approve_nad_status($org_id);
            if($is_approve_status != $this->session->userdata('is_Approved') && $is_Approved_session =='V'){
                /*
                 * 1. call notification
                 * 2. call api
                 * 3. call api
                 * 4. update local approve
                 */
				 
				/* UPDATED BY SRISHTI TASKID#8834 ADD TWO PARAMETER */ 
				$getid =  substr($org_id,-2);
			    $shortcodeURI = 'NAU'.$getid;	
                $shortcodeDOC =	'NAD'.$getid; 	
                $issuerid = $this->session->userdata('issuer_id');		
                $getname = explode('.',$issuerid);
                $apiname = $getname[count($getname)-1].' NAD RESULT';	
                /* UPDATED BY SRISHTI TASKID#8834 ADD TWO PARAMETER */ 				
              
               $this->update_approve_status($is_Approved_session,$org_id);
				$zipkin_context = $this->zipkin_lib->init_backend_trace("add_pull_url");
				$this->partner_api->add_pulluri($org_id,$digilocker_id,$shortcodeURI,$apiname);
				$this->zipkin_lib->end_backend_trace($zipkin_context);
				$zipkin_context_2 = $this->zipkin_lib->init_backend_trace("add_pull_doc");
				$this->partner_api->add_pulldoc($org_id,$digilocker_id,$shortcodeDOC,$apiname );
				$this->zipkin_lib->end_backend_trace($zipkin_context_2);
//               $this->update_approved_notification();    
            
                }        
        }
    }
    
    /*update approve status */
    private function update_approve_status($is_Approved_session = NULL, $org_id = NULL){
        if($is_Approved_session && $org_id && $this->dashboard_model->update_approve_status($is_Approved_session,$org_id)){
           return true;
        }else{
            return "";
        }
        
    }
    
    
    private function update_approved_notification(){ 
        $data = array(
                'notifications_master_id'=>2,
                'status'=> "A",
                'priority'=>1,
                'read_status'=>'N',
                'visible'=>1,
                'created_on'=>date('Y-m-d H:i:s'),
                'university_id'=>(int) $this->session->userdata('univ_id'),
                'read_on'=>'',
                'org_id'=> $this->session->userdata('org_id')
                
            );   
        
            $url = $this->publish_url.'api/institute_notifications/add_notification';
            $fields_string_master_id2 = json_encode($data);
            $headers = array('Content-Type: application/json');
            Common::curlRequests($url, $headers, 'POST', array('post_data' => $fields_string_master_id2, 'curl_timeout' => 30));
          
    }
    
    
    private function email_notification(){        
        $org_id = $this->session->userdata("org_id");
        $email_verify_status = $this->session->userdata('email_verify_status'); 
        $email_notification_status = $this->session->userdata('email_notification_status'); 
        //$digilocker_id = $this->session->userdata('user_id');
        if($org_id && !is_null($org_id) && $email_verify_status === 'Y' && $email_notification_status === 'Y'){
            /* email notification send  */ 
            return "";
        
       }else if($org_id && !is_null($org_id) && $email_verify_status === 'Y'){
           /*update notification status */
            $this->load->model('dashboard_model');        
            $this->dashboard_model->update_email_notification_status($org_id);
            
       }
        
    }
    
    
    function error_404()
    {
        $this->load->view('error_page');
    }

    /**
     * Function to check condtion for abc popup
     * 
     */
    function abc_isNadApproved() {                
        $org_id = $this->session->userdata("org_id");
        $is_Approved_session = $this->session->userdata('is_Approved');         
        $digilocker_id = $this->session->userdata('user_id');
        if($org_id && !is_null($org_id)){
            $this->load->model('dashboard_model');        
            // $is_approve_status = $this->dashboard_model->is_approve_nad_status($org_id);
            if($is_Approved_session =='V'){
                // Code for issuer id exist
                $url = ELK_API_BASEURL.'issuerVerification';
                $access_token = Common::curl_authToken();
                $authorization = "Authorization: Bearer ".$access_token;
                $response = Common::curl_getData($url, $authorization,array('org_id'=>$org_id),'POST');
                //condition for abc popup display
                $response = json_decode($response);

                if($response->abc_registration){
                        return false;
                } else {
                     if(($this->session->userdata('role_id') == 1) && (in_array($this->session->userdata('institution_type'),INSTITUTION_TYPE_LIST_IN_ABC_POPUP))) {
                        $this->session->set_userdata('abcpopup','1');
                        return true;
                    } else {
                        return false;
                    }
                }
            }                //end of abc popup conditions
            }
        }
    

	/**
	 * Function to get count of actions and notifications
	 *
	 */
	function getActivititesNotifications() {
		$org_id = $this->session->userdata("org_id");
		if($org_id && !is_null($org_id)){
			$url = ELK_API_BASEURL.'getActivitiesAndNotifications';
			$access_token = Common::curl_authToken();
			$authorization = "Authorization: Bearer ".$access_token;
			$response = Common::curl_getData($url, $authorization,array('org_id'=>$org_id),'POST');
			$response = json_decode($response);
			$data = $response->data;
			if(!empty($data)){
				$notifications = [
					'nameMatchRequestsReceived'=>!empty($data->nameMatchRequestsReceived)?$data->nameMatchRequestsReceived:0,
					'recordsUploadFailed'=>!empty($data->recordsUpload->failed)?$data->recordsUpload->failed:0,
					'photosUploadFailed'=>!empty($data->photosUpload->failed)?$data->photosUpload->failed:0
				];
				$photosUploadedCount = !empty($data->photosUpload->success)?$data->photosUpload->success:0;
				$photosUploaded['photosUploaded'] = $photosUploadedCount;
				if($photosUploadedCount > 0) {
					$photosUploaded['YearUploaded'] = $data->photosUpload->folder;
					$photosUploaded['nameUploaded'] = $data->photosUpload->name;
				}
				//	prx($data->recordsUpload);
				$recordUploadedCount = !empty($data->recordsUpload->success)?$data->recordsUpload->success:0;

				$recordsUploaded['recordsUploaded'] = $recordUploadedCount;
				if($recordUploadedCount > 0) {
					$recordsUploaded['YearUploaded'] = $data->recordsUpload->folder;
					if($data->recordsUpload->name) {
						if ($data->recordsUpload->name == "API") {
							$recordsUploaded['nameUploaded'] = "through API";
						} else {
							$recordsUploaded['nameUploaded'] = "by " . $data->recordsUpload->name;
						}
					}
					//$recordsUploaded['nameUploaded'] = ($data->recordsUpload->name=="API")?"through API":"by ".$data->recordsUpload->name;
				}
				if(!empty($data->recordsUpload->showVerify)) {
					$recordsUploaded['showVerifyNow'] = $data->recordsUpload->showVerify;
				}
				return  [
					'notifications'=>$notifications,
					'activities'=>[$photosUploaded,$recordsUploaded],
				];
			}
			return [];
		}
	}
}
