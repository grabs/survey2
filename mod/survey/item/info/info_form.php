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

class survey_info_form extends survey_item_form {
    protected $type = "info";

    public function definition() {

        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'survey'));
        $mform->addElement('hidden', 'required', 0);
        $mform->setType('required', PARAM_INT);

        $mform->addElement('text',
                            'name',
                            get_string('item_name', 'survey'),
                            array('size'=>SURVEY_ITEM_NAME_TEXTBOX_SIZE, 'maxlength'=>255));
        $mform->addElement('text',
                            'label',
                            get_string('item_label', 'survey'),
                            array('size'=>SURVEY_ITEM_LABEL_TEXTBOX_SIZE, 'maxlength'=>255));

        $options=array();
        $options[1]  = get_string('responsetime', 'survey');
        $options[2]  = get_string('course');
        $options[3]  = get_string('coursecategory');
        $this->infotype = &$mform->addElement('select',
                                              'presentation',
                                              get_string('infotype', 'survey'),
                                              $options);

        parent::definition();
        $this->set_data($item);

    }
}

