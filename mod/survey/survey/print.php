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
 * print a printview of survey-items
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/survey/print.php', array('id'=>$id));

if (! $cm = get_coursemodule_from_id('survey', $id)) {
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

require_capability('mod/survey:view', $context);
$PAGE->set_pagelayout('embedded');

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$survey_url = new moodle_url('/mod/survey/index.php', array('id'=>$course->id));
$PAGE->navbar->add($strsurveys, $survey_url);
$PAGE->navbar->add(format_string($survey->name));

$PAGE->set_title(format_string($survey->name));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
echo $OUTPUT->heading(format_text($survey->name));

$surveyitems = $DB->get_records('survey_item', array('survey'=>$survey->id), 'position');
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo $OUTPUT->continue_button('view.php?id='.$id);
if (is_array($surveyitems)) {
    $itemnr = 0;
    $align = right_to_left() ? 'right' : 'left';

    echo $OUTPUT->box_start('survey_items printview');
    //check, if there exists required-elements
    $params = array('survey'=>$survey->id, 'required'=>1);
    $countreq = $DB->count_records('survey_item', $params);
    if ($countreq > 0) {
        echo '<span class="survey_required_mark">(*)';
        echo get_string('items_are_required', 'survey');
        echo '</span>';
    }
    //print the inserted items
    $itempos = 0;
    foreach ($surveyitems as $surveyitem) {
        echo $OUTPUT->box_start('survey_item_box_'.$align);
        $itempos++;
        //Items without value only are labels
        if ($surveyitem->hasvalue == 1 AND $survey->autonumbering) {
            $itemnr++;
                echo $OUTPUT->box_start('survey_item_number_'.$align);
                echo $itemnr;
                echo $OUTPUT->box_end();
        }
        echo $OUTPUT->box_start('box generalbox boxalign_'.$align);
        if ($surveyitem->typ != 'pagebreak') {
            survey_print_item_complete($surveyitem, false, false);
        } else {
            echo $OUTPUT->box_start('survey_pagebreak');
            echo '<hr class="survey_pagebreak" />';
            echo $OUTPUT->box_end();
        }
        echo $OUTPUT->box_end();
        echo $OUTPUT->box_end();
    }
    echo $OUTPUT->box_end();
} else {
    echo $OUTPUT->box(get_string('no_items_available_yet', 'survey'),
                    'generalbox boxaligncenter boxwidthwide');
}
echo $OUTPUT->continue_button('view.php?id='.$id);
echo $OUTPUT->box_end();
/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();

