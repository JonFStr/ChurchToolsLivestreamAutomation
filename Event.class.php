<?php

class Event {
  /**
   * The events id
   */
  public static int $id;
  /**
   * The id of the category this event is in
   */
  public static int $categoryId;
  /**
   * Time when the event starts
   */
  public static DateTimeInterface $startTime;
  /**
   * Time when the event ends
   */
  public static DateTimeInterface $endTime;
  /**
   * The events title
   */
  public static string $title;
  /**
   * A description of the event
   */
  public static string $description;
  /**
   * Whether or not livestreaming is enabled for this event
   */
  public static bool $livestreamEnabled = false;
  /**
   * The speaches subject
   */
  public static string $subject;
  /**
   * The speakers name
   * @TODO Allow multiple speakers
   */
  public static string $speaker;
  /**
   * The events link
   */
  public static Link $link;
  /**
   * This events broadcast
   */
  protected Google_Service_YouTube_LiveBroadcast $broadcast;
  /**
   * The YouTube API interface
   */
  protected YouTube $youtube;
  /**
   * The ChurchTools API connection
   */
  protected ChurchTools $churchToolsApi;
  /**
   * The cc cal id (I guess some database id)
   */
  public static int $ccCalId;
  /**
   * Wether this events broadcast was just created in this session
   */
  private bool $broadcastJustCreated = false;
  /**
   * The events broadcasts privacy status
   */
  protected $privacyStatus;
  /**
   * The events custom thumbnail
   */
  protected FileConnection $thumbnail;

  /**
   * Load all event data
   *
   * @param array $data The events raw data
   * @param ChurchTools $churchToolsApi The ChurchTools API connection
   * @param array $factList All facts this event has
   * @param array $serviceTypeList All available service types
   *
   * @TODO Extract subject from data
   */
  function __construct(array $data, ChurchTools $churchToolsApi, array $factList=array(), array $serviceTypeList=array()) {
    $this->churchToolsApi = $churchToolsApi;
    // Get general info
    $this->id = (int)$data['id'];
    $this->categoryId = (int)$data['category_id'];
    $this->ccCalId = (int)$data['cc_cal_id'];
    $this->startTime = new DateTimeImmutable($data['startdate']);
    $this->endTime = new DateTimeImmutable($data['enddate']);
    $this->title = $data['bezeichnung'];
    $this->description = $data['special'];
    $this->link = isset($data['link']) ? new Link($data['link']) : new Link();

    // Extract info from facts
    $this->setPrivacyStatus(new Fact());
    foreach($factList as $fact) {
      switch ($fact->title) {
        // Wether livestreaming is enabled
        case CONFIG['events']['livestream']['title']:
          $this->livestreamEnabled = $fact->value === CONFIG['events']['livestream']['value'];
          break;
        // Set livestream visibility
        case CONFIG['events']['livestream_visibility']['title']:
          $this->setPrivacyStatus($fact);
          break;
      }
    }

    // Extract info from services
    $serviceTypeMatch = array('speaker' => -1);
    foreach($serviceTypeList as $serviceType) {
      if ($serviceType->title === CONFIG['events']['speaker']) $serviceTypeMatch['speaker'] = $serviceType->id;
    }
    if (isset($data['services']) && is_array($data['services'])) {
      foreach($data['services'] as $service) {
        switch ($service['service_id']) {
          // Speaker
          case $serviceTypeMatch['speaker']:
            $this->speaker = $service['name'];
            break;
        }
      }
    }

    // Set custom or default thumbnail
    $this->setThumbnail();
  }

  /**
   * Get the events duration in seconds
   *
   * @return DateInterval Event duration
   */
  public function duration() {
    return $this->startTime->diff($this->endTime);
  }

  /**
   * Attach an existing YouTube broadcast to this event
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast to attach
   * @param YouTube $youtube The YouTube API interface
   */
  public function attachYouTubeBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast, YouTube $youtube) {
    $this->broadcast = $broadcast;
    $this->youtube = $youtube;
  }

  /**
   * Checks if the given YouTube broadcast belongs to this event
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast to check
   *
   * @return bool Whether the broadcast belongs to this event
   *
   * @TODO Implement
   */
  public function isEventBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast) {
    # Check that a broadcast exists for this event
    $videoId = $this->link->getYoutubeVideoId();
    if (empty($videoId)) return false;
    # Compare the two video ids
    return $videoId === $broadcast['id'];
  }

  /**
   * Get the events information for a broadcast
   *
   * @param array The gathered information
   */
  private function getBroadcastInformation() {
    // Gather all title information
    $broadcastTitle = CONFIG['youtube']['title'];
    $title = $this->title;
    $subject = isset($this->subject) ? '"' . $this->subject . '"' : '';
    $speaker = isset($this->speaker) ? ' mit ' . $this->speaker : '';
    $date = ' am ' . $this->startTime->format('d.m.Y');
    // Build title
    $titleReplacements = array('title', 'subject', 'speaker', 'date');
    foreach($titleReplacements as $replacement) {
      $broadcastTitle = str_replace("%$replacement%", ${$replacement}, $broadcastTitle);
    }

    // Gather all description information
    $broadcastDescription = CONFIG['youtube']['description'];
    $subject = isset($this->subject) ? 'Thema: ' . $this->subject : '';
    $subject_newline = isset($this->subject) ? $subject . PHP_EOL : '';
    $speaker = isset($this->speaker) ? 'Prediger: ' . $this->speaker : '';
    $speaker_newline = isset($this->speaker) ? $speaker . PHP_EOL : '';
    // Build description
    $descriptionReplacements = array('title', 'subject', 'subject_newline', 'speaker', 'speaker_newline', 'date');
    foreach($descriptionReplacements as $replacement) {
      $broadcastDescription = str_replace("%$replacement%", ${$replacement}, $broadcastDescription);
    }

    return array('title' => $broadcastTitle, 'description' => $broadcastDescription);
  }

  /**
   * Create a new broadcast for this event
   *
   * @param YouTube $youtube The YouTube API interface
   *
   * @TODO Rework title creation
   */
  public function createYouTubeBroadcast(YouTube $youtube) {
    // If a broadcast already exists, we are done here
    if (isset($this->broadcast)) return;
    $this->broadcastJustCreated = true;
    $this->youtube = $youtube;
    // Gather information
    $broadcastInformation = $this->getBroadcastInformation();

    // Create YouTube broadcast
    $this->broadcast = $this->youtube->createBroadcast($broadcastInformation['title'], $broadcastInformation['description'], $this->startTime, $this->endTime, $this->thumbnail->getDownloadLink(), $this->privacyStatus);
    // Update the event link
    $this->link = Link::fromYouTubeBroadcast($this->broadcast);
    $this->update();
  }

  /**
   * Delete the broadcast for this event
   */
   public function deleteBroadcast() {
    // If no broadcast exists, we are done here
    if (!isset($this->broadcast)) return;
    $this->broadcastJustCreated = false;
    // Delete the youtube broadcast
    $this->youtube->deleteBroadcast($this->broadcast);
    // Update the event
    unset($this->broadcast);
    $this->link = new Link('');
    $this->update();
  }

  /**
   * Update the events broadcast link
   */
  protected function update() {
    $this->churchToolsApi->updateEventParameters($this, array('link' => $this->link->url));
  }

  /**
   * Update the events YouTube Broadcast
   */
  public function updateYouTubeBroadcast() {
    // Skip if no broadcast exists or was just created and doesn't need to be updated
    if (!isset($this->broadcast, $this->youtube) || $this->broadcastJustCreated) return;

    // Gather information
    $broadcastInformation = $this->getBroadcastInformation();
    // Update broadcast
    $this->broadcast = $this->youtube->updateBroadcast($this->broadcast, $broadcastInformation['title'], $broadcastInformation['description'], $this->startTime, $this->endTime, $this->thumbnail->getDownloadLink(), $this->privacyStatus);
  }

  /**
   * Get the YouTube Broadcasts privacy status
   *
   * @param Fact $fact The events visibility fact
   *
   * @return YouTubePrivacyStatus
   */
  private function setPrivacyStatus($fact) {
    foreach (CONFIG['events']['livestream_visibility']['values'] as $visibility => $description) {
      if ($description == $fact->value) {
        // Use the set visibility
        $this->privacyStatus = new YouTubePrivacyStatus($visibility);
        return;
      }
    }
    // Use default value if none was set
    $this->privacyStatus = new YouTubePrivacyStatus();
  }

  /**
   * Set the events thumbnail
   */
  protected function setThumbnail() {
    $availableFiles = $this->churchToolsApi->getEventFiles($this);

    // Check if any file is a thumbnail
    foreach ($availableFiles as $file) {
      if ($file->getName() === CONFIG['events']['thumbnail_name']) {
        $this->thumbnail = $file;
        return;
      }
    }

    // No thumbnail was set, so use the default one
    global $youTubeDefaultThumbnail;
    if (null === $youTubeDefaultThumbnail) {
      $youTubeDefaultThumbnail = FileConnection::fromExternalUrl(CONFIG['youtube']['thumbnail']);
    }
    $this->thumbnail = $youTubeDefaultThumbnail;
  }
}
