# WebinarGo 商業化討論紀錄

**日期：** 2026-02-24
**狀態：** 討論中，等 LineHub v2.0 完成後啟動

## 商業定位

- **產品類型：** 付費 WordPress 外掛（LineHub 的商業附加模組）
- **定價策略：** 每站 ≥10,000 TWD，終身授權
- **商業模式：** LineHub 免費 → WebinarGo 付費
- **目標客群：** 使用 WebinarJam/Webinarkit 做自動化直播的業者

## 架構分工（房子比喻）

- **LineHub = 硬裝**（柱子、水電）：LINE API、用戶綁定、訊息發送能力（含 Flex/卡片/輪播的 API 封裝）
- **WebinarGo = 軟裝**（沙發、冰箱）：通知模板、Webinar 串接邏輯、排程規則
- **搬家 = 換站**：客戶帶走 WebinarGo 設定和模板，LineHub 硬裝留原地

結論：訊息格式能力（卡片/輪播/圖片按鈕）由 LineHub 提供 API，模板內容由 WebinarGo 定義。

## MVP 定義（用戶確認）

> 1. 一個後台表單（設定 UI）
> 2. 串接 WebinarJam 或 Webinarkit
> 3. 用戶填表單、選日期 → LINE 收到通知 + 可排程

## 完整產品功能（商業化必備）

### 通知場景（A+B+C 全做）
- **A. 報名通知**：用戶報名 → LINE 立刻收到「報名成功 + 時間 + 連結」
- **B. 排程提醒**：研討會前 N 分鐘 → LINE 收到「即將開始」提醒
- **C. 重播通知**：研討會結束 → LINE 收到「重播連結」

### 訊息格式
- 卡片式或一般式訊息
- 圖片 + 按鈕 / 圖片 + 連結
- 輪播功能

### 後台 UI
- 客戶不需登入 LINE，在 WordPress 後台設定訊息、時間、格式
- LINE 訊息預覽功能

### 授權系統
- 付費驗證機制（可沿用 BGO 的 buygo_is_pro() 模式）

## Email 橋梁

不是問題。用戶從 LINE 進入生態系，未綁定 LINE 就收不到通知，自然會去做 Email 驗證。跟 BuyGo 邏輯一致。

## 開發順序（建議）

1. WebinarJam Adapter + Webhook 接收
2. 後台設定 UI（API Key + 選通知場景 + 訊息格式設定）
3. 訊息模板系統（卡片/輪播/圖片按鈕）
4. 排程提醒（wp_cron）
5. 重播 + 結束通知
6. 授權系統

## 待確認（開 milestone 前）

- [ ] WebinarJam API/Webhook 文件研究
- [ ] Webinarkit API/Webhook 文件研究
- [ ] LineHub Flex Message 能力盤點（目前支援到什麼程度）
- [ ] 第一個 demo 目標確認

## 前置條件

- [ ] LineHub v2.0 Phase 9（Tab 重構）完成
- [ ] LineHub v2.0 Phase 10（開發者體驗）完成
