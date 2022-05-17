<?php

namespace Drupal\mrmilu_readable_url\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ReadableUrlForm extends FormBase {

  public function getFormId() {
    return 'mrmilu_readable_url';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $langcodeString = \Drupal::languageManager()->getCurrentLanguage()->getName();

    $form['container'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('messages', 'messages--status')),
    );
    $form['container']['wrapper_language'] = array(
      '#markup' => strtoupper($langcodeString) . ' Variables'
    );
    $form['block_content_inscription'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#default_value' => null
    ];


    $form['footer_hour'] = [
      '#type' => 'textfield',
      '#title' => t('Service hours'),
      '#default_value' => \Drupal::state()->get('footer_hour'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['footer_copyright'] = [
      '#type' => 'textfield',
      '#title' => t('Copyright'),
      '#default_value' => \Drupal::state()->get('footer_copyright'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['footer_bottom_text'] = [
      '#type' => 'textfield',
      '#title' => t('Bottom text'),
      '#default_value' => \Drupal::state()->get('footer_bottom_text'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $fileId = \Drupal::state()->get('footer_bottom_icon');
    $form['footer_bottom_icon'] = array(
      '#type' => 'managed_file',
      '#title' => t('Bottom icon'),
      '#description' => t('Upload image, allowed extensions: jpg png'),
      '#upload_location' => 'public://footer/',
      '#upload_validators'  => array(
        'file_validate_extensions' => array('jpg png'),
      ),
      '#default_value' => $fileId ? [$fileId] : NULL,
      '#required' => TRUE,
    );


    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $vars = ['footer_hour', 'footer_copyright', 'footer_bottom_text'];
    foreach ($vars as $var) \Drupal::state()->set($var, $form_state->getValue($var));

    $fileID = $form_state->getValue('footer_bottom_icon');
    $this->setFile('footer_bottom_icon', $fileID);

    \Drupal::messenger()->addMessage(t('Saved footer variables'));
  }

  private function setFile($varState, $fileID) {
    if ($fileID) {
      $fileID = array_shift($fileID);
      \Drupal::state()->set($varState, $fileID);
    }
    else \Drupal::state()->delete($varState);
  }
}
