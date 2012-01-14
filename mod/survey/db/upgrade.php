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

// This file keeps track of upgrades to
// the survey module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_survey_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2007012310) {
        //rename old survey table in survey_old
        $table = new xmldb_table('survey');
        $dbman->rename_table($table, 'survey_old');
        
        //rename old survey_analysis table in survey_analysis_old
        $table = new xmldb_table('survey_analysis');
        $dbman->rename_table($table, 'survey_analysis_old');
        
        //rename old survey_answers table in survey_answers_old
        $table = new xmldb_table('survey_answers');
        $dbman->rename_table($table, 'survey_answers_old');
        
        //rename old survey_questions table in survey_questions_old
        $table = new xmldb_table('survey_questions');
        $dbman->rename_table($table, 'survey_questions_old');
        
        //rename old feedback table in survey
        $table = new xmldb_table('feedback');
        $dbman->rename_table($table, 'survey');
        
        //rename old feedback_completed table in survey_completed
        $table = new xmldb_table('feedback_completed');
        $dbman->rename_table($table, 'survey_completed');
        
        //rename old feedback_completedtmp table in survey_completedtmp
        $table = new xmldb_table('feedback_completedtmp');
        $dbman->rename_table($table, 'survey_completedtmp');
        
        //rename old feedback_item table in survey_item
        $table = new xmldb_table('feedback_item');
        $dbman->rename_table($table, 'survey_item');
        
        //rename old feedback_sitecourse_map table in survey_sitecourse_map
        $table = new xmldb_table('feedback_sitecourse_map');
        $dbman->rename_table($table, 'survey_sitecourse_map');
        
        //rename old feedback_template table in survey_template
        $table = new xmldb_table('feedback_template');
        $dbman->rename_table($table, 'survey_template');
        
        //rename old feedback_tracking table in survey_tracking
        $table = new xmldb_table('feedback_tracking');
        $dbman->rename_table($table, 'survey_tracking');
        
        //rename old feedback_value table in survey_value
        $table = new xmldb_table('feedback_value');
        $dbman->rename_table($table, 'survey_value');
        
        //rename old feedback_valuetmp table in survey_valuetmp
        $table = new xmldb_table('feedback_valuetmp');
        $dbman->rename_table($table, 'survey_valuetmp');

        //switch the feedback-capabilities to survey-capabilities
        $capabilities_replaces = array(
            'mod/feedback:view'                 =>'mod/survey:view',
            'mod/feedback:complete'             =>'mod/survey:complete',
            'mod/feedback:viewanalysepage'      =>'mod/survey:viewanalysepage',
            'mod/feedback:deletesubmissions'    =>'mod/survey:deletesubmissions',
            'mod/feedback:mapcourse'            =>'mod/survey:mapcourse',
            'mod/feedback:edititems'            =>'mod/survey:edititems',
            'mod/feedback:createprivatetemplate'=>'mod/survey:createprivatetemplate',
            'mod/feedback:createpublictemplate' =>'mod/survey:createpublictemplate',
            'mod/feedback:deletetemplate'       =>'mod/survey:deletetemplate',
            'mod/feedback:viewreports'          =>'mod/survey:viewreports',
            'mod/feedback:receivemail'          =>'mod/survey:receivemail',
        )
        
        foreach ($capabilities_replaces as $search => $replace) {
            $cap = $DB->get_record('capabilities', array($name => $search, 'component' => 'mod_feedback'));
            if ($cap) {
                $cap->name = $replace;
                $cap->component = 'mod_survey';
                $DB->update_record('capabilities', $cap);
            }
            $rolecaps = $DB->get_records('role_capabilities', array($name => $search));
            if ($rolecaps) {
                foreach($rolecaps as $rc) {
                    $rc->capability = $replace;
                    $DB->update_record('role_capabilities', $rc);
                }
            }
        }
        
        upgrade_mod_savepoint(true, 2007012310, 'survey');
    }

    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this

    return true;
}


