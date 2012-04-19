<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Mreporting plugin for GLPI
 Copyright (C) 2003-2011 by the mreporting Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of mreporting.

 mreporting is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 mreporting is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with mreporting. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */
 
class PluginMreportingHelpdesk Extends PluginMreportingBaseclass {
   private $sql_date, $filters, $where_entities;

   function __construct()  {
      global $LANG;
      $this->sql_date = PluginMreportingMisc::getSQLDate();
      $this->filters = array(
         'open' => array(
            'label' => $LANG['job'][14],
            'status' => array(
               'new' => $LANG['joblist'][9],
               'assign' => $LANG['joblist'][18],
               'plan' => $LANG['joblist'][19],
               'waiting' => $LANG['joblist'][26]
            )
         ),
         'close' => array(
            'label' => $LANG['job'][16],
            'status' => array(
               'solved' => $LANG['joblist'][32],
               'closed' => $LANG['joblist'][33]
            )
         )
      );
      $this->where_entities = "'".implode("', '", $_SESSION['glpiactiveentities'])."'";
   }

   function reportHbarTicketNumberByEntity() {
      global $DB, $LANG;
      $datas = array();

      $query = "SELECT
         COUNT(glpi_tickets.id) as nb,
         glpi_entities.name as name
      FROM glpi_tickets
      LEFT JOIN glpi_entities
         ON glpi_tickets.entities_id = glpi_entities.id
      WHERE ".$this->sql_date."
      AND glpi_entities.id IN (".$this->where_entities.")
      GROUP BY glpi_entities.name
      ORDER BY glpi_entities.name ASC";
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {
         if (empty($data['name'])) $data['name'] = $LANG['entity'][2];
         $datas[$data['name']] = $data['nb'];
      }

      return array('datas' => $datas);
   }

   function reportPieTicketNumberByEntity() {
      return $this->reportHbarTicketNumberByEntity();
   }

   function reportHgbarTicketNumberByCatAndEntity() {
      global $DB, $LANG;
      $datas = array();
      $tmp_datas = array();


      //get categories used in this period
      $query_cat = "SELECT
         DISTINCT(glpi_tickets.itilcategories_id) as itilcategories_id,
         glpi_itilcategories.completename as category
      FROM glpi_tickets
      LEFT JOIN glpi_itilcategories
         ON glpi_tickets.itilcategories_id = glpi_itilcategories.id
      WHERE ".$this->sql_date."
      AND glpi_tickets.entities_id IN (".$this->where_entities.")
      ORDER BY glpi_itilcategories.id ASC";
      $res_cat = $DB->query($query_cat);
      $categories = array();
      while ($data = $DB->fetch_assoc($res_cat)) {
         if (empty($data['category'])) $data['category'] = $LANG['job'][32];
         $categories[$data['category']] = $data['itilcategories_id'];
      }


      $labels2 = array_keys($categories);
      $tmp_cat = array();
      foreach(array_values($categories) as $id) {
         $tmp_cat[] = "cat_$id";
      }
      $cat_str = "'".implode("', '", array_values($categories))."'";

      //count ticket by entity and categories previously selected
      $query = "SELECT
         COUNT(glpi_tickets.id) as nb,
         glpi_entities.name as entity,
         glpi_tickets.itilcategories_id as cat_id
      FROM glpi_tickets
      LEFT JOIN glpi_entities
         ON glpi_tickets.entities_id = glpi_entities.id
      WHERE glpi_tickets.itilcategories_id IN ($cat_str)
      AND glpi_tickets.entities_id IN (".$this->where_entities.")
      AND ".$this->sql_date."
      GROUP BY glpi_entities.name, glpi_tickets.itilcategories_id
      ORDER BY glpi_entities.name ASC, glpi_tickets.itilcategories_id ASC";
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {
         if (empty($data['entity'])) $data['entity'] = $LANG['entity'][2];
         $tmp_datas[$data['entity']]["cat_".$data['cat_id']] = $data['nb'];
      }

      //merge missing datas (0 ticket for a category)
      foreach($tmp_datas as &$data) {
         $data = array_merge(array_fill_keys($tmp_cat, 0), $data);
      }

      //replace cat_id by labels2
      foreach ($tmp_datas as $entity => &$subdata) {
         $tmp = array();
         $i = 0;
         foreach ($subdata as $value) {
            $cat_label = $labels2[$i];
            $tmp[$cat_label] = $value;
            $i++;
         }
         $subdata = $tmp;
      }

      $datas['datas'] = $tmp_datas;
      $datas['labels2'] = $labels2;

      return $datas;
   }

   function reportPieTicketOpenedAndClosed() {
      global $DB;

      $datas = array();
      foreach($this->filters as $filter) {

         $query = "
            SELECT COUNT(*)
            FROM glpi_tickets
            WHERE ".$this->sql_date."
            AND glpi_tickets.entities_id IN (".$this->where_entities.")
            AND glpi_tickets.status IN('".implode("', '", array_keys($filter['status']))."')
         ";
         $result = $DB->query($query);
         $datas[$filter['label']] = $DB->result($result, 0, 0);
      }

      return array('datas' => $datas);
   }
   
   function reportPieTicketOpenedbyStatus() {
      global $DB;
      
      $datas = array();
      foreach($this->filters['open']['status'] as $key => $val) {

            $query = "
               SELECT COUNT(glpi_tickets.id) as count
               FROM glpi_tickets
               WHERE ".$this->sql_date."
               AND glpi_tickets.is_deleted = '0'
               AND glpi_tickets.entities_id IN (".$this->where_entities.")
               AND glpi_tickets.status ='".$key."'
            ";
            $result = $DB->query($query);
            
            while ($ticket = $DB->fetch_assoc($result)) {

               $datas['datas'][$val] = $ticket['count'];
            }
      }
      
      
      return $datas;
   }
   
   function reportHgbarOpenTicketNumberByCategoryAndByType() {
      return $this->reportHgbarTicketNumberByCategoryAndByType('open');
   }

   function reportHgbarCloseTicketNumberByCategoryAndByType() {
      return $this->reportHgbarTicketNumberByCategoryAndByType('close');
   }

   private function reportHgbarTicketNumberByCategoryAndByType($filter) {
      global $DB, $LANG;
      $datas = array();

      $datas['labels2']['type_1'] = $LANG['job'][1];
      $datas['labels2']['type_2'] = $LANG['job'][2];

      $query = "
         SELECT
            glpi_itilcategories.id as category_id,
            glpi_itilcategories.completename as category_name,
            glpi_tickets.type,
            COUNT(glpi_tickets.id) as count
         FROM glpi_tickets
         LEFT JOIN glpi_itilcategories
            ON glpi_itilcategories.id = glpi_tickets.itilcategories_id
         WHERE ".$this->sql_date."
         AND glpi_tickets.entities_id IN (".$this->where_entities.")
         AND glpi_tickets.status IN('".implode("', '", array_keys($this->filters[$filter]['status']))."')
         GROUP BY glpi_itilcategories.id, glpi_tickets.type
         ORDER BY glpi_itilcategories.name
      ";
      $result = $DB->query($query);

      $datas['datas'] = array();
      while ($ticket = $DB->fetch_assoc($result)) {
         if(is_null($ticket['category_id'])) {
            $ticket['category_id'] = 0;
            $ticket['category_name'] = $LANG['job'][32];
         }
         $datas['datas'][$ticket['category_name']]['type_'.$ticket['type']] = $ticket['count'];
      }

      return $datas;
   }

   function reportHgbarTicketNumberByService() {
      global $DB, $LANG;
      $datas = array();

      foreach($this->filters as $class=>$filter) {

         $datas['labels2'][$class] = $filter['label'];

         $query = "
            SELECT COUNT(*)
            FROM glpi_tickets
            WHERE id NOT IN (
               SELECT tickets_id
               FROM glpi_groups_tickets
               WHERE glpi_groups_tickets.type = 1
            )
            AND glpi_tickets.entities_id IN (".$this->where_entities.")
            AND ".$this->sql_date."
            AND status IN('".implode("', '", array_keys($filter['status']))."')
         ";
         $result = $DB->query($query);

         $datas['datas'][$LANG['common'][49]][$class] = $DB->result($result, 0, 0);

         $query = "
            SELECT
               glpi_groups.name as group_name,
               COUNT(glpi_tickets.id) as count
            FROM glpi_tickets, glpi_groups_tickets, glpi_groups
            WHERE glpi_tickets.id = glpi_groups_tickets.tickets_id
            AND glpi_tickets.entities_id IN (".$this->where_entities.")
            AND glpi_groups_tickets.groups_id = glpi_groups.id
            AND glpi_groups_tickets.type = 1
            AND ".$this->sql_date."
            AND glpi_tickets.status IN('".implode("', '", array_keys($filter['status']))."')
            GROUP BY glpi_groups.id
            ORDER BY glpi_groups.name
         ";
         $result = $DB->query($query);

         while ($ticket = $DB->fetch_assoc($result)) {
            $datas['datas'][$ticket['group_name']][$class] = $ticket['count'];
         }

      }

      return $datas;
   }

   function reportHgbarOpenedTicketNumberByCategory() {
      global $DB, $LANG;
      $datas = array();


      $status = array_merge(
         $this->filters['open']['status'],
         $this->filters['close']['status']
      );
      $status_keys = array_keys($status);



      $query = "
         SELECT
            glpi_tickets.status,
            glpi_itilcategories.completename as category_name,
            COUNT(glpi_tickets.id) as count
         FROM glpi_tickets
         LEFT JOIN glpi_itilcategories
            ON glpi_itilcategories.id = glpi_tickets.itilcategories_id
         WHERE ".$this->sql_date."
         AND glpi_tickets.entities_id IN (".$this->where_entities.")
         AND glpi_tickets.status IN('".implode("', '",$status_keys)."')
         GROUP BY glpi_itilcategories.id, glpi_tickets.status
         ORDER BY glpi_itilcategories.name
      ";
      $result = $DB->query($query);

      while ($ticket = $DB->fetch_assoc($result)) {
         if(is_null($ticket['category_name'])) {
            $ticket['category_name'] = $LANG['job'][32];
         }
         $datas['labels2'][$ticket['status']] = $status[$ticket['status']];
         $datas['datas'][$ticket['category_name']][$ticket['status']] = $ticket['count'];
      }

      return $datas;
   }

   function reportAreaNbTicket() {
      global $DB, $LANG;
      $datas = array();

      $query = "SELECT
         DISTINCT DATE_FORMAT(date, '%y%m') as month,
         DATE_FORMAT(date, '%b%y') as month_l,
         COUNT(id) as nb
      FROM glpi_tickets
      WHERE ".$this->sql_date."
      AND glpi_tickets.entities_id IN (".$this->where_entities.")
      GROUP BY month
      ORDER BY month";
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {
         $datas['datas'][$data['month_l']] = $data['nb'];
      }

      //curve lines
      $datas['spline'] = true;

      return $datas;
   }


   function reportLineNbTicket() {
      return $this->reportAreaNbTicket();
   }


   function reportGlineNbTicket() {
      global $DB, $LANG;
      $datas = array();

      $query = "SELECT DISTINCT
         DATE_FORMAT(date, '%y%m') as month,
         DATE_FORMAT(date, '%b%y') as month_l,
         status,
         COUNT(id) as nb
      FROM glpi_tickets
      WHERE ".$this->sql_date."
      AND glpi_tickets.entities_id IN (".$this->where_entities.")
      GROUP BY month, status
      ORDER BY month, status";
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {
         $status =Ticket::getStatus($data['status']);
         $datas['labels2'][$data['month_l']] = $data['month_l'];
         $datas['datas'][$status][$data['month_l']] = $data['nb'];
      }

      //curve lines
      $datas['spline'] = true;

      return $datas;
   }

   function reportGareaNbTicket() {
      return $this->reportGlineNbTicket();
   }
}

?>