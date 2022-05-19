<?php

namespace Drupal\mrmilu_readable_url\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\mrmilu_readable_url\Helper\UrlsHelper;
use Symfony\Component\HttpFoundation\Request;
use Drupal\path_alias\AliasManagerInterface;

class PathProcessorUnlimited implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  protected $aliasManager;
  private $nids;
  private $filterValues;
  private $filterKeys;

  public function __construct(AliasManagerInterface $aliasManager) {
    $this->aliasManager = $aliasManager;
    $this->nids = UrlsHelper::getNids();
    $this->filterValues = \Drupal::state()->get('mrmilu_readable_url_filter_value');
    $this->filterKeys = \Drupal::state()->get('mrmilu_readable_url_filter_key');
  }

  private function testFancyURL($basePath) {
    $pathsAndQuery = $this->getPathsAndQueryKeys();
    $pathsWithFancyUrls = array_keys($pathsAndQuery);
    foreach ($pathsWithFancyUrls as $keyValue) {
      if (strpos($basePath, $keyValue) === 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function processInbound($path, Request $request) {
    $hasFancyUrl = $this->testFancyURL($path);

    if ($hasFancyUrl) {
      $nid = $this->getPageNid($path);
      if ($nid) {
        $parts = explode('/', trim($path, '/'));
        array_shift($parts);

        $filterKeys = $this->filterKeys[$nid];
        $filterValues = UrlsHelper::textareaKeysToArray($filterKeys, $this->filterValues[$nid]);

        foreach ($filterKeys as $key => $filter) {
          $valueResult = $parts[$key] ?? NULL;
          $keyResult = array_search($valueResult, $filterValues[$filter]);

          if ($keyResult) {
            $request->query->set($filter, $keyResult);
          }
        }

        return '/node/' . $nid;
      }
    }

    return $path;
  }

  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleableMetadata = NULL) {
    $langCode = isset($options['language']) ? $options['language']->getId() : NULL;
    $pathAlias = $this->aliasManager->getAliasByPath($path, $langCode);

    $hasFancyUrl = $this->testFancyURL($pathAlias);

    if ($hasFancyUrl && array_key_exists('query', $options)) {
      if (sizeof($options['query']) == 0) {
        $pathArray = explode('/', $path);
        $nid = end($pathArray);
        $filterKeys = $this->filterKeys[$nid];
        $filterValues = UrlsHelper::textareaKeysToArray($filterKeys, $this->filterValues[$nid]);

        $pathResult[] = $pathAlias;
        $allUrl = TRUE;
        foreach ($filterKeys as $key => $filter) {
          $keyResult = \Drupal::request()->query->has($filter) ? \Drupal::request()->query->get($filter) : 'all';
          $pathResult[] = $keyResult != 'all' ? $filterValues[$filter][$keyResult] : 'all';
          if ($keyResult != 'all') {
            $allUrl = FALSE;
          }
        }

        if (!$allUrl) {
          return implode('/', $pathResult);
        }
        return $path;
      }
    }
    return $path;
  }


  /**
   * Auxiliar functions
   */
  private function getPageNid($basePath) {
    foreach ($this->nids as $nid) {
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
      if (strpos($basePath, $alias) === 0) {
        return $nid;
      }
    }

    return NULL;
  }

  private function getPathsAndQueryKeys() {
    $result = [];

    foreach ($this->nids as $nid) {
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nid);
      $result[$alias] = $this->filterKeys[$nid];
    }

    return $result;
  }
}
