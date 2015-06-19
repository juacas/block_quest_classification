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
 * Form for editing quest_classification block instances.
 *
 * @package   block_quest_classification
 * @copyright 2013 Juan Pablo de Castro
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Definition of the Block form
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 */
class block_quest_classification_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $DB;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        if (!$this->block->get_owning_quest()) {
            $quests = $DB->get_records_menu('quest', array('course' => $this->page->course->id), '', 'id, name');
            if (empty($quests)) {
                $mform->addElement('static', 'no_quests_in_course',
                        get_string('error_emptyquestid', 'block_quest_classification'),
                        get_string('config_no_quests_in_course', 'block_quest_classification'));
            } else {
                foreach ($quests as $id => $name) {
                    $quests[$id] = strip_tags(format_string($name));
                }
                $mform->addElement('select', 'config_questid', get_string('config_select_quest', 'block_quest_classification'),
                        $quests);
            }
        }

        $mform->addElement('text', 'config_showbest', get_string('config_show_best', 'block_quest_classification'),
                array('size' => 3));
        $mform->setDefault('config_showbest', 3);
        $mform->setType('config_showbest', PARAM_INT);

        $mform->addElement('selectyesno', 'config_useteams', get_string('config_use_teams', 'block_quest_classification'));
    }
}
