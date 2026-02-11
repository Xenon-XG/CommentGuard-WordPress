import { createRoot } from '@wordpress/element';
import App from './components/App';
import './styles/admin.css';

const root = document.getElementById('commentguard-root');
if (root) {
    createRoot(root).render(<App />);
}
