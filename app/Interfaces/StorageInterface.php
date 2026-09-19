<?php
declare(strict_types=1);
namespace App\Interfaces;

/**
 * 文件存储契约。实现方须完成 MIME/扩展名双校验、随机文件名与目录穿越防护。
 */
interface StorageInterface
{
    /** 保存上传文件，返回 ['type','path','thumb','w','h','duration']。 */
    public static function store(array $file, string $kind = 'post'): array;

    /** 删除已保存的文件（相对 uploads 的路径），返回是否删除成功。 */
    public static function remove(string $path): bool;
}
