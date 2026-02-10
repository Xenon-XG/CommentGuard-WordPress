# 🤖 CommentGuard

[中文](../README.md)

AI-powered WordPress comment moderation using **Agent mode**. The AI autonomously analyzes each comment and decides to approve, reject, or flag it for human review.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0-green)](https://www.gnu.org/licenses/gpl-2.0.html)

## ✨ Features

- **🤖 AI Agent Mode** — AI uses function calling (tool use) to autonomously approve, reject, or flag comments
- **⚡ Async Queue** — Comments are processed in the background via WP-Cron, zero impact on page load
- **🔌 Extensible Providers** — OpenAI built-in; easily add Gemini, DeepSeek, or any OpenAI-compatible API
- **📊 Statistics Dashboard** — Track approval/rejection rates at a glance
- **📝 Audit Log** — Detailed logging of every decision with reason, model, and token usage
- **🌐 Multi-Language UI** — Chinese / English interface with scalable locale architecture
- **⚙️ Customizable Prompt** — Full control over the AI system prompt
- **🛡️ Smart Rules** — Skip admin comments, configurable queue interval, auto-cleanup old records

## 🔧 How It Works

```
Visitor submits comment
        ↓
Comment enters moderation queue
        ↓
WP-Cron triggers AI Agent
        ↓
AI analyzes: content + article context + author info
        ↓
  ✅ Approve → Published
  🚫 Reject  → Trash
  ⚠️ Flag    → Stays pending for human review
```

## 📦 Installation

### From GitHub

1. Download or clone this repository
2. Run `npm install && npm run build` to compile frontend assets
3. Upload the `ai-comment-moderator` folder to `/wp-content/plugins/`
4. Activate the plugin in WordPress **Plugins** page
5. Go to **Comments → AI Moderation** to configure

### Configuration

1. Select your AI provider and enter your **API Key**
2. Go to **Settings → Discussion** and enable *"Comment must be manually approved"*
3. Toggle **Enable AI Moderation** in the plugin settings

## 🏗️ Architecture

```
ai-comment-moderator/
├── ai-comment-moderator.php        # Plugin entry point
├── uninstall.php                   # Clean removal (drops tables, options, cron)
├── includes/
│   ├── class-admin.php                 # WP admin integration
│   ├── class-ai-provider-interface.php # Provider contract
│   ├── class-ai-provider-manager.php   # Provider registry
│   ├── class-openai-provider.php       # OpenAI implementation
│   ├── class-moderation-agent.php      # Core AI Agent logic
│   ├── class-moderation-queue.php      # Queue data model
│   ├── class-queue-processor.php       # Async queue processing (WP-Cron)
│   ├── class-comment-hooks.php         # WordPress comment hook integration
│   ├── class-audit-log.php             # Audit log data model
│   └── class-rest-api.php              # REST API endpoints
├── src/
│   ├── settings.js                     # Entry point
│   ├── i18n.js                         # Multi-language context provider
│   ├── locales/                        # Translation files
│   │   ├── index.js                    # Locale registry
│   │   ├── zh.js                       # Chinese
│   │   └── en.js                       # English
│   ├── components/                     # React components
│   │   ├── App.jsx                     # Main app shell
│   │   ├── SettingsTab.jsx             # Settings panel
│   │   ├── QueueTab.jsx                # Queue management
│   │   ├── LogsTab.jsx                 # Audit log viewer
│   │   └── StatsTab.jsx                # Statistics dashboard
│   └── styles/admin.css                # Plugin styles
└── build/                              # Compiled assets (auto-generated)
```

## 🌐 Adding a New Language

Only **2 steps** required, no backend changes:

**1.** Create `src/locales/xx.js` (copy `en.js` and translate all values):

```javascript
export default {
    'app.title': 'CommentGuard',
    'app.tabs.settings': '設定',
    // ... translate all keys
};
```

**2.** Register in `src/locales/index.js`:

```javascript
import ja from './ja';

const locales = {
    zh: { label: '中文', translations: zh },
    en: { label: 'English', translations: en },
    ja: { label: '日本語', translations: ja },  // ← add this line
};
```

The AI response language automatically follows the UI language selection.

## 🔌 Adding a Custom AI Provider

Implement the `AIProviderInterface`:

```php
class MyProvider implements \flavor\flavor\AIProviderInterface {
    public function get_id(): string { return 'my-provider'; }
    public function get_name(): string { return 'My Provider'; }
    public function chat(string $api_key, string $model, array $messages, array $tools, array $options = []): array {
        // Call your AI API and return standardized response
    }
    public function test_connection(string $api_key, array $options = []): array {
        // Test API connectivity
    }
}
```

Register it via the hook:

```php
add_action('flavor_flavor_register_providers', function($manager) {
    $manager->register(new MyProvider());
});
```

## 📋 REST API

All endpoints require `manage_options` capability.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/ai-moderator/v1/settings` | Get plugin settings |
| `POST` | `/ai-moderator/v1/settings` | Save plugin settings |
| `GET` | `/ai-moderator/v1/queue` | List queue items |
| `POST` | `/ai-moderator/v1/queue/retry/{id}` | Retry a failed item |
| `DELETE` | `/ai-moderator/v1/queue/delete/{id}` | Delete a queue item |
| `POST` | `/ai-moderator/v1/process` | Manually trigger queue processing |
| `GET` | `/ai-moderator/v1/logs` | List audit log entries |
| `DELETE` | `/ai-moderator/v1/logs/clear` | Clear all audit logs |
| `GET` | `/ai-moderator/v1/stats` | Get moderation statistics |
| `POST` | `/ai-moderator/v1/test` | Test AI provider connection |

## 🛠️ Development

```bash
# Install dependencies
npm install

# Development mode (watch)
npm start

# Production build
npm run build
```

## 📄 Requirements

- WordPress 6.0+
- PHP 7.4+
- An AI API key (OpenAI or compatible provider)

## 📜 License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
