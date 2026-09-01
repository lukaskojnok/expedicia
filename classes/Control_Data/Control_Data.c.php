<?php
class Control_Data {

  public function sanitaze_string( $item ) {
    return htmlspecialchars( $item );
  }

  public function sanitaze_int( $item ) {
    return filter_var($item, FILTER_SANITIZE_NUMBER_INT);
  }

  public function sanitaze_float( $item ) {
    return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
  }

  public function sanitaze_email( $item ) {
    return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
  }


  public function control_email( $item ) {
    if ( !filter_var($item, FILTER_VALIDATE_EMAIL) ) {
      return new ResultFunction(false, 'Chybný e-mail');
    } else {
      return new ResultFunction(true, '');
    }
  }

  public function control_login( $login ) {
    if ( !$login ) {
      return new ResultFunction(false, 'Musíte zadať login/nick');
    } elseif ( strpos(strtolower($login), "admin") !== false ) {
      return new ResultFunction(false, 'Login nesmie obsahovať slová, ktoré ste použili v logine');
    } elseif ( !preg_match("/^([a-zA-Z0-9']+)$/", $login) ) {
      return new ResultFunction(false, 'Chybný login/nick (písmená, číslice)');
    } elseif ( strlen($login) > 15 ) {
      return new ResultFunction(false, 'Zadali ste dlhý login/nick');
    } elseif ( strlen($login) < 4 ) {
      return new ResultFunction(false, 'Zadali ste krátky login/nick');
    } else {
      return new ResultFunction(true, '');
    }
  }

  public function control_required( $item ) {
    $item = htmlspecialchars( $item );
    $item = trim( $item );
    if ( !empty( $item ) ) {
      return $item;
    } else {
      return false;
    }
  }

  public function control_phone_number( $item ) {
    $item = str_replace(" ", "", $item);
    if ( preg_match('/^[0-9+]+$/', $item) ) {
      return $item;
    } else {
      return false;
    }
  }

  public function google_reCaptcha_v3_0( $recaptchaResponse ) {
    $response = file_get_contents( "https://www.google.com/recaptcha/api/siteverify?secret=".GOOGLE_SECRET_KEY."&response={$recaptchaResponse}" );
    $response_reCaptcha = json_decode($response, true);
    if ( isset($response_reCaptcha["success"]) AND $response_reCaptcha["success"] == 1 ) {
      return true;
    } {
      return false;
    }
  }

}
