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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_survey_activity_task
 */

/**
 * Structure step to restore one survey activity
 */
class restore_survey_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('survey', '/activity/survey');
        $paths[] = new restore_path_element('survey_item', '/activity/survey/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('survey_completed', '/activity/survey/completeds/completed');
            $paths[] = new restore_path_element('survey_value', '/activity/survey/completeds/completed/values/value');
            $paths[] = new restore_path_element('survey_tracking', '/activity/survey/trackings/tracking');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_survey($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the survey record
        $newitemid = $DB->insert_record('survey', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_survey_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->survey = $this->get_new_parentid('survey');

        //dependitem
        $data->dependitem = $this->get_mappingid('survey_item', $data->dependitem);

        $newitemid = $DB->insert_record('survey_item', $data);
        $this->set_mapping('survey_item', $oldid, $newitemid, true); // Can have files
    }

    protected function process_survey_completed($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->survey = $this->get_new_parentid('survey');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('survey_completed', $data);
        $this->set_mapping('survey_completed', $oldid, $newitemid);
    }

    protected function process_survey_value($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->completed = $this->get_new_parentid('survey_completed');
        $data->item = $this->get_mappingid('survey_item', $data->item);
        $data->course_id = $this->get_courseid();

        $newitemid = $DB->insert_record('survey_value', $data);
        $this->set_mapping('survey_value', $oldid, $newitemid);
    }

    protected function process_survey_tracking($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->survey = $this->get_new_parentid('survey');
        $data->completed = $this->get_mappingid('survey_completed', $data->completed);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('survey_tracking', $data);
    }


    protected function after_execute() {
        // Add survey related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_survey', 'intro', null);
        $this->add_related_files('mod_survey', 'item', 'survey_item');
    }
}
