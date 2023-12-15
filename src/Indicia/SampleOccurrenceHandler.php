<?php

namespace Drupal\iform_layout_builder\Indicia;

use data_entry_helper;

/**
 * A class for handling RESTful interactions with samples and occurrences.
 */
class SampleOccurrenceHandler extends IndiciaRestClient {
  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * Post a sample/occurrence submission.
   *
   * @param array $data
   *   Posted form data.
   * @param object $entity
   *   Drupal entity defining what type of form it is.
   *
   * @todo Uses data services so convert to REST API.
   */
  public function postRecord($data, $entity) {
    $config = \Drupal::config('iform.settings');
    $auth = \data_entry_helper::get_read_write_auth($config->get('website_id'), $config->get('password'));
    $isDeletion = !empty($_POST['action']) && $_POST['action'] === 'DELETE';
    $values = array_merge($data, [
      'website_id' => $config->get('website_id'),
      'survey_id' => $entity->field_survey_id->value,
      'sample:input_form' => trim(\Drupal::service('path_alias.manager')
        ->getAliasByPath('/node/' . $entity->id()), '/'),
    ]);
    if ($isDeletion) {
      $values['sample:deleted'] = 't';
    }
    // Link to recording group/activity.
    if (!empty($_GET['group_id'])) {
      $values['sample:group_id'] = $_GET['group_id'];
    }
    $zeroAttrs = $this->getAbundanceAttrs($auth['read'], $entity->field_survey_id->value);
    if ($entity->field_form_type->value === 'single') {
      $submission = \data_entry_helper::build_sample_occurrence_submission($values, $zeroAttrs);
    }
    elseif ($entity->field_form_type->value === 'list') {
      $submission = \data_entry_helper::build_sample_occurrences_list_submission($values, FALSE, $zeroAttrs);
    }
    elseif ($entity->field_form_type->value === 'multiplace') {
      echo $entity->field_form_type->value . ' submission build.<br/>';
      $submission = \data_entry_helper::build_sample_subsamples_occurrences_submission($values, FALSE, $zeroAttrs);

    }
    $response = \data_entry_helper::forward_post_to('save', $submission, $auth['write_tokens']);
    if (is_array($response) && array_key_exists('success', $response)) {
      if ($isDeletion && $entity->field_form_type->value === 'single') {
        \Drupal::messenger()->addMessage("The record has been deleted.");
      }
      elseif ($isDeletion) {
        \Drupal::messenger()->addMessage("The list of records has been deleted.");
      }
      else {
        \Drupal::messenger()->addMessage("Thank you for the record.");
      }
    }
    elseif (isset($response['errors'])) {
      \data_entry_helper::dump_errors($response);
    }
  }

  /**
   * Finds the attributes for occurrence abundance.
   *
   * Used for zero abundance processing. Attribute term lists are also loaded.
   *
   * @param array $readAuth
   *   Read authorisation tokens.
   * @param int $surveyId
   *   Survey ID.
   *
   * @return array
   *   List of attribute definitions loaded from DB, keyed by attribute ID.
   */
  private function getAbundanceAttrs($readAuth, $surveyId) {
    $attrOpts = [
      'valuetable' => 'occurrence_attribute_value',
      'attrtable' => 'occurrence_attribute',
      'key' => 'occurrence_id',
      'fieldprefix' => 'occAttr',
      'extraParams' => $readAuth,
      'survey_id' => $surveyId,
    ];
    $attrs = \data_entry_helper::getAttributes($attrOpts, FALSE);
    $abundanceAttrs = [];
    foreach ($attrs as &$attr) {
      if ($attr['system_function'] === 'sex_stage_count') {
        // If we have any lookups, we need to load the terms so we can check
        // the data properly for zero abundance as term Ids are never zero.
        if ($attr['data_type'] === 'L') {
          $attr['terms'] = \data_entry_helper::get_population_data([
            'table' => 'termlists_term',
            'extraParams' => $readAuth + ['termlist_id' => $attr['termlist_id'], 'view' => 'cache', 'columns' => 'id,term'],
            'cachePerUser' => FALSE,
          ]);
        }
        $abundanceAttrs[$attr['attributeId']] = $attr;
      }
    }
    return $abundanceAttrs;
  }

  /**
   * Copies data from a REST response values array to $entity_to_load.
   *
   * Prepares the default values for data entry helper controls according to
   * loaded data for existing records.
   */
  private function copyEntityValueToLoadData($values, $prefix, $entityName) {
    foreach ($values as $key => $value) {
      if (substr($key, 0, 8) === "{$prefix}Attr:") {
        $fieldKey = $key;
        if ($value['multi_value'] === 'f') {
          $value = $value['raw_value'];
        }
        else {
          // Convert format to multi-value format recognised by data entry
          // helper.
          $multiVal = [];
          foreach ($value as $item) {
            $multiVal[] = [
              'fieldname' => "$fieldKey:$item[value_id]",
              'default' => $item['raw_value'],
            ];
          }
          $value = $multiVal;
        }
      }
      else {
        $fieldKey = "$entityName:$key";
        // Taxon label for autocomplete needs a special field name.
        if ($fieldKey === 'occurrence:taxon') {
          $fieldKey = 'occurrence:taxa_taxon_list_id:taxon';
        }
      }
      \data_entry_helper::$entity_to_load[$fieldKey] = $value;
    }

  }

  /**
   * Loads sample or occurrence media from the REST API.
   *
   * Stores the media data in $entity_to_load so it's picked up by a form in
   * edit mode.
   *
   * @param string $entityType
   *   Base entity, i.e. sample or occurrence.
   * @param array $entity
   *   Array of entity data.
   */
  private function loadMedia($entityType, array $entity) {
    $mediaEntity = "{$entityType}_medium";
    $mediaEntityPlural = "{$entityType}_media";
    $images = $this->getRestResponse($mediaEntityPlural, 'GET', NULL, ["{$entityType}_id" => $entity['id']]);
    foreach ($images['response'] as $image) {
      $imageId = $image['values']['id'];
      \data_entry_helper::$entity_to_load["$mediaEntity:id:$imageId"] = $imageId;
      \data_entry_helper::$entity_to_load["$mediaEntity:path:$imageId"] = $image['values']['path'];
      \data_entry_helper::$entity_to_load["$mediaEntity:caption:$imageId"] = $image['values']['caption'];
      \data_entry_helper::$entity_to_load["$mediaEntity:media_type:$imageId"] = $image['values']['media_type'];
      \data_entry_helper::$entity_to_load["$mediaEntity:media_type_id:$imageId"] = $image['values']['media_type_id'];
    }
  }

  /**
   * Load an occurrence ready to show on a form for editing.
   *
   * @param int $id
   *   Occurrence ID.
   * @param object $formEntity
   *   Drupal entity defining what type of form it is.
   */
  public function getExistingOccurrence($id, $formEntity) {
    $response = $this->getRestResponse("occurrences/$id", 'GET', NULL, ['verbose' => 1]);
    $occurrence = $response['response']['values'];
    $response = $this->getRestResponse("samples/$occurrence[sample_id]", 'GET', NULL, ['verbose' => 1]);
    $sample = $response['response']['values'];
    \data_entry_helper::$entity_to_load = [];
    $this->copyEntityValueToLoadData($sample, 'smp', 'sample');
    $this->loadMedia('sample', $sample);
    if ($formEntity->field_form_type->value === 'single') {
      $this->copyEntityValueToLoadData($occurrence, 'occ', 'occurrence');
      $this->loadMedia('occurrence', $occurrence);
    }
  }

  /**
   * Load a sample ready to show on a form for editing.
   *
   * @param int $id
   *   Sample ID.
   * @param object $formEntity
   *   Drupal entity defining what type of form it is.
   */
  public function getExistingSample($id, $formEntity) {
    $response = $this->getRestResponse("samples/$id", 'GET', NULL, ['verbose' => 1]);
    if (isset($response['response']['code']) && $response['response']['code'] === 404) {
      \Drupal::messenger()->addWarning($this->t('The existing sample could not be found, possibly because it has been deleted or was input by another user.'));
      return;
    }
    $sample = $response['response']['values'];
    \data_entry_helper::$entity_to_load = [];
    $this->copyEntityValueToLoadData($sample, 'smp', 'sample');
    $this->loadMedia('sample', $sample);
    if ($formEntity->field_form_type->value === 'single') {
      $response = $this->getRestResponse("occurrences", 'GET', NULL, [
        'verbose' => 1,
        'sample_id' => $sample['id'],
      ]);
      if (count($response['response']) === 1) {
        $occurrence = $response['response'][0]['values'];
        $this->copyEntityValueToLoadData($occurrence, 'occ', 'occurrence');
        $this->loadMedia('occurrence', $occurrence);
      }
      else {
        \Drupal::messenger()->addWarning($this->t('Only samples containing a single occurrence can load on this form.'));
      }
    }
  }
}