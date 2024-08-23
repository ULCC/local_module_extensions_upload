<?php

namespace local_module_extensions_upload\task;

use local_module_extensions_upload\import_extension_data;

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


        if (empty($missingdata)) {

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

            
            $importdataprocessor    =   new     \local_module_extensions_upload\import_data_processor();

            $importdataprocessor->process_data($importedextensionrecords);





        }   else    {
            if (!empty($pluginconfig->output_debug))  mtrace("The following config settings are missing : ".implode(" ,",$missingsettings));
        }


    }
}