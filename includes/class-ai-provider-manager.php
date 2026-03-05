<?php
/**
 * AI Provider Manager
 *
 * Registry for all AI providers. Allows registering new providers
 * and retrieving the currently active one based on settings.
 */

namespace Xenon\CommentGuard;

defined('ABSPATH') || exit;

class AIProviderManager
{
    private static $instance = null;

    /** @var AIProviderInterface[] */
    private $providers = [];

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register built-in providers
        $this->register_provider(new OpenAIProvider());

        /**
         * Action to register additional AI providers.
         *
         * Example:
         *   add_action('flavor_flavor_register_providers', function($manager) {
         *       $manager->register_provider(new GeminiProvider());
         *   });
         */
        do_action('flavor_flavor_register_providers', $this);
    }

    /**
     * Register an AI provider
     */
    public function register_provider(AIProviderInterface $provider): void
    {
        $this->providers[$provider->get_id()] = $provider;
    }

    /**
     * Get a provider by ID
     */
    public function get_provider(string $id): ?AIProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * Get all registered providers
     * @return AIProviderInterface[]
     */
    public function get_all_providers(): array
    {
        return $this->providers;
    }

    /**
     * Get the currently active provider based on settings
     */
    public function get_active_provider(): ?AIProviderInterface
    {
        $settings = get_option('flavor_flavor_settings', []);
        $provider_id = $settings['ai_provider'] ?? 'openai';
        return $this->get_provider($provider_id);
    }

    /**
     * Get provider info for frontend display
     */
    public function get_providers_info(): array
    {
        $info = [];
        foreach ($this->providers as $provider) {
            $info[] = [
                'id' => $provider->get_id(),
                'name' => $provider->get_name(),
                'models' => $provider->get_models(),
            ];
        }
        return $info;
    }
}
