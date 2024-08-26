<?php


namespace   local_module_extensions_upload;



class import_extension_data     {

    function    check_config($pluginconfig)  {

        $configfields   =   array('tablename','id','user','course','assessment','date','timelimit','type','reason_code','reason_desc','action','timecreated');
        $missing        =   array();

        foreach ($configfields as $field) {
                if (empty($pluginconfig->{$field})) {
                    $missing[] = $field;
                }
        }

        return $missing;
    }

    function external_db_init($dbtype,$dbhost, $dbuser, $dbpass, $dbname, $dbsetupsql,$debugdb) {
        global $CFG;

        require_once($CFG->libdir . '/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($dbtype);

        if ($debugdb) {
            $extdb->debug = true;
        }

        // The dbtype may contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($dbhost, $dbuser, $dbpass, $dbname, true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);

        if ($dbsetupsql) {
            $extdb->Execute($dbsetupsql);
        }

        return $extdb;
    }



}



