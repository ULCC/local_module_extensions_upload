<?php

require_once("../../../config.php");
require_once($CFG->dirroot."/local/module_extensions_upload/classes/forms/upload_extensions_mform.php");

global $DB, $CFG, $OUTPUT, $PAGE, $USER;
require_login();

$context = context_system::instance();

if(!has_capability('local/module_extensions_upload:view', $context)) {
    //return error
    throw new moodle_exception(get_string('nopermission', 'local_module_extensions_upload'));
}

$PAGE->set_url('/local/module_extensions_upload/actions/upload_extensions.php');

$PAGE->set_pagelayout('standard');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_title(get_string('uploadextensions','local_module_extensions_upload'));

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('pluginname','local_module_extensions_upload'));
$PAGE->requires->jquery();

$mform       =      new  upload_extensions_mform();

//was the form cancelled?
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot."/user/profile.php?id=".$USER->id);
}


//was the form submitted?
if($mform->is_submitted() && $mform->get_data() && $mform->is_validated()) {
    $uploadid     =   $mform->process_data($mform->get_data(),$mform->get_file_content('userfile'));
    redirect($CFG->wwwroot."/local/module_extensions_upload/actions/view_upload_history.php");
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadextensions','local_module_extensions_upload'));

echo $mform->display();

echo $OUTPUT->footer();