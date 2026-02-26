<?php

namespace ShopCode\Services;

/**
 * Šifrování a dešifrování citlivých dat (např. Shoptet hesel)
 * Používá AES-256-CBC s klíčem z config.php
 */
class Encryption
{
    private const CIPHER = 'aes-256-cbc';
    
    private string $key;
    
    public function __construct()
    {
        // Encryption key z config.php
        if (!defined('ENCRYPTION_KEY')) {
            throw new \RuntimeException(
                'ENCRYPTION_KEY není definován v config.php. ' .
                'Vygeneruj pomocí: base64_encode(random_bytes(32))'
            );
        }
        
        $this->key = base64_decode(ENCRYPTION_KEY);
        
        if (strlen($this->key) !== 32) {
            throw new \RuntimeException('ENCRYPTION_KEY musí být 32 bytů (base64 encoded)');
        }
    }
    
    /**
     * Zašifruje text
     * 
     * @param  string $plaintext Text k zašifrování
     * @return string Base64 encoded: iv:ciphertext
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }
        
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($ciphertext === false) {
            throw new \RuntimeException('Šifrování selhalo');
        }
        
        // Uložíme iv:ciphertext (oba base64 encoded)
        return base64_encode($iv) . ':' . base64_encode($ciphertext);
    }
    
    /**
     * Dešifruje text
     * 
     * @param  string $encrypted Base64 encoded: iv:ciphertext
     * @return string Dešifrovaný text
     */
    public function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        
        $parts = explode(':', $encrypted, 2);
        
        if (count($parts) !== 2) {
            throw new \RuntimeException('Neplatný formát šifrovaného textu');
        }
        
        [$ivBase64, $ciphertextBase64] = $parts;
        
        $iv = base64_decode($ivBase64);
        $ciphertext = base64_decode($ciphertextBase64);
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($plaintext === false) {
            throw new \RuntimeException('Dešifrování selhalo');
        }
        
        return $plaintext;
    }
    
    /**
     * Testuje, jestli šifrování funguje správně
     * 
     * @return bool
     */
    public static function test(): bool
    {
        try {
            $enc = new self();
            $plaintext = 'test-' . bin2hex(random_bytes(8));
            $encrypted = $enc->encrypt($plaintext);
            $decrypted = $enc->decrypt($encrypted);
            
            return $plaintext === $decrypted;
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * Vygeneruje nový encryption key
     * 
     * @return string Base64 encoded 32-byte klíč
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
