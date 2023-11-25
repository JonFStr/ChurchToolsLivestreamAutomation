<?php

final class YouTubePrivacyStatus implements JsonSerializable {
  const PUBLIC = 'public';
  const UNLISTED = 'unlisted';
  const PRIVATE = 'private';
  const DEFAULT = YouTubePrivacyStatus::PUBLIC;

  private $status;

  /**
   * Init status
   *
   * @param string The desired status
   */
  function __construct(string $status='') {
    $class = new ReflectionClass(__CLASS__);
    $validSates = $class->getConstants();

    if (in_array($status, $validSates)) {
      $this->status = $status;
    }
    else if (in_array(CONFIG['youtube']['visibility'], $validSates)) {
        $this->status = CONFIG['youtube']['visibility'];
    }
    else {
      $this->status = YouTubePrivacyStatus::DEFAULT;
    }
  }

  /**
   * Get the status
   *
   * @return string The status
   */
  public function status() {
    return $this->status;
  }

  /**
   * Collect data for json
   * @return string The privacy status as string
   */
  public function jsonSerialize(): string {
    return $this->status;
  }
}
