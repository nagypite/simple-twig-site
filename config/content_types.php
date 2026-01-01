<?php
return [
  'gallery' => [
    'label' => 'Gallery',
    'roles' => ['admin', 'gallery'],
    'serve_files' => [
      '#^content/gallery/images/(\d+|_new)/([^/]*)$#' => 'content/gallery/images/\1/\2'
    ],
    'preprocess' => [
      'gallery',
    ],
    'required_fields' => [
      'title',
      'stub',
      'content',
    ],
  ],
  'post' => [
    'label' => 'Posts',
    'roles' => ['admin'],
    'preprocess' => [
      'content',
    ],
    'required_fields' => [
      'title',
      'stub',
      'content',
      'date',
    ],
  ],
];
