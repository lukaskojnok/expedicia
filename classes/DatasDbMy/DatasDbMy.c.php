<?php
/*
$datasDbMy->prdata = "folderhash, titleimage,id";
$datasDbMy->data = "title_h1";
$datasDbMy->item_id = 2;
$datasDbMy->link = "prvy-casopis"; //toto je preddefinované pred "item_id"
$datasDbMy->active = 1;
$x = $datasDbMy->g( "magazines" );
*/

class DatasDbMy {
  private $db;
  public $prdata = "";
  public $data = "*";
  private $PRDATAS_V = "";
  private $DATAS_V = "";
  public $order_by = "prdata.poradie ASC";
  public $group_by = "";
  public $active = 1;
  public $active_menu = 1;
  public $item_id = "all";
  public $link = "";
  public $select_by = "";
  public $select_by_id = "";

  public function __construct( $db ) {
    $this->db = $db;
  }

  public function clear() {
    $this->item_id = "all";
    $this->link = "";
    $this->prdata = "";
    $this->data = "*";
    $this->group_by = "";
    $this->order_by = "prdata.poradie ASC";
    $this->select_by = "";
    $this->select_by_id = "";
  }

  private function g_prdata() {
    if ( is_string($this->prdata) AND !empty($this->prdata) ) {
      $prdata = explode(",", $this->prdata);
      $prdata = array_map("trim", $prdata);
      return implode(", ", array_map(fn($item) => "prdata.$item", $prdata));
    } elseif ( $this->prdata === "*" ) {
      return "prdata.*";
    }
  }  

  private function g_data() {
    if ( is_string($this->data) AND !empty($this->data) ) {
      $data = explode(",", $this->data);
      $data = array_map("trim", $data);
      return implode(", ", array_map(fn($item) => "data.$item", $data));
    } else {
      return "data.*";
    }
  }  

  private function items_select() {
    if ( $this->item_id !== "all" ) {
      return "AND data.item_id='{$this->item_id}'";
    }
  }

  public function g( $w ) {
    $prepare = "";
    $execute = [];
    $is_list = 1;

    $data_select = $this->g_data() . (!empty($this->g_prdata()) ? ", " . $this->g_prdata() : "");

    if ( $this->item_id !== "all" AND is_numeric($this->item_id) ) {
      $prepare = "AND data.item_id=:item_id";
      $execute = ["item_id" => $this->item_id];
      $is_list = 0;
    } 
    if ( !empty($this->link) ) {
      $prepare = "AND data.link=:link";
      $execute = ["link" => $this->link];
      $is_list = 0;
    } 
    if ( !empty($this->select_by) AND !empty($this->select_by_id) ) {
      $prepare = "AND prdata.{$this->select_by}=:{$this->select_by}";
      $execute = [$this->select_by => $this->select_by_id];
    } 

    $query = $this->db->prepare( "SELECT 
      $data_select
      FROM $w AS prdata LEFT JOIN {$w}_data AS data ON prdata.id = data.item_id 
      WHERE 
        data.lang=:lang 
        -- AND active = $this->active
        -- AND active_menu = $this->active_menu
        $prepare
        $this->group_by
      ORDER BY $this->order_by" );
    $query->execute( array_merge([ "lang" => LANG ], $execute ) );

    if ( $is_list === 1 ) {
      return $query->rowCount() ? $query->fetchAll( PDO::FETCH_ASSOC ) : [];
    } else {
      return $query->rowCount() ? $query->fetch( PDO::FETCH_ASSOC ) : [];
    }
  }  

}