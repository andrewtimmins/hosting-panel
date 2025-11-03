<?php
namespace App\Services;

use App\Database\Connection;
use App\Support\CommandRunner;
use PDO;
use RuntimeException;

class ServiceControlService
{
    private PDO $db;
    private array $allowedCommands;
    private CommandRunner $runner;
    private ?array $dbConfig = null;

    public function __construct(array $dbConfig, array $allowedCommands, ?CommandRunner $runner = null)
    {
        $this->dbConfig = $dbConfig;
        $this->db = Connection::get($dbConfig);
        $this->allowedCommands = $allowedCommands;
        $this->runner = $runner ?? new CommandRunner();
    }

    public function run(string $commandKey): array
    {
        if (!isset($this->allowedCommands[$commandKey])) {
            throw new RuntimeException('Command not allowed');
        }

        $command = $this->allowedCommands[$commandKey];
        $result = $this->runner->run($command);

        // Log the action with retry on connection failure
        $maxRetries = 2;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                $stmt = $this->db->prepare('INSERT INTO actions_log (command_key, command, status, exit_code, stdout, stderr, executed_at) VALUES (:command_key, :command, :status, :exit_code, :stdout, :stderr, NOW())');
                $stmt->execute([
                    ':command_key' => $commandKey,
                    ':command' => $command,
                    ':status' => $result['exit_code'] === 0 ? 'success' : 'failure',
                    ':exit_code' => $result['exit_code'],
                    ':stdout' => $result['stdout'],
                    ':stderr' => $result['stderr'],
                ]);
                break; // Success, exit loop
            } catch (\PDOException $e) {
                $attempt++;
                
                // Check if it's a "MySQL server has gone away" error
                if (str_contains($e->getMessage(), 'gone away') || str_contains($e->getMessage(), 'Lost connection')) {
                    if ($attempt < $maxRetries) {
                        // Reconnect and retry
                        Connection::reconnect();
                        $this->db = Connection::get($this->getDbConfig());
                        continue;
                    }
                }
                
                // Re-throw if not a connection error or max retries exceeded
                throw $e;
            }
        }

        return [
            'exit_code' => $result['exit_code'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'success' => $result['exit_code'] === 0,
        ];
    }
    
    private function getDbConfig(): array
    {
        return $this->dbConfig ?? [];
    }

    public function history(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT command_key, command, status, executed_at FROM actions_log ORDER BY executed_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map(function (array $row): array {
            return [
                'command_key' => $row['command_key'],
                'command_label' => $this->commandLabel($row['command_key']),
                'status' => $row['status'],
                'executed_at' => $row['executed_at'],
            ];
        }, $rows);
    }

    private function commandLabel(string $commandKey): string
    {
        return match ($commandKey) {
            'nginx_reload' => 'Reload NGINX',
            'nginx_restart' => 'Restart NGINX',
            'php_fpm_restart' => 'Restart PHP-FPM',
            'php_fpm_reload' => 'Reload PHP-FPM',
            'mysql_restart' => 'Restart MySQL',
            'mysql_reload' => 'Reload MySQL',
            'powerdns_restart' => 'Restart PowerDNS',
            'powerdns_reload' => 'Reload PowerDNS',
            default => ucfirst(str_replace('_', ' ', $commandKey)),
        };
    }
}
