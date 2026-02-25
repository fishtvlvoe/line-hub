<?php
/**
 * UserService 單元測試
 *
 * @package LineHub\Tests
 */

namespace LineHub\Tests\Unit\Services;

use LineHub\Services\UserService;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class UserServiceTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        $GLOBALS['mock_transients'] = [];
        $GLOBALS['mock_wpdb_results'] = [];

        // 重置 NSL columns 靜態快取（PHP 8.1+ property 預設 accessible）
        $ref = new \ReflectionClass(UserService::class);
        $prop = $ref->getProperty('nsl_columns');
        $prop->setValue(null, null);
    }

    // ── getUserByLineUid ──────────────────────────────────

    public function test_getUserByLineUid_returns_user_id() {
        // 第一個 get_var 回傳 user_id = 42
        $GLOBALS['mock_wpdb_results'] = [42];

        $result = UserService::getUserByLineUid('U1234567890abcdef');
        $this->assertEquals(42, $result);
    }

    public function test_getUserByLineUid_returns_null_when_not_found() {
        // 第一個 get_var 回傳 null（LINE Hub 表無結果）
        // 第二個 get_var 回傳 0（NSL 表不存在）
        $GLOBALS['mock_wpdb_results'] = [null, 0];

        $result = UserService::getUserByLineUid('U_nonexistent');
        $this->assertNull($result);
    }

    // ── isLinked ──────────────────────────────────────────

    public function test_isLinked_returns_true_when_bound() {
        // getBinding 的 get_row 回傳有結果
        $binding = (object) [
            'id' => 1, 'user_id' => 10, 'line_uid' => 'Uabc123',
            'display_name' => 'Test', 'picture_url' => null,
            'email' => null, 'email_verified' => 0,
            'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];
        $GLOBALS['mock_wpdb_results'] = [$binding];

        $this->assertTrue(UserService::isLinked(10));
    }

    public function test_isLinked_returns_false_when_not_bound() {
        // get_row 回傳 null（LINE Hub 表）
        // get_var 回傳 0（NSL 表不存在）
        $GLOBALS['mock_wpdb_results'] = [null, 0];

        $this->assertFalse(UserService::isLinked(999));
    }

    // ── linkUser ──────────────────────────────────────────

    public function test_linkUser_returns_error_when_uid_already_bound() {
        // getUserByLineUid 回傳 user_id = 99（已被其他用戶綁定）
        $GLOBALS['mock_wpdb_results'] = [99];

        $result = UserService::linkUser(10, 'Ualready_bound');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('line_uid_already_bound', $result->get_error_code());
    }

    // ── unlinkUser ────────────────────────────────────────

    public function test_unlinkUser_returns_false_when_not_bound() {
        // getBinding 的 get_row 回傳 null
        // NSL 表不存在
        $GLOBALS['mock_wpdb_results'] = [null, 0];

        $this->assertFalse(UserService::unlinkUser(999));
    }

    // ── getLineUid ────────────────────────────────────────

    public function test_getLineUid_returns_uid_from_binding() {
        $binding = (object) [
            'id' => 1, 'user_id' => 10, 'line_uid' => 'Uxyz789',
            'display_name' => 'Test', 'picture_url' => null,
            'email' => null, 'email_verified' => 0,
            'status' => 'active', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];
        $GLOBALS['mock_wpdb_results'] = [$binding];

        $this->assertEquals('Uxyz789', UserService::getLineUid(10));
    }
}
