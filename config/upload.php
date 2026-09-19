<?php
declare(strict_types=1);
/**
 * APX - 上传配置
 * 所有大小以字节计；视频仅存原文件不转码。
 */
return [
    'base_dir' => __DIR__ . '/../storage/uploads',
    'base_url' => '/uploads',
    'image_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'image_ext'  => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'video_mime' => ['video/mp4', 'video/webm', 'video/quicktime'],
    'video_ext'  => ['mp4', 'webm', 'mov'],
    'file_ext'   => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'rar', '7z'],
    'max_size' => [
        'avatar' => 5 * 1024 * 1024,
        'cover'  => 10 * 1024 * 1024,
        'image'  => 10 * 1024 * 1024,
        'video'  => 100 * 1024 * 1024,
        'file'   => 50 * 1024 * 1024,
    ],
    'image_sizes' => [
        'avatar'  => ['w' => 256, 'h' => 256, 'crop' => true],
        'cover'   => ['w' => 1280, 'h' => 360, 'crop' => true],
        'thumb'   => ['w' => 400, 'h' => 400, 'crop' => false],
        'preview' => ['w' => 1080, 'h' => 1080, 'crop' => false],
    ],
    'allowed_drivers' => ['local'],
];
