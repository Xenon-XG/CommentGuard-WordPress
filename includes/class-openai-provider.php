<?php
/**
 * OpenAI Provider Implementation
 *
 * Implements the AIProviderInterface for OpenAI's Chat Completions API
 * with function calling (tool use) support.
 */

namespace flavor\flavor;

defined('ABSPATH') || exit;

class OpenAIProvider implements AIProviderInterface
{
    const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    const DEFAULT_MODEL = 'gpt-4o-mini';
    const TIMEOUT = 120;

    public function get_name(): string
    {
        return 'OpenAI';
    }

    public function get_id(): string
    {
        return 'openai';
    }

    public function get_models(): array
    {
        return [
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini (推荐)', 'description' => '成本低、速度快，适合审核任务'],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => '更强能力，成本较高'],
            ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 Mini', 'description' => '混合推理模型'],
            ['id' => 'gpt-4.1-nano', 'name' => 'GPT-4.1 Nano', 'description' => '超低成本'],
        ];
    }

    /**
     * Send chat completion request to OpenAI API
     */
    public function chat(string $api_key, string $model, array $messages, array $tools = [], array $options = []): array
    {
        $base_url = $options['base_url'] ?? self::DEFAULT_BASE_URL;
        $base_url = rtrim($base_url, '/');

        $body = [
            'model' => $model ?: self::DEFAULT_MODEL,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
        ];

        if (!empty($tools)) {
            $body['tools'] = $this->format_tools($tools);
            $body['tool_choice'] = 'required';
        }

        $response = wp_remote_post($base_url . '/chat/completions', [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
                'tool_calls' => null,
                'content' => null,
                'usage' => null,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $body['error']['message'] ?? __('Unknown API error', 'commentguard');
            return [
                'success' => false,
                'error' => sprintf('[%d] %s', $status_code, $error_msg),
                'tool_calls' => null,
                'content' => null,
                'usage' => null,
            ];
        }

        $choice = $body['choices'][0] ?? null;
        if (!$choice) {
            return [
                'success' => false,
                'error' => __('No response from AI model', 'commentguard'),
                'tool_calls' => null,
                'content' => null,
                'usage' => null,
            ];
        }

        $message = $choice['message'] ?? [];
        $result = [
            'success' => true,
            'error' => null,
            'content' => $message['content'] ?? null,
            'tool_calls' => null,
            'usage' => $body['usage'] ?? null,
        ];

        // Parse tool calls
        if (!empty($message['tool_calls'])) {
            $result['tool_calls'] = [];
            foreach ($message['tool_calls'] as $tool_call) {
                $result['tool_calls'][] = [
                    'name' => $tool_call['function']['name'] ?? '',
                    'arguments' => json_decode($tool_call['function']['arguments'] ?? '{}', true),
                ];
            }
        }

        return $result;
    }

    /**
     * Validate API key by making a simple models list request
     */
    public function validate_api_key(string $api_key, array $options = []): array
    {
        $base_url = $options['base_url'] ?? self::DEFAULT_BASE_URL;
        $base_url = rtrim($base_url, '/');

        $response = wp_remote_get($base_url . '/models', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            return ['valid' => true, 'error' => null];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error = $body['error']['message'] ?? __('Invalid API key', 'commentguard');
        return ['valid' => false, 'error' => $error];
    }

    /**
     * Format tools into OpenAI function calling format
     */
    private function format_tools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ];
        }
        return $formatted;
    }
}
