"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.containsLink = containsLink;
exports.containsBadWord = containsBadWord;
const linkRegex = /(https?:\/\/|t\.me\/)\S+/i;
const badWords = new Set([
    'fuck', 'shit', 'bitch', 'asshole', 'faggot', 'nigger', 'slut', 'whore', 'porn', 'xxx', 'rape', 'sex'
]);
function containsLink(text) {
    if (!text)
        return false;
    return linkRegex.test(text);
}
function containsBadWord(text) {
    if (!text)
        return false;
    const lower = text.toLowerCase();
    for (const w of badWords)
        if (lower.includes(w))
            return true;
    return false;
}
