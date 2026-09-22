import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.min.css';

// Initializes the lightbox for project gallery links.
document.addEventListener('DOMContentLoaded', () => {
    GLightbox({
        selector: '[data-glightbox]',
    });
});
