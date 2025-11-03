<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class FileManagerService
{
    private string $baseDir;
    private array $allowedExtensions;
    private int $maxUploadSize;

    public function __construct(
        string $baseDir = '/websites',
        array $allowedExtensions = ['php', 'html', 'css', 'js', 'txt', 'json', 'xml', 'md', 'htaccess', 'conf'],
        int $maxUploadSize = 10485760 // 10MB
    ) {
        $this->baseDir = rtrim($baseDir, '/');
        $this->allowedExtensions = $allowedExtensions;
        $this->maxUploadSize = $maxUploadSize;
    }

    /**
     * List directory contents
     */
    public function listDirectory(string $path = '/'): array
    {
        $fullPath = $this->getFullPath($path);
        
        if (!is_dir($fullPath)) {
            throw new RuntimeException("Directory not found: {$path}");
        }

        $items = [];
        $files = scandir($fullPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $file;
            $relativePath = $this->getRelativePath($itemPath);

            $stat = stat($itemPath);
            $owner = posix_getpwuid($stat['uid']);
            $group = posix_getgrgid($stat['gid']);
            
            $items[] = [
                'name' => $file,
                'path' => $relativePath,
                'type' => is_dir($itemPath) ? 'directory' : 'file',
                'size' => is_file($itemPath) ? filesize($itemPath) : 0,
                'modified' => filemtime($itemPath),
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4),
                'owner' => $owner['name'] ?? $stat['uid'],
                'group' => $group['name'] ?? $stat['gid'],
                'is_readable' => is_readable($itemPath),
                'is_writable' => is_writable($itemPath),
                'extension' => is_file($itemPath) ? pathinfo($file, PATHINFO_EXTENSION) : null
            ];
        }

        // Sort: directories first, then files
        usort($items, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'directory' ? -1 : 1;
        });

        return $items;
    }

    /**
     * Read file contents
     */
    public function readFile(string $path): array
    {
        $fullPath = $this->getFullPath($path);

        if (!is_file($fullPath)) {
            throw new RuntimeException("File not found: {$path}");
        }

        if (!is_readable($fullPath)) {
            throw new RuntimeException("File is not readable: {$path}");
        }

        $size = filesize($fullPath);
        if ($size > 5242880) { // 5MB
            throw new RuntimeException("File too large to edit (max 5MB)");
        }

        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }

        return [
            'path' => $path,
            'name' => basename($fullPath),
            'contents' => $contents,
            'size' => $size,
            'modified' => filemtime($fullPath),
            'permissions' => substr(sprintf('%o', fileperms($fullPath)), -4)
        ];
    }

    /**
     * Write file contents
     */
    public function writeFile(string $path, string $contents): array
    {
        $fullPath = $this->getFullPath($path);

        if (!is_writable(dirname($fullPath))) {
            throw new RuntimeException("Directory is not writable");
        }

        $result = file_put_contents($fullPath, $contents);
        if ($result === false) {
            throw new RuntimeException("Failed to write file: {$path}");
        }

        return [
            'path' => $path,
            'size' => $result,
            'modified' => filemtime($fullPath)
        ];
    }

    /**
     * Create new file
     */
    public function createFile(string $path, string $contents = ''): array
    {
        $fullPath = $this->getFullPath($path);

        if (file_exists($fullPath)) {
            throw new RuntimeException("File already exists: {$path}");
        }

        return $this->writeFile($path, $contents);
    }

    /**
     * Create new directory
     */
    public function createDirectory(string $path): array
    {
        $fullPath = $this->getFullPath($path);

        if (file_exists($fullPath)) {
            throw new RuntimeException("Directory already exists: {$path}");
        }

        if (!mkdir($fullPath, 0755, true)) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }

        return [
            'path' => $path,
            'created' => true
        ];
    }

    /**
     * Delete file or directory
     */
    public function delete(string $path): array
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Path not found: {$path}");
        }

        if (is_dir($fullPath)) {
            $this->deleteDirectory($fullPath);
        } else {
            if (!unlink($fullPath)) {
                throw new RuntimeException("Failed to delete file: {$path}");
            }
        }

        return ['deleted' => true];
    }

    /**
     * Rename/move file or directory
     */
    public function rename(string $oldPath, string $newPath): array
    {
        $oldFullPath = $this->getFullPath($oldPath);
        $newFullPath = $this->getFullPath($newPath);

        if (!file_exists($oldFullPath)) {
            throw new RuntimeException("Source not found: {$oldPath}");
        }

        if (file_exists($newFullPath)) {
            throw new RuntimeException("Destination already exists: {$newPath}");
        }

        if (!rename($oldFullPath, $newFullPath)) {
            throw new RuntimeException("Failed to rename: {$oldPath}");
        }

        return [
            'old_path' => $oldPath,
            'new_path' => $newPath
        ];
    }

    /**
     * Upload file
     */
    public function uploadFile(string $targetPath, array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException("Invalid upload");
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload error: " . $this->getUploadError($file['error']));
        }

        if ($file['size'] > $this->maxUploadSize) {
            throw new RuntimeException("File too large (max " . ($this->maxUploadSize / 1024 / 1024) . "MB)");
        }

        $fullPath = $this->getFullPath($targetPath . '/' . $file['name']);

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException("Failed to save uploaded file");
        }

        return [
            'path' => $this->getRelativePath($fullPath),
            'name' => $file['name'],
            'size' => $file['size']
        ];
    }

    /**
     * Get file download path
     */
    public function getDownloadPath(string $path): string
    {
        $fullPath = $this->getFullPath($path);

        if (!is_file($fullPath)) {
            throw new RuntimeException("File not found: {$path}");
        }

        if (!is_readable($fullPath)) {
            throw new RuntimeException("File is not readable");
        }

        return $fullPath;
    }

    /**
     * Create zip archive of file or directory
     */
    public function createZip(string $path): array
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Path not found: {$path}");
        }

        // Create temp zip file
        $zipName = basename($fullPath) . '_' . date('YmdHis') . '.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create zip archive");
        }

        if (is_file($fullPath)) {
            // Add single file
            $zip->addFile($fullPath, basename($fullPath));
        } else {
            // Add directory recursively
            $this->addDirectoryToZip($zip, $fullPath, basename($fullPath));
        }

        $zip->close();

        return [
            'zip_path' => $zipPath,
            'zip_name' => $zipName,
            'size' => filesize($zipPath)
        ];
    }

    /**
     * Recursively add directory to zip
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $zipPath): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            $localPath = $zipPath . '/' . $file;

            if (is_dir($fullPath)) {
                $zip->addEmptyDir($localPath);
                $this->addDirectoryToZip($zip, $fullPath, $localPath);
            } else {
                $zip->addFile($fullPath, $localPath);
            }
        }
    }

    /**
     * Change file permissions
     */
    public function chmod(string $path, string $permissions): array
    {
        $fullPath = $this->getFullPath($path);

        if (!file_exists($fullPath)) {
            throw new RuntimeException("Path not found: {$path}");
        }

        $mode = octdec($permissions);
        if (!chmod($fullPath, $mode)) {
            throw new RuntimeException("Failed to change permissions");
        }

        return [
            'path' => $path,
            'permissions' => $permissions
        ];
    }

    /**
     * Search files
     */
    public function search(string $directory, string $query, bool $caseSensitive = false): array
    {
        $fullPath = $this->getFullPath($directory);
        $results = [];
        
        $this->searchRecursive($fullPath, $query, $caseSensitive, $results);
        
        return $results;
    }

    /**
     * Get full filesystem path
     */
    private function getFullPath(string $path): string
    {
        $path = '/' . trim($path, '/');
        $fullPath = $this->baseDir . $path;
        
        // Prevent directory traversal
        $realBase = realpath($this->baseDir);
        $realPath = realpath($fullPath);
        
        if ($realPath === false) {
            // Path doesn't exist yet, check parent
            $realPath = realpath(dirname($fullPath));
            if ($realPath === false || strpos($realPath, $realBase) !== 0) {
                throw new RuntimeException("Invalid path");
            }
            return $fullPath;
        }
        
        if (strpos($realPath, $realBase) !== 0) {
            throw new RuntimeException("Access denied: path outside base directory");
        }

        return $realPath;
    }

    /**
     * Get relative path from full path
     */
    private function getRelativePath(string $fullPath): string
    {
        return str_replace($this->baseDir, '', $fullPath);
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    /**
     * Recursive search
     */
    private function searchRecursive(string $dir, string $query, bool $caseSensitive, array &$results): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            $matches = $caseSensitive 
                ? strpos($file, $query) !== false
                : stripos($file, $query) !== false;

            if ($matches) {
                $results[] = [
                    'name' => $file,
                    'path' => $this->getRelativePath($path),
                    'type' => is_dir($path) ? 'directory' : 'file'
                ];
            }

            if (is_dir($path)) {
                $this->searchRecursive($path, $query, $caseSensitive, $results);
            }
        }
    }

    /**
     * Get upload error message
     */
    private function getUploadError(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
}
