import { Telegraf, Context } from 'telegraf';
import { message } from 'telegraf/filters';
import dotenv from 'dotenv';
import { logger } from './logger';
import { db, ensureUser, getSettings, addWarn, getWarn, logSanction, SettingsRow } from './db';
import { containsLink, containsBadWord } from './filters';

dotenv.config();

export function createBot() {
	const bot = new Telegraf(process.env.BOT_TOKEN || '');

	bot.start(async (ctx) => {
		await ctx.reply('ربات گاردین فعال است. من را ادمین گروه کنید تا مدیریت کنم. /help');
	});

	bot.help(async (ctx) => {
		await ctx.reply(
			[
				'دستورات مدیریت:',
				'/settings - لینک پنل',
				'/warn @user [دلیل]',
				'/mute @user [مدت] (مثال: 10m, 2h, 1d)',
				'/ban @user [دلیل]',
				'/lockdown on|off',
			].join('\n')
		);
	});

	bot.command('settings', async (ctx) => {
		if (!ctx.chat) return;
		const chatId = ctx.chat.id;
		const ts = Math.floor(Date.now() / 1000);
		const crypto = await import('crypto');
		const hash = crypto.createHmac('sha256', process.env.WEB_APP_SECRET || 'devsecret').update(`${chatId}:${ctx.from?.id}:${ts}`).digest('hex');
		const url = `${process.env.WEB_ORIGIN || 'http://localhost:'+ (process.env.PORT||8080)}/?chat_id=${chatId}&user_id=${ctx.from?.id}&timestamp=${ts}&hash=${hash}`;
		await ctx.reply(`پنل مدیریت: ${url}`);
	});

	bot.on(message('new_chat_members'), async (ctx) => {
		if (!ctx.chat) return;
		const settings: SettingsRow = getSettings(ctx.chat.id);
		if (settings.captcha_required) {
			for (const m of ctx.message.new_chat_members) {
				try {
					await ctx.restrictChatMember(m.id, { permissions: { can_send_messages: false } });
					await ctx.reply(`${m.first_name} خوش آمدی! برای دسترسی «من ربات هستم نیستم» را بزنید.`, {
						reply_markup: { inline_keyboard: [[{ text: 'من ربات نیستم 🤖❌', callback_data: `captcha:${m.id}` }]] },
					});
				} catch (e) {
					logger.warn(e, 'captcha restrict failed');
				}
			}
		}
	});

	bot.on('callback_query', async (ctx) => {
		if (ctx.callbackQuery && 'data' in ctx.callbackQuery) {
			const data = String((ctx.callbackQuery as any).data || '');
			if (data.startsWith('captcha:')) {
				const userId = Number(data.split(':')[1]);
				if (ctx.from?.id === userId && ctx.chat) {
					await ctx.restrictChatMember(userId, { permissions: { can_send_messages: true, can_send_audios: true, can_send_documents: true, can_send_photos: true, can_send_videos: true, can_send_video_notes: true, can_send_voice_notes: true, can_send_polls: true, can_send_other_messages: true } });
					await ctx.answerCbQuery('تایید شد. خوش آمدید!');
				}
			}
		}
	});

	bot.on(message('text'), async (ctx) => {
		if (!ctx.chat || !ctx.from) return;
		ensureUser(ctx.chat.id, ctx.from);
		const isAdmin = await isUserAdmin(ctx);
		if (isAdmin) return;
		const settings: SettingsRow = getSettings(ctx.chat.id);
		if (settings.lockdown) {
			await deleteMessageSafe(ctx);
			return;
		}
		const text = ctx.message.text || '';
		if (settings.anti_link && containsLink(text)) {
			await warnAndMaybeMute(ctx, 'ارسال لینک ممنوع است');
			return;
		}
		if (settings.anti_badwords && containsBadWord(text)) {
			await warnAndMaybeMute(ctx, 'کلمات نامناسب ممنوع است');
			return;
		}
	});

	bot.use(async (ctx, next) => {
		// Detect forwarded messages generically
		if ((ctx as any).update && (ctx as any).update.message) {
			const msg: any = (ctx as any).update.message;
			if (msg.forward_date && (ctx as any).chat && (ctx as any).from) {
				const chatId: number = (ctx as any).chat.id;
				const settings: SettingsRow = getSettings(chatId);
				if (settings.anti_forward) {
					await deleteMessageSafe(ctx as any);
					await (ctx as any).reply('فوروارد پیام در این گروه مجاز نیست.');
					return; // stop chain
				}
			}
		}
		return next();
	});

	bot.command('warn', async (ctx) => {
		if (!ctx.chat || !ctx.from) return;
		if (!(await isUserAdmin(ctx))) return;
		const target = await extractTargetUserId(ctx);
		if (!target) return ctx.reply('کاربر نامعتبر');
		await warnUser(ctx, target, 'اخطار دستی');
	});

	bot.command('mute', async (ctx) => {
		if (!ctx.chat || !ctx.from) return;
		if (!(await isUserAdmin(ctx))) return;
		const target = await extractTargetUserId(ctx);
		if (!target) return ctx.reply('کاربر نامعتبر');
		const duration = parseDuration(ctx.message.text?.split(' ').slice(2).join(' ') || '10m');
		await ctx.restrictChatMember(target, { until_date: Math.floor(Date.now() / 1000) + duration, permissions: { can_send_messages: false } });
		logSanction(ctx.chat.id, target, 'mute', 'by admin', Math.floor(Date.now() / 1000) + duration);
		await ctx.reply('کاربر میوت شد');
	});

	bot.command('ban', async (ctx) => {
		if (!ctx.chat || !ctx.from) return;
		if (!(await isUserAdmin(ctx))) return;
		const target = await extractTargetUserId(ctx);
		if (!target) return ctx.reply('کاربر نامعتبر');
		await ctx.banChatMember(target);
		logSanction(ctx.chat.id, target, 'ban', 'by admin');
		await ctx.reply('کاربر بن شد');
	});

	bot.command('lockdown', async (ctx) => {
		if (!ctx.chat) return;
		if (!(await isUserAdmin(ctx))) return;
		const arg = ctx.message.text?.split(' ')[1];
		const on = arg === 'on' ? 1 : 0;
		db.prepare('UPDATE settings SET lockdown = ? WHERE chat_id = ?').run(on, ctx.chat.id);
		await ctx.reply(on ? 'لاکدان فعال شد' : 'لاکدان غیرفعال شد');
	});

	bot.catch((err, ctx) => {
		logger.error({ err }, 'Bot error');
	});

	return bot;
}

async function isUserAdmin(ctx: Context): Promise<boolean> {
	if (!ctx.chat || !ctx.from) return false;
	try {
		const m = await ctx.getChatMember(ctx.from.id);
		return ['creator', 'administrator'].includes(m.status);
	} catch {
		return false;
	}
}

async function deleteMessageSafe(ctx: Context) {
	try {
		// @ts-ignore
		await ctx.deleteMessage(ctx.message?.message_id);
	} catch {}
}

async function warnAndMaybeMute(ctx: Context, reason: string) {
	if (!ctx.chat || !ctx.from) return;
	await deleteMessageSafe(ctx);
	addWarn(ctx.chat.id, ctx.from.id);
	const warns = getWarn(ctx.chat.id, ctx.from.id);
	const settings = getSettings(ctx.chat.id);
	await ctx.reply(`${ctx.from.first_name}: ${reason} (اخطار ${warns}/${settings.max_warns})`);
	logSanction(ctx.chat.id, ctx.from.id, 'warn', reason);
	if (warns >= settings.max_warns) {
		await ctx.restrictChatMember(ctx.from.id, { permissions: { can_send_messages: false }, until_date: Math.floor(Date.now() / 1000) + 3600 });
		logSanction(ctx.chat.id, ctx.from.id, 'mute', 'auto mute after warns', Math.floor(Date.now() / 1000) + 3600);
		await ctx.reply('کاربر به مدت ۱ ساعت میوت شد');
	}
}

async function warnUser(ctx: Context, userId: number, reason: string) {
	if (!ctx.chat) return;
	addWarn(ctx.chat.id, userId);
	const warns = getWarn(ctx.chat.id, userId);
	const settings = getSettings(ctx.chat.id);
	logSanction(ctx.chat.id, userId, 'warn', reason);
	if (warns >= settings.max_warns) {
		await ctx.restrictChatMember(userId, { permissions: { can_send_messages: false }, until_date: Math.floor(Date.now() / 1000) + 3600 });
		logSanction(ctx.chat.id, userId, 'mute', 'auto mute after warns', Math.floor(Date.now() / 1000) + 3600);
	}
}

function parseDuration(input: string): number {
	const m = input.match(/(\d+)([smhd])/i);
	if (!m) return 600;
	const value = Number(m[1]);
	switch (m[2].toLowerCase()) {
		case 's': return value;
		case 'm': return value * 60;
		case 'h': return value * 3600;
		case 'd': return value * 86400;
		default: return 600;
	}
}

async function extractTargetUserId(ctx: Context): Promise<number | null> {
	// Prefer replied user
	// @ts-ignore
	const reply = ctx.message?.reply_to_message;
	if (reply?.from?.id) return reply.from.id;
	const parts = ctx.message && 'text' in ctx.message ? ctx.message.text.split(' ') : [];
	const handle = parts[1];
	if (!handle) return null;
	if (/^\d+$/.test(handle)) return Number(handle);
	try {
		const user = await (ctx as any).telegram.getChatMember((ctx.chat as any).id, handle);
		return user.user.id;
	} catch {
		return null;
	}
}