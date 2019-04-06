<?php

namespace Drupal\social_auth_box\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\social_auth\Form\SocialAuthSettingsForm;

/**
 * Settings form for Social Auth Box.
 */
class BoxAuthSettingsForm extends SocialAuthSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_auth_box_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array_merge(
      parent::getEditableConfigNames(),
      ['social_auth_box.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('social_auth_box.settings');

    $form['box_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Box Client settings'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a Box App at <a href="@box-dev">@box-dev</a>',
        ['@box-dev' => 'https://app.box.com/developers/console/']),
    ];

    $form['box_settings']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here.'),
    ];

    $form['box_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#description' => $this->t('Copy the Client Secret here.'),
    ];

    $form['box_settings']['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['box_settings']['advanced']['scopes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scopes for API call'),
      '#default_value' => $config->get('scopes'),
      '#description' => $this->t('Define any additional scopes to be requested, separated by a comma (e.g.: item_preview,item_upload).<br>
                                  No scopes is added by default.<br>
                                  You can see the full list of valid scopes <a href="@scopes">here</a>.',
                                  ['@scopes' => 'https://developer.box.com/reference#section-scopes']),
    ];

    $form['box_settings']['advanced']['endpoints'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API calls to be made to collect data'),
      '#default_value' => $config->get('endpoints'),
      '#description' => $this->t('Define the Endpoints to be requested when user authenticates with Box for the first time<br>
                                  Enter each endpoint in different lines in the format <em>endpoint</em>|<em>name_of_endpoint</em>.<br>
                                  <b>For instance:</b><br>
                                  /2.0/events/|events<br>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('social_auth_box.settings')
      ->set('client_id', $values['client_id'])
      ->set('client_secret', $values['client_secret'])
      ->set('scopes', $values['scopes'])
      ->set('endpoints', $values['endpoints'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
