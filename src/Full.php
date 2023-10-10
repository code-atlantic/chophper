<?php

/**
 * Truncation functions.
 *
 * @category Truncation
 * @package  Chophper
 * @author   Daniel Iser <daniel@code-atlantic.com>
 */

namespace Chophper;

require_once __DIR__ . '/functions.php';

use DOMDocument;
use Masterminds\HTML5;
use Chophper\Exceptions\InvalidHtmlException;

use function Chophper\ht_substr;
use function Chophper\ht_strlen;
use function Chophper\ht_strtolower;
use function Chophper\ht_strtoupper;


/**
 * Truncate HTML using full parser.
 */
class Full
{
    public static $default_options = [
        'ellipsis'            => '…',
        'length_in_chars'     => false,
        'length_in_sentences' => false,
        'length_in_blocks'    => false,
    ];

    // These tags are allowed to have an ellipsis inside.
    public static $ellipsable_tags = [
        'p',
        'ol',
        'ul',
        'li',
        'div',
        'header',
        'article',
        'nav',
        'section',
        'footer',
        'aside',
        'dd',
        'dt',
        'dl',
    ];

    /**
     * These tags are self-closing.
     *
     * @var array
     */
    public static $self_closing_tags = [
        'br',
        'hr',
        'img',
    ];

    /**
     * Truncate given HTML string to specified length.
     * If length_in_chars is false it's trimmed by number
     * of words, otherwise by number of characters.
     *
     * @param string       $html   string   HTML string to truncate.
     * @param integer      $length Length to truncate to.
     * @param string|array $opts   Options.
     *
     * @return string
     *
     * @throws InvalidHtmlException If the HTML is invalid.
     */
    public static function truncate($html, $length, $opts = [])
    {
        if (is_string($opts)) {
            $opts = [ 'ellipsis' => $opts ];
        }
        $opts = array_merge(static::$default_options, $opts);
        // wrap the html in case it consists of adjacent nodes like <p>foo</p><p>bar</p>.
        $html = '<div>' . static::utf8ForXml($html) . '</div>';

        $root_node = null;

        // Parse using HTML5Lib if it's available.
        if (class_exists('ContentControlPro\Vendor\Masterminds\HTML5')) {
            try {
                $html5     = new HTML5();
                $doc       = $html5->loadHTML($html);
                $root_node = $doc->documentElement->lastChild;
            } catch (\Exception $e) {
            }
        }

        if ($root_node === null) {
            // HTML5Lib not available so we'll have to use DOMDocument
            // We'll only be able to parse HTML5 if it's valid XML
            $doc                     = new DOMDocument();
            $doc->formatOutput       = false;
            $doc->preserveWhitespace = true;
            // loadHTML will fail with HTML5 tags (article, nav, etc)
            // so we need to suppress errors and if it fails to parse we
            // retry with the XML parser instead
            $prev_use_errors = libxml_use_internal_errors(true);
            if ($doc->loadHTML($html)) {
                $root_node = $doc->documentElement->lastChild->lastChild;
            } elseif ($doc->loadXML($html)) {
                $root_node = $doc->documentElement;
            } else {
                libxml_use_internal_errors($prev_use_errors);
                throw new InvalidHtmlException();
            }
            libxml_use_internal_errors($prev_use_errors);
        }

        if ($opts['length_in_blocks']) {
            list($text, $_, $opts) = static::truncateByBlocks($doc, $root_node, $length, $opts);
        } else {
            list($text, $_, $opts) = static::truncateNode($doc, $root_node, $length, $opts);
        }

        $text = ht_substr(ht_substr($text, 0, -6), 5);
        return $text;
    }

    /**
     * Truncate given HTML string to specified number of words.
     *
     * @param object       $doc    HTML string to truncate.
     * @param object       $node   Node to truncate.
     * @param integer      $length Length to truncate to.
     * @param string|array $opts   Options.
     *
     * @return string
     */
    protected static function truncateNode($doc, $node, $length, $opts)
    {
        if ($length === 0 && ! static::ellipsable($node)) {
            return [ '', 1, $opts ];
        }
        list($inner, $remaining, $opts) = static::innerTruncate($doc, $node, $length, $opts);
        if (0 === ht_strlen($inner)) {
            return [ in_array(ht_strtolower($node->nodeName), static::$self_closing_tags) ? $doc->saveXML($node) : '', $length - $remaining, $opts ];
        }
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $newNode = $doc->createDocumentFragment();
        $newNode->appendXml($inner);
        $node->appendChild($newNode);
        return [ $doc->saveXML($node), $length - $remaining, $opts ];
    }

    /**
     * Truncate the inner contents of a node.
     *
     * @param object  $doc    Document.
     * @param object  $node   Node to truncate.
     * @param integer $length Length to truncate to.
     * @param array   $opts   Options.
     *
     * @return array
     */
    protected static function innerTruncate($doc, $node, $length, $opts)
    {
        $inner     = '';
        $remaining = $length;
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                list($txt, $nb, $opts) = static::truncateNode($doc, $childNode, $remaining, $opts);
            } elseif ($childNode->nodeType === XML_TEXT_NODE) {
                list($txt, $nb, $opts) = static::truncateText($doc, $childNode, $remaining, $opts);
            } else {
                $txt = '';
                $nb  = 0;
            }
            $remaining -= $nb;
            $inner     .= $txt;
            if ($remaining < 0) {
                if (static::ellipsable($node)) {
                    $inner                 = preg_replace('/(?:[\s\pP]+|(?:&(?:[a-z]+|#[0-9]+);?))*$/u', '', $inner) . $opts['ellipsis'];
                    $opts['ellipsis']      = '';
                    $opts['was_truncated'] = true;
                }
                break;
            }
        }
        return [ $inner, $remaining, $opts ];
    }

    /**
     * Truncate by root-level block elements like p, ul, ol, etc.
     */
    protected static function truncateByBlocks($doc, $node, $length, $opts)
    {
        $block_tags = ['p', 'ul', 'ol', 'div', 'header', 'article', 'nav', 'section', 'footer', 'aside', 'dd', 'dt', 'dl'];
        $counter = 0;

        // Create a new document fragment to hold our truncated content
        $fragment = $doc->createDocumentFragment();

        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE && in_array($childNode->nodeName, $block_tags)) {
                $counter++;
                if ($counter > $length) {
                    break;  // exit loop if we've reached the desired number of blocks
                }
                // Import the child node to our main document (deep copy) and append it to the fragment
                $fragment->appendChild($doc->importNode($childNode, true));
            } elseif ($childNode->nodeType !== XML_ELEMENT_NODE) {
                // Append other types of nodes (like text nodes) to the fragment directly
                $fragment->appendChild($doc->importNode($childNode, true));
            }
        }

        // Convert fragment back to string
        $inner = $doc->saveXML($fragment);

        // Add ellipsis if content was truncated
        if ($counter > $length) {
            $inner .= $opts['ellipsis'];
        }

        return [$inner, $counter, $opts];
    }

    /**
     * Truncate a text node.
     *
     * @param object  $doc    Document.
     * @param object  $node   Node to truncate.
     * @param integer $length Length to truncate to.
     * @param array   $opts   Options.
     *
     * @return array
     */
    protected static function truncateText($doc, $node, $length, $opts)
    {
        $xhtml = $node->ownerDocument->saveXML($node);
        preg_match_all('/\s*\S+/', $xhtml, $words);
        $words = $words[0];

        if ($opts['length_in_sentences']) {
            // Split the text into sentences
            preg_match_all('/(.*?[.!?]+)(?:\s|$)/us', $xhtml, $sentences);
            $sentences = $sentences[0];
            $count     = count($sentences);
            if ($count <= $length && $length > 0) {
                return [ $xhtml, $count, $opts ];
            }
            return [ implode('', array_slice($sentences, 0, $length)), $count, $opts ];
        } elseif ($opts['length_in_chars']) {
            $count = ht_strlen($xhtml);
            if ($count <= $length && $length > 0) {
                return [ $xhtml, $count, $opts ];
            }
            if (count($words) > 1) {
                $content = '';

                foreach ($words as $word) {
                    if (ht_strlen($content) + ht_strlen($word) > $length) {
                        break;
                    }

                    $content .= $word;
                }

                return [ $content, $count, $opts ];
            }
            return [ ht_substr($node->textContent, 0, $length), $count, $opts ];
        } else {
            $count = count($words);
            if ($count <= $length && $length > 0) {
                return [ $xhtml, $count, $opts ];
            }
            return [ implode('', array_slice($words, 0, $length)), $count, $opts ];
        }
    }

    /**
     * Check if a node is ellipsable.
     *
     * @param object $node Node to check.
     *
     * @return boolean
     */
    protected static function ellipsable($node)
    {
        return ( $node instanceof DOMDocument )
            || in_array(ht_strtolower($node->nodeName), static::$ellipsable_tags);
    }

    /**
     * Convert a string to UTF-8 for XML.
     *
     * @param string $string String to convert.
     *
     * @return string
     */
    protected static function utf8ForXml($string)
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }
}
