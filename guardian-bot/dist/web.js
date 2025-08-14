"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.startWebServer = startWebServer;
const express_1 = __importDefault(require("express"));
const path_1 = __importDefault(require("path"));
const crypto_1 = __importDefault(require("crypto"));
const logger_1 = require("./logger");
const db_1 = require("./db");
const APP_SECRET = process.env.WEB_APP_SECRET || 'devsecret';
function startWebServer(port) {
    const app = (0, express_1.default)();
    app.use(express_1.default.json());
    app.use(express_1.default.urlencoded({ extended: true }));
    app.use('/static', express_1.default.static(path_1.default.join(process.cwd(), 'public')));
    app.get('/', (_req, res) => {
        res.sendFile(path_1.default.join(process.cwd(), 'public', 'index.html'));
    });
    app.post('/api/auth', (req, res) => {
        const { chat_id, user_id, hash, timestamp } = req.body || {};
        if (!chat_id || !user_id || !hash || !timestamp)
            return res.status(400).json({ ok: false, error: 'bad_request' });
        const str = `${chat_id}:${user_id}:${timestamp}`;
        const calc = crypto_1.default.createHmac('sha256', APP_SECRET).update(str).digest('hex');
        if (calc !== hash)
            return res.status(401).json({ ok: false, error: 'unauthorized' });
        return res.json({ ok: true });
    });
    app.get('/api/settings/:chatId', (req, res) => {
        const chatId = Number(req.params.chatId);
        const s = (0, db_1.getSettings)(chatId);
        res.json({ ok: true, settings: s });
    });
    app.post('/api/settings/:chatId', (req, res) => {
        const chatId = Number(req.params.chatId);
        const updates = req.body || {};
        for (const [key, value] of Object.entries(updates)) {
            (0, db_1.setSetting)(chatId, key, value);
        }
        res.json({ ok: true, settings: (0, db_1.getSettings)(chatId) });
    });
    app.get('/api/sanctions/:chatId', (req, res) => {
        const chatId = Number(req.params.chatId);
        const rows = db_1.db.prepare('SELECT * FROM sanctions WHERE chat_id = ? ORDER BY id DESC LIMIT 200').all(chatId);
        res.json({ ok: true, sanctions: rows });
    });
    app.listen(port, () => logger_1.logger.info({ port }, 'Web server started'));
}
