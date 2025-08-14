"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.createBot = createBot;
const telegraf_1 = require("telegraf");
const filters_1 = require("telegraf/filters");
const dotenv_1 = __importDefault(require("dotenv"));
const logger_1 = require("./logger");
const db_1 = require("./db");
const filters_2 = require("./filters");
dotenv_1.default.config();
function createBot() {
    const bot = new telegraf_1.Telegraf(process.env.BOT_TOKEN || '');
    bot.start(async (ctx) => {
        await ctx.reply('ربات گاردین فعال است. من را ادمین گروه کنید تا مدیریت کنم. /help');
    });
    bot.help(async (ctx) => {
        await ctx.reply([
            'دستورات مدیریت:',
            '/settings - لینک پنل',
            '/warn @user [دلیل]',
            '/mute @user [مدت] (مثال: 10m, 2h, 1d)',
            '/ban @user [دلیل]',
            '/lockdown on|off',
        ].join('\n'));
    });
    bot.command('settings', async (ctx) => {
        if (!ctx.chat)
            return;
        const chatId = ctx.chat.id;
        const ts = Math.floor(Date.now() / 1000);
        const crypto = await Promise.resolve().then(() => __importStar(require('crypto')));
        const hash = crypto.createHmac('sha256', process.env.WEB_APP_SECRET || 'devsecret').update(`${chatId}:${ctx.from?.id}:${ts}`).digest('hex');
        const url = `${process.env.WEB_ORIGIN || 'http://localhost:' + (process.env.PORT || 8080)}/?chat_id=${chatId}&user_id=${ctx.from?.id}&timestamp=${ts}&hash=${hash}`;
        await ctx.reply(`پنل مدیریت: ${url}`);
    });
    bot.on((0, filters_1.message)('new_chat_members'), async (ctx) => {
        if (!ctx.chat)
            return;
        const settings = (0, db_1.getSettings)(ctx.chat.id);
        if (settings.captcha_required) {
            for (const m of ctx.message.new_chat_members) {
                try {
                    await ctx.restrictChatMember(m.id, { permissions: { can_send_messages: false } });
                    await ctx.reply(`${m.first_name} خوش آمدی! برای دسترسی «من ربات هستم نیستم» را بزنید.`, {
                        reply_markup: { inline_keyboard: [[{ text: 'من ربات نیستم 🤖❌', callback_data: `captcha:${m.id}` }]] },
                    });
                }
                catch (e) {
                    logger_1.logger.warn(e, 'captcha restrict failed');
                }
            }
        }
    });
    bot.on('callback_query', async (ctx) => {
        if (ctx.callbackQuery && 'data' in ctx.callbackQuery) {
            const data = String(ctx.callbackQuery.data || '');
            if (data.startsWith('captcha:')) {
                const userId = Number(data.split(':')[1]);
                if (ctx.from?.id === userId && ctx.chat) {
                    await ctx.restrictChatMember(userId, { permissions: { can_send_messages: true, can_send_audios: true, can_send_documents: true, can_send_photos: true, can_send_videos: true, can_send_video_notes: true, can_send_voice_notes: true, can_send_polls: true, can_send_other_messages: true } });
                    await ctx.answerCbQuery('تایید شد. خوش آمدید!');
                }
            }
        }
    });
    bot.on((0, filters_1.message)('text'), async (ctx) => {
        if (!ctx.chat || !ctx.from)
            return;
        (0, db_1.ensureUser)(ctx.chat.id, ctx.from);
        const isAdmin = await isUserAdmin(ctx);
        if (isAdmin)
            return;
        const settings = (0, db_1.getSettings)(ctx.chat.id);
        if (settings.lockdown) {
            await deleteMessageSafe(ctx);
            return;
        }
        const text = ctx.message.text || '';
        if (settings.anti_link && (0, filters_2.containsLink)(text)) {
            await warnAndMaybeMute(ctx, 'ارسال لینک ممنوع است');
            return;
        }
        if (settings.anti_badwords && (0, filters_2.containsBadWord)(text)) {
            await warnAndMaybeMute(ctx, 'کلمات نامناسب ممنوع است');
            return;
        }
    });
    bot.use(async (ctx, next) => {
        // Detect forwarded messages generically
        if (ctx.update && ctx.update.message) {
            const msg = ctx.update.message;
            if (msg.forward_date && ctx.chat && ctx.from) {
                const chatId = ctx.chat.id;
                const settings = (0, db_1.getSettings)(chatId);
                if (settings.anti_forward) {
                    await deleteMessageSafe(ctx);
                    await ctx.reply('فوروارد پیام در این گروه مجاز نیست.');
                    return; // stop chain
                }
            }
        }
        return next();
    });
    bot.command('warn', async (ctx) => {
        if (!ctx.chat || !ctx.from)
            return;
        if (!(await isUserAdmin(ctx)))
            return;
        const target = await extractTargetUserId(ctx);
        if (!target)
            return ctx.reply('کاربر نامعتبر');
        await warnUser(ctx, target, 'اخطار دستی');
    });
    bot.command('mute', async (ctx) => {
        if (!ctx.chat || !ctx.from)
            return;
        if (!(await isUserAdmin(ctx)))
            return;
        const target = await extractTargetUserId(ctx);
        if (!target)
            return ctx.reply('کاربر نامعتبر');
        const duration = parseDuration(ctx.message.text?.split(' ').slice(2).join(' ') || '10m');
        await ctx.restrictChatMember(target, { until_date: Math.floor(Date.now() / 1000) + duration, permissions: { can_send_messages: false } });
        (0, db_1.logSanction)(ctx.chat.id, target, 'mute', 'by admin', Math.floor(Date.now() / 1000) + duration);
        await ctx.reply('کاربر میوت شد');
    });
    bot.command('ban', async (ctx) => {
        if (!ctx.chat || !ctx.from)
            return;
        if (!(await isUserAdmin(ctx)))
            return;
        const target = await extractTargetUserId(ctx);
        if (!target)
            return ctx.reply('کاربر نامعتبر');
        await ctx.banChatMember(target);
        (0, db_1.logSanction)(ctx.chat.id, target, 'ban', 'by admin');
        await ctx.reply('کاربر بن شد');
    });
    bot.command('lockdown', async (ctx) => {
        if (!ctx.chat)
            return;
        if (!(await isUserAdmin(ctx)))
            return;
        const arg = ctx.message.text?.split(' ')[1];
        const on = arg === 'on' ? 1 : 0;
        db_1.db.prepare('UPDATE settings SET lockdown = ? WHERE chat_id = ?').run(on, ctx.chat.id);
        await ctx.reply(on ? 'لاکدان فعال شد' : 'لاکدان غیرفعال شد');
    });
    bot.catch((err, ctx) => {
        logger_1.logger.error({ err }, 'Bot error');
    });
    return bot;
}
async function isUserAdmin(ctx) {
    if (!ctx.chat || !ctx.from)
        return false;
    try {
        const m = await ctx.getChatMember(ctx.from.id);
        return ['creator', 'administrator'].includes(m.status);
    }
    catch {
        return false;
    }
}
async function deleteMessageSafe(ctx) {
    try {
        // @ts-ignore
        await ctx.deleteMessage(ctx.message?.message_id);
    }
    catch { }
}
async function warnAndMaybeMute(ctx, reason) {
    if (!ctx.chat || !ctx.from)
        return;
    await deleteMessageSafe(ctx);
    (0, db_1.addWarn)(ctx.chat.id, ctx.from.id);
    const warns = (0, db_1.getWarn)(ctx.chat.id, ctx.from.id);
    const settings = (0, db_1.getSettings)(ctx.chat.id);
    await ctx.reply(`${ctx.from.first_name}: ${reason} (اخطار ${warns}/${settings.max_warns})`);
    (0, db_1.logSanction)(ctx.chat.id, ctx.from.id, 'warn', reason);
    if (warns >= settings.max_warns) {
        await ctx.restrictChatMember(ctx.from.id, { permissions: { can_send_messages: false }, until_date: Math.floor(Date.now() / 1000) + 3600 });
        (0, db_1.logSanction)(ctx.chat.id, ctx.from.id, 'mute', 'auto mute after warns', Math.floor(Date.now() / 1000) + 3600);
        await ctx.reply('کاربر به مدت ۱ ساعت میوت شد');
    }
}
async function warnUser(ctx, userId, reason) {
    if (!ctx.chat)
        return;
    (0, db_1.addWarn)(ctx.chat.id, userId);
    const warns = (0, db_1.getWarn)(ctx.chat.id, userId);
    const settings = (0, db_1.getSettings)(ctx.chat.id);
    (0, db_1.logSanction)(ctx.chat.id, userId, 'warn', reason);
    if (warns >= settings.max_warns) {
        await ctx.restrictChatMember(userId, { permissions: { can_send_messages: false }, until_date: Math.floor(Date.now() / 1000) + 3600 });
        (0, db_1.logSanction)(ctx.chat.id, userId, 'mute', 'auto mute after warns', Math.floor(Date.now() / 1000) + 3600);
    }
}
function parseDuration(input) {
    const m = input.match(/(\d+)([smhd])/i);
    if (!m)
        return 600;
    const value = Number(m[1]);
    switch (m[2].toLowerCase()) {
        case 's': return value;
        case 'm': return value * 60;
        case 'h': return value * 3600;
        case 'd': return value * 86400;
        default: return 600;
    }
}
async function extractTargetUserId(ctx) {
    // Prefer replied user
    // @ts-ignore
    const reply = ctx.message?.reply_to_message;
    if (reply?.from?.id)
        return reply.from.id;
    const parts = ctx.message && 'text' in ctx.message ? ctx.message.text.split(' ') : [];
    const handle = parts[1];
    if (!handle)
        return null;
    if (/^\d+$/.test(handle))
        return Number(handle);
    try {
        const user = await ctx.telegram.getChatMember(ctx.chat.id, handle);
        return user.user.id;
    }
    catch {
        return null;
    }
}
