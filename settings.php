<?php

defined('MOODLE_INTERNAL') || die();

global $DB, $OUTPUT, $ADMIN, $CFG, $PAGE;

$PAGE->requires->jquery();

// Will set off the function that adds listeners for onclick/onchange etc.
$jsmodule = array(
    'name' => 'local_module_extensions_upload',
    'fullpath' => '/local/module_extensions_upload/module.js',
    'requires' => array('base',
        'node-base')
);
$PAGE->requires->js_init_call('M.local_module_extensions_upload.local_module_extensions_upload_admin_page',
    array(),
    false,
    $jsmodule);

$settings =  new admin_settingpage('module_extensions_upload', get_string('pluginname', 'local_module_extensions_upload'));


$localexits = $ADMIN->locate('localplugins');

if (!is_null($localexits)) {

    $ADMIN->add('localplugins', $settings);



    $settings->add(new admin_setting_configcheckbox('local_module_extensions_upload/importdata', get_string('importdata', 'local_module_extensions_upload'), get_string('importdata_desc', 'local_module_extensions_upload'), 0));


    /*
* ----------------------
* DB connection settings
* ----------------------
*/
    $settings->add(new admin_setting_heading('local_module_extensions_upload_settings', get_string('dbsettings', 'local_module_extensions_upload'), ''));

    $options =  array( 'local' => get_string('local','local_module_extensions_upload'), 'external' => get_string('external','local_module_extensions_upload'));

    $settings->add(new admin_setting_configselect('local_module_extensions_upload/dblocation', get_string('dblocation','local_module_extensions_upload'), get_string('dblocation_desc','local_module_extensions_upload'), 'local', $options));

    $options = array('', "access", "ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "pdo", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);

    $settings->add(new admin_setting_configselect('local_module_extensions_upload/dbtype', get_string('dbtype', 'local_module_extensions_upload'), get_string('dbtype_desc', 'local_module_extensions_upload'), '', $options));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/dbhost', get_string('dbhost', 'local_module_extensions_upload'), get_string('dbhost_desc', 'local_module_extensions_upload'), 'localhost'));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/dbuser', get_string('dbuser', 'local_module_extensions_upload'), '', ''));
    $settings->add(new admin_setting_configpasswordunmask('local_module_extensions_upload/dbpass', get_string('dbpass', 'local_module_extensions_upload'), '', ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/dbname', get_string('dbname', 'local_module_extensions_upload'), get_string('dbname_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configcheckbox('local_module_extensions_upload/dbsybasequoting', get_string('dbsybasequoting', 'local_module_extensions_upload'), get_string('dbsybasequoting_desc', 'local_module_extensions_upload'), 0));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/dbsetupsql', get_string('dbsetupsql', 'local_module_extensions_upload'),get_string('dbsetupsql_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configcheckbox('local_module_extensions_upload/debugdb', get_string('debugdb', 'local_module_extensions_upload'), get_string('debugdb_desc', 'local_module_extensions_upload'), 0));
    $settings->add(new admin_setting_configcheckbox('local_module_extensions_upload/output_debug', get_string('output_debug', 'local_module_extensions_upload'), get_string('output_debug_desc', 'local_module_extensions_upload'), 0));




    $settings->add(new admin_setting_heading('import_table', get_string('import_table', 'local_module_extensions_upload') ,''));


    $settings->add(new admin_setting_configtext('local_module_extensions_upload/tablename', get_string('tablename', 'local_module_extensions_upload'), get_string('tablename_desc', 'local_module_extensions_upload'), ''));

    $settings->add(new admin_setting_configtext('local_module_extensions_upload/id', get_string('id', 'local_module_extensions_upload'), get_string('id_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/user', get_string('user', 'local_module_extensions_upload'), get_string('user_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/course', get_string('course', 'local_module_extensions_upload'), get_string('course_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/assessment', get_string('assessment', 'local_module_extensions_upload'), get_string('assessment_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/date', get_string('date', 'local_module_extensions_upload'), get_string('date_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/timelimit', get_string('timelimit', 'local_module_extensions_upload'), get_string('timelimit_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/type', get_string('type', 'local_module_extensions_upload'), get_string('type_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/reason_code', get_string('reason_code', 'local_module_extensions_upload'), get_string('reason_code_desc', 'local_module_extensions_upload'), ''));

    $settings->add(new admin_setting_configtext('local_module_extensions_upload/reason_desc', get_string('reason_desc', 'local_module_extensions_upload'), get_string('reason_desc_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/action', get_string('action', 'local_module_extensions_upload'), get_string('action_desc', 'local_module_extensions_upload'), ''));
    $settings->add(new admin_setting_configtext('local_module_extensions_upload/timecreated', get_string('timecreated', 'local_module_extensions_upload'), get_string('timecreated', 'local_module_extensions_upload'), ''));

    $options = array("shortname"=>get_string('shortname', 'local_module_extensions_upload'), "idnumber"=>get_string('idnumber', 'local_module_extensions_upload'));

    $settings->add(new admin_setting_configselect('local_module_extensions_upload/courseidentifier', get_string('courseidentifier', 'local_module_extensions_upload'), get_string('courseidentifier_desc', 'local_module_extensions_upload'), '', $options));


    $options = array("username"=>get_string('username', 'local_module_extensions_upload'), "idnumber"=>get_string('idnumber', 'local_module_extensions_upload'), "email"=>get_string('email', 'local_module_extensions_upload'));

    $settings->add(new admin_setting_configselect('local_module_extensions_upload/useridentifier', get_string('useridentifier', 'local_module_extensions_upload'), get_string('useridentifier_desc', 'local_module_extensions_upload'), '', $options));


    $settings->add(new admin_setting_heading('log_heading_section', get_string('log_heading', 'local_module_extensions_upload') ,''));

    $settings->add(new admin_setting_configtext('local_module_extensions_upload/lognotificationusers', get_string('lognotificationusers', 'local_module_extensions_upload'), get_string('lognotificationusers_desc', 'local_module_extensions_upload'), ''));



}