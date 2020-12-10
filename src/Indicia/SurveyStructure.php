<?php

namespace Drupal\iform_layout_builder\Indicia;

use Drupal\Core\Entity\EntityInterface;

class SurveyStructure extends IndiciaRestClient {

  public static $savingStructure = FALSE;

  /**
   * Creates survey for a form requesting one.
   *
   * Called on form layout save.
   *
   * @param obj $entity
   *   Node entity containing the form.
   */
  public function createSurvey($entity) {
    $config = \Drupal::config('iform.settings');
    $submission = [
      'values' => [
        'website_id' => $config->get('website_id'),
        'title' => $entity->getTitle(),
        'description' => 'Survey dataset generated by iform_layout_builder.',
      ],
    ];
    $response = $this->getRestResponse('surveys', 'POST', $submission);
    if ($response['httpCode'] === 201) {
      // HTTP response resource created.
      $entity->set('field_survey_id', $response['response']['values']['id']);
      $entity->save();
      \Drupal::messenger()->addMessage(t(
        'A survey dataset has been created on the warehouse with ID @id. Use the layout tab to configure the form.',
        ['@id' => $response['response']['values']['id']]
      ));
    }
    else {
      $message = empty($response['response']['message']) ? $response['errorMessage'] : $response['response']['message'];
      \Drupal::messenger()->addError(t('Attempt to save a survey failed: @error', ['@error' => $message]));
      \Drupal::logger('iform_layout_builder')->error('Attempt to save a survey failed: ' . var_export($response, TRUE));
    }
    return $entity->field_survey_id->value;
  }

  public function getAttribute($attrEntityName, $id) {
    return $this->getRestResponse("{$attrEntityName}_attributes/$id", 'GET');
  }

  /**
   * Builds the submission array for an attribute from block config.
   */
  private function getAttributeSubmission($attrEntityName, $blockConfig, $surveyId) {
    $config = \Drupal::config('iform.settings');
    $submission = [
      'values' => [
        'caption' => $blockConfig['option_admin_name'],
        'description' => $blockConfig['option_admin_description'],
        'data_type' => $blockConfig['option_data_type'],
        'unit' => $blockConfig['option_suffix'],
        'multi_value' => $blockConfig['option_data_type'] === 'L' && $blockConfig['option_lookup_options_control'] === 'checkbox_group'
          ? 't' : 'f',
      ],
      "{$attrEntityName}_attributes_websites" => [
        [
          'values' => [
            'restrict_to_survey_id' => $surveyId,
            'website_id' => $config->get('website_id'),
            'validation_rules' => $blockConfig['option_required'] === 1 ? 'required' : NULL,
          ],
        ],
      ],
    ];
    if ($blockConfig['option_data_type'] === 'L') {
      if (!empty($blockConfig['option_lookup_options_terms'])) {
        // Pass through the terms to auto-generate. Safe way to split strings by line.
        $termsText = str_replace("\r\n", "\n", $blockConfig['option_lookup_options_terms']);
        $termsText = str_replace("\r", "\n", $termsText);
        $submission['terms'] = explode("\n", trim($termsText));
      }
      if (!empty($blockConfig['option_existing_termlist_id'])) {
        $submission['values']['termlist_id'] = $blockConfig['option_existing_termlist_id'];
      }
    }
    return $submission;
  }

  /**
   * @todo Merge update and createAttribute into 1 function?
   */
  private function createAttribute($attrEntityName, $blockConfig, $surveyId) {
    $submission = $this->getAttributeSubmission($attrEntityName, $blockConfig, $surveyId);
    $response = $this->getRestResponse("{$attrEntityName}_attributes", 'POST', $submission);
    if ($response['httpCode'] !== 201) {
      \Drupal::logger('iform_layout_builder')->error('Failed to create an attribute.');
      \Drupal::logger('iform_layout_builder')->error('Submission: ' . var_export($submission, TRUE));
      \Drupal::logger('iform_layout_builder')->error('Response: ' . var_export($response, TRUE));
      \Drupal::logger('iform_layout_builder')->error('BlockConfig: ' . var_export($blockConfig, TRUE));
      \Drupal::messenger()->addMessage(t(
        'Attribute creation failed: @status: @msg.',
        ['@status' => $response['response']['status'], '@msg' => $response['response']['message']]
      ));
    }
    return $response['response'];
  }

  private function updateAttribute($attrEntityName, $blockConfig, $surveyId) {
    $submission = $this->getAttributeSubmission($attrEntityName, $blockConfig, $surveyId);
    $id = $blockConfig['option_existing_attribute_id'];
    // Ensure existing attribute->website link used, not duplicated.
    $submission["{$attrEntityName}_attributes_websites"][0]['values']['id'] = $blockConfig['option_existing_attributes_website_id'];
    $response = $this->getRestResponse("{$attrEntityName}_attributes/$id", 'PUT', $submission);
    if ($response['httpCode'] !== 200) {
      \Drupal::logger('iform_layout_builder')->error('Failed to update an attribute.');
      \Drupal::logger('iform_layout_builder')->error('Submission: ' . var_export($submission, TRUE));
      \Drupal::logger('iform_layout_builder')->error('Response: ' . var_export($response, TRUE));
      \Drupal::logger('iform_layout_builder')->error('BlockConfig: ' . var_export($blockConfig, TRUE));
      \Drupal::messenger()->addMessage(t(
        'Attribute update failed: @status: @msg.',
        ['@status' => $response['response']['status'], '@msg' => $response['response']['message']]
      ));
      throw new \Exception('Failed to update an attribute.');
    }
    return $response['response'];
  }

  private function updateAttributeWebsiteLink($attrEntityName, $surveyId, $blockConfig, $existingAttr) {
    $config = \Drupal::config('iform.settings');
    $submission = [
      'values' => [
        "{$attrEntityName}_attribute_id" => $blockConfig['option_existing_attribute_id'],
        'website_id' => $config->get('website_id'),
        'restrict_to_survey_id' => $surveyId,
        // Required rule goes in the website link as likely to be different per
        // survey dataset.
        'validation_rules' => $blockConfig['option_required'] === 1 ? 'required' : NULL,
      ],
    ];
    $endpoint = "{$attrEntityName}_attributes_websites";
    if (!empty($existingAttr) && !empty($existingAttr["{attrEntityName}_attributes_website_id"])) {
      // PUT to update.
      $resourceId = $existingAttr["{attrEntityName}_attributes_website_id"];
      $endpoint .= "/$resourceId";
      $response = $this->getRestResponse($endpoint, 'PUT', $submission);
    }
    else {
      // POST to create.
      $response = $this->getRestResponse($endpoint, 'POST', $submission);
      $resourceId = $response['response']['values']['id'];
    }
    if (!in_array($response['httpCode'], [200, 201])) {
      \Drupal::logger('iform_layout_builder')->error('Failed to link an attribute to a survey dataset.');
      \Drupal::messenger()->addMessage(t(
        'Failed to link an attribute to a survey dataset: @status: @msg.',
        ['@status' => $response['response']['status'], '@msg' => $response['response']['message']]
      ));
      throw new \Exception('Failed to link an attribute to a survey dataset.');
    }
    return $resourceId;
  }

  /**
   * Ensures that all attributes required by an entity exist.
   */
  public function checkAttrsExists($entity) {
    if (empty($entity->field_survey_id->value)) {
      \Drupal::messenger()->addError(t('Cannot create attributes until survey created.'));
      return;
    }
    $existingAttrs = [
      'occurrence' => $this->getExistingCustomAttributesForSurvey('occurrence', $entity->field_survey_id->value),
      'sample' => $this->getExistingCustomAttributesForSurvey('sample', $entity->field_survey_id->value),
    ];
    $sections = $entity->get('layout_builder__layout')->getSections();
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $attrAdmin = $user->hasPermission('administer indicia attributes');
    foreach ($sections as $section) {
      $components = $section->getComponents();
      foreach ($components as $component) {
        $asArray = $component->toArray();
        $blockConfig = $asArray['configuration'];
        if (preg_match('/data_entry_(sample|occurrence)_custom_attribute_block/', $blockConfig['id'], $matches)) {
          $attrType = $matches[1];
          if ($blockConfig['option_create_or_existing'] === 'new' || $blockConfig['option_existing_attribute_id'] === NULL) {
            // Create a new attribute, which will also link to the survey.
            $createResponse = $this->createAttribute($matches[1], $blockConfig, $entity->field_survey_id->value);
            $fetch = $this->getRestResponse($createResponse['href'], 'GET');
            $attr = $fetch['response'];
            $blockConfig['option_create_or_existing'] = 'existing';
            $blockConfig['option_existing_attribute_id'] = $attr['values']['id'];
            $blockConfig['option_existing_termlist_id'] = $attr['values']['termlist_id'];
            // Also store the attributes_website link ID, helpful when updating.
            $blockConfig['option_existing_attributes_website_id'] = $createResponse['sample_attributes_websites'][0]['values']['id'];
            $component->setConfiguration($blockConfig);
            \Drupal::messenger()->addMessage(t(
              'A new @type attribute has been created on the warehouse with ID @id for the @label control.',
              [
                '@type' => $matches[1],
                '@id' => $attr['values']['id'],
                '@label' =>  $blockConfig['option_label'],
              ]
            ));
          }
          else {
            // If user is has attribute admin rights then update the warehouse
            // attribute caption, description, validation rules etc.
            if ($attrAdmin) {
              $this->updateAttribute($matches[1], $blockConfig, $entity->field_survey_id->value);
            }
            else {
              // User not admin but can still update the link data between the
              // attribute and survey, e.g. the required validation rule in
              // attributes_websites link.
              $this->updateAttributeWebsiteLink(
                $attrType,
                $entity->field_survey_id->value,
                $blockConfig,
                array_key_exists($blockConfig['option_existing_attribute_id'], $existingAttrs[$attrType])
                  ? $existingAttrs[$attrType][$blockConfig['option_existing_attribute_id']] : NULL
              );
            }
          }
        }
      }
    }
  }

  /**
   * Retrieve a simple list of available survey titles keyed by ID.
   *
   * @return array
   *   Associative array of surveys.
   */
  public function getSurveyList() {
    try {
      $response = $this->getRestResponse('surveys', 'GET');
    }
    catch (\Exception $e) {
      if (substr($e->getMessage(), 0, 40) === 'JSON response could not be decoded: 404 ') {
        \Drupal::messenger()->addError(t('The warehouse needs the REST API module installed.'));
        return [];
      }
      else {
        throw $e;
      }
    }
    $r = [];
    foreach ($response['response'] as $survey) {
      $r[$survey['values']['id']] = $survey['values']['title'];
    }
    return $r;
  }

  /**
   * Retrieve a simple list of available attribute titles keyed by ID.
   *
   * @param string $entity
   *   Entity name (sample or occurrence).
   *
   * @return array
   *   Associative array of attributes IDs & captions.
   */
  public function getExistingCustomAttributeCaptions($entity) {
    $response = $this->getRestResponse("{$entity}_attributes", 'GET', NULL, ['public'=>'f']);
    $r = [];
    foreach ($response['response'] as $attr) {
      $r[$attr['values']['id']] = $attr['values']['caption'];
    }
    return $r;
  }

  /**
   * Retrieve a list of attributes for a survey, keyed by ID.
   *
   * @param string $entity
   *   Entity name (sample or occurrence).
   * @param integer $survey_id
   *   ID of the survey.
   *
   * @return array
   *   Associative array of attributes IDs & attribute values.
   */
  public function getExistingCustomAttributesForSurvey($entity, $survey_id) {
    $response = $this->getRestResponse("{$entity}_attributes", 'GET', NULL, ['public'=>'f', 'restrict_to_survey_id' => $survey_id]);
    $r = [];
    foreach ($response['response'] as $attr) {
      $r[$attr['values']['id']] = $attr['values'];
    }
    return $r;
  }

}