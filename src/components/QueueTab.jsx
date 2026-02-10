import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useLang } from '../i18n';

export default function QueueTab({ showNotice }) {
    const { t } = useLang();
    const [items, setItems] = useState([]);
    const [total, setTotal] = useState(0);
    const [page, setPage] = useState(1);
    const [pages, setPages] = useState(1);
    const [statusFilter, setStatusFilter] = useState('');
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);

    const STATUS_LABELS = {
        pending: { text: '⏳ ' + t('queue.pending'), className: 'acm-status-pending' },
        processing: { text: '🔄 ' + t('queue.processing'), className: 'acm-status-processing' },
        completed: { text: '✅ ' + t('queue.completed'), className: 'acm-status-completed' },
        error: { text: '❌ ' + t('queue.error'), className: 'acm-status-error' },
    };

    const RESULT_LABELS = {
        approved: { text: '✅ ' + t('shared.result_approved'), className: 'acm-result-approved' },
        rejected: { text: '🚫 ' + t('shared.result_rejected'), className: 'acm-result-rejected' },
        flagged: { text: '⚠️ ' + t('shared.result_flagged'), className: 'acm-result-flagged' },
    };

    const fetchQueue = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({ page, per_page: 15 });
            if (statusFilter) params.append('status', statusFilter);
            const res = await apiFetch({ path: `/ai-moderator/v1/queue?${params}` });
            setItems(res.items || []);
            setTotal(res.total || 0);
            setPages(res.pages || 1);
        } catch (err) {
            console.error('Failed to fetch queue:', err);
        }
        setLoading(false);
    }, [page, statusFilter]);

    useEffect(() => { fetchQueue(); }, [fetchQueue]);

    const handleRetry = async (id) => {
        try {
            await apiFetch({ path: `/ai-moderator/v1/queue/retry/${id}`, method: 'POST' });
            showNotice(t('queue.retry_success'));
            fetchQueue();
        } catch (err) {
            showNotice(err.message || t('queue.operation_failed'), 'error');
        }
    };

    const handleDelete = async (id) => {
        try {
            await apiFetch({ path: `/ai-moderator/v1/queue/delete/${id}`, method: 'DELETE' });
            fetchQueue();
        } catch (err) {
            showNotice(err.message || t('queue.delete_failed'), 'error');
        }
    };

    const handleManualProcess = async () => {
        setProcessing(true);
        try {
            const res = await apiFetch({ path: '/ai-moderator/v1/process', method: 'POST' });
            showNotice(res.message);
            fetchQueue();
        } catch (err) {
            showNotice(err.message || t('queue.process_failed'), 'error');
        }
        setProcessing(false);
    };

    return (
        <div className="acm-queue-tab">
            <div className="acm-queue-toolbar">
                <SelectControl
                    value={statusFilter}
                    options={[
                        { label: t('queue.all_status'), value: '' },
                        { label: t('queue.pending'), value: 'pending' },
                        { label: t('queue.processing'), value: 'processing' },
                        { label: t('queue.completed'), value: 'completed' },
                        { label: t('queue.error'), value: 'error' },
                    ]}
                    onChange={(val) => { setStatusFilter(val); setPage(1); }}
                    __nextHasNoMarginBottom
                />
                <span className="acm-queue-count">{total} {t('queue.records')}</span>
                <Button variant="secondary" onClick={handleManualProcess} disabled={processing} className="acm-process-btn">
                    {processing && <Spinner />}
                    {t('queue.process')}
                </Button>
                <Button variant="tertiary" onClick={fetchQueue} disabled={loading}>
                    {t('queue.refresh')}
                </Button>
            </div>

            {loading ? (
                <div className="acm-loading"><Spinner /></div>
            ) : items.length === 0 ? (
                <div className="acm-empty">{t('queue.empty')}</div>
            ) : (
                <table className="acm-table widefat striped">
                    <thead>
                        <tr>
                            <th>{t('queue.col_comment')}</th>
                            <th>{t('queue.col_author')}</th>
                            <th>{t('queue.col_post')}</th>
                            <th>{t('queue.col_status')}</th>
                            <th>{t('queue.col_result')}</th>
                            <th>{t('queue.col_reason')}</th>
                            <th>{t('queue.col_time')}</th>
                            <th>{t('queue.col_actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item.id}>
                                <td className="acm-comment-cell">
                                    {item.comment_content
                                        ? item.comment_content.substring(0, 60) + (item.comment_content.length > 60 ? '...' : '')
                                        : <em>{t('queue.comment_deleted')}</em>
                                    }
                                </td>
                                <td>{item.comment_author || '—'}</td>
                                <td>{item.post_title ? item.post_title.substring(0, 30) : '—'}</td>
                                <td>
                                    <span className={STATUS_LABELS[item.status]?.className || ''}>
                                        {STATUS_LABELS[item.status]?.text || item.status}
                                    </span>
                                </td>
                                <td>
                                    {item.result ? (
                                        <span className={RESULT_LABELS[item.result]?.className || ''}>
                                            {RESULT_LABELS[item.result]?.text || item.result}
                                        </span>
                                    ) : '—'}
                                </td>
                                <td className="acm-reason-cell" title={item.reason || ''}>
                                    {item.reason ? item.reason.substring(0, 40) + (item.reason.length > 40 ? '...' : '') : '—'}
                                </td>
                                <td className="acm-time-cell">{item.created_at || '—'}</td>
                                <td className="acm-actions-cell">
                                    {(item.status === 'error' || item.status === 'completed') && (
                                        <Button variant="link" onClick={() => handleRetry(item.id)}>
                                            {t('queue.retry')}
                                        </Button>
                                    )}
                                    <Button variant="link" isDestructive onClick={() => handleDelete(item.id)}>
                                        {t('queue.delete')}
                                    </Button>
                                </td>
                            </tr>
                        ))}
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
