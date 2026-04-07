<?php

namespace KatalystSolutions\OFX;

use RuntimeException;
use SimpleXMLElement;

class OFXUtils
{
    /**
     * Normalize raw OFX content to a SimpleXMLElement.
     * - Normalizes line endings
     * - Detects and fixes encoding (avoid double-conversion when content is already UTF-8)
     * - Converts SGML-style OFX (v1) to XML when needed
     * - Parses into SimpleXML with internal libxml error handling
     *
     * @param string $ofxContent Raw OFX content
     * @return false|SimpleXMLElement SimpleXMLElement on success, false on failure
     */
    public static function normalizeOfx(string $ofxContent): false|SimpleXMLElement
    {
        // Normalize line endings to "\n" (previous code used literal '\r\n' which did nothing)
        $ofxContent = str_replace(["\r\n", "\r"], "\n", $ofxContent);

        // Locate the <OFX> root and split header/body.
        // Header is ASCII by spec, so it can be parsed without prior re-encoding.
        $sgmlStart = stripos($ofxContent, '<OFX>');

        if ($sgmlStart === false) {
            // No <OFX> tag found: invalid input
            return false;
        }

        $ofxHeaderRaw = trim(substr($ofxContent, 0, $sgmlStart));
        $ofxBodyRaw   = trim(substr($ofxContent, $sgmlStart));
        $header       = self::parseHeader($ofxHeaderRaw);

        // Determine declared encoding from header (OFX v1 and v2 conventions)
        // Typical keys: ENCODING, CHARSET
        $declared = null;

        if (!empty($header['ENCODING'])) {
            // OFX v1: ENCODING:USASCII (with CHARSET:1252 etc.)
            $enc = strtoupper((string) $header['ENCODING']);

            if ($enc === 'UTF-8' || $enc === 'UTF8') {
                $declared = 'UTF-8';
            }
        }

        if ($declared === null && !empty($header['CHARSET'])) {
            $cs = strtoupper((string) $header['CHARSET']);
            if ($cs === 'UTF-8' || $cs === 'UTF8') {
                $declared = 'UTF-8';
            } elseif ($cs === '1252') {
                $declared = 'Windows-1252';
            } elseif ($cs === 'ISO-8859-1' || $cs === 'ISO8859-1') {
                $declared = 'ISO-8859-1';
            }
        }

        // Encoding policy:
        // - If content is already valid UTF-8 with multi-byte chars, DO NOT reconvert (some banks lie in header).
        // - Else, if header declares a non-UTF8 encoding, convert once to UTF-8.
        // - Else leave as-is (ASCII often).
        $looksUtf8 = mb_check_encoding($ofxContent, 'UTF-8')
            && (bool) preg_match('/[\xC2-\xF4]/', $ofxContent);

        if (!$looksUtf8 && $declared !== null && $declared !== 'UTF-8') {
            $converted = @mb_convert_encoding($ofxContent, 'UTF-8', $declared);

            if (is_string($converted) && $converted !== '') {
                $ofxContent = $converted;

                // Update the split parts after conversion to keep indices consistent
                $sgmlStart = stripos($ofxContent, '<OFX>');

                if ($sgmlStart === false) {
                    return false;
                }

                $ofxHeaderRaw = trim(substr($ofxContent, 0, $sgmlStart));
                $ofxBodyRaw   = trim(substr($ofxContent, $sgmlStart));
            }
        }

        // If header looks like XML prolog (OFX v2), body should already be XML.
        // Otherwise it's SGML-style OFX v1 and must be converted.
        $isXmlHeader = preg_match('/^<\?xml/i', $ofxHeaderRaw) === 1;

        // SGML to XML conversion for OFX v1 if needed
        $ofxXml = $isXmlHeader ? $ofxBodyRaw : self::convertSgmlToXml($ofxBodyRaw);

        // Parse as XML with libxml internal error capture
        libxml_clear_errors();
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($ofxXml);

        if ($xml === false) {
            $errors = libxml_get_errors();
            // Surface a meaningful exception to the caller
            throw new RuntimeException('Failed to parse OFX: ' . var_export($errors, true));
        }

        return $xml;
    }

    /**
     * Parse OFX header area (before <OFX>) into a key-value array.
     * Supports:
     * - OFX v2 XML-style header (<?xml ...?><OFX ...?> form) -> "key=value" tokens
     * - OFX v1 line-based header ("KEY:VALUE" per line)
     *
     * @param string $ofxHeader
     * @return array<string, string>
     */
    private static function parseHeader(string $ofxHeader): array
    {
        $header = [];

        $text = trim($ofxHeader);
        // Remove empty lines
        $text = (string) preg_replace('/^\s*$/m', '', $text);

        // XML-like header (OFX v2)
        if (preg_match('/^<\?xml/i', $text) === 1) {
            /* Remove XML prolog and possible <?OFX ...?> line */
            $text = (string) preg_replace('/<\?xml .*?\?>\s*/i', '', $text);
            $text = (string) preg_replace('/<\?OFX\s*/i', '', $text);
            $text = (string) preg_replace('/\?>\s*/', '', $text);
            $text = trim($text);

            if ($text !== '') {
                // Tokenize on spaces, each token like KEY="VALUE" or KEY=VALUE
                $tokens = preg_split('/\s+/', $text);

                foreach ($tokens as $token) {
                    if ($token === '') {
                        continue;
                    }

                    $parts = explode('=', $token, 2);

                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1], " \t\n\r\0\x0B\"'");

                        if ($key !== '') {
                            $header[$key] = $val;
                        }
                    }
                }
            }

            return $header;
        }

        // Line-based header (OFX v1)
        $lines = preg_split('/\n+/', $text) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // KEY:VALUE
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                if ($key !== '') {
                    $header[$key] = $val;
                }
            }
        }

        return $header;
    }

    /**
     * Convert SGML-style OFX v1 into well-formed XML.
     * - Escapes bare ampersands
     * - Closes unclosed tags by scanning and backfilling
     *
     * @param string $sgml
     * @return string XML string
     */
    private static function convertSgmlToXml(string $sgml): string
    {
        // Escape bare "&" to "&amp;" while keeping existing entities intact
        $sgml = (string) preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $sgml);

        $lines = explode("\n", $sgml);
        $stack = [];

        foreach ($lines as $i => &$line) {
            // Normalize each line and try to autoclose common unclosed content tags
            $line = trim(self::closeUnclosedXmlTags($line)) . "\n";

            // Match tags like <TAG> or </TAG>
            if (! preg_match('/^<(\/?[A-Za-z0-9.]+)>$/', trim($line), $m)) {
                continue;
            }

            // Closing tag: unwind stack until matching opening tag is found
            if ($m[1][0] === '/') {
                $tag = substr($m[1], 1);

                while (($last = array_pop($stack)) && $last[1] !== $tag) {
                    $lines[$last[0]] = "<{$last[1]}/>\n";
                }
            } else {
                // Opening tag: push to stack
                $stack[] = [$i, $m[1]];
            }
        }

        unset($line); // break reference

        // Close any remaining open tags as self-closing
        while ($last = array_pop($stack)) {
            $lines[$last[0]] = "<{$last[1]}/>\n";
        }

        // Return compacted XML string
        return implode("", array_map('trim', $lines));
    }

    /**
     * Close common unclosed tags that carry inline content on the same line.
     * Example:
     *   "<NAME>ACME CORP"  -> "<NAME>ACME CORP</NAME>"
     * Special-case MEMO when tag is present with no content.
     *
     * @param string $line
     * @return string
     */
    private static function closeUnclosedXmlTags(string $line): string
    {
        $line = trim($line);

        // Special-case discovered: empty MEMO line should be closed
        if (preg_match('/^<MEMO>$/', $line) === 1) {
            return '<MEMO></MEMO>';
        }

        // Match: <TAG>content   (no closing on the same line)
        // Avoid touching already-closed tags
        if (preg_match(
            '/^<([A-Za-z0-9.]+)>([^<]+)$/u',
            $line,
            $m
        )) {
            $tag = $m[1];
            $content = $m[2];
            return "<{$tag}>{$content}</{$tag}>";
        }

        return $line;
    }
}
