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
 * prints the form so the user can fill out the survey
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');

survey_init_survey_session();

$id = required_param('id', PARAM_INT);
$completedid = optional_param('completedid', false, PARAM_INT);
$preservevalues  = optional_param('preservevalues', 0,  PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);
$gopage = optional_param('gopage', -1, PARAM_INT);
$lastpage = optional_param('lastpage', false, PARAM_INT);
$startitempos = optional_param('startitempos', 0, PARAM_INT);
$lastitempos = optional_param('lastitempos', 0, PARAM_INT);
$anonymous_response = optional_param('anonymous_response', 0, PARAM_INT); //arb

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
        print_error('missingparameter');
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

if (has_capability('mod/survey:complete', $context)) {
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
            print_error('notavailable', 'survey');
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

if (!$survey_complete_cap) {
    print_error('error');
}

// Mark activity viewed for completion-tracking
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

if ($course->id == SITEID) {
    $PAGE->set_cm($cm, $course); // set's up global $COURSE
    $PAGE->set_pagelayout('incourse');
}

$PAGE->navbar->add(get_string('survey:complete', 'survey'));
$urlparams = array('id'=>$cm->id, 'gopage'=>$gopage, 'courseid'=>$course->id);
$PAGE->set_url('/mod/survey/complete.php', $urlparams);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

//ishidden check.
//survey in courses
if ((empty($cm->visible) AND
        !has_capability('moodle/course:viewhiddenactivities', $context)) AND
        $course->id != SITEID) {
    notice(get_string("activityiscurrentlyhidden"));
}

//ishidden check.
//survey on mainsite
if ((empty($cm->visible) AND
        !has_capability('moodle/course:viewhiddenactivities', $context)) AND
        $courseid == SITEID) {
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
        if (!isset($SESSION->survey->is_started) OR !$SESSION->survey->is_started == true) {
            print_error('error', '', $CFG->wwwroot.'/course/view.php?id='.$course->id);
        }
        //checken, ob alle required items einen wert haben
        if (survey_check_values($startitempos, $lastitempos)) {
            $userid = $USER->id; //arb
            if ($completedid = survey_save_values($USER->id, true)) {
                if ($userid > 0) {
                    add_to_log($course->id,
                               'survey',
                               'startcomplete',
                               'view.php?id='.$cm->id,
                               $survey->id,
                               $cm->id,
                               $userid);
                }
                if (!$gonextpage AND !$gopreviouspage) {
                    $preservevalues = false;//es kann gespeichert werden
                }

            } else {
                $savereturn = 'failed';
                if (isset($lastpage)) {
                    $gopage = $lastpage;
                } else {
                    print_error('missingparameter');
                }
            }
        } else {
            $savereturn = 'missing';
            $highlightrequired = true;
            if (isset($lastpage)) {
                $gopage = $lastpage;
            } else {
                print_error('missingparameter');
            }

        }
    }

    //saving the items
    if ($savevalues AND !$preservevalues) {
        //exists there any pagebreak, so there are values in the survey_valuetmp
        $userid = $USER->id; //arb

        if ($survey->anonymous == SURVEY_ANONYMOUS_NO) {
            $surveycompleted = survey_get_current_completed($survey->id, false, $courseid);
        } else {
            $surveycompleted = false;
        }
        $params = array('id' => $completedid);
        $surveycompletedtmp = $DB->get_record('survey_completedtmp', $params);
        //fake saving for switchrole
        $is_switchrole = survey_check_is_switchrole();
        if ($is_switchrole) {
            $savereturn = 'saved';
            survey_delete_completedtmp($completedid);
        } else {
            $new_completed_id = survey_save_tmp_values($surveycompletedtmp,
                                                         $surveycompleted,
                                                         $userid);
            if ($new_completed_id) {
                $savereturn = 'saved';
                if ($survey->anonymous == SURVEY_ANONYMOUS_NO) {
                    add_to_log($course->id,
                              'survey',
                              'submit',
                              'view.php?id='.$cm->id,
                              $survey->id,
                              $cm->id,
                              $userid);

                    survey_send_email($cm, $survey, $course, $userid);
                } else {
                    survey_send_email_anonym($cm, $survey, $course, $userid);
                }
                //tracking the submit
                $tracking = new stdClass();
                $tracking->userid = $USER->id;
                $tracking->survey = $survey->id;
                $tracking->completed = $new_completed_id;
                $DB->insert_record('survey_tracking', $tracking);
                unset($SESSION->survey->is_started);

                // Update completion state
                $completion = new completion_info($course);
                if ($completion->is_enabled($cm) && $survey->completionsubmit) {
                    $completion->update_state($cm, COMPLETION_COMPLETE);
                }

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
    $params = array('survey' => $survey->id, 'typ' => 'pagebreak');
    if ($pagebreaks = $DB->get_records('survey_item', $params, 'position')) {
        $pagebreaks = array_values($pagebreaks);
        $firstpagebreak = $pagebreaks[0];
    } else {
        $firstpagebreak = false;
    }
    $maxitemcount = $DB->count_records('survey_item', array('survey'=>$survey->id));

    //get the values of completeds before done. Anonymous user can not get these values.
    if ((!isset($SESSION->survey->is_started)) AND
                          (!isset($savereturn)) AND
                          ($survey->anonymous == SURVEY_ANONYMOUS_NO)) {

        $surveycompletedtmp = survey_get_current_completed($survey->id, true, $courseid);
        if (!$surveycompletedtmp) {
            $surveycompleted = survey_get_current_completed($survey->id, false, $courseid);
            if ($surveycompleted) {
                //copy the values to survey_valuetmp create a completedtmp
                $surveycompletedtmp = survey_set_tmp_values($surveycompleted);
            }
        }
    } else {
        $surveycompletedtmp = survey_get_current_completed($survey->id, true, $courseid);
    }

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

        $params = array('userid' => $USER->id, 'survey' => $survey->id);
        if ($multiple_count = $DB->count_records('survey_tracking', $params)) {
            echo $OUTPUT->box_start('mdl-align');
            echo '<a href="'.$analysisurl->out().'">';
            echo get_string('completed_surveys', 'survey').'</a>';
            echo $OUTPUT->box_end();
        }
    }

    if (isset($savereturn) && $savereturn == 'saved') {
        if ($survey->page_after_submit) {
            echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
            echo format_text($survey->page_after_submit,
                             $survey->page_after_submitformat,
                             array('overflowdiv' => true));
            echo $OUTPUT->box_end();
        } else {
            echo '<p align="center">';
            echo '<b><font color="green">';
            echo get_string('entries_saved', 'survey');
            echo '</font></b>';
            echo '</p>';
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
            echo '<form action="complete.php" method="post" onsubmit=" ">';
            echo '<fieldset>';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo $OUTPUT->box_start('survey_anonymousinfo');
            switch ($survey->anonymous) {
                case SURVEY_ANONYMOUS_YES:
                    echo '<input type="hidden" name="anonymous" value="1" />';
                    $inputvalue = 'value="'.SURVEY_ANONYMOUS_YES.'"';
                    echo '<input type="hidden" name="anonymous_response" '.$inputvalue.' />';
                    echo get_string('mode', 'survey').': '.get_string('anonymous', 'survey');
                    break;
                case SURVEY_ANONYMOUS_NO:
                    echo '<input type="hidden" name="anonymous" value="0" />';
                    $inputvalue = 'value="'.SURVEY_ANONYMOUS_NO.'"';
                    echo '<input type="hidden" name="anonymous_response" '.$inputvalue.' />';
                    echo get_string('mode', 'survey').': ';
                    echo get_string('non_anonymous', 'survey');
                    break;
            }
            echo $OUTPUT->box_end();
            //check, if there exists required-elements
            $params = array('survey' => $survey->id, 'required' => 1);
            $countreq = $DB->count_records('survey_item', $params);
            if ($countreq > 0) {
                echo '<span class="survey_required_mark">(*)';
                echo get_string('items_are_required', 'survey');
                echo '</span>';
            }
            echo $OUTPUT->box_start('survey_items');

            unset($startitem);
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
                $frmvaluename = $surveyitem->typ . '_'. $surveyitem->id;
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
                                                         true);
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
                $inputvalue = 'value="'.$surveycompletedtmp->id.'"';
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

            if ( $ispagebreak AND $lastbreakposition > $firstpagebreak->position) {
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
        echo '<h2>';
        echo '<font color="red">';
        echo get_string('this_survey_is_already_submitted', 'survey');
        echo '</font>';
        echo '</h2>';
        echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
    echo $OUTPUT->box_end();
}
/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();
