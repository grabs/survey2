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
 * shows an analysed view of a survey on the mainsite
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");

$current_tab = 'analysis';

$id = required_param('id', PARAM_INT);  //the POST dominated the GET
$coursefilter = optional_param('coursefilter', '0', PARAM_INT);
$courseitemfilter = optional_param('courseitemfilter', '0', PARAM_INT);
$courseitemfiltertyp = optional_param('courseitemfiltertyp', '0', PARAM_ALPHANUM);
$searchcourse = optional_param('searchcourse', '', PARAM_RAW);
$courseid = optional_param('courseid', false, PARAM_INT);

$url = new moodle_url('/mod/survey/analysis_course.php', array('id'=>$id));
if ($courseid !== false) {
    $url->param('courseid', $courseid);
}
if ($coursefilter !== '0') {
    $url->param('coursefilter', $coursefilter);
}
if ($courseitemfilter !== '0') {
    $url->param('courseitemfilter', $courseitemfilter);
}
if ($courseitemfiltertyp !== '0') {
    $url->param('courseitemfiltertyp', $courseitemfiltertyp);
}
if ($searchcourse !== '') {
    $url->param('searchcourse', $searchcourse);
}
$PAGE->set_url($url);

if (($searchcourse OR $courseitemfilter OR $coursefilter) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

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

if (!($survey->publish_stats OR has_capability('mod/survey:viewreports', $context))) {
    print_error('error');
}

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

/// print the tabs
require('tabs.php');

//print the analysed items
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

if (has_capability('mod/survey:viewreports', $context)) {
    //button "export to excel"
    echo $OUTPUT->container_start('mdl-align');
    $aurl = new moodle_url('analysis_to_excel.php',
                           array('sesskey' => sesskey(),
                                 'id' => $id,
                                 'coursefilter' => $coursefilter));

    echo $OUTPUT->single_button($aurl, get_string('export_to_excel', 'survey'));
    echo $OUTPUT->container_end();
}

//get the groupid
//lstgroupid is the choosen id
$mygroupid = false;
//get completed surveys
$completedscount = survey_get_completeds_group_count($survey, $mygroupid, $coursefilter);

//show the count
echo '<b>'.get_string('completed_surveys', 'survey').': '.$completedscount. '</b><br />';

// get the items of the survey
$params = array('survey' => $survey->id, 'hasvalue' => 1);
$items = $DB->get_records('survey_item', $params, 'position');
//show the count
if (is_array($items)) {
    echo '<b>'.get_string('questions', 'survey').': ' .count($items). ' </b><hr />';
    echo '<a href="analysis_course.php?id=' . $id . '&courseid='.$courseid.'">';
    echo get_string('show_all', 'survey');
    echo '</a>';
} else {
    $items=array();
}

echo '<form name="report" method="post" id="analysis-form">';
echo '<div class="mdl-align"><table width="80%" cellpadding="10">';
if ($courseitemfilter > 0) {
    $avgvalue = 'avg(value)';
    if ($DB->get_dbfamily() == 'postgres') { // TODO: this should be moved to standard sql DML function ;-)
         $avgvalue = 'avg(cast (value as integer))';
    }

    $sql = "SELECT fv.course_id, c.shortname, $avgvalue AS avgvalue
            FROM {survey_value} fv, {course} c, {survey_item} fi
            WHERE fv.course_id = c.id AND fi.id = fv.item AND fi.typ = ? AND fv.item = ?
            GROUP BY course_id, shortname
            ORDER BY avgvalue desc";

    if ($courses = $DB->get_records_sql($sql, array($courseitemfiltertyp, $courseitemfilter))) {
        $item = $DB->get_record('survey_item', array('id'=>$courseitemfilter));
        echo '<tr><th colspan="2">'.$item->name.'</th></tr>';
        echo '<tr><td><table align="left">';
        echo '<tr><th>Course</th><th>Average</th></tr>';
        $sep_dec = get_string('separator_decimal', 'survey');
        $sep_thous = get_string('separator_thousand', 'survey');

        foreach ($courses as $c) {
            $coursecontext = get_context_instance(CONTEXT_COURSE, $c->course_id);
            $shortname = format_string($c->shortname, true, array('context' => $coursecontext));

            echo '<tr>';
            echo '<td>'.$shortname.'</td>';
            echo '<td align="right">';
            echo number_format(($c->avgvalue), 2, $sep_dec, $sep_thous);
            echo '</td>';
            echo '</tr>';
        }
         echo '</table></td></tr>';
    } else {
         echo '<tr><td>'.get_string('noresults').'</td></tr>';
    }
} else {

    echo get_string('search_course', 'survey') . ': ';
    echo '<input type="text" name="searchcourse" value="'.s($searchcourse).'"/> ';
    echo '<input type="submit" value="'.get_string('search').'"/>';
    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
    echo '<input type="hidden" name="id" value="'.$id.'" />';
    echo '<input type="hidden" name="courseitemfilter" value="'.$courseitemfilter.'" />';
    echo '<input type="hidden" name="courseitemfiltertyp" value="'.$courseitemfiltertyp.'" />';
    echo '<input type="hidden" name="courseid" value="'.$courseid.'" />';
    echo html_writer::script('', $CFG->wwwroot.'/mod/survey/survey.js');
    $sql = 'select DISTINCT c.id, c.shortname from {course} c, '.
                                          '{survey_value} fv, {survey_item} fi '.
                                          'where c.id = fv.course_id and fv.item = fi.id '.
                                          'and fi.survey = ? '.
                                          'and
                                          ('.$DB->sql_like('c.shortname', '?', false).'
                                          OR '.$DB->sql_like('c.fullname', '?', false).')';
    $params = array($survey->id, "%$searchcourse%", "%$searchcourse%");

    if ($courses = $DB->get_records_sql_menu($sql, $params)) {

         echo ' ' . get_string('filter_by_course', 'survey') . ': ';

         echo html_writer::select($courses, 'coursefilter', $coursefilter,
                                  null, array('id'=>'coursefilterid'));

         $PAGE->requires->js_init_call('M.util.init_select_autosubmit',
                                        array('analysis-form', 'coursefilterid', false));
    }
    echo '<hr />';
    $itemnr = 0;
    //print the items in an analysed form
    echo '<tr><td>';
    foreach ($items as $item) {
        if ($item->hasvalue == 0) {
            continue;
        }
        echo '<table width="100%" class="generalbox">';
        //get the class from item-typ
        $itemobj = survey_get_item_class($item->typ);
        $itemnr++;
        if ($survey->autonumbering) {
            $printnr = $itemnr.'.';
        } else {
            $printnr = '';
        }
        $itemobj->print_analysed($item, $printnr, $mygroupid, $coursefilter);
        if (preg_match('/rated$/i', $item->typ)) {
            $onclick = 'onclick="setcourseitemfilter'.
                        "(".$item->id.",'".$item->typ."');".
                        ' return false;"';

            $anker = '<a href="#" '.$onclick.'>'.
                     get_string('sort_by_course', 'survey').
                     '</a>';

            echo '<tr><td colspan="2">'.$anker.'</td></tr>';
        }
        echo '</table>';
    }
    echo '</td></tr>';
}
echo '</table></div>';
echo '</form>';
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

