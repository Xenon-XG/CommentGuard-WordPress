import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useLang } from '../i18n';

export default function LogsTab({ showNotice }) {
    const { t } = useLang();
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [pages, setPages] = useState(1);
    const [actionFilter, setActionFilter] = useState('');
    const [loading, setLoading] = useState(false);
    const [clearing, setClearing] = useState(false);
    const [expandedId, setExpandedId] = useState(null);

    const ACTION_LABELS = {
        approve: { text: '✅ ' + t('logs.approved'), className: 'acm-result-approved' },
        reject: { text: '🚫 ' + t('logs.rejected'), className: 'acm-result-rejected' },
        flag: { text: '⚠️ ' + t('logs.flagged'), className: 'acm-result-flagged' },
    };

    const fetchLogs = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({ page, per_page: 20 });
            if (actionFilter) params.append('action', actionFilter);
            const res = await apiFetch({ path: `/ai-moderator/v1/logs?${params}` });
            setItems(res.items || []);
            setTotal(res.total || 0);
            setPages(res.pages || 1);
        } catch (err) {
            console.error('Failed to fetch logs:', err);
        }
        setLoading(false);
    }, [page, actionFilter]);

    useEffect(() => { fetchLogs(); }, [fetchLogs]);

    const handleClearAll = async () => {
        if (!window.confirm(t('logs.clear_confirm'))) return;
        setClearing(true);
        try {
            await apiFetch({ path: '/ai-moderator/v1/logs/clear', method: 'DELETE' });
            showNotice(t('logs.cleared'), 'success');
            setPage(1);
            fetchLogs();
        } catch (err) {
            showNotice(err.message || t('logs.clear_failed'), 'error');
        }
        setClearing(false);
    };

    const toggleExpand = (id) => {
        setExpandedId(prev => prev === id ? null : id);
    };

    const parseTokenUsage = (raw) => {
        if (!raw) return null;
        try { return typeof raw === 'string' ? JSON.parse(raw) : raw; }
        catch { return null; }
    };

    return (
        <div className="acm-logs-tab">
            <div className="acm-queue-toolbar">
                <SelectControl
                    value={actionFilter}
                    options={[
                        { label: t('logs.all_actions'), value: '' },
                        { label: t('logs.approved'), value: 'approve' },
                        { label: t('logs.rejected'), value: 'reject' },
                        { label: t('logs.flagged'), value: 'flag' },
                    ]}
                    onChange={(val) => { setActionFilter(val); setPage(1); }}
                    __nextHasNoMarginBottom
                />
                <span className="acm-queue-count">{total} {t('queue.records')}</span>
                <Button variant="tertiary" onClick={fetchLogs} disabled={loading}>
                    {t('queue.refresh')}
                </Button>
                <Button
                    variant="secondary"
                    isDestructive
                    onClick={handleClearAll}
                    disabled={clearing || total === 0}
                    className="acm-clear-logs-btn"
                >
                    {clearing && <Spinner />}
                    {t('logs.clear_all')}
                </Button>
            </div>

            {loading ? (
                <div className="acm-loading"><Spinner /></div>
            ) : items.length === 0 ? (
                <div className="acm-empty">{t('logs.empty')}</div>
            ) : (
                <table className="acm-table widefat striped">
                    <thead>
                        <tr>
                            <th>{t('logs.col_content')}</th>
                            <th>{t('queue.col_author')}</th>
                            <th>{t('queue.col_post')}</th>
                            <th>{t('queue.col_actions')}</th>
                            <th>{t('logs.col_reason')}</th>
                            <th>{t('logs.col_model')}</th>
                            <th>{t('queue.col_time')}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => {
                            const isExpanded = expandedId === item.id;
                            const usage = parseTokenUsage(item.token_usage);
                            return (
                                <>
                                    <tr key={item.id} className={isExpanded ? 'acm-row-expanded' : ''}>
                                        <td className="acm-comment-cell">
                                            {item.comment_content
                                                ? item.comment_content.substring(0, 60) + (item.comment_content.length > 60 ? '...' : '')
                                                : <em>{t('queue.comment_deleted')}</em>
                                            }
                                        </td>
                                        <td>{item.comment_author || '—'}</td>
                                        <td>{item.post_title ? item.post_title.substring(0, 30) : '—'}</td>
                                        <td>
                                            <span className={ACTION_LABELS[item.action]?.className || ''}>
                                                {ACTION_LABELS[item.action]?.text || item.action}
                                            </span>
                                        </td>
                                        <td className="acm-reason-cell" title={item.reason || ''}>
                                            {item.reason ? item.reason.substring(0, 50) + (item.reason.length > 50 ? '...' : '') : '—'}
                                        </td>
                                        <td>{item.ai_model || '—'}</td>
                                        <td className="acm-time-cell">{item.created_at || '—'}</td>
                                        <td>
                                            <Button variant="link" onClick={() => toggleExpand(item.id)}>
                                                {isExpanded ? t('logs.collapse') : t('logs.detail')}
                                            </Button>
                                        </td>
                                    </tr>
                                    {isExpanded && (
                                        <tr key={`${item.id}-detail`} className="acm-detail-row">
                                            <td colSpan={8}>
                                                <div className="acm-detail-content">
                                                    <div className="acm-detail-section">
                                                        <strong>{t('logs.full_content')}:</strong>
                                                        <p>{item.comment_content || '—'}</p>
                                                    </div>
                                                    <div className="acm-detail-section">
                                                        <strong>{t('logs.full_reason')}:</strong>
                                                        <p>{item.reason || '—'}</p>
                                                    </div>
                                                    <div className="acm-detail-row-meta">
                                                        <span><strong>{t('logs.provider')}:</strong> {item.ai_provider || '—'}</span>
                                                        <span><strong>{t('logs.col_model')}:</strong> {item.ai_model || '—'}</span>
                                                        {usage && (
                                                            <span>
                                                                <strong>{t('logs.token_usage')}:</strong>{' '}
                                                                {usage.prompt_tokens || 0} + {usage.completion_tokens || 0} = {usage.total_tokens || 0}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            );
                        })}
                    </tbody>
                </table>
            )}

            {pages > 1 && (
                <div className="acm-pagination">
                    <Button disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
                        ‹ {t('queue.prev_page')}
                    </Button>
                    <span>{page} / {pages}</span>
                    <Button disabled={page >= pages} onClick={() => setPage(p => p + 1)}>
                        {t('queue.next_page')} ›
                    </Button>
                </div>
            )}
        </div>
    );
}
