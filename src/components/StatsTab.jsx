import { useState, useEffect } from '@wordpress/element';
import { Card, CardBody, Spinner, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useLang } from '../i18n';

export default function StatsTab() {
    const { t } = useLang();
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    const fetchStats = async () => {
        setLoading(true);
        try {
            const res = await apiFetch({ path: '/ai-moderator/v1/stats' });
            setStats(res);
        } catch (err) {
            console.error('Failed to fetch stats:', err);
        }
        setLoading(false);
    };

    useEffect(() => { fetchStats(); }, []);

    if (loading) return <div className="acm-loading"><Spinner /></div>;
    if (!stats) return <div className="acm-empty">{t('stats.load_failed')}</div>;

    const rate = (count) => stats.completed > 0 ? Math.round((count / stats.completed) * 100) : 0;

    return (
        <div className="acm-stats-tab">
            <div className="acm-stats-grid">
                <Card className="acm-stat-card">
                    <CardBody>
                        <div className="acm-stat-number">{stats.total}</div>
                        <div className="acm-stat-label">{t('stats.total')}</div>
                    </CardBody>
                </Card>
                <Card className="acm-stat-card acm-stat-pending">
                    <CardBody>
                        <div className="acm-stat-number">{stats.pending}</div>
                        <div className="acm-stat-label">{t('stats.pending')}</div>
                    </CardBody>
                </Card>
                <Card className="acm-stat-card acm-stat-approved">
                    <CardBody>
                        <div className="acm-stat-number">{stats.approved}</div>
                        <div className="acm-stat-label">{t('stats.approved')}</div>
                        <div className="acm-stat-rate">{rate(stats.approved)}%</div>
                    </CardBody>
                </Card>
                <Card className="acm-stat-card acm-stat-rejected">
                    <CardBody>
                        <div className="acm-stat-number">{stats.rejected}</div>
                        <div className="acm-stat-label">{t('stats.rejected')}</div>
                        <div className="acm-stat-rate">{rate(stats.rejected)}%</div>
                    </CardBody>
                </Card>
                <Card className="acm-stat-card acm-stat-flagged">
                    <CardBody>
                        <div className="acm-stat-number">{stats.flagged}</div>
                        <div className="acm-stat-label">{t('stats.flagged')}</div>
                        <div className="acm-stat-rate">{rate(stats.flagged)}%</div>
                    </CardBody>
                </Card>
                <Card className="acm-stat-card acm-stat-error">
                    <CardBody>
                        <div className="acm-stat-number">{stats.error}</div>
                        <div className="acm-stat-label">{t('stats.error')}</div>
                    </CardBody>
                </Card>
            </div>
            <div className="acm-stats-refresh">
                <Button variant="tertiary" onClick={fetchStats} disabled={loading}>
                    {t('stats.refresh')}
                </Button>
            </div>
        </div>
    );
}
