<?php
/**
 * Project text-helper
 * Created by PhpStorm
 * User: 713uk13m <dev@nguyenanhung.com>
 * Copyright: 713uk13m <dev@nguyenanhung.com>
 * Date: 09/22/2021
 * Time: 19:31
 */

namespace nguyenanhung\Libraries\Text;

if (!class_exists('nguyenanhung\Libraries\Text\TextProcessor')) {
    /**
     * Class TextProcessor
     *
     * @package   nguyenanhung\Libraries\Text
     * @author    713uk13m <dev@nguyenanhung.com>
     * @copyright 713uk13m <dev@nguyenanhung.com>
     */
    class TextProcessor
    {
        /**
         * Word Limiter
         *
         * Limits a string to X number of words.
         *
         * @param string
         * @param int
         * @param string    the end character. Usually an ellipsis
         *
         * @return    string
         * @author: 713uk13m <dev@nguyenanhung.com>
         * @time  : 9/29/18 11:25
         *
         */
        public static function wordLimiter($str = '', $limit = 100, $end_char = '&#8230;'): string
        {
            if (trim($str) === '') {
                return $str;
            }

            preg_match('/^\s*+(?:\S++\s*+){1,' . (int) $limit . '}/', $str, $matches);

            if (strlen($str) === strlen($matches[0])) {
                $end_char = '';
            }

            return rtrim($matches[0]) . $end_char;
        }

        /**
         * Word Wrap
         *
         * Wraps text at the specified character. Maintains the integrity of words.
         * Anything placed between {unwrap}{/unwrap} will not be word wrapped, nor
         * will URLs.
         *
         * @param string $str     the text string
         * @param int    $charlim = 76    the number of characters to wrap at
         *
         * @return    string
         */
        public static function wordWrap(string $str = '', int $charlim = 76): string
        {
            // Set the character limit
            is_numeric($charlim) or $charlim = 76;

            // Reduce multiple spaces
            $str = preg_replace('| +|', ' ', $str);

            // Standardize newlines
            if (strpos($str, "\r") !== false) {
                $str = str_replace(["\r\n", "\r"], "\n", $str);
            }

            // If the current word is surrounded by {unwrap} tags we'll
            // strip the entire chunk and replace it with a marker.
            $unwrap        = [];
            $patternUnWrap = '|\{unwrap\}(.+?)\{/unwrap\}|s';
            if (preg_match_all($patternUnWrap, $str, $matches)) {
                for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                    $unwrap[] = $matches[1][$i];
                    $str      = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
                }
            }

            // Use PHP's native function to do the initial wordwrap.
            // We set the cut flag to FALSE so that any individual words that are
            // too long get left alone. In the next step we'll deal with them.
            $str = wordwrap($str, $charlim, "\n", false);

            // Split the string into individual lines of text and cycle through them
            $output = '';
            foreach (explode("\n", $str) as $line) {
                // Is the line within the allowed character count?
                // If so we'll join it to the output and continue
                if (mb_strlen($line) <= $charlim) {
                    $output .= $line . "\n";
                    continue;
                }

                $temp = '';
                while (mb_strlen($line) > $charlim) {
                    // If the over-length word is a URL we won't wrap it
                    $charlimPatter = '!\[url.+\]|://|www\.!';
                    if (preg_match($charlimPatter, $line)) {
                        break;
                    }

                    // Trim the word down
                    $temp .= mb_substr($line, 0, $charlim - 1);
                    $line = mb_substr($line, $charlim - 1);
                }

                // If $temp contains data it means we had to split up an over-length
                // word into smaller chunks so we'll add it back to our current line
                if ($temp !== '') {
                    $output .= $temp . "\n" . $line . "\n";
                } else {
                    $output .= $line . "\n";
                }
            }

            // Put our markers back
            if (count($unwrap) > 0) {
                foreach ($unwrap as $key => $val) {
                    $output = str_replace('{{unwrapped' . $key . '}}', $val, $output);
                }
            }

            return $output;
        }

        /**
         * Word Censoring Function
         *
         * Supply a string and an array of disallowed words and any
         * matched words will be converted to #### or to the replacement
         * word you've submitted.
         *
         * @param string       $str         the text string
         * @param string|array $censored    the array of censored words
         * @param string       $replacement the optional replacement value
         *
         * @return    string
         */
        public static function wordCensor(string $str = '', $censored = '', string $replacement = ''): string
        {
            if (!is_array($censored)) {
                return $str;
            }

            $str = ' ' . $str . ' ';

            // \w, \b and a few others do not match on a unicode character
            // set for performance reasons. As a result words like über
            // will not match on a word boundary. Instead, we'll assume that
            // a bad word will be bookeneded by any of these characters.
            $delim = '[-_\'\"`(){}<>\[\]|!?@#%&,.:;^~*+=\/ 0-9\n\r\t]';

            foreach ($censored as $badword) {
                $badword = str_replace('\*', '\w*?', preg_quote($badword, '/'));
                if ($replacement !== '') {
                    $str = preg_replace(
                        "/({$delim})(" . $badword . ")({$delim})/i",
                        "\\1{$replacement}\\3",
                        $str
                    );
                } elseif (preg_match_all("/{$delim}(" . $badword . "){$delim}/i", $str, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
                    $matches = $matches[1];
                    for ($i = count($matches) - 1; $i >= 0; $i--) {
                        $length = strlen($matches[$i][0]);
                        $str    = substr_replace(
                            $str,
                            str_repeat('#', $length),
                            $matches[$i][1],
                            $length
                        );
                    }
                }
            }

            return trim($str);
        }

        /**
         * Character Limiter
         *
         * Limits the string based on the character count.  Preserves complete words
         * so the character count may not be exactly as specified.
         *
         * @param string
         * @param int
         * @param string    the end character. Usually an ellipsis
         *
         * @return    string
         */
        public static function characterLimiter($str = '', $n = 500, $end_char = '&#8230;')
        {
            if (mb_strlen($str) < $n) {
                return $str;
            }

            // a bit complicated, but faster than preg_replace with \s+
            $str = preg_replace('/ {2,}/', ' ', str_replace(["\r", "\n", "\t", "\v", "\f"], ' ', $str));

            if (mb_strlen($str) <= $n) {
                return $str;
            }

            $out = '';
            foreach (explode(' ', trim($str)) as $val) {
                $out .= $val . ' ';

                if (mb_strlen($out) >= $n) {
                    $out = trim($out);

                    return (mb_strlen($out) === mb_strlen($str)) ? $out : $out . $end_char;
                }
            }

            return null;
        }

        /**
         * High ASCII to Entities
         *
         * Converts high ASCII text and MS Word special characters to character entities
         *
         * @param string $str
         *
         * @return    string
         */
        public static function asciiToEntities(string $str = ''): string
        {
            $out    = '';
            $length = defined('MB_OVERLOAD_STRING')
                ? mb_strlen($str, '8bit') - 1
                : strlen($str) - 1;
            for ($i = 0, $count = 1, $temp = []; $i <= $length; $i++) {
                $ordinal = ord($str[$i]);

                if ($ordinal < 128) {
                    /*
                        If the $temp array has a value but we have moved on, then it seems only
                        fair that we output that entity and restart $temp before continuing. -Paul
                    */
                    if (count($temp) === 1) {
                        $out   .= '&#' . array_shift($temp) . ';';
                        $count = 1;
                    }

                    $out .= $str[$i];
                } else {
                    if (count($temp) === 0) {
                        $count = ($ordinal < 224) ? 2 : 3;
                    }

                    $temp[] = $ordinal;

                    if (count($temp) === $count) {
                        $number = ($count === 3)
                            ? (($temp[0] % 16) * 4096) + (($temp[1] % 64) * 64) + ($temp[2] % 64)
                            : (($temp[0] % 32) * 64) + ($temp[1] % 64);

                        $out   .= '&#' . $number . ';';
                        $count = 1;
                        $temp  = [];
                    } // If this is the last iteration, just output whatever we have
                    elseif ($i === $length) {
                        $out .= '&#' . implode(';', $temp) . ';';
                    }
                }
            }

            return $out;
        }

        /**
         * Entities to ASCII
         *
         * Converts character entities back to ASCII
         *
         * @param string
         * @param bool
         *
         * @return    string
         */
        public static function entitiesToAscii($str = '', $all = true): string
        {
            $pattern = '/\&#(\d+)\;/';
            if (preg_match_all($pattern, $str, $matches)) {
                for ($i = 0, $s = count($matches[0]); $i < $s; $i++) {
                    $digits = $matches[1][$i];
                    $out    = '';

                    if ($digits < 128) {
                        $out .= chr($digits);

                    } elseif ($digits < 2048) {
                        $out .= chr(192 + (($digits - ($digits % 64)) / 64)) . chr(128 + ($digits % 64));
                    } else {
                        $out .= chr(224 + (($digits - ($digits % 4096)) / 4096))
                                . chr(128 + ((($digits % 4096) - ($digits % 64)) / 64))
                                . chr(128 + ($digits % 64));
                    }

                    $str = str_replace($matches[0][$i], $out, $str);
                }
            }

            if ($all) {
                return str_replace(
                    ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;', '&#45;'],
                    ['&', '<', '>', '"', "'", '-'],
                    $str
                );
            }

            return $str;
        }

        /**
         * Code Highlighter
         *
         * Colorizes code strings
         *
         * @param string    the text string
         *
         * @return    string
         */
        public static function highlightCode($str = ''): string
        {
            /* The highlight string function encodes and highlights
             * brackets so we need them to start raw.
             *
             * Also replace any existing PHP tags to temporary markers
             * so they don't accidentally break the string out of PHP,
             * and thus, thwart the highlighting.
             */
            $str = str_replace(
                ['&lt;', '&gt;', '<?', '?>', '<%', '%>', '\\', '</script>'],
                ['<', '>', 'phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'],
                $str
            );

            // The highlight_string function requires that the text be surrounded
            // by PHP tags, which we will remove later
            $str = highlight_string('<?php ' . $str . ' ?>', true);

            // Remove our artificially added PHP, and the syntax highlighting that came with it
            $str = preg_replace(
                [
                    '/<span style="color: #([A-Z0-9]+)">&lt;\?php(&nbsp;| )/i',
                    '/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>\n<\/span>\n<\/code>/is',
                    '/<span style="color: #[A-Z0-9]+"><\/span>/i'
                ],
                [
                    '<span style="color: #$1">',
                    "$1</span>\n</span>\n</code>",
                    ''
                ],
                $str
            );

            // Replace our markers back to PHP tags.
            return str_replace(
                ['phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'],
                ['&lt;?', '?&gt;', '&lt;%', '%&gt;', '\\', '&lt;/script&gt;'],
                $str
            );
        }

        /**
         * Phrase Highlighter
         *
         * Highlights a phrase within a text string
         *
         * @param string $str       the text string
         * @param string $phrase    the phrase you'd like to highlight
         * @param string $tag_open  the openging tag to precede the phrase with
         * @param string $tag_close the closing tag to end the phrase with
         *
         * @return    string
         */
        public static function highlightPhrase(string $str = '', string $phrase = '', string $tag_open = '<mark>', string $tag_close = '</mark>'): string
        {
            define('UTF8_ENABLED', true);

            return ($str !== '' && $phrase !== '')
                ? preg_replace('/(' . preg_quote($phrase, '/') . ')/i' . (UTF8_ENABLED ? 'u' : ''), $tag_open . '\\1' . $tag_close, $str)
                : $str;
        }

        /**
         * Ellipsize String - This function will strip tags from a string, split it at its max_length and ellipsize
         *
         * @param string    string to ellipsize
         * @param int    max length of string
         * @param mixed    int (1|0) or float, .5, .2, etc for position to split
         * @param string    ellipsis ; Default '...'
         *
         * @return string
         * @author   : 713uk13m <dev@nguyenanhung.com>
         * @copyright: 713uk13m <dev@nguyenanhung.com>
         * @time     : 09/22/2021 35:20
         */
        public static function ellipsize($str, $max_length, $position = 1, $ellipsis = '&hellip;'): string
        {
            // Strip tags
            $str = trim(strip_tags($str));

            // Is the string long enough to ellipsize?
            if (mb_strlen($str) <= $max_length) {
                return $str;
            }

            $beg      = mb_substr($str, 0, floor($max_length * $position));
            $position = ($position > 1) ? 1 : $position;

            if ($position === 1) {
                $end = mb_substr($str, 0, -($max_length - mb_strlen($beg)));
            } else {
                $end = mb_substr($str, -($max_length - mb_strlen($beg)));
            }

            return $beg . $ellipsis . $end;
        }

        /**
         * Convert Accented Foreign Characters to ASCII
         *
         * @param string $str Input string
         *
         * @return    string
         */
        public static function convertAccentedCharacters(string $str): string
        {
            static $array_from, $array_to;

            if (!is_array($array_from)) {
                $foreign_characters = [];
                if (file_exists(__DIR__ . '/../data/foreign_chars.php')) {
                    $foreign_characters = include __DIR__ . '/../data/foreign_chars.php';
                }
                if (empty($foreign_characters) || !is_array($foreign_characters)) {
                    $array_from = [];
                    $array_to   = [];

                    return $str;
                }

                $array_from = array_keys($foreign_characters);
                $array_to   = array_values($foreign_characters);
            }

            return preg_replace($array_from, $array_to, $str);
        }
    }
}
