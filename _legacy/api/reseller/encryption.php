<?php
class Encryption {
    public static function encrypt($str) {
        return base64_encode($str);
    }

    public static function decrypt($str) {
        return base64_decode($str);
    }
}
?>