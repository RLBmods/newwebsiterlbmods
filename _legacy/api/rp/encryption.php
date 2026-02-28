<?php
class Encryption {
    public static function encrypt($str) {
        $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        return self::encryptBytes($str);
    }

    public static function decrypt($bytes) {
        return self::decryptBytes($bytes);
    }

    public static function encryptBytes($str) {
        $encryptedString = '';
        $length = strlen($str);
        $encryptedString .= strlen($length) . $length;

        $indices = range(0, $length - 1);
        shuffle($indices);

        foreach ($indices as $index) {
            $encryptedString .= strlen($index) . $index;
            $byteValue = ord($str[$index]);
            if ($byteValue > 255) {
                $byteValue = $byteValue % 256;
            }
            $encryptedString .= strlen($byteValue) . $byteValue;
        }

        return $encryptedString;
    }

    public static function decryptBytes($str) {
        $lenLength = (int)substr($str, 0, 1);
        $bytesLength = (int)substr($str, 1, $lenLength);
        $decryptedBytes = str_repeat("\0", $bytesLength);

        $index = 1 + $lenLength;
        while ($index < strlen($str)) {
            $indexLength = (int)substr($str, $index, 1);
            $startIndex = $index + 1;
            $arrayIndex = (int)substr($str, $startIndex, $indexLength);

            $startIndex += $indexLength;
            $byteLength = (int)substr($str, $startIndex, 1);
            $startIndex += 1;

            $byteValue = (int)substr($str, $startIndex, $byteLength);
            $decryptedBytes[$arrayIndex] = chr($byteValue);
            $index = $startIndex + $byteLength;
        }

        return $decryptedBytes;
    }
}
?>