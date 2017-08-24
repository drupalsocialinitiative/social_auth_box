<?php

namespace Drupal\social_auth_box\Settings;

/**
 * Defines an interface for Social Auth Box settings.
 */
interface BoxAuthSettingsInterface {

  /**
   * Gets the client ID.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId();

  /**
   * Gets the client secret.
   *
   * @return string
   *   The client secret.
   */
  public function getClientSecret();

}
