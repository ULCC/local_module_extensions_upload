<?php

namespace local_module_extensions_upload\task;

use local_module_extensions_upload\import_extension_data;
use local_module_extensions_upload\module_ext_log;
use mod_data\output\empty_database_action_bar;

/**
* A scheduled task for the coursework module cron.
*
* @package    mod_coursework
* @copyright  2014 ULCC
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class import_extensions extends \core\task\scheduled_task {

    /**
    * Get a descriptive name for this task (shown to admins).
    *
    * @return string
    */
    public function get_name() {
        return get_string('importextensions', 'local_module_extensions_upload');
    }

    /**
    * Run coursework cron.
    */
    public function execute() {

        global $CFG, $DB;


        $importextdata    =   new     import_extension_data();

        $pluginconfig   =   get_config('local_module_extensions_upload');

        //first lets check if the settings have been entered correctly

        if (!empty($pluginconfig->output_debug)) mtrace("Performing check of plugin table settings");

        $missingsettings    =   $importextdata->check_config($pluginconfig);

        $sql    =   "SELECT      *  
                                 FROM       {$pluginconfig->tablename}";
        if (!empty($pluginconfig->lastcron))   {
            $sql    .=    " WHERE   {$pluginconfig->timecreated} > $pluginconfig->lastcron ";
        }


        if (empty($missingsettings)) {

            if ($pluginconfig->dblocation == "external") {

                $extconn    =   $importextdata->external_db_init($pluginconfig->dbtype,$pluginconfig->dbhost, $pluginconfig->dbuser, $pluginconfig->dbpass, $pluginconfig->dbname, $pluginconfig->dbsetupsql,$pluginconfig->debugdb);

                // Connect to external DB.
                if (empty($extconn)) {
                    if (!empty($pluginconfig->output_debug)) mtrace(get_string('dbconnectionfailed'));
                    return;
                }


                $extdata = $extconn->Execute($sql);
                if (!empty($pluginconfig->output_debug)) mtrace("DB query");
                if (!empty($pluginconfig->output_debug)) mtrace($sql);

                $importedextensionrecords = array();
                if ($extdata) {
                    while (!$extdata->EOF) {
                        $importedextensionrecords[] = (object) $extdata->fields;
                        $extdata->MoveNext();
                    }
                    $extdata->Close();
                    //$importedextensionrecords[]   =   $record;
                }
                if (empty($enrolmentsprs)) {
                    if (!empty($pluginconfig->output_debug)) mtrace("No records returned!");
                } else {
                    if (!empty($pluginconfig->output_debug)) mtrace("records".count($enrolmentsprs)." returned!");
                }

            } else if ($pluginconfig->dblocation == "local") {
                $dbman = $DB->get_manager();

                //check if module_extensions_queue

                if (!$dbman->table_exists($pluginconfig->tablename)) {

                    //get all records from module_extensions_queue that were created since the last execution
                    $importedextensionrecords  =  $DB->get_records_sql($sql);


                }

            }

            set_config('lastcron',time(),'local_module_extensions_upload');

            
            $importdataprocessor    =   new     \local_module_extensions_upload\import_data_processor();

            $importresults      =   $importdataprocessor->process_data($importedextensionrecords);
            $logger     =   new     module_ext_log();

            $logger->log(2,'automatic',$importresults);


        }   else    {
            if (!empty($pluginconfig->output_debug))  mtrace("The following config settings are missing : ".implode(" ,",$missingsettings));

        }

        $recorddata =   "";

        if (!empty($missingsettings))   {

            $msgheader      =   get_string('missingconfigsubject','local_module_extensions_upload');
            $msgdata       =   get_string('missingconfig','local_module_extensions_upload',implode("<br />",$missingsettings));

        }   else {

            $msgheader  =   'Module extension import result';

            $added  =   0;
            $updated    =   0;
            $deleted    =   0;
            $errors     =   0;

            foreach($importresults  as  $res)   {

                $extensionrec       =       $res['record'];
                $resultrecord       =       (is_object($res['result']))  ?  $res['result'] : (object) $res['result'] ;

                if (!empty($resultrecord->error))  {
                    $errors++;
                }  else if ($resultrecord->actualaction ==  'insert')   {
                    $added++;
                }   else if ($resultrecord->actualaction ==  'update')   {
                    $updated++;
                } else if ($resultrecord->actualaction ==  'delete')   {
                    $deleted++;
                }



            }

            $msgdataobj         =   array();
            $msgdataobj['importdate']   =   date('d-m-Y H:i');
            $msgdataobj['recordcount']   =   count($importresults);
            $msgdataobj['added']   =   $added;
            $msgdataobj['updated']   =   $updated;
            $msgdataobj['deleted']   =   $deleted;
            $msgdataobj['errors']   =   $errors;

            $msgdata    =   get_string('import_result_notification','local_module_extensions_upload',$msgdataobj);
            $tablerowdata   =   "";

            foreach($importresults  as  $res)   {

                $extensionrec       =       $res['record'];
                $resultrecord       =       (is_object($res['result']))  ?  $res['result'] : (object) $res['result'] ;

                $rowdata            =   array();

                $rowdata['course']        =   $extensionrec->course;
                $rowdata['user']        =   $extensionrec->user;
                $rowdata['assessment']        =   $extensionrec->assessment;
                $rowdata['error']             =    (!empty($resultrecord->error))   ?   "*" : "";
                $rowdata['msg']        =   $resultrecord->msg;

                $tablerowdata         .=     get_string('import_result_notification_tr','local_module_extensions_upload',$rowdata);
            }

            $recorddata     =  get_string('import_result_notification_table','local_module_extensions_upload',$tablerowdata);




        }

        $notifyusers    =   explode(",",$pluginconfig->lognotificationusers );

        foreach($notifyusers   as  $nu) {

            // New approach.
            $eventdata = new \core\message\message();
            $eventdata->component = 'local_module_extensions_upload';
            $eventdata->name = 'automatic_update_result';
            $eventdata->userfrom = \core_user::get_noreply_user();
            $eventdata->userto = $nu;
            $eventdata->subject = $msgheader;

            $eventdata->fullmessage = $msgdata . $recorddata;
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml = $msgdata . $recorddata;;
            $eventdata->smallmessage = $msgdata;
            $eventdata->notification = 1;
            // $eventdata->contexturl =
            //   $CFG->wwwroot . '/local/coursework/view.php?id=' . $coursework->get_coursemodule_id();
            //$eventdata->contexturlname = 'View the submission here';
            //$eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }


    }
}