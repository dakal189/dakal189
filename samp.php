// Polyfills
if (!function_exists('str_starts_with')) {
	function str_starts_with($haystack, $needle) {
		return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
	}
}

const SUPPORTED_LANGS = ['fa', 'en', 'ru'];