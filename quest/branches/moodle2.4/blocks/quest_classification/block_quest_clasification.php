<?php //$Id: block_quest_clasification.php
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
require_once("lib_block.php");

class block_quest_clasification extends block_base {
    function init() {
        $this->title = get_string('clasificationquest', 'block_quest_clasification');
        $this->version = 2005012600;
    }

    function get_content() {
        global $USER, $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }
        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if($this->instance->pagetype == 'course-view') {
            // We need to see if we are monitoring a quest
            $questid   = empty($this->config->questid) ? 0 : $this->config->questid;
            $groupid   = empty($this->config->groupid) ? 0 : $this->config->groupid;
            $courseid = $this->instance->pageid;
        }
        else {
            // Assuming we are displayed in the quest view page
            $questid = $this->instance->pageid;

            // A trick to take advantage of instance config and save queries
            if(empty($this->config->courseid)) {
                $modrecord = get_record('modules', 'name', 'quest');
                $cmrecord  = get_record('course_modules', 'module', $modrecord->id, 'instance', $questid);
                $this->config->courseid = intval($cmrecord->course);
                $this->instance_config_commit();
            }
            $courseid = $this->config->courseid;
        }

        if(empty($questid)) {
            $this->content->text = get_string('error_emptyquestid', 'block_quest_clasification');
            return $this->content;
        }

        // Get the quest record
        $quest = get_record('quest', 'id', $questid);
        if(empty($quest)) {
            $this->content->text = get_string('error_emptyquestrecord', 'block_quest_clasification');
            return $this->content;
        }


        if(empty($this->config->showbest)) {
            $this->content->text = get_string('configuredtoshownothing', 'block_quest_clasification');
            return $this->content;
        }

        $groupmode = NOGROUPS;
        $best = array();

            // The block was configured to operate in group mode
        $course = get_record_select('course', 'id = '.$courseid);
        if($course->groupmodeforce) {
          $groupmode = $course->groupmode;
        }
        else {
          $module = get_record_sql('SELECT cm.groupmode FROM '.$CFG->prefix.'modules m LEFT JOIN '.$CFG->prefix.'course_modules cm ON m.id = cm.module WHERE m.name = \'quest\' AND cm.instance = '.$questid);
          $groupmode = $module->groupmode;
        }
            // The actual groupmode for the quest is now known to be $groupmode
        if(!isteacher($courseid)){
         $groupid = get_and_set_current_group($course, $groupmode, -1);
        }

        /////////////////////////////////////////////////////////////////

        if(($this->config->useteams == 0)||($quest->allowteams == 0)) {
          $actionclasification = 'global';
        }
        elseif($this->config->useteams == 1){
          $actionclasification = 'teams';
        }

        $nstudents = $this->config->showbest;

        if((isteacher($courseid))&&($groupmode != 0)){
         $string = '<center>'.get_string('namequest','block_quest_clasification',$quest).'</center>';
         $string .= substr($this->print_simple_calification($quest,$course,$groupid, $actionclasification,$nstudents),5);
         $this->content->text = $string;
         return $this->content;
        }
        else{
         if(isteacher($courseid)){
          $groupid = 0;
         }
         $string = '<center>'.get_string('namequest','block_quest_clasification',$quest).'</center>';
         $string .= substr($this->print_simple_calification($quest,$course,$groupid, $actionclasification,$nstudents),5);
         $this->content->text = $string;
         return $this->content;
        }

    }

    function instance_allow_multiple() {
        return true;
    }

    ////////////////////////////////////////////////////////////////////////////////////////7
    function print_simple_calification($quest,$course,$currentgroup, $actionclasification, $nstudents){

     global $CFG;

     $string = '';
     $sort = '';

        if (!$users = $this->block_quest_get_course_members($course->id, "u.lastname, u.firstname")){

            exit;
        }

        /// Now prepare table with student
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

             $data[] = print_user_picture($user->id, $course->id, $user->picture,true,true);
             $sortdata['picture'] = 1;

             $data[] = "<b>".fullname($user).'</b>';
             $sortdata['user'] = fullname($user);

             if($quest->allowteams){
                 if($clasification_team = get_record("quest_calification_teams", "teamid", $calification_users[$i]->teamid, "questid", $quest->id)){

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

         $table->head = array ("",get_string('user','block_quest_clasification'), get_string('calification','block_quest_clasification'));

        }
        elseif($actionclasification == 'teams'){

         $teamstemp = array();

          if($teams = get_records_select("quest_teams", "questid = $quest->id")){
           foreach($teams as $team){
              foreach ($users as $user) {
              // skip if student not in group
                  if ($currentgroup) {
                          if (!ismember($currentgroup, $user->id)) {
                           continue;
                          }
                  }

                  $clasification = get_record("quest_calification_users", "userid", $user->id, "questid", $quest->id);
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

         $table->head = array (get_string('team','block_quest_clasification'), get_string('calification','block_quest_clasification'));

        }

        $string .= $this->print_table($table,$nstudents);
        return $string;

     }

     ////////////////////////////////////////////////////////7
     function print_table($table,$nstudents) {

      $string = '';

          if (isset($table->align)) {
              foreach ($table->align as $key => $aa) {
                  if ($aa) {
                      $align[$key] = ' align="'. $aa .'"';
                  } else {
                      $align[$key] = '';
                  }
              }
          }
          if (isset($table->size)) {
              foreach ($table->size as $key => $ss) {
                  if ($ss) {
                      $size[$key] = ' width="'. $ss .'"';
                  } else {
                      $size[$key] = '';
                  }
              }
          }
          if (isset($table->wrap)) {
              foreach ($table->wrap as $key => $ww) {
                  if ($ww) {
                      $wrap[$key] = ' nowrap="nowrap" ';
                  } else {
                      $wrap[$key] = '';
                  }
              }
          }

          if (empty($table->width)) {
              $table->width = '80%';
          }

          if (empty($table->cellpadding)) {
              $table->cellpadding = '5';
          }

          if (empty($table->cellspacing)) {
              $table->cellspacing = '1';
          }

          if (empty($table->class)) {
              $table->class = 'generaltable';
          }

          $tableid = empty($table->id) ? '' : 'id="'.$table->id.'"';


          $string .= "<table align=\"center\" width=\"$table->width\" class=\"generalbox\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">".
                "<tr><td bgcolor=\"#ffffff\" class=\"generalbox\""."content\">";
          $string .= '<table width="100%" border="0" align="center" ';
          $string .= " cellpadding=\"$table->cellpadding\" cellspacing=\"$table->cellspacing\" class=\"$table->class\" $tableid>\n";

          $countcols = 0;

          if (!empty($table->head)) {
              $countcols = count($table->head);
              $string .= '<tr>';
              foreach ($table->head as $key => $heading) {

                  if (!isset($size[$key])) {
                      $size[$key] = '';
                  }
                  if (!isset($align[$key])) {
                      $align[$key] = '';
                  }
                  $string .= '<th valign="top" '. $align[$key].$size[$key] .' nowrap="nowrap" class="header c'.$key.'">'. $heading .'</th>';
              }
              $string .= '</tr>'."\n";
          }

          $contador = 0;

          if (!empty($table->data)) {
              $oddeven = 1;
              foreach ($table->data as $key => $row) {

               $contador++;

                  $oddeven = $oddeven ? 0 : 1;
                  $string .= '<tr class="r'.$oddeven.'">'."\n";
                  if ($row == 'hr' and $countcols) {
                      $string .= '<td colspan="'. $countcols .'"><div class="tabledivider"></div></td>';
                  } else {  /// it's a normal row of data
                      foreach ($row as $key => $item) {

                          if (!isset($size[$key])) {
                              $size[$key] = '';
                          }
                          if (!isset($align[$key])) {
                              $align[$key] = '';
                          }
                          if (!isset($wrap[$key])) {
                              $wrap[$key] = '';
                          }
                          $string .= '<td '. $align[$key].$size[$key].$wrap[$key] .' class="cell c'.$key.'">'. $item .'</td>';

                      }
                  }
                  $string .= '</tr>'."\n";
                  if($contador >= $nstudents)
                        break;
              }
          }
          $string .= '</table>'."\n";
          $string .= '</td></tr></table>';

          return $string;
     }

     function block_quest_get_course_members($courseid, $sort='s.timeaccess', $dir='', $page=0, $recordsperpage=99999,
                             $firstinitial='', $lastinitial='', $group=NULL, $search='', $fields='', $exceptions='') {

          global $CFG;

if ($CFG->release>="1.9")
{

	if (!$fields) {
        $fields = 'u.id, u.confirmed, u.username, u.firstname, u.lastname, '.
                  'u.maildisplay, u.mailformat, u.maildigest, u.email, u.city, '.
                  'u.country, u.picture, u.idnumber, u.department, u.institution, '.
                  'u.emailstop, u.lang, u.timezone, u.lastaccess';
    }
	$students=get_course_users($courseid,$sort,NULL,$fields);
	return $students;
}
else
if ($CFG->release>="1.7")
{
	
	if (!$fields) {
        $fields = 'u.id, u.confirmed, u.username, u.firstname, u.lastname, '.
                  'u.maildisplay, u.mailformat, u.maildigest, u.email, u.city, '.
                  'u.country, u.picture, u.idnumber, u.department, u.institution, '.
                  'u.emailstop, u.lang, u.timezone, s.timeaccess as lastaccess';
    }
	$students=get_course_users($courseid,$sort,NULL,$fields);
	return $students;
}
else
{
          $limit     = sql_paging_limit($page, $recordsperpage);
          $LIKE      = sql_ilike();
          $fullname  = sql_fullname('u.firstname','u.lastname');

          $groupmembers = '';

          // make sure it works on the site course
          $select = 's.course = \''. $courseid .'\' AND ';
          if ($courseid == SITEID) {
              $select = '';
          }

          $select .= 's.userid = u.id AND u.deleted = \'0\' ';

          if (!$fields) {
              $fields = 'u.id, u.confirmed, u.username, u.firstname, u.lastname, '.
                        'u.maildisplay, u.mailformat, u.maildigest, u.email, u.city, '.
                        'u.country, u.picture, u.idnumber, u.department, u.institution, '.
                        'u.emailstop, u.lang, u.timezone, s.timeaccess as lastaccess';
          }

          if ($search) {
              $search = ' AND ('. $fullname .' '. $LIKE .'\'%'. $search .'%\' OR email '. $LIKE .'\'%'. $search .'%\') ';
          }

          if ($firstinitial) {
              $select .= ' AND u.firstname '. $LIKE .'\''. $firstinitial .'%\' ';
          }

          if ($lastinitial) {
              $select .= ' AND u.lastname '. $LIKE .'\''. $lastinitial .'%\' ';
          }

          if ($group === 0) {   /// Need something here to get all students not in a group
              return array();

          } else if ($group !== NULL) {
              $groupmembers = ', '. $CFG->prefix .'groups_members gm ';
              $select .= ' AND u.id = gm.userid AND gm.groupid = \''. $group .'\'';
          }

          if (!empty($exceptions)) {
              $select .= ' AND u.id NOT IN ('. $exceptions .')';
          }

          if ($sort) {
              $sort = ' ORDER BY '. $sort .' ';
          }

          $students = get_records_sql("SELECT $fields
                                  FROM {$CFG->prefix}user u,
                                       {$CFG->prefix}user_students s
                                       $groupmembers
                                  WHERE $select $search $sort $dir $limit");

          if (!$teachers = get_records_sql("SELECT $fields
                                  FROM {$CFG->prefix}user u,
                                       {$CFG->prefix}user_teachers s
                                        $groupmembers
                                  WHERE $select $search $sort $dir $limit")) {
              return $students;
          }
          if (!$students) {
              return $teachers;
          }
          return $teachers + $students;
}
     }

     function block_quest_get_calification($quest) {

         return get_records_select("quest_calification_users", "questid = $quest->id", "points ASC" );


     }

     function block_quest_get_calification_teams($quest) {

         return get_records_select("quest_calification_teams", "questid = $quest->id", "points ASC" );
     }



}

?>
