<?php
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
 * prints the form to edit a dedicated item
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");

survey_init_survey_session();

$cmid = required_param('cmid', PARAM_INT);
$typ = optional_param('typ', false, PARAM_ALPHA);
$id = optional_param('id', false, PARAM_INT);
$action = optional_param('action', false, PARAM_ALPHA);

$editurl = new moodle_url('/mod/survey/edit.php', array('id'=>$cmid));

if (!$typ) {
    redirect($editurl->out(false));
}

$url = new moodle_url('/mod/survey/edit_item.php', array('cmid'=>$cmid));
if ($typ !== false) {
    $url->param('typ', $typ);
}
if ($id !== false) {
    $url->param('id', $id);
}
$PAGE->set_url($url);

// set up some general variables
$usehtmleditor = can_use_html_editor();


if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

if (! $cm = get_coursemodule_from_id('survey', $cmid)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $survey = $DB->get_record("survey", array("id"=>$cm->instance))) {
    print_error('invalidcoursemodule');
}

if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
}

require_login($course->id, true, $cm);

require_capability('mod/survey:edititems', $context);

//if the typ is pagebreak so the item will be saved directly
if ($typ == 'pagebreak') {
    survey_create_pagebreak($survey->id);
    redirect($editurl->out(false));
    exit;
}

//get the existing item or create it
// $formdata->itemid = isset($formdata->itemid) ? $formdata->itemid : NULL;
if ($id and $item = $DB->get_record('survey_item', array('id'=>$id))) {
    $typ = $item->typ;
} else {
    $item = new stdClass();
    $item->id = null;
    $item->position = -1;
    if (!$typ) {
        print_error('typemissing', 'survey', $editurl->out(false));
    }
    $item->typ = $typ;
    $item->options = '';
}

require_once($CFG->dirroot.'/mod/survey/item/'.$typ.'/lib.php');

$itemobj = survey_get_item_class($typ);

$itemobj->build_editform($item, $survey, $cm);

if ($itemobj->is_cancelled()) {
    redirect($editurl->out(false));
    exit;
}
if ($itemobj->get_data()) {
    if ($item = $itemobj->save_item()) {
        survey_move_item($item, $item->position);
        redirect($editurl->out(false));
    }
}

////////////////////////////////////////////////////////////////////////////////////
/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

if ($item->id) {
    $PAGE->navbar->add(get_string('edit_item', 'survey'));
} else {
    $PAGE->navbar->add(get_string('add_item', 'survey'));
}
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();
/// print the tabs
require('tabs.php');
/// Print the main part of the page
echo $OUTPUT->heading(format_text($survey->name));
//print errormsg
if (isset($error)) {
    echo $error;
}
$itemobj->show_editform();

if ($typ!='label') {
    $PAGE->requires->js('/mod/survey/survey.js');
    $PAGE->requires->js_function_call('set_item_focus', Array('id_itemname'));
}

/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();
