<?php

class WordPress {
  /**
   * Establish WordPress API connection
   */
  function __construct() {

  }

  /**
   * Generate the shortcode for a livestream button of an event
   *
   * @param Event $event The event to generate the shortcode for
   *
   * @return string Generated shortcode
   */
  public function generateEventLivestreamButtonShortcode(Event $event) {
    if (!$event->livestreamEnabled) return '';
    // Gather all data
    $keysToReplace = array(
      'pre_time' => (clone $event->startTime)->modify(sprintf('-%d day', CONFIG['wordpress']['days_to_show_button_in_advance']))->format(DateTime::ATOM),
      'start_time' => $event->startTime->format(DateTime::ATOM),
      'end_time' => $event->endTime->format(DateTime::ATOM),
      'video_link' => $event->link->url,
      'title' => $event->title,
      'datetime' => $event->startTime->format('d.m. h:i'),
    );

    // Generate the shortcode
    $shortcode = CONFIG['wordpress']['livestream_button_shortcode'];
    foreach($keysToReplace as $key => $value) {
      $shortcode = str_replace('%' . $key . '%', $value, $shortcode);
    }

    return $shortcode;
  }
}
