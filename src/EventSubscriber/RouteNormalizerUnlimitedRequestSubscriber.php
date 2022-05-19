<?php

namespace Drupal\mrmilu_readable_url\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RequestHelper;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\mrmilu_readable_url\Helper\UrlsHelper;
use Drupal\redirect\RedirectChecker;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Normalizes GET requests performing a redirect if required.
 *
 * The normalization can be disabled by setting the "_disable_route_normalizer"
 * request parameter to TRUE. However, this should be done before
 * onKernelRequestRedirect() method is executed.
 */
class RouteNormalizerUnlimitedRequestSubscriber implements EventSubscriberInterface {

  protected $config;
  protected $urlGenerator;
  protected $pathMatcher;
  protected $redirectChecker;
  private $languages;

  /**
   * Constructs a RouteNormalizerRequestSubscriber object.
   *
   * @param UrlGeneratorInterface $url_generator
   *   The URL generator service.
   * @param PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param ConfigFactoryInterface $config
   *   The config.
   * @param RedirectChecker $redirect_checker
   *   The redirect checker service.
   *   The value of the route_normalizer_enabled container parameter.
   */
  public function __construct(UrlGeneratorInterface $url_generator, PathMatcherInterface $path_matcher,
                              ConfigFactoryInterface $config, RedirectChecker $redirect_checker, LanguageManagerInterface $languageManager) {
    $this->urlGenerator = $url_generator;
    $this->pathMatcher = $path_matcher;
    $this->redirectChecker = $redirect_checker;
    $this->config = $config->get('redirect.settings');

    $languages = $languageManager->getLanguages();
    $this->languages = array_keys($languages);
  }

  /**
   * Performs a redirect if the URL changes in routing.
   *
   * The redirect happens if a URL constructed from the current route is
   * different from the requested one. Examples:
   * - Language negotiation system detected a language to use, and that language
   *   has a path prefix: perform a redirect to the language prefixed URL.
   * - A route that's set as the front page is requested: redirect to the front
   *   page.
   * - Requested path has an alias: redirect to alias.
   *
   * @param RequestEvent $event
   *   The Event to process.
   */
  public function onKernelRequestRedirect(RequestEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    $urls = $this->getUrlsWithFilters();

    $found = $this->foundUrls($path, $urls);
    if (!$found) {
      return;
    }

    if ($request->attributes->get('_disable_route_normalizer')) {
      return;
    }

    if ($this->redirectChecker->canRedirect($request)) {
      // The "<current>" placeholder can be used for all routes except the front
      // page because it's not a real route.
      $route_name = $this->pathMatcher->isFrontPage() ? '<front>' : '<current>';

      // Don't pass in the query here using $request->query->all()
      // since that can potentially modify the query parameters.
      $options = ['absolute' => TRUE];
      $redirect_uri = $this->urlGenerator->generateFromRoute($route_name, [], $options);

      // Strip off query parameters added by the route such as a CSRF token.
      if (strpos($redirect_uri, '?') !== FALSE) {
        $redirect_uri  = strtok($redirect_uri, '?');
      }

      // Add pager query param if exists
      if ($request->query->has('page')) {
        $redirect_uri .= '?page=' . $request->query->get('page');
      }

      // Remove /index.php from redirect uri the hard way.
      if (!RequestHelper::isCleanUrl($request)) {
        // This needs to be fixed differently.
        $redirect_uri = str_replace('/index.php', '', $redirect_uri);
      }

      $original_uri = $request->getSchemeAndHttpHost() . $request->getRequestUri();
      $original_uri = urldecode($original_uri);
      $redirect_uri = urldecode($redirect_uri);
      if ($redirect_uri != $original_uri) {
        $response = new TrustedRedirectResponse($redirect_uri, $this->config->get('default_status_code'));
        $response->headers->set('X-Drupal-Route-Normalizer', 1);
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onKernelRequestRedirect', 30);
    return $events;
  }

  /**
   * Private functions
   */
  private function getUrlsWithFilters() {
    $nids = UrlsHelper::getNids();
    $urls = [];

    foreach ($nids as $nid) {
      foreach ($this->languages as $language) {
        $urls[$language][$nid] = $this->getKernelUrl($nid, $language);
      }
    }

    return $urls;
  }

  private function foundUrls($path, $urls) {
    $found = FALSE;
    foreach ($urls as $urlItem) {
      foreach ($urlItem as $url) {
        $strCondition = strpos($path, $url) === FALSE;
        if (!$strCondition) {
          $found = TRUE;
          break 2;
        }
      }
    }

    return $found;
  }

  private function getKernelUrl($nid, $langcode) {
    return \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid, $langcode) . '/';
  }
}
