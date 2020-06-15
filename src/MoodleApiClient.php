<?php
//   MoodleApiClientTools
//   Copyright (C) 2020  H. Reimers

//   This program is free software; you can redistribute it and/or modify
//   it under the terms of the GNU General Public License as published by
//   the Free Software Foundation; either version 3 of the License, or
//   (at your option) any later version.


/*! \class MoodleApiClient
*    \brief A class for communicating with the Moodle WebServices
*
*    A Moodle server with web services enabled is required.
*    Also necessary:
*     * moodle-url
*     * moodle-token
*/
 
class MoodleApiClient
{
	public 	$urlSite=null;
	public 	$url=null;
	private $token=null;	//token (secret)
	public 	$responce=null;
	public	$apiReturnFormat = 'json';
	private	$lastUrl=null; //contains the token
	public 	$lastResponce=null;
	public 	$get_post="GET";

	public $roles=array();

	//	$roles=array(
	//		'manager' 	 => array( 'roleid' => '1',  'shortname' => 'manager'),
	//		'editingteacher' => array( 'roleid' => '3',  'shortname' => 'editingteacher'),
	//		'teacher'        => array( 'roleid' => '4',  'shortname' => 'teacher'),
	//		'student'        => array( 'roleid' => '5',  'shortname' => 'student'),
	//		);

	/**
	 *	Help for troubleshooting
	 *
	 *	@return The lass URL without token.
	 */
	public function getLastUrl(){
		return str_replace( $this->token, "(secret-token)", $this->lastUrl;
	}

	/**	Create a connection to a moodle-server
	 *	
	 * @param urlSite Url from moodle-server 
	 * @param token Secret to access the moodle-server
	*/

	public function __construct($urlSite = null, $token = null){
		$this->urlSite = trim($urlSite);
		$this->token = trim($token);
		$this->url=$this->urlSite."?wstoken=" . $this->token;
	}
    
	private function buildGetQuery($paramArray){
		$paramArray = http_build_query($paramArray, "", "&");
		
		//Moodle hack?!?
		$paramArray = str_replace("%5B","[",$paramArray);
		$paramArray = str_replace("%5D","]",$paramArray);
		
		return $paramArray;
	}
    
	/**
	*	Create a Mooodle Category
	*	- a parent should not have a Category-Name twice
	*
	*
	**/
	public function createCategory ($name=null,$parent=null,$idnumber=null,$description=null, $visible=1){
		if($name===null OR strlen($name)<2 ){
			echo "error in createCategory 'name' not set or to short<br>\n"; 
			return false;
		}
		if( !is_numeric($parent) ){
			echo "error in createCategory 'parant' is not numeric<br>\n"; 
			return false;
		}

		//Has the parent already a Category with this name?
		$cat=$this->getCategories($name,null,null,$parent);

		if( sizeof($cat)>0 ){
			echo "the parent 'id:$parent' has already a Category with the name '$name'<br>\n"; 
			return $cat;			
		}

		//Create params for http-request
		$params = array('categories' => array(array('name' => "$name", 'parent' => "$parent")));

		if($description!==null AND strlen($description)>0 ){
			$params["categories"][0]["description"]=$description;
			$params["categories"][0]["descriptionformat"]=1;
		}

		//Create new Category
		$responce=$this->sendRequest("core_course_create_categories",$params);

		if( isset($responce[0]["id"]) ){
			$id=$responce[0]["id"];

			//Get data from new Category
			$cat=$this->getCategories(null,$id,null,null);
			//print_r($cat);
			return $cat;

		}

		

		return false;
	}


	/**
	*	Enrol the aktive user to a course
	*
	*
	**/

	public function enrolSelfByCoursePassword($courseId=null, $password="", $enrolId=null){
		if ($courseId===null AND $enrolId===null){
			echo "Error in 'setCourseSelfEnrolment' fields 'courseId' and 'enrolId' are NULL<br>\n";
			return -1;
		}
		$params = array('courseid' => "$courseId",'password' => "$password");

		$responce=$this->sendRequest("enrol_self_enrol_user",$params);

		return $responce;
	}

	/**
	*	Get the Enrolment method from a moodle course
	*
	*
	**/

	public function getCourseEnrolment($courseId){
		//Create params for http-request
		$params = array('courses' => array(array('id' => "$courseId")));
		$params = array('courseid' => "$courseId");

		//Gat a array with all enrolments for this course
		$course_enrolements=$this->sendRequest("core_enrol_get_course_enrolment_methods",$params);

		$retval=array();
		foreach($course_enrolements As $nr => $enrolment){

			if ( isset($enrolment["id"]) ){
				$instanceId=$enrolment["id"];
				$params = array('instanceid' => "$instanceId");
				$retval[$instanceId]=$this->sendRequest("enrol_self_get_instance_info",$params);
			}

		}

		return $retval;

	}


	/**
	*	Create a Mooodle Course
	*	- a parent should not have a Course-Name twice
	*
	*
	**/
	public function createCourse ($fullname=null,$shortname=null, $parent=null,$idnumber=null,$description=null, $visible=1){

		if($fullname===null OR strlen($fullname)<2 ){
			echo "error in createCourse 'name' not set or to short<br>\n"; 
			return false;
		}
		if($shortname===null OR strlen($shortname)<2 ){
			$shortname=$fullname;
		}


		if( !is_numeric($parent) ){
			echo "error in createCourse 'parant' is not numeric<br>\n"; 
			return false;
		}


		//Has the parent already a Course with this name?
		$course=$this->getCourseByNameAndParent($fullname,$parent);


		if( $course!==false){
			//echo "the parent 'id:$parent' has already a Category with the fullname '$fullname'<br>\n"; 
			return $course;			
		}

		//Create a new course

		//Create params for http-request
		$params = array('courses' => array(array('fullname' => "$fullname", 'shortname' => "$shortname", 'categoryid' => "$parent")));

		if($description!==null AND strlen($description)>0 ){
			$params["courses"][0]["description"]=$description;
			$params["courses"][0]["descriptionformat"]=1;
		}

		//Create new course
		$responce=$this->sendRequest("core_course_create_courses",$params);


		if( isset($responce[0]["id"]) ){
			$id=$responce[0]["id"];

			//Get data from new course
			$course=$this->getCoursesByField("id", $id);
			//print_r($course);
			return $course;

		}

		

		return false;


	}

	/*
	*	search a couse-name in a parent-folder
	*
	**/
	public function getCourseByNameAndParent($name,$parent){

		$parentCourses=$this->getCoursesByField("category", $parent);

		if( !isset($parentCourses["courses"])){
			return false;
		}

		foreach($parentCourses["courses"] As $course){
			if ( isset($course["fullname"]) ) {
				if( $course["fullname"]==$name){
					//found course with this name
					return $course;
				}

			}

		}
	
		//Nothing found -> return "false"
		return false; 

	}



	public function getCategories($name=null,$id=null,$idnumber=null,$parent=null,$visible=1){
		$params = array('criteria' => array(array('key' => 'visible', 'value' => "$visible")), 'addsubcategories' => 0);
		if($parent!==null){
			$params["criteria"][]=array('key' => 'parent', 'value' => "$parent");
		}
		if($name!==null){
			$params["criteria"][]=array('key' => 'name', 'value' => "$name");
		}
		if($id!==null){
			$params["criteria"][]=array('key' => 'id', 'value' => "$id");
		}

		$responce=$this->sendRequest("core_course_get_categories",$params);

		return $responce;
	}


	public function getCoursesByField($fieldname=null, $value=null){

		$params=array( "field"=>"$fieldname", "value"=>"$value" ) ;

		$responce=$this->sendRequest("core_course_get_courses_by_field",$params);

		if( isset( $responce["exception"])){
			echo "Unknown field:'$fieldname' - use: 'category':'catId'|'id':'courseId'|'shortname':'Text' <br>";
			return false;
		}

		return $responce;
	}


//core_enrol_get_users_courses

	public function getCoursesById($ids=null){
		
		if( is_array($ids) ){
			$params= array("options"=> 
				array( "ids"=> $ids )
	                       );
		}
		else if( is_numeric($ids) ){
			$params= array("options"=> 
				array( "ids"=> array("$ids") )
	                       );
		}
		else{
			$params= array();
		}
	

			$params= array("options"=> 
				array( "ids"=> $ids )
	                       );

		$responce=$this->sendRequest("core_course_get_courses",$params);
		return $responce;
	}


	/**
	*	Try to get roles from moodle
	*
	*	Needs a course-shortname form a course with a user with all roles
	*
	*	1. Create somewhere in moodle an invisble course with a name like 'system_course_invisible'
	*	2. Put a user in the course
	*	3. Give this user all global roles for this course
	*
	**/
	public function getRoles($courseShortname){
		$course=$this->getCoursesByField("shortname", $courseShortname);
		$roles=array();
		if( isset($course["courses"][0]["id"]) ){
			$courseId=$course["courses"][0]["id"];
			
			$course = $this->getCourseUser($courseId);
		
			foreach($course AS $user){
				if( isset($user["roles"]) ){
					foreach( $user["roles"] As $role ){
						$roles[$role["shortname"]]=$role;
					}
				}
			}
		}

		if( sizeof($roles) > 0 ){
			$this->roles=$roles;
		}

		return $this->roles;

	}


	public function getUserCourses($userId){
		$params=array('userid' => "$userId");

		$responce=$this->sendRequest("core_enrol_get_users_courses",$params);

		return $responce;
	}


	public function getCourseUser($courseId){
	
		$params=array('courseid' => "$courseId");

		$responce=$this->sendRequest("core_enrol_get_enrolled_users",$params);

		return $responce;

	}


	public function getAllUsersDirty(){
		//Try to get userdata
		$params= array("criteria"=> 
			array( 
				array( "key"=>"email","value"=>"%%")
			
			     )
                       );
		$responce=$this->sendRequest("core_user_get_users",$params);
		return $responce;
	}
    
	public function sendRequest($function="core_webservice_get_site_info",$params=null){
		//Build url with function and token
		$this->lastUrl=$this->url."&wsfunction=" . $function . "&moodlewsrestformat=" . $this->apiReturnFormat;
		
		
		if($this->get_post=="GET"){
			//add parameter tu URL
			$this->lastUrl  = $this->lastUrl . "&". $this->buildGetQuery($params);
		}
		
		//Open CURL and set some params
		$curl = curl_init( $this->lastUrl );
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);	
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		
		if($this->get_post=="POST"){
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS,  buildGetQuery($params));
		}

		//Send http request
		$this->lastResponce = curl_exec($curl);
		
		return json_decode($this->lastResponce, true);
	}
    
    
}

?>
