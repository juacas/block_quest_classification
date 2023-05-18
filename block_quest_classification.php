<?php
// This file is part of Questournament activity for Moodle http://moodle.org/
//
// Questournament for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Questournament for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Questournament for Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * block_quest_classification
 *
 * Questournament activity for Moodle
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @package block_quest_classification
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright  2007-13 Eduvalab University of Valladolid http://www.eduvalab.uva.es
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
/**
 * Definition of the Block
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, Eduvalab  http://www.eduvalab.uva.es
 */
class block_quest_classification extends block_base {

    /**
     * Initialization code
     */
    public function init() {
        $this->title = get_string('classificationquest', 'block_quest_classification');
    }

    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * {@link blocks_name_allowed_in_format()} function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    public function applicable_formats() {
        return array('course' => true, 'mod-quest' => true);
    }

    /**
     * If this block belongs to a quest context, then return that quest's id.
     * Otherwise, return 0.
     * @return integer the quest id.
     */
    public function get_owning_quest() {
        if (empty($this->instance->parentcontextid)) {
            return 0;
        }
        $parentcontext = context::instance_by_id($this->instance->parentcontextid);
        if ($parentcontext->contextlevel != CONTEXT_MODULE) {
            return 0;
        }
        $cm = get_coursemodule_from_id('quest', $parentcontext->instanceid);
        if (!$cm) {
            return 0;
        }
        return $cm->instance;
    }
    /**
     * Serialize and store config data
     * @param type $data
     * @param type $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        if (empty($data->questid)) {
            $data->questid = $this->get_owning_quest();
        }
        parent::instance_config_save($data);
    }

    /**
     * return the content object.
     *
     * @return stdObject
     */
    public function get_content() {
        global $USER, $CFG, $DB;
        $string = '';

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        if ($this->page->activityname == 'quest' && $this->page->context->id == $this->instance->parentcontextid) {
            $quest = $this->page->activityrecord;
            $questid = $quest->id;
            $courseid = $this->page->course->id;
            $inquest = true;
        } else if (!empty($this->config->questid)) {

            $questid = $this->config->questid;

            $quest = $DB->get_record('quest', array('id' => $questid));

            if (empty($quest)) {
                $this->content->text = get_string('error_emptyquestrecord', 'block_quest_classification');
                return $this->content;
            }
            $courseid = $quest->course;
            $inquest = false;
        } else {
            $questid = 0;
        }

        if (empty($questid)) {
            $this->content->text = get_string('error_emptyquestid', 'block_quest_classification');
            return $this->content;
        }

        if (empty($this->config->showbest)) {
            $this->content->text = get_string('configuredtoshownothing', 'block_quest_classification');
            return $this->content;
        }
        $groupmode = NOGROUPS;
        $best = array();

        // The block was configured to operate in group mode.
        $course = $this->page->course;

        if ($course->groupmodeforce) {
            $groupmode = $course->groupmode;
        } else {
            $module = get_coursemodule_from_instance('quest', $questid);
            $groupmode = $module->groupmode;
        }

        $canaccessgroups = has_capability('moodle/site:accessallgroups', $this->page->context);
        // The actual groupmode for the quest is now known to be $groupmode.
        if (!$canaccessgroups) {
            $groupid = groups_get_course_group($course);
        }
        $groupmode = $groupid = false; // JPC group support desactivation.

        if (($this->config->useteams == 0) || ($quest->allowteams == 0)) {
            $actionclasification = 'global';
        } else if ($this->config->useteams == 1) {
            $actionclasification = 'teams';
        }

        $nstudents = $this->config->showbest;
        $this->title = get_string('namequest', 'block_quest_classification', $quest);

        if ($canaccessgroups && ($groupmode != 0)) {
            $string .= substr($this->print_simple_calification($quest, $course, $groupid, $actionclasification, $nstudents), 5);
            $this->content->text = $string;
            return $this->content;
        } else {
            if ($canaccessgroups) {
                $groupid = 0;
            }
            $listing = $this->print_simple_calification($quest, $course, $groupid, $actionclasification, $nstudents);
            $this->content->text = $listing;
            return $this->content;
        }
    }

    /**
     * Are you going to allow multiple instances of this block as there may be many questournaments in a course
     * @return boolean
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Generates a simple report about califications in the contest.
     *
     * @param stdClass $quest
     * @param stdClass $course
     * @param type $currentgroup
     * @param type $actionclasification 'global' or 'teams'
     * @param type $nstudents
     * @return string Table with the students' scores
     */
    public function print_simple_calification(stdClass $quest, stdClass $course, $currentgroup, $actionclasification, $nstudents) {
        global $CFG, $OUTPUT, $DB;
        $string = '';
        $dir = '';
        $sort = '';
        $users = $this->block_quest_get_course_members($course->id, "u.lastname, u.firstname");
        if (!$users) {
            return '----';
        }

        // Now prepare table with student.
        $tablesort = new stdClass();
        $tablesort->data = array();
        $tablesort->sortdata = array();
        $calificationusers = array();
        $calificationteams = array();
        $indice = 0;

        if ($actionclasification == 'global') {
            if ($califications = $this->block_quest_get_calification($quest)) {
                foreach ($califications as $calification) {
                    // ...skip if student not in group.
                    if ($currentgroup) {
                        if (!ismember($currentgroup, $calification->userid)) {
                            continue;
                        }
                    }
                    $calificationusers[] = $calification;
                    $indice++;
                }
            }
            for ($i = 0; $i < $indice; $i++) {
                foreach ($users as $user) {
                    if ($user->id == $calificationusers[$i]->userid) {
                        break;
                    }
                }
                $points = 0;
                $data = array();
                $sortdata = array();

                $data[] = $OUTPUT->user_picture($user, array('courseid' => $course->id, 'link' => true));
                $data[] = fullname($user);
                $sortdata['user'] = fullname($user);

                if ($quest->allowteams) {
                    if ($clasificationteam = $DB->get_record("quest_calification_teams",
                            array('teamid' => $calificationusers[$i]->teamid, 'questid' => $quest->id))) {

                        $points = $calificationusers[$i]->points + $clasificationteam->points * $quest->teamporcent / 100;
                    }
                } else {
                    $points = $calificationusers[$i]->points;
                }

                $points = number_format($points, 4);
                $data[] = $points;
                $sortdata['calification'] = $points;

                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
            uasort($tablesort->sortdata, array($this, 'sort_by_grade'));
            $table = new html_table();
            $table->data = array();
            $count = 0;
            foreach ($tablesort->sortdata as $key => $row) {
                $count++;
                if ($count > $nstudents) {
                    break;
                }
                $table->data[] = $tablesort->data[$key];
            }
            $table->align = array('left', 'left', 'center', 'center', 'center', 'center',
                                 'center', 'center', 'center', 'center', 'center');
            $string = [];
            $columns = array('picture', 'user', 'calification');
            $table->width = "95%";
            foreach ($columns as $column) {
                $string[$column] = get_string("$column", 'quest');
                if ($sort != $column) {
                    $columnicon = '';
                    $columndir = 'ASC';
                } else {
                    $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
                    if ($column == 'lastaccess') {
                        $columnicon = $dir == 'ASC' ? 'up' : 'down';
                    } else {
                        $columnicon = $dir == 'ASC' ? 'down' : 'up';
                    }
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"$columnicon\" />";
                }

                $$column = "<a href=\"view.php?sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
            }

            $table->head = array(get_string('user', 'block_quest_classification'), get_string('calification',
                        'block_quest_classification')
                    );
            $table->headspan = array(2, 1);
        } else if ($actionclasification == 'teams') {

            $teamstemp = array();
            if ($teams = $DB->get_records('quest_teams', array('questid' => $quest->id))) {
                foreach ($teams as $team) {
                    foreach ($users as $user) {
                        // ...skip if student not in group.
                        if ($currentgroup) {
                            if (!ismember($currentgroup, $user->id)) {
                                continue;
                            }
                        }
                        $clasification = $DB->get_record('quest_calification_users',
                                array('userid' => $user->id, 'questid' => $quest->id));
                        if ($clasification && $clasification->teamid == $team->id) {
                            $existy = false;
                            foreach ($teamstemp as $teamtemp) {
                                if ($teamtemp->id == $team->id) {
                                    $existy = true;
                                }
                            }
                            if (!$existy) {
                                $teamstemp[] = $team;
                            }
                        }
                    }
                }
            }
            $teams = $teamstemp;

            if ($clasificationteams = $this->block_quest_get_calification_teams($quest)) {
                foreach ($clasificationteams as $clasificationteam) {
                    foreach ($teams as $team) {
                        if ($clasificationteam->teamid == $team->id) {
                            $calificationteams[] = $clasificationteam;
                            $indice++;
                        }
                    }
                }
            }
            for ($i = 0; $i < $indice; $i++) {
                $data = array();
                $sortdata = array();

                foreach ($teams as $team) {
                    if ($calificationteams[$i]->teamid == $team->id) {
                        $data[] = $team->name;
                        $sortdata['team'] = $team->name;
                    }
                }
                $points = $calificationteams[$i]->points;
                $points = number_format($points, 4);
                $data[] = $points;
                $sortdata['calification'] = $points;

                $tablesort->data[] = $data;
                $tablesort->sortdata[] = $sortdata;
            }
            uasort($tablesort->sortdata, array($this, 'sort_by_grade'));
            $table = new html_table();
            $table->data = array();
            foreach ($tablesort->sortdata as $key => $row) {
                $table->data[] = $tablesort->data[$key];
            }
            $table->align = array('left', 'center', 'center', 'center', 'center', 'center',
                                  'center', 'center', 'center', 'center', 'center');

            $columns = array('team', 'calification');
            $string = array();
            foreach ($columns as $column) {
                $string[$column] = get_string("$column", 'quest');
                if ($sort != $column) {
                    $columnicon = '';
                    $columndir = 'ASC';
                } else {
                    $columndir = $dir == 'ASC' ? 'DESC' : 'ASC';
                    if ($column == 'lastaccess') {
                        $columnicon = $dir == 'ASC' ? 'up' : 'down';
                    } else {
                        $columnicon = $dir == 'ASC' ? 'down' : 'up';
                    }
                    $columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"$columnicon\" />";
                }
                $$column = "<a href=\"view.php?sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
            }
            $table->head = array(get_string('team', 'block_quest_classification'), get_string('calification',
                        'block_quest_classification'));
        }
        $tablestring = html_writer::table($table);

        return $tablestring;
    }

    /**
     * Users of the course that can view the classification.
     * @param type $courseid
     * @param type $sort
     * @return array users
     */
    public function block_quest_get_course_members($courseid, $sort = 's.timeaccess') {
        $members = get_users_by_capability($this->page->context, 'block/quest_classification:viewlist', null, $sort);
        return $members;
    }

    /**
     * Scores of the users
     * @param stdClass $quest
     * @return array
     */
    public function block_quest_get_calification(stdClass $quest) {
        global $DB;
        return $DB->get_records('quest_calification_users', array('questid' => $quest->id), 'points ASC');
    }

    /**
     * Get scores of the teams
     * @param stdClass $quest
     * @return type
     */
    public function block_quest_get_calification_teams(stdClass $quest) {
        global $DB;
        return $DB->get_records('quest_calification_teams', array('questid' => $quest->id), 'points ASC');
    }

    /**
     * Define the order of the table by total score.
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    private function sort_by_grade($a, $b) {
        $sort = 'calification';
        $dir = 'DESC';
        if ($dir == 'ASC') {
            return ($a[$sort] - $b[$sort]);
        } else {
            return ($b[$sort] - $a[$sort]);
        }
    }

}
