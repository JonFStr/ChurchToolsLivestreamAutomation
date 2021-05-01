<?php

class HttpRequest {
// TODO: Implement logging
  /**
   * All cookies saved during this session
   */
  private array $savedCookies = array();
  /**
   * A user to authenticate with
   */
  private string $user = '';
  /**
   * A password to authenticate with
   */
  private string $password = '';

  /**
   * Setup authentication
   *
   * @param string $username A user to authenticate with
   * @param string $password The password coresponding to the username
   */
  function __construct(string $username='', string $password='') {
    $this->user = $username;
    $this->password = $password;
  }

  /**
   * Send request to $url with $data and get raw data in return
   *
   * @param string $url The url to send the request to
   * @param array $data The data to send with the request
   * @param string $method The request method to use
   *
   * @return array The response
   */
  public function getRaw(string $url, array $data=array(), string $method='POST') {
    $header = '';
    // Set csrf header
    if ('' !== $this->csrfToken) {
      $header .= "CSRF-Token: " . $this->csrfToken . "\r\n";
    }

    // Set authentication header
    if (!empty($this->user)) {
      $header .= "Authorization: Basic " . base64_encode("$this->user:$this->password") . "\r\n";
    }

    // Set cookies
    $header .= "Cookie: " . $this->getCookies() . "\r\n";

    // Set content type
    $header .= "Content-type: application/x-www-form-urlencoded\r\n";

    // Set context
    $options = array(
      'http' => array(
        'header'  => $header,
        'method'  => $method,
      ),
    );

    // Set data
    if ('GET' !== $method) {
      $options['http']['content'] = http_build_query($data);
    }
    else {
      $url .= (strpos($url, '?') !== false) ? '&' : '?';
      $url .= http_build_query($data);
    }

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Save login cookies for next request
    $this->saveCookies($http_response_header);

    return $result;
  }

  /**
   * Send request to $url with $data and get a json object in return
   *
   * @param string $url The url to send the request to
   * @param array $data The data to send with the request
   * @param string $method The request method to use
   *
   * @return array The parsed json response
   */
  public function getJson(string $url, array $data=array(), string $method='POST') {
    $result = $this->getRaw($url, $data, $method);
    $decoded = json_decode($result, true);

    if (null === $decoded) {
      return array('status' => 'failure');
    }
    else return $decoded;
  }

  /**
   * Get all saved cookies
   *
   * @return string Combined cookies
   */
  private function getCookies() {
    $combinedCookies = '';

    foreach ($this->savedCookies as $key => $value) {
      $combinedCookies .= $key . '=' . $value . ';';
    }
    return $combinedCookies;
  }

  /**
   * Save cookies from a http response header
   *
   * @param array $httpResponseHeader
   */
  private function saveCookies($httpResponseHeader) {
    $cookies = array();
    foreach ($httpResponseHeader as $header) {
      if (preg_match('#^Set-Cookie:\s*([^;]+)#i', $header, $matches)) {
          parse_str($matches[1], $tmp);
          $cookies += $tmp;
      }
    }

    foreach ($cookies as $key => $value) {
      $this->savedCookies[$key] = $value;
    }
  }

  /**
   * Set the CSRF token for all future requests
   *
   * @param string $token The CSRF token
   */
  public function setCsrfToken(string $token) {
    $this->csrfToken = $token;
  }
}
