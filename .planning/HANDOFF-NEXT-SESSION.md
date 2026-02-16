# 明日測試計畫 — BuyGo + LineHub 整合測試

> 日期：2026-02-17
> 範圍：本機完整測試（產品上架 + LINE 通知 + Webhook 流程）
> 環境：https://test.buygo.me（本機外連）

---

## 前置檢查清單

在開始測試前，確認以下項目：

### 環境啟動
- [ ] Local by Flywheel 已啟動（buygo.local）
- [ ] ngrok / cloudflare tunnel 已啟動（test.buygo.me 可連）
- [ ] WordPress 已登入（facemarketin@gmail.com / nms149149）

### 外掛啟用確認
- [ ] BuyGo Plus One（buygo-plus-one）已啟用
- [ ] LINE Hub（line-hub）已啟用
- [ ] FluentCart 已啟用
- [ ] buygo-line-notify 已停用（測試 LineHub 獨立運作）

### LINE 設定確認
- [ ] LINE Hub 設定頁：Channel ID、Channel Secret、LIFF ID 已填入
- [ ] LINE Developers Console：Webhook URL 設為 `https://test.buygo.me/wp-json/line-hub/v1/webhook`
- [ ] LINE Developers Console：Webhook 已開啟（Use webhook = ON）

---

## 測試流程

### Phase A：LINE 登入測試（5 分鐘）

1. **LIFF 登入**
   - 手機打開 `https://liff.line.me/2008622068-iU4Z1lk4`
   - 確認能正確登入 WordPress
   - 確認歡迎 Toast 顯示

2. **OAuth 登入**
   - 瀏覽器打開 `https://test.buygo.me/line-hub/auth/`
   - 確認 LINE Login 授權流程正常
   - 確認 callback 後成功建立/綁定帳號

3. **帳號綁定確認**
   - WordPress 個人資料頁（/wp-admin/profile.php）
   - 確認 LINE 綁定區塊顯示正確資訊
   - 確認 LINE UID、頭像、顯示名稱

### Phase B：Webhook Verify 測試（2 分鐘）

1. LINE Developers Console → Webhook settings → 點 **Verify**
2. 預期結果：回應 200 OK（Success）
3. 檢查 WordPress 端無 error log

### Phase C：產品上架測試（10 分鐘）

**完整流程：LINE 圖片 → 選擇類型 → 輸入資訊 → 建立商品**

1. **發送商品圖片**
   - 用 LINE 發送一張商品圖片給 OA
   - 預期：收到「請選擇商品類型」Flex Message（按鈕）

2. **選擇商品類型**
   - 點擊 Flex 按鈕（例：一般商品）
   - 預期：收到「請依照格式輸入商品資訊」的說明

3. **輸入商品資訊**
   - 輸入格式化文字（商品名稱、價格、數量等）
   - 預期：商品建立成功，收到確認訊息含商品連結

4. **驗證商品**
   - BuyGo 後台 → 商品管理 → 確認新商品出現
   - FluentCart 前台 → 確認商品頁面正常

### Phase D：通知測試（5 分鐘）

1. **關鍵字回覆**
   - LINE 輸入 `/help` → 預期：收到使用說明
   - LINE 輸入 `/ID` → 預期：收到帳號資訊
   - LINE 輸入 `/綁定` → 預期：收到綁定資訊

2. **Follow/Unfollow**
   - 取消好友 → 重新加好友
   - 預期：收到歡迎訊息

### Phase E：驗證 Webhook 記錄（3 分鐘）

1. 檢查 `wp_line_hub_webhooks` 資料表
   - 確認所有事件都有記錄
   - 確認 `processed = 1`
   - 確認沒有重複記錄

---

## 除錯工具

如果有問題，可用以下方式除錯：

```php
// wp-config.php 加入（測試完記得移除）
define('LINE_HUB_DEBUG', true);
define('LINE_HUB_SKIP_SIGNATURE_VERIFY', false); // 正式測試不要跳過

// 查看 error log
tail -f /Users/fishtv/Local\ Sites/buygo/app/public/wp-content/debug.log
```

```sql
-- 查看 Webhook 記錄
SELECT * FROM wp_line_hub_webhooks ORDER BY id DESC LIMIT 20;

-- 查看用戶綁定
SELECT * FROM wp_line_hub_users ORDER BY id DESC LIMIT 10;
```

---

## Claude Code 使用建議（避免 Prompt is too long）

1. **開新 session**：明天測試直接開新對話
2. **給這份文件**：告訴 Claude「讀取 line-hub/.planning/HANDOFF-NEXT-SESSION.md」
3. **逐 Phase 測試**：每個 Phase 是獨立的，一個 Phase 完成後可以 `/compact`
4. **問題截圖**：遇到問題直接貼截圖，Claude 可以直接分析
5. **不要開子代理做測試**：測試階段主要是人工操作 + 看 log，不需要平行子代理

---

## 整合點快速參考

| BuyGo Hook | LineHub 觸發點 | 參數數量 |
|------------|---------------|---------|
| `line_hub/webhook/message/text` | EventDispatcher | 4 |
| `line_hub/webhook/message/image` | EventDispatcher | 4 |
| `line_hub/webhook/postback` | EventDispatcher | 3 |
| `line_hub/webhook/follow` | EventDispatcher | 1 |
| `line_hub/webhook/unfollow` | EventDispatcher | 1 |

| BuyGo 發送方式 | 優先 | Fallback |
|---------------|------|---------|
| `LineMessagingFacade::send_reply()` | LineHub MessagingService | buygo-line-notify |
| `LineMessagingFacade::send_flex()` | LineHub MessagingService | buygo-line-notify |
| `NotificationService::pushText()` | LineHub MessagingService | buygo-line-notify |
