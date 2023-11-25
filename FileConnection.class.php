<?php

// TODO: Implement local file
class FileConnection implements JsonSerializable {
  /**
   * Available file connections
   */
  protected const CONNECTIONS = array(
    'url' => 0,
    'churchtools' => 1,
  );
  /**
   * This files object connection
   */
  protected string $connection;
  /**
   * ChurchTools API connection
   */
  protected ChurchTools $churchToolsApi;
  /**
   * Special ChurchTools file name
   */
  protected string $churchToolsName;
  /**
   * Unique identifier
   */
  protected int $id;
  /**
   * File name
   */
  protected string $name;
  /**
   * File extension
   */
  protected string $extension;
  /**
   * The files location on the server
   */
  protected string $location = '';

  /**
   * Initate the file
   *
   * @param int $connection The type of connection this file has
   * @param string $name Filename; with extension
   */
  protected function __construct(int $connection, string $name) {
    // Set the files connection
    if (in_array($connection, FileConnection::CONNECTIONS)) {
      $this->connection = $connection;
    }
    else {
      throw new InvalidArgumentException("Invalid file connection type");
    }

    // Set file name and extension
    if (preg_match('#(?<name>^.+)\.(?<extension>[^.]+$)#', $name, $nameParts) && isset($nameParts['name'], $nameParts['extension'])) {
      $this->name = $nameParts['name'];
      $this->extension = $nameParts['extension'];
      // TODO: Extract file type from extension
    }
    else {
      $this->name = $name;
      $this->extension = '';
    }
  }

  /**
   * Get the files download link
   *
   * @return Link The download link
   */
  public function getDownloadLink() {
    switch($this->connection) {
      case FileConnection::CONNECTIONS['url']:
        // Nothing to do here
        return new Link($this->location);
      case FileConnection::CONNECTIONS['churchtools']:
        // Check the file was already downloaded
        if (!file_exists($this->location)) {
          $this->saveChurchToolsFileLocaly();
        }
        return new Link(CONFIG['url'] . $this->location);
      default:
        return new Link('');
    }
  }

  /**
   * Download a ChurchTools file and save it localy
   */
  protected function saveChurchToolsFileLocaly() {
    $fileContents = $this->churchToolsApi->downloadFile($this->id, $this->churchToolsName);
    $fileLocation = 'img/' . $this->churchToolsName;
    file_put_contents($fileLocation, $fileContents);
    $this->location = $fileLocation;
  }

  /**
   * Create a new object from ChurchTools files data
   *
   * @param array $fileData ChurchTools files data
   *
   * @return FileConnection The create file object
   */
  public static function fromChurchToolsFileData(array $fileData, ChurchTools $churchToolsApi) {
    // This is an actual ChurchTools file
    if (isset($fileData['image_options']) && null !== $fileData['image_options']) {
      $file = new FileConnection(FileConnection::CONNECTIONS['churchtools'], $fileData['bezeichnung']);
      $file->churchToolsApi = $churchToolsApi;
      $file->id = $fileData['id'];
      $file->churchToolsName = $fileData['filename'];
    }
    // This is an external url
    else {
      $file = FileConnection::fromExternalUrl($fileData['filename']);
    }

    return $file;
  }

  /**
   * Create a new object from an external url
   *
   * @param string $url Link to the external file
   *
   * @return FileConnection The create file object
   */
  public static function fromExternalUrl(string $url) {
    // Extrac the filename
    $explodedUrl = explode('/', $url);
    $query = $explodedUrl[count($explodedUrl) - 1];
    $explodedQuery = explode('?', $query);
    $fileName = $explodedQuery[0];
    // Create the file connection
    $file = new FileConnection(FileConnection::CONNECTIONS['url'], $fileName);
    $file->location = $url;

    return $file;
  }

  /**
   * Get the files name
   *
   * @return string The files name
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Collect data for json
   * @return Link The files link
   */
  public function jsonSerialize(): Link {
    return $this->getDownloadLink();
  }
}
