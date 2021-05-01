<?php
echo '<pre>';

include 'Exceptions.php';
spl_autoload_register(function ($class_name) {
    include $class_name . '.class.php';
});

define('CONFIG_FILE', __DIR__ . '/config.php');
require(CONFIG_FILE);

// Set php timezone
date_default_timezone_set(CONFIG['timezone']);

const FORCE_RENEW = false;

// Setup ChruchTools API
$churchtools = new ChurchTools(CONFIG['churchtools']['url'], CONFIG['churchtools']['userId'], CONFIG['churchtools']['token']);
// Setup YouTube API
$youtube = new YouTube();
// Setup WordPress API
if (CONFIG['wordpress']['enabled']) {
  $wordpress = new WordPress();
}

// Everything is ready
if ($youtube->isValid()) {
  // Get all events that are 6 days into the future
  $datetime = new DateTime(sprintf('+%d day', CONFIG['events']['days_to_load_in_advance']));
  $eventList = $churchtools->getUpcomingEvents($datetime);
  // Get all scheduled boradcasts
  $broadcastList = $youtube->getAllScheduledBoradcasts();

  // Go through all events
  foreach ($eventList as $event) {
    // Try to find an existing broadcast for this event
    foreach ($broadcastList as $broadcast) {
      if ($event->isEventBroadcast($broadcast)) {
        // TODO: Update YouTube broadcast if ChurchTools information has changed
        $event->attachYouTubeBroadcast($broadcast, $youtube);
        break;
      }
    }

    if (!$event->livestreamEnabled) {
      // Delete broadcast if livestream has been disabled
      $event->deleteBroadcast();
      continue;
    }
    else {
      // Create a new broadcast if this event doesn't have one already
      $event->createYouTubeBroadcast($youtube);
    }
  }

  // Check if any event has changed
  $eventListHash = md5(json_encode($eventList));
  $storedEventListHash = file_exists('storedEventListHash') ? file_get_contents('storedEventListHash') : '';
  // Check if any event has changed
  if (FORCE_RENEW || $eventListHash !== $storedEventListHash) {
    // Update all event broadcasts
    foreach ($eventList as $event) {
      $event->updateYouTubeBroadcast();
    }

    if (CONFIG['wordpress']['enabled']) {
      $wordpress->updateEventEntries($eventList);
    }
    // Store new information for the next request
    file_put_contents('storedEventListHash', $eventListHash);
    echo 'UPDATED';
  }
  else {
    echo 'skipped';
  }
}

echo '</pre>';
