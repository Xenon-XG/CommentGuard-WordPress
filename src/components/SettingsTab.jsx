import { useState, useCallback } from '@wordpress/element';
import {
    Card, CardBody, CardHeader,
    TextControl, TextareaControl, SelectControl,
    ToggleControl, Button, Spinner
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useLang } from '../i18n';

export default function SettingsTab({ initialSettings, providers, defaultSystemPrompt, showNotice }) {
    const { t } = useLang();
    const [settings, setSettings] = useState(initialSettings || {});
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [newApiKey, setNewApiKey] = useState('');

    const updateSetting = useCallback((key, value) => {
        setSettings(prev => ({ ...prev, [key]: value }));
    }, []);

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = { ...settings };
            if (newApiKey) {
                payload.api_key = newApiKey;
            } else {
                delete payload.api_key;
            }
            delete payload.api_key_masked;
            delete payload.api_key_set;

            const result = await apiFetch({
                path: '/ai-moderator/v1/settings',
                method: 'POST',
                data: payload,
            });
            showNotice(t('settings.saved'), 'success');

            // Update local state and global source with server-returned settings
            if (result.settings) {
                setSettings(result.settings);
                // Sync to global so tab switches get fresh data
                if (window.aiCommentModerator) {
                    window.aiCommentModerator.settings = result.settings;
                }
            }
            setNewApiKey('');
        } catch (err) {
            showNotice(err.message || t('settings.save_failed'), 'error');
        }
        setSaving(false);
    };

    const handleTest = async () => {
        setTesting(true);
        try {
            const res = await apiFetch({
                path: '/ai-moderator/v1/test',
                method: 'POST',
                data: {
                    provider: settings.ai_provider,
                    api_key: newApiKey || '',
                    base_url: settings.api_base_url || '',
                },
            });
            showNotice(res.message, 'success');
        } catch (err) {
            const data = err.data || err;
            showNotice(data.message || t('settings.test_failed'), 'error');
        }
        setTesting(false);
    };

    return (
        <div className="acm-settings-tab">
            <Card className="acm-card">
                <CardHeader><h2>{t('settings.enable_title')}</h2></CardHeader>
                <CardBody>
                    <ToggleControl
                        label={t('settings.enable_label')}
                        help={settings.enabled ? t('settings.enable_help_on') : t('settings.enable_help_off')}
                        checked={settings.enabled}
                        onChange={(val) => updateSetting('enabled', val)}
                    />
                </CardBody>
            </Card>

            <Card className="acm-card">
                <CardHeader><h2>{t('settings.ai_config_title')}</h2></CardHeader>
                <CardBody>
                    <SelectControl
                        label={t('settings.provider')}
                        value={settings.ai_provider}
                        options={providers?.map(p => ({ label: p.name, value: p.id })) || []}
                        onChange={(val) => updateSetting('ai_provider', val)}
                    />
                    <TextControl
                        label={t('settings.model')}
                        value={settings.ai_model || ''}
                        placeholder="gpt-4o-mini"
                        onChange={(val) => updateSetting('ai_model', val)}
                        help={t('settings.model_help')}
                    />
                    <div className="acm-api-key-field">
                        <TextControl
                            label="API Key"
                            type="password"
                            value={newApiKey}
                            placeholder={settings.api_key_set ? settings.api_key_masked : t('settings.api_key_placeholder')}
                            onChange={setNewApiKey}
                            help={settings.api_key_set ? t('settings.api_key_help_set') : t('settings.api_key_help_unset')}
                        />
                    </div>
                    <TextControl
                        label={t('settings.base_url')}
                        value={settings.api_base_url || ''}
                        placeholder="https://api.openai.com/v1"
                        onChange={(val) => updateSetting('api_base_url', val)}
                        help={t('settings.base_url_help')}
                    />
                    <div className="acm-button-group">
                        <Button variant="secondary" onClick={handleTest} disabled={testing}>
                            {testing && <Spinner />}
                            {t('settings.test_connection')}
                        </Button>
                    </div>
                </CardBody>
            </Card>

            <Card className="acm-card">
                <CardHeader><h2>{t('settings.moderation_title')}</h2></CardHeader>
                <CardBody>
                    <ToggleControl
                        label={t('settings.skip_admins')}
                        help={t('settings.skip_admins_help')}
                        checked={settings.skip_admins}
                        onChange={(val) => updateSetting('skip_admins', val)}
                    />
                    <ToggleControl
                        label={t('settings.moderate_approved')}
                        help={t('settings.moderate_approved_help')}
                        checked={settings.auto_queue_approved}
                        onChange={(val) => updateSetting('auto_queue_approved', val)}
                    />
                    <ToggleControl
                        label={t('settings.enable_audit_log')}
                        help={t('settings.enable_audit_log_help')}
                        checked={settings.audit_log_enabled}
                        onChange={(val) => updateSetting('audit_log_enabled', val)}
                    />
                    <TextControl
                        label={t('settings.cleanup_days')}
                        type="number"
                        value={settings.cleanup_days}
                        onChange={(val) => updateSetting('cleanup_days', parseInt(val) || 30)}
                        min={1} max={365}
                        help={t('settings.cleanup_days_help')}
                    />
                    <TextControl
                        label={t('settings.cron_interval')}
                        type="number"
                        value={settings.cron_interval || 1}
                        onChange={(val) => updateSetting('cron_interval', Math.max(1, parseInt(val) || 1))}
                        min={1} max={60}
                        help={t('settings.cron_interval_help')}
                    />
                </CardBody>
            </Card>

            <Card className="acm-card">
                <CardHeader><h2>{t('settings.prompt_title')}</h2></CardHeader>
                <CardBody>
                    <TextareaControl
                        value={settings.system_prompt || defaultSystemPrompt || ''}
                        onChange={(val) => updateSetting('system_prompt', val)}
                        rows={12}
                        help={t('settings.prompt_help')}
                    />
                    <Button variant="link" isDestructive onClick={() => updateSetting('system_prompt', '')}>
                        {t('settings.prompt_restore')}
                    </Button>
                </CardBody>
            </Card>

            <div className="acm-save-bar">
                <Button variant="primary" onClick={handleSave} disabled={saving} className="acm-save-btn">
                    {saving && <Spinner />}
                    {t('settings.save')}
                </Button>
            </div>
        </div>
    );
}
