# LINE Hub 全方位外掛 - 3週開發路線圖

**專案代號**: LINE Hub
**目標**: 從 buygo-line-notify 升級為全功能 LINE 整合中心
**時程**: 3 週 (15 個工作天)
**執行模式**: Auto Run (前端規劃確認後)
**建立日期**: 2026-02-06

---

## 🎯 專案定位

```
┌─────────────────────────────────────────────────────────┐
│                    LINE Hub 生態系統                      │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────┐ │
│  │ LINE Notify  │    │  LINE Hub    │    │ 外部整合   │ │
│  │   (保留)     │    │  (新外掛)    │    │           │ │
│  │              │    │              │    │           │ │
│  │ • 簡易通知   │───▶│ • LINE 登入   │───▶│WooCommerce│ │
│  │ • 單一功能   │    │ • Email 擷取  │    │ FluentCRM │ │
│  │              │    │ • 帳號註冊   │    │   Gmail   │ │
│  └──────────────┘    │ • 多種通知   │    │    IG     │ │
│                       │ • Webhook    │    │ Webinarkit│ │
│                       │ • 串接中心   │    └───────────┘ │
│                       └──────────────┘                   │
│                                                           │
│  🎯 目標: 成為 WordPress 的 LINE 整合中樞                │
│  📱 參考: Zapier/n8n 的串接邏輯                          │
│  🎨 後台: FluentCommunity/FluentCRM 風格                 │
└─────────────────────────────────────────────────────────┘
```

---

## 📋 核心功能清單

### ✅ MVP 功能 (第 1-3 週)

| 功能模組 | 說明 | 優先級 | 來源 |
|---------|------|--------|------|
| **LINE 登入** | OAuth 認證,取代 NSL | P0 | NSL 移植 |
| **Email 擷取** | 登入時擷取,支援重新認證 | P0 | NSL 移植 |
| **帳號註冊** | 自動建立 WordPress 帳號 | P0 | NSL 移植 |
| **訂單通知** | FluentCart 訂單通知 | P0 | 現有功能 |
| **出貨通知** | BuyGo 出貨通知 | P0 | 現有功能 |
| **會員通知** | 註冊歡迎,密碼重設 | P1 | 新功能 |
| **Webhook 中心** | 統一的 Webhook 接收端點 | P1 | 新功能 |
| **直覺後台** | Tab 導航,簡化設定 | P0 | GSD 規劃 |

### 🔮 未來擴充 (第 4 週+)

| 功能模組 | 說明 | 預計時程 |
|---------|------|---------|
| **WooCommerce 整合** | 訂單/出貨通知 | Week 4 |
| **FluentCRM 整合** | 行銷自動化通知 | Week 5 |
| **外部 SaaS 串接** | Webinarkit, Gmail, IG | Week 6+ |
| **LIFF 功能** | LINE 內嵌網頁 | Week 8+ |

---

## 📅 3週 Milestone 規劃

### 總覽圖

```
Week 1              Week 2              Week 3
┌──────────┐       ┌──────────┐       ┌──────────┐
│ 架構升級  │  ───▶ │ 功能移植  │  ───▶ │ 整合上架  │
│          │       │          │       │          │
│ • 後端架構│       │ • LINE登入│       │ • 整合測試│
│ • 資料庫  │       │ • Email處理│      │ • 文件完成│
│ • API設計│       │ • 帳號註冊│       │ • 打包發布│
│ • 後台骨架│       │ • 通知增強│       │ • 客戶驗收│
│          │       │ • 後台實作│       │          │
└──────────┘       └──────────┘       └──────────┘
   5天                6天                4天
```

---

## 🗓️ Week 1: 架構升級與後台骨架

**目標**: 建立穩固基礎,避免未來衝突

### Day 1: 架構設計與規範確認

```
09:00-12:00 (3h)
├─ 繪製功能架構圖
│  └─ LINE Hub 核心模組圖
├─ 設計資料庫 Schema
│  ├─ line_users (LINE 用戶)
│  ├─ line_bindings (綁定關係)
│  ├─ line_settings (設定)
│  └─ line_webhooks (Webhook 記錄)
└─ 規劃 API 端點
   ├─ /line-hub/v1/login
   ├─ /line-hub/v1/binding
   ├─ /line-hub/v1/settings
   └─ /line-hub/v1/webhook

14:00-17:00 (3h)
├─ WordPress 規範檢查
│  ├─ 命名空間設計 (避免衝突)
│  ├─ Hook 優先級規劃
│  └─ 前端資源載入策略
└─ 產出文件
   ├─ ARCHITECTURE.md
   └─ DATABASE-SCHEMA.md
```

**產出**:
- ✅ `LINE-HUB-ARCHITECTURE.md` - 完整架構文件
- ✅ `DATABASE-SCHEMA.md` - 資料庫設計
- ✅ `API-ENDPOINTS.md` - API 規範

### Day 2: 後端架構實作 (Phase 1.1)

```
09:00-12:00 (3h)
└─ 建立統一設定管理
   ├─ Settings_Schema 類別
   │  └─ 定義所有設定欄位結構
   ├─ SettingsService 擴展
   │  ├─ getSettings()
   │  ├─ updateSettings()
   │  └─ validateSettings()
   └─ 資料庫 options 儲存

14:00-17:00 (3h)
└─ 設定 API 端點 (Phase 1.2)
   ├─ Settings_API 類別
   ├─ GET /line-hub/v1/settings
   ├─ POST /line-hub/v1/settings
   └─ 驗證和錯誤處理
```

**GSD 對應**: Phase 1 (後端設定架構) - Plan 01-01, 01-02

### Day 3: 選單與導航框架 (Phase 2)

```
09:00-12:00 (3h)
└─ 頂層選單重構
   ├─ 建立「LINE Hub」選單
   ├─ 移除舊的「LINE 通知」
   └─ 權限控制 (manage_options)

14:00-17:00 (3h)
└─ Tab 導航系統
   ├─ 6 個 Tab 導航列
   │  ├─ 入門 (Getting Started)
   │  ├─ 設定 (Settings)
   │  ├─ 按鈕 (Buttons)
   │  ├─ 通知 (Notifications) ✨ 新增
   │  ├─ 用法 (Usage)
   │  └─ 進階 (Advanced)
   ├─ Tab 狀態管理
   └─ URL 路由 (?tab=xxx)
```

**GSD 對應**: Phase 2 (選單與導航框架)

### Day 4: 視覺設計系統 (Phase 3)

```
09:00-12:00 (3h)
└─ 設計系統 CSS
   ├─ FluentCommunity 風格參考
   ├─ 配色系統 (主色/輔助色)
   ├─ 統一表單元件
   │  ├─ input, select, checkbox
   │  ├─ toggle switch
   │  └─ button 樣式
   └─ 響應式 Grid

14:00-17:00 (3h)
└─ 核心 UI 元件
   ├─ 成功/錯誤提示
   ├─ Loading 狀態
   ├─ 空白狀態 (Empty state)
   └─ 卡片 (Card) 元件
```

**GSD 對應**: Phase 3 (視覺設計系統)

### Day 5: 核心 Tab 實作 - 入門和設定 (Phase 4.1, 4.2)

```
09:00-12:00 (3h)
└─ 入門 Tab
   ├─ 歡迎訊息
   ├─ 步驟 1: LINE 開發者帳號引導
   ├─ 步驟 2: Channel 資訊說明
   ├─ 步驟 3: Webhook URL 複製
   └─ 連線測試功能

14:00-17:00 (3h)
└─ 設定 Tab (簡化版)
   ├─ LINE OA 設定
   │  ├─ Channel ID
   │  ├─ Channel Secret
   │  └─ Channel Access Token
   ├─ LINE Login 設定
   │  ├─ Login Channel ID
   │  └─ Login Channel Secret
   └─ 表單驗證
```

**GSD 對應**: Phase 4 (核心 Tab 實作) - 入門、設定

**Week 1 產出**:
- ✅ 完整的後端架構
- ✅ 統一設定管理 API
- ✅ 後台選單和導航
- ✅ 視覺設計系統
- ✅ 入門和設定 Tab

---

## 🗓️ Week 2: NSL 功能移植與通知增強

**目標**: 核心功能完整可用

### Day 6-7: LINE 登入核心移植

```
Day 6 (09:00-17:00, 6h)
└─ OAuth 認證流程
   ├─ 分析 NSL 的 OAuth 實作
   ├─ 移植認證邏輯到 LINE Hub
   ├─ LIFF 登入頁面
   │  ├─ 設計簡潔的登入界面
   │  └─ LINE SDK 整合
   └─ Token 管理
      ├─ Access Token 儲存
      ├─ Refresh Token 處理
      └─ Token 過期檢查

Day 7 (09:00-17:00, 6h)
└─ 登入流程完善
   ├─ 登入按鈕 Shortcode
   │  └─ [line_hub_login]
   ├─ 登入回呼處理
   ├─ 錯誤處理和重試
   └─ 測試各種登入情境
      ├─ 新用戶登入
      ├─ 已綁定用戶登入
      └─ 登入失敗情境
```

**技術重點**:
- 命名空間: `LineHub\Auth` (避免與 NSL 衝突)
- Hook 優先級: `10` (確保不覆蓋 NSL)
- 資料表: 獨立的 `wp_line_hub_users`

### Day 8: Email 處理與帳號註冊

```
09:00-12:00 (3h)
└─ Email 擷取機制
   ├─ LINE Profile API 取得 Email
   ├─ Email 驗證流程
   ├─ 重新認證機制
   │  └─ 當 Email 無效時引導用戶輸入
   └─ Email 來源設定
      ├─ LINE Profile (優先)
      └─ 手動輸入 (備用)

14:00-17:00 (3h)
└─ WordPress 帳號註冊
   ├─ 自動建立用戶邏輯
   │  ├─ username 生成策略
   │  ├─ Email 設定
   │  └─ Display Name 設定
   ├─ 角色分配
   │  └─ 預設 'subscriber'
   └─ 密碼重設 Email
      └─ 整合 WordPress 密碼重設
```

### Day 9: 通知功能增強

```
09:00-12:00 (3h)
└─ 新增通知場景
   ├─ 會員註冊歡迎訊息
   │  ├─ 訊息模板設計
   │  └─ 觸發時機: 註冊完成
   ├─ 密碼重設通知
   │  └─ 觸發時機: 請求密碼重設
   └─ 產品上架通知 (選配)
      └─ 整合 WooCommerce Hook

14:00-17:00 (3h)
└─ 通知後台管理
   ├─ 「通知」Tab 實作
   ├─ 通知場景開關
   │  ├─ Toggle: 訂單通知
   │  ├─ Toggle: 出貨通知
   │  ├─ Toggle: 會員通知
   │  └─ Toggle: 產品通知
   └─ 訊息模板編輯器
      ├─ Textarea with placeholders
      └─ 預覽功能
```

### Day 10: Webhook 中心建立

```
09:00-12:00 (3h)
└─ 統一 Webhook 端點
   ├─ /wp-json/line-hub/v1/webhook
   ├─ 簽名驗證
   ├─ 事件分類處理
   │  ├─ message (訊息)
   │  ├─ follow (關注)
   │  ├─ unfollow (取消關注)
   │  └─ postback (回傳)
   └─ Webhook 記錄
      └─ wp_line_hub_webhooks 表

14:00-17:00 (3h)
└─ Webhook 後台管理
   ├─ Webhook URL 顯示
   ├─ 測試發送功能
   ├─ Webhook 記錄查看
   │  └─ 最近 100 筆記錄
   └─ 清除記錄功能
```

### Day 11: 按鈕和用法 Tab (Phase 4.3, 5.2)

```
09:00-12:00 (3h)
└─ 按鈕 Tab
   ├─ 按鈕位置設定
   ├─ 按鈕文字自訂
   ├─ 按鈕樣式選擇
   └─ 即時預覽功能

14:00-17:00 (3h)
└─ 用法 Tab
   ├─ Shortcode 文檔
   │  ├─ [line_hub_login]
   │  ├─ [line_hub_binding]
   │  └─ [line_hub_profile]
   ├─ REST API 文檔
   └─ 程式碼複製功能
```

**Week 2 產出**:
- ✅ LINE 登入功能 (取代 NSL)
- ✅ Email 擷取與認證
- ✅ WordPress 帳號註冊
- ✅ 多種通知場景
- ✅ Webhook 統一管理
- ✅ 完整後台介面

---

## 🗓️ Week 3: 整合測試與上架準備

**目標**: 符合 WordPress 規範,準備上架

### Day 12: 整合測試 (Phase 6)

```
09:00-12:00 (3h)
└─ 功能測試
   ├─ LINE 登入流程完整測試
   │  ├─ 新用戶註冊
   │  ├─ 已綁定用戶登入
   │  └─ Email 擷取和認證
   ├─ 通知發送測試
   │  ├─ 訂單通知
   │  ├─ 出貨通知
   │  └─ 會員通知
   └─ Webhook 接收測試

14:00-17:00 (3h)
└─ 衝突測試
   ├─ 與 NSL 共存測試
   │  └─ 兩個外掛同時啟用
   ├─ 與 BuyGo Plus 1 整合測試
   ├─ 與 FluentCart 整合測試
   └─ 常見外掛組合測試
      ├─ WooCommerce
      ├─ Elementor
      └─ Contact Form 7
```

### Day 13: 效能優化與安全檢查

```
09:00-12:00 (3h)
└─ 效能優化
   ├─ 資料庫查詢優化
   │  ├─ 加入必要索引
   │  └─ 避免 N+1 查詢
   ├─ 前端資源優化
   │  ├─ CSS/JS 最小化
   │  └─ 按需載入
   └─ API 快取策略

14:00-17:00 (3h)
└─ 安全檢查
   ├─ SQL Injection 防護
   ├─ XSS 防護
   ├─ CSRF Token 驗證
   ├─ 權限檢查
   │  └─ 所有 API 端點
   └─ 敏感資料加密
      ├─ Channel Secret
      └─ Access Token
```

### Day 14: 文件完成與打包

```
09:00-12:00 (3h)
└─ 文件撰寫
   ├─ README.md
   │  ├─ 安裝步驟
   │  ├─ 快速開始
   │  └─ 常見問題 FAQ
   ├─ CHANGELOG.md
   │  └─ v1.0.0 初版功能
   ├─ 用戶手冊
   │  ├─ 如何取得 LINE Channel
   │  ├─ 如何設定 Webhook
   │  └─ 如何自訂通知訊息
   └─ 開發者文檔
      ├─ Hook 參考
      └─ Filter 參考

14:00-17:00 (3h)
└─ 打包與發布準備
   ├─ 更新版本號 (1.0.0)
   ├─ 執行 build-release.sh
   ├─ 檢查 ZIP 內容
   ├─ 建立 GitHub Release
   └─ 準備 WordPress.org 提交
```

### Day 15: 客戶驗收與修正

```
09:00-12:00 (3h)
└─ 客戶端部署
   ├─ 上傳到測試站
   ├─ 引導客戶設定
   └─ 記錄客戶回饋

14:00-17:00 (3h)
└─ 問題修正
   ├─ 處理客戶回報 Bug
   ├─ UI/UX 微調
   └─ 最終測試驗收
```

**Week 3 產出**:
- ✅ 完整功能測試報告
- ✅ 效能和安全檢查通過
- ✅ 完整文件 (用戶+開發者)
- ✅ GitHub Release (v1.0.0)
- ✅ 客戶驗收通過

---

## 🎨 後台設計規範

### 設計參考

```
FluentCommunity/FluentCRM 風格
├─ 簡潔清晰的 Tab 導航
├─ 卡片式佈局
├─ 統一的配色 (藍色主色調)
└─ 直覺的表單設計

Zapier/n8n 串接邏輯
├─ 視覺化的流程設定
├─ 條件判斷
└─ 測試發送功能
```

### 後台截圖範例 (設計目標)

```
┌─────────────────────────────────────────────────────┐
│ LINE Hub                                            │
├─────────────────────────────────────────────────────┤
│                                                     │
│  [入門] [設定] [按鈕] [通知] [用法] [進階]           │
│  ────────────────────────────────────────────────   │
│                                                     │
│  ┌───────────────────────────────────────────┐     │
│  │ 🚀 快速開始                                │     │
│  │                                            │     │
│  │ 步驟 1: 取得 LINE Channel                  │     │
│  │ → 前往 LINE Developers Console            │     │
│  │                                            │     │
│  │ 步驟 2: 設定 Webhook URL                   │     │
│  │ ┌──────────────────────────────────────┐  │     │
│  │ │ https://yoursite.com/wp-json/...    │  │     │
│  │ └──────────────────────────────────────┘  │     │
│  │ [📋 複製]                                  │     │
│  │                                            │     │
│  │ 步驟 3: 測試連線                           │     │
│  │ [✓ 測試 Webhook]                          │     │
│  └────────────────────────────────────────────┘     │
│                                                     │
│  ┌───────────────────────────────────────────┐     │
│  │ 📝 常見問題                                │     │
│  │ ▼ 如何取得 Channel ID?                    │     │
│  │ ▼ Webhook 無法連線怎麼辦?                 │     │
│  └────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────┘
```

---

## 🔧 技術規範

### 命名空間設計 (避免衝突)

```php
// 舊的 buygo-line-notify
namespace BuygoLineNotify;

// 新的 LINE Hub
namespace LineHub;
namespace LineHub\Auth;      // 登入認證
namespace LineHub\Webhook;   // Webhook 處理
namespace LineHub\Notify;    // 通知發送
namespace LineHub\Admin;     // 後台管理
```

### Hook 優先級策略

```php
// 確保不覆蓋 NSL
add_action('init', 'LineHub\init', 15);  // NSL 是 10

// 確保與 BuyGo Plus 1 整合
add_action('buygo/order_created', 'LineHub\notify_order', 20);
```

### 資料表設計

```sql
-- LINE 用戶表
CREATE TABLE wp_line_hub_users (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    line_uid varchar(255) NOT NULL,
    display_name varchar(255),
    picture_url varchar(500),
    email varchar(255),
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY line_uid (line_uid),
    KEY user_id (user_id)
);

-- Webhook 記錄表
CREATE TABLE wp_line_hub_webhooks (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    event_type varchar(50) NOT NULL,
    payload longtext NOT NULL,
    processed tinyint(1) DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY created_at (created_at)
);
```

---

## 📊 進度追蹤

### Milestone 檢查點

| 檢查點 | 日期 | 狀態 | 驗收標準 |
|--------|------|------|---------|
| Week 1 完成 | Day 5 | ⏸️ | 後台骨架可訪問,設定 API 可用 |
| Week 2 完成 | Day 11 | ⏸️ | LINE 登入可用,通知可發送 |
| Week 3 完成 | Day 15 | ⏸️ | 客戶驗收通過,可上架 |

### 每日進度報告格式

```markdown
## Day X 進度報告 (YYYY-MM-DD)

**計畫任務**:
- [ ] 任務 1
- [ ] 任務 2

**實際完成**:
- [x] 任務 1 ✓
- [x] 任務 2 ✓

**遇到問題**:
- 問題描述
- 解決方式

**明日計畫**:
- 任務 3
- 任務 4
```

---

## 🚀 執行模式

### Auto Run 條件

✅ **前端規劃確認後,立即啟動 Auto Run**

**Auto Run 規則**:
1. 每日 09:00 開始執行
2. 按照 Roadmap 順序執行
3. 每完成一個任務,提交 Git
4. 遇到阻礙時暫停,等待討論
5. 每日 17:00 提交進度報告

### 人工介入時機

- ❌ 技術無法解決的問題
- ❌ 需要設計決策的選擇
- ❌ 客戶回饋需要調整
- ❌ 發現架構需要重大調整

---

## 🔗 與現有 GSD 規劃的整合

### GSD Phase 對應

| LINE Hub Week | GSD Phase | 說明 |
|--------------|-----------|------|
| Week 1 Day 2 | Phase 1 | 後端設定架構 |
| Week 1 Day 3 | Phase 2 | 選單與導航框架 |
| Week 1 Day 4 | Phase 3 | 視覺設計系統 |
| Week 1 Day 5 | Phase 4.1, 4.2 | 入門、設定 Tab |
| Week 2 Day 11 | Phase 4.3, 5.2 | 按鈕、用法 Tab |
| Week 3 Day 12 | Phase 6 | 整合測試 |

**未包含的 GSD Phase**:
- Phase 5.1 (同步數據 Tab) - 移至 v2
- Phase 5.3 (LIFF Tab) - 移至 v2

---

## 📝 下一步行動

### 立即確認 (開始前)

請確認以下問題:

1. **功能範圍確認** ✅ or 調整?
   - LINE 登入 (取代 NSL)
   - Email 擷取與認證
   - WordPress 帳號註冊
   - 訂單/出貨/會員通知
   - Webhook 中心
   - 直覺後台 (Tab 導航)

2. **時程確認** 3週 ✅ or ___週?

3. **執行模式** Auto Run ✅ or 人工?

4. **優先級調整** 有沒有功能要調整優先級?

### 確認後立即執行

```bash
# 開始 Auto Run
cd /Users/fishtv/Development/buygo-line-notify
/gsd:execute-phase 1  # 開始執行 Phase 1
```

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**預計完成**: 2026-02-27 (3週後)
**執行模式**: Auto Run (待確認)
