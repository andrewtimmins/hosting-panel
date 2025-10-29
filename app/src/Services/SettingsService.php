<?php
namespace App\Services;

use App\Database\Connection;
use InvalidArgumentException;
use PDO;

class SettingsService
{
    private const WORDPRESS_KEY = 'wordpress_defaults';
    private const OPENCART_KEY = 'opencart_defaults';

    private PDO $db;
    private array $wordpressDefaults;
    private array $opencartDefaults;

    public function __construct(array $dbConfig, array $wordpressDefaults = [], array $opencartDefaults = [])
    {
        $this->db = Connection::get($dbConfig);
        $this->wordpressDefaults = array_merge($this->baseWordPressDefaults(), $this->normalizeWordPressDefaults($wordpressDefaults));
        $this->opencartDefaults = array_merge($this->baseOpenCartDefaults(), $this->normalizeOpenCartDefaults($opencartDefaults));
    }

    public function getWordPressDefaults(): array
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => self::WORDPRESS_KEY]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $this->wordpressDefaults;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $this->wordpressDefaults;
        }

        $resolved = array_merge($this->wordpressDefaults, $this->normalizeWordPressDefaults($decoded));
        $this->wordpressDefaults = $resolved;

        return $resolved;
    }

    public function updateWordPressDefaults(array $settings): array
    {
        $normalized = $this->validateWordPress($settings);

        $stmt = $this->db->prepare('REPLACE INTO settings (setting_key, setting_value) VALUES (:key, :value)');
        $stmt->execute([
            ':key' => self::WORDPRESS_KEY,
            ':value' => json_encode($normalized, JSON_THROW_ON_ERROR),
        ]);

        $this->wordpressDefaults = array_merge($this->wordpressDefaults, $normalized);

        return $this->wordpressDefaults;
    }

    public function getOpenCartDefaults(): array
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => self::OPENCART_KEY]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $this->opencartDefaults;
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return $this->opencartDefaults;
        }

        $resolved = array_merge($this->opencartDefaults, $this->normalizeOpenCartDefaults($decoded));
        $this->opencartDefaults = $resolved;

        return $resolved;
    }

    public function updateOpenCartDefaults(array $settings): array
    {
        $normalized = $this->validateOpenCart($settings);

        $stmt = $this->db->prepare('REPLACE INTO settings (setting_key, setting_value) VALUES (:key, :value)');
        $stmt->execute([
            ':key' => self::OPENCART_KEY,
            ':value' => json_encode($normalized, JSON_THROW_ON_ERROR),
        ]);

        $this->opencartDefaults = array_merge($this->opencartDefaults, $normalized);

        return $this->opencartDefaults;
    }

    private function normalizeWordPressDefaults(array $input): array
    {
        $defaults = $this->baseWordPressDefaults();

        return [
            'download_url' => $input['download_url'] ?? $defaults['download_url'],
            'default_admin_username' => $input['default_admin_username'] ?? $defaults['default_admin_username'],
            'default_admin_password' => $input['default_admin_password'] ?? $defaults['default_admin_password'],
            'default_admin_email' => $input['default_admin_email'] ?? $defaults['default_admin_email'],
            'default_site_title' => $input['default_site_title'] ?? $defaults['default_site_title'],
            'default_table_prefix' => $input['default_table_prefix'] ?? $defaults['default_table_prefix'],
        ];
    }

    private function normalizeOpenCartDefaults(array $input): array
    {
        $defaults = $this->baseOpenCartDefaults();

        return [
            'download_url' => $input['download_url'] ?? $defaults['download_url'],
            'default_admin_username' => $input['default_admin_username'] ?? $defaults['default_admin_username'],
            'default_admin_password' => $input['default_admin_password'] ?? $defaults['default_admin_password'],
            'default_admin_email' => $input['default_admin_email'] ?? $defaults['default_admin_email'],
            'default_store_name' => $input['default_store_name'] ?? $defaults['default_store_name'],
        ];
    }

    private function validateWordPress(array $settings): array
    {
        $normalized = $this->normalizeWordPressDefaults(array_merge($this->wordpressDefaults, $settings));

        $username = trim($normalized['default_admin_username']);
        if ($username === '') {
            throw new InvalidArgumentException('Default admin username cannot be empty');
        }

        $password = trim($normalized['default_admin_password']);
        if ($password === '') {
            throw new InvalidArgumentException('Default admin password cannot be empty');
        }

        $email = filter_var($normalized['default_admin_email'], FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new InvalidArgumentException('Default admin email must be a valid email address');
        }

        $prefix = trim($normalized['default_table_prefix']);
        if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new InvalidArgumentException('Default table prefix must contain only letters, numbers, or underscores');
        }
        if (!str_ends_with($prefix, '_')) {
            $prefix .= '_';
        }

        $normalized['default_admin_username'] = $username;
        $normalized['default_admin_password'] = $password;
        $normalized['default_admin_email'] = $email;
        $normalized['default_table_prefix'] = $prefix;

        return $normalized;
    }

    private function validateOpenCart(array $settings): array
    {
        $normalized = $this->normalizeOpenCartDefaults(array_merge($this->opencartDefaults, $settings));

        $username = trim($normalized['default_admin_username']);
        if ($username === '') {
            throw new InvalidArgumentException('Default admin username cannot be empty');
        }

        $password = trim($normalized['default_admin_password']);
        if ($password === '') {
            throw new InvalidArgumentException('Default admin password cannot be empty');
        }

        $email = filter_var($normalized['default_admin_email'], FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new InvalidArgumentException('Default admin email must be a valid email address');
        }

        $normalized['default_admin_username'] = $username;
        $normalized['default_admin_password'] = $password;
        $normalized['default_admin_email'] = $email;

        return $normalized;
    }

    private function baseWordPressDefaults(): array
    {
        return [
            'download_url' => 'https://wordpress.org/latest.zip',
            'default_admin_username' => 'admin',
            'default_admin_password' => 'ChangeMe123!',
            'default_admin_email' => 'admin@example.com',
            'default_site_title' => 'WordPress Site for {server_name}',
            'default_table_prefix' => 'wp_',
        ];
    }

    private function baseOpenCartDefaults(): array
    {
        return [
            'download_url' => 'https://github.com/opencart/opencart/releases/download/4.0.2.3/opencart-4.0.2.3.zip',
            'default_admin_username' => 'admin',
            'default_admin_password' => 'Admin123!',
            'default_admin_email' => 'admin@example.com',
            'default_store_name' => 'OpenCart Store for {server_name}',
        ];
    }
}
