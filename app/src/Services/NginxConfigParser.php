<?php
namespace App\Services;

class NginxConfigParser
{
    public function parse(string $config): array
    {
        $stripped = $this->stripComments($config);
        $serverBlock = $this->extractFirstServerBlock($stripped);

        if ($serverBlock === null) {
            return [
                'raw' => $config,
                'server_block' => null,
                'server_names' => [],
                'listen' => [],
            ];
        }

        $parsed = [
            'raw' => $config,
            'server_block' => $serverBlock,
            'server_names' => $this->parseServerNames($serverBlock),
            'listen' => $this->parseListen($serverBlock),
            'root' => $this->parseRoot($serverBlock),
            'index' => $this->parseIndex($serverBlock),
            'fastcgi_pass' => $this->parseFastCgi($serverBlock),
            'ssl_certificate' => $this->parseDirective($serverBlock, 'ssl_certificate'),
            'ssl_certificate_key' => $this->parseDirective($serverBlock, 'ssl_certificate_key'),
            'ssl_includes' => $this->parseSslIncludes($serverBlock),
            'ssl' => $this->detectSsl($serverBlock),
        ];

        $parsed['server_name'] = $parsed['server_names'][0] ?? null;

        return $parsed;
    }

    private function stripComments(string $config): string
    {
        return preg_replace('/#.*$/m', '', $config) ?? $config;
    }

    private function extractFirstServerBlock(string $config): ?string
    {
        $pattern = '/server\s*\{/i';
        if (!preg_match($pattern, $config, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $match[0][1];
        $depth = 0;
        $length = strlen($config);
        for ($i = $offset; $i < $length; $i++) {
            if ($config[$i] === '{') {
                $depth++;
            } elseif ($config[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($config, $offset, $i - $offset + 1);
                }
            }
        }

        return null;
    }

    private function parseServerNames(string $block): array
    {
        if (!preg_match_all('/server_name\s+([^;]+);/i', $block, $matches)) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $group) {
            $parts = preg_split('/\s+/', trim($group)) ?: [];
            $names = array_merge($names, $parts);
        }

        return array_values(array_unique(array_filter($names)));
    }

    private function parseListen(string $block): array
    {
        if (!preg_match_all('/listen\s+([^;]+);/i', $block, $matches)) {
            return [];
        }

        $directives = array_map(static fn ($value) => trim(preg_replace('/\s+/', ' ', $value)), $matches[1]);
        return array_values(array_unique($directives));
    }

    private function parseRoot(string $block): ?string
    {
        if (!preg_match_all('/(^|\s)root\s+([^;]+);/im', $block, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Prefer root directive that is not inside a location block by checking preceding text
        foreach ($matches[2] as $match) {
            $offset = $match[1];
            $before = substr($block, 0, $offset);
            $lastLocation = strrpos($before, 'location');
            $lastBrace = strrpos($before, '{');
            if ($lastLocation === false || $lastBrace === false || $lastLocation < $lastBrace) {
                return trim($match[0]);
            }
        }

        // Fallback to first match
        return trim($matches[2][0][0]);
    }

    private function parseIndex(string $block): ?array
    {
        if (!preg_match('/index\s+([^;]+);/i', $block, $match)) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($match[1])) ?: [];
        return array_values(array_filter($parts));
    }

    private function parseFastCgi(string $block): ?string
    {
        if (!preg_match('/fastcgi_pass\s+([^;]+);/i', $block, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function parseDirective(string $block, string $directive): ?string
    {
        if (!preg_match('/' . preg_quote($directive, '/') . '\s+([^;]+);/i', $block, $match)) {
            return null;
        }
        return trim($match[1]);
    }

    private function parseSslIncludes(string $block): array
    {
        if (!preg_match_all('/include\s+([^;]+);/i', $block, $matches)) {
            return [];
        }

        $includes = [];
        foreach ($matches[1] as $include) {
            $include = trim($include);
            if (stripos($include, 'ssl') !== false) {
                $includes[] = $include;
            }
        }

        return array_values(array_unique($includes));
    }

    private function detectSsl(string $block): bool
    {
        foreach ($this->parseListen($block) as $listen) {
            if (str_contains($listen, 'ssl') || str_contains($listen, '443')) {
                return true;
            }
        }
        return false;
    }
}
