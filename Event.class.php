<?php

class Event implements JsonSerializable {
  /**
   * The events id
   */
  public int $id;
  /**
   * The id of the category this event is in
   */
  public int $categoryId;
  /**
   * Time when the event starts
   */
  public DateTimeInterface $startTime;
  /**
   * Time when the event ends
   */
  public DateTimeInterface $endTime;
  /**
   * The events title
   */
  public string $title = '';
  /**
   * A description of the event
   */
  public string $description = '';
  /**
   * Whether livestreaming is enabled for this event
   */
  public bool $livestreamEnabled = CONFIG['events']['livestream']['default'];
  /**
   * Whether this event's existing broadcast should be ignored
   */
  private bool $livestreamIgnored = false;
  /**
   * Whether the livestream of this event should be displayed on the homepage
   */
  public bool $livestreamOnHomepage = CONFIG['events']['livestream_on_homepage']['default'];
  /**
   * Whether the livestream link should be written to the `link`-field in the calendar entry
   */
  public bool $livestreamInCalendar = CONFIG['events']['livestream_in_calendar']['default'];
  /**
   * The speaches subject
   */
  public string $subject = '';
  /**
   * The speakers name
   * @TODO Allow multiple speakers
   */
  public string $speaker = '';
  /**
   * The events link
   */
  public Link $link;

  /**
   * The YouTube stream
   */
  public ?FileConnection $streamLink = null;
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
  public int $ccCalId;
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
   * The events raw data
   */
  private array $rawData;

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
  function __construct(array $data, ChurchTools $churchToolsApi, array $factList = array(), array $serviceTypeList = array()) {
    $this->churchToolsApi = $churchToolsApi;
    $this->loadData($data, $factList, $serviceTypeList);
  }

  /**
   * (Re)load this events data
   *
   * @param array $data The events raw data
   * @param array $factList All facts this event has
   * @param array $serviceTypeList All available service types
   */
  public function loadData(array $data, array $factList = array(), array $serviceTypeList = array()) {
    $this->rawData = $data;
    // Get general info
    $this->id = (int) $data['id'];
    $this->categoryId = (int) $data['category_id'];
    $this->ccCalId = (int) $data['cc_cal_id'];
    $this->startTime = new DateTimeImmutable($data['startdate']);
    $this->endTime = new DateTimeImmutable($data['enddate']);
    $this->title = $data['bezeichnung'];
    $this->description = null === $data['special'] ? '' : $data['special'];
    $this->link = isset($data['link']) ? new Link($data['link']) : new Link();

    // Extract info from facts
    $this->setPrivacyStatus(new Fact());
    foreach ($factList as $fact) {
      switch ($fact->title) {
        // Whether livestreaming is enabled
        case CONFIG['events']['livestream']['title']:
          $this->livestreamEnabled = $fact->value === CONFIG['events']['livestream']['value'];
          // Check if the livestream should be ignored
          $this->livestreamIgnored = $fact->value === CONFIG['events']['livestream']['ignore_value'];
          break;
        // Set livestream visibility
        case CONFIG['events']['livestream_visibility']['title']:
          $this->setPrivacyStatus($fact);
          break;
        // Set livestream homepage visibility
        case CONFIG['events']['livestream_on_homepage']['title']:
          $this->livestreamOnHomepage = $fact->value === CONFIG['events']['livestream_on_homepage']['value'];
          break;
        //Set livestream calendar behavior
        case CONFIG['events']['livestream_in_calendar']['title']:
          $this->livestreamInCalendar = $fact->value == CONFIG['events']['livestream_on_homepage']['value'];
          break;
      }
    }

    // Extract info from services
    $serviceTypeMatch = array('speaker' => -1);
    foreach ($serviceTypeList as $serviceType) {
      if ($serviceType->title === CONFIG['events']['speaker']) $serviceTypeMatch['speaker'] = $serviceType->id;
    }
    if (isset($data['services']) && is_array($data['services'])) {
      foreach ($data['services'] as $service) {
        switch ($service['service_id']) {
          // Speaker
          case $serviceTypeMatch['speaker']:
            $this->speaker = null === $service['name'] ? '' : $service['name'];
            break;
        }
      }
    }
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
   */
  public function isEventBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast) {
    // Check that a broadcast exists for this event
    if ($this->streamLink == null) {
      return false;
    }
    $videoId = $this->streamLink->getDownloadLink()->getYoutubeVideoId();
    if (empty($videoId)) {
      return false;
    }

    // Compare the two video ids
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
    $subject = !empty($this->subject) ? '"' . $this->subject . '"' : '';
    $speaker = !empty($this->speaker) ? ' mit ' . $this->speaker : '';
    $date = ' am ' . $this->startTime->format('d.m.Y');
    // Build title
    $titleReplacements = array('title', 'subject', 'speaker', 'date');
    foreach ($titleReplacements as $replacement) {
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
    foreach ($descriptionReplacements as $replacement) {
      $broadcastDescription = str_replace("%$replacement%", ${$replacement}, $broadcastDescription);
    }

    return array('title' => $broadcastTitle, 'description' => $broadcastDescription);
  }

  /**
   * Create a new broadcast for this event
   *
   * @param YouTube $youtube The YouTube API interface
   */
  public function createYouTubeBroadcast(YouTube $youtube) {
    // If a broadcast already exists, we are done here
    if (isset($this->broadcast)) return;
    if ($this->streamLink != null) {
      //creating new stream despite link already existing, delete old link
      $this->churchToolsApi->deleteFile($this->streamLink);
    }
    $this->broadcastJustCreated = true;
    $this->youtube = $youtube;
    // Gather information
    $broadcastInformation = $this->getBroadcastInformation();

    // Create YouTube broadcast
    $this->broadcast = $this->youtube->createBroadcast($broadcastInformation['title'], $broadcastInformation['description'], $this->startTime, $this->endTime, $this->thumbnail->getDownloadLink(), $this->privacyStatus);
    // Update the event link
    $this->streamLink = FileConnection::fromExternalUrl(Link::fromYouTubeBroadcast($this->broadcast)->url);

    $this->update();
  }

  public function checkCalendarLink() {
    if ($this->livestreamInCalendar) {
      if ($this->streamLink->getDownloadLink()->url != $this->link->url) {
        $this->link = $this->streamLink->getDownloadLink();
        $this->updateCalendarLink();
      }
    } else {
      if ($this->streamLink->getDownloadLink()->url == $this->link->url) {
        $this->link = new Link('');
        $this->updateCalendarLink();
      }
    }
  }

  /**
   * Delete the broadcast for this event
   */
  public function deleteBroadcast() {
    // Skip if broadcasts should be ignored
    if ($this->livestreamIgnored) {
      return;
    }
    // If no broadcast exists, we are done here
    if (!isset($this->broadcast)) return;
    $this->broadcastJustCreated = false;
    // Delete the YouTube broadcast
    $this->youtube->deleteBroadcast($this->broadcast);
    // Update the event
    unset($this->broadcast);
    $this->streamLink = null;
    if ($this->livestreamInCalendar) {
      $this->link = new Link('');
    }
    $this->update();
    $this->updateCalendarLink();
  }

  /**
   * Update the events broadcast link
   */
  protected function update() {
    // Update the link attachment
    if ($this->streamLink != null) {
      $this->churchToolsApi->deleteFile($this->streamLink);
      $this->churchToolsApi->setStreamLink($this);
    }
  }

  protected function updateCalendarLink() {
    // Make sure this is a single event and not part of a series
    if (!$this->convertToSingleEvent()) {
      echo 'Failed to save event "' . $this->title . '" starting at ' . $this->startTime->format("Y-m-d H:i") . '<br>';
    } else {
      //and then add the link to the calendar entry
      $this->churchToolsApi->updateEventParameters($this, array('link' => $this->link->url));
    }
  }

  /**
   * Make sure this is a single event and not part of a series
   */
  protected function convertToSingleEvent() {
    // It is already a single event, so nothing to do here
    if (0 == $this->rawData['repeat_id']) {
      return true;
    }

    $eventCalData = $this->churchToolsApi->eventGetCalendarData($this);

    /** Get original event **/
    $requestData = array(
      'originEvent' => $eventCalData,
      'splitDate' => $this->startTime->format('Y-m-d H:i'),
      'untilEnd_yn' => 0,
      'browsertabId' => 1
    );

    /** Get new event **/
    $requestData['newEvent'] = $eventCalData;
    $requestData['newEvent']['old_id'] = $requestData['newEvent']['id'];
    unset($requestData['newEvent']['id'], $requestData['newEvent']['exceptions'], $requestData['newEvent']['additions']);
    $requestData['newEvent']['repeat_id'] = 0;
    $requestData['newEvent']['startdate'] = $this->startTime->format('Y-m-d H:i:s');
    $requestData['newEvent']['enddate'] = $this->endTime->format('Y-m-d H:i:s');
    $requestData['newEvent']['csevents'] = array(
      $this->id => $eventCalData['csevents'][$this->id],
    );
    $requestData['newEvent']['csevents'][$this->id]['mark'] = true;
    $requestData['newEvent']['informCreator'] = false;
    $requestData['newEvent']['informMe'] = false;

    /** Get past event **/
    $requestData['pastEvent'] = $eventCalData;
    unset($requestData['pastEvent']['csevents'][$this->id]);
    // Set new exceptionid
    if (!isset($requestData['pastEvent']['exceptions'])) {
      $requestData['pastEvent']['exceptions'] = array();
    }
    $requestData['pastEvent']['exceptionids'] = 0;
    foreach ($requestData['pastEvent']['exceptions'] as $id => $exception) {
      if ($id < $requestData['pastEvent']['exceptionids']) {
        $requestData['pastEvent']['exceptionids'] = $id;
      }
    }
    $requestData['pastEvent']['exceptionids']--;
    // Set new exception
    $requestData['pastEvent']['exceptions'][$requestData['pastEvent']['exceptionids']] = array(
      'id' => $requestData['pastEvent']['exceptionids'],
      'except_date_start' => $this->startTime->format('Y-m-d'),
      'except_date_end' => $this->startTime->format('Y-m-d')
    );

    // Get existing conflicts
    $response = $this->churchToolsApi->sendRequest('ChurchCal', 'getEventChangeImpact', $requestData);

    // Make sure no conflicts exist
    if ('success' == $response['status']) {
      $dataTypes = array('cal', 'services', 'bookings');
      foreach ($dataTypes as $type) {
        if (!isset($response['data'][$type])) {
          continue;
        }
        foreach ($response['data'][$type] as $changeElement) {
          if ('new' != $changeElement['status']) {
            // We found a conflict
            return false;
          }
        }
      }

      // Split the event from the series
      $response = $this->churchToolsApi->sendRequest('ChurchCal', 'saveSplittedEvent', $requestData);

      if ('success' == $response['status'] && isset($response['data']['id'])) {
        // Reload event data - the link is loaded at runtime and will be overwritten by reloading the event data
        $link = $this->link;
        $this->churchToolsApi->reloadEventData($this);
        $this->link = $link;
        return true;
      }
    }
    return false;
  }

  /**
   * Update the events YouTube Broadcast
   */
  public function updateYouTubeBroadcast() {
    // Skip if broadcasts should be ignored
    if ($this->livestreamIgnored) {
      return;
    }
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
  public function setThumbnail(FileConnection|null $file) {
    if ($file !== null) {
      $this->thumbnail = $file;
    } else {
      // No thumbnail was set, so use the default one
      global $youTubeDefaultThumbnail;
      if (null === $youTubeDefaultThumbnail) {
        $youTubeDefaultThumbnail = FileConnection::fromExternalUrl(CONFIG['youtube']['thumbnail']);
      }
      $this->thumbnail = $youTubeDefaultThumbnail;
    }
  }

  /**
   * Collect data for json
   * @return array The objects data for serialization
   */
  public function jsonSerialize(): array {
    return array(
      'id' => $this->id,
      'categoryId' => $this->categoryId,
      'startTime' => $this->startTime,
      'endTime' => $this->endTime,
      'title' => $this->title,
      'description' => $this->description,
      'livestreamEnabled' => $this->livestreamEnabled,
      'livestreamOnHomepage' => $this->livestreamOnHomepage,
      'subject' => $this->subject,
      'speaker' => $this->speaker,
      'streamLink' => $this->streamLink,
      'link' => $this->link,
      'ccCalId' => $this->ccCalId,
      'privacyStatus' => $this->privacyStatus,
      'thumbnail' => $this->thumbnail,
    );
  }

  /**
   * Get the events raw data
   * @return array The events raw data
   */
  public function getRaw() {
    return $this->rawData;
  }

  /**
   * Check if this event has an attached livestream
   * @return bool
   */
  public function hasAttachedBroadcast() {
    return isset($this->broadcast, $this->youtube);
  }
}
