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
 * prints the tabbed bar
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */
defined('MOODLE_INTERNAL') OR die('not allowed');

$tabs = array();
$row  = array();
$inactive = array();
$activated = array();

//some pages deliver the cmid instead the id
if (isset($cmid) AND intval($cmid) AND $cmid > 0) {
    $usedid = $cmid;
} else {
    $usedid = $id;
}

if (!$context = get_context_instance(CONTEXT_MODULE, $usedid)) {
        print_error('badcontext');
}


$courseid = optional_param('courseid', false, PARAM_INT);
// $current_tab = $SESSION->survey->current_tab;
if (!isset($current_tab)) {
    $current_tab = '';
}

$viewurl = new moodle_url('/mod/survey/view.php', array('id'=>$usedid, 'do_show'=>'view'));
$row[] = new tabobject('view', $viewurl->out(), get_string('overview', 'survey'));

if (has_capability('mod/survey:edititems', $context)) {
    $editurl = new moodle_url('/mod/survey/edit.php', array('id'=>$usedid, 'do_show'=>'edit'));
    $row[] = new tabobject('edit', $editurl->out(), get_string('edit_items', 'survey'));

    $templateurl = new moodle_url('/mod/survey/edit.php', array('id'=>$usedid, 'do_show'=>'templates'));
    $row[] = new tabobject('templates', $templateurl->out(), get_string('templates', 'survey'));
}

if (has_capability('mod/survey:viewreports', $context)) {
    if ($survey->course == SITEID) {
        $url_params = array('id'=>$usedid, 'courseid'=>$courseid, 'do_show'=>'analysis');
        $analysisurl = new moodle_url('/mod/survey/analysis_course.php', $url_params);
        $row[] = new tabobject('analysis',
                                $analysisurl->out(),
                                get_string('analysis', 'survey'));

    } else {
        $url_params = array('id'=>$usedid, 'courseid'=>$courseid, 'do_show'=>'analysis');
        $analysisurl = new moodle_url('/mod/survey/analysis.php', $url_params);
        $row[] = new tabobject('analysis',
                                $analysisurl->out(),
                                get_string('analysis', 'survey'));
    }

    $url_params = array('id'=>$usedid, 'do_show'=>'showentries');
    $reporturl = new moodle_url('/mod/survey/show_entries.php', $url_params);
    $row[] = new tabobject('showentries',
                            $reporturl->out(),
                            get_string('show_entries', 'survey'));

    if ($survey->anonymous == SURVEY_ANONYMOUS_NO AND $survey->course != SITEID) {
        $nonrespondenturl = new moodle_url('/mod/survey/show_nonrespondents.php', array('id'=>$usedid));
        $row[] = new tabobject('nonrespondents',
                                $nonrespondenturl->out(),
                                get_string('show_nonrespondents', 'survey'));
    }
}

if (count($row) > 1) {
    $tabs[] = $row;

    print_tabs($tabs, $current_tab, $inactive, $activated);
}

