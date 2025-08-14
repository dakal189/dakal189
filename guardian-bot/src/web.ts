import express from 'express';
import path from 'path';
import crypto from 'crypto';
import { logger } from './logger';
import { db, getSettings, setSetting } from './db';

const APP_SECRET = process.env.WEB_APP_SECRET || 'devsecret';

export function startWebServer(port: number) {
	const app = express();
	app.use(express.json());
	app.use(express.urlencoded({ extended: true }));

	app.use('/static', express.static(path.join(process.cwd(), 'public')));

	app.get('/', (_req, res) => {
		res.sendFile(path.join(process.cwd(), 'public', 'index.html'));
	});

	app.post('/api/auth', (req, res) => {
		const { chat_id, user_id, hash, timestamp } = req.body || {};
		if (!chat_id || !user_id || !hash || !timestamp) return res.status(400).json({ ok: false, error: 'bad_request' });
		const str = `${chat_id}:${user_id}:${timestamp}`;
		const calc = crypto.createHmac('sha256', APP_SECRET).update(str).digest('hex');
		if (calc !== hash) return res.status(401).json({ ok: false, error: 'unauthorized' });
		return res.json({ ok: true });
	});

	app.get('/api/settings/:chatId', (req, res) => {
		const chatId = Number(req.params.chatId);
		const s = getSettings(chatId);
		res.json({ ok: true, settings: s });
	});

	app.post('/api/settings/:chatId', (req, res) => {
		const chatId = Number(req.params.chatId);
		const updates = req.body || {};
		for (const [key, value] of Object.entries(updates)) {
			setSetting(chatId, key, value);
		}
		res.json({ ok: true, settings: getSettings(chatId) });
	});

	app.get('/api/sanctions/:chatId', (req, res) => {
		const chatId = Number(req.params.chatId);
		const rows = db.prepare('SELECT * FROM sanctions WHERE chat_id = ? ORDER BY id DESC LIMIT 200').all(chatId);
		res.json({ ok: true, sanctions: rows });
	});

	app.listen(port, () => logger.info({ port }, 'Web server started'));
}