<?php
/**
 * SettingsService 單元測試
 *
 * @package LineHub\Tests
 */

namespace LineHub\Tests\Unit\Services;

use LineHub\Services\SettingsService;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class SettingsServiceTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        $GLOBALS['mock_transients'] = [];
        $GLOBALS['mock_wpdb_results'] = [];
    }

    // ── 加密 / 解密 ──────────────────────────────────────

    public function test_encrypt_decrypt_roundtrip() {
        $original = 'my-secret-channel-token-12345';
        $encrypted = SettingsService::encrypt($original);

        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($original, $encrypted);

        $decrypted = SettingsService::decrypt($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypt_empty_returns_empty() {
        $this->assertEquals('', SettingsService::encrypt(''));
    }

    public function test_decrypt_invalid_returns_empty() {
        // 完全非法的 base64（base64_decode 回傳 false）
        $this->assertEquals('', SettingsService::decrypt("\x00\x01\x02"));
    }

    public function test_decrypt_empty_returns_empty() {
        $this->assertEquals('', SettingsService::decrypt(''));
    }

    // ── get（Schema 驗證）─────────────────────────────────

    public function test_get_returns_default_for_unknown_group() {
        $result = SettingsService::get('nonexistent_group', 'some_key', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    public function test_get_returns_default_for_unknown_key() {
        $result = SettingsService::get('general', 'nonexistent_key', 'fallback');
        $this->assertEquals('fallback', $result);
    }

    // ── set（Schema 驗證）─────────────────────────────────

    public function test_set_returns_false_for_unknown_key() {
        $result = SettingsService::set('general', 'nonexistent_key', 'value');
        $this->assertFalse($result);
    }

    public function test_set_returns_false_for_unknown_group() {
        $result = SettingsService::set('nonexistent_group', 'key', 'value');
        $this->assertFalse($result);
    }

    // ── Transient 快取 ────────────────────────────────────

    public function test_get_uses_transient_cache() {
        // 預設 transient 有值
        $GLOBALS['mock_transients']['line_hub_setting_general_channel_id'] = 'cached-value';

        $result = SettingsService::get('general', 'channel_id');
        $this->assertEquals('cached-value', $result);
    }

    public function test_clear_cache_removes_transient() {
        $GLOBALS['mock_transients']['line_hub_setting_general_channel_id'] = 'cached-value';

        SettingsService::clear_cache('general', 'channel_id');

        $this->assertArrayNotHasKey('line_hub_setting_general_channel_id', $GLOBALS['mock_transients']);
    }
}
