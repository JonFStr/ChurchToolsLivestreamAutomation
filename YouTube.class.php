<?php

class YouTube {
  /**
   * The service handling all YouTube API requests
   */
  protected $service = null;
  /**
   * The broadcasts data parts that are handeled by this class
   */
  protected const broadcastParts = 'contentDetails,snippet,status';

  /**
   * Setup API connection
   */
  function __construct() {
    // Make sure the YouTube API is present
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
      throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
    }
    require_once __DIR__ . '/vendor/autoload.php';

    // Setup Google client
    $this->client = new Google_Client();
    $this->client->setApplicationName('ChurchTools Livestream Creator');
    $this->client->setScopes([
      'https://www.googleapis.com/auth/youtube.force-ssl',
    ]);
    $this->client->setAuthConfig('client_secret.json');
    $this->client->setAccessType('offline');

    // Request a access token on first use
    if (empty(CONFIG['youtube']['refreshToken'])) {
      $this->getRefreshToken();
    } else {
      // Otherwise setup YouTube API
      $this->connectWithToken();
    }

    // Make sure a streamKeyId is set
    if (empty(CONFIG['youtube']['streamKeyId'])) {
      // Show all available stream keys to the user
      $this->displayStreamKeyList();
      // Make this api invalid
      $this->service = null;
    }
  }

  /**
   * Does a connection to the YouTube API exist?
   *
   * @return bool Whether a connection exists
   */
  public function isValid() {
    return null !== $this->service;
  }

  /**
   * Request an auth code from the YouTube API
   */
  private function requestAuthCode() {
    // Request authorization from the user
    $authUrl = $this->client->createAuthUrl();
    echo "Authenticate the app by visiting the following link:\r\n";
    echo $authUrl;
  }

  /**
   * Handle an auth code request from the YouTube API
   */
  private function getRefreshToken() {
    if (!isset($_GET['code'])) {
      $this->requestAuthCode();
      return;
    }
    $authCode = $_GET['code'];

    // Exchange authorization code for an access token.
    $response = $this->client->fetchAccessTokenWithAuthCode($authCode);
    if (isset($response['error'])) throw new Exception($response['error_description']);

    // Save the refresh token
    $configFileContent = file_get_contents(CONFIG_FILE);
    $configFileContent = preg_replace("/'refreshToken' => ['\"].*['\"],/m", sprintf("'refreshToken' => '%s',", $response['refresh_token']), $configFileContent);
    file_put_contents(CONFIG_FILE, $configFileContent);
    // Done
    echo 'Connection to YouTube API established, the program can now run autonomously<br>';
  }

  /**
   * Connect to the YouTube API with the refresh or access token
   */
  private function connectWithToken() {
    $accessToken = json_decode(CONFIG['youtube']['accessToken'], true);
    if (is_array($accessToken) && !isset($accessToken['error'])) {
      $this->client->setAccessToken($accessToken);
    }
    // Check if the token is expired
    if (!is_array($accessToken) || isset($accessToken['error']) || $this->client->isAccessTokenExpired()) {
      // Update the access token
      $accessToken = $this->client->fetchAccessTokenWithRefreshToken(CONFIG['youtube']['refreshToken']);
      // Don't store the refresh token in the same place
      if (isset($accessToken['refresh_token'])) {
        unset($accessToken['refresh_token']);
      }
      // Save the access token
      $configFileContent = file_get_contents(CONFIG_FILE);
      $configFileContent = preg_replace("/'accessToken' => ['\"].*['\"],/m", sprintf("'accessToken' => '%s',", json_encode($accessToken)), $configFileContent);
      file_put_contents(CONFIG_FILE, $configFileContent);
    }

    // Define service object for making API requests.
    $this->service = new Google_Service_YouTube($this->client);
  }

  /**
   * Get all scheduled broadcasts
   *
   * @return array A list of all scheduled broadcasts
   */
  public function getAllScheduledBroadcasts() {
    $queryParams = [
      'broadcastStatus' => 'upcoming',
      'pageToken' => '',
    ];
    $broadcastList = array();

    // Fetch all pages of scheduled broadcasts
    do {
      $response = $this->service->liveBroadcasts->listLiveBroadcasts($this::broadcastParts, $queryParams);
      $broadcastList = array_merge($broadcastList, $response['items']);
      $queryParams['pageToken'] = isset($response['nextPageToken']) ? $response['nextPageToken'] : '';
    } while ('' !== $queryParams['pageToken']);

    return $broadcastList;
  }

  /**
   * Get all active boradcasts
   *
   * @return array A list of all active boradcasts
   */
  public function getAllActiveBroadcasts() {
    $queryParams = [
      'broadcastStatus' => 'active',
      'pageToken' => '',
    ];
    $broadcastList = array();

    // Fetch all pages of scheduled broadcasts
    do {
      $response = $this->service->liveBroadcasts->listLiveBroadcasts($this::broadcastParts, $queryParams);
      $broadcastList = array_merge($broadcastList, $response['items']);
      $queryParams['pageToken'] = isset($response['nextPageToken']) ? $response['nextPageToken'] : '';
    } while ('' !== $queryParams['pageToken']);

    return $broadcastList;
  }

  /**
   * Get all active boradcasts
   *
   * @return array A list of all active boradcasts
   */
  public function getAllScheduledAndActiveBroadcasts() {
    return array_merge($this->getAllScheduledBroadcasts(), $this->getAllActiveBroadcasts());
  }

  /**
   * Create a new broadcast
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The base broadcast to build on
   * @param string $title The broadcasts title
   * @param string $description A description for the broadcast
   * @param DateTime $startTime Time when the broadcast is scheduled to start
   * @param DateTime $endTime Time when the broadcast is scheduled to end
   * @param YouTubePrivacyStatus $privacyStatus How visible should the broadcast be
   *
   * @return Google_Service_YouTube_LiveBroadcast $broadcast The created broadcast
   */
  private function buildBroadcastObject(Google_Service_YouTube_LiveBroadcast $broadcast, string $title, string $description, DateTimeInterface $startTime, DateTimeInterface $endTime, YouTubePrivacyStatus $privacyStatus) {
    // Add 'contentDetails' object to the $liveBroadcast object.
    $broadcastContentDetails = $broadcast->getContentDetails();
    if (null === $broadcastContentDetails) $broadcastContentDetails = new Google_Service_YouTube_LiveBroadcastContentDetails();
    $broadcastContentDetails->setEnableClosedCaptions(false);
    $broadcastContentDetails->setEnableEmbed(true);
    $broadcastContentDetails->setLatencyPreference('low');
    $broadcastContentDetails->setRecordFromStart(true);
    $broadcast->setContentDetails($broadcastContentDetails);

    // Add 'snippet' object to the $liveBroadcast object.
    $broadcastSnippet = $broadcast->getSnippet();
    if (null === $broadcastSnippet) $broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet();
    $broadcastSnippet->setTitle($title);
    $broadcastSnippet->setDescription($description);
    $broadcastSnippet->setScheduledStartTime($startTime->format(DateTime::ATOM));
    $broadcastSnippet->setScheduledEndTime($endTime->format(DateTime::ATOM));
    $broadcast->setSnippet($broadcastSnippet);

    // Add 'status' object to the $liveBroadcast object.
    $broadcastStatus = $broadcast->getStatus();
    if (null === $broadcastStatus) $broadcastStatus = new Google_Service_YouTube_LiveBroadcastStatus();
    $broadcastStatus->setPrivacyStatus($privacyStatus->status());
    $broadcastStatus->setSelfDeclaredMadeForKids(false);
    $broadcast->setStatus($broadcastStatus);

    return $broadcast;
  }

  /**
   * List all available stream keys to the user to add to the configuration
   */
  protected function displayStreamKeyList() {
    $streamList = array();
    $queryParams = array(
      'mine' => true,
    );
    // Get all available livestreams
    do {
      $response = $this->service->liveStreams->listLiveStreams('snippet', $queryParams);
      $streamList = array_merge($streamList, $response['items']);
      $queryParams['pageToken'] = isset($response['nextPageToken']) ? $response['nextPageToken'] : '';
    } while ('' !== $queryParams['pageToken']);

    // Display them to the user
    echo 'No valid streamKeyId has been set yet. Please choose one from below and insert it in the config.php file under "youtube > streamKeyId":<br><br>';
    foreach ($streamList as $stream) {
      echo $stream->snippet->title . '<br>';
      echo $stream->id . '<br>';
      echo '<hr><br>';
    }
  }

  /**
   * Bind stream key and thumbnail to the broadcast and  update it
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast to update
   * @param Link $thumbnailLink A link to the broadcasts thumbnail
   *
   * @return Google_Service_YouTube_LiveBroadcast The updated broadcast
   */
  private function bindStreamKeyAndThumbnailToBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast, Link $thumbnailLink) {
    // Bind a stream key to the broadcast
    if (isset(CONFIG['youtube']['streamKeyId']) && !empty(CONFIG['youtube']['streamKeyId'])) {
      $this->service->liveBroadcasts->bind($broadcast->id, 'snippet', array('streamId' => CONFIG['youtube']['streamKeyId']));
    }
    // Upload thumbnail
    $thumbnailResponse = $this->service->thumbnails->set($broadcast->id, array(
      'data' => file_get_contents($thumbnailLink->url),
      'mimeType' => 'application/octet-stream',
      'uploadType' => 'multipart',
    ));

    // Add the thumbnail to the broadcast
    $thumbnailDetails = $thumbnailResponse->items[0];
    $broadcastSnippet = $broadcast->getSnippet();
    $broadcastSnippet->setThumbnails($thumbnailDetails);
    $broadcast->setSnippet($broadcastSnippet);
    $broadcast = $this->service->liveBroadcasts->update($this::broadcastParts, $broadcast);

    return $broadcast;
  }

  /**
   * Create a new broadcast
   *
   * @param string $title The broadcasts title
   * @param string $description A description for the broadcast
   * @param DateTime $startTime Time when the broadcast is scheduled to start
   * @param DateTime $endTime Time when the broadcast is scheduled to end
   * @param Link $thumbnailLink A link to the broadcasts thumbnail
   * @param YouTubePrivacyStatus $privacyStatus How visible should the broadcast be
   *
   * @return Google_Service_YouTube_LiveBroadcast $broadcast The created broadcast
   */
  public function createBroadcast(string $title, string $description, DateTimeInterface $startTime, DateTimeInterface $endTime, Link $thumbnailLink, YouTubePrivacyStatus $privacyStatus) {
    $broadcast = $this->buildBroadcastObject(new Google_Service_YouTube_LiveBroadcast(), $title, $description, $startTime, $endTime, $privacyStatus);
    // Create the broadcast on YouTube
    $createdBroadcast = $this->service->liveBroadcasts->insert($this::broadcastParts, $broadcast);

    // Add stream key and thumbnail to the broadcast
    $createdBroadcast = $this->bindStreamKeyAndThumbnailToBroadcast($createdBroadcast, $thumbnailLink);
    return $createdBroadcast;
  }

  /**
   * Update an existing broadcast
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast to update
   * @param string $title The broadcasts title
   * @param string $description A description for the broadcast
   * @param DateTime $startTime Time when the broadcast is scheduled to start
   * @param DateTime $endTime Time when the broadcast is scheduled to end
   * @param Link $thumbnailLink A link to the broadcasts thumbnail
   * @param YouTubePrivacyStatus $privacyStatus How visible should the broadcast be
   *
   * @return Google_Service_YouTube_LiveBroadcast $broadcast The updated broadcast
   */
  public function updateBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast, string $title, string $description, DateTimeInterface $startTime, DateTimeInterface $endTime, Link $thumbnailLink, YouTubePrivacyStatus $privacyStatus) {
    // Update broadcast information
    $broadcast = $this->buildBroadcastObject($broadcast, $title, $description, $startTime, $endTime, $privacyStatus);
    $updatedBroadcast = $this->bindStreamKeyAndThumbnailToBroadcast($broadcast, $thumbnailLink);

    return $updatedBroadcast;
  }

  /**
   * Delete the given broadcast
   *
   * @param Google_Service_YouTube_LiveBroadcast $broadcast The broadcast to delete
   */
  public function deleteBroadcast(Google_Service_YouTube_LiveBroadcast $broadcast) {
    $this->service->liveBroadcasts->delete($broadcast->id);
  }
}
