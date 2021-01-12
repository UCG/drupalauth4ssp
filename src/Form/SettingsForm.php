<?php

namespace Drupal\drupalauth4ssp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure DrupalAuth for SimpleSAMLphp settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['is_possible_idp_session_cookie_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Possible IdP session" Cookie Name'),
      '#default_value' => $this
        ->config('drupalauth4ssp.settings')
        ->get('is_possible_idp_session_cookie_name'),
      '#description' => $this->t('The name of the cookie indicating there is possibly a session on the IdP.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupalauth4ssp_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('drupalauth4ssp.settings')
      ->set('authsource', $form_state->getValue('is_possible_idp_session_cookie_name'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['drupalauth4ssp.settings'];
  }

}
