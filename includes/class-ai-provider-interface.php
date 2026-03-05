<?php
/**
 * AI Provider Interface
 *
 * All AI providers must implement this interface.
 * This ensures a consistent API across OpenAI, Gemini, DeepSeek, etc.
 */

namespace Xenon\CommentGuard;

defined('ABSPATH') || exit;

interface AIProviderInterface
{
    /**
     * Get the provider display name
     * @return string e.g. "OpenAI"
     */
    public function get_name(): string;

    /**
     * Get the provider unique ID
     * @return string e.g. "openai"
     */
    public function get_id(): string;

    /**
     * Get available models for this provider
     * @return array e.g. [['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini'], ...]
     */
    public function get_models(): array;

    /**
     * Send a chat completion request with optional tool/function calling
     *
     * @param string $api_key The API key
     * @param string $model   The model ID
     * @param array  $messages Chat messages array
     * @param array  $tools   Tool definitions for function calling
     * @param array  $options Additional options (base_url, temperature, etc.)
     * @return array {
     *   'success' => bool,
     *   'tool_calls' => array|null,  // [{name, arguments}]
     *   'content' => string|null,     // Text response if no tool call
     *   'usage' => array|null,        // Token usage info
     *   'error' => string|null
     * }
     */
    public function chat(string $api_key, string $model, array $messages, array $tools = [], array $options = []): array;

    /**
     * Validate an API key
     *
     * @param string $api_key
     * @param array  $options Additional options (base_url etc.)
     * @return array { 'valid' => bool, 'error' => string|null }
     */
    public function validate_api_key(string $api_key, array $options = []): array;
}
