import './bootstrap.js';
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/tokens.css';
import './styles/app.css';
import './styles/crop.css';
import './styles/recipe.css';
import './styles/movies.css';
import './styles/mcp.css';

// Embedded in an iframe, e.g. in slides: hide the scrollbars, scrolling still works
if (window.top !== window.self) {
    document.documentElement.classList.add('in-iframe');
}
