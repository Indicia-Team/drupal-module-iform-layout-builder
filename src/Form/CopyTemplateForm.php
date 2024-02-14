<?php

/**
 * @file
 * Contains \Drupal\iform_layout_builder\Form\CopyTemplateForm.
 */

namespace Drupal\iform_layout_builder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\iform_layout_builder\Indicia\SurveyStructure;

class CopyTemplateForm extends FormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Class constructor.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'iform_layout_builder_copy_template_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    if (empty($_GET['template_node_id'])) {
      $this->messenger->addWarning($this->t('Invalid call - template_node_id missing.'));
      return $form;
    }
    $templateNode = \Drupal\node\Entity\Node::load($_GET['template_node_id']);
    if (!$templateNode || $templateNode->getType() !== 'iform_layout_builder_form' || $templateNode->field_template->value !== '1') {
      $this->messenger->addWarning($this->t('Invalid call - template_node_id parameter does not refer to a form template.'));
      return $form;
    }

    $form['info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Template information'),
    ];
    $form['info']['instruction'] = [
      '#markup' => '<p class="alert alert-info">' .
        $this->t('This tool creates a copy of the <strong>@title</strong> template which you can then refine to make a custom form for your survey records.',
        ['@title' => $templateNode->getTitle()]) .
        '</p>'
    ];
    $form['info']['body-title'] = [
      '#markup' => '<h3>' . $this->t('Template description') . '</h3>',
    ];
    $form['info']['body'] = [
      '#markup' => $templateNode->body->value,
    ];
    $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $_GET['template_node_id']]);
    $form['info']['link'] = [
      '#title' => $this->t('View the template in a new tab'),
      '#type' => 'link',
      '#url' => $url,
      '#attributes' => ['target' => '_blank'],
    ];
    $form['survey_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter a title for your form'),
      '#required' => TRUE,
    ];
    $form['survey_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter a description for your form and the collected data.'),
      '#required' => TRUE,
    ];
    $form['template_node_id'] = [
      '#type' => 'hidden',
      '#value' => $_GET['template_node_id'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy the template'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $templateNode = \Drupal\node\Entity\Node::load($form_state->getValue('template_node_id'));
    if (!$templateNode || $templateNode->getType() !== 'iform_layout_builder_form' || $templateNode->field_template->value !== '1') {
      $this->messenger->addWarning($this->t('Invalid call - template_node_id parameter does not refer to a form template.'));
      return;
    }
    $clonedNode = $templateNode->createDuplicate();
    $clonedNode->setTitle($form_state->getValue('survey_title'));
    $clonedNode->set('body', $form_state->getValue('survey_description'));
    $clonedNode->set('field_template', 0);
    // Trigger creation of a new dataset.
    $clonedNode->save();
    // Create new survey selected.
    $surveyStructure = new SurveyStructure();
    $surveyStructure->createSurvey($clonedNode);
    $surveyStructure->checkAttrsExists($clonedNode);
  }

}
