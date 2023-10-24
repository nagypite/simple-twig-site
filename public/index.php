<?php

error_reporting(E_ALL);

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);

define('BASE_PATH', realpath(__DIR__.'/..'));
require_once BASE_PATH.'/includes/bootstrap.php';

$message = NULL;
$variables = array(
  'sitename' => $config['sitename'],
);

if (isset($_REQUEST['mid'])) {
  $mid = intval($_REQUEST['mid']);
}
else {
  $mid = NULL;

  if (!empty($_SERVER['REDIRECT_URL']) && strlen($_SERVER['REDIRECT_URL']) > 1) {
    $redirect_path = substr($_SERVER['REDIRECT_URL'], 1);

    if (preg_match('/(cikk|oldal)\/(\d+)/', $redirect_path, $match)) {
      $mid = intval($match[2]);
      $variables['page_url'] = '/oldal/'.$mid;
    }
    else if(isset($config['rgm_alias'][$redirect_path])) {
      $mid = $config['rgm_alias'][$redirect_path];
      $variables['page_url'] = '/'.$redirect_path;
    }
    else {
      $message = 'A választott oldal nem található.';
    }
  }
}

if ($mid) {
  $variables['mid'] = $mid;

  $content_twig_path = implode('/', array('content', $mid, 'content.html'));
  if (file_exists($config['template_path'].'/'.$content_twig_path)) {
    echo $twig->render('content/'.$mid.'/content.html', $variables);
  }
  else {
    $content_html_path = implode('/', array('content', $mid, 'main_right.html'));
    if (file_exists($config['template_path'].'/'.$content_html_path)) {
      if (isset($config['rgm_content'][$mid]['template'])) {
        $template_name = $config['rgm_content'][$mid]['template'];
      }
      else {
        $template_name = $config['rgm_default_template'];
      }

      $content = $twig->render('content/'.$mid.'/main_right.html');
      $fomenu = $twig->render('common/fomenu.html');

      echo $twig->render('common/'.$template_name.'.html', array_merge($variables, array(
        'SITEBODY' => $content,
        'SITEHEAD' => $fomenu,
        'SITEMENU' => '<!-- SITEMENU -->',
      )));
    }
    else {
      $mid = NULL;
      $message = 'A választott tartalom nem található.';
    }
  }
}

if (!empty($message)) {
  $variables['message'] = $message;
}

$variables['title'] = empty($title) ? $config['sitename'] : $title;

if (!$mid) {
  $title = $config['sitename'];
  echo $twig->render('index.html', $variables);
}
