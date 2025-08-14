const linkRegex = /(https?:\/\/|t\.me\/)\S+/i;
const badWords = new Set<string>([
	'fuck','shit','bitch','asshole','faggot','nigger','slut','whore','porn','xxx','rape','sex'
]);

export function containsLink(text?: string | null): boolean {
	if (!text) return false;
	return linkRegex.test(text);
}

export function containsBadWord(text?: string | null): boolean {
	if (!text) return false;
	const lower = text.toLowerCase();
	for (const w of badWords) if (lower.includes(w)) return true;
	return false;
}