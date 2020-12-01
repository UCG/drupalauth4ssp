<?php

namespace Drupal\drupalauth4ssp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure DrupalAuth for SimpleSAMLphp settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupalauth4ssp_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['drupalauth4ssp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['authsource'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authsource'),
      '#default_value' => $this
        ->config('drupalauth4ssp.settings')
        ->get('authsource'),
      '#description' => $this->t('The machine name of the authsource used in SimpleSAMLphp.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('drupalauth4ssp.settings')
      ->set('authsource', $form_state->getValue('authsource'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
