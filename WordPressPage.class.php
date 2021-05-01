<?php

class WordPressPage {
  /**
   * The pages id
   */
  protected int $id;
  /**
   * The pages title
   */
  protected string $title;
  /**
   * The pages unrendered content, with the templates filtered out
   */
  protected array $content;
  /**
   * Wether the page is valid
   */
  private bool $valid = true;
  /**
   * Content template opening tag
   */
  private static string $openTag = '<!-- ' . CONFIG['wordpress']['content_tag'] . ' -->';
  /**
   * Content template closing tag
   */
  private static string $closeTag = '<!-- /' . CONFIG['wordpress']['content_tag'] . ' -->';

  /**
   * Load page data
   */
  function __construct($data) {
    foreach ($data as $key => $value) {
      switch ($key) {
        case 'id':
          $this->id = $value;
          break;
        case 'title':
          $this->title = $value['rendered'];
          break;
        case 'content':
          $this->parseContent($value['raw']);
      }
    }
  }

  /**
   * Get the content blocks from the content
   * @param string $content The pages content to parse
   * @return array The parsed content split into blocks
   */
  private function parseContent(string $content) {
    $tagOpen = false;
    $this->content = array();

    // Split the content at the content tags
    While(true) {
      $tagToSplitWith = $tagOpen ? WordPressPage::$closeTag : WordPressPage::$openTag;

      // Split at tag
      $split = explode($tagToSplitWith, $content, 2);

      $this->content[] = array(
        'content' => $split[0],
        'type' => $tagOpen ? 'template' : 'content',
      );
      // No tag to split by was found
      if (count($split) < 2) {
        break;
      }
      // Prepare for next run
      $content = $split[1];
      $tagOpen = !$tagOpen;
    }

    // Check if all open tags closed
    if ($tagOpen) {
      $this->valid = false;
    }
  }

  /**
   * Apply the given template to this pages content
   * @param string The template to apply
   */
  public function applyTemplateToContent(string $template) {
    if (!$this->isValid()) return;
    foreach ($this->content as &$contentBlock) {
      if ('template' === $contentBlock['type']) {
        $contentBlock['content'] = $template;
      }
    }
  }

  /**
   * Get the pages content
   * @return string The pages content
   */
  public function getContent() {
    if (!$this->isValid()) return '';
    // Build content
    $content = '';
    foreach ($this->content as $contentBlock) {
      if ('template' === $contentBlock['type']) $content .= WordPressPage::$openTag;
      $content .= $contentBlock['content'];
      if ('template' === $contentBlock['type']) $content .= WordPressPage::$closeTag;
    }

    return $content;
  }

  /**
   * Get the pages id
   * @return int $id
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Check wether the page is valid
   * @return bool Valid or not
   */
  public function isValid() {
    return $this->valid;
  }
}
