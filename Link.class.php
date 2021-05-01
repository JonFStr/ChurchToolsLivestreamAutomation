<?php

class Link implements JsonSerializable {
  /**
   * Wether the link is valid
   */
  public bool $valid = false;
  /**
   * The links url
   */
  public string $url;
  /**
   * A videos id this link is pointing to
   */
  protected string $videoId;

  /**
   * Validate link
   *
   * @param string $url The url this link represents
   */
  function __construct(string $url) {
    # Sanitize url
    $url = filter_var($url, FILTER_SANITIZE_URL);
    # Validate the url
    $this->valid = filter_var($url, FILTER_VALIDATE_URL);
    $this->url = $this->valid ? $url : '';
  }

  /**
   * Create a YouTube link from a YouTube broadcast
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast
   *
   * @return Link The created YouTube Link
   */
  public static function fromYouTubeBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast) {
    return new Link('https://youtu.be/' . $broadcast->id);
  }

  /**
   * Get the id of the youtube video this link is pointing to
   *
   * @return string The youtube videos id
   */
  public function getYoutubeVideoId() {
    if (!$this->isYoutubeVideoLink()) return '';
    else return $this->videoId;
  }

  /**
   * Check if this is a youtube video link
   *
   * @return bool Wether this is link points to a youtube video
   */
  public function isYoutubeVideoLink() {
    preg_match('#(?:https?://)?(?:www\.)?(?:youtube\.com/watch/?\?v=|youtu\.be/)(?<video_id>[^"&?\s]+)#i', $this->url, $match);
    if (!is_array($match) || !isset($match['video_id'])) return false;
    # Valid youtube video link
    $this->videoId = $match['video_id'];
    return true;
  }

  /**
   * Collect data for json
   * @return string This links url
   */
  public function jsonSerialize() {
    return $this->url;
  }
}
