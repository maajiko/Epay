<?php

namespace WeChatPay;

class RsaTool
{
    /**
     * @var string - Equal to `sequence(oid(1.2.840.113549.1.1.1), null))`
     * @link https://datatracker.ietf.org/doc/html/rfc3447#appendix-A.2
     */
    private const ASN1_OID_RSAENCRYPTION = '300d06092a864886f70d0101010500';
    private const ASN1_SEQUENCE = 48;
    private const CHR_NUL = "\0";
    private const CHR_ETX = "\3";

    /**
     * Translate the \$thing strlen from `X690` style to the `ASN.1` 128bit hexadecimal length string
     *
     * @param string $thing - The string
     *
     * @return string The `ASN.1` 128bit hexadecimal length string
     */
    private static function encodeLength(string $thing): string
    {
        $num = strlen($thing);
        if ($num <= 0x7F) {
            return sprintf('%c', $num);
        }

        $tmp = ltrim(pack('N', $num), self::CHR_NUL);
        return pack('Ca*', strlen($tmp) | 0x80, $tmp);
    }

    /**
     * Convert the `PKCS#1` format RSA Public Key to `SPKI` format
     *
     * @param string $thing - The base64-encoded string, without evelope style
     *
     * @return string The `SPKI` style public key without evelope string
     */
    public static function pkcs1ToSpki(string $thing): string
    {
        $raw = self::CHR_NUL . base64_decode($thing);
        $new = pack('H*', self::ASN1_OID_RSAENCRYPTION) . self::CHR_ETX . self::encodeLength($raw) . $raw;

        return base64_encode(pack('Ca*a*', self::ASN1_SEQUENCE, self::encodeLength($new), $new));
    }

    public static function pemToBase64(string $data): string
    {
        $line = explode("\n", $data);
        $base64 = '';
        foreach($line as $row){
            if(empty($row) || strpos($row, '-----BEGIN')!==false || strpos($row, '-----END')!==false) continue;
            $base64 .= trim($row);
        }
        return $base64;
    }
    
    public static function base64ToPem(string $data, $type): string
    {
        if(empty($data) || strpos($data, '-----BEGIN')!==false) return $data;
        $pem = "-----BEGIN ".$type."-----\n" .
            wordwrap($data, 64, "\n", true) .
            "\n-----END ".$type."-----";
        return $pem;
    }

    public static function pkcs1ToSpkiPem(string $thing): string
    {
        $raw = self::pemToBase64($thing);
        $new = self::pkcs1ToSpki($raw);
        return self::base64ToPem($new, 'PUBLIC KEY');
    }
}