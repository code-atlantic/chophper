<?php
/**
 * Truncation functions.
 *
 * @category Truncation
 * @package  Chophper
 * @author   Daniel Iser <daniel@code-atlantic.com>
 */

namespace Chophper;

if ( function_exists( 'grapheme_strlen' ) ) {
	/**
	 * Get the length of a string
	 *
	 * @param string $str The string being measured for length.
	 *
	 * @return int
	 */
	function ht_strlen( $str ) {
		return grapheme_strlen( $str );
	}

	/**
	 * Get part of string
	 *
	 * @param string $str  The input string. Must be one character or longer.
	 * @param int    $from Start position in $str. If $from is non-negative, the returned string will start at the $from'th position in $str, counting from zero. If $from is negative, the returned string will start at the $from'th character from the end of string.
	 * @param int    $to   If $to is given, the string returned will contain at most $to characters beginning from $from (depending on the length of $str).
	 *
	 * @return string
	 */
	function ht_substr( $str, $from, $to = 2147483647 ) {
		return grapheme_substr( $str, $from, $to );
	}
} elseif ( function_exists( 'mb_strlen' ) ) {
	/**
	 * Get the length of a string
	 *
	 * @param string $str The string being measured for length.
	 *
	 * @return int
	 */
	function ht_strlen( $str ) {
		return mb_strlen( $str );
	}

	/**
	 * Get part of string
	 *
	 * @param string $str  The input string. Must be one character or longer.
	 * @param int    $from Start position in $str. If $from is non-negative, the returned string will start at the $from'th position in $str, counting from zero. If $from is negative, the returned string will start at the $from'th character from the end of string.
	 * @param int    $to   If $to is given, the string returned will contain at most $to characters beginning from $from (depending on the length of $str).
	 *
	 * @return string
	 */
	function ht_substr( $str, $from, $to = 2147483647 ) {
		return mb_substr( $str, $from, $to );
	}
} elseif ( function_exists( 'iconv_strlen' ) ) {
	/**
	 * Get the length of a string
	 *
	 * @param string $str The string being measured for length.
	 *
	 * @return int
	 */
	function ht_strlen( $str ) {
		return iconv_strlen( $str );
	}

	/**
	 * Get part of string
	 *
	 * @param string $str  The input string. Must be one character or longer.
	 * @param int    $from Start position in $str. If $from is non-negative, the returned string will start at the $from'th position in $str, counting from zero. If $from is negative, the returned string will start at the $from'th character from the end of string.
	 * @param int    $to   If $to is given, the string returned will contain at most $to characters beginning from $from (depending on the length of $str).
	 *
	 * @return string
	 */
	function ht_substr( $str, $from, $to = 2147483647 ) {
		return iconv_substr( $str, $from, $to );
	}
} else {
	/**
	 * Get the length of a string
	 *
	 * @param string $str The string being measured for length.
	 *
	 * @return int
	 */
	function ht_strlen( $str ) {
		return strlen( $str );
	}

	/**
	 * Get part of string
	 *
	 * @param string $str  The input string. Must be one character or longer.
	 * @param int    $from Start position in $str. If $from is non-negative, the returned string will start at the $from'th position in $str, counting from zero. If $from is negative, the returned string will start at the $from'th character from the end of string.
	 * @param int    $to   If $to is given, the string returned will contain at most $to characters beginning from $from (depending on the length of $str).
	 *
	 * @return string
	 */
	function ht_substr( $str, $from, $to = 2147483647 ) {
		return substr( $str, $from, $to );
	}
}

if ( function_exists( 'mb_strtolower' ) ) {
	/**
	 * Make a string lowercase
	 *
	 * @param string $str The string being lowercased.
	 *
	 * @return string
	 */
	function ht_strtolower( $str ) {
		return mb_strtolower( $str );
	}

	/**
	 * Make a string uppercase
	 *
	 * @param string $str The string being uppercased.
	 *
	 * @return string
	 */
	function ht_strtoupper( $str ) {
		return mb_strtoupper( $str );
	}
} else {
	/**
	 * Make a string lowercase
	 *
	 * @param string $str The string being lowercased.
	 *
	 * @return string
	 */
	function ht_strtolower( $str ) {
		return strtolower( $str );
	}

	/**
	 * Make a string uppercase
	 *
	 * @param string $str The string being uppercased.
	 *
	 * @return string
	 */
	function ht_strtoupper( $str ) {
		return strtoupper( $str );
	}
}
