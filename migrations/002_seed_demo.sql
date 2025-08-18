INSERT INTO skins (id, name, `group`, model, story, photo_file_id, search_count, like_count)
VALUES (150, 'Grove Member', 'Grove Street', 'm_y_grove_01', 'عضو Grove Street', NULL, 0, 0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

