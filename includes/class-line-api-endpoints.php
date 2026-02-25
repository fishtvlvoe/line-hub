<?php
/**
 * LINE API Endpoints 常數類別
 *
 * 集中管理所有 LINE API URL，避免重複定義
 *
 * @package LineHub
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LINE API 端點常數
 *
 * 所有 LINE Platform API URL 統一在此管理。
 * 各服務類別透過 LineApiEndpoints::CONSTANT 引用，不要硬編碼 URL。
 */
class LineApiEndpoints {

    // ── OAuth 2.0 ──────────────────────────────────────
    /** Token 交換端點（authorization code → access token） */
    const OAUTH_TOKEN = 'https://api.line.me/oauth2/v2.1/token';

    /** Token 驗證端點（驗證 access token 或 ID token） */
    const OAUTH_VERIFY = 'https://api.line.me/oauth2/v2.1/verify';

    /** OAuth Access Token 端點（Channel Access Token v2.0，後台連線測試用） */
    const OAUTH_ACCESS_TOKEN = 'https://api.line.me/v2/oauth/accessToken';

    // ── User Profile ───────────────────────────────────
    /** 用戶 Profile 端點（取得 displayName, pictureUrl 等） */
    const PROFILE = 'https://api.line.me/v2/profile';

    // ── Messaging API ──────────────────────────────────
    /** Bot 訊息發送端點（push/reply/multicast/broadcast） */
    const BOT_MESSAGE = 'https://api.line.me/v2/bot/message';

    /** Bot 資訊端點（取得 Bot 基本資訊） */
    const BOT_INFO = 'https://api.line.me/v2/bot/info';
}
