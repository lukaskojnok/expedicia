<!DOCTYPE html>
<html xml:lang="<?php echo LANG_META; ?>" lang="<?php echo LANG_META; ?>" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="content-language" content="<?php echo LANG_META; ?>" />

    <?php
    foreach ($meta["languages"] as $key => $item) {
      $hreflang = $key . "-" . strtoupper($key);
      if ($key=="cz") $hreflang = "cs-CZ";
      $hreflang_dmn = "";
      if ( PRIMARY_LANG!=$key ) $hreflang_dmn = "$key/";
      echo "<link rel='alternate' hreflang='$hreflang' href='".WEB_URL."/{$hreflang_dmn}$item' />\n";
    }
    ?>
    
    <link href="<?php echo WEB_URL_ACTUAL; ?>" rel="canonical" />
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />

    <meta name="Title" content="<?php echo $meta["title"]; ?>" />
    <meta name="robots" content="index, follow" />
    <meta name="description" content="<?php echo $meta["description"]; ?>" />

    <meta property="og:url" content="<?php echo WEB_URL_ACTUAL; ?>" />
    <meta property="og:title" content="<?php echo $meta["title"]; ?>" />
    <meta property="og:description" content="<?php echo $meta["description"]; ?>" />
    <?php if ($meta["image"]): ?>
    <meta property="og:image" content="<?php echo $meta["image"]; ?>" />
    <?php endif; ?>

    <script src="/js/jquery-3.6.0.min.js"></script>

    <?php
    $Css_Js_Meta = new Css_Js_Meta( [ "/css/css.css" ] );
    echo $Css_Js_Meta->merge();
    ?>

    <title><?php echo $meta["title"]; ?></title>
  </head>
  <body>
