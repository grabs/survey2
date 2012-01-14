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
 * Library of functions and constants for module survey
 * includes the main-part of survey-functions
 *
 * @package mod-survey
 * @copyright Andreas Grabs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');

define('SURVEY_ANONYMOUS_YES', 1);
define('SURVEY_ANONYMOUS_NO', 2);
define('SURVEY_MIN_ANONYMOUS_COUNT_IN_GROUP', 2);
define('SURVEY_DECIMAL', '.');
define('SURVEY_THOUSAND', ',');
define('SURVEY_RESETFORM_RESET', 'survey_reset_data_');
define('SURVEY_RESETFORM_DROP', 'survey_drop_survey_');
define('SURVEY_MAX_PIX_LENGTH', '400'); //max. Breite des grafischen Balkens in der Auswertung
define('SURVEY_DEFAULT_PAGE_COUNT', 20);

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function survey_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * this will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $survey the object given by mod_survey_mod_form
 * @return int
 */
function survey_add_instance($survey) {
    global $DB;

    $survey->timemodified = time();
    $survey->id = '';

    //check if openenable and/or closeenable is set and set correctly to save in db
    if (empty($survey->openenable)) {
        $survey->timeopen = 0;
    }
    if (empty($survey->closeenable)) {
        $survey->timeclose = 0;
    }
    if (empty($survey->site_after_submit)) {
        $survey->site_after_submit = '';
    }

    //saving the survey in db
    $surveyid = $DB->insert_record("survey", $survey);

    $survey->id = $surveyid;

    survey_set_events($survey);

    return $surveyid;
}

/**
 * this will update a given instance
 *
 * @global object
 * @param object $survey the object given by mod_survey_mod_form
 * @return boolean
 */
function survey_update_instance($survey) {
    global $DB;

    $survey->timemodified = time();
    $survey->id = $survey->instance;

    //check if openenable and/or closeenable is set and set correctly to save in db
    if (empty($survey->openenable)) {
        $survey->timeopen = 0;
    }
    if (empty($survey->closeenable)) {
        $survey->timeclose = 0;
    }
    if (empty($survey->site_after_submit)) {
        $survey->site_after_submit = '';
    }

    //save the survey into the db
    $DB->update_record("survey", $survey);

    //create or update the new events
    survey_set_events($survey);

    return true;
}

/**
 * Serves the files included in survey items like label. Implements needed access control ;-)
 *
 * There are two situations in general where the files will be sent.
 * 1) filearea = item, 2) filearea = template
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function survey_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    $itemid = (int)array_shift($args);

    //get the item what includes the file
    if (!$item = $DB->get_record('survey_item', array('id'=>$itemid))) {
        return false;
    }

    //if the filearea is "item" so we check the permissions like view/complete the survey
    if ($filearea === 'item') {
        //get the survey
        if (!$survey = $DB->get_record('survey', array('id'=>$item->survey))) {
            return false;
        }

        $canload = false;
        //first check whether the user has the complete capability
        if (has_capability('mod/survey:complete', $context)) {
            $canload = true;
        }

        //now we check whether the user has the view capability
        if (has_capability('mod/survey:view', $context)) {
            $canload = true;
        }

        //if the survey is on frontpage and anonymous and the fullanonymous is allowed
        //so the file can be loaded too.
        if (isset($CFG->survey_allowfullanonymous)
                    AND $CFG->survey_allowfullanonymous
                    AND $course->id == SITEID
                    AND $survey->anonymous == SURVEY_ANONYMOUS_YES ) {
            $canload = true;
        }

        if (!$canload) {
            return false;
        }
    } else if ($filearea === 'template') { //now we check files in templates
        if (!$template = $DB->get_record('survey_template', array('id'=>$item->template))) {
            return false;
        }

        //if the file is not public so the capability edititems has to be there
        if (!$template->ispublic) {
            if (!has_capability('mod/survey:edititems', $context)) {
                return false;
            }
        } else { //on public templates, at least the user has to be logged in
            if (!isloggedin()) {
                return false;
            }
        }
    } else {
        return false;
    }

    if ($context->contextlevel == CONTEXT_MODULE) {
        if ($filearea !== 'item') {
            return false;
        }

        if ($item->survey == $cm->instance) {
            $filecontext = $context;
        } else {
            return false;
        }
    }

    if ($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_SYSTEM) {
        if ($filearea !== 'template') {
            return false;
        }
    }

    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_survey/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true); // download MUST be forced - security!

    return false;
}

/**
 * this will delete a given instance.
 * all referenced data also will be deleted
 *
 * @global object
 * @param int $id the instanceid of survey
 * @return boolean
 */
function survey_delete_instance($id) {
    global $DB;

    //get all referenced items
    $surveyitems = $DB->get_records('survey_item', array('survey'=>$id));

    //deleting all referenced items and values
    if (is_array($surveyitems)) {
        foreach ($surveyitems as $surveyitem) {
            $DB->delete_records("survey_value", array("item"=>$surveyitem->id));
            $DB->delete_records("survey_valuetmp", array("item"=>$surveyitem->id));
        }
        if ($delitems = $DB->get_records("survey_item", array("survey"=>$id))) {
            foreach ($delitems as $delitem) {
                survey_delete_item($delitem->id, false);
            }
        }
    }

    //deleting the referenced tracking data
    $DB->delete_records('survey_tracking', array('survey'=>$id));

    //deleting the completeds
    $DB->delete_records("survey_completed", array("survey"=>$id));

    //deleting the unfinished completeds
    $DB->delete_records("survey_completedtmp", array("survey"=>$id));

    //deleting old events
    $DB->delete_records('event', array('modulename'=>'survey', 'instance'=>$id));
    return $DB->delete_records("survey", array("id"=>$id));
}

/**
 * this is called after deleting all instances if the course will be deleted.
 * only templates have to be deleted
 *
 * @global object
 * @param object $course
 * @return boolean
 */
function survey_delete_course($course) {
    global $DB;

    //delete all templates of given course
    return $DB->delete_records('survey_template', array('course'=>$course->id));
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $survey
 * @return object
 */
function survey_user_outline($course, $user, $mod, $survey) {
    return null;
}

/**
 * Returns all users who has completed a specified survey since a given time
 * many thanks to Manolescu Dorel, who contributed these two functions
 *
 * @global object
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $activities Passed by reference
 * @param int $index Passed by reference
 * @param int $timemodified Timestamp
 * @param int $courseid
 * @param int $cmid
 * @param int $userid
 * @param int $groupid
 * @return void
 */
function survey_get_recent_mod_activity(&$activities, &$index,
                                          $timemodified, $courseid,
                                          $cmid, $userid="", $groupid="") {

    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $sqlargs = array();

    //TODO: user user_picture::fields;
    $sql = " SELECT fk . * , fc . * , u.firstname, u.lastname, u.email, u.picture, u.email
                                            FROM {survey_completed} fc
                                                JOIN {survey} fk ON fk.id = fc.survey
                                                JOIN {user} u ON u.id = fc.userid ";

    if ($groupid) {
        $sql .= " JOIN {groups_members} gm ON  gm.userid=u.id ";
    }

    $sql .= " WHERE fc.timemodified > ? AND fk.id = ? ";
    $sqlargs[] = $timemodified;
    $sqlargs[] = $cm->instace;

    if ($userid) {
        $sql .= " AND u.id = ? ";
        $sqlargs[] = $userid;
    }

    if ($groupid) {
        $sql .= " AND gm.groupid = ? ";
        $sqlargs[] = $groupid;
    }

    if (!$surveyitems = $DB->get_records_sql($sql, $sqlargs)) {
        return;
    }

    $cm_context      = get_context_instance(CONTEXT_MODULE, $cm->id);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    if (is_null($modinfo->groups)) {
        // load all my groups and cache it in modinfo
        $modinfo->groups = groups_get_user_groups($course->id);
    }

    $aname = format_string($cm->name, true);
    foreach ($surveyitems as $surveyitem) {
        if ($surveyitem->userid != $USER->id) {

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                $usersgroups = groups_get_all_groups($course->id,
                                                     $surveyitem->userid,
                                                     $cm->groupingid);
                if (!is_array($usersgroups)) {
                    continue;
                }
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();

        $tmpactivity->type      = 'survey';
        $tmpactivity->cmid      = $cm->id;
        $tmpactivity->name      = $aname;
        $tmpactivity->sectionnum= $cm->sectionnum;
        $tmpactivity->timestamp = $surveyitem->timemodified;

        $tmpactivity->content->surveyid = $surveyitem->id;
        $tmpactivity->content->surveyuserid = $surveyitem->userid;

        //TODO: add all necessary user fields, this is not enough for user_picture
        $tmpactivity->user->userid   = $surveyitem->userid;
        $tmpactivity->user->fullname = fullname($surveyitem, $viewfullnames);
        $tmpactivity->user->picture  = $surveyitem->picture;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * Prints all users who has completed a specified survey since a given time
 * many thanks to Manolescu Dorel, who contributed these two functions
 *
 * @global object
 * @param object $activity
 * @param int $courseid
 * @param string $detail
 * @param array $modnames
 * @return void Output is echo'd
 */
function survey_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td>";

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"$modname\" />";
        echo "<a href=\"$CFG->wwwroot/mod/survey/view.php?id={$activity->cmid}\">{$activity->name}</a>";
        echo '</div>';
    }

    echo '<div class="title">';
    echo '</div>';

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->userid}&amp;course=$courseid\">"
         ."{$activity->user->fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';

    echo "</td></tr></table>";

    return;
}

/**
 * Obtains the automatic completion state for this survey based on the condition
 * in survey settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function survey_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    // Get survey details
    $survey = $DB->get_record('survey', array('id'=>$cm->instance), '*', MUST_EXIST);

    // If completion option is enabled, evaluate it and return true/false
    if ($survey->completionsubmit) {
        $params = array('userid'=>$userid, 'survey'=>$survey->id);
        return $DB->record_exists('survey_tracking', $params);
    } else {
        // Completion option is not enabled so just return $type
        return $type;
    }
}


/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $mod
 * @param object $survey
 * @return bool
 */
function survey_user_complete($course, $user, $mod, $survey) {
    return true;
}

/**
 * @return bool true
 */
function survey_cron () {
    return true;
}

/**
 * @todo: deprecated - to be deleted in 2.2
 * @return bool false
 */
function survey_get_participants($surveyid) {
    return false;
}


/**
 * @return bool false
 */
function survey_scale_used ($surveyid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of survey
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any assignment
 */
function survey_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * @return array
 */
function survey_get_view_actions() {
    return array('view', 'view all');
}

/**
 * @return array
 */
function survey_get_post_actions() {
    return array('submit');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all responses from the specified survey
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @uses SURVEY_RESETFORM_RESET
 * @uses SURVEY_RESETFORM_DROP
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function survey_reset_userdata($data) {
    global $CFG, $DB;

    $resetsurveys = array();
    $dropsurveys = array();
    $status = array();
    $componentstr = get_string('modulenameplural', 'survey');

    //get the relevant entries from $data
    foreach ($data as $key => $value) {
        switch(true) {
            case substr($key, 0, strlen(SURVEY_RESETFORM_RESET)) == SURVEY_RESETFORM_RESET:
                if ($value == 1) {
                    $templist = explode('_', $key);
                    if (isset($templist[3])) {
                        $resetsurveys[] = intval($templist[3]);
                    }
                }
            break;
            case substr($key, 0, strlen(SURVEY_RESETFORM_DROP)) == SURVEY_RESETFORM_DROP:
                if ($value == 1) {
                    $templist = explode('_', $key);
                    if (isset($templist[3])) {
                        $dropsurveys[] = intval($templist[3]);
                    }
                }
            break;
        }
    }

    //reset the selected surveys
    foreach ($resetsurveys as $id) {
        $survey = $DB->get_record('survey', array('id'=>$id));
        survey_delete_all_completeds($id);
        $status[] = array('component'=>$componentstr.':'.$survey->name,
                        'item'=>get_string('resetting_data', 'survey'),
                        'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @global object
 * @uses SURVEY_RESETFORM_RESET
 * @param object $mform form passed by reference
 */
function survey_reset_course_form_definition(&$mform) {
    global $COURSE, $DB;

    $mform->addElement('header', 'surveyheader', get_string('modulenameplural', 'survey'));

    if (!$surveys = $DB->get_records('survey', array('course'=>$COURSE->id), 'name')) {
        return;
    }

    $mform->addElement('static', 'hint', get_string('resetting_data', 'survey'));
    foreach ($surveys as $survey) {
        $mform->addElement('checkbox', SURVEY_RESETFORM_RESET.$survey->id, $survey->name);
    }
}

/**
 * Course reset form defaults.
 *
 * @global object
 * @uses SURVEY_RESETFORM_RESET
 * @param object $course
 */
function survey_reset_course_form_defaults($course) {
    global $DB;

    $return = array();
    if (!$surveys = $DB->get_records('survey', array('course'=>$course->id), 'name')) {
        return;
    }
    foreach ($surveys as $survey) {
        $return[SURVEY_RESETFORM_RESET.$survey->id] = true;
    }
    return $return;
}

/**
 * Called by course/reset.php and shows the formdata by coursereset.
 * it prints checkboxes for each survey available at the given course
 * there are two checkboxes:
 * 1) delete userdata and keep the survey
 * 2) delete userdata and drop the survey
 *
 * @global object
 * @uses SURVEY_RESETFORM_RESET
 * @uses SURVEY_RESETFORM_DROP
 * @param object $course
 * @return void
 */
function survey_reset_course_form($course) {
    global $DB, $OUTPUT;

    echo get_string('resetting_surveys', 'survey'); echo ':<br />';
    if (!$surveys = $DB->get_records('survey', array('course'=>$course->id), 'name')) {
        return;
    }

    foreach ($surveys as $survey) {
        echo '<p>';
        echo get_string('name', 'survey').': '.$survey->name.'<br />';
        echo html_writer::checkbox(SURVEY_RESETFORM_RESET.$survey->id,
                                1, true,
                                get_string('resetting_data', 'survey'));
        echo '<br />';
        echo html_writer::checkbox(SURVEY_RESETFORM_DROP.$survey->id,
                                1, false,
                                get_string('drop_survey', 'survey'));
        echo '</p>';
    }
}

/**
 * This creates new events given as timeopen and closeopen by $survey.
 *
 * @global object
 * @param object $survey
 * @return void
 */
function survey_set_events($survey) {
    global $DB;

    // adding the survey to the eventtable (I have seen this at quiz-module)
    $DB->delete_records('event', array('modulename'=>'survey', 'instance'=>$survey->id));

    if (!isset($survey->coursemodule)) {
        $cm = get_coursemodule_from_id('survey', $survey->id);
        $survey->coursemodule = $cm->id;
    }

    // the open-event
    if ($survey->timeopen > 0) {
        $event = null;
        $event->name         = get_string('start', 'survey').' '.$survey->name;
        $event->description  = format_module_intro('survey', $survey, $survey->coursemodule);
        $event->courseid     = $survey->course;
        $event->groupid      = 0;
        $event->userid       = 0;
        $event->modulename   = 'survey';
        $event->instance     = $survey->id;
        $event->eventtype    = 'open';
        $event->timestart    = $survey->timeopen;
        $event->visible      = instance_is_visible('survey', $survey);
        if ($survey->timeclose > 0) {
            $event->timeduration = ($survey->timeclose - $survey->timeopen);
        } else {
            $event->timeduration = 0;
        }

        calendar_event::create($event);
    }

    // the close-event
    if ($survey->timeclose > 0) {
        $event = null;
        $event->name         = get_string('stop', 'survey').' '.$survey->name;
        $event->description  = format_module_intro('survey', $survey, $survey->coursemodule);
        $event->courseid     = $survey->course;
        $event->groupid      = 0;
        $event->userid       = 0;
        $event->modulename   = 'survey';
        $event->instance     = $survey->id;
        $event->eventtype    = 'close';
        $event->timestart    = $survey->timeclose;
        $event->visible      = instance_is_visible('survey', $survey);
        $event->timeduration = 0;

        calendar_event::create($event);
    }
}

/**
 * this function is called by {@link survey_delete_userdata()}
 * it drops the survey-instance from the course_module table
 *
 * @global object
 * @param int $id the id from the coursemodule
 * @return boolean
 */
function survey_delete_course_module($id) {
    global $DB;

    if (!$cm = $DB->get_record('course_modules', array('id'=>$id))) {
        return true;
    }
    return $DB->delete_records('course_modules', array('id'=>$cm->id));
}



////////////////////////////////////////////////
//functions to handle capabilities
////////////////////////////////////////////////

/**
 * returns the context-id related to the given coursemodule-id
 *
 * @staticvar object $context
 * @param int $cmid the coursemodule-id
 * @return object $context
 */
function survey_get_context($cmid) {
    static $context;

    if (isset($context)) {
        return $context;
    }

    if (!$context = get_context_instance(CONTEXT_MODULE, $cmid)) {
            print_error('badcontext');
    }
    return $context;
}

/**
 *  returns true if the current role is faked by switching role feature
 *
 * @global object
 * @return boolean
 */
function survey_check_is_switchrole() {
    global $USER;
    if (isset($USER->switchrole) AND
            is_array($USER->switchrole) AND
            count($USER->switchrole) > 0) {

        return true;
    }
    return false;
}

/**
 * count users which have not completed the survey
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @param object $cm
 * @param int $group single groupid
 * @param string $sort
 * @param int $startpage
 * @param int $pagecount
 * @return object the userrecords
 */
function survey_get_incomplete_users($cm,
                                       $group = false,
                                       $sort = '',
                                       $startpage = false,
                                       $pagecount = false) {

    global $DB;

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    //first get all user who can complete this survey
    $cap = 'mod/survey:complete';
    $fields = 'u.id, u.username';
    if (!$allusers = get_users_by_capability($context,
                                            $cap,
                                            $fields,
                                            $sort,
                                            '',
                                            '',
                                            $group,
                                            '',
                                            true)) {
        return false;
    }
    $allusers = array_keys($allusers);

    //now get all completeds
    $params = array('survey'=>$cm->instance);
    if (!$completedusers = $DB->get_records_menu('survey_completed', $params, '', 'userid,id')) {
        return $allusers;
    }
    $completedusers = array_keys($completedusers);

    //now strike all completedusers from allusers
    $allusers = array_diff($allusers, $completedusers);

    //for paging I use array_slice()
    if ($startpage !== false AND $pagecount !== false) {
        $allusers = array_slice($allusers, $startpage, $pagecount);
    }

    return $allusers;
}

/**
 * count users which have not completed the survey
 *
 * @global object
 * @param object $cm
 * @param int $group single groupid
 * @return int count of userrecords
 */
function survey_count_incomplete_users($cm, $group = false) {
    if ($allusers = survey_get_incomplete_users($cm, $group)) {
        return count($allusers);
    }
    return 0;
}

/**
 * count users which have completed a survey
 *
 * @global object
 * @uses SURVEY_ANONYMOUS_NO
 * @param object $cm
 * @param int $group single groupid
 * @return int count of userrecords
 */
function survey_count_complete_users($cm, $group = false) {
    global $DB;

    $params = array(SURVEY_ANONYMOUS_NO, $cm->instance);

    $fromgroup = '';
    $wheregroup = '';
    if ($group) {
        $fromgroup = ', {groups_members} g';
        $wheregroup = ' AND g.groupid = ? AND g.userid = c.userid';
        $params[] = $group;
    }

    $sql = 'SELECT COUNT(u.id) FROM {user} u, {survey_completed} c'.$fromgroup.'
              WHERE anonymous_response = ? AND u.id = c.userid AND c.survey = ?
              '.$wheregroup;

    return $DB->count_records_sql($sql, $params);

}

/**
 * get users which have completed a survey
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @uses SURVEY_ANONYMOUS_NO
 * @param object $cm
 * @param int $group single groupid
 * @param string $where a sql where condition (must end with " AND ")
 * @param array parameters used in $where
 * @param string $sort a table field
 * @param int $startpage
 * @param int $pagecount
 * @return object the userrecords
 */
function survey_get_complete_users($cm,
                                     $group = false,
                                     $where = '',
                                     array $params = null,
                                     $sort = '',
                                     $startpage = false,
                                     $pagecount = false) {

    global $DB;

    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
            print_error('badcontext');
    }

    $params = (array)$params;

    $params['anon'] = SURVEY_ANONYMOUS_NO;
    $params['instance'] = $cm->instance;

    $fromgroup = '';
    $wheregroup = '';
    if ($group) {
        $fromgroup = ', {groups_members} g';
        $wheregroup = ' AND g.groupid = :group AND g.userid = c.userid';
        $params['group'] = $group;
    }

    if ($sort) {
        $sortsql = ' ORDER BY '.$sort;
    } else {
        $sortsql = '';
    }

    $ufields = user_picture::fields('u');
    $sql = 'SELECT DISTINCT '.$ufields.'
            FROM {user} u, {survey_completed} c '.$fromgroup.'
            WHERE '.$where.' anonymous_response = :anon
                AND u.id = c.userid
                AND c.survey = :instance
              '.$wheregroup.$sortsql;

    if ($startpage === false OR $pagecount === false) {
        $startpage = false;
        $pagecount = false;
    }
    return $DB->get_records_sql($sql, $params, $startpage, $pagecount);
}

/**
 * get users which have the viewreports-capability
 *
 * @uses CONTEXT_MODULE
 * @param int $cmid
 * @param mixed $groups single groupid or array of groupids - group(s) user is in
 * @return object the userrecords
 */
function survey_get_viewreports_users($cmid, $groups = false) {

    if (!$context = get_context_instance(CONTEXT_MODULE, $cmid)) {
            print_error('badcontext');
    }

    //description of the call below:
    //get_users_by_capability($context, $capability, $fields='', $sort='', $limitfrom='',
    //                          $limitnum='', $groups='', $exceptions='', $doanything=true)
    return get_users_by_capability($context,
                            'mod/survey:viewreports',
                            '',
                            'lastname',
                            '',
                            '',
                            $groups,
                            '',
                            false);
}

/**
 * get users which have the receivemail-capability
 *
 * @uses CONTEXT_MODULE
 * @param int $cmid
 * @param mixed $groups single groupid or array of groupids - group(s) user is in
 * @return object the userrecords
 */
function survey_get_receivemail_users($cmid, $groups = false) {

    if (!$context = get_context_instance(CONTEXT_MODULE, $cmid)) {
            print_error('badcontext');
    }

    //description of the call below:
    //get_users_by_capability($context, $capability, $fields='', $sort='', $limitfrom='',
    //                          $limitnum='', $groups='', $exceptions='', $doanything=true)
    return get_users_by_capability($context,
                            'mod/survey:receivemail',
                            '',
                            'lastname',
                            '',
                            '',
                            $groups,
                            '',
                            false);
}

////////////////////////////////////////////////
//functions to handle the templates
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * creates a new template-record.
 *
 * @global object
 * @param int $courseid
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return int the new templateid
 */
function survey_create_template($courseid, $name, $ispublic = 0) {
    global $DB;

    $templ = new stdClass();
    $templ->course   = ($ispublic ? 0 : $courseid);
    $templ->name     = $name;
    $templ->ispublic = $ispublic;

    $templid = $DB->insert_record('survey_template', $templ);
    return $DB->get_record('survey_template', array('id'=>$templid));
}

/**
 * creates new template items.
 * all items will be copied and the attribute survey will be set to 0
 * and the attribute template will be set to the new templateid
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @param object $survey
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return boolean
 */
function survey_save_as_template($survey, $name, $ispublic = 0) {
    global $DB;
    $fs = get_file_storage();

    if (!$surveyitems = $DB->get_records('survey_item', array('survey'=>$survey->id))) {
        return false;
    }

    if (!$newtempl = survey_create_template($survey->course, $name, $ispublic)) {
        return false;
    }

    //files in the template_item are in the context of the current course or
    //if the template is public the files are in the system context
    //files in the survey_item are in the survey_context of the survey
    if ($ispublic) {
        $s_context = get_system_context();
    } else {
        $s_context = get_context_instance(CONTEXT_COURSE, $newtempl->course);
    }
    $cm = get_coursemodule_from_instance('survey', $survey->id);
    $f_context = get_context_instance(CONTEXT_MODULE, $cm->id);

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($surveyitems as $item) {

        $t_item = clone($item);

        unset($t_item->id);
        $t_item->survey = 0;
        $t_item->template     = $newtempl->id;
        $t_item->id = $DB->insert_record('survey_item', $t_item);
        //copy all included files to the survey_template filearea
        $itemfiles = $fs->get_area_files($f_context->id,
                                    'mod_survey',
                                    'item',
                                    $item->id,
                                    "id",
                                    false);
        if ($itemfiles) {
            foreach ($itemfiles as $ifile) {
                $file_record = new stdClass();
                $file_record->contextid = $s_context->id;
                $file_record->component = 'mod_survey';
                $file_record->filearea = 'template';
                $file_record->itemid = $t_item->id;
                $fs->create_file_from_storedfile($file_record, $ifile);
            }
        }

        $itembackup[$item->id] = $t_item->id;
        if ($t_item->dependitem) {
            $dependitemsmap[$t_item->id] = $t_item->dependitem;
        }

    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('survey_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('survey_item', $newitem);
    }

    return true;
}

/**
 * deletes all survey_items related to the given template id
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @param object $template the template
 * @return void
 */
function survey_delete_template($template) {
    global $DB;

    //deleting the files from the item is done by survey_delete_item
    if ($t_items = $DB->get_records("survey_item", array("template"=>$template->id))) {
        foreach ($t_items as $t_item) {
            survey_delete_item($t_item->id, false, $template);
        }
    }
    $DB->delete_records("survey_template", array("id"=>$template->id));
}

/**
 * creates new survey_item-records from template.
 * if $deleteold is set true so the existing items of the given survey will be deleted
 * if $deleteold is set false so the new items will be appanded to the old items
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @uses CONTEXT_MODULE
 * @param object $survey
 * @param int $templateid
 * @param boolean $deleteold
 */
function survey_items_from_template($survey, $templateid, $deleteold = false) {
    global $DB, $CFG;

    require_once($CFG->libdir.'/completionlib.php');

    $fs = get_file_storage();

    if (!$template = $DB->get_record('survey_template', array('id'=>$templateid))) {
        return false;
    }
    //get all templateitems
    if (!$templitems = $DB->get_records('survey_item', array('template'=>$templateid))) {
        return false;
    }

    //files in the template_item are in the context of the current course
    //files in the survey_item are in the survey_context of the survey
    if ($template->ispublic) {
        $s_context = get_system_context();
    } else {
        $s_context = get_context_instance(CONTEXT_COURSE, $survey->course);
    }
    $course = $DB->get_record('course', array('id'=>$survey->course));
    $cm = get_coursemodule_from_instance('survey', $survey->id);
    $f_context = get_context_instance(CONTEXT_MODULE, $cm->id);

    //if deleteold then delete all old items before
    //get all items
    if ($deleteold) {
        if ($surveyitems = $DB->get_records('survey_item', array('survey'=>$survey->id))) {
            //delete all items of this survey
            foreach ($surveyitems as $item) {
                survey_delete_item($item->id, false);
            }
            //delete tracking-data
            $DB->delete_records('survey_tracking', array('survey'=>$survey->id));

            $params = array('survey'=>$survey->id);
            if ($completeds = $DB->get_records('survey_completed', $params)) {
                $completion = new completion_info($course);
                foreach ($completeds as $completed) {
                    // Update completion state
                    if ($completion->is_enabled($cm) && $survey->completionsubmit) {
                        $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
                    }
                    $DB->delete_records('survey_completed', array('id'=>$completed->id));
                }
            }
            $DB->delete_records('survey_completedtmp', array('survey'=>$survey->id));
        }
        $positionoffset = 0;
    } else {
        //if the old items are kept the new items will be appended
        //therefor the new position has an offset
        $positionoffset = $DB->count_records('survey_item', array('survey'=>$survey->id));
    }

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($templitems as $t_item) {
        $item = clone($t_item);
        unset($item->id);
        $item->survey = $survey->id;
        $item->template = 0;
        $item->position = $item->position + $positionoffset;

        $item->id = $DB->insert_record('survey_item', $item);

        //moving the files to the new item
        $templatefiles = $fs->get_area_files($s_context->id,
                                        'mod_survey',
                                        'template',
                                        $t_item->id,
                                        "id",
                                        false);
        if ($templatefiles) {
            foreach ($templatefiles as $tfile) {
                $file_record = new stdClass();
                $file_record->contextid = $f_context->id;
                $file_record->component = 'mod_survey';
                $file_record->filearea = 'item';
                $file_record->itemid = $item->id;
                $fs->create_file_from_storedfile($file_record, $tfile);
            }
        }

        $itembackup[$t_item->id] = $item->id;
        if ($item->dependitem) {
            $dependitemsmap[$item->id] = $item->dependitem;
        }
    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('survey_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('survey_item', $newitem);
    }
}

/**
 * get the list of available templates.
 * if the $onlyown param is set true so only templates from own course will be served
 * this is important for droping templates
 *
 * @global object
 * @param object $course
 * @param string $onlyownorpublic
 * @return array the template recordsets
 */
function survey_get_template_list($course, $onlyownorpublic = '') {
    global $DB, $CFG;

    switch($onlyownorpublic) {
        case '':
            $templates = $DB->get_records_select('survey_template',
                                                 'course = ? OR ispublic = 1',
                                                 array($course->id),
                                                 'name');
            break;
        case 'own':
            $templates = $DB->get_records('survey_template',
                                          array('course'=>$course->id),
                                          'name');
            break;
        case 'public':
            $templates = $DB->get_records('survey_template', array('ispublic'=>1), 'name');
            break;
    }
    return $templates;
}

////////////////////////////////////////////////
//Handling der Items
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * load the lib.php from item-plugin-dir and returns the instance of the itemclass
 *
 * @global object
 * @param object $item
 * @return object the instanz of itemclass
 */
function survey_get_item_class($typ) {
    global $CFG;

    //get the class of item-typ
    $itemclass = 'survey_item_'.$typ;
    //get the instance of item-class
    if (!class_exists($itemclass)) {
        require_once($CFG->dirroot.'/mod/survey/item/'.$typ.'/lib.php');
    }
    return new $itemclass();
}

/**
 * load the available item plugins from given subdirectory of $CFG->dirroot
 * the default is "mod/survey/item"
 *
 * @global object
 * @param string $dir the subdir
 * @return array pluginnames as string
 */
function survey_load_survey_items($dir = 'mod/survey/item') {
    global $CFG;
    $names = get_list_of_plugins($dir);
    $ret_names = array();

    foreach ($names as $name) {
        require_once($CFG->dirroot.'/'.$dir.'/'.$name.'/lib.php');
        if (class_exists('survey_item_'.$name)) {
            $ret_names[] = $name;
        }
    }
    return $ret_names;
}

/**
 * load the available item plugins to use as dropdown-options
 *
 * @global object
 * @return array pluginnames as string
 */
function survey_load_survey_items_options() {
    global $CFG;

    $survey_options = array("pagebreak" => get_string('add_pagebreak', 'survey'));

    if (!$survey_names = survey_load_survey_items('mod/survey/item')) {
        return array();
    }

    foreach ($survey_names as $fn) {
        $survey_options[$fn] = get_string($fn, 'survey');
    }
    asort($survey_options);
    $survey_options = array_merge( array(' ' => get_string('select')), $survey_options );
    return $survey_options;
}

/**
 * load the available items for the depend item dropdown list shown in the edit_item form
 *
 * @global object
 * @param object $survey
 * @param object $item the item of the edit_item form
 * @return array all items except the item $item, labels and pagebreaks
 */
function survey_get_depend_candidates_for_item($survey, $item) {
    global $DB;
    //all items for dependitem
    $where = "survey = ? AND typ != 'pagebreak' AND hasvalue = 1";
    $params = array($survey->id);
    if (isset($item->id) AND $item->id) {
        $where .= ' AND id != ?';
        $params[] = $item->id;
    }
    $dependitems = array(0 => get_string('choose'));
    $surveyitems = $DB->get_records_select_menu('survey_item',
                                                  $where,
                                                  $params,
                                                  'position',
                                                  'id, label');

    if (!$surveyitems) {
        return $dependitems;
    }
    //adding the choose-option
    foreach ($surveyitems as $key => $val) {
        $dependitems[$key] = $val;
    }
    return $dependitems;
}

/**
 * creates a new item-record
 *
 * @global object
 * @param object $data the data from edit_item_form
 * @return int the new itemid
 */
function survey_create_item($data) {
    global $DB;

    $item = new stdClass();
    $item->survey = $data->surveyid;

    $item->template=0;
    if (isset($data->templateid)) {
            $item->template = intval($data->templateid);
    }

    $itemname = trim($data->itemname);
    $item->name = ($itemname ? $data->itemname : get_string('no_itemname', 'survey'));

    if (!empty($data->itemlabel)) {
        $item->label = trim($data->itemlabel);
    } else {
        $item->label = get_string('no_itemlabel', 'survey');
    }

    $itemobj = survey_get_item_class($data->typ);
    $item->presentation = ''; //the date comes from postupdate() of the itemobj

    $item->hasvalue = $itemobj->get_hasvalue();

    $item->typ = $data->typ;
    $item->position = $data->position;

    $item->required=0;
    if (!empty($data->required)) {
        $item->required = $data->required;
    }

    $item->id = $DB->insert_record('survey_item', $item);

    //move all itemdata to the data
    $data->id = $item->id;
    $data->survey = $item->survey;
    $data->name = $item->name;
    $data->label = $item->label;
    $data->required = $item->required;
    return $itemobj->postupdate($data);
}

/**
 * save the changes of a given item.
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function survey_update_item($item) {
    global $DB;
    return $DB->update_record("survey_item", $item);
}

/**
 * deletes an item and also deletes all related values
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $itemid
 * @param boolean $renumber should the kept items renumbered Yes/No
 * @param object $template if the template is given so the items are bound to it
 * @return void
 */
function survey_delete_item($itemid, $renumber = true, $template = false) {
    global $DB;

    $item = $DB->get_record('survey_item', array('id'=>$itemid));

    //deleting the files from the item
    $fs = get_file_storage();

    if ($template) {
        if ($template->ispublic) {
            $context = get_system_context();
        } else {
            $context = get_context_instance(CONTEXT_COURSE, $template->course);
        }
        $templatefiles = $fs->get_area_files($context->id,
                                    'mod_survey',
                                    'template',
                                    $item->id,
                                    "id",
                                    false);

        if ($templatefiles) {
            $fs->delete_area_files($context->id, 'mod_survey', 'template', $item->id);
        }
    } else {
        if (!$cm = get_coursemodule_from_instance('survey', $item->survey)) {
            return false;
        }
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $itemfiles = $fs->get_area_files($context->id,
                                    'mod_survey',
                                    'item',
                                    $item->id,
                                    "id", false);

        if ($itemfiles) {
            $fs->delete_area_files($context->id, 'mod_survey', 'item', $item->id);
        }
    }

    $DB->delete_records("survey_value", array("item"=>$itemid));
    $DB->delete_records("survey_valuetmp", array("item"=>$itemid));

    //remove all depends
    $DB->set_field('survey_item', 'dependvalue', '', array('dependitem'=>$itemid));
    $DB->set_field('survey_item', 'dependitem', 0, array('dependitem'=>$itemid));

    $DB->delete_records("survey_item", array("id"=>$itemid));
    if ($renumber) {
        survey_renumber_items($item->survey);
    }
}

/**
 * deletes all items of the given surveyid
 *
 * @global object
 * @param int $surveyid
 * @return void
 */
function survey_delete_all_items($surveyid) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!$survey = $DB->get_record('survey', array('id'=>$surveyid))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance('survey', $survey->id)) {
        return false;
    }

    if (!$course = $DB->get_record('course', array('id'=>$survey->course))) {
        return false;
    }

    if (!$items = $DB->get_records('survey_item', array('survey'=>$surveyid))) {
        return;
    }
    foreach ($items as $item) {
        survey_delete_item($item->id, false);
    }
    if ($completeds = $DB->get_records('survey_completed', array('survey'=>$survey->id))) {
        $completion = new completion_info($course);
        foreach ($completeds as $completed) {
            // Update completion state
            if ($completion->is_enabled($cm) && $survey->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
            }
            $DB->delete_records('survey_completed', array('id'=>$completed->id));
        }
    }

    $DB->delete_records('survey_completedtmp', array('survey'=>$surveyid));

}

/**
 * this function toggled the item-attribute required (yes/no)
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function survey_switch_item_required($item) {
    global $DB, $CFG;

    $itemobj = survey_get_item_class($item->typ);

    if ($itemobj->can_switch_require()) {
        $new_require_val = (int)!(bool)$item->required;
        $params = array('id'=>$item->id);
        $DB->set_field('survey_item', 'required', $new_require_val, $params);
    }
    return true;
}

/**
 * renumbers all items of the given surveyid
 *
 * @global object
 * @param int $surveyid
 * @return void
 */
function survey_renumber_items($surveyid) {
    global $DB;

    $items = $DB->get_records('survey_item', array('survey'=>$surveyid), 'position');
    $pos = 1;
    if ($items) {
        foreach ($items as $item) {
            $DB->set_field('survey_item', 'position', $pos, array('id'=>$item->id));
            $pos++;
        }
    }
}

/**
 * this decreases the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function survey_moveup_item($item) {
    global $DB;

    if ($item->position == 1) {
        return true;
    }

    $params = array('survey'=>$item->survey);
    if (!$items = $DB->get_records('survey_item', $params, 'position')) {
        return false;
    }

    $itembefore = null;
    foreach ($items as $i) {
        if ($i->id == $item->id) {
            if (is_null($itembefore)) {
                return true;
            }
            $itembefore->position = $item->position;
            $item->position--;
            survey_update_item($itembefore);
            survey_update_item($item);
            survey_renumber_items($item->survey);
            return true;
        }
        $itembefore = $i;
    }
    return false;
}

/**
 * this increased the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function survey_movedown_item($item) {
    global $DB;

    $params = array('survey'=>$item->survey);
    if (!$items = $DB->get_records('survey_item', $params, 'position')) {
        return false;
    }

    $movedownitem = null;
    foreach ($items as $i) {
        if (!is_null($movedownitem) AND $movedownitem->id == $item->id) {
            $movedownitem->position = $i->position;
            $i->position--;
            survey_update_item($movedownitem);
            survey_update_item($i);
            survey_renumber_items($item->survey);
            return true;
        }
        $movedownitem = $i;
    }
    return false;
}

/**
 * here the position of the given item will be set to the value in $pos
 *
 * @global object
 * @param object $moveitem
 * @param int $pos
 * @return boolean
 */
function survey_move_item($moveitem, $pos) {
    global $DB;

    $params = array('survey'=>$moveitem->survey);
    if (!$allitems = $DB->get_records('survey_item', $params, 'position')) {
        return false;
    }
    if (is_array($allitems)) {
        $index = 1;
        foreach ($allitems as $item) {
            if ($index == $pos) {
                $index++;
            }
            if ($item->id == $moveitem->id) {
                $moveitem->position = $pos;
                survey_update_item($moveitem);
                continue;
            }
            $item->position = $index;
            survey_update_item($item);
            $index++;
        }
        return true;
    }
    return false;
}

/**
 * prints the given item as a preview.
 * each item-class has an own print_item_preview function implemented.
 *
 * @global object
 * @param object $item the item what we want to print out
 * @return void
 */
function survey_print_item_preview($item) {
    global $CFG;
    if ($item->typ == 'pagebreak') {
        return;
    }
    //get the instance of the item-class
    $itemobj = survey_get_item_class($item->typ);
    $itemobj->print_item_preview($item);
}

/**
 * prints the given item in the completion form.
 * each item-class has an own print_item_complete function implemented.
 *
 * @param object $item the item what we want to print out
 * @param mixed $value the value
 * @param boolean $highlightrequire if this set true and the value are false on completing so the item will be highlighted
 * @return void
 */
function survey_print_item_complete($item, $value = false, $highlightrequire = false) {
    global $CFG;
    if ($item->typ == 'pagebreak') {
        return;
    }

    //get the instance of the item-class
    $itemobj = survey_get_item_class($item->typ);
    $itemobj->print_item_complete($item, $value, $highlightrequire);
}

/**
 * prints the given item in the show entries page.
 * each item-class has an own print_item_show_value function implemented.
 *
 * @param object $item the item what we want to print out
 * @param mixed $value
 * @return void
 */
function survey_print_item_show_value($item, $value = false) {
    global $CFG;
    if ($item->typ == 'pagebreak') {
        return;
    }

    //get the instance of the item-class
    $itemobj = survey_get_item_class($item->typ);
    $itemobj->print_item_show_value($item, $value);
}

/**
 * if the user completes a survey and there is a pagebreak so the values are saved temporary.
 * the values are not saved permanently until the user click on save button
 *
 * @global object
 * @param object $surveycompleted
 * @return object temporary saved completed-record
 */
function survey_set_tmp_values($surveycompleted) {
    global $DB;

    //first we create a completedtmp
    $tmpcpl = new stdClass();
    foreach ($surveycompleted as $key => $value) {
        $tmpcpl->{$key} = $value;
    }
    unset($tmpcpl->id);
    $tmpcpl->timemodified = time();
    $tmpcpl->id = $DB->insert_record('survey_completedtmp', $tmpcpl);
    //get all values of original-completed
    if (!$values = $DB->get_records('survey_value', array('completed'=>$surveycompleted->id))) {
        return;
    }
    foreach ($values as $value) {
        unset($value->id);
        $value->completed = $tmpcpl->id;
        $DB->insert_record('survey_valuetmp', $value);
    }
    return $tmpcpl;
}

/**
 * this saves the temporary saved values permanently
 *
 * @global object
 * @param object $surveycompletedtmp the temporary completed
 * @param object $surveycompleted the target completed
 * @param int $userid
 * @return int the id of the completed
 */
function survey_save_tmp_values($surveycompletedtmp, $surveycompleted, $userid) {
    global $DB;

    $tmpcplid = $surveycompletedtmp->id;
    if ($surveycompleted) {
        //first drop all existing values
        $DB->delete_records('survey_value', array('completed'=>$surveycompleted->id));
        //update the current completed
        $surveycompleted->timemodified = time();
        $DB->update_record('survey_completed', $surveycompleted);
    } else {
        $surveycompleted = clone($surveycompletedtmp);
        $surveycompleted->id = '';
        $surveycompleted->userid = $userid;
        $surveycompleted->timemodified = time();
        $surveycompleted->id = $DB->insert_record('survey_completed', $surveycompleted);
    }

    //save all the new values from survey_valuetmp
    //get all values of tmp-completed
    $params = array('completed'=>$surveycompletedtmp->id);
    if (!$values = $DB->get_records('survey_valuetmp', $params)) {
        return false;
    }
    foreach ($values as $value) {
        //check if there are depend items
        $item = $DB->get_record('survey_item', array('id'=>$value->item));
        if ($item->dependitem > 0) {
            $check = survey_compare_item_value($tmpcplid,
                                        $item->dependitem,
                                        $item->dependvalue,
                                        true);
        } else {
            $check = true;
        }
        if ($check) {
            unset($value->id);
            $value->completed = $surveycompleted->id;
            $DB->insert_record('survey_value', $value);
        }
    }
    //drop all the tmpvalues
    $DB->delete_records('survey_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('survey_completedtmp', array('id'=>$tmpcplid));
    return $surveycompleted->id;

}

/**
 * deletes the given temporary completed and all related temporary values
 *
 * @global object
 * @param int $tmpcplid
 * @return void
 */
function survey_delete_completedtmp($tmpcplid) {
    global $DB;

    $DB->delete_records('survey_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('survey_completedtmp', array('id'=>$tmpcplid));
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the pagebreaks
////////////////////////////////////////////////

/**
 * this creates a pagebreak.
 * a pagebreak is a special kind of item
 *
 * @global object
 * @param int $surveyid
 * @return mixed false if there already is a pagebreak on last position or the id of the pagebreak-item
 */
function survey_create_pagebreak($surveyid) {
    global $DB;

    //check if there already is a pagebreak on the last position
    $lastposition = $DB->count_records('survey_item', array('survey'=>$surveyid));
    if ($lastposition == survey_get_last_break_position($surveyid)) {
        return false;
    }

    $item = new stdClass();
    $item->survey = $surveyid;

    $item->template=0;

    $item->name = '';

    $item->presentation = '';
    $item->hasvalue = 0;

    $item->typ = 'pagebreak';
    $item->position = $lastposition + 1;

    $item->required=0;

    return $DB->insert_record('survey_item', $item);
}

/**
 * get all positions of pagebreaks in the given survey
 *
 * @global object
 * @param int $surveyid
 * @return array all ordered pagebreak positions
 */
function survey_get_all_break_positions($surveyid) {
    global $DB;

    $params = array('typ'=>'pagebreak', 'survey'=>$surveyid);
    $allbreaks = $DB->get_records_menu('survey_item', $params, 'position', 'id, position');
    if (!$allbreaks) {
        return false;
    }
    return array_values($allbreaks);
}

/**
 * get the position of the last pagebreak
 *
 * @param int $surveyid
 * @return int the position of the last pagebreak
 */
function survey_get_last_break_position($surveyid) {
    if (!$allbreaks = survey_get_all_break_positions($surveyid)) {
        return false;
    }
    return $allbreaks[count($allbreaks) - 1];
}

/**
 * this returns the position where the user can continue the completing.
 *
 * @global object
 * @global object
 * @global object
 * @param int $surveyid
 * @param int $courseid
 * @param string $guestid this id will be saved temporary and is unique
 * @return int the position to continue
 */
function survey_get_page_to_continue($surveyid, $courseid = false, $guestid = false) {
    global $CFG, $USER, $DB;

    //is there any break?

    if (!$allbreaks = survey_get_all_break_positions($surveyid)) {
        return false;
    }

    $params = array();
    if ($courseid) {
        $courseselect = "AND fv.course_id = :courseid";
        $params['courseid'] = $courseid;
    } else {
        $courseselect = '';
    }

    if ($guestid) {
        $userselect = "AND fc.guestid = :guestid";
        $usergroup = "GROUP BY fc.guestid";
        $params['guestid'] = $guestid;
    } else {
        $userselect = "AND fc.userid = :userid";
        $usergroup = "GROUP BY fc.userid";
        $params['userid'] = $USER->id;
    }

    $sql =  "SELECT MAX(fi.position)
               FROM {survey_completedtmp} fc, {survey_valuetmp} fv, {survey_item} fi
              WHERE fc.id = fv.completed
                    $userselect
                    AND fc.survey = :surveyid
                    $courseselect
                    AND fi.id = fv.item
         $usergroup";
    $params['surveyid'] = $surveyid;

    $lastpos = $DB->get_field_sql($sql, $params);

    //the index of found pagebreak is the searched pagenumber
    foreach ($allbreaks as $pagenr => $br) {
        if ($lastpos < $br) {
            return $pagenr;
        }
    }
    return count($allbreaks);
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the values
////////////////////////////////////////////////

/**
 * this saves the values of an completed.
 * if the param $tmp is set true so the values are saved temporary in table survey_valuetmp.
 * if there is already a completed and the userid is set so the values are updated.
 * on all other things new value records will be created.
 *
 * @global object
 * @param int $userid
 * @param boolean $tmp
 * @return mixed false on error or the completeid
 */
function survey_save_values($usrid, $tmp = false) {
    global $DB;

    $completedid = optional_param('completedid', 0, PARAM_INT);

    $tmpstr = $tmp ? 'tmp' : '';
    $time = time();
    $timemodified = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));

    if ($usrid == 0) {
        return survey_create_values($usrid, $timemodified, $tmp);
    }
    $completed = $DB->get_record('survey_completed'.$tmpstr, array('id'=>$completedid));
    if (!$completed) {
        return survey_create_values($usrid, $timemodified, $tmp);
    } else {
        $completed->timemodified = $timemodified;
        return survey_update_values($completed, $tmp);
    }
}

/**
 * this saves the values from anonymous user such as guest on the main-site
 *
 * @global object
 * @param string $guestid the unique guestidentifier
 * @return mixed false on error or the completeid
 */
function survey_save_guest_values($guestid) {
    global $DB;

    $completedid = optional_param('completedid', false, PARAM_INT);

    $timemodified = time();
    if (!$completed = $DB->get_record('survey_completedtmp', array('id'=>$completedid))) {
        return survey_create_values(0, $timemodified, true, $guestid);
    } else {
        $completed->timemodified = $timemodified;
        return survey_update_values($completed, true);
    }
}

/**
 * get the value from the given item related to the given completed.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp
 *
 * @global object
 * @param int $completeid
 * @param int $itemid
 * @param boolean $tmp
 * @return mixed the value, the type depends on plugin-definition
 */
function survey_get_item_value($completedid, $itemid, $tmp = false) {
    global $DB;

    $tmpstr = $tmp ? 'tmp' : '';
    $params = array('completed'=>$completedid, 'item'=>$itemid);
    return $DB->get_field('survey_value'.$tmpstr, 'value', $params);
}

/**
 * compares the value of the itemid related to the completedid with the dependvalue.
 * this is used if a depend item is set.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp.
 *
 * @global object
 * @global object
 * @param int $completeid
 * @param int $itemid
 * @param mixed $dependvalue
 * @param boolean $tmp
 * @return bool
 */
function survey_compare_item_value($completedid, $itemid, $dependvalue, $tmp = false) {
    global $DB, $CFG;

    $dbvalue = survey_get_item_value($completedid, $itemid, $tmp);

    //get the class of the given item-typ
    $item = $DB->get_record('survey_item', array('id'=>$itemid));

    //get the instance of the item-class
    $itemobj = survey_get_item_class($item->typ);
    return $itemobj->compare_value($item, $dbvalue, $dependvalue); //true or false
}

/**
 * this function checks the correctness of values.
 * the rules for this are implemented in the class of each item.
 * it can be the required attribute or the value self e.g. numeric.
 * the params first/lastitem are given to determine the visible range between pagebreaks.
 *
 * @global object
 * @param int $firstitem the position of firstitem for checking
 * @param int $lastitem the position of lastitem for checking
 * @return boolean
 */
function survey_check_values($firstitem, $lastitem) {
    global $DB, $CFG;

    $surveyid = optional_param('surveyid', 0, PARAM_INT);

    //get all items between the first- and lastitem
    $select = "survey = ?
                    AND position >= ?
                    AND position <= ?
                    AND hasvalue = 1";
    $params = array($surveyid, $firstitem, $lastitem);
    if (!$surveyitems = $DB->get_records_select('survey_item', $select, $params)) {
        //if no values are given so no values can be wrong ;-)
        return true;
    }

    foreach ($surveyitems as $item) {
        //get the instance of the item-class
        $itemobj = survey_get_item_class($item->typ);

        //the name of the input field of the completeform is given in a special form:
        //<item-typ>_<item-id> eg. numeric_234
        //this is the key to get the value for the correct item
        $formvalname = $item->typ . '_' . $item->id;

        if ($itemobj->value_is_array()) {
            $value = optional_param_array($formvalname, null, $itemobj->value_type());
        } else {
            $value = optional_param($formvalname, null, $itemobj->value_type());
        }

        //check if the value is set
        if (is_null($value) AND $item->required == 1) {
            return false;
        }

        //now we let check the value by the item-class
        if (!$itemobj->check_value($value, $item)) {
            return false;
        }
    }
    //if no wrong values so we can return true
    return true;
}

/**
 * this function create a complete-record and the related value-records.
 * depending on the $tmp (true/false) the values are saved temporary or permanently
 *
 * @global object
 * @param int $userid
 * @param int $timemodified
 * @param boolean $tmp
 * @param string $guestid a unique identifier to save temporary data
 * @return mixed false on error or the completedid
 */
function survey_create_values($usrid, $timemodified, $tmp = false, $guestid = false) {
    global $DB;

    $surveyid = optional_param('surveyid', false, PARAM_INT);
    $anonymous_response = optional_param('anonymous_response', false, PARAM_INT);
    $courseid = optional_param('courseid', false, PARAM_INT);

    $tmpstr = $tmp ? 'tmp' : '';
    //first we create a new completed record
    $completed = new stdClass();
    $completed->survey           = $surveyid;
    $completed->userid             = $usrid;
    $completed->guestid            = $guestid;
    $completed->timemodified       = $timemodified;
    $completed->anonymous_response = $anonymous_response;

    $completedid = $DB->insert_record('survey_completed'.$tmpstr, $completed);

    $completed = $DB->get_record('survey_completed'.$tmpstr, array('id'=>$completedid));

    //the keys are in the form like abc_xxx
    //with explode we make an array with(abc, xxx) and (abc=typ und xxx=itemnr)

    //get the items of the survey
    if (!$allitems = $DB->get_records('survey_item', array('survey'=>$completed->survey))) {
        return false;
    }
    foreach ($allitems as $item) {
        if (!$item->hasvalue) {
            continue;
        }
        //get the class of item-typ
        $itemobj = survey_get_item_class($item->typ);

        $keyname = $item->typ.'_'.$item->id;

        if ($itemobj->value_is_array()) {
            $itemvalue = optional_param_array($keyname, null, $itemobj->value_type());
        } else {
            $itemvalue = optional_param($keyname, null, $itemobj->value_type());
        }

        if (is_null($itemvalue)) {
            continue;
        }

        $value = new stdClass();
        $value->item = $item->id;
        $value->completed = $completed->id;
        $value->course_id = $courseid;

        //the kind of values can be absolutely different
        //so we run create_value directly by the item-class
        $value->value = $itemobj->create_value($itemvalue);
        $DB->insert_record('survey_value'.$tmpstr, $value);
    }
    return $completed->id;
}

/**
 * this function updates a complete-record and the related value-records.
 * depending on the $tmp (true/false) the values are saved temporary or permanently
 *
 * @global object
 * @param object $completed
 * @param boolean $tmp
 * @return int the completedid
 */
function survey_update_values($completed, $tmp = false) {
    global $DB;

    $courseid = optional_param('courseid', false, PARAM_INT);
    $tmpstr = $tmp ? 'tmp' : '';

    $DB->update_record('survey_completed'.$tmpstr, $completed);
    //get the values of this completed
    $values = $DB->get_records('survey_value'.$tmpstr, array('completed'=>$completed->id));

    //get the items of the survey
    if (!$allitems = $DB->get_records('survey_item', array('survey'=>$completed->survey))) {
        return false;
    }
    foreach ($allitems as $item) {
        if (!$item->hasvalue) {
            continue;
        }
        //get the class of item-typ
        $itemobj = survey_get_item_class($item->typ);

        $keyname = $item->typ.'_'.$item->id;

        if ($itemobj->value_is_array()) {
            $itemvalue = optional_param_array($keyname, null, $itemobj->value_type());
        } else {
            $itemvalue = optional_param($keyname, null, $itemobj->value_type());
        }

        //is the itemvalue set (could be a subset of items because pagebreak)?
        if (is_null($itemvalue)) {
            continue;
        }

        $newvalue = new stdClass();
        $newvalue->item = $item->id;
        $newvalue->completed = $completed->id;
        $newvalue->course_id = $courseid;

        //the kind of values can be absolutely different
        //so we run create_value directly by the item-class
        $newvalue->value = $itemobj->create_value($itemvalue);

        //check, if we have to create or update the value
        $exist = false;
        foreach ($values as $value) {
            if ($value->item == $newvalue->item) {
                $newvalue->id = $value->id;
                $exist = true;
                break;
            }
        }
        if ($exist) {
            $DB->update_record('survey_value'.$tmpstr, $newvalue);
        } else {
            $DB->insert_record('survey_value'.$tmpstr, $newvalue);
        }
    }

    return $completed->id;
}

/**
 * get the values of an item depending on the given groupid.
 * if the survey is anonymous so the values are shuffled
 *
 * @global object
 * @global object
 * @param object $item
 * @param int $groupid
 * @param int $courseid
 * @param bool $ignore_empty if this is set true so empty values are not delivered
 * @return array the value-records
 */
function survey_get_group_values($item,
                                   $groupid = false,
                                   $courseid = false,
                                   $ignore_empty = false) {

    global $CFG, $DB;

    //if the groupid is given?
    if (intval($groupid) > 0) {
        if ($ignore_empty) {
            $ignore_empty_select = "AND fbv.value != '' AND fbv.value != '0'";
        } else {
            $ignore_empty_select = "";
        }

        $query = 'SELECT fbv .  *
                    FROM {survey_value} fbv, {survey_completed} fbc, {groups_members} gm
                   WHERE fbv.item = ?
                         AND fbv.completed = fbc.id
                         AND fbc.userid = gm.userid
                         '.$ignore_empty_select.'
                         AND gm.groupid = ?
                ORDER BY fbc.timemodified';
        $values = $DB->get_records_sql($query, array($item->id, $groupid));

    } else {
        if ($ignore_empty) {
            $ignore_empty_select = "AND value != '' AND value != '0'";
        } else {
            $ignore_empty_select = "";
        }

        if ($courseid) {
            $select = "item = ? AND course_id = ? ".$ignore_empty_select;
            $params = array($item->id, $courseid);
            $values = $DB->get_records_select('survey_value', $select, $params);
        } else {
            $select = "item = ? ".$ignore_empty_select;
            $params = array($item->id);
            $values = $DB->get_records_select('survey_value', $select, $params);
        }
    }
    $params = array('id'=>$item->survey);
    if ($DB->get_field('survey', 'anonymous', $params) == SURVEY_ANONYMOUS_YES) {
        if (is_array($values)) {
            shuffle($values);
        }
    }
    return $values;
}

/**
 * check for multiple_submit = false.
 * if the survey is global so the courseid must be given
 *
 * @global object
 * @global object
 * @param int $surveyid
 * @param int $courseid
 * @return boolean true if the survey already is submitted otherwise false
 */
function survey_is_already_submitted($surveyid, $courseid = false) {
    global $USER, $DB;

    $params = array('userid'=>$USER->id, 'survey'=>$surveyid);
    if (!$trackings = $DB->get_records_menu('survey_tracking', $params, '', 'id, completed')) {
        return false;
    }

    if ($courseid) {
        $select = 'completed IN ('.implode(',', $trackings).') AND course_id = ?';
        if (!$values = $DB->get_records_select('survey_value', $select, array($courseid))) {
            return false;
        }
    }

    return true;
}

/**
 * if the completion of a survey will be continued eg.
 * by pagebreak or by multiple submit so the complete must be found.
 * if the param $tmp is set true so all things are related to temporary completeds
 *
 * @global object
 * @global object
 * @global object
 * @param int $surveyid
 * @param boolean $tmp
 * @param int $courseid
 * @param string $guestid
 * @return int the id of the found completed
 */
function survey_get_current_completed($surveyid,
                                        $tmp = false,
                                        $courseid = false,
                                        $guestid = false) {

    global $USER, $CFG, $DB;

    $tmpstr = $tmp ? 'tmp' : '';

    if (!$courseid) {
        if ($guestid) {
            $params = array('survey'=>$surveyid, 'guestid'=>$guestid);
            return $DB->get_record('survey_completed'.$tmpstr, $params);
        } else {
            $params = array('survey'=>$surveyid, 'userid'=>$USER->id);
            return $DB->get_record('survey_completed'.$tmpstr, $params);
        }
    }

    $params = array();

    if ($guestid) {
        $userselect = "AND fc.guestid = :guestid";
        $params['guestid'] = $guestid;
    } else {
        $userselect = "AND fc.userid = :userid";
        $params['userid'] = $USER->id;
    }
    //if courseid is set the survey is global.
    //there can be more than one completed on one survey
    $sql =  "SELECT DISTINCT fc.*
               FROM {survey_value{$tmpstr}} fv, {survey_completed{$tmpstr}} fc
              WHERE fv.course_id = :courseid
                    AND fv.completed = fc.id
                    $userselect
                    AND fc.survey = :surveyid";
    $params['courseid']   = intval($courseid);
    $params['surveyid'] = $surveyid;

    if (!$sqlresult = $DB->get_records_sql($sql, $params)) {
        return false;
    }
    foreach ($sqlresult as $r) {
        return $DB->get_record('survey_completed'.$tmpstr, array('id'=>$r->id));
    }
}

/**
 * get the completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $survey
 * @param int $groupid
 * @param int $courseid
 * @return mixed array of found completeds otherwise false
 */
function survey_get_completeds_group($survey, $groupid = false, $courseid = false) {
    global $CFG, $DB;

    if (intval($groupid) > 0) {
        $query = "SELECT fbc.*
                    FROM {survey_completed} fbc, {groups_members} gm
                   WHERE fbc.survey = ?
                         AND gm.groupid = ?
                         AND fbc.userid = gm.userid";
        if ($values = $DB->get_records_sql($query, array($survey->id, $groupid))) {
            return $values;
        } else {
            return false;
        }
    } else {
        if ($courseid) {
            $query = "SELECT DISTINCT fbc.*
                        FROM {survey_completed} fbc, {survey_value} fbv
                        WHERE fbc.id = fbv.completed
                            AND fbc.survey = ?
                            AND fbv.course_id = ?
                        ORDER BY random_response";
            if ($values = $DB->get_records_sql($query, array($survey->id, $courseid))) {
                return $values;
            } else {
                return false;
            }
        } else {
            if ($values = $DB->get_records('survey_completed', array('survey'=>$survey->id))) {
                return $values;
            } else {
                return false;
            }
        }
    }
}

/**
 * get the count of completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $survey
 * @param int $groupid
 * @param int $courseid
 * @return mixed count of completeds or false
 */
function survey_get_completeds_group_count($survey, $groupid = false, $courseid = false) {
    global $CFG, $DB;

    if ($courseid > 0 AND !$groupid <= 0) {
        $sql = "SELECT id, COUNT(item) AS ci
                  FROM {survey_value}
                 WHERE course_id  = ?
              GROUP BY item ORDER BY ci DESC";
        if ($foundrecs = $DB->get_records_sql($sql, array($courseid))) {
            $foundrecs = array_values($foundrecs);
            return $foundrecs[0]->ci;
        }
        return false;
    }
    if ($values = survey_get_completeds_group($survey, $groupid)) {
        return count($values);
    } else {
        return false;
    }
}

/**
 * deletes all completed-recordsets from a survey.
 * all related data such as values also will be deleted
 *
 * @global object
 * @param int $surveyid
 * @return void
 */
function survey_delete_all_completeds($surveyid) {
    global $DB;

    if (!$completeds = $DB->get_records('survey_completed', array('survey'=>$surveyid))) {
        return;
    }
    foreach ($completeds as $completed) {
        survey_delete_completed($completed->id);
    }
}

/**
 * deletes a completed given by completedid.
 * all related data such values or tracking data also will be deleted
 *
 * @global object
 * @param int $completedid
 * @return boolean
 */
function survey_delete_completed($completedid) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!$completed = $DB->get_record('survey_completed', array('id'=>$completedid))) {
        return false;
    }

    if (!$survey = $DB->get_record('survey', array('id'=>$completed->survey))) {
        return false;
    }

    if (!$course = $DB->get_record('course', array('id'=>$survey->course))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance('survey', $survey->id)) {
        return false;
    }

    //first we delete all related values
    $DB->delete_records('survey_value', array('completed'=>$completed->id));

    //now we delete all tracking data
    $params = array('completed'=>$completed->id, 'survey'=>$completed->survey);
    if ($tracking = $DB->get_record('survey_tracking', $params)) {
        $DB->delete_records('survey_tracking', array('completed'=>$completed->id));
    }

    // Update completion state
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && $survey->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
    }
    //last we delete the completed-record
    return $DB->delete_records('survey_completed', array('id'=>$completed->id));
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle sitecourse mapping
////////////////////////////////////////////////

/**
 * checks if the course and the survey is in the table survey_sitecourse_map.
 *
 * @global object
 * @param int $surveyid
 * @param int $courseid
 * @return int the count of records
 */
function survey_is_course_in_sitecourse_map($surveyid, $courseid) {
    global $DB;
    $params = array('surveyid'=>$surveyid, 'courseid'=>$courseid);
    return $DB->count_records('survey_sitecourse_map', $params);
}

/**
 * checks if the survey is in the table survey_sitecourse_map.
 *
 * @global object
 * @param int $surveyid
 * @return boolean
 */
function survey_is_survey_in_sitecourse_map($surveyid) {
    global $DB;
    return $DB->record_exists('survey_sitecourse_map', array('surveyid'=>$surveyid));
}

/**
 * gets the surveys from table survey_sitecourse_map.
 * this is used to show the global surveys on the survey block
 * all surveys with the following criteria will be selected:<br />
 *
 * 1) all surveys which id are listed together with the courseid in sitecoursemap and<br />
 * 2) all surveys which not are listed in sitecoursemap
 *
 * @global object
 * @param int $courseid
 * @return array the survey-records
 */
function survey_get_surveys_from_sitecourse_map($courseid) {
    global $DB;

    //first get all surveys listed in sitecourse_map with named courseid
    $sql = "SELECT f.id AS id,
                   cm.id AS cmid,
                   f.name AS name,
                   f.timeopen AS timeopen,
                   f.timeclose AS timeclose
            FROM {survey} f, {course_modules} cm, {survey_sitecourse_map} sm, {modules} m
            WHERE f.id = cm.instance
                   AND f.course = '".SITEID."'
                   AND m.id = cm.module
                   AND m.name = 'survey'
                   AND sm.courseid = ?
                   AND sm.surveyid = f.id";

    if (!$surveys1 = $DB->get_records_sql($sql, array($courseid))) {
        $surveys1 = array();
    }

    //second get all surveys not listed in sitecourse_map
    $surveys2 = array();
    $sql = "SELECT f.id AS id,
                   cm.id AS cmid,
                   f.name AS name,
                   f.timeopen AS timeopen,
                   f.timeclose AS timeclose
            FROM {survey} f, {course_modules} cm, {modules} m
            WHERE f.id = cm.instance
                   AND f.course = '".SITEID."'
                   AND m.id = cm.module
                   AND m.name = 'survey'";
    if (!$allsurveys = $DB->get_records_sql($sql)) {
        $allsurveys = array();
    }
    foreach ($allsurveys as $a) {
        if (!$DB->record_exists('survey_sitecourse_map', array('surveyid'=>$a->id))) {
            $surveys2[] = $a;
        }
    }

    return array_merge($surveys1, $surveys2);

}

/**
 * gets the courses from table survey_sitecourse_map.
 *
 * @global object
 * @param int $surveyid
 * @return array the course-records
 */
function survey_get_courses_from_sitecourse_map($surveyid) {
    global $DB;

    $sql = "SELECT f.id, f.courseid, c.fullname, c.shortname
              FROM {survey_sitecourse_map} f, {course} c
             WHERE c.id = f.courseid
                   AND f.surveyid = ?
          ORDER BY c.fullname";

    return $DB->get_records_sql($sql, array($surveyid));

}

/**
 * removes non existing courses or surveys from sitecourse_map.
 * it shouldn't be called all too often
 * a good place for it could be the mapcourse.php or unmapcourse.php
 *
 * @global object
 * @return void
 */
function survey_clean_up_sitecourse_map() {
    global $DB;

    $maps = $DB->get_records('survey_sitecourse_map');
    foreach ($maps as $map) {
        if (!$DB->get_record('course', array('id'=>$map->courseid))) {
            $params = array('courseid'=>$map->courseid, 'surveyid'=>$map->surveyid);
            $DB->delete_records('survey_sitecourse_map', $params);
            continue;
        }
        if (!$DB->get_record('survey', array('id'=>$map->surveyid))) {
            $params = array('courseid'=>$map->courseid, 'surveyid'=>$map->surveyid);
            $DB->delete_records('survey_sitecourse_map', $params);
            continue;
        }

    }
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//not relatable functions
////////////////////////////////////////////////

/**
 * prints the option items of a selection-input item (dropdownlist).
 * @param int $startval the first value of the list
 * @param int $endval the last value of the list
 * @param int $selectval which item should be selected
 * @param int $interval the stepsize from the first to the last value
 * @return void
 */
function survey_print_numeric_option_list($startval, $endval, $selectval = '', $interval = 1) {
    for ($i = $startval; $i <= $endval; $i += $interval) {
        if ($selectval == ($i)) {
            $selected = 'selected="selected"';
        } else {
            $selected = '';
        }
        echo '<option '.$selected.'>'.$i.'</option>';
    }
}

/**
 * sends an email to the teachers of the course where the given survey is placed.
 *
 * @global object
 * @global object
 * @uses SURVEY_ANONYMOUS_NO
 * @uses FORMAT_PLAIN
 * @param object $cm the coursemodule-record
 * @param object $survey
 * @param object $course
 * @param int $userid
 * @return void
 */
function survey_send_email($cm, $survey, $course, $userid) {
    global $CFG, $DB;

    if ($survey->email_notification == 0) {  // No need to do anything
        return;
    }

    $user = $DB->get_record('user', array('id'=>$userid));

    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }

    if ($groupmode == SEPARATEGROUPS) {
        $groups = $DB->get_records_sql_menu("SELECT g.name, g.id
                                               FROM {groups} g, {groups_members} m
                                              WHERE g.courseid = ?
                                                    AND g.id = m.groupid
                                                    AND m.userid = ?
                                           ORDER BY name ASC", array($course->id, $userid));
        $groups = array_values($groups);

        $teachers = survey_get_receivemail_users($cm->id, $groups);
    } else {
        $teachers = survey_get_receivemail_users($cm->id);
    }

    if ($teachers) {

        $strsurveys = get_string('modulenameplural', 'survey');
        $strsurvey  = get_string('modulename', 'survey');
        $strcompleted  = get_string('completed', 'survey');

        if ($survey->anonymous == SURVEY_ANONYMOUS_NO) {
            $printusername = fullname($user);
        } else {
            $printusername = get_string('anonymous_user', 'survey');
        }

        foreach ($teachers as $teacher) {
            $info = new stdClass();
            $info->username = $printusername;
            $info->survey = format_string($survey->name, true);
            $info->url = $CFG->wwwroot.'/mod/survey/show_entries.php?'.
                            'id='.$cm->id.'&'.
                            'userid='.$userid.'&'.
                            'do_show=showentries';

            $postsubject = $strcompleted.': '.$info->username.' -> '.$survey->name;
            $posttext = survey_send_email_text($info, $course);

            if ($teacher->mailformat == 1) {
                $posthtml = survey_send_email_html($info, $course, $cm);
            } else {
                $posthtml = '';
            }

            if ($survey->anonymous == SURVEY_ANONYMOUS_NO) {
                $eventdata = new stdClass();
                $eventdata->name             = 'submission';
                $eventdata->component        = 'mod_survey';
                $eventdata->userfrom         = $user;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = '';
                message_send($eventdata);
            } else {
                $eventdata = new stdClass();
                $eventdata->name             = 'submission';
                $eventdata->component        = 'mod_survey';
                $eventdata->userfrom         = $teacher;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = '';
                message_send($eventdata);
            }
        }
    }
}

/**
 * sends an email to the teachers of the course where the given survey is placed.
 *
 * @global object
 * @uses FORMAT_PLAIN
 * @param object $cm the coursemodule-record
 * @param object $survey
 * @param object $course
 * @return void
 */
function survey_send_email_anonym($cm, $survey, $course) {
    global $CFG;

    if ($survey->email_notification == 0) { // No need to do anything
        return;
    }

    $teachers = survey_get_receivemail_users($cm->id);

    if ($teachers) {

        $strsurveys = get_string('modulenameplural', 'survey');
        $strsurvey  = get_string('modulename', 'survey');
        $strcompleted  = get_string('completed', 'survey');
        $printusername = get_string('anonymous_user', 'survey');

        foreach ($teachers as $teacher) {
            $info = new stdClass();
            $info->username = $printusername;
            $info->survey = format_string($survey->name, true);
            $info->url = $CFG->wwwroot.'/mod/survey/show_entries_anonym.php?id='.$cm->id;

            $postsubject = $strcompleted.': '.$info->username.' -> '.$survey->name;
            $posttext = survey_send_email_text($info, $course);

            if ($teacher->mailformat == 1) {
                $posthtml = survey_send_email_html($info, $course, $cm);
            } else {
                $posthtml = '';
            }

            $eventdata = new stdClass();
            $eventdata->name             = 'submission';
            $eventdata->component        = 'mod_survey';
            $eventdata->userfrom         = $teacher;
            $eventdata->userto           = $teacher;
            $eventdata->subject          = $postsubject;
            $eventdata->fullmessage      = $posttext;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml  = $posthtml;
            $eventdata->smallmessage     = '';
            message_send($eventdata);
        }
    }
}

/**
 * send the text-part of the email
 *
 * @param object $info includes some infos about the survey you want to send
 * @param object $course
 * @return string the text you want to post
 */
function survey_send_email_text($info, $course) {
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
    $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $posttext  = $courseshortname.' -> '.get_string('modulenameplural', 'survey').' -> '.
                    $info->survey."\n";
    $posttext .= '---------------------------------------------------------------------'."\n";
    $posttext .= get_string("emailteachermail", "survey", $info)."\n";
    $posttext .= '---------------------------------------------------------------------'."\n";
    return $posttext;
}


/**
 * send the html-part of the email
 *
 * @global object
 * @param object $info includes some infos about the survey you want to send
 * @param object $course
 * @return string the text you want to post
 */
function survey_send_email_html($info, $course, $cm) {
    global $CFG;
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
    $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $course_url = $CFG->wwwroot.'/course/view.php?id='.$course->id;
    $survey_all_url = $CFG->wwwroot.'/mod/survey/index.php?id='.$course->id;
    $survey_url = $CFG->wwwroot.'/mod/survey/view.php?id='.$cm->id;

    $posthtml = '<p><font face="sans-serif">'.
            '<a href="'.$course_url.'">'.$courseshortname.'</a> ->'.
            '<a href="'.$survey_all_url.'">'.get_string('modulenameplural', 'survey').'</a> ->'.
            '<a href="'.$survey_url.'">'.$info->survey.'</a></font></p>';
    $posthtml .= '<hr /><font face="sans-serif">';
    $posthtml .= '<p>'.get_string('emailteachermailhtml', 'survey', $info).'</p>';
    $posthtml .= '</font><hr />';
    return $posthtml;
}

/**
 * @param string $url
 * @return string
 */
function survey_encode_target_url($url) {
    if (strpos($url, '?')) {
        list($part1, $part2) = explode('?', $url, 2); //maximal 2 parts
        return $part1 . '?' . htmlentities($part2);
    } else {
        return $url;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $surveynode The node to add module settings to
 */
function survey_extend_settings_navigation(settings_navigation $settings,
                                             navigation_node $surveynode) {

    global $PAGE, $DB;

    if (!$context = get_context_instance(CONTEXT_MODULE, $PAGE->cm->id)) {
        print_error('badcontext');
    }

    if (has_capability('mod/survey:edititems', $context)) {
        $questionnode = $surveynode->add(get_string('questions', 'survey'));

        $questionnode->add(get_string('edit_items', 'survey'),
                    new moodle_url('/mod/survey/edit.php',
                                    array('id' => $PAGE->cm->id,
                                          'do_show' => 'edit')));

        $questionnode->add(get_string('export_questions', 'survey'),
                    new moodle_url('/mod/survey/export.php',
                                    array('id' => $PAGE->cm->id,
                                          'action' => 'exportfile')));

        $questionnode->add(get_string('import_questions', 'survey'),
                    new moodle_url('/mod/survey/import.php',
                                    array('id' => $PAGE->cm->id)));

        $questionnode->add(get_string('templates', 'survey'),
                    new moodle_url('/mod/survey/edit.php',
                                    array('id' => $PAGE->cm->id,
                                          'do_show' => 'templates')));
    }

    if (has_capability('mod/survey:viewreports', $context)) {
        $survey = $DB->get_record('survey', array('id'=>$PAGE->cm->instance));
        if ($survey->course == SITEID) {
            $surveynode->add(get_string('analysis', 'survey'),
                    new moodle_url('/mod/survey/analysis_course.php',
                                    array('id' => $PAGE->cm->id,
                                          'course' => $PAGE->course->id,
                                          'do_show' => 'analysis')));
        } else {
            $surveynode->add(get_string('analysis', 'survey'),
                    new moodle_url('/mod/survey/analysis.php',
                                    array('id' => $PAGE->cm->id,
                                          'course' => $PAGE->course->id,
                                          'do_show' => 'analysis')));
        }

        $surveynode->add(get_string('show_entries', 'survey'),
                    new moodle_url('/mod/survey/show_entries.php',
                                    array('id' => $PAGE->cm->id,
                                          'do_show' => 'showentries')));
    }
}

function survey_init_survey_session() {
    //initialize the survey-Session - not nice at all!!
    global $SESSION;
    if (!empty($SESSION)) {
        if (!isset($SESSION->survey) OR !is_object($SESSION->survey)) {
            $SESSION->survey = new stdClass();
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function survey_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-survey-*'=>get_string('page-mod-survey-x', 'survey'));
    return $module_pagetype;
}
