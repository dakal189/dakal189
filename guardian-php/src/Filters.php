<?php
namespace Guardian;

class Filters {
	private static array $badWords = [
		'fuck','shit','bitch','asshole','faggot','nigger','slut','whore','porn','xxx','rape','sex'
	];

	public static function containsLink(?string $text): bool {
		if (!$text) return false;
		return (bool)preg_match('/(https?:\/\/|t\.me\/)\S+/i', $text);
	}

	public static function containsBadWord(?string $text): bool {
		if (!$text) return false;
		$lower = mb_strtolower($text);
		foreach (self::$badWords as $w) {
			if (str_contains($lower, $w)) return true;
		}
		return false;
	}
}