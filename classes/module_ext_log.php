<?php

namespace   local_module_extensions_upload;



class module_ext_log     {

    function    log($userid,$type,$extensionrecords)   {

        global  $DB;


        $logrecord     =       new \stdClass();

        $logrecord->userid         =   $userid;
        $logrecord->type           =   $type;
        $logrecord->timecreated    =   time();

        $uploadid   =   $DB->insert_record('local_module_ext_upload',$logrecord);

        foreach($extensionrecords   as  $record)    {

            $this->record_log($uploadid,$record);

        }


    }


    function    record_log($uploadid,$record) {

        global  $DB;

        $logrecord      =       new     \stdClass();

        $logrecord->uploadid    =   $uploadid;

        $extensionrec       =       $record['record'];
        $resultrecord       =       (is_object($record['result']))  ?  $record['result'] : (object) $record['result'] ;




        $logrecord->course  =       $extensionrec->course;
        $logrecord->assessment  =   $extensionrec->assessment;
        $logrecord->student     =   $extensionrec->user;
        $logrecord->courseid    =   $resultrecord->courseid;
        $logrecord->studentid   =   $resultrecord->studentid;
        $logrecord->activityid  =   $resultrecord->activityid;

        $logrecord->data        =   serialize($extensionrec);
        $logrecord->action      =   $extensionrec->action;
        $logrecord->actualaction      =   (!empty($resultrecord->actualaction))   ? $resultrecord->actualaction  :  '';
        $logrecord->result      =   $resultrecord->msg;
        $logrecord->timecreated      =   $resultrecord->timecreated;
        $logrecord->error       =   (!empty($extensionrec->error))  ? 1  : 0;

        $DB->insert_record('local_module_ext_upload_data',$logrecord);



    }


}
