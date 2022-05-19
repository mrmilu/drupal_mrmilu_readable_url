<?php

namespace Drupal\mrmilu_readable_url\Helper;

class UrlsHelper {

  public static function getNids() {
    return \Drupal::state()->get('mrmilu_readable_url_node') ? \Drupal::state()->get('mrmilu_readable_url_node') : NULL;
  }

  public static function getValue($key) {
    return \Drupal::request()->query->has($key) ? \Drupal::request()->query->get($key) : NULL;
  }

  public static function textareaKeysToArray($keysArray, $stringArray) {
    if ($stringArray) {
      $stringResult = [];
      foreach ($stringArray as $i => $string) {
        $values = preg_split('/[\r\n]+/', $string, -1, PREG_SPLIT_NO_EMPTY);

        $result = [];
        foreach ($values as $record) {
          list($key, $value) = array_pad(explode('|', $record), 2, null); // array_pad prevents string without key|value
          $result[$key] = \Drupal::service('pathauto.alias_cleaner')->cleanString($value);
        }
        $stringResult[$keysArray[$i]] = $result;
      }
      return $stringResult;
    }

    return [];
  }
}
