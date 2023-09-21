<?php

namespace local_module_extensions_upload;

use mod_coursework\models\coursework;
use mod_coursework\models\user;
/**
 * Class processor
 * Handle the CSV import and Coursework creation.
 */

require_once($CFG->dirroot.'/config.php');
require_once($CFG->dirroot.'/mod/coursework/lib.php');

use core\event\tag_added;
use core_tag\output\tag;
use mod_coursework;
class   processor     {


    /**
     * Processes data from a uploaded csv
     * @param $filename
     * @param string $delimiter
     * @return array
     */


    function    dataprocessor($filename, $moduletype, $delimiter=',')     {

        global  $DB,$USER;

        if (file_exists($filename))   {

            $filehandle = fopen($filename, "r");

            $file = new \stdClass();
            $file->file_path = $filename;
            $file->import_time = time();

            $linenumber     =   0;
            $added          =   0;
            $updated        =   0;
            $errors         =   array();

            while(!feof($filehandle)) {

                $line_of_text = fgetcsv($filehandle, 1024, $delimiter);
                if ($linenumber == 0) { // skip headers line
                    $linenumber++;
                    continue;
                }
                if ($line_of_text && $line_of_text[0] != null) {
                    if ($line_of_text[0] != '' && $line_of_text[1] != ''
                        && $line_of_text[2] != '' && $line_of_text[3] != '') {

                        // find course by shortname $line_of_text[0]
                        $course = $DB->get_record('course', array('shortname' => $line_of_text[0]));
                        if(!$course){
                            $errors[$linenumber] = "Course doesn't exist";
                            $linenumber++;
                            continue;
                        }

                        $record_spr = $DB->get_record('local_user_info_ext', ['value' => trim($line_of_text[1])], IGNORE_MULTIPLE);

                        $student = $DB->get_record('user', array('id' => $record_spr->userid));
                        if(!$student){
                            $errors[$linenumber] = "Student doesn't exist";
                            $linenumber++;
                            continue;
                        }

                        // workout coursework from assessmentcode
                        $activity = $this->get_module_from_assessmentcode($course->id, $student->id, $line_of_text[2], $moduletype);

                        if ($moduletype == 'coursework') {

                            $coursework = $activity;

                            if (is_object($coursework)) {

                                // VALIDATE EXTENSION
                                $user_deadline = $coursework->get_allocatable_deadline($student->id);
                                // simple validation of date
                                $datevalue  =   explode('/',$line_of_text[3]);
                                $yeartime = explode(' ',  $datevalue[2]);

                                $timevalid = true;
                                if (array_key_exists (1 , $yeartime)){
                                    $timevalid = (bool)preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $yeartime[1]);
                                }

                                $extensiondate = strtotime(str_replace('/', '-', $line_of_text[3]));
                                // extension format validation
                                if(!checkdate( $datevalue[1] , $datevalue[0] , $yeartime[0]) || $timevalid == false){
                                    $errors[$linenumber] = "Invalid extension date";
                                    $linenumber++;
                                    continue;
                                }
                                // extension can't be smaller than user's deadline
                                if($extensiondate < $user_deadline){
                                    $errors[$linenumber] = "Extension date must be later than user's deadline/current extension";
                                    $linenumber++;
                                    continue;
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
                                    $csvdata->pre_defined_reason = $line_of_text[4];
                                    $csvdata->createdbyid = $USER->id;
                                    $csvdata->extra_information_text = $line_of_text[5];
                                    $csvdata->extra_information_format = 1;
                                    $csvdata->type = 'extension';

                                    $DB->insert_record('coursework_mitigations', $csvdata);
                                    $added++;

                                } else { // update an extension

                                    $new_extension = new \stdClass();
                                    $new_extension->id = $mitigation->id;
                                    $new_extension->extended_deadline = $extensiondate;
                                    $new_extension->pre_defined_reason = $line_of_text[4];
                                    $new_extension->createdbyid = $USER->id;
                                    $new_extension->extra_information_text = $line_of_text[5];
                                    $new_extension->extra_information_format = 1;
                                    $new_extension->type = 'extension';

                                    $DB->update_record('coursework_mitigations', $new_extension);
                                    $updated++;

                                }

                            } else {
                                $errors[$linenumber] = $activity;
                            }
                        }
                        else if ($moduletype == 'quiz') {

                            $quiz = $activity;

                            if (is_object($quiz)) {

                                // VALIDATE EXTENSION
                                // simple validation of date
                                $datevalue  =   explode('/',$line_of_text[3]);
                                $yeartime = explode(' ',  $datevalue[2]);

                                $timevalid = true;
                                if (array_key_exists (1, $yeartime)){
                                    $timevalid = (bool)preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $yeartime[1]);
                                }

                                $extensiondate = strtotime(str_replace('/', '-', $line_of_text[3]));
                                // extension format validation
                                if(!checkdate( $datevalue[1] , $datevalue[0] , $yeartime[0]) || $timevalid == false){
                                    $errors[$linenumber] = "Invalid extension date";
                                    $linenumber++;
                                    continue;
                                }

                                // extension can't be smaller than user's deadline
                                if($extensiondate < $quiz->timeclose) {
                                    $errors[$linenumber] = "Extension date must be later than quiz close time.";
                                    $linenumber++;
                                    continue;
                                }

                                if ($cm = get_coursemodule_from_instance('quiz', $quiz->id)) {
                                    $course = $DB->get_record('course', array('id' => $quiz->course));
                                    $context = context_module::instance($cm->id);

                                    // Check if the user is enrolled in the course
                                    if (is_enrolled($context, $student->id, '', true)) {
                                        // Create or update the quiz override for the user
                                        $quizoverride = $DB->get_record('quiz_overrides', array('quiz' => $quiz->id, 'userid' => $student->id), '*');
                                        if (empty($quizoverride)) {
                                            $quizoverride = new stdClass();
                                            $quizoverride->quizid = $quiz->id;
                                            $quizoverride->userid = $student->id;
                                            $quizoverride->timestart = $quiz->timeopen;
                                            $quizoverride->timeclose = $extensiondate;
                                            $DB->insert_record('quiz_overrides', $quizoverride);
                                        }
                                        else {
                                            if($extensiondate < $quizoverride->timeclose) {
                                                $errors[$linenumber] = "Extension date must be later than the current extension time.";
                                                $linenumber++;
                                                continue;
                                            }
                                            $quizoverride->timeclose = $extensiondate;
                                            $DB->update_record('quiz_overrides', $quizoverride);
                                        }

                                    } else {
                                        $errors[$linenumber] = "User is not enrolled in the course.";
                                    }
                                }
                                else {
                                    $errors[$linenumber] = "Course module not found.";
                                }
                            }
                            else {
                                $errors[$linenumber] = $activity;
                            }
                        }
                    }
                    else {
                        $errors[$linenumber] = 'Wrong file format or any of the first 4 columns is empty';
                    }

                    $linenumber++;
                }
            }

            fclose($filehandle);

            //processing of the file has been completed now delete the file

            if (unlink($filename)) {
                //echo "\n\nSource file was successfully deleted";
            } else {
                // echo "\n\nCould not delete the source file!!!";
            }
        }
        $info           =   new \stdClass();
        $info->added    =   $added;
        $info->updated  =   $updated;
        $info->errors   =   $errors;
        $info->lines    =   $linenumber-1;

        return  $info;
    }

    /**
     * @param $courseid
     * @param $studentid
     * @param $assessmentcode
     * @return mixed|mod_coursework\decorators\coursework_groups_decorator|\mod_coursework_coursework|string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_module_from_assessmentcode($courseid, $studentid, $assessmentcode, $moduletype){
        global $DB;

        $activities = array();
        $cmid = '';
        // find the group to which assessmentcode in specified course belongs to
        $group = $DB->get_record('groups', array('courseid'=>$courseid, 'name' => $assessmentcode));

        // get module id for moduletype
        $module = $DB->get_record('modules', array('name' => $moduletype));

        // get cmid for the specific activities that are not hidden
        if($group) {
            $sql = "SELECT * 
                FROM {course_modules} 
                WHERE module = :module 
                AND visible = 1
                AND course = :course
                AND availability LIKE '%{\"type\":\"group\",\"id\":{$group->id}}%'";

            $cms = $DB->get_records_sql($sql, array('module' => $module->id, 'course' => $courseid));

            // remove not available activities
            foreach ($cms as $cm) {
                $inf = '';
                $modinfo = get_fast_modinfo($cm->course);

                $current_coursemodules = $modinfo->get_cms();

                if (empty($current_coursemodules) || empty($current_coursemodules[$cm->id])) return '';

                $cminfo = $modinfo->get_cm($cm->id);
                $cmid = $cm->instance;

                $availability = new \core_availability\info_module($cminfo);

                $available = $availability->is_available($inf, false, $studentid);
                if ($available == false) {
                    continue;
                }

                $activities[] = $cm->instance;
            }
        }

        if ($moduletype == 'coursework') {

            if(count($activities) == 1) {
                // get courseworkid

                $coursework = coursework::find($activities[0]);
                if($coursework && $coursework->use_groups){
                    // error group cw?
                    $coursework = 'Group coursework';
                } elseif (!$coursework){

                    $coursework = "Coursework doesn't exist";
                }

            }
            else if (count($activities) > 1) { // skip if not found or found more than 1
                $coursework = 'Found more than 1 courseworks with the same assessmentcode';
            }
            else {
                $coursework = '';
                if($cmid){
                    $coursework = coursework::find($cmid);
                }
                if($coursework && $coursework->use_groups){
                    // error group cw?
                    $coursework = 'Group coursework';
                } else {
                    $coursework = 'Coursework not found';
                }
            }

            return $coursework;
        }
        else if ($moduletype == 'quiz') {

            if(count($activities) == 1) {
                return $activities[0];
            }
            else if (count($activities) > 1) { // skip if not found or found more than 1
                return 'Found more than 1 quizzes with the same assessmentcode';
            }
            else {
                $quiz = '';
                if($cmid){
                    $quiz = $DB->get_record('quiz', ['id' => $cmid]);
                }
                else {
                    $quiz = 'Quiz not found';
                }

                return $quiz;
            }
        }
    }

}