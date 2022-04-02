<?php
define('CONFIG', array(
  // What timezone to use (both for ChurchTools and YouTube)
  'timezone' => 'Europe/Berlin',
  // Url to this program; with trailing slash
  'url' => 'https://EXAMPLE.COM/',
  /**
   * Setting up ChurchTools events
   */
  'events' => array(
    /**
     * ChurchTools fact, that determines if a livestream should be created for this event
     */
    'livestream' => array(
      // Name of the ChurchTools fact
      'title' => 'Livestream',
      // Value that fact should have
      'value' => 'Ja',
      // Default value
      'default' => false,
      // Value that fact should have if any attached livestream should be ignored
      'ignore_value' => 'Ingorieren',
    ),
    /**
     * ChurchTools fact, that determines if the livestream of an event should be visible on the homepage
     */
    'livestream_on_homepage' => array(
      // Name of the ChurchTools fact
      'title' => 'Livestream auf der Homepage',
      // Value that fact should have
      'value' => 'Ja',
      // Default value
      'default' => true,
    ),
    /**
     * ChurchTools fact, that determines the default YouTube visibility of an events livestream
     * The default value for this can be set under "youtube > visibility"
     */
    'livestream_visibility' => array(
      // Name of the ChurchTools fact
      'title' => 'Livestream Sichtbarkeit',
      // Asociate YouTube privacy states with values the fact can have
      'values' => array(
        YouTubePrivacyStatus::PUBLIC => 'Öffentlich',
        YouTubePrivacyStatus::UNLISTED => 'Nur über einen Link',
        YouTubePrivacyStatus::PRIVATE => 'Privat',
      )
    ),
    // The ChurchTools service that represents an events speaker
    'speaker' => 'Predigt',
    // How many days ahead of the events start should
    'days_to_load_in_advance' => 6,
    // The name of the image (without extension) attached to a ChurchTools event, that should be used as the YouTube livestreams thumbnail
    'thumbnail_name' => 'YouTube',
  ),
  /**
   * General ChurchTools settings
   */
  'churchtools' => array(
    // The url to your ChurchTools instance
    'url' => 'https://YOURINSTACE.church.tools/',
    // The id of the user that should performe all ChurchTools requests
    'userId' => 101,
    // The users login token (is different from his password)
    'token' => '',
  ),
  /**
   * General YouTube settings
   */
  'youtube' => array(
    /**
     * How the livestreams title should be built; the following placeholders are available:
     *
     * %title% - The ChurchTools events title
     * %subject% - The speakers subject
     * %speaker% - Name of the speaker (set under "churchtools > speaker")
     * %date% - The ChurchTools events start date
     */
    'title' => '%title% -%subject%%speaker%%date%',
      /**
       * How the livestreams description should be built; the following placeholders are available:
       *
       * %title% - The ChurchTools events title
       * %subject% - The speakers subject
       * %subject_newline% - Same as subject but with a linebreak at the end if the subject is not empty
       * %speaker% - Name of the speaker (set under "churchtools > speaker")
       * %speaker_newline% - Same as speaker but with a linebreak at the end if the speakers name is not empty
       * %date% - The ChurchTools events start date
       */
    'description' => 'Livestream aus unserer Gemeinde

%speaker_newline%%subject%',
    /**
     * The default thumbnail to use
     * Publicly accessable link to an image
     */
    'thumbnail' => '',
    // The id of YouTube stream key to use (not the key itself!)
    'streamKeyId' => '',
    // Default livestream visibility
    'visibility' => 'public',
    // YouTube access token - DON'T TOUCH
    'accessToken' => '',
    // YouTube refresh token - DON'T TOUCH
    'refreshToken' => '',
  ),
  /**
   * General WordPress settings
   */
  'wordpress' => array(
    // Enable automatic WordPress page editing
    'enabled' => true,
    // How many days ahead of the events start should this be shown (determines %pre_time% placeholder for the shortcode)
    'days_to_show_button_in_advance' => 6,
    // Allow a pre event time in the content templates to overlap with a running event. Otherwise the second events pre times always start after the first event has ended
    'allow_parallel_event_pre_times' => true,
    /**
     * Which pages to plase the generated content in
     * Use the pages id as key and the key from 'content_templates' as value:
     * 'page_id' => 'content_template_name'
     */
    'pages' => array(
      6 => 'play_button',
    ),
    /**
     * Which tag to put the content in
     * e.g. 'ct-livestreams', then the opening tag should be: '<!-- ct-livestreams --><!-- /ct-livestreams -->'
     * The content will be placed between these tags
     */
    'content_tag' => 'ct-livestreams',
    // Url to the WordPress instance
    'url' => 'https://YOUR-WORDPRESS-INSTACE.COM/',
    // The user to make all API calls as
    'user' => 'admin',
    // The users application password (from the Application Password plugin: https://wordpress.org/plugins/application-passwords/)
    'application_password' => '',
    /**
     * How the WordPress contents should be built; the following placeholders are available:
     *
     * %pre_time% - Atom timestamp of the time to start showing the shortcode (e.g.: 2010-04-05T15:52:01+02:00)
     * %end_time% - Atom timestamp of the scheduled events start (e.g.: 2010-04-11T15:52:01+02:00)
     * %end_time% - Atom timestamp of the scheduled events end (e.g.: 2010-04-11T16:22:01+02:00)
     * %video_link% - Link to the events YouTube livestream
     * %title% - The ChurchTools events title
     * %datetime% - The ChurchTools events start date and time
     */
     'content_templates' => array(
       'play_button' => '[timed-content-server show="%pre_time%" hide="%start_time%"]

<div class="wp-block-getwid-video-popup"><a class="wp-block-getwid-video-popup__link" href="%video_link%"><div class="wp-block-getwid-video-popup__wrapper"><div class="wp-block-getwid-video-popup__button is-style-default"><div class="wp-block-getwid-video-popup__icon"><i class="fas fa-play"></i></div><div class="wp-block-getwid-video-popup__button-caption"><p class="wp-block-getwid-video-popup__title">%title% - Live-Stream - %datetime%</p></div></div></div></a></div>

[/timed-content-server]


[timed-content-server show="%start_time%" hide="%end_time%"]

<div class="wp-block-getwid-video-popup"><a class="wp-block-getwid-video-popup__link" href="%video_link%"><div class="wp-block-getwid-video-popup__wrapper"><div class="wp-block-getwid-video-popup__button is-style-default has-animation-pulse"><div class="wp-block-getwid-video-popup__icon"><i class="fas fa-play"></i></div><div class="wp-block-getwid-video-popup__button-caption"><p class="wp-block-getwid-video-popup__title">Jetzt live - %title%</p></div></div></div></a></div>

[/timed-content-server]',
    ),
  ),
));
