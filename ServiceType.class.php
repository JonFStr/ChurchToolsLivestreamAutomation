<?php

class ServiceType {
  /**
   * Id of the service type
   */
  public $id;
  /**
   * Title of the service type
   */
  public $title;

  /**
   * Load all type data
   *
   * @param array $dataArray The raw data for this service type
   */
  function __construct(array $dataArray) {
    $this->id = $dataArray['id'];
    $this->title = $dataArray['bezeichnung'];
  }
}
