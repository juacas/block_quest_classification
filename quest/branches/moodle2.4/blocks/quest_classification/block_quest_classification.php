<?php //$Id: block_quest_classification.php
/******************************************************
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package quest
 ********************************************************/
class block_quest_classification extends block_base {
	function init() {
		$this->title = get_string('classificationquest', 'block_quest_classification');
	}
	function applicable_formats() {
		return array('course' => true, 'mod-quest' => true);
	}

	/**
	 * If this block belongs to a quiz context, then return that quiz's id.
	 * Otherwise, return 0.
	 * @return integer the quiz id.
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

	function instance_config_save($data, $nolongerused = false) {
		if (empty($data->questid)) {
			$data->questid = $this->get_owning_quest();
		}
		parent::instance_config_save($data);
	}

	function get_content()
	{
		global $USER, $CFG, $DB;

		if ($this->content !== NULL) {
			return $this->content;
		}
		$this->content = new stdClass;
		$this->content->text = '';
		$this->content->footer = '';

		if (empty($this->instance)) {
			$this->content = '';
			return $this->content;
		}

		if ($this->page->activityname == 'quest'
		&& $this->page->context->id == $this->instance->parentcontextid)
		{
			$quest = $this->page->activityrecord;
			$questid = $quest->id;
			$courseid = $this->page->course->id;
			$inquest = true;
		} else if (!empty($this->config->questid))
		{
				
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


		if(empty($questid)) {
			$this->content->text = get_string('error_emptyquestid', 'block_quest_classification');
			return $this->content;
		}

			
		if(empty($this->config->showbest)) {
			$this->content->text = get_string('configuredtoshownothing', 'block_quest_classification');
			return $this->content;
		}
		$groupmode = NOGROUPS;
		$best = array();

		// The block was configured to operate in group mode
		$course = $this->page->course;

		if($course->groupmodeforce) {
			$groupmode = $course->groupmode;
		}
		else {
			$module = get_coursemodule_from_instance('quest', $questid);
			$groupmode = $module->groupmode;
		}

		$can_access_groups=has_capability('moodle/site:accessallgroups', $this->page->context);
		// The actual groupmode for the quest is now known to be $groupmode
		if(!$can_access_groups)
		{
			$groupid = get_current_group($course->id, false);
		}

		/////////////////////////////////////////////////////////////////

		if(($this->config->useteams == 0)||($quest->allowteams == 0)) {
			$actionclasification = 'global';
		}
		elseif($this->config->useteams == 1){
			$actionclasification = 'teams';
		}

		$nstudents = $this->config->showbest;

		if(	$can_access_groups
		&&($groupmode != 0))
		{
			$string = '<center>'.get_string('namequest','block_quest_classification',$quest).'</center>';
			$string .= substr($this->print_simple_calification($quest,$course,$groupid, $actionclasification,$nstudents),5);
			$this->content->text = $string;
			return $this->content;
		}
		else{
				
			if($can_access_groups)
			{
				$groupid = 0;
			}
			//$string = '<center>'.get_string('namequest','block_quest_classification',$quest).'</center>';
			$listing = $this->print_simple_calification($quest,$course,$groupid, $actionclasification,$nstudents);
			
			$this->title = get_string('namequest','block_quest_classification',$quest);
			$this->content->text = $listing;
			return $this->content;
		}

	}

	function instance_allow_multiple() {
		return true;
	}

	////////////////////////////////////////////////////////////////////////////////////////7
	function print_simple_calification($quest,$course,$currentgroup, $actionclasification, $nstudents){

		global $CFG,$OUTPUT,$DB;

		$string = '';
		$sort = '';
		$users = $this->block_quest_get_course_members($course->id, "u.lastname, u.firstname");
		if (!$users)
		{
			return '----';
		}

		/// Now prepare table with student
		$tablesort= new stdClass();
		$tablesort->data = array();
		$tablesort->sortdata = array();
		$calification_users = array();
		$calification_teams = array();
		$indice = 0;


		if($actionclasification == 'global'){

			if($califications = $this->block_quest_get_calification($quest)){

				foreach ($califications as $calification) {
					// skip if student not in group
					if ($currentgroup) {
						if (!ismember($currentgroup, $calification->userid)) {
							continue;
						}
					}
					$calification_users[] = $calification;
					$indice++;
				}

			}

			for($i=0;$i<$indice;$i++){
				foreach($users as $user){
					if($user->id == $calification_users[$i]->userid){
						break;
					}
				}
				$points=0;
				$data = array();
				$sortdata = array();

				$data[] = $OUTPUT->user_picture($user,array('courseid'=>$course->id, 'link'=>true));
				$data[] = fullname($user);
				$sortdata['user'] = fullname($user);

				if($quest->allowteams){
					if($clasification_team = $DB->get_record("quest_calification_teams", array('teamid'=>$calification_users[$i]->teamid, 'questid'=>$quest->id)))
					{

						$points = $calification_users[$i]->points + $clasification_team->points*$quest->teamporcent/100;

					}
				}
				else{
					$points = $calification_users[$i]->points;

				}

				$points = number_format($points, 4);
				$data[] = $points;
				$sortdata['calification'] = $points;

				$tablesort->data[] = $data;
				$tablesort->sortdata[] = $sortdata;
			}

			uasort($tablesort->sortdata, 'block_quest_sortfunction_calification');
			$table = new html_table();
			$table->data = array();
			foreach($tablesort->sortdata as $key => $row) {
				$table->data[] = $tablesort->data[$key];
			}
			$table->align = array ('left','left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');

			$columns = array('picture','user','calification');
			$table->width = "95%";

			foreach ($columns as $column) {
				$string[$column] = get_string("$column", 'quest');
				if ($sort != $column) {
					$columnicon = '';
					$columndir = 'ASC';
				} else {
					$columndir = $dir == 'ASC' ? 'DESC':'ASC';
					if ($column == 'lastaccess') {
						$columnicon = $dir == 'ASC' ? 'up':'down';
					} else {
						$columnicon = $dir == 'ASC' ? 'down':'up';
					}
					$columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"$columnicon\" />";

				}

				$$column = "<a href=\"view.php?sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
			}

			$table->head = array (get_string('user','block_quest_classification'), get_string('calification','block_quest_classification'));
			$table->headspan=array(2,1);
				
		}
		elseif($actionclasification == 'teams'){

			$teamstemp = array();

			if($teams = $DB->get_records('quest_teams', array('questid' => $quest->id))){
				foreach($teams as $team){
					foreach ($users as $user) {
						// skip if student not in group
						if ($currentgroup) {
							if (!ismember($currentgroup, $user->id)) {
								continue;
							}
						}

						$clasification = $DB->get_record('quest_calification_users',array('userid'=>$user->id, 'questid'=>$quest->id));
						if($clasification->teamid == $team->id){
							$existy = false;
							foreach($teamstemp as $teamtemp){
								if($teamtemp->id == $team->id){
									$existy = true;
								}
							}
							if(!$existy){
								$teamstemp[] = $team;
							}

						}
					}
				}
			}
			$teams = $teamstemp;

			if($clasification_teams = $this->block_quest_get_calification_teams($quest)){
				foreach($clasification_teams as $clasification_team){
					foreach($teams as $team){
						if($clasification_team->teamid == $team->id){
							$calification_teams[] = $clasification_team;
							$indice++;
						}
					}
				}
			}


			for($i=0;$i<$indice;$i++){

				$data = array();
				$sortdata = array();

				foreach($teams as $team){
					if($calification_teams[$i]->teamid == $team->id){
						$data[] = $team->name;
						$sortdata['team'] = $team->name;
					}
				}

				$points = $calification_teams[$i]->points;
				$points = number_format($points, 4);
				$data[] = $points;
				$sortdata['calification'] = $points;

				$tablesort->data[] = $data;
				$tablesort->sortdata[] = $sortdata;
			}

			uasort($tablesort->sortdata, 'block_quest_sortfunction_calification');
			$table->data = array();
			foreach($tablesort->sortdata as $key => $row) {
				$table->data[] = $tablesort->data[$key];
			}
			$table->align = array ('left','center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');

			$columns = array('team','calification');
			$table->width = "95%";

			foreach ($columns as $column) {
				$string[$column] = get_string("$column", 'quest');
				if ($sort != $column) {
					$columnicon = '';
					$columndir = 'ASC';
				} else {
					$columndir = $dir == 'ASC' ? 'DESC':'ASC';
					if ($column == 'lastaccess') {
						$columnicon = $dir == 'ASC' ? 'up':'down';
					} else {
						$columnicon = $dir == 'ASC' ? 'down':'up';
					}
					$columnicon = " <img src=\"$CFG->pixpath/t/$columnicon.gif\" alt=\"$columnicon\" />";

				}
					
				$$column = "<a href=\"view.php?sort=$column&amp;dir=$columndir\">".$string[$column]."</a>$columnicon";
			}

			$table->head = array (get_string('team','block_quest_classification'), get_string('calification','block_quest_classification'));
			$table->headspan=array(2,1);
		
		}

		$table_string = html_writer::table($table);//$this->print_table($table,$nstudents);
		
		return $table_string;

	}

	////////////////////////////////////////////////////////7
	

	function block_quest_get_course_members($courseid, $sort='s.timeaccess')
	{
		$members=get_users_by_capability($this->page->context, 'block/quest_classification:viewlist',null,$sort);
		return $members;
	}

	function block_quest_get_calification($quest) {
		global $DB;
		return $DB->get_records('quest_calification_users', array('questid'=>$quest->id), 'points ASC' );

	}

	function block_quest_get_calification_teams($quest) {
		global $DB;
		return $DB->get_records_select('quest_calification_teams',array('questid' =>$quest->id), 'points ASC' );
	}




}
	function block_quest_sortfunction_calification($a, $b)
	{
		$sort = 'calification';
		$dir = 'DESC';
		if ($dir == 'ASC') {
			return ($a[$sort] > $b[$sort]);
		} else {
			return ($a[$sort] < $b[$sort]);
		}
	}
?>
