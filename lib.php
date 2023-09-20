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


require_once($CFG->libdir.'/accesslib.php');
require_once($CFG->libdir.'/navigationlib.php');
//require_once( __DIR__ . '/locallib.php');


/**
 * @param \core_user\output\myprofile\tree $tree
 * @param $user
 * @param $iscurrentuser
 * @param $course
 * @return bool
 */
function local_module_extensions_upload_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course){

    if(!$iscurrentuser){
        return false;
    }

    $context = context_system::instance();
    if(!has_capability('local/module_extensions_upload:view', $context)){
        return false;
    }


    //add category for this module
    $title = get_string('userprofilecategory', 'local_module_extensions_upload');

    if (!array_key_exists('courseworkmanagement', $tree->__get('categories'))) {
        $category = new core_user\output\myprofile\category('courseworkmanagement', $title);
        $tree->add_category($category);
    }

    //add links to category
    $url = new moodle_url('/local/module_extensions_upload/actions/upload_extensions.php');
    $linktext = get_string('pluginname', 'local_module_extensions_upload');
    $node = new core_user\output\myprofile\node('courseworkmanagement', 'cwextensionupload', $linktext, null, $url);
    $tree->add_node($node);

    return true;

}

/**
 * Serves files for pluginfile.php
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @return bool
 */
function local_module_extensions_upload_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    if ($filearea === 'resultsfile') {

        $fullpath = "/{$context->id}/local_module_extensions_upload/resultsfile/".
            "0/{$args[1]}";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, true);
        return true;
    }

    return false;
}





