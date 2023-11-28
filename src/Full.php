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

use function Chophper\ht_strlen;
use function Chophper\ht_substr;
use function Chophper\ht_strtolower;

/**
 * Truncate HTML using full parser.
 */
class Full {

	/**
	 * These tags can be truncated.
	 *
	 * @var array
	 */
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
	 * Parse options.
	 *
	 * @param string|array $opts Options.
	 *
	 * @return array
	 */
	public static function parse_options( $opts ) {
		$opts = array_merge( [
			'ellipsis'      => '…',
			'truncateBy'    => 'words', // words, chars, sentences, blocks.
			'preserveWords' => false,
		], $opts );

		return $opts;
	}

	/**
	 * Get the root node of an HTML string.
	 *
	 * @param string $html HTML string.
	 *
	 * @return \DOMNode|null
	 *
	 * @throws InvalidHtmlException If the HTML is invalid.
	 */
	public static function get_root_node( $html ) {
		$root_node = null;

		// Parse using HTML5Lib if it's available.
		if ( class_exists( 'Masterminds\HTML5' ) ) {
			try {
				$html5     = new HTML5();
				$doc       = $html5->loadHTML( $html );
				$root_node = $doc->documentElement->lastChild;
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'HTML5Lib failed to parse HTML: ' . $e->getMessage() );
			}
		}

		if ( null === $root_node ) {
			// HTML5Lib not available so we'll have to use DOMDocument
			// We'll only be able to parse HTML5 if it's valid XML.
			$doc                     = new DOMDocument();
			$doc->formatOutput       = false;
			$doc->preserveWhitespace = true;
			// loadHTML will fail with HTML5 tags (article, nav, etc)
			// so we need to suppress errors and if it fails to parse we
			// retry with the XML parser instead.
			$prev_use_errors = libxml_use_internal_errors( true );
			if ( $doc->loadHTML( $html ) ) {
				$root_node = $doc->documentElement->lastChild->lastChild;
			} elseif ( $doc->loadXML( $html ) ) {
				$root_node = $doc->documentElement;
			} else {
				libxml_use_internal_errors( $prev_use_errors );
				throw new InvalidHtmlException();
			}
			libxml_use_internal_errors( $prev_use_errors );
		}

		return $root_node;
	}

	/**
	 * Truncate given HTML string to specified length.
	 * If length_in_chars is false it's trimmed by number
	 * of words, otherwise by number of characters.
	 *
	 * @param string       $str   string   HTML string to truncate.
	 * @param integer      $length Length to truncate to.
	 * @param string|array $opts   Options.
	 *
	 * @return string
	 *
	 * @throws InvalidHtmlException If the HTML is invalid.
	 */
	public static function truncate( $str, $length, $opts = [] ) {
		$opts = static::parse_options( $opts );

		$node = self::get_root_node( '<div>' . static::utf8_for_xml( $str ) . '</div>' );

		if ( ! $node ) {
			return '';
		}

		switch ( $opts['truncateBy'] ) {
			default:
			case 'words':
			case 'chars':
			case 'sentences':
				// Truncate by traversing the DOM tree, counting words as we go through each nodea recursively and truncating when we reach the desired length.
				$results = static::truncate_node( $node, $length, $opts );
				break;

			case 'blocks':
				$results = static::truncateBy_blocks( $node, $length, $opts );
				break;
		}

		list( $str ) = $results;

		// Strip off the root node div added before.
		$str = ht_substr( ht_substr( $str, 0, -6 ), 5 );

		return $str;
	}

	/**
	 * Truncate given HTML string to specified number of words.
	 *
	 * Truncate by traversing the DOM tree, counting words as we go through each
	 * nodea recursively and truncating when we reach the desired length.
	 *
	 * @param \DOMNode $node   Node to truncate.
	 * @param integer  $length Length to truncate to.
	 * @param array    $opts   Options.
	 *
	 * @return array[string,integer,array} [0] Truncated inner contents. [1] Number of words remaining. [2] Options.
	 */
	protected static function truncate_node( $node, $length, $opts ) {
		$doc = $node->ownerDocument;

		// If the length is 0, return an empty string.
		if ( 0 === $length && ! static::is_ellipsable( $node ) ) {
			return [
				'',
				0, // TODO Why is this 1 and not 0?
				$opts,
			];
		}

		// Truncate the nodes inner contents recursively.
		list( $inner, $remaining, $opts ) = static::inner_truncate( $node, $length, $opts );

		// If the inner contents are empty, return an empty string.
		if ( 0 === ht_strlen( $inner ) ) {
			return [
				in_array( ht_strtolower( $node->nodeName ), static::$self_closing_tags, true )
					// Self-closing tags should be returned as-is.
					? $doc->saveXML( $node )
					// Other tags should be returned as an empty string.
					: '',
				// Return the number of words remaining.
				// $length - $remaining, // TODO Review why is this not simply $remaining?
				$remaining < 0 ? 0 : $remaining,
				$opts,
			];
		}

		// Remove all child nodes from the node.
		while ( $node->firstChild ) {
			$node->removeChild( $node->firstChild );
		}

		// Create a new document fragment to hold our truncated content.
		$new_node = $doc->createDocumentFragment();

		// Append the inner contents to the fragment.
		$new_node->appendXml( $inner );

		// Append the fragment to the node.
		$node->appendChild( $new_node );

		// Return the truncated node.
		return [
			$doc->saveXML( $node ),
			// $length - $remaining, // TODO Review why is this not simply $remaining?
			$remaining < 0 ? 0 : $remaining,
			$opts,
		];
	}

	/**
	 * Truncate the inner contents of a node.
	 *
	 * @param \DOMNode $node   Node to truncate.
	 * @param integer  $length Length to truncate to.
	 * @param array    $opts   Options.
	 *
	 * @return array[string,integer,array} [0] Truncated inner contents. [1] Number of words remaining. [2] Options.
	 */
	protected static function inner_truncate( $node, $length, $opts ) {
		$inner     = '';
		$remaining = $length;

		foreach ( $node->childNodes as $child_node ) {
			if ( XML_ELEMENT_NODE === $child_node->nodeType ) {
				// Truncate nodes recursively.
				list( $text, $remaining, $opts ) = static::truncate_node( $child_node, $remaining, $opts );
			} elseif ( XML_TEXT_NODE === $child_node->nodeType ) {
				// Process the child node, checking if it needs to be truncated, returning the truncated node and the number of words remaining.
				list( $text, $remaining, $opts ) = static::truncate_text( $child_node, $remaining, $opts );
			} else {
				// If the node is not a text or element node, set the text to an empty string and the number of words to 0.
				$text = '';
			}

			$inner .= $text;

			if ( $remaining <= 0 ) {
				if ( static::is_ellipsable( $node ) ) {
					$inner = preg_replace( '/(?:[\s\pP]+|(?:&(?:[a-z]+|#[0-9]+);?))*$/u', '', $inner ) . $opts['ellipsis'];
				}
				break;
			}
		}

		return [
			$inner,
			$remaining < 0 ? 0 : $remaining,
			$opts,
		];
	}

	/**
	 * Truncate by root-level block elements like p, ul, ol, etc.
	 *
	 * @param \DOMNode $node   Node to truncate.
	 * @param integer  $length Length to truncate to.
	 * @param array    $opts   Options.
	 *
	 * @return array[string,integer,array} [0] Truncated inner contents. [1] Number of words remaining. [2] Options.
	 */
	protected static function truncateBy_blocks( $node, $length, $opts ) {
		$doc = $node->ownerDocument;

		$block_tags = [ 'p', 'ul', 'ol', 'div', 'header', 'article', 'nav', 'section', 'footer', 'aside', 'dd', 'dt', 'dl' ];
		$remaining  = $length;

		$nodes_to_keep = [];

		foreach ( $node->childNodes as $child_node ) {
			if ( $remaining <= 0 ) {
				break;
			}

			if ( XML_ELEMENT_NODE === $child_node->nodeType && in_array( ht_strtolower( $child_node->nodeName ), $block_tags, true ) ) {
				// If the node is a block element, add it to the fragment.
				$nodes_to_keep[] = $child_node;
				--$remaining;
			} elseif ( XML_TEXT_NODE === $child_node->nodeType && 0 !== ht_strlen( $child_node->textContent ) ) {
				// If the node is a non-empty text node, add it to the fragment.
				$nodes_to_keep[] = $child_node;
			}
		}

		$new_nodes = count( $nodes_to_keep );

		// Remove all child nodes from the node.
		while ( $node->firstChild ) {
			$node->removeChild( $node->firstChild );
		}

		// Loop over the nodes to process.
		foreach ( $nodes_to_keep as $i => $child_node ) {
			if ( 0 === $remaining && $i === $new_nodes - 1 ) {
				$child_node->appendChild( $doc->createTextNode( $opts['ellipsis'] ) );
			}

			// If the node is a block element, add it to the fragment.
			if ( XML_ELEMENT_NODE === $child_node->nodeType && in_array( ht_strtolower( $child_node->nodeName ), $block_tags, true ) ) {
				$node->appendChild( $child_node );
			} elseif ( XML_TEXT_NODE === $child_node->nodeType ) {
				// If the node is a non-empty text node, add it to the fragment.
				$node->appendChild( $child_node );
			}
		}

		// Return the truncated node.
		return [
			$doc->saveXML( $node ),
			$remaining < 0 ? 0 : $remaining,
			$opts,
		];
	}

	/**
	 * Truncate a text node.
	 *
	 * @param \DOMText $node    Text node to truncate.
	 * @param integer  $length  Length to truncate to.
	 * @param array    $opts    Options.
	 *
	 * @return array[string,integer,array} [0] Truncated inner contents. [1] Number of words remaining. [2] Options.
	 */
	protected static function truncate_text( $node, $length, $opts ) {
		$doc   = $node->ownerDocument;
		$xhtml = $doc->saveXML( $node );

		switch ( $opts['truncateBy'] ) {
			default:
			case 'words':
				// Split the text into words.
				preg_match_all( '/\s*\S+/', $xhtml, $words );

				// Get the words.
				$words = $words[0];

				// Count the words and get the number of words remaining after truncation.
				$word_count = count( $words );
				$remaining  = $length - $word_count > 0 ? $length - $word_count : 0;

				// If the number of words is less than or equal to the length, return the text in full.
				if ( $length > $word_count ) {
					return [
						// Return the full text.
						$xhtml,
						// Return the number of words remaining.
						$remaining,
						$opts,
					];
				}

				return [
					// Slice the words array to the desired length and implode it back into a string.
					implode( '', array_slice( $words, 0, $length ) ),
					// Return the number of words remaining.
					$remaining,
					$opts,
				];

			case 'chars':
				// Split the text into words.
				preg_match_all( '/\s*\S+/', $xhtml, $words );

				// Get the words.
				$words = $words[0];

				// Count the words and get the number of words remaining after truncation.
				$char_count = ht_strlen( $xhtml );

				// If the number of chars is less than or equal to the length, return the text in full.
				if ( $length > $char_count ) {
					return [
						// Return the full text.
						$xhtml,
						// Return the number of chars remaining.
						$length - $char_count,
						$opts,
					];
				}

				if ( count( $words ) > 1 ) {
					$content = '';

					$remaining = $length;

					// Loop through the words and add them to the content until we reach the desired length.
					foreach ( $words as $word ) {
						if ( $remaining <= 0 ) {
							break;
						}

						// If the length of the content plus the length of the word is greater than the desired length, break the loop.
						if ( ht_strlen( $content ) + ht_strlen( $word ) > $length ) {
								// If option for keeping words whole is set, break on the last word.
							if ( $opts['preserveWords'] ) {
								break;
							} else {
								// Trim the word to the remaining length.
								$word = ht_substr( $word, 0, $remaining );
							}
						}

						// Add the word to the content.
						$content   .= $word;
						$remaining -= ht_strlen( $word );
					}

					return [
						// Return the truncated content.
						$content,
						// Return the number of words remaining.
						$remaining < 0 ? 0 : $remaining,
						$opts,
					];
				} else {
					// If there is only one word, truncate it to the desired length.
					return [
						// Truncate the text to the desired length.
						ht_substr( $node->textContent, 0, $length ),
						// Return the number of words remaining.
						$length - $char_count,
						$opts,
					];
				}
				break;

			case 'sentences':
				// Split the text into sentences.
				preg_match_all( '/(.*?[.!?]+)(?:\s|$)/us', $xhtml, $sentences );

				// Get the sentences.
				$sentences = $sentences[0];

				// Count the sentences.
				$sentence_count = count( $sentences );
				$remaining      = $length - $sentence_count;

				// If the number of sentences is less than or equal to the length, return the text in full.
				if ( $length >= $sentence_count ) {
					$remaining = $length - $sentence_count;

					return [
						// Return the full text.
						$xhtml,
						// Return the number of sentences remaining.
						$remaining < 0 ? 0 : $remaining,
						$opts,
					];
				}

				return [
					// Implode the sentences array back into a string.
					implode( '', array_slice( $sentences, 0, $length ) ),
					// Return the number of sentences remaining.
					0,
					$opts,
				];
		}
	}

	/**
	 * Check if a node is ellipsable.
	 *
	 * @param \DOMNode|\DOMDocument $node Node to truncate.
	 *
	 * @return boolean
	 */
	protected static function is_ellipsable( $node ) {
		return ( $node instanceof DOMDocument )
			|| in_array( ht_strtolower( $node->nodeName ), static::$ellipsable_tags, true );
	}

	/**
	 * Convert a string to UTF-8 for XML.
	 *
	 * @param string $str String to convert.
	 *
	 * @return string
	 */
	protected static function utf8_for_xml( $str ) {
		return preg_replace( '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $str );
	}
}
