<?php


global $CFG;
require_once("$CFG->libdir/formslib.php");


class upload_extensions_mform extends moodleform {

    //Add elements to form
    function definition() {
        global $CFG;
        $file_size_limit = get_max_upload_file_size();

        $mform = $this->_form; // Don't forget the underscore!

        // display link to the upload history
        $link = '<a href="' .$CFG->wwwroot.'/local/module_extensions_upload/actions/view_upload_history.php">' .
            get_string('uploadhistory', 'local_module_extensions_upload') . '</a>';

        $mform->addElement('static', 'viewhistorylink',$link);

        // filepicker
        $mform->addElement('filepicker', 'userfile', get_string('selectfile', 'local_module_extensions_upload'), null, array('maxbytes' => $file_size_limit, 'accepted_types'=>'*.txt,*.csv'));

        $mform->addRule('userfile', 'Please select a file!', 'required');

        // delimiter
        $options = array(
            ',' => ',',
            '|' => '|'
        );

        $mform->addElement('select', 'delimiter', get_string('delimiter', 'local_module_extensions_upload'), $options);

        // Module types such as Coursework or Quiz.
        $options = array(
            'coursework_mitigations' => 'Coursework Mitigations',
            'coursework_overrides' => 'Coursework Time Limit Override',
            'quiz'       => 'Quiz'
        );
        $mform->addElement('select', 'moduletype', get_string('moduletype', 'local_module_extensions_upload'), $options);

        $mform->addElement("html",' <div class="row">
                                        <div class="col-md-3">&nbsp;</div>
                                        <div class="col-md-9 moduletypeformat">
                                            <p class="forcourseworkmitigations">courseid, studentid, assessmentcode, extended_deadline, pre_defined_reason, extra_information_text</p>
                                            <p class="forcourseworkoverrides">courseid, studentid, assessmentcode, timelimit</p>
                                            <p class="forquiz">courseid, studentid, assessmentcode, extended_deadline</p>
                                        </div>
                                    </div>');

        $button_array[] =   $this->_form->createElement('submit', 'upload', get_string('upload', 'local_module_extensions_upload'));
        $button_array[] =  $mform->createElement('cancel');
        $mform->addGroup($button_array, 'buttonar', '', array(' '), false);

        $mform->closeHeaderBefore('upload');

        $custom_html = '
            <style type="text/css">
                .forquiz { display: none; }
                .forcourseworkoverrides { display: none; }
            </style>
            <script type="text/javascript">
                $(document).ready(function(){
                    $("select#id_moduletype").on("change", function() {
                        $(".moduletypeformat p").attr("style", "display: none;");
                        if ($(this).val() == "coursework_mitigations") {
                            $(".forcourseworkmitigations").attr("style", "display: block;");
                        }
                        else if ($(this).val() == "coursework_overrides") {
                            $(".forcourseworkoverrides").attr("style", "display: block;");
                        }
                        else if ($(this).val() == "quiz") {
                            $(".forquiz").attr("style", "display: block;");
                        }
                    });
                });
            </script>';

        $mform->addElement("html", $custom_html);
    }

    function process_data($data,$filecontent) {

        global  $CFG, $DB,$USER;

        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);

        $filename = $CFG->tempdir . '/module_extensions_upload/tempfile'.time().'.csv';
        make_temp_directory('module_extensions_upload');


        // Fix mac/dos newlines
        $text = preg_replace('!\r\n?!',"\n",$filecontent);

        //save all the data in to a file
        $fp = fopen($filename, "w");

        fwrite($fp,$text);

        fclose($fp);

        $importprocessor    =   new local_module_extensions_upload\processor();

        $errors = $importprocessor->dataprocessor($filename, $data->moduletype, $data->delimiter);


        // Write results file

        // Prepare temp area.
        $tempfolder = make_temp_directory('module_extensions_upload');
        $tempfile = $tempfolder . '/' . rand();
        $date = date('d-m-Y H_i_s',time());
        $filetitle = $date.'.txt';


        // Write file.
        $handle = fopen($tempfile, 'w');
        if (!$handle) {
            throw new coding_exception('Unable to open file!');
        }

        $errs = $errors->errors;
        $data  = "Uploaded on: " .str_replace('_', ':', $date). "\n";
        $data .= "Uploaded by: " .fullname($USER). "\n";
        $data .= "Lines in the file: " .$errors->lines. "\n";
        $data .= "Extensions added: " .$errors->added. "\n";
        $data .= "Extensions updated: " . $errors->updated. "\n";
        $data .= "Errors (".count($errs)."): " .  "\n";

        foreach($errs as $key=>$err) {
            $data .= 'Line number: '.$key. ' Error: ' .$err ."\n";
        }

        fwrite($handle, $data);
        fclose($handle);

        // Add file.
        $fs = get_file_storage();
        $filerecord = array('component' => 'local_module_extensions_upload', 'filearea' => 'resultsfile',
            'contextid' => SYSCONTEXTID, 'itemid' => 0, 'filepath' => '/',
            'filename' => $filetitle);
        $fs->create_file_from_pathname($filerecord, $tempfile);

        unlink($tempfile); // Destroy temp file.

    }

    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}
