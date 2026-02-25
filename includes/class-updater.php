<?php
/**
 * Plugin Updater
 *
 * Uses yahnis-elsts/plugin-update-checker to auto-update from GitHub Releases
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {

    private const GITHUB_REPO = 'https://github.com/fishtvlvoe/line-hub';

    private $update_checker;
    private $plugin_file;

    public function __construct(string $plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->init_update_checker();
    }

    private function init_update_checker(): void {
        $autoload_path = dirname($this->plugin_file) . '/vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            return;
        }

        require_once $autoload_path;

        try {
            $this->update_checker = PucFactory::buildUpdateChecker(
                self::GITHUB_REPO,
                $this->plugin_file,
                'line-hub'
            );

            $this->update_checker->setBranch('master');
            $this->update_checker->getVcsApi()->enableReleaseAssets();

        } catch (\Exception $e) {
            if (function_exists('error_log')) {
                error_log('LINE Hub updater init failed: ' . $e->getMessage());
            }
        }
    }
}
