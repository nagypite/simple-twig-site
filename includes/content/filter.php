<?php

/**
 * Filter content list by filter criteria
 * @param array $content_list
 * @param array $filter
 * @return array
 */
function _content_filter($content_list, $filter) {
  return array_filter($content_list, function($content) use ($filter) {
    return _content_filter_content($content, $filter);
  });
}

/**
 * Filter a single content by filter criteria
 * @param array $content
 * @param array $filter
 * @return boolean
 */
function _content_filter_content($content, $filter) {
  if (empty($filter)) {
    return true;
  }

  if (!empty($filter['keywords'])) {
    if (empty($content['keywords'])) {
      return false;
    }
    if (!array_intersect($filter['keywords'], $content['keywords'])) {
      return false;
    }
  }

  $content_date_unix = $content['date'] ? strtotime($content['date']) : 0;
  if (!empty($filter['date_from'])) {
    $date_from_unix = strtotime($filter['date_from']);
    if ($date_from_unix > 0 && $date_from_unix > $content_date_unix) {
      return false;
    }
  }
  if (!empty($filter['date_to'])) {
    $date_to_unix = strtotime($filter['date_to']);
    if ($date_to_unix > 0 && $date_to_unix < $content_date_unix) {
      return false;
    }
  }

  return true;
}
