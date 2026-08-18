<?php
///////////////////////////////////////////////////////////////


class Css_Js_Meta {
  private $files_arr = [];
  private $files_result_arr = [];

  public function __construct( $files_arr = [] ) {
    $this->files_arr = $files_arr;
  }

  public function merge() {
    foreach ( $this->files_arr as $file ) {
      $ext = explode(".", $file);
      $ext = strtolower(end($ext));
      if ( $ext == "css" ) {
        $this->files_result_arr[] = '<link href="'.$file.'?v'.filemtime(BASE_ROOT . "/$file").'" type="text/css" rel="stylesheet" />';
      } elseif ( $ext == "js" ) {
        $this->files_result_arr[] = '<script src="'.$file.'?v'.filemtime(BASE_ROOT . "/$file").'" type="text/javascript"></script>';
      }
    }
    return $this->files_result_arr["0"] ? implode( " ", $this->files_result_arr ) . "\n" : "";
  }
}


///////////////////////////////////////////////////////////////



function get_params( $w="" ) {
  global $PARAM, $PARAMQ;

  $PARAM = [];
  $PARAMQ = [];

  $url_ = is_ajax() ? $_SERVER["HTTP_REFERER"] : $_SERVER["REQUEST_URI"];

  $ru = filter_var( $url_, FILTER_SANITIZE_URL );
  $param_path = explode( "/", trim( parse_url( $ru, PHP_URL_PATH ), '/' ) );
  $param_query = @explode( "&", trim( parse_url( $ru, PHP_URL_QUERY ) ) );

  $n=0;
  if ( !empty( array_filter ( $param_path ) ) )
  foreach ($param_path as $item) { $n++;
    $PARAM["p".$n] = $item;
  }

  $param_query_ = parse_url( $url_ );
  if ( isset($param_query_['query']) ) {
    parse_str($param_query_['query'], $param_query);

    if ( !empty( array_filter ( $param_query ) ) ) {
      foreach ($param_query as $key => $item) {
        $PARAMQ["$key"] = $item;
      }
    }
  }

  if ( $w == "PARAM" ) return $PARAM;
  if ( $w == "PARAMQ" ) return $PARAMQ;
}

///////////////////////////////////////////////////////////////


function is_ajax() {
	return ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' );
}

///////////////////////////////////////////////////////////////
?>