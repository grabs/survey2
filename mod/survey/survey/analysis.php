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
 * shows an analysed view of survey
 *
 * @copyright Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");

$current_tab = 'analysis';

$id = required_param('id', PARAM_INT);  //the POST dominated the GET
$courseid = optional_param('courseid', false, PARAM_INT);

$url = new moodle_url('/mod/survey/analysis.php', array('id'=>$id));
if ($courseid !== false) {
    $url->param('courseid', $courseid);
}
$PAGE->set_url($url);

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

if ($course->id == SITEID) {
    require_login($course->id, true);
} else {
    require_login($course->id, true, $cm);
}

//check whether the given courseid exists
if ($courseid AND $courseid != SITEID) {
    if ($course2 = $DB->get_record('course', array('id'=>$courseid))) {
        require_course_login($course2); //this overwrites the object $course :-(
        $course = $DB->get_record("course", array("id"=>$cm->course)); // the workaround
    } else {
        print_error('invalidcourseid');
    }
}

if ( !( ((intval($survey->publish_stats) == 1) AND
        has_capability('mod/survey:viewanalysepage', $context)) OR
        has_capability('mod/survey:viewreports', $context))) {
    print_error('error');
}

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->navbar->add(get_string('analysis', 'survey'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

/// print the tabs
require('tabs.php');


//print analysed items
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

//get the groupid
$myurl = $CFG->wwwroot.'/mod/survey/analysis.php?id='.$cm->id.'&do_show=analysis';
$groupselect = groups_print_activity_menu($cm, $myurl, true);
$mygroupid = groups_get_activity_group($cm);

if ( has_capability('mod/survey:viewreports', $context) ) {

    echo isset($groupselect) ? $groupselect : '';
    echo '<div class="clearer"></div>';

    //button "export to excel"
    echo $OUTPUT->container_start('form-buttons');
    $aurl = new moodle_url('analysis_to_excel.php', array('sesskey'=>sesskey(), 'id'=>$id));
    echo $OUTPUT->single_button($aurl, get_string('export_to_excel', 'survey'));
    echo $OUTPUT->container_end();
}

//get completed surveys
$completedscount = survey_get_completeds_group_count($survey, $mygroupid);

//show the group, if available
if ($mygroupid and $group = $DB->get_record('groups', array('id'=>$mygroupid))) {
    echo '<b>'.get_string('group').': '.$group->name. '</b><br />';
}
//show the count
echo '<b>'.get_string('completed_surveys', 'survey').': '.$completedscount. '</b><br />';

// get the items of the survey
$items = $DB->get_records('survey_item',
                          array('survey'=>$survey->id, 'hasvalue'=>1),
                          'position');
//show the count
if (is_array($items)) {
    echo '<b>'.get_string('questions', 'survey').': ' .count($items). ' </b><hr />';
} else {
    $items=array();
}
$check_anonymously = true;
if ($mygroupid > 0 AND $survey->anonymous == SURVEY_ANONYMOUS_YES) {
    if ($completedscount < SURVEY_MIN_ANONYMOUS_COUNT_IN_GROUP) {
        $check_anonymously = false;
    }
}

echo '<div><table width="80%" cellpadding="10"><tr><td>';
if ($check_anonymously) {
    $itemnr = 0;
    //print the items in an analysed form
    foreach ($items as $item) {
        if ($item->hasvalue == 0) {
            continue;
        }
        echo '<table width="100%" class="generalbox">';

        //get the class of item-typ
        $itemobj = survey_get_item_class($item->typ);

        $itemnr++;
        if ($survey->autonumbering) {
            $printnr = $itemnr.'.';
        } else {
            $printnr = '';
        }
        $itemobj->print_analysed($item, $printnr, $mygroupid);
        echo '</table>';
    }
} else {
    echo $OUTPUT->heading_with_help(get_string('insufficient_responses_for_this_group', 'survey'),
                                    'insufficient_responses',
                                    'survey');
}
echo '</td></tr></table></div>';
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

