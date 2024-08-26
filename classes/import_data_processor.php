<?php

namespace   local_module_extensions_upload;

use mod_coursework\models\coursework;
use mod_coursework\models\sub_timelimit;
use mod_coursework\models\user;

class import_data_processor     {

    /**
     *  Converts the import record into a record with the expected var names
     *
     * @param $record   -   the record to be converted
     * @param $pconfig  -   plugin config if not provided this will be retrieved
     * @return \stdClass
     */
    function convert_data_fields($record,$pconfig=false)      {

    $pconfig    =   (empty($pconfig))   ?   get_config('local_module_extensions_upload')  :   $pconfig;

        $converteddata  =   new \stdClass();

        print_r($pconfig);

        $converteddata->user    =   (!empty($record->{$pconfig->user}))  ?   $record->{$pconfig->user}   :   "";
        $converteddata->course    =   (!empty($record->{$pconfig->course}))  ?   $record->{$pconfig->course}   :   "";
        $converteddata->assessment    =   (!empty($record->{$pconfig->assessment}))  ?   $record->{$pconfig->assessment}   :   "";
        $converteddata->date    =   (!empty($record->{$pconfig->date}))  ?   $record->{$pconfig->date}   :   "";
        $converteddata->timelimit    =   (!empty($record->{$pconfig->timelimit}))  ?   $record->{$pconfig->timelimit}   :   "";
        $converteddata->type    =   (!empty($record->{$pconfig->type}))  ?   $record->{$pconfig->type}   :   "";
        $converteddata->reason_code    =   (!empty($record->{$pconfig->reason_code}))  ?   $record->{$pconfig->reason_code}   :   "";
        $converteddata->reason_desc    =   (!empty($record->{$pconfig->reason_desc}))  ?   $record->{$pconfig->reason_desc}   :   "";
        $converteddata->action    =   (!empty($record->{$pconfig->action}))  ?   $record->{$pconfig->action}   :   "";
        $converteddata->timecreated    =   (!empty($record->{$pconfig->timecreated}))  ?   $record->{$pconfig->timecreated}   :   "";

        return  $converteddata;
    }


    function process_data(array $extensionrecords)    {

        global  $DB;

        $importresults    =   array();

        foreach ($extensionrecords  as  $record) {

            //get the plugins config
            $pluginconfig   =   get_config('local_module_extensions_upload');

            //convert data field names

            $record =   $this->convert_data_fields($record,$pluginconfig);


            $processrecord = true;
            $uploadprocessor    =   new \local_module_extensions_upload\processor();



            //check if the course can be found
            $course = $DB->get_record('course', array('shortname' => $record->course));
            if (!$course) {
                $result = array('error' => true, 'msg' => "Course doesn't exist");
                $processrecord  =   false;
            }

            //check if the student can be founc
            $student = $DB->get_record('user', array('idnumber' => $record->user));
            if (!$student) {
                $result = array('error' => true, 'msg' => "Student doesn't exist");
                $processrecord  =   false;

            }

            // workout coursework from assessmentcode
            $activity = $uploadprocessor->get_module_from_assessmentcode($course->id, $student->id, $record->assessment, $record->type);
            if (!is_object($activity)) {
                //note in case of an get_module_from_assessmentcode returns the error instead of an object
                $result = array('error' => true, 'msg' => $activity);
                $processrecord  =   false;
            }


            //only continue if the course, student and activity have been found
            if (!empty($processrecord)) {

                if ($record->type == 'coursework_mitigations') {

                    if ($record->action == 'insert' || $record->action == 'update') {

                        $result = $this->create_coursework_mitigation($activity, $student, $course, $record);

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_mitigation($activity, $student, $course, $record);
                    }



                } else if ($record->type == 'coursework_overrides' ) {

                    if ($record->action == 'insert' || $record->action == 'update') {

                        $result = $this->create_coursework_override($activity, $student, $course, $record);

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_override($activity, $student, $course, $record);
                    }

                } else if ($record->type == 'coursework_permanent_exemption') {

                    if ($record->action == 'insert' || $record->action == 'update') {

                        $result = $this->create_coursework_exemption($activity, $student, $course, $record,'permanent');

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_exemption($activity, $student, $course, $record,'permanent');
                    }

                } else if ($record->type == 'coursework_temporary_exemption') {

                    if ($record->action == 'insert' || $record->action == 'update') {
                        $result = $this->create_coursework_exemption($activity, $student, $course, $record,'temporary');
                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_exemption($activity, $student, $course, $record,'temporary');
                    }

                }   else if ($record->type == 'quiz_extensions') {

                    if ($record->action == 'insert' || $record->action == 'update') {

                        $result = $this->create_quiz_extension($activity, $student, $course, $record);

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_quiz_extension($activity, $student, $course, $record);
                    }

                } else if ($record->type == 'quiz_timelimit') {

                    if ($record->action == 'insert' ||  $record->action == 'update') {

                        $result = $this->create_quiz_timelimit($activity, $student, $course, $record);

                    } else if ($record->action == 'delete') {

                        $result =   $this->delete_quiz_timelimit($activity, $student, $course, $record);
                    }

                } else {

                    $result = array('error' => true, 'msg' => "Unknown type given");

                }

                $result['timecreated']      =   time();

                $importresults[]    =   array('record'=>$record,'result'=>$result);



                //log result of record import


            }
        }


        return  $importresults;



    }



    function    create_coursework_mitigation($activity,$student,$course,$importrecord)
    {

        global $USER, $DB;

        $coursework = $activity;

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']    =   true;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        if (is_object($coursework)) {

            // VALIDATE EXTENSION
            $user_deadline = $coursework->get_allocatable_deadline($student->id);
            // simple validation of date
            $datevalue = explode('/', $importrecord->date);
            $yeartime = explode(' ', $datevalue[2]);

            $timevalid = true;
            if (array_key_exists(1, $yeartime)) {
                $timevalid = (bool)preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $yeartime[1]);
            }

            $extensiondate = strtotime(str_replace('/', '-', $importrecord->date));
            // extension format validation
            if (!checkdate($datevalue[1], $datevalue[0], $yeartime[0]) || $timevalid == false) {
                $result['msg']      =   "Invalid extension date";
                return $result;
            }

            // extension can't be smaller than user's deadline
            if ($extensiondate < $user_deadline) {
                $result['msg']      =   "Extension date must be later than user's deadline/current extension";
                return $result;
            }

            // check if submission for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id());

            $mitigation = $DB->get_record('coursework_mitigations', $params);

            if (!$mitigation) { // create a new extension

                $csvdata = new \stdClass();
                //courseid, studentid, assessmentcode, extended_deadline, pre_defined_reason, extra_information_text

                $csvdata->allocatableid = $student->id;
                $csvdata->allocatabletype = 'user';
                $csvdata->courseworkid = $coursework->id();
                $csvdata->extended_deadline = $extensiondate;
                $csvdata->pre_defined_reason = $importrecord->reason_code;
                $csvdata->createdbyid = $creatinguser;
                $csvdata->extra_information_text = $importrecord->reason_desc;
                $csvdata->extra_information_format = 1;
                $csvdata->type = 'extension';
                $csvdata->timecreated = time();

                $DB->insert_record('coursework_mitigations', $csvdata);

                $result['actualaction']    =   'insert';
                $result['error']    =   false;
                $result['msg']      =   "coursework mitigation created";
                return $result;

            }  else { // update an extension

                $new_extension = new \stdClass();
                $new_extension->id = $mitigation->id;
                $new_extension->extended_deadline = $extensiondate;
                $new_extension->pre_defined_reason = $importrecord->reason_code;
                $new_extension->createdbyid = $creatinguser;
                $new_extension->extra_information_text = $importrecord->reason_desc;
                $new_extension->extra_information_format = 1;
                $new_extension->type = 'extension';
                $new_extension->timecreated = time();

                $DB->update_record('coursework_mitigations', $new_extension);

                $result['actualaction']    =   'update';
                $result['error']    =   false;
                $result['msg']      =   "coursework mitigation updated";
                return $result;
            }
        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }

    }


    function    create_coursework_override($activity,$student,$course,$importrecord)   {
        global  $DB;

        $coursework = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']    =   true;

        if (is_object($coursework)) {

            // VALIDATE TIMELIMIT
            $user_timelimit = $coursework->get_allocatable_timelimit($student->id);

            print_r($user_timelimit);
            // simple validation of timelimit
            $newtimelimit = $importrecord->timelimit;
            if(!is_number($newtimelimit)){
                $result['msg']      =   "Invalid timelimit";
                return $result;
            }

            // extension can't be smaller than user's deadline
            if($newtimelimit < $user_timelimit){
                $result['msg']      =   "Time limit must be later than user's current time limit/override";
                return $result;
            }

            // check if the Begin Coursework button is not pressed
            $allocatable = user::find($student, false);
            $timelimit = new sub_timelimit($coursework, $allocatable);
            if ($timelimit->get_allocatable_sub_timelimit()) {
                $result['msg']      =   "Override can't be applied as Coursework has begun";
                return $result;
            }

            // check if override for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id());

            $override = $DB->get_record('coursework_overrides', $params);

            if (!$override) { // create a new override

                $csvdata = new \stdClass();
                //courseid, studentid, assessmentcode, timelimit

                $csvdata->allocatableid = $student->id;
                $csvdata->allocatabletype = 'user';
                $csvdata->courseworkid = $coursework->id();
                $csvdata->timelimit = $newtimelimit;
                $csvdata->createdbyid = $creatinguser;
                $csvdata->timecreated = time();

                $DB->insert_record('coursework_overrides', $csvdata);

                $result['actualaction']    =   'insert';
                $result['error']    =   false;
                $result['msg']      =   "coursework override created";
                return $result;

            } else { // update an override

                $new_override = new \stdClass();
                $new_override->id = $override->id;
                $new_override->timelimit = $newtimelimit;
                $new_override->createdbyid = $creatinguser;
                $new_override->timecreated = time();

                $DB->update_record('coursework_overrides', $new_override);

                $result['actualaction']    =   'update';
                $result['error']    =   false;
                $result['msg']      =   "coursework override updated";

                return $result;
            }

        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }
    }

    function    create_coursework_exemption($activity,$student,$course,$importrecord,$exemptiontype)    {
        global $USER, $DB;

        $coursework = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($coursework)) {

            // check if submission for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id());

            $mitigation = $DB->get_record('coursework_mitigations', $params);

            if (!$mitigation) { // create a new extension

                $csvdata = new \stdClass();
                //courseid, studentid, assessmentcode, extended_deadline, pre_defined_reason, extra_information_text

                $csvdata->allocatableid = $student->id;
                $csvdata->allocatabletype = 'user';
                $csvdata->courseworkid = $coursework->id();
                //$csvdata->extended_deadline = $extensiondate;
                //$csvdata->pre_defined_reason = $importrecord->reason_code;
                $csvdata->createdbyid = $creatinguser;
                //$csvdata->extra_information_text = $importrecord->reason_desc;
                //$csvdata->extra_information_format = 1;
                $csvdata->type = $exemptiontype;
                $csvdata->timecreated = time();

                $DB->insert_record('coursework_mitigations', $csvdata);

                $result['actualaction']    =   'insert';
                $result['error']    =   false;
                $result['msg']      =   "coursework exemption created";

                return $result;

            }  else { // update an extension

                $new_extension = new \stdClass();
                $new_extension->id = $mitigation->id;
                //$new_extension->extended_deadline = $extensiondate;
                //$new_extension->pre_defined_reason = $importrecord->reason_code;
                $new_extension->createdbyid = $creatinguser;
                //$new_extension->extra_information_text = $importrecord->reason_desc;
                //$new_extension->extra_information_format = 1;
                $new_extension->type = $exemptiontype;
                $new_extension->timecreated = time();

                $DB->update_record('coursework_mitigations', $new_extension);

                $result['actualaction']    =   'update';
                $result['error']    =   false;
                $result['msg']      =   "coursework exemption updated";

                return $result;
            }
        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }
    }


    function    create_quiz_extension($activity,$student,$course,$importrecord)    {
        global $DB;

        $quiz = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($quiz)) {

            // VALIDATE EXTENSION
            // simple validation of date
            $datevalue  =   explode('/',$importrecord->date);
            $yeartime = explode(' ',  $datevalue[2]);

            $timevalid = true;
            if (array_key_exists (1, $yeartime)){
                $timevalid = (bool)preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $yeartime[1]);
            }

            $extensiondate = strtotime(str_replace('/', '-', $importrecord->date));
            // extension format validation
            if(!checkdate( $datevalue[1] , $datevalue[0] , $yeartime[0]) || $timevalid == false){
                $result['msg']      =   "Invalid extension date";
                return $result;
            }

            // extension can't be smaller than user's deadline
            if($extensiondate < $quiz->timeclose) {
                $result['msg']      =   "Extension date must be later than quiz close time.";
                return $result;
            }

            if ($cm = get_coursemodule_from_instance('quiz', $quiz->id)) {
                //$course = $DB->get_record('course', array('id' => $quiz->course));
                $context = \context_module::instance($cm->id);

                // Check if the user is enrolled in the course
                if (is_enrolled($context, $student->id, '', true)) {
                    // Create or update the quiz override for the user
                    $quizoverride = $DB->get_record('quiz_overrides', array('quiz' => $quiz->id, 'userid' => $student->id), '*');
                    if (empty($quizoverride)) {
                        $quizoverride = new \stdClass();
                        $quizoverride->quiz = $quiz->id;
                        $quizoverride->userid = $student->id;
                        $quizoverride->timeopen = $quiz->timeopen;
                        $quizoverride->timeclose = $extensiondate;
                        $DB->insert_record('quiz_overrides', $quizoverride);

                        $result['actualaction']    =   'insert';
                        $result['error']    =   false;
                        $result['msg']      =   "quiz extension created";

                        return $result;

                    }
                    else {
                        if($extensiondate < $quizoverride->timeclose) {
                            $result['msg']      =   "Extension date must be later than the current extension time.";
                            return $result;
                        }
                        $quizoverride->timeclose = $extensiondate;
                        $DB->update_record('quiz_overrides', $quizoverride);

                        $result['actualaction']    =   'update';
                        $result['error']    =   false;
                        $result['msg']      =   "quiz extension updated";

                        return $result;
                     }

                } else {
                    $result['msg']      =   "User is not enrolled in the course.";
                    return $result;
                }
            }
            else {
                $result['msg']      =   "Course module not found.";
                return $result;
            }
        }
        else {
            $result['msg']      =   "activity not found";
            return $result;
        }
    }


    function    create_quiz_timelimit($activity,$student,$course,$importrecord)     {

        global  $DB;

        $quiz = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($quiz)) {

            // VALIDATE timelimit
            // simple validation of timelimit
            $newtimelimit = $importrecord->timelimit;

            if(!is_number($newtimelimit)){
                $result['msg']      =   "Invalid timelimit";
                return $result;
            }


            // timelimit can't be smaller than user's deadline
            if($newtimelimit < $quiz->timelimit) {
                $result['msg']      =   "Time limit must be later than quiz time limit.";
                return $result;
            }

            if ($cm = get_coursemodule_from_instance('quiz', $quiz->id)) {

                $context = \context_module::instance($cm->id);

                // Check if the user is enrolled in the course
                if (is_enrolled($context, $student->id, '', true)) {
                    // Create or update the quiz override for the user
                    $quizoverride = $DB->get_record('quiz_overrides', array('quiz' => $quiz->id, 'userid' => $student->id), '*');
                    if (empty($quizoverride)) {
                        $quizoverride = new \stdClass();
                        $quizoverride->quiz = $quiz->id;
                        $quizoverride->userid = $student->id;
                        $quizoverride->timelimit = $newtimelimit;
                        $DB->insert_record('quiz_overrides', $quizoverride);

                        $result['actualaction']    =   'insert';
                        $result['error']    =   false;
                        $result['msg']      =   "quiz timelimit created";

                        return $result;
                    }
                    else {
                        $quizoverride->timelimit = $newtimelimit;
                        $DB->update_record('quiz_overrides', $quizoverride);

                        $result['actualaction']    =   'update';
                        $result['error']    =   false;
                        $result['msg']      =   "quiz timelimit updated";

                        return $result;
                    }

                } else {
                    $result['msg']      =   "User is not enrolled in the course.";
                    return $result;
                }
            }
            else {
                $result['msg']      =   "Course module not found.";
                return $result;
            }
        }
        else {
            $result['msg']      =   "activity not found";
            return $result;
        }

    }


    function    delete_quiz_timelimit($activity,$student,$course,$importrecord,$type='time limit')     {

        global  $DB;

        $quiz = $activity;

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']    =   true;

        if (is_object($quiz)) {

            if ($cm = get_coursemodule_from_instance('quiz', $quiz->id)) {

                $context = \context_module::instance($cm->id);

                // Check if the user is enrolled in the course
                if (is_enrolled($context, $student->id, '', true)) {
                    // Create or update the quiz override for the user
                    $quizoverride = $DB->get_record('quiz_overrides', array('quiz' => $quiz->id, 'userid' => $student->id), '*');
                    if (!empty($quizoverride)) {

                        //delete record
                        $DB->delete_records('quiz_overrides',array('id'=>$quizoverride->id));
                        $result['actualaction']    =   'delete';
                        $result['error']    =   false;
                        $result['msg']      =   "quiz {$type} record deleted";
                        return $result;

                    }   else {
                        $result['msg']      =   "quiz {$type} record was not found";
                        return $result;
                    }
                }   else    {
                    $result['msg']      =   "User is not enrolled in the course.";
                    return $result;
                }
            }   else    {
                $result['msg']      =   "Course module not found.";
                return $result;
            }
        }   else    {
            $result['msg']      =   "activity not found";
            return $result;
        }
    }


    function        delete_quiz_extension($activity,$student,$course,$importrecord)     {
           return  $this->delete_quiz_timelimit($activity,$student,$course,$importrecord,'extension');
    }

    function        delete_coursework_mitigation($activity,$student,$course,$importrecord)      {

        global $USER, $DB;

        $coursework = $activity;

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($coursework)) {

            // check if submission for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id(),
                'type'=>'extension');

            $mitigation = $DB->get_record('coursework_mitigations', $params);

            if (!empty($mitigation)) { // does the mitigation exist

                //delete record
                $DB->delete_records('coursework_mitigations',array('id'=>$mitigation->id));
                $result['actualaction']    =   'delete';
                $result['error']    =   false;
                $result['msg']      =   "mitigation record deleted";
                return $result;

            }   else {
                $result['msg']      =   "mitigation record was not found";
                return $result;
            }
        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }


    }
    
    
    function    delete_coursework_override($activity,$student,$course,$importrecord)   {

        global $DB;
        
        $coursework = $activity;

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($coursework)) {

            // check if override for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id());

            $override = $DB->get_record('coursework_overrides', $params);

            if (!empty($override)) { // create a new override

                //delete record
                $DB->delete_records('coursework_overrides',array('id'=>$override->id));
                $result['actualaction']    =   'delete';
                $result['error']    =   false;
                $result['msg']      =   "override record deleted";
                return $result;
            }   else {
                $result['msg']      =   "override record was not found";
                return $result;
            }

        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }
        
    }

    function delete_coursework_exemption($activity,$student,$course,$importrecord,$exemptiontype) {

        global $USER, $DB;

        $coursework = $activity;

        $result         =   array();
        $result['courseid']     =   $course->id;
        $result['studentid']    =   $student->id;
        $result['activityid']   =   $activity->id;
        $result['error']        =   true;

        if (is_object($coursework)) {

            // check if submission for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id(),
                'type' => $exemptiontype);

            $mitigation = $DB->get_record('coursework_mitigations', $params);

            if (!empty($mitigation)) { // create a new extension
                $DB->delete_records('coursework_mitigations',array('id'=>$mitigation->id));

                $result['actualaction']    =   'delete';
                $result['error']    =   false;
                $result['msg']      =   "{$exemptiontype} exemption record deleted";
                return $result;
            } else {
                $result['msg']      =   "{$exemptiontype} exemption record was not found";
                return $result;
            }
        }   else {
            $result['msg']      =   "activity not found";
            return $result;
        }

    }

}