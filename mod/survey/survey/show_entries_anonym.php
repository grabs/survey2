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
 * print the single-values of anonymous completeds
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/tablelib.php');

$id = required_param('id', PARAM_INT);
$showcompleted = optional_param('showcompleted', false, PARAM_INT);
$do_show = optional_param('do_show', false, PARAM_ALPHA);
$perpage = optional_param('perpage', SURVEY_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$showall = optional_param('showall', false, PARAM_INT);  // should we show all users

$current_tab = $do_show;

$url = new moodle_url('/mod/survey/show_entries_anonym.php', array('id'=>$id));
// if ($userid !== '') {
    // $url->param('userid', $userid);
// }
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

require_login($course->id, true, $cm);

require_capability('mod/survey:viewreports', $context);

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
require('tabs.php');

echo $OUTPUT->heading(format_text($survey->name));

//print the list with anonymous completeds
if (!$showcompleted) {

    //get the completeds
    // if a new anonymous record has not been assigned a random response number
    $params = array('survey'=>$survey->id,
                    'random_response'=>0,
                    'anonymous_response'=>SURVEY_ANONYMOUS_YES);

    if ($surveycompleteds = $DB->get_records('survey_completed', $params, 'random_response')) {
        //then get all of the anonymous records and go through them
        $params = array('survey'=>$survey->id, 'anonymous_response'=>SURVEY_ANONYMOUS_YES);
        $surveycompleteds = $DB->get_records('survey_completed', $params, 'id'); //arb
        shuffle($surveycompleteds);
        $num = 1;
        foreach ($surveycompleteds as $compl) {
            $compl->random_response = $num;
            $DB->update_record('survey_completed', $compl);
            $num++;
        }
    }

    $params = array('survey'=>$survey->id, 'anonymous_response'=>SURVEY_ANONYMOUS_YES);
    $surveycompletedscount = $DB->count_records('survey_completed', $params);

    // preparing the table for output
    $baseurl = new moodle_url('/mod/survey/show_entries_anonym.php');
    $baseurl->params(array('id'=>$id, 'do_show'=>$do_show, 'showall'=>$showall));

    $tablecolumns = array('response', 'showresponse');
    $tableheaders = array('', '');

    if (has_capability('mod/survey:deletesubmissions', $context)) {
        $tablecolumns[] = 'deleteentry';
        $tableheaders[] = '';
    }

    $table = new flexible_table('survey-showentryanonym-list-'.$course->id);

    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($baseurl);

    $table->sortable(false);
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'showentryanonymtable');
    $table->set_attribute('class', 'generaltable generalbox');
    $table->set_control_variables(array(
                TABLE_VAR_SORT    => 'ssort',
                TABLE_VAR_IFIRST  => 'sifirst',
                TABLE_VAR_ILAST   => 'silast',
                TABLE_VAR_PAGE    => 'spage'
                ));
    $table->setup();

    $matchcount = $surveycompletedscount;
    $table->initialbars(true);

    if ($showall) {
        $startpage = false;
        $pagecount = false;
    } else {
        $table->pagesize($perpage, $matchcount);
        $startpage = $table->get_page_start();
        $pagecount = $table->get_page_size();
    }


    $surveycompleteds = $DB->get_records('survey_completed',
                                        array('survey'=>$survey->id, 'anonymous_response'=>SURVEY_ANONYMOUS_YES),
                                        'random_response',
                                        'id,random_response',
                                        $startpage,
                                        $pagecount);

    if (is_array($surveycompleteds)) {
        echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
        echo $OUTPUT->heading(get_string('anonymous_entries', 'survey'), 3);
        foreach ($surveycompleteds as $compl) {
            $data = array();

            $data[] = get_string('response_nr', 'survey').': '. $compl->random_response;

            //link to the entry
            $showentryurl = new moodle_url($baseurl, array('showcompleted'=>$compl->id));
            $showentrylink = '<a href="'.$showentryurl->out().'">'.get_string('show_entry', 'survey').'</a>';
            $data[] = $showentrylink;

            //link to delete the entry
            if (has_capability('mod/survey:deletesubmissions', $context)) {
                $delet_url_params = array('id'=>$cm->id,
                                    'completedid'=>$compl->id,
                                    'do_show'=>'',
                                    'return'=>'entriesanonym');

                $deleteentryurl = new moodle_url($CFG->wwwroot.'/mod/survey/delete_completed.php', $delet_url_params);
                $deleteentrylink = '<a href="'.$deleteentryurl->out().'">'.get_string('delete_entry', 'survey').'</a>';
                $data[] = $deleteentrylink;
            }
            $table->add_data($data);
        }
        $table->print_html();

        $allurl = new moodle_url($baseurl);

        if ($showall) {
            $allurl->param('showall', 0);
            $str_showperpage = get_string('showperpage', '', SURVEY_DEFAULT_PAGE_COUNT);
            echo $OUTPUT->container(html_writer::link($allurl, $str_showperpage), array(), 'showall');
        } else if ($matchcount > 0 && $perpage < $matchcount) {
            $allurl->param('showall', 1);
            echo $OUTPUT->container(html_writer::link($allurl, get_string('showall', '', $matchcount)), array(), 'showall');
        }
        echo $OUTPUT->box_end();
    }
}
//print the items
if ($showcompleted) {
    $continueurl = new moodle_url('/mod/survey/show_entries_anonym.php',
                                array('id'=>$id, 'do_show'=>''));

    echo $OUTPUT->continue_button($continueurl);

    //get the surveyitems
    $params = array('survey'=>$survey->id);
    $surveyitems = $DB->get_records('survey_item', $params, 'position');
    $surveycompleted = $DB->get_record('survey_completed', array('id'=>$showcompleted));
    if (is_array($surveyitems)) {
        $align = right_to_left() ? 'right' : 'left';

        if ($surveycompleted) {
            echo $OUTPUT->box_start('survey_info');
            echo get_string('chosen_survey_response', 'survey');
            echo $OUTPUT->box_end();
            echo $OUTPUT->box_start('survey_info');
            echo get_string('response_nr', 'survey').': ';
            echo $surveycompleted->random_response.' ('.get_string('anonymous', 'survey').')';
            echo $OUTPUT->box_end();
        } else {
            echo $OUTPUT->box_start('survey_info');
            echo get_string('not_completed_yet', 'survey');
            echo $OUTPUT->box_end();
        }

        echo $OUTPUT->box_start('survey_items');
        $itemnr = 0;
        foreach ($surveyitems as $surveyitem) {
            //get the values
            $params = array('completed'=>$surveycompleted->id, 'item'=>$surveyitem->id);
            $value = $DB->get_record('survey_value', $params);
            echo $OUTPUT->box_start('survey_item_box_'.$align);
            if ($surveyitem->hasvalue == 1 AND $survey->autonumbering) {
                $itemnr++;
                echo $OUTPUT->box_start('survey_item_number_'.$align);
                echo $itemnr;
                echo $OUTPUT->box_end();
            }
            if ($surveyitem->typ != 'pagebreak') {
                echo $OUTPUT->box_start('box generalbox boxalign_'.$align);
                $itemvalue = isset($value->value) ? $value->value : false;
                survey_print_item_show_value($surveyitem, $itemvalue);
                echo $OUTPUT->box_end();
            }
            echo $OUTPUT->box_end();
        }
        echo $OUTPUT->box_end();
    }
}
/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();

