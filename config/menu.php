<?php

return [
  [
    'label' => 'Home',
    'path' => 'index',
    'path_aliases' => ['', 'home'],
    'preprocess' => 'index',
  ],
  [
    'label' => 'First page',
    'path' => 'first-page',
    'children' => [
      [
        'label' => 'subpage',
        'path' => 'subpage',
      ],
      [
        'label' => 'second subpage',
        'path' => 'subpage-2',
      ],
    ],
  ],
  [
    'label' => 'Galleries',
    'path' => 'gallery',
    'path_aliases' => ['galleries'],
    'children' => 'content:gallery',
    'error_path' => 'gallery',
    'view_path' => 'gallery/view',
    'content_type' => 'gallery',
    'preprocess' => 'content_preprocess',
    'auth_required' => true,
    'roles' => ['admin'],
    'show_for' => ['admin'],
  ],
  [
    'label' => 'Posts',
    'path' => 'post',
    'path_aliases' => ['blog'],
    'children' => 'content:post',
    'error_path' => 'post',
    'view_path' => 'post/view',
    'content_type' => 'post',
    'preprocess' => 'content_preprocess',
    'roles' => ['admin'],
  ],
  [
    'label' => 'Sign in',
    'path' => 'login',
    'path_aliases' => ['signin'],
    'action' => 'login',
    'show_for' => ['_guest'],
  ],
];
