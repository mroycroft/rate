<?php

/**
 * @file
 * Contains \Drupal\rate\Form\RateSettingsForm.
 */

namespace Drupal\rate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\comment\Entity\CommentType;
use Drupal\node\Entity\NodeType;

class RateSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rate_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = $this->config('rate.settings');

    $form['bot'] = [
      '#type' => 'fieldset',
      '#title' => t('Bot detection'),
      '#description' => t('Bots can be automatically banned from voting if they rate more than a given amount of votes within one minute or hour. This threshold is configurable below. Votes from the same IP-address will be ignored forever after reaching this limit.'),
      '#collapsbile' => FALSE,
      '#collapsed' => FALSE,
    ];

    $options = array_combine([0, 10, 25, 50, 100, 250, 500, 1000], [
      0,
      10,
      25,
      50,
      100,
      250,
      500,
      1000,
    ]);
    $options[0] = t('disable');

    $form['bot']['bot_minute_threshold'] = array(
     '#type' => 'select',
     '#title' => t('1 minute threshold'),
     '#options' => $options,
     '#default_value' => $config->get('bot_minute_threshold', 25),
    );

    $form['bot']['bot_hour_threshold'] = array(
      '#type' => 'select',
      '#title' => t('1 hour threshold'),
      '#options' => $options,
      '#default_value' => $config->get('bot_hour_threshold', 250),
    );

    $form['bot']['botscout_key'] = array(
      '#type' => 'textfield',
      '#title' => t('BotScout.com API key'),
      '#default_value' => $config->get('botscout_key', ''),
      '#description' => t('Rate will check the voters IP against the BotScout database if it has an API key. You can request a key at %url.', array('%url' => 'http://botscout.com/getkey.htm')),
    );

    $form['rate_types_enabled'] = array(
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#title' => t('Entity types with Rate widgets enabled:'),
      '#description' => t('If you disable any type here, already existing data will remain untouched.'),
    );

    foreach (NodeType::loadMultiple() as $type) {
      $id = 'node_' . $type->id() . '_available';
      $form['vote_types_enabled'][$id] = array(
        '#type' => 'checkbox',
        '#title' => $type->label(),
        '#default_value' => $config->get($id, 0),
      );
    }
    if (\Drupal::moduleHandler()->moduleExists('comment')) {
      foreach (CommentType::loadMultiple() as $type) {
        $id = 'comment_' . $type->id() . '_available';
        $form['vote_types_enabled'][$id] = array(
          '#type' => 'checkbox',
          '#title' => $type->label(),
          '#default_value' => $config->get($id, 0),
        );
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    if ($form_state->getValue(['botscout_key'])) {
      $uri = "http://botscout.com/test/?ip=84.16.230.111&key=" . $form_state->getValue(['botscout_key']);
      try {
        $response = \Drupal::httpClient()->get($uri, array('headers' => array('Accept' => 'text/plain')));
        $data = (string) $response->getBody();
        $status_code = $response->getStatusCode();
        if (empty($data)) {
          drupal_set_message(t('An empty response was returned from botscout.'), 'warning');
        }
        elseif ($status_code == 200) {
          if ($data{0} == 'Y' || $data{0} == 'N') {
            drupal_set_message(t('Rate has succesfully contacted the BotScout server.'));
          }
          else {
            $form_state->setErrorByName('botscout_key', t('Invalid API-key.'));
          }
        }
        else {
          drupal_set_message(t('Rate was unable to contact the BotScout server.'), 'warning');
        }
      }
      catch (RequestException $e) {
        drupal_set_message(t('An error occurred contacting BotScout.'), 'warning');
        watchdog_exception('rate', $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('rate.settings');

    foreach (NodeType::loadMultiple() as $type) {
      $id = 'node_' . $type->id() . '_available';
      $config->set($id, $form_state->getValue($id))->save();
    }

    if (\Drupal::moduleHandler()->moduleExists('comment')) {
      foreach (CommentType::loadMultiple() as $type) {
        $id = 'comment_' . $type->id() . '_available';
        $config->set($id, $form_state->getValue($id))->save();
      }
    }

    $config->set('bot_minute_threshold', $form_state->getValue('bot_minute_threshold'))
      ->set('bot_hour_threshold', $form_state->getValue('bot_hour_threshold'))
      ->set('botscout_key', $form_state->getValue('botscout_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}