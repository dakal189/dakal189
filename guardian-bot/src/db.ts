import Database from 'better-sqlite3';
import path from 'path';
import fs from 'fs';
import { logger } from './logger';

export type SettingsRow = {
	chat_id: number;
	anti_link: number;
	anti_forward: number;
	anti_badwords: number;
	captcha_required: number;
	max_warns: number;
	lockdown: number;
};

const dataDir = path.join(process.cwd(), 'data');
if (!fs.existsSync(dataDir)) fs.mkdirSync(dataDir, { recursive: true });

export const db = new Database(path.join(dataDir, 'guardian.db'));

db.pragma('journal_mode = WAL');

db.exec(`
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

export function getSettings(chatId: number): SettingsRow {
	const row = db.prepare('SELECT * FROM settings WHERE chat_id = ?').get(chatId) as SettingsRow | undefined;
	if (row) return row;
	db.prepare('INSERT INTO settings (chat_id) VALUES (?)').run(chatId);
	return db.prepare('SELECT * FROM settings WHERE chat_id = ?').get(chatId) as SettingsRow;
}

export function setSetting(chatId: number, key: string, value: unknown) {
	const column = String(key).replace(/[^a-z_]/g, '');
	db.prepare(`UPDATE settings SET ${column} = ? WHERE chat_id = ?`).run(value as never, chatId);
}

export function addWarn(chatId: number, userId: number) {
	db.prepare('INSERT INTO users (user_id, chat_id, warn_count) VALUES (?, ?, 1) ON CONFLICT(user_id, chat_id) DO UPDATE SET warn_count = warn_count + 1').run(userId, chatId);
}

export function getWarn(chatId: number, userId: number): number {
	const row = db.prepare('SELECT warn_count FROM users WHERE chat_id = ? AND user_id = ?').get(chatId, userId) as { warn_count?: number } | undefined;
	return row?.warn_count ?? 0;
}

export function ensureUser(chatId: number, user: { id: number; first_name?: string; last_name?: string; username?: string }) {
	db.prepare(
		`INSERT OR IGNORE INTO users (user_id, chat_id, first_name, last_name, username) VALUES (@id, @chatId, @first_name, @last_name, @username)`
	).run({ id: user.id, chatId, first_name: user.first_name ?? '', last_name: user.last_name ?? '', username: user.username ?? '' });
}

export function logSanction(chatId: number, userId: number, action: string, reason: string, expiresAt?: number) {
	db.prepare('INSERT INTO sanctions (chat_id, user_id, action, reason, expires_at) VALUES (?,?,?,?,?)').run(chatId, userId, action, reason, expiresAt ?? null);
	logger.info({ chatId, userId, action, reason, expiresAt }, 'Sanction logged');
}