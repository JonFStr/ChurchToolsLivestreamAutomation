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
   * Send a request to the ChurchTools API
   *
   * @param string $module The ChurchTools module to reference
   * @param string $function The function in the module to call
   * @param array $params Key value pairs to pass to the function as parameters
   *
   * @return array|false The parsed json response or false on failure
   * @TODO: Implement logging
   */
  protected function sendRequest(string $module, string $function, array $params=array()) {
    $request_url = $this->instanceUrl . '?q=' . strtolower($module) . '/ajax';
    $params['func'] = strtolower($function);
    $response = $this->httpRequests->getJson($request_url, $params);

    if ($response['status'] !== 'success') {
      echo '<pre>'; var_export( $response ); echo '</pre>';
      return false;
    }
    else return $response;
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
   * @return array A list of all upcoming events
   */
  public function getUpcomingEvents(DateTimeInterface $latestStart) {
    // Collect all data
    $response = $this->sendRequest('ChurchService', 'getAllEventData');
    $eventDataList = $response['data'];
    $factList = $this->getAllFacts();
    $serviceTypes = $this->getAllServiceTypes();
    // Build the events
    $eventList = array();
    foreach($eventDataList as $eventId => $eventData) {
      $eventFactList = isset($factList[$eventId]) ? $factList[$eventId] : array();
      $event = new Event($eventData, $this, $eventFactList, $serviceTypes);
      // Only keep upcoming events
      if ($event->endTime < new DateTimeImmutable()) continue;
      // Make sure the event isn't too far in the future
      if ($latestStart < $event->startTime) continue;
      $eventList[] = $event;
    }
    return $eventList;
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
    foreach($factsData as $eventId => $factArray) {
      if (!isset($factList[$eventId])) $factList[$eventId] = array();
      foreach($factArray as $factData) {
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
    foreach($request['data']['service'] as $serviceTypeData) {
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
    $requestData = array(
      'event_id' => $event->id,
      'category_id' => $event->categoryId,
    	"id" => $event->ccCalId,
    	"informCreator" => "false",
    	"informMe" => "false"
    );
    // Add parameters
    foreach ($paramsToUpdate as $key => $value) {
      if (isset($event->$key)) {
        $requestData[$key] = $value;
      }
    }

    $response = $this->sendRequest('ChurchService', 'updateEvent', $requestData);
    return isset($response['status']) && 'success' === $response['status'];
  }
}
