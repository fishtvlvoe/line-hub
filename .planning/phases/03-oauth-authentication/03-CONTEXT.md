# Phase 3: OAuth Authentication - Context

**Gathered:** 2026-02-07
**Status:** Ready for planning

<domain>
## Phase Boundary

用户可以透过 LINE OAuth 2.0 认证系统安全登入 WordPress，系统自动创建账号、处理 Email 缺失情况，并完成绑定关系建立。

此 Phase 仅处理认证流程，不包含登入按钮 UI（属于 Phase 7 Admin UI）。

</domain>

<decisions>
## Implementation Decisions

### OAuth State 与安全机制

**State 参数生命周期：**
- 有效期：**5 分钟**（短期高安全）
- 超时后显示友善提示：「登入超时，请重新登入」（而非技术错误信息）

**State 存储方式：**
- **参考 NSL 的实现方式**
- 研究员需要：
  1. 检查 NSL 如何存储 State（Transient API / 自建表 / PHP Session）
  2. 了解 NSL 的 State 数据结构
  3. 复制相同的存储策略

**CSRF 防护机制：**
- **完全参考 NSL 的 CSRF 防护实现**
- 研究员需要：
  1. 查看 NSL 是否使用 State 之外的额外机制（Nonce / PKCE 等）
  2. 复制所有相关的安全检查逻辑

### 新用户账号创建规则

**Username 生成规则：**
- **参考 NSL 的 username 生成逻辑**
- 研究员需要了解：
  1. NSL 如何处理中文 display_name（转拼音或使用 LINE UID）
  2. NSL 的 username 前缀设定（如 `line_`）
  3. NSL 如何处理 username 冲突（后缀数字等）

**Display Name 来源：**
- 直接使用 **LINE profile 的 display_name**

**预设角色：**
- 新用户角色：**subscriber（订阅者）**

**账号冲突处理：**
- 如果 Email 已存在于 WordPress：**直接登入到现有账号**
- 逻辑：同一个 Email = 同一个人，自动绑定 LINE UID 到该账号

### 登入后的重定向行为

**默认重定向规则：**
- **优先级 1**：返回**原始页面**（用户点击登入按钮的页面）
- **优先级 2**：如果没有原始页面（直接访问登入网址），跳转到**首页 /**

**管理员可配置：**
- 在后台设定页面提供「登入后跳转网址」配置选项
- 自定义网址优先级高于默认规则

### Email 验证与补救流程

**Email 缺失处理：**
- 当 LINE ID Token 没有 Email 时：**直接显示 Email 输入表单**
- 表单显示位置：在**登入回调页面（callback URL）**

**Email 验证机制：**
- **由管理员在后台设定**是否强制验证 Email
- 如果启用验证：发送验证邮件，用户需点击链接确认

**Email 冲突处理：**
- 如果用户填写的 Email 已存在：**直接登入到现有账号**
- 逻辑：同一个 Email = 同一个人，自动绑定 LINE UID 到该账号

### Claude's Discretion

研究员和规划员可自行决定：
- OAuth Client 的具体实现方式（是否使用第三方库）
- ID Token 解析和验证的技术细节
- 回调页面的错误处理和重试机制
- Email 输入表单的 UI 设计（简洁即可）

</decisions>

<specifics>
## Specific Ideas

**参考实现：**
- **NSL (Nextend Social Login)** 是主要参考对象
- 需要研究的 NSL 部分：
  1. OAuth State 存储机制
  2. CSRF 防护实现
  3. Username 生成规则（特别是中文 display_name 处理）
  4. 账号冲突和 Email 冲突的处理逻辑

**用户体验要点：**
- 错误信息应该友善易懂（非技术用语）
- Email 表单在回调页面显示（无需弹窗或额外跳转）
- 重定向逻辑应该让用户感觉流畅（回到原本想去的地方）

**安全要点：**
- State 参数 5 分钟短期有效，防止 CSRF 攻击
- 完全复制 NSL 的安全机制，不自创安全方案

</specifics>

<deferred>
## Deferred Ideas

无 - 讨论保持在 Phase 3 范围内

</deferred>

---

*Phase: 03-oauth-authentication*
*Context gathered: 2026-02-07*
