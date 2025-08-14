CREATE TABLE IF NOT EXISTS groups (
	chat_id BIGINT PRIMARY KEY,
	type VARCHAR(32) NOT NULL,
	title VARCHAR(255) NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
	user_id BIGINT,
	chat_id BIGINT,
	first_name VARCHAR(255) NULL,
	last_name VARCHAR(255) NULL,
	username VARCHAR(255) NULL,
	is_admin TINYINT DEFAULT 0,
	warn_count INT DEFAULT 0,
	PRIMARY KEY (user_id, chat_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
	chat_id BIGINT PRIMARY KEY,
	anti_link TINYINT DEFAULT 1,
	anti_forward TINYINT DEFAULT 1,
	anti_badwords TINYINT DEFAULT 1,
	captcha_required TINYINT DEFAULT 1,
	max_warns INT DEFAULT 3,
	lockdown TINYINT DEFAULT 0,
	welcome_banner_url VARCHAR(512) NULL,
	welcome_text VARCHAR(512) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sanctions (
	id BIGINT AUTO_INCREMENT PRIMARY KEY,
	chat_id BIGINT,
	user_id BIGINT,
	action VARCHAR(32),
	reason VARCHAR(512) NULL,
	expires_at INT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	INDEX (chat_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_actions (
	chat_id BIGINT,
	admin_id BIGINT,
	day CHAR(8),
	deletions INT DEFAULT 0,
	PRIMARY KEY (chat_id, admin_id, day)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bot_state (
	id TINYINT PRIMARY KEY DEFAULT 1,
	disabled TINYINT DEFAULT 0,
	force_channel_id BIGINT DEFAULT 0
) ENGINE=InnoDB;