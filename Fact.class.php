<?php

class Fact {
  /**
   * Title of the fact
   */
  public static string $title;
  /**
   * Value stored for this fact
   */
  public static string $value;

  /**
   * Load all fact data
   *
   * @param array $dataArray The actual data for this fact
   * @param array $generalFactConfig The general config for all facts
   */
  function __construct(array $dataArray=array(), array $generalFactConfig=array()) {
    $factConfig = $generalFactConfig[$dataArray['fact_id']];
    $this->title = $factConfig['bezeichnung'];
    $this->value = $dataArray['value'];
  }
}
