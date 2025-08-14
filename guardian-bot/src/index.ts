import dotenv from 'dotenv';
import { logger } from './logger';
import { startWebServer } from './web';
import { createBot } from './bot';

dotenv.config();

async function main(){
	const port = Number(process.env.PORT || 8080);
	startWebServer(port);
	const bot = createBot();
	await bot.launch();
	logger.info('Bot launched');

	process.once('SIGINT', () => bot.stop('SIGINT'));
	process.once('SIGTERM', () => bot.stop('SIGTERM'));
}

main().catch((err)=>{
	logger.error({ err }, 'Fatal error');
	process.exit(1);
});