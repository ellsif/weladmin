<?php
namespace ellsif;

use ellsif\WelCMS\Pocket;

function tag($tagName, $attributes, $text = null) :string
{
  $tag = "<${tagName}";
  foreach($attributes as $name => $value) {
    $tag .= " ${name}=\"${value}\"";
  }
  if ($text !== null) {
    $tag .= '>' . $text . tagEnd($tagName);
  } else {
    $tag .= ' />';
  }
  return $tag;
}

function tagStart($tagName, $attributes) :string
{
  $tag = "<${tagName}";
  foreach($attributes as $name => $value) {
    $tag .= " ${name}=\"${value}\"";
  }
  $tag .= '>';
  return $tag;
}

function tagEnd($tagName) :string
{
  return "</${tagName}>";
}

function tagged($tagName, $attributes, $body) :string
{
  $html = tagStart($tagName, $attributes);
  $html .= $body;
  $html .= tagEnd($tagName);
  return $html;
}


/**
 * Errorを投げる
 */
function throwError($message, $debug = '', $code = 500, $previous = null)
{
  $config = Pocket::getInstance();
  if ($config->runMode() == 'development') {
    $message .= "\n(${debug})";
  }
  throw new \Error($message, $code, $previous);
}

function pre_dump($obj)
{
  echo '<pre>';
  var_dump($obj);
  echo '</pre>';
}

/**
 * WebPartをincludeする。
 */
function includePart($name)
{
  try {
    $config = Pocket::getInstance();
    $name = basename($name);
    include $config->dirView() . "parts/${name}.php";
  } catch(\Exception $e) {
    echo "<p>${name}部品のロードに失敗しました。</p>";
  }
}

/**
 * WebPartのHtml表現を取得する。
 */
function getPartHtml($name)
{
  ob_start();
  includePart($name);
  $html = ob_get_contents();
  ob_end_clean();
  return $html;
}