<?php
/******************************************************
* Module developed at the University of Valladolid
* Designed and directed by Juan Pablo de Castro with the effort of many other
* students of telecommunciation engineering
* this module is provides as-is without any guarantee. Use it as your own risk.
*
* @author Juan Pablo de Castro and many others.
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quest
******************************************/
function block_quest_sortfunction_calification($a, $b) {
           $sort = 'calification';
           $dir = 'DESC';
           if ($dir == 'ASC') {
               return ($a[$sort] > $b[$sort]);
           } else {
               return ($a[$sort] < $b[$sort]);
           }
     }
?>
