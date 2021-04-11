<?php

class ServiceType {
  /**
   * Id of the service type
   */
  public static string $id;
  /**
   * Title of the service type
   */
  public static string $title;

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
