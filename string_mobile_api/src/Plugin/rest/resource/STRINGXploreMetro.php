<?php

namespace Drupal\string_mobile_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\paragraphs\Entity\Paragraph;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Drupal\node\Entity\Node;
use Drupal\Core\Entity;
use Drupal\string_mobile_api\UIItemTypes\STRINGItemTypeNode;

// technical sessions
use Drupal\string_ts_entity\Entity\stringTechnicalSession;
use Drupal\string_ts_entity\Entity\stringTechnicalSessionAuthor;
use Drupal\string_ts_entity\Entity\stringTechnicalSessionPaper;

// exhibitors
use Drupal\string_exh_entities\Entity\stringExhibitorEntity;
use Drupal\string_exh_entities\Entity\stringBoothEntity;
use Drupal\string_exh_entities\Entity\stringContactEntity;
use Drupal\string_exh_entities\Entity\stringProductEntity;

/**
 * Provides a resource for automatic creation of scheduled events of all types
 *
 * @RestResource(
 *   id = "string_xplore_metro",
 *   label = @Translation("string: Metro-js automatic json endpoint"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/xplore/{endpoint}",
 *   }
 * )
 */
class STRINGXploreMetro extends ResourceBase
{

  public function get($endpoint = '')
  {
    $date = \Drupal::request()->query->get('_date');
    if (empty($date)) {
      $date = "02.06.2019";
    }
    $type = \Drupal::request()->query->get('_type');
    if (empty($type)) {
      $type = 'string';
    }
    switch ($endpoint) {
      case 'schedule':
        return $this->getSchedule($date, $type);
        break;
    }
  }

  protected function getSchedule($date, $type) {

    function applyRows($array)
    {
      $modified = [];
      $taken = [];
      foreach ($array as $key => $event) {
        $range_start = stringTimeToint($event['time']);
        do {
          if(!in_array($range_start, $taken)) {
            $taken[] = $range_start;
            $event['row'] = 1;
          }
          if(in_array($range_start, $taken)) {
            $taken[] = $range_start;
            $counts = array_count_values($taken);
            $row = $counts["$range_start"] - 1;
            if(empty($event['row'])) {
              $event['row'] = $row;
            }
          }
          $range_start += 5;
        } while ($range_start <= stringTimeToint($event['endtime']));
        unset ($event['endtime']);
        $modified[] = $event;
      }
      return $modified;
    }

    function applyRowsEC($array)
    {
      $modified = [];
      $taken = [];
      foreach ($array as $key => $event) {
        foreach ($event as $keyEC => $eventEC) {
          $range_start = stringTimeToint($event['time']);
          do {
            if (!in_array($range_start, $taken)) {
              $taken[] = $range_start;
              $event['row'] = 1;
            }
            if (in_array($range_start, $taken)) {
              $taken[] = $range_start;
              $counts = array_count_values($taken);
              $row = $counts["$range_start"] - 1;
              if (empty($event['row'])) {
                $event['row'] = $row;
              }
            }
            $range_start += 5;
          } while ($range_start <= stringTimeToint($event['endtime']));
          unset ($event['endtime']);
          $modified[] = $event;
        }
      }
      return $modified;
    }

    function escapeStringTime ($string)
    {
    $time = explode("T", $string);
    $ws = explode(":", end($time));
    return "$ws[0]:$ws[1]";
    }

    function escapeStringTimeEC ($string)
    {
      $ws = explode(":", $string);
      return "$ws[0]:$ws[1]";
    }

    function escapeStringDate($string)
    {
    $date = explode("T", $string);
    $actual_date = explode("-", $date[0]);
    return "$actual_date[2].$actual_date[1].$actual_date[0]";
    }

    function escapeStringDateEC($string)
    {
      $actual_date = explode("-", $string);
      return "$actual_date[2].$actual_date[1].$actual_date[0]";
    }

    function stringTimeToInt($string) {
      $time = explode(':', $string);
      $hours = (int)$time[0];
      $minutes = (int)$time[1];

      return $hours * 60 + $minutes;
    }

    function findSize ($start_time, $end_time)
    {
      $mfm_start = stringTimeToInt($start_time);
      $mfm_end = stringTimeToInt($end_time);
      return ($mfm_end - $mfm_start) / 5;
    }

    function toHexColor($colorConfigId = '') {
      $hexColor = '';
      if ($colorConfigId) {
        $config = \Drupal::service('entity.manager')->getStorage('ts_color')->load($colorConfigId);
        if ($config) {
          $hexColor = $config->getHexColor();
        }
      }
      return $hexColor;
    }

    function toApiArray($ts)
    {
      $color_config = toHexColor($ts->color_config_id->value);
      $start_time = escapeStringTime($ts->start_time->value);
      $end_time = escapeStringTime($ts->end_time->value);
      $event_type = trim($ts->event_type->value, " \t.,");
      $type = $ts->type->value;
      $size = findSize($start_time, $end_time);
      $desc = "$start_time - $end_time";
      $event_title = $ts->session_number_full->value;

      $result = [
        'time' => $start_time,
        'title' => "$event_title: ",
        'subtitle' => htmlspecialchars_decode($ts->label()),
        'desc' => $desc,
        'size' => $size,
        'cls' => "exclude-click-class session-color-$color_config",
        'endtime' => $end_time
      ];

      return $result;
    }

    function mediaTimeDeFormatter($seconds)
    {

      $ret = "";

      $hours = (string )floor($seconds / 3600);
      $secs = (string )$seconds % 60;
      $mins = (string )floor(($seconds - ($hours * 3600)) / 60);

      if (strlen($hours) == 1)
        $hours = "0" . $hours;
      if (strlen($secs) == 1)
        $secs = "0" . $secs;
      if (strlen($mins) == 1)
        $mins = "0" . $mins;

      if ($hours == 0)
        $ret = "$mins:$secs";
      else
        $ret = "$hours:$mins:$secs";

      return $ret;
    }

    function getActualParagraphId($paragraphId)
    {

      $connection = \Drupal::database();
      $sql = "SELECT DISTINCT paragraphs__target_id  FROM {paragraphs_library_item_field_data} WHERE id = :paras"; // 2 dashes!! _ _
      $query = $connection->query($sql, [':paras' => $paragraphId])->fetchAll();
      $query = json_decode(json_encode($query), True);

      return $query[0]['paragraphs__target_id'];
    }

    function getEventType($obj)
    {

      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_event_type')) {
          $pet = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_event_type')->getValue()[0]['target_id'];
          $event_type = Term::load($pet)->name->value;
          return $event_type;
        }
      }
    }

    function getTidFromTname ($tname)
    {
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $tname]);
      $tid = array_keys($term)[0];

      return $tid;
    }

    function getPsidFromTname ($tname)
    {
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $tname]);
      $tid = array_keys($term)[0];
      $connection = \Drupal::database();
      $sql = "SELECT DISTINCT entity_id  FROM {paragraph__field_subcategory} WHERE field_subcategory_target_id = :paras";
      $query = $connection->query($sql, [':paras' => $tid])->fetchAll();
      $query = json_decode(json_encode($query), True);

      $psid = $query[0]['entity_id'];

      return $psid;
    }

    function getEventCategory($obj)
    {
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_category')) {
          $pec = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_category')->getValue()[0]['target_id'];
          $event_category = Term::load($pec)->name->value;
          return $event_category;
        }
      }
    }

    function getEventSubCategory($obj)
    {
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_subcategory')) {
          $pecs = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_subcategory')->getValue()[0]['target_id'];
          $event_subcategory = Term::load($pecs)->name->value;
          return $event_subcategory;
        }
      }
    }

    function getColorConfigId($obj)
    {
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if($loaded_paragraph) {
        $pcc = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_color_config_id')->getValue()[0]['value'];
        return $pcc;
      }
    }

    function getEventFields1($obj, $date, $type)
    {
      $event_fields1 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];

      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      $event_fields1 = [];
      if ($loaded_paragraph->hasField('field_event')) {
        $paragraph_events1 = $loaded_paragraph->get('field_event')->getValue();
        foreach ($paragraph_events1 as $paragraph_event_id => $nid) {

          $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);

          $start_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
            ->getValue()[0]['from']));
          $end_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
            ->getValue()[0]['to']));
          $size = findSize($start_time, $end_time);
          $desc = "$start_time - $end_time";
          $event_date = escapeStringDateEC($paragraph_event->get('field_date')->getValue()[0]['value']);
          $event_type = (int)$loaded_paragraph->get('field_event_type')->getValue()[0]['target_id'];
          if ($event_date === $date) {
            if ($event_type === getTidFromTname($type)) {
              // $color_config_id = getColorConfigId(##PARENT_NODE);

              $fields = [
                'time' => "$start_time",
                'title' => "",
                'subtitle' => $paragraph_event->label(),
                'desc' => $desc,
                'cls' => "bg-lightgrey",
                'size' => $size,
                'endtime' => $end_time,


              ];
              $event_fields1[] = $fields;
            }
          }
        }
      }
      return $event_fields1;
    }

    function getEventFieldsFromPar($obj, $date, $type)
    {
      $loaded_paragraph = $obj;
      $event_fields1 = [];
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_event')) {
          $paragraph_events1 = $loaded_paragraph->get('field_event')->getValue();
          foreach ($paragraph_events1 as $paragraph_event_id => $nid) {

            $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);

            $start_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
              ->getValue()[0]['from']));
            $end_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
              ->getValue()[0]['to']));
            $size = findSize($start_time, $end_time);
            $desc = "$start_time - $end_time";
            $event_date = escapeStringDateEC($paragraph_event->get('field_date')->getValue()[0]['value']);
            $event_type = (int)$loaded_paragraph->get('field_event_type')->getValue()[0]['target_id'];
            if ($event_date === $date) {
              if ($event_type === getTidFromTname($type)) {
                // $color_config_id = getColorConfigId(##PARENT_NODE);

                $fields = [
                  'time' => "$start_time",
                  'title' => "",
                  'subtitle' => $paragraph_event->label(),
                  'desc' => $desc,
                  'cls' => "bg-lightgrey",
                  'size' => $size,
                  'endtime' => $end_time
                ];
                $event_fields1[] = $fields;
              }
            }
          }
        }
      }
      return $event_fields1;
    }


    function getEvent2FieldsFromPar($obj, $date, $type)
    {
      $loaded_paragraph = $obj;
      $event_fields2 = [];
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_event2')) {
          $paragraph_events1 = $loaded_paragraph->get('field_event2')->getValue();
          foreach ($paragraph_events1 as $paragraph_event_id => $nid) {

            $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);

            $start_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
              ->getValue()[0]['from']));
            $end_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
              ->getValue()[0]['to']));
            $size = findSize($start_time, $end_time);
            $desc = "$start_time - $end_time";
            $event_date = escapeStringDateEC($paragraph_event->get('field_date')->getValue()[0]['value']);
            $event_type = (int)$loaded_paragraph->get('field_event_type')->getValue()[0]['target_id'];
            if ($event_date === $date) {
              if ($event_type === getTidFromTname($type)) {
                // $color_config_id = getColorConfigId(##PARENT_NODE);

                $fields = [
                  'time' => "$start_time",
                  'title' => "",
                  'subtitle' => $paragraph_event->label(),
                  'desc' => $desc,
                  'cls' => "bg-lightgrey",
                  'size' => $size,
                  'endtime' => $end_time
                ];
                $event_fields2[] = $fields;
              }
            }
          }
        }
      }
      return $event_fields2;
    }

    function getEventFields2($obj, $date, $type)
    {
      $event_fields2 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph->hasField('field_event2')) {
        $paragraph_events1 = $loaded_paragraph->get('field_event2')->getValue();
        foreach ($paragraph_events1 as $paragraph_event_id => $nid) {

          $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);

          $start_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
            ->getValue()[0]['from']));
          $end_time = escapeStringTimeEC(mediaTimeDeFormatter($paragraph_event->get('field_time_range')
            ->getValue()[0]['to']));
          $size = findSize($start_time, $end_time);
          $desc = "$start_time - $end_time";
          $event_date = escapeStringDateEC($paragraph_event->get('field_date')->getValue()[0]['value']);
          $event_type = (int)$loaded_paragraph->get('field_event_type')->getValue()[0]['target_id'];
          if ($event_date === $date) {
            if ($event_type === getTidFromTname($type)) {
              // $color_config_id = getColorConfigId(##PARENT_NODE);

              $fields = [
                'time' => "$start_time",
                'title' => "",
                'subtitle' => $paragraph_event->label(),
                'desc' => $desc,
                'cls' => "bg-lightgrey",
                'size' => $size,
                'endtime' => $end_time,


              ];
              $event_fields2[] = $fields;
            }
          }
        }
      }
      return $event_fields2;
    }

    function toApiArrayEC($ec)
    {
        /**
         * logical structure:
         *event_card
         * ---paragraph reference
         * ------paragraph_field_event
         * ---------title
         * ---------id
         * ---------location
         * ---------start_time
         * ---------end_time
         * ---------description
         * ------...(repeated n times for each event)
         * ------paragraph_field_textfield
         * ------...(repeated n times for each textfield)
         * ------paragraph_field_event2
         * ---------title
         * ---------id
         * ---------location
         * ---------start_time
         * ---------end_time
         * ---------description
         * ------...(repeated n times for each event)
         * ------paragraph_field_textfield2
         * ------...(repeated n times for each textfield)
         */

        $resulted = [
          'title' => ' ',
          'secondary' => '',
          'icon' => ' ',
          'cls' => 'bg-greyblue fg-dark br-bt'
        ];


        return $resulted;
    }


    function getTechnicalProgram($date, $type)
      /** Retrieving string technical sessions of type 'Oral Session' and 'Interactive Forum' for schedule */
    {

      $result['title'] = "Technical Program";
      $result['secondary'] = 'string Technical Sessions';
      $result['icon'] = "<span></span>";
      $result['cls'] = "bg-greyblue fg-dark top-br br-bt";


      $idsOS = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Oral Session,')
        ->execute();
      $limit = 400;
      $groups = array_chunk($idsOS, $limit);
      foreach ($groups as $group) {
        $sessions = stringTechnicalSession::loadMultiple($group);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['events']['date'][] = "date that you've sent is $date";
//          $result['events']['start_date'][] = "start date = $start_date";
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type) {
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }
      $idsIF = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Interactive Forum,')
        ->execute();
      $limit = 400;
      $groups = array_chunk($idsIF, $limit);
      foreach ($groups as $groupIF) {
        $sessions = stringTechnicalSession::loadMultiple($groupIF);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type) {
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }
      $result['events'] = applyRows($result['events']);

      return $result;
    }

    function getWSAndSC($date, $type)
      /** Retrieving string technical sessions of type 'Workshop' and 'Short course' for schedule */
    {

      $result['title'] = " ";
      $result['secondary'] = 'Workshops & Short Courses';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark";

      $idsWS = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Workshop,')
        ->execute();


      $idsSC = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Short Course,')
        ->execute();

      $limit = 400;

      $groups = array_chunk($idsWS, $limit);
      foreach ($groups as $groupWS) {
        $sessions = stringTechnicalSession::loadMultiple($groupWS);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type){
            if ($start_date === $date) {
            $session_array = toApiArray($session);
            $result['events'][] = $session_array;
            }
          }
        }
      }

      $groups = array_chunk($idsSC, $limit);
      foreach ($groups as $groupSC) {
        $sessions = stringTechnicalSession::loadMultiple($groupSC);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type) {
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }

      $result['events'] = applyRows($result['events']);

      return $result;

    }

    function getPanelSessions($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Panel Sessions' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Panel Sessions';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark br-bt";
      $psid = getPsidFromTname('Panel Sessions');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function get5GSummits($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'IEEE 5G Summit' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = '5G Summit';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark br-bt";
      $psid = getPsidFromTname('IEEE 5G Summit');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getRFBootcamps ($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'RF Bootcamp' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'RF Bootcamp';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark br-bt";
      $psid = getPsidFromTname('RF Bootcamp');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getstringPlenary ($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'string Plenary & Reception' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'string Plenary & Reception';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark br-bt";
      $psid = getPsidFromTname('string Plenary & Reception');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getstringClosing ($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'string Closing Ceremony' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'string Closing Ceremony';
      $result['icon'] = " ";
      $result['cls'] = "bg-greyblue fg-dark br-bt";
      $psid = getPsidFromTname('string Closing Ceremony');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getAPPapers($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Advanced Practice Papers' for schedule */
    {
      $result['title'] = "Competitions";
      $result['secondary'] = 'Advanced Practice Papers';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";
      $psid = getPsidFromTname('Advanced Practice Papers');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getIndustryPapers($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Industry Papers' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Industry Papers';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";
      $psid = getPsidFromTname('Industry Papers');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function get3MTs($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Three Minute Thesis (3MT)' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Three Minute Thesis (3MT)';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";
      $psid = getPsidFromTname('Three Minute Thesis (3MT)');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getSSPresentations($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Sixty Second Presentation' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Sixty Second Presentation';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";
      $psid = getPsidFromTname('Sixty Second Presentation');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getHackathon($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Hackathon' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Hackathon';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";
      $psid = getPsidFromTname('Hackathon');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getInteractiveForums($date, $type)
      /**Retrieving string technical sessions of type 'Interactive Forum' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'Interactive Forum/Posters';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightpink fg-dark top-br";


      $idsIF = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Interactive Forum,')
        ->execute();

      $limit = 400;

      $groups = array_chunk($idsIF, $limit);
      foreach ($groups as $groupIF) {
        $sessions = stringTechnicalSession::loadMultiple($groupIF);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type){
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }

      $result['events'] = applyRows($result['events']);

      return $result;

    }

    function getExhibitions($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Exhibition, Start-up and 5G Pavilion' for schedule */
    {
      $result['title'] = "Exhibition";
      $result['secondary'] = 'Exhibition, Start-up and 5G Pavilion';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightgreen fg-dark top-br";
      $psid = getPsidFromTname('Exhibition, Start-up and 5G Pavilion');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }

    function getMicroApps($date, $type)
      /**Retrieving string technical sessions of type 'MicroApps Seminar' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'MicroApps';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightgreen fg-dark top-br";


      $idsMA = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'MicroApps Seminar,')
        ->execute();

      $limit = 400;

      $groups = array_chunk($idsMA, $limit);
      foreach ($groups as $groupMA) {
        $sessions = stringTechnicalSession::loadMultiple($groupMA);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type){
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }

      $result['events'] = applyRows($result['events']);

      return $result;

    }

    function getIndustryWorkshops($date, $type)
      /**Retrieving string technical sessions of type 'Industry Workshop' for schedule */
    {
      $result['title'] = " ";
      $result['secondary'] = 'MicroApps';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightgreen fg-dark top-br";


      $idsMA = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('event_type', 'Industry Workshop,')
        ->execute();

      $limit = 400;

      $groups = array_chunk($idsMA, $limit);
      foreach ($groups as $groupMA) {
        $sessions = stringTechnicalSession::loadMultiple($groupMA);
        foreach ($sessions as $key => $session) {
          $start_date = escapeStringDate($session->start_time->value);
//          $result['date'] = $date;
//          $result['start_date'] = $start_date;
          $ts_type = getTidFromTname(strtoupper($session->type->value));
          if (getTidFromTname($type) === $ts_type){
            if ($start_date === $date) {
              $session_array = toApiArray($session);
              $result['events'][] = $session_array;
            }
          }
        }
      }

      $result['events'] = applyRows($result['events']);

      return $result;

    }

    function getCareerFairs($date, $type)
      /** Retrieving Event_cards where field_event_subcategory = 'Career Fair' for schedule */
    {
      $result['title'] = "Student Program";
      $result['secondary'] = 'Career Fair';
      $result['icon'] = " ";
      $result['cls'] = "bg-lightgreen fg-dark top-br";
      $psid = getPsidFromTname('Career Fair');
      $par = Paragraph::load(getActualParagraphId($psid));
      $result['events'] = applyRows(getEventFieldsFromPar($par, $date, $type));
      if (getEvent2FieldsFromPar($par, $date, $type) !== []) {
        $result['events'] = applyRows(getEvent2FieldsFromPar($par, $date, $type));
      }

      return $result;
    }
    





    $result[] = getTechnicalProgram($date, $type);
    $result[] = getWSAndSC($date, $type);
    $result[] = getPanelSessions($date, $type);
    $result[] = get5GSummits($date, $type);
    $result[] = getRFBootcamps($date, $type);
    $result[] = getstringPlenary($date, $type);
    $result[] = getstringClosing($date, $type);
    $result[] = getAPPapers($date, $type);
    $result[] = getIndustryPapers($date, $type);
    $result[] = get3MTs($date, $type);
    $result[] = getSSPresentations($date, $type);
    $result[] = getHackathon($date, $type);
    $result[] = getInteractiveForums($date, $type);
    $result[] = getExhibitions($date, $type);
    $result[] = getMicroApps($date, $type);
    $result[] = getIndustryWorkshops($date, $type);
    $result[] = getCareerFairs($date, $type);










    $tags[] = 'event_list';

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'tags' => $tags,
        'max-age' => 300,
      ),
    );

    $actions = ['html' => "<span class=\"title-event\">EVENTS</span>"];

    $timeline = [
      'start' => "07:00", //Todo $earliest_start_time
      'stop' => "21:00", // TODO $latest_end_time
      'step' => 5
    ];

    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['actions' => $actions , 'timeline' => $timeline, 'streams' => $result]);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }
}
