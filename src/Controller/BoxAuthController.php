<?php

namespace Drupal\social_auth_box\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_box\BoxAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Returns responses for Simple Box Connect module routes.
 */
class BoxAuthController extends ControllerBase {

  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;

  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;

  /**
   * The box authentication manager.
   *
   * @var \Drupal\social_auth_box\BoxAuthManager
   */
  private $boxManager;

  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;

  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;


  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * BoxAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_box network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_box\BoxAuthManager $box_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $social_auth_data_handler
   *   SocialAuthDataHandler object.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   */
  public function __construct(NetworkManager $network_manager, SocialAuthUserManager $user_manager, BoxAuthManager $box_manager, RequestStack $request, SocialAuthDataHandler $social_auth_data_handler, LoggerChannelFactoryInterface $logger_factory) {

    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->boxManager = $box_manager;
    $this->request = $request;
    $this->dataHandler = $social_auth_data_handler;
    $this->loggerFactory = $logger_factory;

    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_box');

    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
    $this->setting = $this->config('social_auth_box.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_box.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.social_auth_data_handler'),
      $container->get('logger.factory')
    );
  }

  /**
   * Response for path 'user/login/box'.
   *
   * Redirects the user to Box for authentication.
   */
  public function redirectToBox() {
    /* @var \League\OAuth2\Client\Provider\Box false $box */
    $box = $this->networkManager->createInstance('social_auth_box')->getSdk();

    // If box client could not be obtained.
    if (!$box) {
      drupal_set_message($this->t('Social Auth Box not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Box service was returned, inject it to $boxManager.
    $this->boxManager->setClient($box);

    // Generates the URL where the user will be redirected for Box login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $box_login_url = $this->boxManager->getBoxLoginUrl();

    $state = $this->boxManager->getState();

    $this->dataHandler->set('oauth2state', $state);

    return new TrustedRedirectResponse($box_login_url);
  }

  /**
   * Response for path 'user/login/box/callback'.
   *
   * Box returns the user here after user has authenticated in Box.
   */
  public function callback() {
    // Checks if user cancel login via Box.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \League\OAuth2\Client\Provider\Box false $box */
    $box = $this->networkManager->createInstance('social_auth_box')->getSdk();

    // If Box client could not be obtained.
    if (!$box) {
      drupal_set_message($this->t('Social Auth Box not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    $state = $this->dataHandler->get('oauth2state');

    // Retreives $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Box login failed. Unvalid oAuth2 State.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->boxManager->getAccessToken());

    $this->boxManager->setClient($box)->authenticate();

    // Gets user's info from Box API.
    if (!$box_profile = $this->boxManager->getUserInfo()) {
      drupal_set_message($this->t('Box login failed, could not load Box profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($box_profile->getName(), $box_profile->getEmail(), $box_profile->getId(), $this->boxManager->getAccessToken(), $box_profile->getAvatarUrl(), '');

  }

}
