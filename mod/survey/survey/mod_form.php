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
 * print the form to add or edit a survey-instance
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

//It must be included from a Moodle page
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_survey_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB;

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'survey'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true, get_string('description', 'survey'));

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));

        $enableopengroup = array();
        $enableopengroup[] =& $mform->createElement('checkbox',
                                    'openenable',
                                    get_string('surveyopen', 'survey'));

        $enableopengroup[] =& $mform->createElement('date_time_selector', 'timeopen', '');
        $mform->addGroup($enableopengroup,
                         'enableopengroup',
                         get_string('surveyopen', 'survey'),
                         ' ',
                         false);

        $mform->addHelpButton('enableopengroup', 'timeopen', 'survey');
        $mform->disabledIf('enableopengroup', 'openenable', 'notchecked');

        $enableclosegroup = array();
        $enableclosegroup[] =& $mform->createElement('checkbox',
                                        'closeenable',
                                        get_string('surveyclose', 'survey'));

        $enableclosegroup[] =& $mform->createElement('date_time_selector', 'timeclose', '');
        $mform->addGroup($enableclosegroup,
                         'enableclosegroup',
                         get_string('surveyclose', 'survey'),
                         ' ',
                         false);

        $mform->addHelpButton('enableclosegroup', 'timeclose', 'survey');
        $mform->disabledIf('enableclosegroup', 'closeenable', 'notchecked');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'surveyhdr', get_string('survey_options', 'survey'));

        $options=array();
        $options[1]  = get_string('anonymous', 'survey');
        $options[2]  = get_string('non_anonymous', 'survey');
        $mform->addElement('select',
                           'anonymous',
                           get_string('anonymous_edit', 'survey'),
                           $options);

        $mform->addElement('selectyesno',
                           'publish_stats',
                           get_string('show_analysepage_after_submit', 'survey'));

        $mform->addElement('selectyesno',
                           'email_notification',
                           get_string('email_notification', 'survey'));

        $mform->addHelpButton('email_notification', 'emailnotification', 'survey');

        // check if there is existing responses to this survey
        if (is_numeric($this->_instance) AND
                    $this->_instance AND
                    $survey = $DB->get_record("survey", array("id"=>$this->_instance))) {

            $completed_survey_count = survey_get_completeds_group_count($survey);
        } else {
            $completed_survey_count = false;
        }

        if ($completed_survey_count) {
            $multiple_submit_value = $survey->multiple_submit ? get_string('yes') : get_string('no');
            $mform->addElement('text',
                               'multiple_submit_static',
                               get_string('multiple_submit', 'survey'),
                               array('size'=>'4',
                                    'disabled'=>'disabled',
                                    'value'=>$multiple_submit_value));

            $mform->addElement('hidden', 'multiple_submit', '');
            $mform->setType('', PARAM_INT);
            $mform->addHelpButton('multiple_submit_static', 'multiplesubmit', 'survey');
        } else {
            $mform->addElement('selectyesno',
                               'multiple_submit',
                               get_string('multiple_submit', 'survey'));

            $mform->addHelpButton('multiple_submit', 'multiplesubmit', 'survey');
        }
        $mform->addElement('selectyesno', 'autonumbering', get_string('autonumbering', 'survey'));
        $mform->addHelpButton('autonumbering', 'autonumbering', 'survey');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'aftersubmithdr', get_string('after_submit', 'survey'));

        $mform->addElement('editor',
                           'page_after_submit',
                           get_string("page_after_submit", "survey"),
                           null,
                           null);

        $mform->setType('page_after_submit', PARAM_RAW);

        $mform->addElement('text',
                           'site_after_submit',
                           get_string('url_for_continue_button', 'survey'),
                           array('size'=>'64', 'maxlength'=>'255'));

        $mform->setType('site_after_submit', PARAM_TEXT);
        $mform->addHelpButton('site_after_submit', 'url_for_continue', 'survey');
        //-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {
        if (empty($default_values['timeopen'])) {
            $default_values['openenable'] = 0;
        } else {
            $default_values['openenable'] = 1;
        }
        if (empty($default_values['timeclose'])) {
            $default_values['closeenable'] = 0;
        } else {
            $default_values['closeenable'] = 1;
        }
        if (!isset($default_values['page_after_submitformat'])) {
            $default_values['page_after_submitformat'] = FORMAT_HTML;
        }
        if (!isset($default_values['page_after_submit'])) {
            $default_values['page_after_submit'] = '';
        }
        $default_values['page_after_submit'] = array('text'=>$default_values['page_after_submit'],
                                                    'format'=>$default_values['page_after_submitformat']);
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->page_after_submitformat = $data->page_after_submit['format'];
            $data->page_after_submit = $data->page_after_submit['text'];

            // Turn off completion settings if the checkboxes aren't ticked
            $autocompletion = !empty($data->completion) AND
                                    $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completion) || !$autocompletion) {
                $data->completionsubmit=0;
            }
            if (empty($data->completionsubmit)) {
                $data->completionsubmit=0;
            }
        }

        return $data;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox',
                           'completionsubmit',
                           '',
                           get_string('completionsubmit', 'survey'));
        return array('completionsubmit');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}
