# 数据库迁移目录

后台 **系统更新 → 数据库迁移** 会按文件名升序执行本目录下的 `*.sql`，并把已执行的文件记录到
`schema_migrations` 表（`version` = 执行时 `VERSION` 文件中的版本号，`filename` = 文件名）。
已记录的文件不会重复执行。

## 命名约定

```
2026_09_16_0001_add_index_to_posts.sql
YYYY_MM_DD_NNNN_描述.sql
```

## 编写要求

- **必须幂等**：`CREATE TABLE IF NOT EXISTS` / `ADD COLUMN` 前先判断存在性，或确保只运行一次。
  由于 MySQL 的 DDL 无法回滚，迁移执行失败会停止后续文件（已成功的文件会被记录，可修复后继续）。
- **兼容 MySQL 5.7**：不要使用 CTE、窗口函数、`JSON` 列类型、`ALTER ... RENAME COLUMN`。
- **单文件单主题**，便于出问题时定位与手工修补。
- 每条语句以 `;` 结束；解析器会忽略单引号字符串内的分号（WAF 正则等字面量安全）。

## 手动执行

不方便使用后台时，可直接导入：

```bash
mysql -uapx -p apx < database/migrations/2026_09_16_0001_xxx.sql
```

并在 `schema_migrations` 中补一条记录，避免后台重复执行：

```sql
INSERT INTO schema_migrations (version, filename, applied_at)
VALUES ('1.0.0', '2026_09_16_0001_xxx.sql', NOW());
```
