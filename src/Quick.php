<?php

/**
 * Truncation functions.
 *
 * @package Chophper
 *
 * @license proprietary?
 * Modified by code-atlantic on 10-October-2023 using Strauss.
 * @see     https://github.com/BrianHenryIE/strauss
 */

namespace Chophper;

use ContentControlPro\Vendor\Masterminds\HTML5;

/**
 * Class to handle HTML truncation.
 */
class Quick
{
    /**
     * HTML parser.
     *
     * @var HTML5
     */
    public $parser;

    public function __construct()
    {
        $this->parser = new HTML5();
    }

    public function safe_strip_tags($text, $remove_breaks = false)
    {
        if (is_null($text)) {
            return '';
        }

        if (! is_scalar($text)) {
            return '';
        }

        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);

        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }

        return trim($text);
    }

    /**
     * Truncate a string of HTML to a certain number of words while preserving HTML tags.
     *
     * This function breaks the HTML into words and tags, truncates the words, and then
     * reassembles the HTML.
     *
     * @param string $html  HTML string to truncate.
     * @param int    $words Number of words to truncate to.
     *
     * @return string
     */
    public function truncate_words($html, $words)
    {
        // First lets check if we need to truncate at all.
        $stripped_html_word_count = str_word_count($this->safe_strip_tags($html));

        if ($stripped_html_word_count <= $words) {
            return $html;
        }

        $parsed = $this->parser->loadHTML($html);

        $truncated_html = '';
        $word_count     = 0;

        foreach ($parsed->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $words_in_node = preg_split('/\s+/', $node->textContent, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($words_in_node as $word) {
                    if ($word_count < $words) {
                        $truncated_html .= $word . ' ';
                    }

                    ++$word_count;
                }
            } else {
                $truncated_html .= $this->parser->saveHTML($node);
            }

            if ($word_count >= $words) {
                break;
            }
        }

        $truncated_html = trim($truncated_html);

        if ('>' === substr($truncated_html, -1)) {
            $truncated_html = substr($truncated_html, 0, strrpos($truncated_html, '<'));
        }

        return $truncated_html;
    }
}
