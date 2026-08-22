<?php
class PagesMy {
  private $db;
  public $countItems_together;
  public $current_page;
  public $current_page2;
  public $limit = 2;
  public $count_pages;
  public $limit_1;
  public $limit_2;
  public $PARAM = [];
  public $PARAMQ = [];

  public function __construct( $db ) {
    $this->db = $db;

    $this->PARAM = get_params( "PARAM" );
    $this->PARAMQ = get_params( "PARAMQ" );

    // $this->getLimits();
  }

  public function count_pages() {
    $this->count_pages = ceil($this->countItems_together / $this->limit);
  }

  public function getLimits() {
    if ( !empty($this->PARAMQ["p"]) AND strpos($this->PARAMQ["p"], "-") !== false ) {
      $p_ = explode( "-", $this->PARAMQ["p"] );

      if ( !is_numeric($p_["0"]) OR !is_numeric($p_["1"]) ) exit;

      $this->current_page = (int) $p_["0"];
      $this->limit_1 = ((int) $p_["0"] * $this->limit) - $this->limit;
      $this->limit_2 = (int) (($p_["1"]-$p_["0"])+1) * $this->limit;
    } else {
      $this->current_page = empty($this->PARAMQ["p"]) ? 1 : $this->PARAMQ["p"];
      $this->limit_1 = ((int) $this->current_page * $this->limit) - $this->limit;
      $this->limit_2 = (int) $this->limit;
    }

    if ( $this->limit_1 < 0 ) exit;
  }


  public function create_link( $w ) {
    if ( empty($this->PARAMQ["p"]) ) {
      $p_["0"] = 1;
      $p_["1"] = 1;
    } else {
      $p_ = explode( "-", $this->PARAMQ["p"] );
      if ( !isset($p_["1"]) ) $p_["1"] = $p_["0"];
    }

    if ( $w == null ) {
      $urlq_[] = "p={$p_["0"]}-" . ($p_["1"]+1);
    } else {
      if ( $w == "next" ) {
        if ( isset($p_["1"]) ) $p__ = $p_["1"]; else $p__ = $p_["0"];
        $urlq_[] = "p=" . $p__+1;
      } elseif ( $w == "prev" ) {
        $urlq_[] = "p=" . $p_["0"]-1;
      } elseif ( $w == "input" ) {
        $urlq_[] = "p=input";
      }
    }

    foreach ($this->PARAMQ as $key => $value) {
      if ( $key != "p" AND $key != "added" ) $urlq_[] = "$key=$value";
    }

    $url = "/" . implode( "/", $this->PARAM );
    $urlq = "?" . implode( "&", $urlq_ );

    return $url . $urlq;
  }


  public function get_html_pages() {
    $this->count_pages();

    if ( !empty($this->PARAMQ["p"]) AND strpos($this->PARAMQ["p"], "-") !== false ) {
      $p_ = explode( "-", $this->PARAMQ["p"] );
      $this->current_page2 = $p_["1"];
    } else {
      $this->current_page2 = $this->current_page;
    }

    if ( $this->current_page2 <  $this->count_pages ) {
      $page_go = $this->create_link( "next" );
      $next_b = "<a href='$page_go' class='next' pages_my scrollTo='[load_items_page]'></a>";
    } else {
      $next_b = "<a href='javascript://' class='next deactive'></a>";
    }
    if ( $this->current_page >  1 ) {
      $page_go = $this->create_link( "prev" );
      $prev_b = "<a href='$page_go' class='prev' pages_my scrollTo='[load_items_page]'></a>";
    } else {
      $prev_b = "<a href='javascript://' class='prev deactive'></a>";
    }

    echo "<div class='n1'>&nbsp;</div><!-- n1 -->";

    echo "<div class='n2'>";
      if ($this->current_page2 < $this->count_pages) {
        echo "<a href='" . $this->create_link( null ) . "' class='button_1 wh showmore' pages_my>ZOBRAZIŤ ĎALŠIE</a>";
      }
    echo "</div><!-- n2 -->";

    $page_go = $this->create_link( "input" );
    echo "
    <div class='n3'>
      <div class='input'>
        <input type='text' name='' data_href='$page_go' data_m='$this->count_pages' value='" . (!empty($this->PARAMQ["p"]) ? $this->PARAMQ["p"] : "1") . "' pages_input_my />
      </div><!-- input -->
      <div class='text'>
        <small>of</small> $this->count_pages
      </div>
      <div class='arrows'>
        $prev_b
        $next_b
      </div><!-- arrows -->
    </div><!-- n3 -->
    ";
  }

}
