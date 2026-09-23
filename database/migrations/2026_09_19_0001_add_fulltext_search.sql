-- v2：为搜索相关表添加 FULLTEXT 索引（ngram 解析器，兼容中文分词），提升搜索召回与性能。
-- 幂等处理：MySQL 5.7 不支持 CREATE INDEX IF NOT EXISTS，故先 DROP（IF EXISTS）再 CREATE。
-- 仅作用于确认存在的列；执行失败会停止后续迁移，请先在测试库验证。

ALTER TABLE posts DROP INDEX IF EXISTS ft_posts_content;
CREATE FULLTEXT INDEX ft_posts_content ON posts (content) WITH PARSER ngram;

ALTER TABLE users DROP INDEX IF EXISTS ft_users_name;
CREATE FULLTEXT INDEX ft_users_name ON users (username, nickname) WITH PARSER ngram;

ALTER TABLE topics DROP INDEX IF EXISTS ft_topics_name;
CREATE FULLTEXT INDEX ft_topics_name ON topics (name, description) WITH PARSER ngram;

ALTER TABLE groups DROP INDEX IF EXISTS ft_groups_name;
CREATE FULLTEXT INDEX ft_groups_name ON groups (name, description) WITH PARSER ngram;
