<?php
/**
 * Moderation Agent
 *
 * The core AI Agent that analyzes comments and makes moderation decisions.
 * Uses function calling (tool use) to execute approve/reject/flag actions.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class ModerationAgent
{
    private static $instance = null;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get the default system prompt
     */
    public function get_default_system_prompt(): string
    {
        return "You are an AI comment moderation agent for a website. Your job is to review user comments and decide whether to approve, reject, or flag them for human review.\n\n" .
        "## Moderation Rules:\n\n" .
        "**APPROVE** a comment if it:\n" .
        "- Is a genuine, relevant response to the article\n" .
        "- Contains constructive feedback, questions, or opinions\n" .
        "- Is polite and respectful, even if critical\n" .
        "- May contain minor grammar or spelling errors (that's fine)\n\n" .
        "**REJECT** a comment if it:\n" .
        "- Is obvious spam (ads, promotional links, unrelated products)\n" .
        "- Contains hate speech, slurs, or severe harassment\n" .
        "- Is clearly automated/bot-generated gibberish\n" .
        "- Contains malicious links or phishing attempts\n" .
        "- Is purely offensive with no constructive value\n\n" .
        "**FLAG FOR REVIEW** a comment if:\n" .
        "- You are unsure about the intent (could be sarcasm, cultural context)\n" .
        "- It contains mild profanity but might be acceptable in context\n" .
        "- It discusses sensitive topics that require human judgment\n" .
        "- It seems borderline between acceptable and unacceptable\n\n" .
        "## Important:\n" .
        "- Always provide a clear, concise reason for your decision\n" .
        "- Consider the context of the article when judging relevance\n" .
        "- Be fair and unbiased in your moderation\n" .
        "- When in doubt, flag for human review rather than rejecting\n" .
        "- Provide your reason in the same language as the comment";
    }

    /**
     * Get the tool definitions for function calling
     */
    public function get_tools(): array
    {
        return [
            [
                'name' => 'approve_comment',
                'description' => 'Approve the comment for publication. Use this when the comment is genuine, relevant, and follows community guidelines.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['reason'],
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Brief explanation of why the comment is approved. Use the same language as the comment.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'reject_comment',
                'description' => 'Reject the comment and move it to trash. Use this for spam, hate speech, or clearly harmful content.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['reason'],
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Brief explanation of why the comment is rejected. Use the same language as the comment.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'flag_for_review',
                'description' => 'Flag the comment for human review. Use this when you are unsure or the comment requires human judgment.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['reason'],
                    'properties' => [
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Brief explanation of why the comment needs human review. Use the same language as the comment.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the user message with comment context
     */
    private function build_user_message(array $context): string
    {
        $parts = [];

        $parts[] = '## Comment to Review';
        $parts[] = '';
        $parts[] = '**Author:** ' . ($context['author_name'] ?? 'Anonymous');
        $parts[] = '**Email:** ' . ($context['author_email'] ?? 'N/A');
        $parts[] = '**IP:** ' . ($context['author_ip'] ?? 'N/A');
        $parts[] = '';
        $parts[] = '**Comment Content:**';
        $parts[] = $context['comment_content'] ?? '';
        $parts[] = '';

        if (!empty($context['post_title'])) {
            $parts[] = '## Article Context';
            $parts[] = '';
            $parts[] = '**Article Title:** ' . $context['post_title'];

            if (!empty($context['post_excerpt'])) {
                $parts[] = '**Article Excerpt:** ' . $context['post_excerpt'];
            }
            $parts[] = '';
        }

        if (!empty($context['parent_comment'])) {
            $parts[] = '## Parent Comment (this is a reply)';
            $parts[] = $context['parent_comment'];
            $parts[] = '';
        }

        return implode("\n", $parts);
    }

    /**
     * Moderate a comment using the AI Agent
     *
     * @param array $context Comment context data
     * @return array {
     *   'success' => bool,
     *   'action'  => string (approve|reject|flag),
     *   'reason'  => string,
     *   'usage'   => array|null,
     *   'error'   => string|null
     * }
     */
    public function moderate(array $context): array
    {
        $provider_manager = AIProviderManager::get_instance();
        $provider = $provider_manager->get_active_provider();

        if (!$provider) {
            return [
                'success' => false,
                'action' => 'flag',
                'reason' => __('No AI provider configured', 'commentguard'),
                'usage' => null,
                'error' => __('No AI provider configured', 'commentguard'),
            ];
        }

        $settings = get_option('flavor_flavor_settings', []);
        $api_key = $settings['api_key'] ?? '';
        $model = $settings['ai_model'] ?? '';
        $base_url = $settings['api_base_url'] ?? '';

        if (empty($api_key)) {
            return [
                'success' => false,
                'action' => 'flag',
                'reason' => __('API key not configured', 'commentguard'),
                'usage' => null,
                'error' => __('API key not configured', 'commentguard'),
            ];
        }

        // Build messages
        $system_prompt = $settings['system_prompt'] ?? $this->get_default_system_prompt();

        // Append language instruction based on UI language setting
        $lang_name = $settings['ui_language_name'] ?? '中文';
        $system_prompt .= "\n\nIMPORTANT: You MUST respond (including the reason) in {$lang_name}.";
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $this->build_user_message($context)],
        ];

        $options = [];
        if (!empty($base_url)) {
            $options['base_url'] = $base_url;
        }

        // Call AI with tools
        $response = $provider->chat($api_key, $model, $messages, $this->get_tools(), $options);

        if (!$response['success']) {
            return [
                'success' => false,
                'action' => 'flag',
                'reason' => __('AI request failed', 'commentguard'),
                'usage' => $response['usage'],
                'error' => $response['error'],
            ];
        }

        // Parse tool call result
        if (empty($response['tool_calls'])) {
            // AI didn't use tools - fall back to flag
            return [
                'success' => true,
                'action' => 'flag',
                'reason' => $response['content'] ?? __('AI did not return a decision', 'commentguard'),
                'usage' => $response['usage'],
                'error' => null,
            ];
        }

        $tool_call = $response['tool_calls'][0];
        $action_map = [
            'approve_comment' => 'approve',
            'reject_comment' => 'reject',
            'flag_for_review' => 'flag',
        ];

        $action = $action_map[$tool_call['name']] ?? 'flag';
        $reason = $tool_call['arguments']['reason'] ?? __('No reason provided', 'commentguard');

        return [
            'success' => true,
            'action' => $action,
            'reason' => $reason,
            'usage' => $response['usage'],
            'error' => null,
        ];
    }
}
