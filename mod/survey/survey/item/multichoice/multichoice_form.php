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

require_once($CFG->dirroot.'/mod/survey/item/survey_item_form_class.php');

class survey_multichoice_form extends survey_item_form {
    protected $type = "multichoice";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'survey'));

        $mform->addElement('checkbox', 'required', get_string('required', 'survey'));

        $mform->addElement('text',
                            'name',
                            get_string('item_name', 'survey'),
                            array('size' => SURVEY_ITEM_NAME_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        $mform->addElement('text',
                            'label',
                            get_string('item_label', 'survey'),
                            array('size' => SURVEY_ITEM_LABEL_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        $mform->addElement('select',
                            'horizontal',
                            get_string('adjustment', 'survey').'&nbsp;',
                            array(0 => get_string('vertical', 'survey'),
                                  1 => get_string('horizontal', 'survey')));

        $mform->addElement('select',
                            'subtype',
                            get_string('multichoicetype', 'survey').'&nbsp;',
                            array('r'=>get_string('radio', 'survey'),
                                  'c'=>get_string('check', 'survey'),
                                  'd'=>get_string('dropdown', 'survey')));

        $mform->addElement('selectyesno',
                           'ignoreempty',
                           get_string('do_not_analyse_empty_submits', 'survey'));

        $mform->addElement('selectyesno',
                           'hidenoselect',
                           get_string('hide_no_select_option', 'survey'));

        $mform->addElement('static',
                           'hint',
                           get_string('multichoice_values', 'survey'),
                           get_string('use_one_line_for_each_value', 'survey'));

        $mform->addElement('textarea', 'values', '', 'wrap="virtual" rows="10" cols="65"');

        parent::definition();
        $this->set_data($item);

    }

    public function set_data($item) {
        $info = $this->_customdata['info'];

        $item->horizontal = $info->horizontal;

        $item->subtype = $info->subtype;

        $itemvalues = str_replace(SURVEY_MULTICHOICE_LINE_SEP, "\n", $info->presentation);
        $itemvalues = str_replace("\n\n", "\n", $itemvalues);
        $item->values = $itemvalues;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }

        $presentation = str_replace("\n", SURVEY_MULTICHOICE_LINE_SEP, trim($item->values));
        if (!isset($item->subtype)) {
            $subtype = 'r';
        } else {
            $subtype = substr($item->subtype, 0, 1);
        }
        if (isset($item->horizontal) AND $item->horizontal == 1 AND $subtype != 'd') {
            $presentation .= SURVEY_MULTICHOICE_ADJUST_SEP.'1';
        }

        $item->presentation = $subtype.SURVEY_MULTICHOICE_TYPE_SEP.$presentation;
        return $item;
    }
}
