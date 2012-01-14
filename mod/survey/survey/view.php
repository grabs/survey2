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
 * the first page to view the survey
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */
require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);

$current_tab = 'view';

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

$survey_complete_cap = false;

if (has_capability('mod/survey:complete', $context)) {
    $survey_complete_cap = true;
}

if (isset($CFG->survey_allowfullanonymous)
            AND $CFG->survey_allowfullanonymous
            AND $course->id == SITEID
            AND (!$courseid OR $courseid == SITEID)
            AND $survey->anonymous == SURVEY_ANONYMOUS_YES ) {
    $survey_complete_cap = true;
}

//check whether the survey is located and! started from the mainsite
if ($course->id == SITEID AND !$courseid) {
    $courseid = SITEID;
}

//check whether the survey is mapped to the given courseid
if ($course->id == SITEID AND !has_capability('mod/survey:edititems', $context)) {
    if ($DB->get_records('survey_sitecourse_map', array('surveyid'=>$survey->id))) {
        $params = array('surveyid'=>$survey->id, 'courseid'=>$courseid);
        if (!$DB->get_record('survey_sitecourse_map', $params)) {
            print_error('invalidcoursemodule');
        }
    }
}

if ($survey->anonymous != SURVEY_ANONYMOUS_YES) {
    if ($course->id == SITEID) {
        require_login($course->id, true);
    } else {
        require_login($course->id, true, $cm);
    }
} else {
    if ($course->id == SITEID) {
        require_course_login($course, true);
    } else {
        require_course_login($course, true, $cm);
    }
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

if ($survey->anonymous == SURVEY_ANONYMOUS_NO) {
    add_to_log($course->id, 'survey', 'view', 'view.php?id='.$cm->id, $survey->id, $cm->id);
}

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

if ($course->id == SITEID) {
    $PAGE->set_context($context);
    $PAGE->set_cm($cm, $course); // set's up global $COURSE
    $PAGE->set_pagelayout('incourse');
}
$PAGE->set_url('/mod/survey/view.php', array('id'=>$cm->id, 'do_show'=>'view'));
$PAGE->set_title(format_string($survey->name));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

//ishidden check.
//survey in courses
$cap_viewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $context);
if ((empty($cm->visible) and !$cap_viewhiddenactivities) AND $course->id != SITEID) {
    notice(get_string("activityiscurrentlyhidden"));
}

//ishidden check.
//survey on mainsite
if ((empty($cm->visible) and !$cap_viewhiddenactivities) AND $courseid == SITEID) {
    notice(get_string("activityiscurrentlyhidden"));
}

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

/// print the tabs
require('tabs.php');

$previewimg = $OUTPUT->pix_icon('t/preview', get_string('preview'));
$previewlnk = '<a href="'.$CFG->wwwroot.'/mod/survey/print.php?id='.$id.'">'.$previewimg.'</a>';

echo $OUTPUT->heading(format_text($survey->name.' '.$previewlnk));

//show some infos to the survey
if (has_capability('mod/survey:edititems', $context)) {
    //get the groupid
    $groupselect = groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/survey/view.php?id='.$cm->id, true);
    $mygroupid = groups_get_activity_group($cm);

    echo $OUTPUT->box_start('boxaligncenter boxwidthwide');
    echo $groupselect.'<div class="clearer">&nbsp;</div>';
    $completedscount = survey_get_completeds_group_count($survey, $mygroupid);
    echo $OUTPUT->box_start('survey_info');
    echo '<span class="survey_info">';
    echo get_string('completed_surveys', 'survey').': ';
    echo '</span>';
    echo '<span class="survey_info_value">';
    echo $completedscount;
    echo '</span>';
    echo $OUTPUT->box_end();

    $params = array('survey'=>$survey->id, 'hasvalue'=>1);
    $itemscount = $DB->count_records('survey_item', $params);
    echo $OUTPUT->box_start('survey_info');
    echo '<span class="survey_info">';
    echo get_string('questions', 'survey').': ';
    echo '</span>';
    echo '<span class="survey_info_value">';
    echo $itemscount;
    echo '</span>';
    echo $OUTPUT->box_end();

    if ($survey->timeopen) {
        echo $OUTPUT->box_start('survey_info');
        echo '<span class="survey_info">';
        echo get_string('surveyopen', 'survey').': ';
        echo '</span>';
        echo '<span class="survey_info_value">';
        echo userdate($survey->timeopen);
        echo '</span>';
        echo $OUTPUT->box_end();
    }
    if ($survey->timeclose) {
        echo $OUTPUT->box_start('survey_info');
        echo '<span class="survey_info">';
        echo get_string('timeclose', 'survey').': ';
        echo '</span>';
        echo '<span class="survey_info_value">';
        echo userdate($survey->timeclose);
        echo '</span>';
        echo $OUTPUT->box_end();
    }
    echo $OUTPUT->box_end();
}

if (has_capability('mod/survey:edititems', $context)) {
    echo $OUTPUT->heading(get_string('description', 'survey'), 4);
}
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
$options = (object)array('noclean'=>true);
echo format_module_intro('survey', $survey, $cm->id);
echo $OUTPUT->box_end();

if (has_capability('mod/survey:edititems', $context)) {
    echo $OUTPUT->heading(get_string("page_after_submit", "survey"), 4);
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    echo format_text($survey->page_after_submit,
                     $survey->page_after_submitformat,
                     array('overflowdiv'=>true));

    echo $OUTPUT->box_end();
}

if ( (intval($survey->publish_stats) == 1) AND
                ( has_capability('mod/survey:viewanalysepage', $context)) AND
                !( has_capability('mod/survey:viewreports', $context)) ) {

    $params = array('userid'=>$USER->id, 'survey'=>$survey->id);
    if ($multiple_count = $DB->count_records('survey_tracking', $params)) {
        $url_params = array('id'=>$id, 'courseid'=>$courseid);
        $analysisurl = new moodle_url('/mod/survey/analysis.php', $url_params);
        echo '<div class="mdl-align"><a href="'.$analysisurl->out().'">';
        echo get_string('completed_surveys', 'survey').'</a>';
        echo '</div>';
    }
}

//####### mapcourse-start
if (has_capability('mod/survey:mapcourse', $context)) {
    if ($survey->course == SITEID) {
        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
        echo '<div class="mdl-align">';
        echo '<form action="mapcourse.php" method="get">';
        echo '<fieldset>';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        echo '<button type="submit">'.get_string('mapcourses', 'survey').'</button>';
        echo $OUTPUT->help_icon('mapcourse', 'survey');
        echo '</fieldset>';
        echo '</form>';
        echo '<br />';
        echo '</div>';
        echo $OUTPUT->box_end();
    }
}
//####### mapcourse-end

//####### completed-start
if ($survey_complete_cap) {
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    //check, whether the survey is open (timeopen, timeclose)
    $checktime = time();
    if (($survey->timeopen > $checktime) OR
            ($survey->timeclose < $checktime AND $survey->timeclose > 0)) {

        echo '<h2><font color="red">'.get_string('survey_is_not_open', 'survey').'</font></h2>';
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit;
    }

    //check multiple Submit
    $survey_can_submit = true;
    if ($survey->multiple_submit == 0 ) {
        if (survey_is_already_submitted($survey->id, $courseid)) {
            $survey_can_submit = false;
        }
    }
    if ($survey_can_submit) {
        //if the user is not known so we cannot save the values temporarly
        if (!isloggedin() or isguestuser()) {
            $completefile = 'complete_guest.php';
            $guestid = sesskey();
        } else {
            $completefile = 'complete.php';
            $guestid = false;
        }
        $url_params = array('id'=>$id, 'courseid'=>$courseid, 'gopage'=>0);
        $completeurl = new moodle_url('/mod/survey/'.$completefile, $url_params);

        $surveycompletedtmp = survey_get_current_completed($survey->id, true, $courseid, $guestid);
        if ($surveycompletedtmp) {
            if ($startpage = survey_get_page_to_continue($survey->id, $courseid, $guestid)) {
                $completeurl->param('gopage', $startpage);
            }
            echo '<a href="'.$completeurl->out().'">'.get_string('continue_the_form', 'survey').'</a>';
        } else {
            echo '<a href="'.$completeurl->out().'">'.get_string('complete_the_form', 'survey').'</a>';
        }
    } else {
        echo '<h2><font color="red">';
        echo get_string('this_survey_is_already_submitted', 'survey');
        echo '</font></h2>';
        if ($courseid) {
            echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$courseid);
        } else {
            echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }
    }
    echo $OUTPUT->box_end();
}
//####### completed-end

/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();

