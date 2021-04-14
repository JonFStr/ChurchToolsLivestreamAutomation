<?php

class HttpRequest {
// TODO: Implement logging
  /**
   * All cookies saved during this session
   */
  private array $savedCookies = array();

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
    $CSRF = '';
    if ('' !== $this->csrfToken) {
      $CSRF = "CSRF-Token: " . $this->csrfToken . "\r\n";
    }

    $options = array(
      'http' => array(
        'header'  => $CSRF . "Cookie: " . $this->getCookies() . "\r\nContent-type: application/x-www-form-urlencoded\r\n",
        'method'  => $method,
        'content' => http_build_query($data),
      ),
    );

    $context  = stream_context_create($options);
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
