<?php

class Fact {
  /**
   * Title of the fact
   */
  public string $title = '';
  /**
   * Value stored for this fact
   */
  public string $value = '';

  /**
   * Load all fact data
   *
   * @param array $dataArray The actual data for this fact
   * @param array $generalFactConfig The general config for all facts
   */
  function __construct(array $dataArray=array(), array $generalFactConfig=array()) {
    if (isset($dataArray['fact_id'])) {
      $factConfig = $generalFactConfig[$dataArray['fact_id']];
      $this->title = $factConfig['bezeichnung'];
    }

    if (isset($dataArray['value'])) {
      $this->value = $dataArray['value'];
    }
  }
}
