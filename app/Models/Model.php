<?php
declare(strict_types=1);
namespace App\Models;

use App\Core\Database;

/**
 * 模型基类。一表一模型，仅做单表读写；复杂关联查询下沉到 Service。
 */
abstract class Model
{
    protected static string $table;
    protected static string $pk = 'id';

    public static function table(): string
    {
        return static::$table;
    }

    public static function find($id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$pk . '` = ? LIMIT 1',
            [$id]
        );
    }

    public static function insert(array $data): int
    {
        return Database::instance()->insert(static::$table, $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::instance()->update(static::$table, $data, static::$pk . ' = ?', [$id]);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete(static::$table, static::$pk . ' = ?', [$id]);
    }
}
