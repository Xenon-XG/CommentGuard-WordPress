# AI Comment Moderator — WordPress 插件实现计划

一款基于 AI Agent 模式的 WordPress 评论异步审核插件。AI 通过 function calling 主动判断评论并执行审核动作。

> [!IMPORTANT]
> 插件将创建在 `c:\Users\Xenon\Desktop\ai-comment-moderator\` 目录下，与现有项目分开。

## 核心设计决策

### AI Agent 模式
AI 不是简单地返回"通过/拒绝"，而是作为 Agent：
- 接收评论上下文（内容、作者、文章标题等）
- 拥有 3 个可调用工具：`approve_comment`、`reject_comment`、`flag_for_review`
- AI 自主分析并决定调用哪个工具，附带审核理由

### 异步队列审核
- 评论提交 → WordPress 标记为"待审核"（[hold](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/function/function.php#648-697)）→ 进入审核队列
- WP-Cron 定时触发队列处理 → 调用 AI Agent → 执行审核结果
- 后台提醒用户开启 WordPress 评论审核队列

### AI Provider 可扩展架构
```
AIProviderInterface (接口)
├── OpenAIProvider (当前实现)
├── GeminiProvider (未来)
└── DeepSeekProvider (未来)
```

---

## Proposed Changes

### 插件基础架构

#### [NEW] [ai-comment-moderator.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/ai-comment-moderator.php)
插件主入口文件：
- 插件头部信息（Plugin Name, Version, Author 等）
- 命名空间 `AICommentModerator`
- 定义常量（版本、路径、REST 命名空间）
- `spl_autoload_register` 自动类加载
- 初始化核心类
- 注册激活/停用钩子

#### [NEW] [includes/](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/)
PHP 类文件目录

---

### AI Provider 层

#### [NEW] [includes/class-ai-provider-interface.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-ai-provider-interface.php)
AI 提供者接口定义：
```php
interface AIProviderInterface {
    public function get_name(): string;
    public function get_id(): string;
    public function chat(array $messages, array $tools = []): array;
    public function validate_api_key(string $key): bool;
}
```

#### [NEW] [includes/class-openai-provider.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-openai-provider.php)
OpenAI Provider 实现：
- [chat()](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-aiblock.php#577-662) — 调用 OpenAI Chat Completions API（支持 function calling）
- `validate_api_key()` — 验证 API Key 有效性
- 支持配置模型（默认 `gpt-4o-mini`）、API Base URL（方便代理）
- 超时处理与错误重试

#### [NEW] [includes/class-ai-provider-manager.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-ai-provider-manager.php)
Provider 管理器：
- 注册/获取 Provider
- 获取当前激活的 Provider
- 提供 `register_provider(AIProviderInterface $provider)` 方法供扩展

---

### AI Agent 系统

#### [NEW] [includes/class-moderation-agent.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-moderation-agent.php)
审核 Agent 核心，基于 function calling 设计：

**System Prompt** — 定义 AI 角色为评论审核专家，说明审核标准

**Tools 定义：**
```json
[
  {
    "name": "approve_comment",
    "description": "批准评论发布",
    "parameters": { "reason": "string" }
  },
  {
    "name": "reject_comment", 
    "description": "拒绝评论，移入垃圾箱",
    "parameters": { "reason": "string" }
  },
  {
    "name": "flag_for_review",
    "description": "标记为可疑，需人工复审",
    "parameters": { "reason": "string" }
  }
]
```

**输入上下文**：评论内容、作者名、作者邮箱、文章标题、文章摘录、该文章已有评论摘要

**执行流程**：组装 messages → 调用 AI chat with tools → 解析 tool_call 响应 → 返回结构化审核结果

---

### 审核队列系统

#### [NEW] [includes/class-moderation-queue.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-moderation-queue.php)
审核队列管理：
- 创建自定义数据库表 `{prefix}ai_comment_queue`
  - [id](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-admin.php#667-777), `comment_id`, [status](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-comment.php#576-602)(pending/processing/completed/error), `result`(approved/rejected/flagged), `reason`, `ai_provider`, `attempts`, `created_at`, `processed_at`
- [enqueue(int $comment_id)](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-thread.php#365-383) — 加入队列
- `dequeue()` — 获取下一个待处理任务
- [update(int $id, array $data)](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-cronqueue.php#957-1000) — 更新任务状态
- `get_stats()` — 获取队列统计信息
- [cleanup(int $days)](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-cronqueue.php#868-955) — 清理历史记录

#### [NEW] [includes/class-queue-processor.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-queue-processor.php)
队列处理器：
- 注册 WP-Cron 事件（每分钟检查一次）
- `process_next()` — 取出一条待处理评论 → 调用 Agent → 执行结果
- 执行审核结果：
  - `approve` → `wp_set_comment_status($id, 'approve')`
  - `reject` → `wp_set_comment_status($id, 'trash')`
  - `flag` → 保持 [hold](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/function/function.php#648-697) 状态，添加 meta 标记
- 错误处理：失败后重试（最多 3 次），超过则标记 error
- 每次处理限制数量（防止超时）

---

### 评论 Hook 集成

#### [NEW] [includes/class-comment-hooks.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-comment-hooks.php)
WordPress 评论钩子集成：
- [comment_post](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-interact.php#834-880) hook — 新评论提交时加入审核队列
- 跳过已登录管理员的评论（可配置）
- 跳过已被 WordPress 标记为垃圾的评论
- 在评论列表中添加 AI 审核状态列
- 在评论行操作中添加"重新 AI 审核"按钮

---

### 后台管理 & React SPA 设置页

#### [NEW] [includes/class-admin.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-admin.php)
后台管理类：
- 注册管理菜单页面（评论菜单下的子菜单）
- 渲染 React 挂载点 `<div id="ai-comment-moderator-root">`
- 注册并加载 React 脚本和样式
- 传递 `wp_localize_script` 数据（REST URL、nonce、当前设置）
- 显示 admin_notices（提醒开启审核队列）
- 检测 WordPress 是否已开启"评论必须经过人工审核"

#### [NEW] [includes/class-rest-api.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-rest-api.php)
REST API 端点（供 React 前端调用）：
- `GET /ai-moderator/v1/settings` — 获取设置
- `POST /ai-moderator/v1/settings` — 保存设置
- `GET /ai-moderator/v1/queue` — 获取队列列表（分页、筛选）
- `POST /ai-moderator/v1/queue/retry/{id}` — 重试审核
- `GET /ai-moderator/v1/stats` — 获取审核统计
- `GET /ai-moderator/v1/logs` — 获取审核日志
- `POST /ai-moderator/v1/test` — 测试 API 连接

#### [NEW] [src/settings.js](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/settings.js)
React SPA 入口文件

#### [NEW] [src/components/App.jsx](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/components/App.jsx)
React 主组件，包含 Tab 导航：
- **基础设置** — AI Provider 选择、API Key、模型、API Base URL
- **审核配置** — 审核规则、是否跳过管理员、自定义 System Prompt
- **队列管理** — 查看/管理审核队列，批量重试
- **审核日志** — 查看历史审核记录（当日志功能启用时）
- **统计看板** — 审核通过率、拒绝率等

#### [NEW] [src/components/SettingsTab.jsx](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/components/SettingsTab.jsx)
#### [NEW] [src/components/QueueTab.jsx](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/components/QueueTab.jsx)
#### [NEW] [src/components/LogsTab.jsx](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/components/LogsTab.jsx)
#### [NEW] [src/components/StatsTab.jsx](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/components/StatsTab.jsx)
#### [NEW] [src/styles/admin.css](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/src/styles/admin.css)

---

### 审核日志

#### [NEW] [includes/class-audit-log.php](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/includes/class-audit-log.php)
审核日志（通过设置开关控制）：
- 自定义数据库表 `{prefix}ai_comment_audit_log`
  - [id](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-admin.php#667-777), `comment_id`, [action](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-thread.php#911-918), `reason`, `ai_provider`, `ai_model`, `raw_response`, `created_at`
- [log(int $comment_id, string $action, string $reason, array $meta)](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/aitoolbox.php#131-139) — 记录日志
- `get_logs(array $filters)` — 查询日志（分页+筛选）
- [cleanup(int $days)](file:///c:/Users/Xenon/Desktop/xhtheme-ai-toolbox/classes/class-xhtheme-cronqueue.php#868-955) — 定期清理旧日志

---

### 构建和配置文件

#### [NEW] [package.json](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/package.json)
使用 `@wordpress/scripts` 构建 React SPA

#### [NEW] [readme.txt](file:///c:/Users/Xenon/Desktop/ai-comment-moderator/readme.txt)
WordPress.org 标准 readme

---

## 插件目录结构总览

```
ai-comment-moderator/
├── ai-comment-moderator.php       # 主入口
├── package.json                   # 前端构建配置
├── readme.txt                     # WP 插件说明
│
├── includes/                      # PHP 类
│   ├── class-ai-provider-interface.php
│   ├── class-openai-provider.php
│   ├── class-ai-provider-manager.php
│   ├── class-moderation-agent.php
│   ├── class-moderation-queue.php
│   ├── class-queue-processor.php
│   ├── class-comment-hooks.php
│   ├── class-admin.php
│   ├── class-rest-api.php
│   └── class-audit-log.php
│
├── src/                           # React 源码
│   ├── settings.js
│   ├── components/
│   │   ├── App.jsx
│   │   ├── SettingsTab.jsx
│   │   ├── QueueTab.jsx
│   │   ├── LogsTab.jsx
│   │   └── StatsTab.jsx
│   └── styles/
│       └── admin.css
│
├── build/                         # 编译产出（wp-scripts build）
└── languages/                     # 国际化
```

---

## Verification Plan

### Automated Tests
由于是全新项目，暂无自动化测试框架。初期通过手动验证。

### Manual Verification

> [!TIP]
> 以下测试需要一个可用的 WordPress 本地环境。如果你目前没有本地 WP 环境，请告诉我你的环境情况，我可以调整验证方式。

**1. 插件激活测试**
- 将插件目录放入 `wp-content/plugins/`
- 在 WordPress 后台"插件"页面激活插件
- 验证：无 PHP Fatal Error，数据库自动创建 `ai_comment_queue` 和 `ai_comment_audit_log` 表

**2. 设置页面测试**
- 访问 WP 后台 → 评论 → AI 审核
- 验证：React SPA 页面正常加载，可以切换 Tab
- 输入 OpenAI API Key → 点击保存 → 刷新后配置仍在

**3. 审核队列提醒测试**
- 如果 WordPress 未开启"评论必须经过审核"，后台应显示提醒通知
- 点击通知中的链接跳转到 WP 讨论设置

**4. 评论审核流程测试**
- 在前台以游客身份提交一条评论
- 验证评论进入待审核状态
- 验证 `ai_comment_queue` 表中有对应记录（status=pending）
- 等待 WP-Cron 执行（或手动触发）
- 验证 AI Agent 调用后评论被正确处理（通过/拒绝/标记）
