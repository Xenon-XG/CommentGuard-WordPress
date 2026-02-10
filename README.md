# 🤖 AI Comment Moderator

[English](./docs/README_EN.md)

基于 **AI Agent 模式**的 WordPress 智能评论审核插件。AI 自主分析每条评论，决定通过、拒绝或标记待人工复审。

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## ✨ 功能特性

- **🤖 AI Agent 模式** — AI 通过 Function Calling（工具调用）自主决定通过、拒绝或标记评论
- **⚡ 异步队列处理** — 通过 WP-Cron 后台处理，不影响页面加载速度
- **🔌 可扩展 AI 提供商** — 内置 OpenAI，可轻松接入 Gemini、DeepSeek 等兼容 API
- **📊 统计看板** — 一目了然查看通过/拒绝比率
- **📝 审核日志** — 记录每次审核决策的理由、模型和 Token 用量，支持展开详情和一键清除
- **🌐 多语言界面** — 中文/英文界面切换，可扩展的 locale 架构
- **⚙️ 自定义提示词** — 可完全自定义 AI 审核的 System Prompt
- **🛡️ 灵活配置** — 跳过管理员评论、可配置队列触发间隔、自动清理旧记录

## 🔧 工作原理

```
访客提交评论
      ↓
评论进入审核队列
      ↓
WP-Cron 触发 AI Agent
      ↓
AI 分析：评论内容 + 文章上下文 + 作者信息
      ↓
  ✅ 通过 → 发布
  🚫 拒绝 → 移至回收站
  ⚠️ 标记 → 保持待审，等待人工复审
```

## 📦 安装

### 从 GitHub 安装

1. 下载或克隆本仓库
2. 运行 `npm install && npm run build` 编译前端资源
3. 将 `ai-comment-moderator` 文件夹上传到 `/wp-content/plugins/`
4. 在 WordPress **插件** 页面激活插件
5. 前往 **评论 → AI 审核** 进行配置

### 初始配置

1. 选择 AI 提供商并输入 **API Key**
2. 前往 **设置 → 讨论**，启用 *"评论必须经人工批准"*
3. 在插件设置中开启 **启用 AI 审核**

## 🏗️ 项目结构

```
ai-comment-moderator/
├── ai-comment-moderator.php        # 插件入口
├── uninstall.php                   # 卸载清理（删除数据表、配置、定时任务）
├── includes/
│   ├── class-admin.php                 # WP 后台集成
│   ├── class-ai-provider-interface.php # AI 提供商接口定义
│   ├── class-ai-provider-manager.php   # 提供商注册管理
│   ├── class-openai-provider.php       # OpenAI 实现
│   ├── class-moderation-agent.php      # 核心 AI Agent 逻辑
│   ├── class-moderation-queue.php      # 队列数据模型
│   ├── class-queue-processor.php       # 异步队列处理（WP-Cron）
│   ├── class-comment-hooks.php         # WordPress 评论钩子
│   ├── class-audit-log.php             # 审核日志模型
│   └── class-rest-api.php              # REST API 端点
├── src/
│   ├── settings.js                     # 前端入口
│   ├── i18n.js                         # 多语言上下文
│   ├── locales/                        # 翻译文件
│   │   ├── index.js                    # 语言注册表
│   │   ├── zh.js                       # 中文
│   │   └── en.js                       # 英文
│   ├── components/                     # React 组件
│   │   ├── App.jsx                     # 应用外壳
│   │   ├── SettingsTab.jsx             # 设置面板
│   │   ├── QueueTab.jsx                # 队列管理
│   │   ├── LogsTab.jsx                 # 审核日志
│   │   └── StatsTab.jsx                # 统计看板
│   └── styles/admin.css                # 样式
└── build/                              # 编译产物（自动生成）
```

## 🌐 添加新语言

只需 **2 步**，无需修改后端：

**1.** 复制 `src/locales/en.js` 为新文件（如 `ja.js`），翻译所有值：

```javascript
export default {
    'app.title': 'AI Comment Moderator',
    'app.tabs.settings': '設定',
    // ... 翻译所有 key
};
```

**2.** 在 `src/locales/index.js` 注册一行：

```javascript
import ja from './ja';

const locales = {
    zh: { label: '中文', translations: zh },
    en: { label: 'English', translations: en },
    ja: { label: '日本語', translations: ja },  // ← 加这一行
};
```

AI 回复语言会自动跟随界面语言切换。

## 🔌 自定义 AI 提供商

实现 `AIProviderInterface` 接口：

```php
class MyProvider implements \flavor\flavor\AIProviderInterface {
    public function get_id(): string { return 'my-provider'; }
    public function get_name(): string { return 'My Provider'; }
    public function chat(string $api_key, string $model, array $messages, array $tools, array $options = []): array {
        // 调用你的 AI API 并返回标准化响应
    }
    public function test_connection(string $api_key, array $options = []): array {
        // 测试 API 连接
    }
}
```

通过钩子注册：

```php
add_action('flavor_flavor_register_providers', function($manager) {
    $manager->register(new MyProvider());
});
```

## 📋 REST API

所有端点需要 `manage_options` 权限。

| 方法 | 端点 | 说明 |
|------|------|------|
| `GET` | `/ai-moderator/v1/settings` | 获取设置 |
| `POST` | `/ai-moderator/v1/settings` | 保存设置 |
| `GET` | `/ai-moderator/v1/queue` | 队列列表 |
| `POST` | `/ai-moderator/v1/queue/retry/{id}` | 重试失败项 |
| `DELETE` | `/ai-moderator/v1/queue/delete/{id}` | 删除队列项 |
| `POST` | `/ai-moderator/v1/process` | 手动触发队列处理 |
| `GET` | `/ai-moderator/v1/logs` | 审核日志列表 |
| `DELETE` | `/ai-moderator/v1/logs/clear` | 清除全部日志 |
| `GET` | `/ai-moderator/v1/stats` | 统计数据 |
| `POST` | `/ai-moderator/v1/test` | 测试 AI 连接 |

## 🛠️ 开发

```bash
# 安装依赖
npm install

# 开发模式（监听变更）
npm start

# 生产构建
npm run build
```

## 📄 环境要求

- WordPress 6.0+
- PHP 7.4+
- AI API Key（OpenAI 或兼容提供商）

## 📜 许可证

GPL-2.0-or-later — 详见 [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
