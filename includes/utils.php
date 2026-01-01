<?php

/**
 * Function for sorting an array with proper locale
 * @param array $array
 * @param string $locale
 * @param boolean $case_insensitive
 * @return array
 */
function sort_intl($array, $locale = 'hu_HU', $case_insensitive = true) {
  if ($case_insensitive) {
    uasort($array, '_sort_compare_hu_insensitive');
  }
  else {
    uasort($array, '_sort_compare_hu_sensitive');
  }

  return $array;
}

/**
 * Fallback comparison function for unaccented, case-insensitive sort.
 * This function should only be used if the Intl extension is unavailable.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function _sort_compare_hu_insensitive($a, $b) {
    // Map of accented to unaccented characters (Hungarian specific)
    $accent_map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O',
        'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
    ];

    // 1. Unaccent the strings using strtr
    $normalized_a = strtr($a, $accent_map);
    $normalized_b = strtr($b, $accent_map);

    // 2. Perform case-insensitive, natural comparison on the normalized strings
    // strnatcasecmp provides natural sorting (e.g., file2 before file10)
    return strnatcasecmp($normalized_a, $normalized_b);
}

/**
 * Fallback comparison function for unaccented, case-sensitive sort.
 * This function should only be used if the Intl extension is unavailable.
 *
 * @param string $a
 * @param string $b
 * @return int
 */
function _sort_compare_hu_sensitive($a, $b) {
    // Map of accented to unaccented characters (Hungarian specific)
    $accent_map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O',
        'Ő' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
    ];

    // 1. Unaccent the strings using strtr
    $normalized_a = strtr($a, $accent_map);
    $normalized_b = strtr($b, $accent_map);

    // 2. Perform case-sensitive, natural comparison on the normalized strings
    // strnatcasecmp provides natural sorting (e.g., file2 before file10)
    return strnatcmp($normalized_a, $normalized_b);
}