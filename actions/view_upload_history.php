<?php

require_once("../../../config.php");
use local_module_extensions_upload\upload_history;

global $DB, $CFG, $OUTPUT, $PAGE;
require_login();

$context = context_system::instance();

if(!has_capability('local/module_extensions_upload:view', $context)) {
    //return error
    throw new moodle_exception(get_string('nopermission', 'local_module_extensions_upload'));
}

$PAGE->set_url('/local/module_extensions_upload/actions/view_upload_history.php');

$PAGE->set_pagelayout('standard');

$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_title(get_string('uploadhistory','local_module_extensions_upload'));

$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('uploadextensions','local_module_extensions_upload'),
                    new moodle_url('/local/module_extensions_upload/actions/upload_extensions.php'));

$PAGE->navbar->ignore_active();

$PAGE->navbar->add(get_string('uploadhistory','local_module_extensions_upload'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('uploadhistory','local_module_extensions_upload'));

$upload_history = new upload_history();

echo $upload_history->display_upload_history();

echo $OUTPUT->footer();

