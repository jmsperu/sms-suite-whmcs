<?php
/**
 * SMS Suite - Segment Counter
 *
 * Detects encoding and calculates SMS segments
 * Based on GSM 03.38 specification
 */

namespace SMSSuite\Core;

class SegmentCounter
{
    // Encoding types
    const GSM_7BIT = 'gsm7';
    const GSM_7BIT_EX = 'gsm7ex';
    const UCS2 = 'ucs2';
    const WHATSAPP = 'whatsapp';

    // Character limits
    const GSM_7BIT_SINGLE = 160;
    const GSM_7BIT_MULTI = 153;
    const UCS2_SINGLE = 70;
    const UCS2_MULTI = 67;
    const WHATSAPP_LIMIT = 1000;

    /**
     * GSM 7-bit basic character set (Unicode code points)
     */
    private static array $gsm7BitChars = [
        10, 12, 13, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44,
        45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60,
        61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76,
        77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92,
        93, 94, 95, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107,
        108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120,
        121, 122, 123, 124, 125, 126, 161, 163, 164, 165, 167, 191, 196,
        197, 198, 199, 201, 209, 214, 216, 220, 223, 224, 228, 229, 230,
        232, 233, 236, 241, 242, 246, 248, 249, 252, 915, 916, 920, 923,
        926, 928, 931, 934, 936, 937, 8364,
    ];

    /**
     * GSM 7-bit extended characters (count as 2 characters)
     */
    private static array $gsm7BitExtChars = [
        12,   // Form feed
        91,   // [
        92,   // \
        93,   // ]
        94,   // ^
        123,  // {
        124,  // |
        125,  // }
        126,  // ~
        8364, // Euro sign
    ];

    /**
     * Count message segments and detect encoding
     *
     * @param string $message
     * @param string $channel 'sms' or 'whatsapp'
     * @return SegmentResult
     */
    public static function count(string $message, string $channel = 'sms'): SegmentResult
    {
        if (empty($message)) {
            return new SegmentResult(self::GSM_7BIT, 0, 0, 0, 160);
        }

        // WhatsApp doesn't use segments the same way
        if ($channel === 'whatsapp') {
            $length = mb_strlen($message, 'UTF-8');
            $segments = (int)ceil($length / self::WHATSAPP_LIMIT);
            return new SegmentResult(self::WHATSAPP, $length, $segments, $segments, self::WHATSAPP_LIMIT);
        }

        // Convert to unicode code points
        $codePoints = self::toCodePoints($message);
        $encoding = self::detectEncoding($codePoints);

        // Calculate length (extended chars count as 2)
        $length = count($codePoints);
        $extendedCount = 0;

        if ($encoding === self::GSM_7BIT_EX) {
            foreach ($codePoints as $cp) {
                if (in_array($cp, self::$gsm7BitExtChars)) {
                    $extendedCount++;
                }
            }
            $length += $extendedCount; // Each extended char counts as 2
        } elseif ($encoding === self::UCS2) {
            // Characters >= U+10000 count as 2 (surrogate pairs)
            foreach ($codePoints as $cp) {
                if ($cp >= 65536) {
                    $length++;
                }
            }
        }

        // Determine per-message limit
        if ($encoding === self::GSM_7BIT || $encoding === self::GSM_7BIT_EX) {
            $singleLimit = self::GSM_7BIT_SINGLE;
            $multiLimit = self::GSM_7BIT_MULTI;
        } else {
            $singleLimit = self::UCS2_SINGLE;
            $multiLimit = self::UCS2_MULTI;
        }

        // Calculate segments
        if ($length <= $singleLimit) {
            $segments = 1;
            $perMessage = $singleLimit;
        } else {
            $segments = (int)ceil($length / $multiLimit);
            $perMessage = $multiLimit;
        }

        // Units (for billing - typically same as segments)
        $units = $segments;

        return new SegmentResult($encoding, $length, $segments, $units, $perMessage);
    }

    /**
     * Detect encoding type for message
     *
     * @param array $codePoints
     * @return string
     */
    private static function detectEncoding(array $codePoints): string
    {
        $hasExtended = false;
        $allGsmChars = array_merge(self::$gsm7BitChars, self::$gsm7BitExtChars);

        foreach ($codePoints as $cp) {
            // Check if character is not in GSM character set
            if (!in_array($cp, $allGsmChars)) {
                return self::UCS2;
            }

            // Check for extended characters
            if (in_array($cp, self::$gsm7BitExtChars)) {
                $hasExtended = true;
            }
        }

        return $hasExtended ? self::GSM_7BIT_EX : self::GSM_7BIT;
    }

    /**
     * Convert UTF-8 string to array of Unicode code points
     *
     * @param string $str
     * @return array
     */
    private static function toCodePoints(string $str): array
    {
        $codePoints = [];
        $len = strlen($str);
        $i = 0;

        while ($i < $len) {
            $byte = ord($str[$i]);

            if ($byte < 128) {
                // ASCII
                $codePoints[] = $byte;
                $i++;
            } elseif (($byte & 0xE0) === 0xC0) {
                // 2-byte sequence
                $codePoints[] = (($byte & 0x1F) << 6) | (ord($str[$i + 1]) & 0x3F);
                $i += 2;
            } elseif (($byte & 0xF0) === 0xE0) {
                // 3-byte sequence
                $codePoints[] = (($byte & 0x0F) << 12) | ((ord($str[$i + 1]) & 0x3F) << 6) | (ord($str[$i + 2]) & 0x3F);
                $i += 3;
            } elseif (($byte & 0xF8) === 0xF0) {
                // 4-byte sequence
                $codePoints[] = (($byte & 0x07) << 18) | ((ord($str[$i + 1]) & 0x3F) << 12) | ((ord($str[$i + 2]) & 0x3F) << 6) | (ord($str[$i + 3]) & 0x3F);
                $i += 4;
            } else {
                // Invalid byte, skip
                $i++;
            }
        }

        return $codePoints;
    }

    /**
     * Remove or replace non-GSM characters
     *
     * @param string $message
     * @param string|null $replacement Character to replace with (null = remove)
     * @return string
     */
    public static function sanitizeToGsm(string $message, ?string $replacement = null): string
    {
        $codePoints = self::toCodePoints($message);
        $allGsmChars = array_merge(self::$gsm7BitChars, self::$gsm7BitExtChars);
        $result = [];

        foreach ($codePoints as $cp) {
            if (in_array($cp, $allGsmChars)) {
                $result[] = $cp;
            } elseif ($replacement !== null) {
                $result[] = ord($replacement);
            }
            // If replacement is null, non-GSM chars are removed
        }

        return self::codePointsToString($result);
    }

    /**
     * Convert code points back to UTF-8 string
     *
     * @param array $codePoints
     * @return string
     */
    private static function codePointsToString(array $codePoints): string
    {
        $str = '';
        foreach ($codePoints as $cp) {
            if ($cp < 128) {
                $str .= chr($cp);
            } elseif ($cp < 2048) {
                $str .= chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
            } elseif ($cp < 65536) {
                $str .= chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
            } else {
                $str .= chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
            }
        }
        return $str;
    }

    /**
     * Truncate message to fit within segment limit
     *
     * @param string $message
     * @param int $maxSegments
     * @param string $channel
     * @return string
     */
    public static function truncate(string $message, int $maxSegments = 1, string $channel = 'sms'): string
    {
        $result = self::count($message, $channel);

        if ($result->segments <= $maxSegments) {
            return $message;
        }

        // Calculate max characters allowed
        $maxChars = $result->perMessage * $maxSegments;

        // Binary search for correct length
        $low = 0;
        $high = mb_strlen($message, 'UTF-8');

        while ($low < $high) {
            $mid = (int)(($low + $high + 1) / 2);
            $truncated = mb_substr($message, 0, $mid, 'UTF-8');
            $truncResult = self::count($truncated, $channel);

            if ($truncResult->segments <= $maxSegments) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return mb_substr($message, 0, $low, 'UTF-8');
    }
}

/**
 * Result of segment counting
 */
class SegmentResult
{
    public string $encoding;
    public int $length;
    public int $segments;
    public int $units;
    public int $perMessage;
    public int $remaining;

    public function __construct(string $encoding, int $length, int $segments, int $units, int $perMessage)
    {
        $this->encoding = $encoding;
        $this->length = $length;
        $this->segments = $segments;
        $this->units = $units;
        $this->perMessage = $perMessage;
        $this->remaining = ($perMessage * $segments) - $length;
    }

    public function toArray(): array
    {
        return [
            'encoding' => $this->encoding,
            'length' => $this->length,
            'segments' => $this->segments,
            'units' => $this->units,
            'per_message' => $this->perMessage,
            'remaining' => $this->remaining,
        ];
    }
}
