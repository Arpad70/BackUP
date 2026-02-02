<?php
declare(strict_types=1);
namespace BackupApp\Service;

/**
 * Minimal translator that loads PHP arrays from lang/{locale}.php
 */
class Translator
{
    private string $locale;
    private string $fallback;
    private string $path;
    private array $messages = [];

    public function __construct(string $locale = 'cs', array $opts = [])
    {
        $this->locale = $locale ?: 'cs';
        $this->fallback = $opts['fallback'] ?? 'cs';
        $this->path = $opts['path'] ?? dirname(__DIR__, 2) . '/lang';
        $this->loadMessages();
    }

    private function loadMessages(): void
    {
        $this->messages = [];
        $file = rtrim($this->path, '/') . '/' . $this->locale . '.php';
        if (is_readable($file)) {
            $data = @include $file;
            if (is_array($data)) $this->messages = $data;
        }
        // fallback
        if ($this->fallback !== $this->locale) {
            $f = rtrim($this->path, '/') . '/' . $this->fallback . '.php';
            if (is_readable($f)) {
                $d = @include $f;
                if (is_array($d)) {
                    $this->messages = array_replace($d, $this->messages);
                }
            }
        }
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function translate(string $key, array $params = []): string
    {
        $text = $this->messages[$key] ?? $key;
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $text = str_replace('%' . $k . '%', (string)$v, $text);
            }
        }
        return $text;
    }
}
