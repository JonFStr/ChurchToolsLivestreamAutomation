<?php

class ChurchTools {
  /**
   * The url of the ChurchTools instance
   */
  private string $instanceUrl;

  /**
   * The id of the user to authenticate with
   */
  private int $userId;

  /**
   * The token of the user to authenticate with
   */
  private string $token;


  /**
   * Setup the ChurchTools instance
   *
   * @param string $instanceUrl The url of the ChurchTools instance
   */
  function __construct(string $instanceUrl, int $userId, string $token) {
    $this->instanceUrl = $instanceUrl;
    $this->userId = $userId;
    $this->token = $token;
    // Login
    $this->httpRequests = new HttpRequest();
    $this->login();
    $this->getCsrfToken();
  }

  /**
   * Unhook from API
   */
  function __destruct() {
    $this->logout();
  }

  /**
   * Send a request to the old ChurchTools API
   *
   * @param string $module The ChurchTools module to reference
   * @param string $function The function in the module to call
   * @param array $params Key value pairs to pass to the function as parameters
   *
   * @return array|false The parsed json response or false on failure
   * @TODO: Implement logging
   */
  public function sendRequest(string $module, string $function, array $params = array()) {
    $requestUrl = $this->instanceUrl . '?q=' . strtolower($module) . '/ajax';
    $params['func'] = strtolower($function);
    $response = $this->httpRequests->getJson($requestUrl, $params);

    if (!isset($response['status']) || $response['status'] !== 'success') {
      var_export($params);
      echo var_export($response, true);
      exit;
      return false;
    } else return $response;
  }

  /**
   * Send a request to the ChurchTools REST API
   *
   * @param string $path The API path
   * @param string $type Request type
   * @param array $params Additional parameters
   *
   * @return array|false The parsed response or false on failure
   * @TODO: Implement logging
   */
  protected function sendRestRequest(string $path, string $type = 'POST', array $params = array()) {
    $requestUrl = $this->instanceUrl . 'api' . $path;
    $response = $this->httpRequests->getJson($requestUrl, $params, $type, false);

    return $response;
  }

  /**
   * Login to the API
   */
  protected function login() {
    $response = $this->sendRequest('login', 'loginWithToken', array('id' => $this->userId, 'token' => $this->token));
    if (false === $response) throw new LoginFailure();
  }

  /**
   * Logout from the API
   */
  protected function logout() {
    $response = $this->sendRequest('login', 'logout', array('id' => $this->userId, 'token' => $this->token));
    if (false === $response) throw new LoginFailure();
  }

  /**
   * Get a CSRF token to validate future requests
   */
  private function getCsrfToken() {
    $requestUrl = $this->instanceUrl . 'api/csrftoken';
    $response = $this->httpRequests->getJson($requestUrl, array(), 'GET');
    $response = $this->httpRequests->setCsrfToken($response['data']);
  }

  /**
   * Load all upcoming events
   *
   * @param DateTimeInterface $latestStart Only get events that start before this time
   *
   * @return Event[] A list of all upcoming events
   */
  public function getUpcomingEvents(DateTimeInterface $latestStart) {
    // Collect all data
    $response = $this->sendRequest('ChurchService', 'getAllEventData');

    $eventDataList = $response['data'];
    $factList = $this->getAllFacts();
    $serviceTypes = $this->getAllServiceTypes();
    // Build the events
    $eventList = array();
    $now = new DateTimeImmutable();
    $i = 0;

    foreach ($eventDataList as $eventId => $eventData) {
      // Only keep upcoming events
      if (new DateTimeImmutable($eventData['enddate']) < $now) continue;
      // Make sure the event isn't too far in the future
      if ($latestStart < new DateTimeImmutable($eventData['startdate'])) continue;

      $eventFactList = isset($factList[$eventId]) ? $factList[$eventId] : array();
      $event = new Event($eventData, $this, $eventFactList, $serviceTypes);

      $eventList[] = $event;
    }
    return $eventList;
  }

  /**
   * Reload an events data
   *
   * @param Event $event the event to reload
   */
  public function reloadEventData(Event $event) {
    // Get event data
    $response = $this->sendRequest('ChurchService', 'getAllEventData', array('id' => $event->id));
    $eventData = $response['data'][$event->id];
    $eventDataList = $response['data'];

    // Get facts
    $factList = $this->getAllFacts();
    $eventFactList = isset($factList[$event->id]) ? $factList[$event->id] : array();

    // Get service types
    $serviceTypes = $this->getAllServiceTypes();

    // Load data into event
    $event->loadData($eventData, $eventFactList, $serviceTypes);
  }

  /**
   * Get the facts of all events
   *
   * @return array A list of all facts grouped by event ids
   */
  public function getAllFacts() {
    // Get fact config
    $request = $this->sendRequest('ChurchService', 'getMasterData');
    $factConfig = $request['data']['fact'];
    // Get the actual facts
    $request = $this->sendRequest('ChurchService', 'getAllFacts');
    $factsData = $request['data'];

    // Combine the two
    $factList = array();
    foreach ($factsData as $eventId => $factArray) {
      if (!isset($factList[$eventId])) $factList[$eventId] = array();
      foreach ($factArray as $factData) {
        // Create a new fact object
        $fact = new Fact($factData, $factConfig);
        $factList[$eventId][] = $fact;
      }
    }
    return $factList;
  }

  /**
   * Get all service types
   *
   * @return array A list of all service types
   */
  public function getAllServiceTypes() {
    $request = $this->sendRequest('ChurchService', 'getMasterData');
    $serviceTypeList = array();
    foreach ($request['data']['service'] as $serviceTypeData) {
      $serviceTypeList[] = new ServiceType($serviceTypeData);
    }
    return $serviceTypeList;
  }

  /**
   * Update event parameters
   *
   * @param Event $event The event to update
   * @param array $paramsToUpdate A list of key-value pairs to update
   *
   * @return bool If the update was a success
   */
  public function updateEventParameters(Event $event, array $paramsToUpdate) {
    $path = '/calendars/' . $event->categoryId . '/appointments/' . $event->ccCalId;
    // Get current event data
    $current_data = $this->sendRestRequest($path, 'GET')['data']['appointment'];
    unset($current_data['calendar']);

    $current_data += array(
      "informCreator" => "false",
      "informMe" => "false",
    );

    // Add parameters
    foreach ($paramsToUpdate as $key => $value) {
      $current_data[$key] = $value;
    }

    $response = $this->sendRestRequest($path, 'PUT', $current_data);
    return isset($response['data']);
  }

  /**
   * Get all of an events files
   *
   * @param Event $event The event to get the files for
   *
   * @return array All the events files
   */
  public function getEventFiles(Event $event) {
    // Get all files
    $response = $this->sendRequest('ChurchService', 'getFiles', array());
    $fileList = array();

    // Convert all files to objects
    foreach ($response['data'] as $fileData) {
      if ($fileData['domain_type'] == 'service' && $fileData['domain_id'] == $event->id) {
        $fileList[] = FileConnection::fromChurchToolsFileData($fileData, $this);
      }
    }

    return $fileList;
  }

  /**
   * Get an events calender data
   *
   * @param Event $event The event to get the calendar data for
   *
   * @return array The events calendar data
   */
  public function eventGetCalendarData(Event $event) {
    $response = $this->sendRequest('ChurchCal', 'getCalPerCategory', array('category_ids' => array($event->categoryId)));

    if (isset($response['data'][$event->categoryId][$event->ccCalId])) {
      return $response['data'][$event->categoryId][$event->ccCalId];
    } else {
      return array();
    }
  }

  /**
   * Download a file
   *
   * @return string The file in binary form
   */
  public function downloadFile(int $fileId, string $fileName) {
    $query = '?q=churchservice/filedownload&filename=' . $fileName . '&id=' . $fileId;
    $file = $this->httpRequests->getRaw($this->instanceUrl . $query);

    return $file;
  }
}
