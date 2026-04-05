import { Card, CardBody, CardHeader, ExternalLink } from '@wordpress/components';
import { useLang } from '../i18n';

const { version } = window.commentguardData || {};

export default function AboutTab() {
    const { t } = useLang();

    return (
        <div className="acm-about-tab">
            {/* License Section */}
            <Card className="acm-card">
                <CardHeader>
                    <h2 className="acm-card-title">{t('about.license_title')}</h2>
                </CardHeader>
                <CardBody>
                    <ol className="acm-about-list">
                        <li>{t('about.license_1')}</li>
                        <li>{t('about.license_2')}</li>
                    </ol>
                </CardBody>
            </Card>

            {/* Usage Notes */}
            <Card className="acm-card">
                <CardHeader>
                    <h2 className="acm-card-title">{t('about.usage_title')}</h2>
                </CardHeader>
                <CardBody>
                    <ol className="acm-about-list">
                        <li>{t('about.usage_1')}</li>
                        <li>{t('about.usage_2')}</li>
                        <li>{t('about.usage_3')}</li>
                        <li>{t('about.usage_4')}</li>
                    </ol>
                </CardBody>
            </Card>

            {/* Plugin Info Table */}
            <Card className="acm-card">
                <CardHeader>
                    <h2 className="acm-card-title">{t('about.info_title')}</h2>
                </CardHeader>
                <CardBody>
                    <table className="acm-about-table">
                        <tbody>
                            <tr>
                                <th>{t('about.info_name')}</th>
                                <td>CommentGuard</td>
                            </tr>
                            <tr>
                                <th>{t('about.info_version')}</th>
                                <td>{version || '1.0.0'}</td>
                            </tr>
                            <tr>
                                <th>{t('about.info_author')}</th>
                                <td>Xenon</td>
                            </tr>
                            <tr>
                                <th>{t('about.info_license')}</th>
                                <td>GPLv2 or later</td>
                            </tr>
                            <tr>
                                <th>{t('about.info_php')}</th>
                                <td>7.4+</td>
                            </tr>
                            <tr>
                                <th>{t('about.info_wp')}</th>
                                <td>6.0+</td>
                            </tr>
                        </tbody>
                    </table>
                </CardBody>
            </Card>

            {/* GitHub Link */}
            <Card className="acm-card">
                <CardHeader>
                    <h2 className="acm-card-title">{t('about.links_title')}</h2>
                </CardHeader>
                <CardBody>
                    <div className="acm-about-links">
                        <ExternalLink href="https://github.com/Xenon-XG/CommentGuard-WordPress">
                            GitHub — Xenon-XG/CommentGuard-WordPress
                        </ExternalLink>
                    </div>
                </CardBody>
            </Card>
        </div>
    );
}
