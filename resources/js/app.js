import './bootstrap';

/**
 * The rich text editor is a large dependency used on one screen, so it is
 * fetched only when a page actually carries one. That keeps it out of the
 * bundle every other page downloads, and keeps a failure inside it — an older
 * browser, a bad chunk — from taking the menus and the sidebar down with it.
 */
function loadRichText() {
    if (!document.querySelector('[data-rich-text]')) {
        return;
    }

    import('./rich-text')
        .then(({ initRichText }) => initRichText())
        .catch((error) => console.error('[hrms] rich text editor failed to load', error));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadRichText);
} else {
    loadRichText();
}
