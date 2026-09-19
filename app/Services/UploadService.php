<?php
declare(strict_types=1);
namespace App\Services;

use App\Interfaces\StorageInterface;
use RuntimeException;

/**
 * 媒体上传：GD 重编码剥离 EXIF、生成缩略图；视频原样存储不转码。
 * 双校验 MIME + 扩展名、随机文件名、落盘前 realpath 前缀校验防穿越。
 */
class UploadService implements StorageInterface
{
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];

    public static function store(array $file, string $kind = 'post'): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('upload.error');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('upload.invalid');
        }
        $cfg = \App\Core\Config::get('upload');
        $maxSize = (int) ($cfg['max_size'] ?? 8 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            throw new RuntimeException('upload.too_large');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($mime, self::IMAGE_TYPES, true) && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return self::storeImage($file['tmp_name'], $mime, $ext);
        }
        if (in_array($mime, self::VIDEO_TYPES, true) && in_array($ext, ['mp4', 'webm', 'mov', 'mkv'], true)) {
            return self::storeVideo($file['tmp_name'], $ext);
        }
        throw new RuntimeException('upload.unsupported');
    }

    private static function baseDir(): string
    {
        $dir = rtrim(APP_ROOT, '/') . '/public/assets/uploads/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('upload.dir');
        }
        return $dir;
    }

    private static function safeName(string $ext): string
    {
        return bin2hex(random_bytes(12)) . '.' . $ext;
    }

    private static function storeImage(string $tmp, string $mime, string $ext): array
    {
        $src = self::loadImage($tmp, $mime);
        if (!$src) {
            throw new RuntimeException('upload.decode');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $dir = self::baseDir();
        $name = self::safeName($ext === 'jpeg' ? 'jpg' : $ext);
        $path = $dir . '/' . $name;

        // 重编码原图（剥离 EXIF），最长边限制 1600
        $max = 1600;
        if ($w > $max || $h > $max) {
            $ratio = $max / max($w, $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            $w = $nw;
            $h = $nh;
        }
        self::saveImage($src, $path, $mime);

        // 缩略图 400x400 等比
        $tw = 400;
        $th = 400;
        $ratio = min($tw / $w, $th / $h);
        $cw = (int) round($w * $ratio);
        $ch = (int) round($h * $ratio);
        $thumb = imagecreatetruecolor($cw, $ch);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $cw, $ch, $w, $h);
        $thumbName = 'th_' . $name;
        self::saveImage($thumb, $dir . '/' . $thumbName, 'image/jpeg');
        imagedestroy($src);
        imagedestroy($thumb);

        return [
            'type' => 'image',
            'path' => self::rel($path),
            'thumb' => self::rel($dir . '/' . $thumbName),
            'w' => $w,
            'h' => $h,
            'duration' => null,
        ];
    }

    private static function storeVideo(string $tmp, string $ext): array
    {
        $dir = self::baseDir();
        $name = self::safeName($ext);
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $path)) {
            throw new RuntimeException('upload.move');
        }
        return [
            'type' => 'video',
            'path' => self::rel($path),
            'thumb' => null,
            'w' => null,
            'h' => null,
            'duration' => null,
        ];
    }

    private static function loadImage(string $tmp, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($tmp);
            case 'image/png':  return @imagecreatefrompng($tmp);
            case 'image/gif':  return @imagecreatefromgif($tmp);
            case 'image/webp': return @imagecreatefromwebp($tmp);
            default: return false;
        }
    }

    private static function saveImage($img, string $path, string $mime): void
    {
        switch ($mime) {
            case 'image/png':  imagepng($img, $path, 8); break;
            case 'image/gif':  imagegif($img, $path); break;
            case 'image/webp': imagewebp($img, $path, 82); break;
            default:           imagejpeg($img, $path, 82); break;
        }
    }

    private static function rel(string $abs): string
    {
        $base = rtrim(APP_ROOT, '/') . '/public/assets/uploads/';
        return ltrim(str_replace('\\', '/', substr($abs, strlen($base))), '/');
    }

    /**
     * 删除已保存的文件（相对 uploads 的路径）。带 realpath 前缀校验，杜绝路径穿越。
     */
    public static function remove(string $path): bool
    {
        $base = realpath(APP_ROOT . '/public/assets/uploads');
        if ($base === false) {
            return false;
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return false;
        }
        $target = realpath($base . '/' . $rel);
        if ($target === false
            || strncmp($target, $base . DIRECTORY_SEPARATOR, strlen($base . DIRECTORY_SEPARATOR)) !== 0
            || !is_file($target)) {
            return false;
        }
        return @unlink($target);
    }
}
