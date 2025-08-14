"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const dotenv_1 = __importDefault(require("dotenv"));
const logger_1 = require("./logger");
const web_1 = require("./web");
const bot_1 = require("./bot");
dotenv_1.default.config();
async function main() {
    const port = Number(process.env.PORT || 8080);
    (0, web_1.startWebServer)(port);
    const bot = (0, bot_1.createBot)();
    await bot.launch();
    logger_1.logger.info('Bot launched');
    process.once('SIGINT', () => bot.stop('SIGINT'));
    process.once('SIGTERM', () => bot.stop('SIGTERM'));
}
main().catch((err) => {
    logger_1.logger.error({ err }, 'Fatal error');
    process.exit(1);
});
