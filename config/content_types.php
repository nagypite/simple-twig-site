<?php
return [
  'article' => [
    'label' => 'Article',
    'relations' => [
      'author_id' => 'author',
    ],
    'serve_files' => [
      '#^content/article/images/(\d+|_new)/([^/]*)$#' => 'content/article/images/\1/\2'
    ],
    'preprocess' => [
      'keywords',
      'authors',
      'content',
      'abstract',
    ],
    'required_fields' => [
      'title',
      'date',
      'author_id',
      'stub',
      'content',
    ],
  ],
  'author' => [
    'label' => 'Author',
    'serve_files' => [
      '#^content/author/images/(\d+|_new)/([^/]*)$#' => 'content/author/images/\1/\2'
    ],
    'preprocess' => [
      'abstract',
    ],
    'required_fields' => [
      'title',
      'stub',
    ],
  ],
  'event' => [
    'label' => 'Event',
    'serve_files' => [
      '#^content/event/images/(\d+|_new)/([^/]*)$#' => 'content/event/images/\1/\2'
    ],
    'preprocess' => [
      'keywords',
      'content',
      'abstract',
      'siblings',
    ],
    'required_fields' => [
      'title',
      'date',
      'stub',
      'content',
    ],
    'abstract_length' => 300,
  ],
  'feature' => [
    'label' => 'Feature',
    'serve_files' => [
      '#^content/feature/images/(\d+|_new)/([^/]*)$#' => 'content/feature/images/\1/\2'
    ],
    'preprocess' => [
      'keywords',
      'content',
      'abstract',
      'siblings',
    ],
    'required_fields' => [
      'title',
      'date',
      'stub',
      'content',
    ],
    'abstract_length' => 200,
  ],
  'newsletter' => [
    'label' => 'Newsletter',
    'serve_files' => [
      '#^content/newsletter/images/(\d+|_new)/([^/]*)$#' => 'content/newsletter/images/\1/\2'
    ],
    'preprocess' => [
      'keywords',
      'content',
      'siblings',
    ],
    'required_fields' => [
      'title',
      'date',
      'stub',
      'content',
    ],
    'abstract_length' => 200,
  ],
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
