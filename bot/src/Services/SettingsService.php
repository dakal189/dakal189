<?php

namespace App\Services;

use App\Infrastructure\Database\Database;

class SettingsService
{
	private Database $db;
	private array $defaults;

	public function __construct(Database $db, array $defaults)
	{
		$this->db = $db;
		$this->defaults = $defaults;
	}

	public function get(string $key, $default = null): string
	{
		$row = $this->db->fetchOne('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
		if ($row) {
			return (string)$row['value'];
		}
		if (array_key_exists($key, $this->defaults)) {
			return (string)$this->defaults[$key];
		}
		return (string)$default;
	}

	public function set(string $key, string $value): void
	{
		$this->db->execute('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`) ', [$key, $value]);
	}
}