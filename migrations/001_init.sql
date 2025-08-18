-- MySQL schema for Samp Info Bot

CREATE TABLE IF NOT EXISTS users (
  id BIGINT PRIMARY KEY,
  lang VARCHAR(2) NOT NULL DEFAULT 'fa',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS force_channels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chat_id BIGINT NOT NULL,
  username VARCHAR(64),
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS sponsors (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  chat_id BIGINT NOT NULL,
  username VARCHAR(64),
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS admins (
  user_id BIGINT PRIMARY KEY,
  is_super TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS admin_permissions (
  user_id BIGINT NOT NULL,
  module ENUM('skin','vehicle','color','weather','object','weapon','map','rules','sponsor','admin','settings') NOT NULL,
  can_add TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  daily_add_limit INT DEFAULT NULL,
  PRIMARY KEY (user_id, module),
  FOREIGN KEY (user_id) REFERENCES admins(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT NOT NULL,
  action ENUM('add','edit','delete') NOT NULL,
  module VARCHAR(32) NOT NULL,
  entity_id BIGINT,
  meta JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS skins (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  `group` VARCHAR(128),
  model VARCHAR(128),
  story TEXT NULL,
  photo_file_id VARCHAR(256) NULL,
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0,
  created_by BIGINT NULL
);

CREATE TABLE IF NOT EXISTS vehicles (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  category VARCHAR(64),
  model VARCHAR(128),
  photo_file_id VARCHAR(256) NULL,
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS colors (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  hex_code CHAR(7) NOT NULL,
  swatch_file_id VARCHAR(256) NULL
);

CREATE TABLE IF NOT EXISTS weathers (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  `type` VARCHAR(64),
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS weather_photos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  weather_id INT NOT NULL,
  photo_file_id VARCHAR(256) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (weather_id) REFERENCES weathers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS objects (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  photo_file_ids JSON NULL,
  mentioned_ids JSON NULL,
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS weapons (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  description TEXT NULL,
  photo_file_id VARCHAR(256) NULL,
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS maps (
  id INT PRIMARY KEY,
  name VARCHAR(128),
  x DECIMAL(10,3), y DECIMAL(10,3), z DECIMAL(10,3),
  tags VARCHAR(256),
  photo_file_id VARCHAR(256) NULL,
  search_count INT NOT NULL DEFAULT 0,
  like_count INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS rules (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) UNIQUE NOT NULL,
  created_by BIGINT NULL
);

CREATE TABLE IF NOT EXISTS rule_translations (
  rule_id BIGINT NOT NULL,
  lang VARCHAR(2) NOT NULL,
  title VARCHAR(256) NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (rule_id, lang),
  FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS likes (
  user_id BIGINT NOT NULL,
  entity ENUM('skin','vehicle','color','weather','object','weapon','map') NOT NULL,
  entity_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, entity, entity_id)
);

CREATE TABLE IF NOT EXISTS favorites (
  user_id BIGINT NOT NULL,
  entity ENUM('skin','vehicle','color','weather','object','weapon','map') NOT NULL,
  entity_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, entity, entity_id)
);

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS cache (
  cache_key VARCHAR(128) PRIMARY KEY,
  cache_value MEDIUMTEXT NOT NULL,
  expires_at DATETIME NULL
);

