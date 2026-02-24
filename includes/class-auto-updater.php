<?php
/**
 * WordPress 外掛自動更新類別
 *
 * 從 Cloudflare Workers API 檢查更新
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Updater {

    private $plugin_slug = 'line-hub';
    private $plugin_file = 'line-hub/line-hub.php';
    private $api_url;
    private $version;
    private $cache_duration = 43200; // 12 小時

    public function __construct($version, $api_url = '') {
        $this->version = $version;

        $this->api_url = !empty($api_url)
            ? $api_url
            : 'https://buygo-plugin-updater.your-subdomain.workers.dev';

        if (defined('LINE_HUB_UPDATE_API_URL')) {
            $this->api_url = LINE_HUB_UPDATE_API_URL;
        }

        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['clear_update_cache'])) {
            delete_transient('line_hub_update_check');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>LINE Hub 更新快取已清除</p></div>';
            });
        }
    }

    /**
     * 檢查外掛更新
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $cached = get_transient('line_hub_update_check');
        if ($cached !== false) {
            if (!empty($cached['has_update'])) {
                $transient->response[$this->plugin_file] = (object) $cached['update_data'];
            }
            return $transient;
        }

        $data = $this->fetchRemoteVersion();
        if ($data === null) {
            return $transient;
        }

        if (!empty($data['up_to_date']) && $data['up_to_date'] === true) {
            set_transient('line_hub_update_check', [
                'has_update' => false, 'checked_at' => current_time('mysql'),
            ], $this->cache_duration);
            return $transient;
        }

        if (!empty($data['version']) && version_compare($this->version, $data['version'], '<')) {
            $this->applyUpdateData($transient, $data);
        }

        return $transient;
    }

    /**
     * 提供外掛詳細資訊（「查看詳情」）
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $data = $this->fetchPluginData();
        return $data !== null ? $this->buildPluginInfoObject($data) : $result;
    }

    /**
     * 手動檢查更新（開發用）
     */
    public function manual_check() {
        delete_transient('line_hub_update_check');

        $response = wp_remote_get(
            add_query_arg(['version' => $this->version, 'action' => 'update-check'], $this->api_url . '/update/' . $this->plugin_slug),
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    // ── Private helpers ──────────────────────────────────

    private function fetchRemoteVersion(): ?array {
        $response = wp_remote_get(
            add_query_arg(['version' => $this->version, 'action' => 'update-check'], $this->api_url . '/update/' . $this->plugin_slug),
            ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]
        );

        if (is_wp_error($response)) {
            error_log('LINE Hub 更新檢查失敗: ' . $response->get_error_message());
            return null;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            error_log('LINE Hub 更新 API 回應錯誤: HTTP ' . wp_remote_retrieve_response_code($response));
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data)) {
            error_log('LINE Hub 更新 API 回應格式錯誤');
            return null;
        }

        return $data;
    }

    private function applyUpdateData($transient, array $data): void {
        $update_data = [
            'slug'         => $this->plugin_slug,
            'plugin'       => $this->plugin_file,
            'new_version'  => $data['version'],
            'url'          => $data['url'] ?? $data['homepage'] ?? '',
            'package'      => $data['download_link'] ?? $data['package'] ?? '',
            'tested'       => $data['tested'] ?? '',
            'requires'     => $data['requires'] ?? '',
            'requires_php' => $data['requires_php'] ?? '',
        ];

        $transient->response[$this->plugin_file] = (object) $update_data;
        set_transient('line_hub_update_check', [
            'has_update' => true, 'update_data' => $update_data, 'checked_at' => current_time('mysql'),
        ], $this->cache_duration);

        error_log(sprintf('LINE Hub 發現新版本: %s -> %s', $this->version, $data['version']));
    }

    private function fetchPluginData(): ?array {
        $response = wp_remote_get(
            $this->api_url . '/info/' . $this->plugin_slug,
            ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($data) ? $data : null;
    }

    private function buildPluginInfoObject(array $data): object {
        return (object) [
            'name'          => $data['name'] ?? 'LINE Hub',
            'slug'          => $this->plugin_slug,
            'version'       => $data['version'] ?? $this->version,
            'author'        => $data['author'] ?? 'BuyGo Team',
            'author_profile' => $data['homepage'] ?? '',
            'requires'      => $data['requires'] ?? '6.5',
            'tested'        => $data['tested'] ?? '6.7',
            'requires_php'  => $data['requires_php'] ?? '8.2',
            'last_updated'  => $data['last_updated'] ?? '',
            'homepage'      => $data['homepage'] ?? '',
            'download_link' => $data['download_url'] ?? '',
            'sections'      => [
                'description' => $data['description'] ?? 'WordPress 的 LINE 整合中樞 - 提供 LINE 登入、訊息通知、Webhook 管理',
                'changelog'   => $this->get_changelog($data),
            ],
            'banners' => $data['banners'] ?? [],
            'icons'   => $data['icons'] ?? [],
        ];
    }

    private function get_changelog($data) {
        if (!empty($data['sections']['changelog'])) {
            return $data['sections']['changelog'];
        }

        $version = $data['version'] ?? $this->version;
        $release_url = $data['release_url'] ?? "https://github.com/fishtvlvoe/line-hub/releases/tag/v{$version}";

        return sprintf(
            '<h4>v%s</h4><p>詳細更新內容請查看 <a href="%s" target="_blank">GitHub Releases</a></p>',
            esc_html($version),
            esc_url($release_url)
        );
    }
}
