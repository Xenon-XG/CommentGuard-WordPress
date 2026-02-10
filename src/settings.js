import { createRoot } from '@wordpress/element';
import App from './components/App';
import './styles/admin.css';

const root = document.getElementById('ai-comment-moderator-root');
if (root) {
    createRoot(root).render(<App />);
}
