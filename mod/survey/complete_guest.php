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
 * prints the form so an anonymous user can fill out the survey on the mainsite
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");

survey_init_survey_session();

$id = required_param('id', PARAM_INT);
$completedid = optional_param('completedid', false, PARAM_INT);
$preservevalues  = optional_param('preservevalues', 0,  PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);
$gopage = optional_param('gopage', -1, PARAM_INT);
$lastpage = optional_param('lastpage', false, PARAM_INT);
$startitempos = optional_param('startitempos', 0, PARAM_INT);
$lastitempos = optional_param('lastitempos', 0, PARAM_INT);

$url = new moodle_url('/mod/survey/complete_guest.php', array('id'=>$id));
if ($completedid !== false) {
    $url->param('completedid', $completedid);
}
if ($preservevalues !== 0) {
    $url->param('preservevalues', $preservevalues);
}
if ($courseid !== false) {
    $url->param('courseid', $courseid);
}
if ($gopage !== -1) {
    $url->param('gopage', $gopage);
}
if ($lastpage !== false) {
    $url->param('lastpage', $lastpage);
}
if ($startitempos !== 0) {
    $url->param('startitempos', $startitempos);
}
if ($lastitempos !== 0) {
    $url->param('lastitempos', $lastitempos);
}
$PAGE->set_url($url);

$highlightrequired = false;

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

//if the use hit enter into a textfield so the form should not submit
if (isset($formdata->sesskey) AND
   !isset($formdata->savevalues) AND
   !isset($formdata->gonextpage) AND
   !isset($formdata->gopreviouspage)) {

    $gopage = $formdata->lastpage;
}
if (isset($formdata->savevalues)) {
    $savevalues = true;
} else {
    $savevalues = false;
}

if ($gopage < 0 AND !$savevalues) {
    if (isset($formdata->gonextpage)) {
        $gopage = $lastpage + 1;
        $gonextpage = true;
        $gopreviouspage = false;
    } else if (isset($formdata->gopreviouspage)) {
        $gopage = $lastpage - 1;
        $gonextpage = false;
        $gopreviouspage = true;
    } else {
        print_error('parameters_missing', 'survey');
    }
} else {
    $gonextpage = $gopreviouspage = false;
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

$survey_complete_cap = false;

if (isset($CFG->survey_allowfullanonymous)
            AND $CFG->survey_allowfullanonymous
            AND $course->id == SITEID
            AND (!$courseid OR $courseid == SITEID)
            AND $survey->anonymous == SURVEY_ANONYMOUS_YES ) {
    $survey_complete_cap = true;
}

//check whether the survey is anonymous
if (isset($CFG->survey_allowfullanonymous)
                AND $CFG->survey_allowfullanonymous
                AND $survey->anonymous == SURVEY_ANONYMOUS_YES
                AND $course->id == SITEID ) {
    $survey_complete_cap = true;
}
if ($survey->anonymous != SURVEY_ANONYMOUS_YES) {
    print_error('survey_is_not_for_anonymous', 'survey');
}

//check whether the user has a session
// there used to be a sesskey test - this could not work - sorry

//check whether the survey is located and! started from the mainsite
if ($course->id == SITEID AND !$courseid) {
    $courseid = SITEID;
}

require_course_login($course);

if ($courseid AND $courseid != SITEID) {
    $course2 = $DB->get_record('course', array('id'=>$courseid));
    require_course_login($course2); //this overwrites the object $course :-(
    $course = $DB->get_record("course", array("id"=>$cm->course)); // the workaround
}

if (!$survey_complete_cap) {
    print_error('error');
}


/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->set_cm($cm, $course); // set's up global $COURSE
$PAGE->set_pagelayout('incourse');

$urlparams = array('id'=>$course->id);
$PAGE->navbar->add($strsurveys, new moodle_url('/mod/survey/index.php', $urlparams));
$PAGE->navbar->add(format_string($survey->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

//ishidden check.
//hidden surveys except surveys on mainsite are only accessible with related capabilities
if ((empty($cm->visible) AND
        !has_capability('moodle/course:viewhiddenactivities', $context)) AND
        $course->id != SITEID) {
    notice(get_string("activityiscurrentlyhidden"));
}

//check, if the survey is open (timeopen, timeclose)
$checktime = time();

$survey_is_closed = ($survey->timeopen > $checktime) OR
                      ($survey->timeclose < $checktime AND
                            $survey->timeclose > 0);

if ($survey_is_closed) {
    echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2><font color="red">';
        echo get_string('survey_is_not_open', 'survey');
        echo '</font></h2>';
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

//additional check for multiple-submit (prevent browsers back-button).
//the main-check is in view.php
$survey_can_submit = true;
if ($survey->multiple_submit == 0 ) {
    if (survey_is_already_submitted($survey->id, $courseid)) {
        $survey_can_submit = false;
    }
}
if ($survey_can_submit) {
    //preserving the items
    if ($preservevalues == 1) {
        if (!$SESSION->survey->is_started == true) {
            print_error('error', 'error', $CFG->wwwroot.'/course/view.php?id='.$course->id);
        }
        //check, if all required items have a value
        if (survey_check_values($startitempos, $lastitempos)) {
            $userid = $USER->id; //arb
            if ($completedid = survey_save_guest_values(sesskey())) {
                add_to_log($course->id,
                           'survey',
                           'startcomplete',
                           'view.php?id='.$cm->id,
                           $survey->id);

                //now it can be saved
                if (!$gonextpage AND !$gopreviouspage) {
                    $preservevalues = false;
                }

            } else {
                $savereturn = 'failed';
                if (isset($lastpage)) {
                    $gopage = $lastpage;
                } else {
                    print_error('parameters_missing', 'survey');
                }
            }
        } else {
            $savereturn = 'missing';
            $highlightrequired = true;
            if (isset($lastpage)) {
                $gopage = $lastpage;
            } else {
                print_error('parameters_missing', 'survey');
            }
        }
    }

    //saving the items
    if ($savevalues AND !$preservevalues) {
        //exists there any pagebreak, so there are values in the survey_valuetmp
        //arb changed from 0 to $USER->id
        //no strict anonymous surveys
        //if it is a guest taking it then I want to know that it was
        //a guest (at least in the data saved in the survey tables)
        $userid = $USER->id;

        $params = array('id'=>$completedid);
        $surveycompletedtmp = $DB->get_record('survey_completedtmp', $params);

        //fake saving for switchrole
        $is_switchrole = survey_check_is_switchrole();
        if ($is_switchrole) {
            $savereturn = 'saved';
            survey_delete_completedtmp($completedid);
        } else {
            $new_completed_id = survey_save_tmp_values($surveycompletedtmp, false, $userid);
            if ($new_completed_id) {
                $savereturn = 'saved';
                survey_send_email_anonym($cm, $survey, $course, $userid);
                unset($SESSION->survey->is_started);

            } else {
                $savereturn = 'failed';
            }
        }
    }

    if ($allbreaks = survey_get_all_break_positions($survey->id)) {
        if ($gopage <= 0) {
            $startposition = 0;
        } else {
            if (!isset($allbreaks[$gopage - 1])) {
                $gopage = count($allbreaks);
            }
            $startposition = $allbreaks[$gopage - 1];
        }
        $ispagebreak = true;
    } else {
        $startposition = 0;
        $newpage = 0;
        $ispagebreak = false;
    }

    //get the surveyitems after the last shown pagebreak
    $select = 'survey = ? AND position > ?';
    $params = array($survey->id, $startposition);
    $surveyitems = $DB->get_records_select('survey_item', $select, $params, 'position');

    //get the first pagebreak
    $params = array('survey'=>$survey->id, 'typ'=>'pagebreak');
    if ($pagebreaks = $DB->get_records('survey_item', $params, 'position')) {
        $pagebreaks = array_values($pagebreaks);
        $firstpagebreak = $pagebreaks[0];
    } else {
        $firstpagebreak = false;
    }
    $maxitemcount = $DB->count_records('survey_item', array('survey'=>$survey->id));
    $surveycompletedtmp = survey_get_current_completed($survey->id,
                                                           true,
                                                           $courseid,
                                                           sesskey());

    /// Print the main part of the page
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////
    $analysisurl = new moodle_url('/mod/survey/analysis.php', array('id'=>$id));
    if ($courseid > 0) {
        $analysisurl->param('courseid', $courseid);
    }
    echo $OUTPUT->heading(format_text($survey->name));

    if ( (intval($survey->publish_stats) == 1) AND
            ( has_capability('mod/survey:viewanalysepage', $context)) AND
            !( has_capability('mod/survey:viewreports', $context)) ) {
        echo $OUTPUT->box_start('mdl-align');
        echo '<a href="'.$analysisurl->out().'">';
        echo get_string('completed_surveys', 'survey');
        echo '</a>';
        echo $OUTPUT->box_end();
    }

    if (isset($savereturn) && $savereturn == 'saved') {
        if ($survey->page_after_submit) {
            echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
            echo format_text($survey->page_after_submit,
                             $survey->page_after_submitformat,
                             array('overflowdiv' => true));
            echo $OUTPUT->box_end();
        } else {
            echo '<p align="center"><b><font color="green">';
            echo get_string('entries_saved', 'survey');
            echo '</font></b></p>';
            if ( intval($survey->publish_stats) == 1) {
                echo '<p align="center"><a href="'.$analysisurl->out().'">';
                echo get_string('completed_surveys', 'survey').'</a>';
                echo '</p>';
            }
        }
        if ($survey->site_after_submit) {
            $url = survey_encode_target_url($survey->site_after_submit);
        } else {
            if ($courseid) {
                if ($courseid == SITEID) {
                    $url = $CFG->wwwroot;
                } else {
                    $url = $CFG->wwwroot.'/course/view.php?id='.$courseid;
                }
            } else {
                if ($course->id == SITEID) {
                    $url = $CFG->wwwroot;
                } else {
                    $url = $CFG->wwwroot.'/course/view.php?id='.$course->id;
                }
            }
        }
        echo $OUTPUT->continue_button($url);
    } else {
        if (isset($savereturn) && $savereturn == 'failed') {
            echo $OUTPUT->box_start('mform error');
            echo get_string('saving_failed', 'survey');
            echo $OUTPUT->box_end();
        }

        if (isset($savereturn) && $savereturn == 'missing') {
            echo $OUTPUT->box_start('mform error');
            echo get_string('saving_failed_because_missing_or_false_values', 'survey');
            echo $OUTPUT->box_end();
        }

        //print the items
        if (is_array($surveyitems)) {
            echo $OUTPUT->box_start('survey_form');
            echo '<form action="complete_guest.php" method="post" onsubmit=" ">';
            echo '<fieldset>';
            echo '<input type="hidden" name="anonymous" value="0" />';
            $inputvalue = 'value="'.SURVEY_ANONYMOUS_YES.'"';
            echo '<input type="hidden" name="anonymous_response" '.$inputvalue.' />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            //check, if there exists required-elements
            $params = array('survey'=>$survey->id, 'required'=>1);
            $countreq = $DB->count_records('survey_item', $params);
            if ($countreq > 0) {
                echo '<span class="survey_required_mark">(*)';
                echo get_string('items_are_required', 'survey');
                echo '</span>';
            }
            echo $OUTPUT->box_start('survey_items');

            $startitem = null;
            $select = 'survey = ? AND hasvalue = 1 AND position < ?';
            $params = array($survey->id, $startposition);
            $itemnr = $DB->count_records_select('survey_item', $select, $params);
            $lastbreakposition = 0;
            $align = right_to_left() ? 'right' : 'left';

            foreach ($surveyitems as $surveyitem) {
                if (!isset($startitem)) {
                    //avoid showing double pagebreaks
                    if ($surveyitem->typ == 'pagebreak') {
                        continue;
                    }
                    $startitem = $surveyitem;
                }

                if ($surveyitem->dependitem > 0) {
                    //chech if the conditions are ok
                    $fb_compare_value = survey_compare_item_value($surveycompletedtmp->id,
                                                                    $surveyitem->dependitem,
                                                                    $surveyitem->dependvalue,
                                                                    true);
                    if (!isset($surveycompletedtmp->id) OR !$fb_compare_value) {
                        $lastitem = $surveyitem;
                        $lastbreakposition = $surveyitem->position;
                        continue;
                    }
                }

                if ($surveyitem->dependitem > 0) {
                    $dependstyle = ' survey_complete_depend';
                } else {
                    $dependstyle = '';
                }

                echo $OUTPUT->box_start('survey_item_box_'.$align.$dependstyle);
                $value = '';
                //get the value
                $frmvaluename = $surveyitem->typ.'_'.$surveyitem->id;
                if (isset($savereturn)) {
                    if (isset($formdata->{$frmvaluename})) {
                        $value = $formdata->{$frmvaluename};
                    } else {
                        $value = null;
                    }
                } else {
                    if (isset($surveycompletedtmp->id)) {
                        $value = survey_get_item_value($surveycompletedtmp->id,
                                                         $surveyitem->id,
                                                         sesskey());
                    }
                }
                if ($surveyitem->hasvalue == 1 AND $survey->autonumbering) {
                    $itemnr++;
                    echo $OUTPUT->box_start('survey_item_number_'.$align);
                    echo $itemnr;
                    echo $OUTPUT->box_end();
                }
                if ($surveyitem->typ != 'pagebreak') {
                    echo $OUTPUT->box_start('box generalbox boxalign_'.$align);
                    survey_print_item_complete($surveyitem, $value, $highlightrequired);
                    echo $OUTPUT->box_end();
                }
                echo $OUTPUT->box_end();

                $lastbreakposition = $surveyitem->position; //last item-pos (item or pagebreak)
                if ($surveyitem->typ == 'pagebreak') {
                    break;
                } else {
                    $lastitem = $surveyitem;
                }
            }
            echo $OUTPUT->box_end();
            echo '<input type="hidden" name="id" value="'.$id.'" />';
            echo '<input type="hidden" name="surveyid" value="'.$survey->id.'" />';
            echo '<input type="hidden" name="lastpage" value="'.$gopage.'" />';
            if (isset($surveycompletedtmp->id)) {
                $inputvalue = 'value="'.$surveycompletedtmp->id;
            } else {
                $inputvalue = 'value=""';
            }
            echo '<input type="hidden" name="completedid" '.$inputvalue.' />';
            echo '<input type="hidden" name="courseid" value="'. $courseid . '" />';
            echo '<input type="hidden" name="preservevalues" value="1" />';
            if (isset($startitem)) {
                echo '<input type="hidden" name="startitempos" value="'.$startitem->position.'" />';
                echo '<input type="hidden" name="lastitempos" value="'.$lastitem->position.'" />';
            }

            if ($ispagebreak AND $lastbreakposition > $firstpagebreak->position) {
                $inputvalue = 'value="'.get_string('previous_page', 'survey').'"';
                echo '<input name="gopreviouspage" type="submit" '.$inputvalue.' />';
            }
            if ($lastbreakposition < $maxitemcount) {
                $inputvalue = 'value="'.get_string('next_page', 'survey').'"';
                echo '<input name="gonextpage" type="submit" '.$inputvalue.' />';
            }
            if ($lastbreakposition >= $maxitemcount) { //last page
                $inputvalue = 'value="'.get_string('save_entries', 'survey').'"';
                echo '<input name="savevalues" type="submit" '.$inputvalue.' />';
            }

            echo '</fieldset>';
            echo '</form>';
            echo $OUTPUT->box_end();

            echo $OUTPUT->box_start('survey_complete_cancel');
            if ($courseid) {
                $action = 'action="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'"';
            } else {
                if ($course->id == SITEID) {
                    $action = 'action="'.$CFG->wwwroot.'"';
                } else {
                    $action = 'action="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'"';
                }
            }
            echo '<form '.$action.' method="post" onsubmit=" ">';
            echo '<fieldset>';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="courseid" value="'. $courseid . '" />';
            echo '<button type="submit">'.get_string('cancel').'</button>';
            echo '</fieldset>';
            echo '</form>';
            echo $OUTPUT->box_end();
            $SESSION->survey->is_started = true;
        }
    }
} else {
    echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2><font color="red">';
        echo get_string('this_survey_is_already_submitted', 'survey');
        echo '</font></h2>';
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
    echo $OUTPUT->box_end();
}
/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();

