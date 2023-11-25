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
   * CSRF Token
   */
  private string $csrfToken = '';

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
   * Use php curl extension to do requests
   *
   * @param string $url The url to send the request to
   * @param array $data The data to send with the request
   * @param string $method The request method to use
   *
   * @return array The response
   */
  public function getRawCurl(string $url, array $data=array(), string $method='POST') {
    $header = array();
    // Set csrf header
    if ('' !== $this->csrfToken) {
      $header[] = "CSRF-Token: " . $this->csrfToken . "\r\n";
    }

    // Set authentication header
    if (!empty($this->user)) {
      $header[] = "Authorization: Basic " . base64_encode("$this->user:$this->password") . "\r\n";
    }

    // Set data
    if ('GET' === $method) {
      $url .= (strpos($url, '?') !== false) ? '&' : '?';
      $url .= http_build_query($data);
    }

    // Set request method
    $handle = curl_init($url);
    switch ($method) {
      case 'GET':
        break;
      case 'POST':
        curl_setopt($handle, CURLOPT_POST, 1);
        break;
      case 'PUT':
        curl_setopt($handle, CURLOPT_PUT, 1);
        break;
      default:
        echo 'Unsupported request method "' . $method . '"';
        return false;
    }
    
    // Set header and options
    if ('GET' !== $method) {
      $post_data = http_build_query($data);
      curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
      $header[] = 'Content-Length: ' . strlen($post_data);
    }
    curl_setopt($handle, CURLOPT_HEADEROPT, 0);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $header);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($handle, CURLOPT_HEADER, 1);
    
    // Set cookies
    curl_setopt($handle, CURLOPT_COOKIE, $this->getCookies());
    
    // Run request and parse response
    $result = curl_exec($handle);
    $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $response_header = substr($result, 0, $header_size);
    $http_response_header = preg_split("/\r\n|\n|\r/", $response_header);
    $body = substr($result, $header_size);
    $status_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    
    // Save login cookies for next request
    $this->saveCookies($http_response_header);

    if(!$result)
    {
      trigger_error(curl_error($handle));
      echo "url\n";
      var_dump($url);
      echo "request header\n";
      var_dump($header);
      echo "method\n";
      var_dump($method);
      echo "response body\n";
      var_dump($result);
      exit();
    }
    return $body;
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
    if ('GET' === $method) {
      $header .= "Content-type: application/x-www-form-urlencoded\r\n";
    }
    else {
      $header .= "Content-type: application/json; charset=UTF-8\r\n";
    }

    // Set context
    $options = array(
      'http' => array(
        'header'  => $header,
        'method'  => $method,
        'ignore_errors' => true,
      ),
    );

    // Set data
    if ('GET' !== $method) {
      $options['http']['content'] = json_encode($data);
      $options['http']['header'] .= "Content-Length: " . strlen($options['http']['content']) . "\r\n";
    }
    else {
      $url .= (strpos($url, '?') !== false) ? '&' : '?';
      $url .= http_build_query($data);
    }

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Save login cookies for next request
    $this->saveCookies($http_response_header);

    if (' 200 OK' == substr($http_response_header[0], -7)) {
      return $result;
    }
    else {
      var_dump($options);
      var_export($result);
      return false;
    }
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
      if (preg_match('#^[Ss]et-[Cc]ookie:\s*([^;]+)#i', $header, $matches)) {
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
