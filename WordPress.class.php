<?php

class WordPress {
  /**
   * Url to WordPress instance
   */
  private Link $instanceUrl;
  /**
   * Http request handler
   */
  private HttpRequest $httpRequests;

  /**
   * Establish WordPress API connection
   */
  function __construct() {
    $this->instanceUrl = new Link(CONFIG['wordpress']['url']);
    $this->httpRequests = new HttpRequest(CONFIG['wordpress']['user'], CONFIG['wordpress']['application_password']);
  }

  /**
   * Render the WordPress content of the given template for an event
   *
   * @param Event $event The event to generate the content for
   * @param string $template The content template to put all data in
   * @param array[Event] $eventList List of all events, ordered descending by end date
   *
   * @return string Generated shortcode
   */
 protected static function renderEventContentTemplate(Event $event, string $template, array $eventList) {
    if (!$event->livestreamEnabled) return '';

    // Set pre time
    $preTime = (clone $event->startTime)->modify(sprintf('-%d day', CONFIG['wordpress']['days_to_show_button_in_advance']));
    // Check if parallel pre times aren't allowed
    if (!CONFIG['wordpress']['allow_parallel_event_pre_times']) {
      foreach ($eventList as $otherEvent) {
        if ($event == $otherEvent) continue;
        $otherEventPreTime = (clone $otherEvent->startTime)->modify(sprintf('-%d day', CONFIG['wordpress']['days_to_show_button_in_advance']));
        // Check if the two events collide
        if ($otherEventPreTime < $preTime && $preTime < $otherEvent->endTime) {
          $preTime = $otherEvent->endTime;
        }
      }
    }
    // Gather all data
    $keysToReplace = array(
      'pre_time' => $preTime->format(DateTime::ATOM),
      'start_time' => $event->startTime->format(DateTime::ATOM),
      'end_time' => $event->endTime->format(DateTime::ATOM),
      'video_link' => $event->link->url,
      'title' => $event->title,
      'datetime' => $event->startTime->format('d.m. h:i'),
    );

    // Generate the content
    foreach($keysToReplace as $key => $value) {
      $template = str_replace('%' . $key . '%', $value, $template);
    }

    return $template;
  }

  /**
   * Get all WordPress pages
   * @return array[WordPressPage] All gathered WordPress pages
   */
  protected function getPages() {
    $gatheredPages = array();
    $data = array(
      'context' => 'edit',
      'page' => 0,
    );

    // Get all pages
    while(True) {
      // Get next set of pages
      $data['page']++;
      $newPages = $this->httpRequests->getJson($this->instanceUrl->url . 'wp-json/wp/v2/pages', $data, 'GET');
      if (isset($newPages['status']) && 'success' !== $newPages['success']) {
        break;
      }
      // Save gathered pages
      $gatheredPages = array_merge($gatheredPages, $newPages);
    }

    // Convert all pages to objects
    $pageObjectsList = array();
    foreach ($gatheredPages as $pageData) {
      $page = new WordPressPage($pageData);
      $pageObjectsList[] = $page;
    }

    return $pageObjectsList;
  }

  /**
   * Update the WordPress post with new event data
   *
   * @param array[Event] $eventList A list of all current events
   */
   public function updateEventEntries(array $eventList) {
     // Order events in descending order by end time
     $endTimeEventList = $eventList;
     usort($endTimeEventList, function (Event $a, Event $b) { return -($a->endTime <=> $b->endTime); });
     // Order events in ascending order by start time
     usort($eventList, function (Event $a, Event $b) { return ($a->startTime <=> $b->startTime); });

     // Render all templates
     $renderedTemplates = array();
     foreach (CONFIG['wordpress']['content_templates'] as $templateKey => $templateContent) {
       $renderedTemplates[$templateKey] = '';
       // Add every event
       foreach ($eventList as $event) {
         $renderedTemplates[$templateKey] .= $this->renderEventContentTemplate($event, $templateContent, $eventList);
       }
     }

     // Update all pages
     foreach (CONFIG['wordpress']['pages'] as $pageId => $templateKey) {
       $page = $this->getPageById($pageId);
       $page->applyTemplateToContent($renderedTemplates[$templateKey]);
       $this->updatePage($page);
     }
   }

   /**
    * Get a WordPress page
    * @param int $pageId The id of the page to get
    * @return WordPressPage|false The page or false if it couldn't be found
    */
   protected function getPageById(int $pageId) {
     $data = array(
       'context' => 'edit',
     );

     $pageData = $this->httpRequests->getJson($this->instanceUrl->url . 'wp-json/wp/v2/pages/' . (string)$pageId, $data, 'GET');
     if (isset($pageData['status']) && 'failure' === $pageData['success']) {
       return false;
     }
     $page = new WordPressPage($pageData);
     // Make sure the page is valid
     return $page->isValid() ? $page : false;
   }

   /**
    * Update a pages content
    * @param WordPressPage The page to update
    */
   protected function updatePage(WordPressPage $page) {
     $data = array(
       'content' => array( 'raw' => $page->getContent(), ),
     );
     // Update the page
     $this->httpRequests->getJson($this->instanceUrl->url . 'wp-json/wp/v2/pages/' . (string)$page->getId(), $data, 'POST');
   }
}
