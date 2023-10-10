<?php

namespace Chophper;

if (function_exists('grapheme_strlen')) {
    function ht_strlen($string)
    {
        return grapheme_strlen($string);
    }
    function ht_substr($string, $from, $to = 2147483647)
    {
        return grapheme_substr($string, $from, $to);
    }
} elseif (function_exists('mb_strlen')) {
    function ht_strlen($string)
    {
        return mb_strlen($string);
    }
    function ht_substr($string, $from, $to = 2147483647)
    {
        return mb_substr($string, $from, $to);
    }
} elseif (function_exists('iconv_strlen')) {
    function ht_strlen($string)
    {
        return iconv_strlen($string);
    }
    function ht_substr($string, $from, $to = 2147483647)
    {
        return iconv_substr($string, $from, $to);
    }
} else {
    function ht_strlen($string)
    {
        return strlen($string);
    }
    function ht_substr($string, $from, $to = 2147483647)
    {
        return substr($string, $from, $to);
    }
}

if (function_exists('mb_strtolower')) {
    function ht_strtolower($string)
    {
        return mb_strtolower($string);
    }
    function ht_strtoupper($string)
    {
        return mb_strtoupper($string);
    }
} else {
    function ht_strtolower($string)
    {
        return strtolower($string);
    }
    function ht_strtoupper($string)
    {
        return strtoupper($string);
    }
}
