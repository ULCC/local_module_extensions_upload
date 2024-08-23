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



                } else if ($record->type == 'coursework_overrides') {

                    if ($record->action == 'insert') {

                        $result = $this->create_coursework_override($activity, $student, $course, $record);

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_override($activity, $student, $course, $record);
                    }

                } else if ($record->type == 'coursework_permanent_exemption') {

                    if ($record->action == 'insert') {

                        $result = $this->create_coursework_exemption($activity, $student, $course, $record,'permanent');

                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_exemption($activity, $student, $course, $record,'permanent');
                    }

                } else if ($record->type == 'coursework_temporary_exemption') {

                    if ($record->action == 'insert') {
                        $result = $this->create_coursework_exemption($activity, $student, $course, $record,'temporary');
                    } else if ($record->action == 'delete') {
                        $result =   $this->delete_coursework_exemption($activity, $student, $course, $record,'temporary');
                    }

                }   else if ($record->type == 'quiz_extensions') {

                    if ($record->action == 'insert') {

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

                $importresults[]    =   array('record'=>$record,'importresult'=>$result);



                //log result of record import


            }
        }

        print_r($importresults);



    }



    function    create_coursework_mitigation($activity,$student,$course,$importrecord)
    {

        global $USER, $DB;

        $coursework = $activity;

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
                return array('error' => true, 'msg' => "Invalid extension date");


            }
            // extension can't be smaller than user's deadline
            if ($extensiondate < $user_deadline) {
                return array('error' => true, 'msg' => "Extension date must be later than user's deadline/current extension");
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

                return array('error' => false, 'msg' => "coursework mitigation created");


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

                return array('error' => false, 'msg' => "coursework mitigation updated");


            }
        }   else {
            //$errors[$linenumber] = $activity;
            return array('error' => true, 'msg' => "activity not found");
        }

    }


    function    create_coursework_override($activity,$student,$course,$importrecord)   {
        global  $DB;

        $coursework = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        if (is_object($coursework)) {

            // VALIDATE TIMELIMIT
            $user_timelimit = $coursework->get_allocatable_timelimit($student->id);

            print_r($user_timelimit);
            // simple validation of timelimit
            $newtimelimit = $importrecord->timelimit;
            if(!is_number($newtimelimit)){
                return array('error' => true, 'msg' => "Invalid timelimit");
            }

            // extension can't be smaller than user's deadline
            if($newtimelimit < $user_timelimit){
                return array('error' => true, 'msg' => "Time limit must be later than user's current time limit/override");
            }

            // check if the Begin Coursework button is not pressed
            $allocatable = user::find($student, false);
            $timelimit = new sub_timelimit($coursework, $allocatable);
            if ($timelimit->get_allocatable_sub_timelimit()) {
                return array('error' => true, 'msg' => "Override can't be applied as Coursework has begun");
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
                return array('error' => false, 'msg' => "coursework override created");

            } else { // update an override

                $new_override = new \stdClass();
                $new_override->id = $override->id;
                $new_override->timelimit = $newtimelimit;
                $new_override->createdbyid = $creatinguser;
                $new_override->timecreated = time();

                $DB->update_record('coursework_overrides', $new_override);
                return array('error' => false, 'msg' => "coursework override updated");
            }

        }   else {
          //$errors[$linenumber] = $activity;
            return array('error' => true, 'msg' => "activity not found");
        }
    }

    function    create_coursework_exemption($activity,$student,$course,$importrecord,$exemptiontype)    {
        global $USER, $DB;

        $coursework = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

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

                return array('error' => false, 'msg' => "coursework exemption created");


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

                return array('error' => false, 'msg' => "coursework exemption updated");


            }
        }   else {
            return array('error' => true, 'msg' => "activity not found");
        }
    }


    function    create_quiz_extension($activity,$student,$course,$importrecord)    {
        global $DB;

        $quiz = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

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
                return array('error' => true, 'msg' => "Invalid extension date");
            }

            // extension can't be smaller than user's deadline
            if($extensiondate < $quiz->timeclose) {
                return array('error' => true, 'msg' => "Extension date must be later than quiz close time.");
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
                        return array('error' => false, 'msg' => "quiz extension created");

                    }
                    else {
                        if($extensiondate < $quizoverride->timeclose) {

                            return array('error' => true, 'msg' => "Extension date must be later than the current extension time.");
                        }
                        $quizoverride->timeclose = $extensiondate;
                        $DB->update_record('quiz_overrides', $quizoverride);
                        return array('error' => false, 'msg' => "quiz extension updated");
                     }

                } else {
                    return array('error' => true, 'msg' => "User is not enrolled in the course.");
                }
            }
            else {
                return array('error' => true, 'msg' => "Course module not found.");
            }
        }
        else {
            return array('error' => true, 'msg' => "activity not found");
        }
    }


    function    create_quiz_timelimit($activity,$student,$course,$importrecord)     {

        global  $DB;

        $quiz = $activity;

        $creatinguser   =   2; //TODO find out which user should be assigned as the creator

        if (is_object($quiz)) {

            // VALIDATE timelimit
            // simple validation of timelimit
            $newtimelimit = $importrecord->timelimit;

            if(!is_number($newtimelimit)){
                return array('error' => true, 'msg' => "Invalid timelimit");
            }


            // timelimit can't be smaller than user's deadline
            if($newtimelimit < $quiz->timelimit) {
                return array('error' => true, 'msg' => "Time limit must be later than quiz time limit.");
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
                        return array('error' => false, 'msg' => "quiz timelimit created");

                    }
                    else {
                        $quizoverride->timelimit = $newtimelimit;
                        $DB->update_record('quiz_overrides', $quizoverride);
                        return array('error' => false, 'msg' => "quiz timelimit updated");
                    }

                } else {
                    return array('error' => true, 'msg' => "User is not enrolled in the course.");
                }
            }
            else {
                return array('error' => true, 'msg' => "Course module not found.");
            }
        }
        else {
            return array('error' => true, 'msg' => "activity not found");
        }

    }


    function    delete_quiz_timelimit($activity,$student,$course,$importrecord,$type='time limit')     {

        global  $DB;

        $quiz = $activity;

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
                        return array('error' => false, 'msg' => "quiz {$type} record deleted");

                    }   else {
                        return array('error' => true, 'msg' => "quiz {$type} record was not found");
                    }
                }   else    {
                    return array('error' => true, 'msg' => "User is not enrolled in the course.");
                }
            }   else    {
                return array('error' => true, 'msg' => "Course module not found.");
            }
        }   else    {
            return array('error' => true, 'msg' => "activity not found");
        }
    }


    function        delete_quiz_extension($activity,$student,$course,$importrecord)     {
           return  $this->delete_quiz_timelimit($activity,$student,$course,$importrecord,'extension');
    }

    function        delete_coursework_mitigation($activity,$student,$course,$importrecord)      {

        global $USER, $DB;

        $coursework = $activity;

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
                return array('error' => false, 'msg' => "mitigation record deleted");

            }   else {
                return array('error' => true, 'msg' => "mitigation record was not found");
            }
        }   else {
            return array('error' => true, 'msg' => "activity not found");
        }


    }
    
    
    function    delete_coursework_override($activity,$student,$course,$importrecord)   {

        global $DB;
        
        $coursework = $activity;

        if (is_object($coursework)) {

            // check if override for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id());

            $override = $DB->get_record('coursework_overrides', $params);

            if (!empty($override)) { // create a new override

                //delete record
                $DB->delete_records('coursework_overrides',array('id'=>$override->id));
                return array('error' => false, 'msg' => "override record deleted");
            }   else {
                return array('error' => true, 'msg' => "override record was not found");
            }

        }   else {
            return array('error' => true, 'msg' => "activity not found");
        }
        
    }

    function delete_coursework_exemption($activity,$student,$course,$importrecord,$exemptiontype) {

        global $USER, $DB;

        $coursework = $activity;

        if (is_object($coursework)) {

            // check if submission for this user already exists
            $params = array('allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id(),
                'type' => $exemptiontype);

            $mitigation = $DB->get_record('coursework_mitigations', $params);

            if (!empty($mitigation)) { // create a new extension
                $DB->delete_records('coursework_mitigations',array('id'=>$mitigation->id));

                return array('error' => false, 'msg' => "{$exemptiontype} exemption record deleted");
            } else {
                return array('error' => true, 'msg' => "{$exemptiontype} exemption record was not found");
            }
        }   else {
            return array('error' => true, 'msg' => "activity not found");
        }

    }

}