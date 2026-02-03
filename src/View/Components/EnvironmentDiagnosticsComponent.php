<?php
declare(strict_types=1);
namespace BackupApp\View\Components;

use BackupApp\Service\Translator;

/**
 * Environment Diagnostics Component
 * 
 * Render environment check diagnostics (replaces duplicated code in 3 views)
 * Eliminates ~240 lines of duplicated HTML template code
 */
class EnvironmentDiagnosticsComponent
{
    public const DIAGNOSTICS_MAPPING = [
        'mysqldump' => [
            'required' => true,
            'icon_success' => '✅',
            'icon_fail' => '❌',
            'value_key' => 'ok',
            'status_required' => true
        ],
        'zip_ext' => [
            'required' => true,
            'icon_success' => '✅',
            'icon_fail' => '❌',
            'value_key' => 'ok',
            'status_required' => true
        ],
        'phpseclib' => [
            'required' => false,
            'icon_success' => '✅',
            'icon_fail' => '⚠️',
            'value_key' => 'available',
            'status_required' => false
        ],
        'ssh2_ext' => [
            'required' => false,
            'icon_success' => '✅',
            'icon_fail' => '⚠️',
            'value_key' => 'available',
            'status_required' => false
        ],
        'tmp_writable' => [
            'required' => true,
            'icon_success' => '✅',
            'icon_fail' => '❌',
            'value_key' => 'yes',
            'status_required' => true
        ]
    ];

    /**
     * Render environment diagnostics HTML
     * 
     * @param array<string,bool> $env Environment check results
     * @param Translator $translator Translation service
     * @return string HTML output
     */
    public static function render(array $env, Translator $translator): string
    {
        $html = '<div class="p-3 rounded mb-3 section-environment">' . "\n";
        $html .= '  <h5 class="mb-3">' . htmlspecialchars($translator->translate('environment_diagnostics')) . '</h5>' . "\n";
        $html .= '  <div class="row g-3">' . "\n";

        foreach (self::DIAGNOSTICS_MAPPING as $key => $config) {
            $isOk = isset($env[$key]) && $env[$key] === true;
            $icon = $isOk ? $config['icon_success'] : $config['icon_fail'];
            $badgeClass = $isOk ? 'bg-success' : ($config['required'] ? 'bg-danger' : 'bg-warning');
            
            $statusLabel = $config['status_required'] 
                ? $translator->translate('env_status_required')
                : $translator->translate('env_status_recommended');
            
            $valueLabel = $translator->translate($config['value_key']);
            $descLabel = $translator->translate('env_' . $key . '_desc');
            $envLabel = $translator->translate('env_' . $key);

            $html .= self::renderDiagnosticItem(
                $key,
                $icon,
                $envLabel,
                $descLabel,
                $statusLabel,
                $badgeClass,
                $valueLabel
            );
        }

        $html .= '  </div>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    /**
     * Render single diagnostic item
     */
    private static function renderDiagnosticItem(
        string $key,
        string $icon,
        string $label,
        string $description,
        string $statusLabel,
        string $badgeClass,
        string $valueLabel
    ): string {
        return <<<HTML
    <!-- {$key} -->
    <div class="col-md-6">
      <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
        <span style="font-size: 1.5rem; min-width: 2rem;">
          {$icon}
        </span>
        <div style="flex: 1;">
          <strong data-tooltip="{$description}">{$label}</strong>
          <div class="small text-muted mt-1">{$statusLabel}</div>
          <span class="badge {$badgeClass} mt-2">
            {$valueLabel}
          </span>
        </div>
      </div>
    </div>

HTML;
    }
}
