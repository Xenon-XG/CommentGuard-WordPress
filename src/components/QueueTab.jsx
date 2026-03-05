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
    const [perPage, setPerPage] = useState(15);
    const [statusFilter, setStatusFilter] = useState('');
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [batchLoading, setBatchLoading] = useState(false);
    const [jumpPage, setJumpPage] = useState('');

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
        setSelectedIds([]);
        try {
            const params = new URLSearchParams({ page, per_page: perPage });
            if (statusFilter) params.append('status', statusFilter);
            const res = await apiFetch({ path: `/ai-moderator/v1/queue?${params}` });
            setItems(res.items || []);
            setTotal(res.total || 0);
            setPages(res.pages || 1);
        } catch (err) {
            console.error('Failed to fetch queue:', err);
        }
        setLoading(false);
    }, [page, perPage, statusFilter]);

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

    // Batch operations
    const handleBatchAction = async (action) => {
        if (selectedIds.length === 0) return;
        if (action === 'delete' && !window.confirm(t('batch.confirm_delete'))) return;
        setBatchLoading(true);
        try {
            const res = await apiFetch({
                path: '/ai-moderator/v1/queue/batch',
                method: 'POST',
                data: { ids: selectedIds, action },
            });
            showNotice(res.message);
            fetchQueue();
        } catch (err) {
            showNotice(err.message || t('batch.failed'), 'error');
        }
        setBatchLoading(false);
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === items.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(items.map(i => i.id));
        }
    };

    const toggleSelectItem = (id) => {
        setSelectedIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    const handleJumpPage = () => {
        const p = parseInt(jumpPage, 10);
        if (p >= 1 && p <= pages) {
            setPage(p);
            setJumpPage('');
        }
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
                <SelectControl
                    value={String(perPage)}
                    options={[
                        { label: '15 ' + t('queue.per_page'), value: '15' },
                        { label: '30 ' + t('queue.per_page'), value: '30' },
                        { label: '50 ' + t('queue.per_page'), value: '50' },
                    ]}
                    onChange={(val) => { setPerPage(Number(val)); setPage(1); }}
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

            {/* Batch action bar */}
            {selectedIds.length > 0 && (
                <div className="acm-batch-bar">
                    <span className="acm-batch-count">
                        {t('batch.selected').replace('%d', selectedIds.length)}
                    </span>
                    <Button
                        variant="secondary"
                        onClick={() => handleBatchAction('retry')}
                        disabled={batchLoading}
                    >
                        {t('batch.retry')}
                    </Button>
                    <Button
                        variant="secondary"
                        isDestructive
                        onClick={() => handleBatchAction('delete')}
                        disabled={batchLoading}
                    >
                        {batchLoading && <Spinner />}
                        {t('batch.delete')}
                    </Button>
                </div>
            )}

            {loading ? (
                <div className="acm-loading"><Spinner /></div>
            ) : items.length === 0 ? (
                <div className="acm-empty">{t('queue.empty')}</div>
            ) : (
                <table className="acm-table widefat striped">
                    <thead>
                        <tr>
                            <th className="acm-col-check">
                                <input
                                    type="checkbox"
                                    checked={selectedIds.length === items.length && items.length > 0}
                                    onChange={toggleSelectAll}
                                />
                            </th>
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
                            <tr key={item.id} className={selectedIds.includes(item.id) ? 'acm-row-selected' : ''}>
                                <td className="acm-col-check">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.includes(item.id)}
                                        onChange={() => toggleSelectItem(item.id)}
                                    />
                                </td>
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
                    <span className="acm-page-jump">
                        <input
                            type="number"
                            min="1"
                            max={pages}
                            value={jumpPage}
                            onChange={(e) => setJumpPage(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleJumpPage()}
                            placeholder={t('queue.goto_page')}
                            className="acm-page-input"
                        />
                        <Button variant="secondary" onClick={handleJumpPage} className="acm-goto-btn">
                            GO
                        </Button>
                    </span>
                </div>
            )}
        </div>
    );
}
