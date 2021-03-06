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
 * print the single entries
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/tablelib.php');

////////////////////////////////////////////////////////
//get the params
////////////////////////////////////////////////////////
$id = required_param('id', PARAM_INT);
$subject = optional_param('subject', '', PARAM_CLEANHTML);
$message = optional_param('message', '', PARAM_CLEANHTML);
$format = optional_param('format', FORMAT_MOODLE, PARAM_INT);
$messageuser = optional_param('messageuser', false, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$perpage = optional_param('perpage', SURVEY_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$showall = optional_param('showall', false, PARAM_INT);  // should we show all users
// $SESSION->survey->current_tab = $do_show;
$current_tab = 'nonrespondents';

////////////////////////////////////////////////////////
//get the objects
////////////////////////////////////////////////////////
if (! $cm = get_coursemodule_from_id('survey', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf');
}

if (! $survey = $DB->get_record("survey", array("id"=>$cm->instance))) {
    print_error('invalidcoursemodule');
}

//this page only can be shown on nonanonymous surveys in courses
//we should never reach this page
if ($survey->anonymous != SURVEY_ANONYMOUS_NO OR $survey->course == SITEID) {
    print_error('error');
}

$url = new moodle_url('/mod/survey/show_nonrespondents.php', array('id'=>$cm->id));

$PAGE->set_url($url);

if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
}

//we need the coursecontext to allow sending of mass mails
if (!$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id)) {
        print_error('badcontext');
}

require_login($course->id, true, $cm);

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

require_capability('mod/survey:viewreports', $context);

if ($action == 'sendmessage' AND has_capability('moodle/course:bulkmessaging', $coursecontext)) {
    $good = 1;
    if (is_array($messageuser)) {
        foreach ($messageuser as $userid) {
            $senduser = $DB->get_record('user', array('id'=>$userid));
            $eventdata = new stdClass();
            $eventdata->name             = 'message';
            $eventdata->component        = 'mod_survey';
            $eventdata->userfrom         = $USER;
            $eventdata->userto           = $senduser;
            $eventdata->subject          = $subject;
            $eventdata->fullmessage      = $message;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml  = '';
            $eventdata->smallmessage     = '';
            $good = $good && message_send($eventdata);
        }
        if (!empty($good)) {
            $msg = $OUTPUT->heading(get_string('messagedselectedusers'));
        } else {
            $msg = $OUTPUT->heading(get_string('messagedselectedusersfailed'));
        }
        redirect($url, $msg, 4);
        exit;
    }
}

////////////////////////////////////////////////////////
//get the responses of given user
////////////////////////////////////////////////////////

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->navbar->add(get_string('show_nonrespondents', 'survey'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

require('tabs.php');

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////
/// Print the users with no responses
////////////////////////////////////////////////////////
//get the effective groupmode of this course and module
if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode =  $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$groupselect = groups_print_activity_menu($cm, $url->out(), true);
$mygroupid = groups_get_activity_group($cm);

// preparing the table for output
$baseurl = new moodle_url('/mod/survey/show_nonrespondents.php');
$baseurl->params(array('id'=>$id, 'showall'=>$showall));

$tablecolumns = array('userpic', 'fullname', 'status');
$tableheaders = array(get_string('userpic'), get_string('fullnameuser'), get_string('status'));

if (has_capability('moodle/course:bulkmessaging', $coursecontext)) {
    $tablecolumns[] = 'select';
    $tableheaders[] = get_string('select');
}

$table = new flexible_table('survey-shownonrespondents-'.$course->id);

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl);

$table->sortable(true, 'lastname', SORT_DESC);
$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'showentrytable');
$table->set_attribute('class', 'generaltable generalbox');
$table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));

$table->no_sorting('select');
$table->no_sorting('status');

$table->setup();

if ($table->get_sql_sort()) {
    $sort = $table->get_sql_sort();
} else {
    $sort = '';
}

//get students in conjunction with groupmode
if ($groupmode > 0) {
    if ($mygroupid > 0) {
        $usedgroupid = $mygroupid;
    } else {
        $usedgroupid = false;
    }
} else {
    $usedgroupid = false;
}

$matchcount = survey_count_incomplete_users($cm, $usedgroupid);
$table->initialbars(false);

if ($showall) {
    $startpage = false;
    $pagecount = false;
} else {
    $table->pagesize($perpage, $matchcount);
    $startpage = $table->get_page_start();
    $pagecount = $table->get_page_size();
}

$students = survey_get_incomplete_users($cm, $usedgroupid, $sort, $startpage, $pagecount);
//####### viewreports-start
//print the list of students
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo isset($groupselect) ? $groupselect : '';
echo '<div class="clearer"></div>';
echo $OUTPUT->box_start('mdl-align');

if (!$students) {
    echo $OUTPUT->notification(get_string('noexistingparticipants', 'enrol'));
} else {
    echo print_string('non_respondents_students', 'survey');
    echo ' ('.$matchcount.')<hr />';

    if (has_capability('moodle/course:bulkmessaging', $coursecontext)) {
        echo '<form class="mform" action="show_nonrespondents.php" method="post" id="survey_sendmessageform">';
    }
    foreach ($students as $student) {
        $user = $DB->get_record('user', array('id'=>$student));
        //userpicture and link to the profilepage
        $profile_url = $CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id;
        $profilelink = '<strong><a href="'.$profile_url.'">'.fullname($user).'</a></strong>';
        $data = array ($OUTPUT->user_picture($user, array('courseid'=>$course->id)), $profilelink);

        if ($DB->record_exists('survey_completedtmp', array('userid'=>$user->id))) {
            $data[] = get_string('started', 'survey');
        } else {
            $data[] = get_string('not_started', 'survey');
        }

        //selections to bulk messaging
        if (has_capability('moodle/course:bulkmessaging', $coursecontext)) {
            $data[] = '<input type="checkbox" class="usercheckbox" name="messageuser[]" value="'.$user->id.'" />';
        }
        $table->add_data($data);
    }
    $table->print_html();

    $allurl = new moodle_url($baseurl);

    if ($showall) {
        $allurl->param('showall', 0);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showperpage', '', SURVEY_DEFAULT_PAGE_COUNT)),
                                    array(), 'showall');

    } else if ($matchcount > 0 && $perpage < $matchcount) {
        $allurl->param('showall', 1);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showall', '', $matchcount)), array(), 'showall');
    }
    if (has_capability('moodle/course:bulkmessaging', $coursecontext)) {
        $usehtmleditor = can_use_html_editor();
        echo '<div class="buttons"><br />';
        echo '<input type="button" id="checkall" value="'.get_string('selectall').'" /> ';
        echo '<input type="button" id="checknone" value="'.get_string('deselectall').'" /> ';
        echo '</div>';
        echo '<fieldset class="clearfix">';
        echo '<legend class="ftoggler">'.get_string('send_message', 'survey').'</legend>';
        echo '<div>';
        echo '<label for="survey_subject">'.get_string('subject', 'survey').'&nbsp;</label>';
        echo '<input type="text" id="survey_subject" size="50" maxlength="255" name="subject" value="'.$subject.'" />';
        echo '</div>';
        print_textarea($usehtmleditor, 15, 25, 30, 10, "message", $message);
        if ($usehtmleditor) {
            print_string('formathtml');
            echo '<input type="hidden" name="format" value="'.FORMAT_HTML.'" />';
        } else {
            choose_from_menu(format_text_menu(), "format", $format, "");
        }
        echo '<br /><div class="buttons">';
        echo '<input type="submit" name="send_message" value="'.get_string('send', 'survey').'" />';
        echo '</div>';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<input type="hidden" name="action" value="sendmessage" />';
        echo '<input type="hidden" name="id" value="'.$id.'" />';
        echo '</fieldset>';
        echo '</form>';
        //include the needed js
        $module = array('name'=>'mod_survey', 'fullpath'=>'/mod/survey/survey.js');
        $PAGE->requires->js_init_call('M.mod_survey.init_sendmessage', null, false, $module);
    }
}
echo $OUTPUT->box_end();
echo $OUTPUT->box_end();

/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();

