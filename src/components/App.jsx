import { useState, useCallback } from '@wordpress/element';
import { TabPanel, Notice, Button } from '@wordpress/components';
import SettingsTab from './SettingsTab';
import QueueTab from './QueueTab';
import LogsTab from './LogsTab';
import StatsTab from './StatsTab';
import AboutTab from './AboutTab';
import { LangProvider, useLang } from '../i18n';

const { settings: initialSettings, providers, defaultSystemPrompt, wpModerationEnabled, discussionSettingsUrl, version } = window.commentguardData || {};

function AppInner() {
    const [globalNotice, setGlobalNotice] = useState(null);
    const { lang, changeLang, langOptions, t } = useLang();

    const showNotice = useCallback((message, type = 'success') => {
        setGlobalNotice({ message, type });
        setTimeout(() => setGlobalNotice(null), 4000);
    }, []);

    const tabs = [
        { name: 'settings', title: t('app.tabs.settings'), className: 'acm-tab' },
        { name: 'queue', title: t('app.tabs.queue'), className: 'acm-tab' },
        { name: 'logs', title: t('app.tabs.logs'), className: 'acm-tab' },
        { name: 'stats', title: t('app.tabs.stats'), className: 'acm-tab' },
        { name: 'about', title: t('app.tabs.about'), className: 'acm-tab' },
    ];

    return (
        <div className="acm-app">
            <div className="acm-header">
                <h1 className="acm-title">
                    <span className="acm-logo">🤖</span>
                    {t('app.title')}
                    <span className="acm-version">v{version}</span>
                </h1>
                <select
                    className="acm-lang-select"
                    value={lang}
                    onChange={(e) => changeLang(e.target.value)}
                >
                    {langOptions.map(opt => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>
            </div>

            {!wpModerationEnabled && (
                <Notice status="warning" isDismissible={false} className="acm-moderation-notice">
                    {t('app.moderation_warning')}
                    {' '}
                    <a href={discussionSettingsUrl}>
                        {t('app.moderation_link')}
                    </a>
                </Notice>
            )}

            {globalNotice && (
                <Notice
                    status={globalNotice.type}
                    isDismissible={true}
                    onDismiss={() => setGlobalNotice(null)}
                    className="acm-global-notice"
                >
                    {globalNotice.message}
                </Notice>
            )}

            <TabPanel tabs={tabs} className="acm-tab-panel">
                {(tab) => {
                    switch (tab.name) {
                        case 'settings':
                            return (
                                <SettingsTab
                                    initialSettings={initialSettings}
                                    providers={providers}
                                    defaultSystemPrompt={defaultSystemPrompt}
                                    showNotice={showNotice}
                                />
                            );
                        case 'queue':
                            return <QueueTab showNotice={showNotice} />;
                        case 'logs':
                            return <LogsTab showNotice={showNotice} />;
                        case 'stats':
                            return <StatsTab />;
                        case 'about':
                            return <AboutTab />;
                        default:
                            return null;
                    }
                }}
            </TabPanel>
        </div>
    );
}

export default function App() {
    return (
        <LangProvider>
            <AppInner />
        </LangProvider>
    );
}
