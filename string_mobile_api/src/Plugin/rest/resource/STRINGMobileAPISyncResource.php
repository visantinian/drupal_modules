<?php

namespace Drupal\string_mobile_api\Plugin\rest\resource;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\field_collection\Entity\FieldCollectionItem;
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
 * Provides a resource for database watchdog log entries.
 *
 * @RestResource(
 *   id = "string_mobile_api_sync",
 *   label = @Translation("string: Main app sync"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/sync/{endpoint}",
 *   }
 * )
 */
class STRINGMobileAPISyncResource extends ResourceBase
{

    public function get($endpoint = '')
    {
        $timestamp = \Drupal::request()->query->get('_ts');
        if (empty($timestamp)) {
            $timestamp = 0;
        }

        // responce format - full or ids
        $format = \Drupal::request()->query->get('_f');
        if (empty($format)) {
            $format = 'ids';
        }

        // ids to load (optional)
        $ids = \Drupal::request()->query->get('_ids');
        if (empty($ids)) {
            $ids = [];
        }

        // ids to load (optional)
        $base64_img = \Drupal::request()->query->get('_base64_img');
        if (empty($base64_img)) {
            $base64_img = false;
        }

        //view_mode
        $vm = \Drupal::request()->query->get('_vm');
        if (empty($vm)) {
          $vm = "default";
        }


    switch ($endpoint) {
      case 'content':
        return $this->getContent($timestamp, $format, $ids, $base64_img);
        break;
      case 'tech_session':
        return $this->getTechnicalSessions($timestamp, $format, $ids, $base64_img);
        break;
      case 'tech_session_papers':
        return $this->getTechnicalSessionsPapers($timestamp, $format, $ids, $base64_img);
        break;
      case 'tech_session_authors':
        return $this->getTechnicalSessionsAuthors($timestamp, $format, $ids, $base64_img);
        break;
      case 'exhibitors':
        return $this->getExhibitors($timestamp, $format, $ids, $base64_img);
        break;
      case 'event_cards':
        return $this->getEventCards($timestamp, $format, $ids, $base64_img, $vm);
        break;
      case 'event':
        return $this->getEvents($timestamp, $format, $ids);

      default:
        break;
    }
  }

  protected function getContent($timestamp, $format, $ids, $base64_img)
  {
    $items = \Drupal::service('entity.manager')->getStorage('string_mobile_api_item')->loadMultiple();
    if (empty($ids)) {
      foreach ($items as $id => $item) {
        if ($item->getType() == STRINGItemTypeNode::TYPE_ID && $item->getEnabled()) {
          $ids[] = $item->getContent();
        }
      }
    }

    $config = \Drupal::config('string_mobile_api.settings');
    $view_mode = !empty($config->get('view_modes.node.view_mode')) ? $config->get('view_modes.node.view_mode') : 'full';
    $config_changed = !empty($config->get('view_modes.node.changed')) ? $config->get('view_modes.node.changed') : 0;

    $nodes = Node::loadMultiple($ids);
    $result = [];
    $tags = [];
    foreach ($nodes as $node) {
      $changed = $node->get('changed')->getValue()[0]['value'];
      if ($changed > $timestamp || $config_changed > $timestamp) {
        $tags[] = 'node:' . $node->id();
        if ($format == 'full') {
          $nodeView = node_view($node, $view_mode);
          $content = mb_convert_encoding(\Drupal::service('renderer')->renderPlain($nodeView), 'html-entities', 'utf-8');
          $doc = new \DOMDocument();
          $doc->validateOnParse = true;
          $libxml_previous_state = libxml_use_internal_errors(true);
          $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT);
          $errors = libxml_get_errors();
          libxml_use_internal_errors($libxml_previous_state);
          if ($errors) {
            $errorStack = [];
            foreach ($errors as $error) {
              $errorStack[] = $error->message;
            }
            \Drupal::logger('string_mobile_api')
              ->warning('DOMDocument::loadHTML() for node @nid: %errors.', [
                '@nid' => $node->id(),
                '%errors' => implode('; ', $errorStack)
              ]);
          }
          $img_tags = $doc->getElementsByTagName('img');
          foreach ($img_tags as $img) {
            if ($base64_img && $src = $this->image_base64($img->getAttribute('src'), $node->id())) {
              $img->setAttribute('src', $src);
            } else {
              $img->setAttribute('src', '//' . \Drupal::request()->getHost() . $img->getAttribute('src'));
            }
          }
          $links = $doc->getElementsByTagName('a');
          foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && stripos($href, '//') !== 0 && stripos($href, 'http') !== 0 && stripos($href, 'mailto:') !== 0) {
              if (stripos($href, '/') === 0) {
                $link->setAttribute('href', 'https://' . \Drupal::request()->getHost() . $href);
              } else {
                $link->setAttribute('href', 'https://' . \Drupal::request()->getHost() . '/' . $href);
              }
            }
          }
          $content = $doc->saveHTML();
          $content = str_replace(PHP_EOL, '', $content);
          $content = preg_replace('/\s+/', ' ', $content);
          $result[$node->id()] = $content;
        } else {
          $result[] = $node->id();
        }
      }
    }

    $tags[] = 'node_list';

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'tags' => $tags,
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'result' => 1, 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  protected function getTechnicalSessions($timestamp, $format, $ids, $base64_img)
  {
    $config = \Drupal::config('string_mobile_api.settings');
    $view_mode = !empty($config->get('view_modes.string_technical_session.view_mode')) ? $config->get('view_modes.string_technical_session.view_mode') : 'full';
    $config_changed = !empty($config->get('view_modes.string_technical_session.changed')) ? $config->get('view_modes.string_technical_session.changed') : 0;
    $filtersConfig = \Drupal::config('string_mobile_api.filters');
    $filters = $filtersConfig->get('string_technical_session');

    if ($config_changed > $timestamp && $format == 'full_html') {
      $query = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0);
      if ($filters) {
        foreach ($filters as $field => $values) {
          if ($values['values']) {
            $query->condition($field, array_values($values['values']), 'NOT IN');
          }
        }
      }
      $ids = $query->execute();
    } else if (empty($ids) || !array($ids)) {
      $query = \Drupal::entityQuery('string_technical_session')
        ->condition('removed', 0)
        ->condition('changed', $timestamp, '>');
      if ($filters) {
        foreach ($filters as $field => $values) {
          if ($values['values']) {
            $query->condition($field, array_values($values['values']), 'NOT IN');
          }
        }
      }
      $ids = $query->execute();
    }

    $query = \Drupal::entityQuery('string_technical_session');

    $group = $query->orConditionGroup()->condition('removed', 1);

    if ($filters) {
      foreach ($filters as $field => $values) {
        if ($values['values']) {
          $query->condition($field, array_values($values['values']), 'IN');
        }
      }
    }

    $result['remove'] = $query
      ->condition('changed', $timestamp, '>')
      ->condition($group)
      ->execute();
    if ($format == 'full' || $format == 'full_html') {
      $result['edit'] = [];
      $limit = 400;
      $groups = array_chunk($ids, $limit);
      foreach ($groups as $group) {
        $sessions = stringTechnicalSession::loadMultiple($group);
        foreach ($sessions as $key => $session) {
          $session->set('event_abstract', htmlspecialchars_decode($session->get('event_abstract')->value), TRUE);
          $session_array = $session->toApiArray();
          if ($format == 'full_html') {
            $entityView = entity_view($session, $view_mode);
            $html = \Drupal::service('renderer')->renderRoot($entityView);
            $html = str_replace(PHP_EOL, '', $html);
            $html = preg_replace('/\s+/', ' ', $html);
            $session_array['html'] = htmlspecialchars_decode(html_entity_decode($html));
          }
          $result['edit'][] = $session_array;
          unset($session);
          unset($session_array);
        }
        unset($sessions);
      }
    } else {
      $result['edit'] = array_values($ids);
    }

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'result' => 1, 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  protected function getTechnicalSessionsPapers($timestamp, $format, $ids, $base64_img)
  {
    $config = \Drupal::config('string_mobile_api.settings');
    $view_mode = !empty($config->get('view_modes.string_technical_session_paper.view_mode')) ? $config->get('view_modes.string_technical_session_paper.view_mode') : 'full';
    $config_changed = !empty($config->get('view_modes.string_technical_session_paper.changed')) ? $config->get('view_modes.string_technical_session_paper.changed') : 0;

    if ($config_changed > $timestamp) {
      $ids = \Drupal::entityQuery('string_technical_session_paper')
        ->condition('removed', 0)
        ->execute();

      $result['remove'] = \Drupal::entityQuery('string_technical_session_paper')
        ->condition('removed', 1)
        ->condition('changed', $timestamp, '>')
        ->execute();
    } else if (empty($ids) || !array($ids)) {
      $ids = \Drupal::entityQuery('string_technical_session_paper')
        ->condition('removed', 0)
        ->condition('changed', $timestamp, '>')
        ->execute();

      $result['remove'] = \Drupal::entityQuery('string_technical_session_paper')
        ->condition('removed', 1)
        ->condition('changed', $timestamp, '>')
        ->execute();
    }

    if ($format == 'full' || $format == 'full_html') {
      $result['edit'] = [];
      $limit = 400;
      $groups = array_chunk($ids, $limit);
      foreach ($groups as $group) {
        $papers = stringTechnicalSessionPaper::loadMultiple($group);
        foreach ($papers as $key => $paper) {
          $paper_array = $paper->toApiArray();
          if ($format == 'full_html') {
            $entityView = entity_view($paper, $view_mode);
            $html = \Drupal::service('renderer')->renderRoot($entityView);
            $html = str_replace(PHP_EOL, '', $html);
            $html = preg_replace('/\s+/', ' ', $html);
            $paper_array['html'] = $html;
          }
          $result['edit'][] = $paper_array;
          unset($paper);
          unset($paper_array);
        }
        unset($papers);
      }
    } else {
      $result['edit'] = array_values($ids);
    }

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'result' => 1, 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  protected  function getEvents($timestamp, $format, $ids)
  {

    function getActualParagraphId($paragraphId)
    {

      $connection = \Drupal::database();
      $sql = "SELECT  id  FROM {paragraphs_library_item_field_data} WHERE paragraphs__target_id = :paras"; // 2 dashes!! _ _
      $query = $connection->query($sql, [':paras' => $paragraphId])->fetchAll();
      $query = json_decode(json_encode($query), True);

      return $query[0]['id'];
    }

    function excludeYoutube($string) {
      $regexp = '/<p><iframe(.+)<\/iframe><\/p>/';
      return preg_replace($regexp, "",$string);
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


    function getFCO($id)
    {
      return FieldCollectionItem::load($id);
    }

    function getYouTubeFieldsT($obj)
    {
      $youtube_fields = [];
      if ($obj !== null) {
        if ($obj->hasField('field_youtube_codes')) {
          $codes = $obj->get('field_youtube_codes')->getValue();
          foreach ($codes as $key => $code) {
            $youtube_fields[$key] = [];
            $address = $code['value'];
            if (getFCO($address)->get('field_youtube_alt')->getValue() !== null) {
              $alt = ['youtube_alt' => getFCO($address)->get('field_youtube_alt')->getValue()[0]['value']];
              $youtube_fields[$key] = $youtube_fields[$key] + $alt;
            }
            if (getFCO($address)->get('field_youtube_code')->getValue() !== null) {
              $codeyu = ['youtube_code' => getFCO($address)->get('field_youtube_code')->getValue()[0]['value']];
              $youtube_fields[$key] = $youtube_fields[$key] + $codeyu;
            }
          }
        }
        if ($obj->hasField('field_youtube_alt')) {
          if ($obj->get('field_youtube_alt')->getValue() !== null) {
            $alt = ['youtube_alt' => $obj->get('field_youtube_alt')->getValue()[0]['value']];
            $youtube_fields = $youtube_fields + $alt;
          }

          if ($obj->get('field_youtube_code')->getValue() !== null) {
            $codeyu = ['youtube_code' => $obj->get('field_youtube_code')->getValue()[0]['value']];
            $youtube_fields = $youtube_fields + $codeyu;
          }
        }
        return $youtube_fields;
      }
    }

    function getParent($event)
    {
      $id = $event->id();
      $result = [];
      $connection = \Drupal::database();
      $sql1 = "SELECT entity_id  FROM {paragraph__field_event} WHERE field_event_target_id = :event_id";
      $query1 = $connection->query($sql1, [':event_id' => $id])->fetchAll();
      $query1 = json_decode(json_encode($query1), True);
      $address1 = $query1[0]['entity_id'];

      $sql2 = "SELECT entity_id  FROM {paragraph__field_event2} WHERE field_event2_target_id = :event_id";
      $query2 = $connection->query($sql2, [':event_id' => $id ])->fetchAll();
      $query2 =  (json_decode(json_encode($query2), True));
      $address2 = $query2[0]['entity_id'];

      $apid1 = getActualParagraphId($address1);
      $apid2 = getActualParagraphId($address2);
      $sql3 = "SELECT entity_id  FROM {node__field_choose_paragraph} WHERE field_choose_paragraph_target_id = :result";
      $query3 = $connection->query($sql3, [':result' => $apid1])->fetchAll();
      $query3 = json_decode(json_encode($query3), True);
//dpm($query3);
      $query4 = $connection->query($sql3, [':result' => $apid2])->fetchAll();
      $query4 = json_decode(json_encode($query4), True);
        $result = $query3;
        if ($query4 !== []) {
          $result = $query4;
        }

      return $result;
    }


    function toApiArray($arg)
    {
      $event = \Drupal::entityTypeManager()->getStorage('node')->load($arg->id());
      if ($event) {
        $event_category_ref = $arg->get('field_event_category')->getValue()[0]['target_id'];
        $event_subcategory_ref = $arg->get('field_event_subcategory')->getValue()[0]['target_id'];
        $event_category = \Drupal\taxonomy\Entity\Term::load($event_category_ref)->name->value;
        $event_subcategory = \Drupal\taxonomy\Entity\Term::load($event_subcategory_ref)->name->value;

        $fields = [
          'id' => $event->id(),
          'title' => $event->label(),
          'event_category' => $event_category,
          'event_subcategory' => $event_subcategory,
          'date' => $event->get('field_date')->getValue()[0]['value'],
          'date_end' => $event->get('field_date_end')->getValue()[0]['value'],
          'start_time' => mediaTimeDeFormatter($event->get('field_time_range')->getValue()[0]['from']),
          'end_time' => mediaTimeDeFormatter($event->get('field_time_range')->getValue()[0]['to']),
          'text_time' => $event->get('field_text_time')->getValue()[0]['value'],
          'description' => excludeYoutube($event->get('field_body')->getValue()[0]['value']),
          'location' => $event->get('field_location')->getValue()[0]['value']
        ];
        if (!empty(getParent($event))) {
          $parent_paragraph = getParent($event)[0]['entity_id'];
          $fields["parent_paragraph"] = $parent_paragraph;
        }
          $yotubeCodesAddresses = $event->get('field_youtube_codes')->getValue();
        foreach ($yotubeCodesAddresses as $key => $youtubeCodeAddress) {
          $obj = getFCO($youtubeCodeAddress['value']);
          $yufields = getYouTubeFieldsT($obj);
          $fields["youtube_$key"] = $yufields;
        }
        return $fields;
      }
    }

    if (empty($ids) || !array($ids)) {
      $ids = \Drupal::entityQuery('node')
        ->condition('type', 'event')
        ->condition('changed', $timestamp, '>')
        ->execute();

      if ($format === 'full' || $format === 'full_html') {

        $limit = 400;
        $groups = array_chunk($ids, $limit);
        foreach ($groups as $group) {
          $events = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($group);
          foreach ($events as $key => $event) {
            $event_result = toApiArray($event);
            $result[] = $event_result;
          }
        }
      } else {
        $result = array_values($ids);
      }
    } elseif ($ids) {
      $event_single = \Drupal::entityTypeManager()->getStorage('node')->load($ids);
      $result[] = toApiArray($event_single);
    }




    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  protected function getEventCards($timestamp, $format, $ids, $base64_img, $vm)
  {

    function getActualParagraphId($paragraphId)
    {

      $connection = \Drupal::database();
      $sql = "SELECT DISTINCT paragraphs__target_id  FROM {paragraphs_library_item_field_data} WHERE id = :paras"; // 2 dashes!! _ _
      $query = $connection->query($sql, [':paras' => $paragraphId])->fetchAll();
      $query = json_decode(json_encode($query), True);

      return $query[0]['paragraphs__target_id'];
    }

    function getFCO($id)
    {
      return FieldCollectionItem::load($id);
    }

    function excludeYoutube($string) {
      $regexp = '/<p><iframe(.+)<\/iframe><\/p>/';
      return preg_replace($regexp, "",$string);
    }


    function getYouTubeFieldsT($obj)
    {
      $youtube_fields = [];
      if ($obj !== null) {
        if ($obj->hasField('field_youtube_codes')) {
          $codes = $obj->get('field_youtube_codes')->getValue();
          foreach ($codes as $key => $code) {
            $youtube_fields[$key] = [];
            $address = $code['value'];
            if (getFCO($address)->get('field_youtube_alt')->getValue() !== null) {
              $alt = ['youtube_alt' => getFCO($address)->get('field_youtube_alt')->getValue()[0]['value']];
              $youtube_fields[$key] = $youtube_fields[$key] + $alt;
            }
            if (getFCO($address)->get('field_youtube_code')->getValue() !== null) {
              $codeyu = ['youtube_code' => getFCO($address)->get('field_youtube_code')->getValue()[0]['value']];
              $youtube_fields[$key] = $youtube_fields[$key] + $codeyu;
            }
          }
        }
        if ($obj->hasField('field_youtube_alt')) {
          if ($obj->get('field_youtube_alt')->getValue() !== null) {
            $alt = ['youtube_alt' => $obj->get('field_youtube_alt')->getValue()[0]['value']];
            $youtube_fields = $youtube_fields + $alt;
          }

          if ($obj->get('field_youtube_code')->getValue() !== null) {
            $codeyu = ['youtube_code' => $obj->get('field_youtube_code')->getValue()[0]['value']];
            $youtube_fields = $youtube_fields + $codeyu;
          }
        }
        return $youtube_fields;
      }
    }


    function getBodyValue($obj)
    {
      $result = [];
      $bodies = $obj->get('field_body')->getValue();
      if ($bodies !== null) {
        foreach ($bodies as $key => $body) {
          $result['value'] = excludeYoutube($body['value']);
          $result['format'] = $body['format'];
        }
      }
      return $result;
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

    function getEventFields1($obj)
    {
      $event_fields1 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];

      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_event')) {
          $paragraph_events1 = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_event')->getValue();
          if (is_array($paragraph_events1)) {
            foreach ($paragraph_events1 as $paragraph_event_id => $nid) {
              $length = count($paragraph_events1);
              $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);
              $i = 0;
              do {
                $fields = [
                  'id' => $paragraph_event->id(),
                  'title' => $paragraph_event->label(),
                  'date' => $paragraph_event->get('field_date')->getValue()[0]['value'],
                  'date_end' => $paragraph_event->get('field_date_end')->getValue()[0]['value'],
                  'start_time' => mediaTimeDeFormatter($paragraph_event->get('field_time_range')->getValue()[0]['from']),
                  'end_time' => mediaTimeDeFormatter($paragraph_event->get('field_time_range')->getValue()[0]['to']),
                  'text_time' => $paragraph_event->get('field_text_time')->getValue()[0]['value'],
                  'description' => excludeYoutube($paragraph_event->get('field_body')->getValue()[0]['value']),
                  'location' => $paragraph_event->get('field_location')->getValue()[0]['value']
                ];
                $yotubeCodesAddresses = $paragraph_event->get('field_youtube_codes')->getValue();
                foreach ($yotubeCodesAddresses as $key => $youtubeCodeAddress) {
                  $obj = getFCO($youtubeCodeAddress['value']);
                  $yufields = getYouTubeFieldsT($obj);
                  $fields["youtube_$key"] = $yufields;
                }
                $i++;
              } while ($i <= $length);
              array_push($event_fields1, $fields);
            }
            return $event_fields1;
          }
        }
      }
      return null;
    }

    function getEventFields2($obj)
    {
      $event_fields2 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_event2')) {
          $paragraph_events2 = Paragraph::load(getActualParagraphId($paragraph_id))->get('field_event2')->getValue();
          if (is_array($paragraph_events2)) {
            foreach ($paragraph_events2 as $paragraph_event_id => $nid) {
              $length = count($paragraph_events2);
              $paragraph_event = \Drupal::entityTypeManager()->getStorage('node')->load($nid['target_id']);
              $i = 0;
              do {
                $fields = [
                  'id' => $paragraph_event->id(),
                  'title' => $paragraph_event->label(),
                  'date' => $paragraph_event->get('field_date')->getValue()[0]['value'],
                  'date_end' => $paragraph_event->get('field_date_end')->getValue()[0]['value'],
                  'start_time' => mediaTimeDeFormatter($paragraph_event->get('field_time_range')->getValue()[0]['from']),
                  'end_time' => mediaTimeDeFormatter($paragraph_event->get('field_time_range')->getValue()[0]['to']),
                  'text_time' => $paragraph_event->get('field_text_time')->getValue()[0]['value'],
                  'description' => excludeYoutube($paragraph_event->get('field_body')->getValue()[0]['value']),
                  'location' => $paragraph_event->get('field_location')->getValue()[0]['value']
                ];
                $yotubeCodesAddresses = $paragraph_event->get('field_youtube_codes')->getValue();
                foreach ($yotubeCodesAddresses as $key => $youtubeCodeAddress) {
                  $obj = getFCO($youtubeCodeAddress['value']);
                  $yufields = getYouTubeFieldsT($obj);
                  $fields["youtube_$key"] = $yufields;
                }
                $i++;
              } while ($i <= $length);
              array_push($event_fields2, $fields);
            }
            return $event_fields2;
          }
        }
      }
      return null;
    }

    function getTextFields1($obj)
    {
      $paragraph_textfields1 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_textfield1_fc')) {
          /**   if (is_array(Paragraph::load(getActualParagraphId($paragraph_id))->get('field_textfield')->getValue())) {
           * $paragraph_txt_count = count(Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue());
           * $k = 0;
           * do {
           * $paragraph_textfield['value'] = Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue()[$k]['value'];
           * $paragraph_textfield['format'] = Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue()[$k]['format'];
           * if ($paragraph_textfield['value'] != null) {
           * array_push($paragraph_textfields1, $paragraph_textfield);
           * }
           * $k++;
           * } while ($k <= $paragraph_txt_count);
           **/
          $ids = $loaded_paragraph->get('field_textfield1_fc')->getValue();
          foreach ($ids as $key => $id) {
            $bodyAddress = $id['value'];
            $fc = getFCO($bodyAddress);
            $paragraph_textfields1[$key]['body'] = getBodyValue($fc);
            if (getYouTubeFieldsT($fc) !== []) {
              $paragraph_textfields1[$key]['youtube'] = getYouTubeFieldsT($fc);
            }
          }
          return $paragraph_textfields1;
        }
      }
      return null;
    }

    function getTextFields2($obj)
    {
      $paragraph_textfields2 = [];
      $paragraph_id = $obj->get('field_choose_paragraph')->getValue()[0]['target_id'];
      $loaded_paragraph = Paragraph::load(getActualParagraphId($paragraph_id));
      if ($loaded_paragraph) {
        if ($loaded_paragraph->hasField('field_textfield2_fc')) {
          /**   if (is_array(Paragraph::load(getActualParagraphId($paragraph_id))->get('field_textfield')->getValue())) {
           * $paragraph_txt_count = count(Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue());
           * $k = 0;
           * do {
           * $paragraph_textfield['value'] = Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue()[$k]['value'];
           * $paragraph_textfield['format'] = Paragraph::load(getActualParagraphId($paragraph_id))
           * ->get('field_textfield')
           * ->getValue()[$k]['format'];
           * if ($paragraph_textfield['value'] != null) {
           * array_push($paragraph_textfields1, $paragraph_textfield);
           * }
           * $k++;
           * } while ($k <= $paragraph_txt_count);
           **/
          $ids = $loaded_paragraph->get('field_textfield2_fc')->getValue();
          foreach ($ids as $key => $id) {
            $bodyAddress = $id['value'];
            $fc = getFCO($bodyAddress);
            $paragraph_textfields2[$key]['body'] = getBodyValue($fc);
            if (getYouTubeFieldsT($fc) !== []) {
              $paragraph_textfields2[$key]['youtube'] = getYouTubeFieldsT($fc);
            }
          }
          return $paragraph_textfields2;
        }
      }
      return null;
    }


    function toApiArray($arg)
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
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($arg->id());

      $resulted = [
        'id' => $node->id(),
        'title' => $node->label()
      ];
      $a = 0;
      $b = 0;

      if (getEventType($node) != null) {
        $eventType = getEventType($node);
        $added = ["event_type" => $eventType];
        $resulted = array_merge($resulted, $added);
      }
      
      if (getEventCategory($node) != null) {
        $eventCategory = getEventCategory($node);
        $added = ["event_category" => $eventCategory];
        $resulted = array_merge($resulted, $added);
      }

      if (getEventCategory($node) != null) {
        $eventCategory = getEventCategory($node);
        $added = ["event_category" => $eventCategory];
        $resulted = array_merge($resulted, $added);
      }

      if (getEventSubCategory($node) != null) {
        $eventSubCategory = getEventSubCategory($node);
        $added = ["event_subcategory" => $eventSubCategory];
        $resulted = array_merge($resulted, $added);
      }

      if (getColorConfigId($node) != null) {
        $colorConfigId = getColorConfigId($node);
        $added = ["color_config_id" => $colorConfigId];
        $resulted = array_merge($resulted, $added);
      }

      if (getEventFields1($node) != null) {
        $addedEvents1 = getEventFields1($node);
        foreach ($addedEvents1 as $addedEvent) {
          $added = ["event_$a" => $addedEvent];
          $resulted = array_merge($resulted, $added);
          $a++;
        }
      }


      if (getTextFields1($node) != null) {
        $addedTextFields1 = getTextFields1($node);
        foreach ($addedTextFields1 as $textfield) {
          $added = ["textfield_$b" => $textfield];
          $resulted = array_merge($resulted, $added);
          $b++;
        }
      }

      if (getEventFields2($node) != null) {
        $addedEvents2 = getEventFields2($node);
        foreach ($addedEvents2 as $addedEvent) {
          $added = ["event_$a" => $addedEvent];
          $resulted = array_merge($resulted, $added);
          $a++;
        }
      }

      if (getTextFields2($node) != null) {
        $addedTextFields2 = getTextFields2($node);
        foreach ($addedTextFields2 as $textfield) {
          $added = ["textfield_$b" => $textfield];
          $resulted = array_merge($resulted, $added);
          $b++;
        }
      }


      return $resulted;
    }

    function toApiVm($arg) {

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($arg->id());

      $resulted = [
        'id' => $node->id(),
        'title' => $node->label()
      ];

      if (getEventType($node) != null) {
        $eventType = getEventType($node);
        $added = ["event_type" => $eventType];
        $resulted = array_merge($resulted, $added);
      }

      if (getEventFields1($node) != null) {
        $addedEvents1 = getEventFields1($node);
        foreach ($addedEvents1 as $addedEvent) {
          $added = ["events" => $addedEvent];
          $resulted = array_merge($resulted, $added);
        }
      }


      if (getEventFields2($node) != null) {
        $addedEvents2 = getEventFields2($node);
        foreach ($addedEvents2 as $addedEvent) {
          $added = ["events" => $addedEvent];
          $resulted = array_merge($resulted, $added);
        }
      }


      return $resulted;

    }

    if (empty($ids) || !array($ids)) {
      $ids = \Drupal::entityQuery('node')
        ->condition('type', 'event_cards')
        ->condition('changed', $timestamp, '>')
        ->execute();

      if ($format === 'full' || $format === 'full_html') {

        $limit = 400;
        $groups = array_chunk($ids, $limit);
        foreach ($groups as $group) {
          $event_cards = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($group);
          foreach ($event_cards as $key => $event_card) {
            if ($vm === "default") {
              $event_result = toApiArray($event_card);
            }
            if ($vm === "array") {
              $event_result[] = toApiVM($event_card);
            }

            $result[] = $event_result;
          }
        }
      } else {
        $result = array_values($ids);
      }
    } elseif ($ids) {
      $event_card_single = \Drupal::entityTypeManager()->getStorage('node')->load($ids);
      if ($vm === "default") {
        $result[] = toApiArray($event_card_single);
      }
      if ($vm === "array") {
        $result[] = toApiVM($event_card_single);
      }
    }





    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }


  protected function getTechnicalSessionsAuthors($timestamp, $format, $ids, $base64_img)
  {
    if (empty($ids) || !array($ids)) {
      $ids = \Drupal::entityQuery('string_technical_session_author')
        ->condition('removed', 0)
        ->condition('changed', $timestamp, '>')
        ->execute();

      $result['remove'] = \Drupal::entityQuery('string_technical_session_author')
        ->condition('removed', 1)
        ->condition('changed', $timestamp, '>')
        ->execute();
    }

    if ($format == 'full' || $format == 'full_html') {
      $result = [];

      $limit = 400;
      $groups = array_chunk($ids, $limit);
      foreach ($groups as $group) {
        $authors = stringTechnicalSessionAuthor::loadMultiple($group);
        foreach ($authors as $key => $author) {
          $result[] = $author->toApiArray();
          unset($author);
        }
        unset($authors);
      }

    } else {
      $result['edit'] = array_values($ids);
    }

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  protected function getExhibitors($timestamp, $format, $ids, $base64_img)
  {
    $result = [];
    $config = \Drupal::config('string_mobile_api.settings');
    $view_mode = !empty($config->get('view_modes.string_exhibitor_entity.view_mode')) ? $config->get('view_modes.string_exhibitor_entity.view_mode') : 'default';
    $config_changed = !empty($config->get('view_modes.string_exhibitor_entity.changed')) ? $config->get('view_modes.string_exhibitor_entity.changed') : 0;

    if ($config_changed > $timestamp) {
      $ids = \Drupal::entityQuery('string_exhibitor_entity')
        ->condition('removed', 0)
        ->execute();

      $result['remove'] = \Drupal::entityQuery('string_exhibitor_entity')
        ->condition('removed', 1)
        ->condition('changed', $timestamp, '>')
        ->execute();
    } else if (empty($ids) || !array($ids)) {
      $ids = \Drupal::entityQuery('string_exhibitor_entity')
        ->condition('changed', $timestamp, '>')
        ->condition('removed', 0)
        ->execute();

      $result['remove'] = \Drupal::entityQuery('string_exhibitor_entity')
        ->condition('removed', 1)
        ->condition('changed', $timestamp, '>')
        ->execute();
    }
    $exhibitors = stringExhibitorEntity::loadMultiple($ids);

    foreach ($exhibitors as $exhibitor) {
      // if ($changed > $timestamp || ($config_changed > $timestamp || $view_mode_changed > $timestamp)) {
      if ($format == 'full' || $format == 'full_html') {
        $data = array(
          'id' => (int)$exhibitor->id(),
          'title' => $exhibitor->label(),
          'content' => \Drupal::service('renderer')->renderPlain(entity_view($exhibitor, $view_mode))
        );
        $result['edit'][] = $data;
      } else {
        $result['edit'][] = $exhibitor->id();
      }
      // }
    }

    $build = array(
      '#cache' => array(
        'contexts' => ['url.query_args'],
        'max-age' => 300,
      ),
    );

    $cache_metadata = \Drupal\Core\Cache\CacheableMetadata::createFromRenderArray($build);
    $response = new ResourceResponse(['ts' => time(), 'result' => 1, 'data' => $result]);
    $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  protected function image_base64($imagepath, $nid)
  {
    $filename = '';
    $imagepath = urldecode($imagepath);
    if (stripos($imagepath, 'http') === 0) {
      $filename = $imagepath;
    } else {
      $filename = \Drupal::root() . $imagepath;
    }

    if ($filename && file_exists($filename)) {
      $filesize = filesize($filename);
      if ($filesize >= 524288) {
        \Drupal::logger('string_mobile_api')
          ->notice('Resource %resource for node @nid exceeds 500 Kb. Filesize: @filesize.', [
            '%resource' => $filename,
            '@nid' => $nid,
            '@filesize' => format_size($filesize)
          ]);
      }
      return 'data:' . mime_content_type($filename) . ';base64,' . base64_encode(file_get_contents($filename));
    } else {
      \Drupal::logger('string_mobile_api')
        ->warning('Resource %resource for node @nid does not exist or is not accessible.', [
          '@nid' => $nid,
          '%resource' => $filename
        ]);
      return '';
    }
  }


}
