<?php
/**
 * MessagingService 單元測試
 *
 * @package LineHub\Tests
 */

namespace LineHub\Tests\Unit\Messaging;

use LineHub\Messaging\MessagingService;
use LineHub\Services\SettingsService;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class MessagingServiceTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        $GLOBALS['mock_transients'] = [];
        $GLOBALS['mock_wpdb_results'] = [];
        $GLOBALS['mock_http_responses'] = [];
    }

    /**
     * 建立無 token 的 MessagingService
     * （constructor 讀取 SettingsService::get('general','access_token')，預設空）
     */
    private function createServiceWithoutToken(): MessagingService {
        // SettingsService::get 會先查 transient cache → 查 DB
        // 預設都回傳空，所以 access_token = '' (default)
        return new MessagingService();
    }

    /**
     * 建立有 token 的 MessagingService
     */
    private function createServiceWithToken(string $token = 'test-token-123'): MessagingService {
        // 透過 transient cache 注入 token
        $GLOBALS['mock_transients']['line_hub_setting_general_access_token'] = $token;
        return new MessagingService();
    }

    // ── pushText ──────────────────────────────────────────

    public function test_pushText_returns_error_without_token() {
        $service = $this->createServiceWithoutToken();
        $result = $service->pushText(1, 'Hello');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('no_channel_access_token', $result->get_error_code());
    }

    public function test_pushText_returns_error_for_empty_text() {
        $service = $this->createServiceWithToken();
        $result = $service->pushText(1, '   ');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('empty_text', $result->get_error_code());
    }

    // ── pushMessage ───────────────────────────────────────

    public function test_pushMessage_returns_error_for_unbound_user() {
        $service = $this->createServiceWithToken();
        // UserService::getLineUid → getBinding → get_row 回傳 null
        // NSL 表不存在
        $GLOBALS['mock_wpdb_results'] = [null, 0];

        $result = $service->pushMessage(999, [['type' => 'text', 'text' => 'Hello']]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('no_line_binding', $result->get_error_code());
    }

    public function test_pushMessage_validates_message_type() {
        $service = $this->createServiceWithToken();
        // UserService 回傳有綁定
        $binding = (object) [
            'id' => 1, 'user_id' => 10, 'line_uid' => 'Uabc123',
            'display_name' => 'Test', 'picture_url' => null,
            'email' => null, 'email_verified' => 0,
            'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];
        $GLOBALS['mock_wpdb_results'] = [$binding];

        // 傳入缺少 type 的訊息
        $result = $service->pushMessage(10, [['text' => 'Hello']]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_message_type', $result->get_error_code());
    }

    // ── multicast ─────────────────────────────────────────

    public function test_multicast_rejects_over_500_recipients() {
        $service = $this->createServiceWithToken();
        $userIds = range(1, 501);
        $messages = [['type' => 'text', 'text' => 'Broadcast']];

        $result = $service->multicast($userIds, $messages);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('too_many_recipients', $result->get_error_code());
    }

    public function test_multicast_returns_error_when_all_unbound() {
        $service = $this->createServiceWithToken();
        // 3 個用戶，每個 getLineUid 查詢都回傳 null（未綁定）
        // 每次 getLineUid → getBinding → get_row(null) + NSL check(0)
        $GLOBALS['mock_wpdb_results'] = [null, 0, null, 0, null, 0];

        $result = $service->multicast([1, 2, 3], [['type' => 'text', 'text' => 'Hi']]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('no_line_users', $result->get_error_code());
    }

    // ── replyMessage ──────────────────────────────────────

    public function test_replyMessage_returns_error_for_empty_token() {
        $service = $this->createServiceWithToken();

        $result = $service->replyMessage('', [['type' => 'text', 'text' => 'Reply']]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_reply_token', $result->get_error_code());
    }
}
