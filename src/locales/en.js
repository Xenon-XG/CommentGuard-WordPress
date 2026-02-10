/**
 * English translations
 *
 * To add a new language, copy this file, translate all values,
 * and register it in ./index.js
 */
export default {
    // App
    'app.title': 'CommentGuard',
    'app.tabs.settings': 'Settings',
    'app.tabs.queue': 'Queue',
    'app.tabs.logs': 'Audit Log',
    'app.tabs.stats': 'Statistics',
    'app.moderation_warning': '⚠️ WordPress comment moderation queue is not enabled.',
    'app.moderation_link': 'Go to Discussion Settings to enable "Comment must be manually approved"',

    // Settings — Enable
    'settings.enable_title': 'Enable AI Moderation',
    'settings.enable_label': 'Enable AI Comment Moderation',
    'settings.enable_help_on': 'AI moderation is enabled. New comments will be queued automatically.',
    'settings.enable_help_off': 'When enabled, new comments will be reviewed by AI automatically.',

    // Settings — AI config
    'settings.ai_config_title': 'AI API Configuration',
    'settings.provider': 'AI Provider',
    'settings.model': 'AI Model',
    'settings.model_help': 'Enter model name, e.g. gpt-4o-mini, gpt-4o, deepseek-chat',
    'settings.base_url': 'API Base URL (Optional)',
    'settings.base_url_help': 'Fill in if using a proxy or custom endpoint. Leave empty for default.',
    'settings.test_connection': 'Test Connection',
    'settings.api_key_placeholder': 'Enter your API Key',
    'settings.api_key_help_set': 'Saved. Leave empty to keep current key, enter new value to overwrite.',
    'settings.api_key_help_unset': 'Enter your AI provider API Key',
    'settings.saved': 'Settings saved',
    'settings.save_failed': 'Save failed',
    'settings.test_failed': 'Connection test failed',

    // Settings — Moderation
    'settings.moderation_title': 'Moderation Settings',
    'settings.skip_admins': 'Skip Admin Comments',
    'settings.skip_admins_help': 'Comments from administrators bypass AI moderation',
    'settings.moderate_approved': 'Moderate Approved Comments',
    'settings.moderate_approved_help': 'Also moderate comments auto-approved by WordPress (e.g. whitelisted users)',
    'settings.enable_audit_log': 'Enable Audit Log',
    'settings.enable_audit_log_help': 'Record details of each AI review, including reasons and token usage',
    'settings.cleanup_days': 'Log Retention Days',
    'settings.cleanup_days_help': 'Queue records and audit logs older than this will be auto-cleaned',
    'settings.cron_interval': 'Queue Trigger Interval (minutes)',
    'settings.cron_interval_help': 'Set the interval for WP-Cron to auto-process the queue. Save and refresh to take effect.',

    // Settings — System prompt
    'settings.prompt_title': 'Custom System Prompt',
    'settings.prompt_help': 'Edit the system prompt for AI moderation directly.',
    'settings.prompt_restore': 'Restore Default Prompt',
    'settings.save': 'Save Settings',

    // Queue
    'queue.all_status': 'All Statuses',
    'queue.pending': 'Pending',
    'queue.processing': 'Processing',
    'queue.completed': 'Completed',
    'queue.error': 'Error',
    'queue.records': 'records',
    'queue.process': 'Process Queue',
    'queue.refresh': 'Refresh',
    'queue.empty': 'No queue records',
    'queue.col_comment': 'Comment',
    'queue.col_author': 'Author',
    'queue.col_post': 'Article',
    'queue.col_status': 'Status',
    'queue.col_result': 'Result',
    'queue.col_reason': 'Reason',
    'queue.col_time': 'Time',
    'queue.col_actions': 'Actions',
    'queue.comment_deleted': 'Comment deleted',
    'queue.retry_success': 'Added to retry queue',
    'queue.operation_failed': 'Operation failed',
    'queue.delete_failed': 'Delete failed',
    'queue.retry': 'Retry',
    'queue.delete': 'Delete',
    'queue.prev_page': 'Previous',
    'queue.next_page': 'Next',
    'queue.process_failed': 'Process failed',

    // Logs
    'logs.all_actions': 'All Actions',
    'logs.approved': 'Approved',
    'logs.rejected': 'Rejected',
    'logs.flagged': 'Flagged',
    'logs.empty': 'No audit logs. Make sure "Audit Log" is enabled in settings.',
    'logs.col_content': 'Comment Content',
    'logs.col_reason': 'Review Reason',
    'logs.col_model': 'Model',
    'logs.clear_all': 'Clear All Logs',
    'logs.clear_confirm': 'Are you sure you want to clear all audit logs? This cannot be undone.',
    'logs.cleared': 'Logs cleared',
    'logs.clear_failed': 'Clear failed',
    'logs.detail': 'Details',
    'logs.collapse': 'Collapse',
    'logs.full_content': 'Full Comment Content',
    'logs.full_reason': 'Full Review Reason',
    'logs.provider': 'AI Provider',
    'logs.token_usage': 'Token Usage',

    // Stats
    'stats.total': 'Total',
    'stats.pending': 'Pending',
    'stats.approved': 'Approved',
    'stats.rejected': 'Rejected',
    'stats.flagged': 'Flagged',
    'stats.error': 'Error',
    'stats.load_failed': 'Failed to load statistics',
    'stats.refresh': 'Refresh Stats',

    // Shared
    'shared.result_approved': 'Approved',
    'shared.result_rejected': 'Rejected',
    'shared.result_flagged': 'Flagged',
};
