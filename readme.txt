=== CommentGuard ===
Contributors: xenon2233
Tags: comments, moderation, ai, spam, openai
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered comment moderation using Agent mode. Automatically approve, reject, or flag comments for human review.

== Description ==

CommentGuard uses AI Agent technology to automatically review and moderate comments on your WordPress site. The AI analyzes each comment in context and autonomously decides whether to approve, reject, or flag it for human review.

**Key Features:**

* 🤖 **AI Agent Mode** — AI uses function calling (tool use) to autonomously make moderation decisions
* ⚡ **Async Queue Processing** — Comments are moderated in the background via WP-Cron, zero impact on page load
* 🔌 **Extensible AI Providers** — OpenAI built-in, architecture supports adding Gemini, DeepSeek, and more
* 📊 **Statistics Dashboard** — Track approval, rejection, and flag rates at a glance
* 📝 **Audit Log** — Detailed logging of every moderation decision with reason, model, and token usage
* 🌐 **Multi-Language UI** — Chinese and English interface, easily extensible to more languages
* ⚙️ **Customizable System Prompt** — Full control over how the AI evaluates comments
* 🛡️ **Smart Rules** — Skip admin comments, configurable queue interval, auto-cleanup old records

**How It Works:**

1. A visitor submits a comment
2. The comment enters the moderation queue
3. WP-Cron triggers the AI Agent to review the comment
4. The AI analyzes context (comment content, article title, author info) and decides:
   - ✅ **Approve** — Genuine, relevant comment → Published
   - 🚫 **Reject** — Spam, hate speech, or harmful → Trash
   - ⚠️ **Flag** — Uncertain, needs human review → Stays pending

**Supported AI Providers:**

* OpenAI (GPT-4o Mini, GPT-4o, GPT-4, etc.)
* Any OpenAI-compatible API (via custom Base URL)

== Installation ==

1. Upload the `commentguard` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Comments → AI Moderation** to configure your AI provider and API key
4. Go to **Settings → Discussion** and enable "Comment must be manually approved"
5. Enable AI moderation in the plugin settings

== Frequently Asked Questions ==

= Which AI providers are supported? =

Currently OpenAI (GPT-4o Mini, GPT-4o, etc.) and any OpenAI-compatible API. The plugin architecture supports adding more providers like Google Gemini and DeepSeek.

= Does it work with custom API endpoints? =

Yes. You can set a custom API Base URL to use proxies or any OpenAI-compatible API service.

= Will it slow down my site? =

No. Moderation is handled asynchronously via WP-Cron in the background, so visitors won't experience any delay.

= Can I customize the moderation rules? =

Yes. You can fully customize the AI system prompt to define your own moderation criteria and behavior.

= What happens if the AI is unsure? =

When the AI is uncertain about a comment, it flags the comment for human review rather than making a wrong decision. The comment stays in pending status for you to review manually.

= Is my data sent to third parties? =

Comment content is sent to the configured AI provider (e.g., OpenAI) for analysis. No other data is shared with third parties.

= Can I see why a comment was approved or rejected? =

Yes. The Audit Log records every decision with the AI's reasoning, the model used, and token consumption.

== Screenshots ==

1. Settings page — Configure AI provider, API key, and system prompt
2. Queue management — View and manage pending moderation items
3. Audit log — Review past moderation decisions with detailed reasoning
4. Statistics dashboard — Track moderation activity and approval rates

== External Services ==

This plugin connects to third-party AI API services to perform comment moderation analysis.

= OpenAI API =

This plugin sends comment data to the OpenAI API (or a compatible API endpoint configured by the user) for AI-powered moderation analysis.

**What data is sent:**
* Comment content (text)
* Comment author name, email, and IP address
* Post title and excerpt (for context)
* Parent comment content (if the comment is a reply)

**When data is sent:**
* Data is sent when the WP-Cron queue processor runs to moderate pending comments
* Data is also sent when an administrator manually triggers queue processing or tests the API connection

**Service details:**
* Default API endpoint: https://api.openai.com/v1
* Users can configure a custom API Base URL to use alternative OpenAI-compatible services
* OpenAI Terms of Use: https://openai.com/terms/
* OpenAI Privacy Policy: https://openai.com/privacy/

== Development ==

The source code for the compiled JavaScript files in the `build/` directory can be found in the `src/` directory of this plugin, or on GitHub:
https://github.com/Xenon-XG/CommentGuard-WordPress

To build from source:

1. Run `npm install`
2. Run `npm run build`

== Changelog ==

= 1.1.3 =
* Fix Contributors username to match WordPress.org profile
* Add External Services section declaring OpenAI API usage
* Add Development section with source code link and build instructions
* Rename JS global variable to use plugin-specific prefix (commentguardData)
* Rename WP-Cron schedule to use plugin-specific prefix (commentguard_every_)

= 1.0.0 =
* Initial release
* OpenAI provider with function calling (AI Agent mode)
* Async moderation queue with configurable WP-Cron interval
* React SPA settings page with multi-language support (Chinese / English)
* Queue management with retry and delete
* Audit log with detail view and bulk clear
* Statistics dashboard
* Comment list AI status column
* Re-moderation action
* Clean uninstall (removes all plugin data)

== Upgrade Notice ==

= 1.0.0 =
Initial release of CommentGuard.
