<?php

namespace Drupal\iform_layout_builder\Indicia;

use Drupal\node\Entity\Node;

class SurveyStructure extends IndiciaRestClient {

  public static $savingStructure = FALSE;

  /**
   * Creates survey for a form requesting one.
   *
   * Called on form layout save.
   *
   * @param Node $entity
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
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node) {
      if (empty($blockConfig['option_child_sample_attribute']) && $node->field_sample_method_id->value) {
        $submission["{$attrEntityName}_attributes_websites"][0]['values']['restrict_to_sample_method_id'] = $node->field_sample_method_id->value;
      }
      elseif (!empty($blockConfig['option_child_sample_attribute']) && $node->field_child_sample_method_id->value) {
        $submission["{$attrEntityName}_attributes_websites"][0]['values']['restrict_to_sample_method_id'] = $node->field_child_sample_method_id->value;
      }
    }
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
      \Drupal::messenger()->addError(t('Attribute creation failed. Please fix the following error then save the layout again to create the attribute.'));
      \Drupal::messenger()->addError($response['response']['status'] . ': ' . $response['response']['message']);
    }
    return $response['response'];
  }

  private function updateAttribute($attrEntityName, $blockConfig, $surveyId) {
    $submission = $this->getAttributeSubmission($attrEntityName, $blockConfig, $surveyId);
    $id = $blockConfig['option_existing_attribute_id'];
    // Ensure existing attribute->website link used, not duplicated.
    if (isset($blockConfig['option_existing_attributes_website_id'])) {
      $submission["{$attrEntityName}_attributes_websites"][0]['values']['id'] = $blockConfig['option_existing_attributes_website_id'];
    }
    $response = $this->getRestResponse("{$attrEntityName}_attributes/$id", 'PUT', $submission);
    if ($response['httpCode'] !== 200) {
      \Drupal::logger('iform_layout_builder')->error('Failed to update an attribute.');
      \Drupal::logger('iform_layout_builder')->error('Submission: ' . var_export($submission, TRUE));
      \Drupal::logger('iform_layout_builder')->error('Response: ' . var_export($response, TRUE));
      \Drupal::logger('iform_layout_builder')->error('BlockConfig: ' . var_export($blockConfig, TRUE));
      $explanation = t('Error information: @status: @msg', [
        '@status' => $response['response']['status'],
        '@msg' => isset($response['response']['message']) ? $response['response']['message'] : '',
      ]);
      if ($response['response']['message'] === 'Attempt to PUT or DELETE record from another website.') {
        $explanation = 'This is because the attribute is being used by other surveys on the warehouse so cannot be updated.';
      }
      \Drupal::messenger()->addMessage(t('Updating attribute @label failed. @explanation', [
        '@label' => $blockConfig['option_label'],
        '@explanation' => $explanation,
      ]));
    }
    return $response['response'];
  }

  private function updateAttributeWebsiteLink($attrEntityName, $surveyId, array $blockConfig, array $existingAttr) {
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
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node) {
      if (empty($blockConfig['option_child_sample_attribute']) && $node->field_sample_method_id->value) {
        $submission['values']['restrict_to_sample_method_id'] = $node->field_sample_method_id->value;
      }
      elseif (!empty($blockConfig['child_sample_attribute']) && $node->field_child_sample_method_id->value) {
        $submission['values']['restrict_to_sample_method_id'] = $node->field_child_sample_method_id->value;
      }
    }
    if (!empty($blockConfig['option_child_sample_attribute'])) {
      if ($node && $node->field_child_sample_method_id->value) {
        $submission['values']['restrict_to_sample_method_id'] = $node->field_child_sample_method_id->value;
      }
    }
    $endpoint = "{$attrEntityName}_attributes_websites";
    $existingAttrsWebsiteId = $blockConfig['option_existing_attributes_website_id'] ?? $existingAttr["{attrEntityName}_attributes_website_id"] ?? NULL;
    if (!empty($existingAttrsWebsiteId)) {
      // PUT to update.
      $endpoint .= "/$existingAttrsWebsiteId";
      $response = $this->getRestResponse($endpoint, 'PUT', $submission);
    }
    else {
      // POST to create.
      $response = $this->getRestResponse($endpoint, 'POST', $submission);
    }
    if (!in_array($response['httpCode'], [200, 201])) {
      \Drupal::logger('iform_layout_builder')->error('Failed to link an attribute to a survey dataset. ' . var_export($response, TRUE));
      \Drupal::messenger()->addMessage(t(
        'Failed to link an attribute to a survey dataset: @status: @msg.',
        ['@status' => $response['response']['status'], '@msg' => $response['response']['message']]
      ));
      throw new \Exception('Failed to link an attribute to a survey dataset.');
    }
    $resourceId = $response['response']['values']['id'];
    return $resourceId;
  }

  /**
   * Tidy required status on removed attributes.
   *
   * If an attribute is added to a form and removed, the attribute itself is
   * not deleted in case there is existing data. In this case it is
   * essential that any required validation on the attribute is removed.
   *
   * @param string $attrEntityName
   *   Sample or occurrence.
   * @param int $attrsWebsiteId
   *   {entity}_sample_attributes_website_id for the record to update.
   * @param string $caption
   *   Caption used if any messages need to be shown to the user.
   */
  private function ensureUnlinkedAttrNotRequired($attrEntityName, $attrsWebsiteId, $caption) {
    $config = \Drupal::config('iform.settings');
    $submission = [
      'values' => [
        'website_id' => $config->get('website_id'),
        'validation_rules' => NULL,
      ],
    ];
    $endpoint = "{$attrEntityName}_attributes_websites/$attrsWebsiteId";
    $response = $this->getRestResponse($endpoint, 'PUT', $submission);
    if ($response['httpCode'] === 404) {
      \Drupal::messenger()->addWarning(t(
        "The \"@caption\" @type attribute originally added to this form had its survey validation settings set to required, so a value is expected. However as the attribute has been removed from the form it will prevent records being saved. The configuration needs to be corrected on the warehouse.",
        ['@caption' => $caption, '@type' => $attrEntityName]
      ));
    }
    elseif (!in_array($response['httpCode'], [200, 201])) {
      \Drupal::logger('iform_layout_builder')->error('Failed to set unlinked attribute to not required.');
      \Drupal::messenger()->addMessage(t(
        'Failed to set unlinked attribute to not required: @status: @msg.',
        ['@status' => $response['response']['status'], '@msg' => $response['response']['message']]
      ));
      throw new \Exception('Failed to set unlinked attribute to not required.');
    }
  }

  /**
   * Ensures that all attributes required by an entity exist.
   *
   * @param Node $entity
   *   Drupal entity.
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
    // Tracking for attributes on the page, so we can detect removals.
    $attrsOnLayout = [
      'sample' => [],
      'occurrence' => [],
    ];
    foreach ($sections as $section) {
      $components = $section->getComponents();
      foreach ($components as $component) {
        $asArray = $component->toArray();
        $blockConfig = $asArray['configuration'];
        if (preg_match('/data_entry_(sample|occurrence)_custom_attribute_block/', $blockConfig['id'], $matches)) {
          $attrType = $matches[1];
          if ($blockConfig['option_create_or_existing'] === 'new' || $blockConfig['option_existing_attribute_id'] === NULL) {
            // Create a new attribute, which will also link to the survey.
            $createResponse = $this->createAttribute($attrType, $blockConfig, $entity->field_survey_id->value);
            if (!empty($createResponse['href'])) {
              $fetch = $this->getRestResponse($createResponse['href'], 'GET');
              $attr = $fetch['response'];
              $blockConfig['option_create_or_existing'] = 'existing';
              $blockConfig['option_existing_attribute_id'] = $attr['values']['id'];
              $blockConfig['option_existing_termlist_id'] = $attr['values']['termlist_id'];
              // Also store the attributes_website link ID, required when
              // updating.
              $blockConfig['option_existing_attributes_website_id'] = $createResponse["{$attrType}_attributes_websites"][0]['values']['id'];
              $component->setConfiguration($blockConfig);
              \Drupal::messenger()->addMessage(t(
                'A new @type attribute has been created on the warehouse with ID @id for the @label control.',
                [
                  '@type' => $attrType,
                  '@id' => $attr['values']['id'],
                  '@label' => $blockConfig['option_label'],
                ]
              ));
            }
          }
          else {
            if (empty($blockConfig['option_existing_attributes_website_id'])) {
              // Fill in the ID if there is an existing attr website link.
              $existingAttrWebsiteLink = $this->getRestResponse("{$attrType}_attributes_websites", 'GET', NULL, [
                "{$attrType}_attribute_id" => $blockConfig['option_existing_attribute_id'],
                "restrict_to_survey_id" => $entity->field_survey_id->value,
              ]);
              if (count($existingAttrWebsiteLink['response']) > 0) {
                $blockConfig['option_existing_attributes_website_id'] = $existingAttrWebsiteLink['response'][0]['values']['id'];
                $component->setConfiguration($blockConfig);
              }
            }
            if (empty($blockConfig['option_data_type'])) {
              // Block is for a new link to an existing attribute, so just need
              // to fill in missing block config from the attribute metadata on
              // the warehouse.
              $existing = $this->getAttribute($attrType, $blockConfig['option_existing_attribute_id']);
              $blockConfig['option_admin_name'] = $existing['response']['values']['caption'];
              $blockConfig['option_data_type'] = $existing['response']['values']['data_type'];
              if ($blockConfig['option_data_type'] === 'L') {
                $blockConfig['option_existing_termlist_id'] = $existing['response']['values']['termlist_id'];
              }
              $blockConfig['option_suffix'] = $existing['response']['values']['unit'];
              if (isset($existing['response']['terms'])) {
                $blockConfig['option_lookup_options_terms'] = implode("\n", $existing['response']['terms']);
              }
              $component->setConfiguration($blockConfig);
              $this->updateAttributeWebsiteLink(
                $attrType,
                $entity->field_survey_id->value,
                $blockConfig,
                array_key_exists($blockConfig['option_existing_attribute_id'], $existingAttrs[$attrType])
                  ? $existingAttrs[$attrType][$blockConfig['option_existing_attribute_id']] : []
              );
            }
            elseif (!empty($blockConfig['dirty'])) {
              // Don't save if there are no changes.
              unset($blockConfig['dirty']);
              $component->setConfiguration($blockConfig);
              // Block config is for an existing attribute which is already linked.
              $attrsOnLayout[$attrType][] = $blockConfig['option_existing_attribute_id'];
              // If user is has attribute admin rights then update the warehouse
              // attribute caption, description, validation rules etc.
              if (false && $attrAdmin) {
                $this->updateAttribute($attrType, $blockConfig, $entity->field_survey_id->value);
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
                    ? $existingAttrs[$attrType][$blockConfig['option_existing_attribute_id']] : []
                );
              }
            }
          }
        }
      }
    }
    // Now, ensure any attributes that are in the survey but not on the layout
    // (maybe removed) are not required.
    foreach ($existingAttrs['sample'] as $existingAttr) {
      if (!in_array($existingAttr['id'], $attrsOnLayout['sample']) && strpos($existingAttr['survey_validation_rules'] ?? '', 'required') !== FALSE) {
        $this->ensureUnlinkedAttrNotRequired('sample', $existingAttr['sample_attributes_website_id'], $existingAttr['caption']);
      }
    }
    foreach ($existingAttrs['occurrence'] as $existingAttr) {
      if (!in_array($existingAttr['id'], $attrsOnLayout['occurrence']) && strpos($existingAttr['survey_validation_rules'] ?? '', 'required') !== FALSE) {
        $this->ensureUnlinkedAttrNotRequired('occurrence', $existingAttr['occurrence_attributes_website_id'], $existingAttr['caption']);
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
    if ($response['httpCode'] !== 200) {
      \Drupal::logger('iform_layout_builder')->error('Invalid response from GET surveys request: ' . var_export($response, TRUE));
      throw new \Exception(t('Invalid response from GET surveys request'));
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
    if ($response['httpCode'] !== 200) {
      \Drupal::logger('iform_layout_builder')->error('Invalid response from GET attributes request: ' . var_export($response, TRUE));
      throw new \Exception(t('Invalid response from GET attributes request'));
    }
    $r = [];
    foreach ($response['response'] as $attr) {
      $r[$attr['values']['id']] = $attr['values']['caption'];
    }
    asort($r);
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
    if ($response['httpCode'] !== 200) {
      \Drupal::logger('iform_layout_builder')->error('Invalid response from GET attributes request: ' . var_export($response, TRUE));
      throw new \Exception(t('Invalid response from GET attributes request'));
    }
    $r = [];
    foreach ($response['response'] as $attr) {
      $r[$attr['values']['id']] = $attr['values'];
    }
    return $r;
  }

}