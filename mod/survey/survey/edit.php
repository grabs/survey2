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
 * prints the form to edit the survey items such moving, deleting and so on
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package survey
 */

require_once("../../config.php");
require_once("lib.php");
require_once('edit_form.php');

survey_init_survey_session();

$id = required_param('id', PARAM_INT);

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

$do_show = optional_param('do_show', 'edit', PARAM_ALPHA);
$moveupitem = optional_param('moveupitem', false, PARAM_INT);
$movedownitem = optional_param('movedownitem', false, PARAM_INT);
$moveitem = optional_param('moveitem', false, PARAM_INT);
$movehere = optional_param('movehere', false, PARAM_INT);
$switchitemrequired = optional_param('switchitemrequired', false, PARAM_INT);

$current_tab = $do_show;

$url = new moodle_url('/mod/survey/edit.php', array('id'=>$id, 'do_show'=>$do_show));

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

require_capability('mod/survey:edititems', $context);

//move up/down items
if ($moveupitem) {
    $item = $DB->get_record('survey_item', array('id'=>$moveupitem));
    survey_moveup_item($item);
}
if ($movedownitem) {
    $item = $DB->get_record('survey_item', array('id'=>$movedownitem));
    survey_movedown_item($item);
}

//moving of items
if ($movehere && isset($SESSION->survey->moving->movingitem)) {
    $item = $DB->get_record('survey_item', array('id'=>$SESSION->survey->moving->movingitem));
    survey_move_item($item, intval($movehere));
    $moveitem = false;
}
if ($moveitem) {
    $item = $DB->get_record('survey_item', array('id'=>$moveitem));
    $SESSION->survey->moving->shouldmoving = 1;
    $SESSION->survey->moving->movingitem = $moveitem;
} else {
    unset($SESSION->survey->moving);
}

if ($switchitemrequired) {
    $item = $DB->get_record('survey_item', array('id'=>$switchitemrequired));
    @survey_switch_item_required($item);
    redirect($url->out(false));
    exit;
}

//the create_template-form
$create_template_form = new survey_edit_create_template_form();
$create_template_form->set_surveydata(array('context'=>$context, 'course'=>$course));
$create_template_form->set_form_elements();
$create_template_form->set_data(array('id'=>$id, 'do_show'=>'templates'));
$create_template_formdata = $create_template_form->get_data();
if (isset($create_template_formdata->savetemplate) && $create_template_formdata->savetemplate == 1) {
    //check the capabilities to create templates
    if (!has_capability('mod/survey:createprivatetemplate', $context) AND
            !has_capability('mod/survey:createpublictemplate', $context)) {
        print_error('cannotsavetempl', 'survey');
    }
    if (trim($create_template_formdata->templatename) == '') {
        $savereturn = 'notsaved_name';
    } else {
        //if the survey is located on the frontpage then templates can be public
        if (has_capability('mod/survey:createpublictemplate', get_system_context())) {
            $create_template_formdata->ispublic = isset($create_template_formdata->ispublic) ? 1 : 0;
        } else {
            $create_template_formdata->ispublic = 0;
        }
        if (!survey_save_as_template($survey,
                                      $create_template_formdata->templatename,
                                      $create_template_formdata->ispublic)) {
            $savereturn = 'failed';
        } else {
            $savereturn = 'saved';
        }
    }
}

//get the surveyitems
$lastposition = 0;
$surveyitems = $DB->get_records('survey_item', array('survey'=>$survey->id), 'position');
if (is_array($surveyitems)) {
    $surveyitems = array_values($surveyitems);
    if (count($surveyitems) > 0) {
        $lastitem = $surveyitems[count($surveyitems)-1];
        $lastposition = $lastitem->position;
    } else {
        $lastposition = 0;
    }
}
$lastposition++;


//the add_item-form
$add_item_form = new survey_edit_add_question_form('edit_item.php');
$add_item_form->set_data(array('cmid'=>$id, 'position'=>$lastposition));

//the use_template-form
$use_template_form = new survey_edit_use_template_form('use_templ.php');
$use_template_form->set_surveydata(array('course' => $course));
$use_template_form->set_form_elements();
$use_template_form->set_data(array('id'=>$id));

/// Print the page header
$strsurveys = get_string("modulenameplural", "survey");
$strsurvey  = get_string("modulename", "survey");

$PAGE->set_url('/mod/survey/edit.php', array('id'=>$cm->id, 'do_show'=>$do_show));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_title(format_string($survey->name));
echo $OUTPUT->header();

/// print the tabs
require('tabs.php');

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

$savereturn=isset($savereturn)?$savereturn:'';

//print the messages
if ($savereturn == 'notsaved_name') {
    echo '<p align="center"><b><font color="red">'.
          get_string('name_required', 'survey').
          '</font></b></p>';
}

if ($savereturn == 'saved') {
    echo '<p align="center"><b><font color="green">'.
          get_string('template_saved', 'survey').
          '</font></b></p>';
}

if ($savereturn == 'failed') {
    echo '<p align="center"><b><font color="red">'.
          get_string('saving_failed', 'survey').
          '</font></b></p>';
}

///////////////////////////////////////////////////////////////////////////
///print the template-section
///////////////////////////////////////////////////////////////////////////
if ($do_show == 'templates') {
    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
    $use_template_form->display();

    if (has_capability('mod/survey:createprivatetemplate', $context) OR
                has_capability('mod/survey:createpublictemplate', $context)) {
        $deleteurl = new moodle_url('/mod/survey/delete_template.php', array('id' => $id));
        $create_template_form->display();
        echo '<p><a href="'.$deleteurl->out().'">'.
             get_string('delete_templates', 'survey').
             '</a></p>';
    } else {
        echo '&nbsp;';
    }

    if (has_capability('mod/survey:edititems', $context)) {
        $urlparams = array('action'=>'exportfile', 'id'=>$id);
        $exporturl = new moodle_url('/mod/survey/export.php', $urlparams);
        $importurl = new moodle_url('/mod/survey/import.php', array('id'=>$id));
        echo '<p>
            <a href="'.$exporturl->out().'">'.get_string('export_questions', 'survey').'</a>/
            <a href="'.$importurl->out().'">'.get_string('import_questions', 'survey').'</a>
        </p>';
    }
    echo $OUTPUT->box_end();
}
///////////////////////////////////////////////////////////////////////////
///print the Item-Edit-section
///////////////////////////////////////////////////////////////////////////
if ($do_show == 'edit') {

    $add_item_form->display();

    if (is_array($surveyitems)) {
        $itemnr = 0;

        $align = right_to_left() ? 'right' : 'left';

        $helpbutton = $OUTPUT->help_icon('preview', 'survey');

        echo $OUTPUT->heading($helpbutton . get_string('preview', 'survey'));
        if (isset($SESSION->survey->moving) AND $SESSION->survey->moving->shouldmoving == 1) {
            $anker = '<a href="edit.php?id='.$id.'">';
            $anker .= get_string('cancel_moving', 'survey');
            $anker .= '</a>';
            echo $OUTPUT->heading($anker);
        }

        //check, if there exists required-elements
        $params = array('survey' => $survey->id, 'required' => 1);
        $countreq = $DB->count_records('survey_item', $params);
        if ($countreq > 0) {
            echo '<span class="survey_required_mark">(*)';
            echo get_string('items_are_required', 'survey');
            echo '</span>';
        }

        //use list instead a table
        echo $OUTPUT->box_start('survey_items');
        if (isset($SESSION->survey->moving) AND $SESSION->survey->moving->shouldmoving == 1) {
            $moveposition = 1;
            $movehereurl = new moodle_url($url, array('movehere'=>$moveposition));
            //only shown if shouldmoving = 1
            echo $OUTPUT->box_start('survey_item_box_'.$align.' clipboard');
            $buttonlink = $movehereurl->out();
            $strbutton = get_string('move_here', 'survey');
            $src = $OUTPUT->pix_url('movehere');
            echo '<a title="'.$strbutton.'" href="'.$buttonlink.'">
                    <img class="movetarget" alt="'.$strbutton.'" src="'.$src.'" />
                  </a>';
            echo $OUTPUT->box_end();
        }
        //print the inserted items
        $itempos = 0;
        foreach ($surveyitems as $surveyitem) {
            $itempos++;
            //hiding the item to move
            if (isset($SESSION->survey->moving)) {
                if ($SESSION->survey->moving->movingitem == $surveyitem->id) {
                    continue;
                }
            }
            if ($surveyitem->dependitem > 0) {
                $dependstyle = ' survey_depend';
            } else {
                $dependstyle = '';
            }
            echo $OUTPUT->box_start('survey_item_box_'.$align.$dependstyle);
            //items without value only are labels
            if ($surveyitem->hasvalue == 1 AND $survey->autonumbering) {
                $itemnr++;
                echo $OUTPUT->box_start('survey_item_number_'.$align);
                echo $itemnr;
                echo $OUTPUT->box_end();
            }
            echo $OUTPUT->box_start('box generalbox boxalign_'.$align);
            echo $OUTPUT->box_start('survey_item_commands_'.$align);
            echo '<span class="survey_item_commands">';
            echo '('.get_string('position', 'survey').':'.$itempos .')';
            echo '</span>';
            //print the moveup-button
            if ($surveyitem->position > 1) {
                echo '<span class="survey_item_command_moveup">';
                $moveupurl = new moodle_url($url, array('moveupitem'=>$surveyitem->id));
                $buttonlink = $moveupurl->out();
                $strbutton = get_string('moveup_item', 'survey');
                echo '<a class="icon up" title="'.$strbutton.'" href="'.$buttonlink.'">
                        <img alt="'.$strbutton.'" src="'.$OUTPUT->pix_url('t/up') . '" />
                      </a>';
                echo '</span>';
            }
            //print the movedown-button
            if ($surveyitem->position < $lastposition - 1) {
                echo '<span class="survey_item_command_movedown">';
                $urlparams = array('movedownitem'=>$surveyitem->id);
                $movedownurl = new moodle_url($url, $urlparams);
                $buttonlink = $movedownurl->out();
                $strbutton = get_string('movedown_item', 'survey');
                echo '<a class="icon down" title="'.$strbutton.'" href="'.$buttonlink.'">
                        <img alt="'.$strbutton.'" src="'.$OUTPUT->pix_url('t/down') . '" />
                      </a>';
                echo '</span>';
            }
            //print the move-button
            echo '<span class="survey_item_command_move">';
            $moveurl = new moodle_url($url, array('moveitem'=>$surveyitem->id));
            $buttonlink = $moveurl->out();
            $strbutton = get_string('move_item', 'survey');
            echo '<a class="editing_move" title="'.$strbutton.'" href="'.$buttonlink.'">
                    <img alt="'.$strbutton.'" src="'.$OUTPUT->pix_url('t/move') . '" />
                  </a>';
            echo '</span>';
            //print the button to edit the item
            if ($surveyitem->typ != 'pagebreak') {
                echo '<span class="survey_item_command_edit">';
                $editurl = new moodle_url('/mod/survey/edit_item.php');
                $editurl->params(array('do_show'=>$do_show,
                                         'cmid'=>$id,
                                         'id'=>$surveyitem->id,
                                         'typ'=>$surveyitem->typ));

                // in edit_item.php the param id is used for the itemid
                // and the cmid is the id to get the module
                $buttonlink = $editurl->out();
                $strbutton = get_string('edit_item', 'survey');
                echo '<a class="editing_update" title="'.$strbutton.'" href="'.$buttonlink.'">
                        <img alt="'.$strbutton.'" src="'.$OUTPUT->pix_url('t/edit') . '" />
                      </a>';
                echo '</span>';
            }

            //print the toggle-button to switch required yes/no
            if ($surveyitem->hasvalue == 1) {
                echo '<span class="survey_item_command_toggle">';
                if ($surveyitem->required == 1) {
                    $buttontitle = get_string('switch_item_to_not_required', 'survey');
                    $buttonimg = 'pics/required.gif';
                } else {
                    $buttontitle = get_string('switch_item_to_required', 'survey');
                    $buttonimg = 'pics/notrequired.gif';
                }
                $urlparams = array('switchitemrequired'=>$surveyitem->id);
                $requiredurl = new moodle_url($url, $urlparams);
                $buttonlink = $requiredurl->out();
                echo '<a class="icon '.
                        'survey_switchrequired" '.
                        'title="'.$buttontitle.'" '.
                        'href="'.$buttonlink.'">'.
                        '<img alt="'.$buttontitle.'" src="'.$buttonimg.'" />'.
                        '</a>';
                echo '</span>';
            }

            //print the delete-button
            echo '<span class="survey_item_command_toggle">';
            $deleteitemurl = new moodle_url('/mod/survey/delete_item.php');
            $deleteitemurl->params(array('id'=>$id,
                                         'do_show'=>$do_show,
                                         'deleteitem'=>$surveyitem->id));

            $buttonlink = $deleteitemurl->out();
            $strbutton = get_string('delete_item', 'survey');
            $src = $OUTPUT->pix_url('t/delete');
            echo '<a class="icon delete" title="'.$strbutton.'" href="'.$buttonlink.'">
                    <img alt="'.$strbutton.'" src="'.$src.'" />
                  </a>';
            echo '</span>';
            echo $OUTPUT->box_end();
            if ($surveyitem->typ != 'pagebreak') {
                survey_print_item_preview($surveyitem);
            } else {
                echo $OUTPUT->box_start('survey_pagebreak');
                echo get_string('pagebreak', 'survey').'<hr class="survey_pagebreak" />';
                echo $OUTPUT->box_end();
            }
            echo $OUTPUT->box_end();
            echo $OUTPUT->box_end();
            if (isset($SESSION->survey->moving) AND $SESSION->survey->moving->shouldmoving == 1) {
                $moveposition++;
                $movehereurl->param('movehere', $moveposition);
                echo $OUTPUT->box_start('clipboard'); //only shown if shouldmoving = 1
                $buttonlink = $movehereurl->out();
                $strbutton = get_string('move_here', 'survey');
                $src = $OUTPUT->pix_url('movehere');
                echo '<a title="'.$strbutton.'" href="'.$buttonlink.'">
                        <img class="movetarget" alt="'.$strbutton.'" src="'.$src.'" />
                      </a>';
                echo $OUTPUT->box_end();
            }
            echo '<div class="clearer">&nbsp;</div>';
        }
        echo $OUTPUT->box_end();
    } else {
        echo $OUTPUT->box(get_string('no_items_available_yet', 'survey'),
                         'generalbox boxaligncenter');
    }
}
/// Finish the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

echo $OUTPUT->footer();
