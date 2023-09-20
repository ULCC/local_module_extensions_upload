<?php
//require_once($CFG->libdir.'/coursecatlib.php');
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package    local_module_extensions_upload
 * @copyright  2020 University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_module_extensions_upload;


/**
 * Class upload_history
 * Handle the upload history of module_extensions_upload
 */

class upload_history {

    public function display_upload_history(){
        global $CFG, $OUTPUT;


        $filelist = "";

        $files = $this->get_results_files();

        if($files) {
            $filelist .= "<div>";
            foreach ($files as $file) {

                $url = "{$CFG->wwwroot}/pluginfile.php/{$file->get_contextid()}" .
                    "/local_module_extensions_upload/resultsfile";
                $filename = $file->get_filename();

                $fileurl = $url . $file->get_filepath() . $file->get_itemid() . '/' . rawurlencode($filename);
                $image = $OUTPUT->pix_icon(file_file_icon($file),
                    $filename,'moodle', array('class' => 'icon'));

                $filelist .= \html_writer::link($fileurl, $image . $filename) .'<br>';


            }
            $filelist .= "<br></div>";
        }



        return $filelist;

    }

    public function get_results_files(){
        global $CFG;

        $fs = get_file_storage();
        $upload_results_files = $fs->get_area_files(SYSCONTEXTID,
            'local_module_extensions_upload',
            'resultsfile', 0,'id DESC',false);

        return $upload_results_files;

    }

}