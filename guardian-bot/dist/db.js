"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.db = void 0;
exports.getSettings = getSettings;
exports.setSetting = setSetting;
exports.addWarn = addWarn;
exports.getWarn = getWarn;
exports.ensureUser = ensureUser;
exports.logSanction = logSanction;
const better_sqlite3_1 = __importDefault(require("better-sqlite3"));
const path_1 = __importDefault(require("path"));
const fs_1 = __importDefault(require("fs"));
const logger_1 = require("./logger");
const dataDir = path_1.default.join(process.cwd(), 'data');
if (!fs_1.default.existsSync(dataDir))
    fs_1.default.mkdirSync(dataDir, { recursive: true });
exports.db = new better_sqlite3_1.default(path_1.default.join(dataDir, 'guardian.db'));
exports.db.pragma('journal_mode = WAL');
exports.db.exec(`
CREATE TABLE IF NOT EXISTS groups (
	chat_id INTEGER PRIMARY KEY,
	name TEXT,
	title TEXT,
	created_at INTEGER DEFAULT (strftime('%s','now'))
);

CREATE TABLE IF NOT EXISTS users (
	user_id INTEGER,
	chat_id INTEGER,
	first_name TEXT,
	last_name TEXT,
	username TEXT,
	is_admin INTEGER DEFAULT 0,
	warn_count INTEGER DEFAULT 0,
	PRIMARY KEY (user_id, chat_id)
);

CREATE TABLE IF NOT EXISTS settings (
	chat_id INTEGER PRIMARY KEY,
	anti_link INTEGER DEFAULT 1,
	anti_forward INTEGER DEFAULT 1,
	anti_badwords INTEGER DEFAULT 1,
	captcha_required INTEGER DEFAULT 1,
	max_warns INTEGER DEFAULT 3,
	lockdown INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sanctions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	chat_id INTEGER,
	user_id INTEGER,
	action TEXT,
	reason TEXT,
	expires_at INTEGER,
	created_at INTEGER DEFAULT (strftime('%s','now'))
);
`);
function getSettings(chatId) {
    const row = exports.db.prepare('SELECT * FROM settings WHERE chat_id = ?').get(chatId);
    if (row)
        return row;
    exports.db.prepare('INSERT INTO settings (chat_id) VALUES (?)').run(chatId);
    return exports.db.prepare('SELECT * FROM settings WHERE chat_id = ?').get(chatId);
}
function setSetting(chatId, key, value) {
    const column = String(key).replace(/[^a-z_]/g, '');
    exports.db.prepare(`UPDATE settings SET ${column} = ? WHERE chat_id = ?`).run(value, chatId);
}
function addWarn(chatId, userId) {
    exports.db.prepare('INSERT INTO users (user_id, chat_id, warn_count) VALUES (?, ?, 1) ON CONFLICT(user_id, chat_id) DO UPDATE SET warn_count = warn_count + 1').run(userId, chatId);
}
function getWarn(chatId, userId) {
    const row = exports.db.prepare('SELECT warn_count FROM users WHERE chat_id = ? AND user_id = ?').get(chatId, userId);
    return row?.warn_count ?? 0;
}
function ensureUser(chatId, user) {
    exports.db.prepare(`INSERT OR IGNORE INTO users (user_id, chat_id, first_name, last_name, username) VALUES (@id, @chatId, @first_name, @last_name, @username)`).run({ id: user.id, chatId, first_name: user.first_name ?? '', last_name: user.last_name ?? '', username: user.username ?? '' });
}
function logSanction(chatId, userId, action, reason, expiresAt) {
    exports.db.prepare('INSERT INTO sanctions (chat_id, user_id, action, reason, expires_at) VALUES (?,?,?,?,?)').run(chatId, userId, action, reason, expiresAt ?? null);
    logger_1.logger.info({ chatId, userId, action, reason, expiresAt }, 'Sanction logged');
}
