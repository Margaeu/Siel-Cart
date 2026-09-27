import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Replies are untrusted model output. Allow only the markup used in chat,
// and let DOMPurify strip unsafe link protocols and all event attributes.
export function renderChatMarkdown(text) {
    return DOMPurify.sanitize(marked.parse(text, { breaks: true, gfm: true }), {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'blockquote'],
        ALLOWED_ATTR: ['href', 'title', 'start'],
        ALLOW_DATA_ATTR: false,
        ALLOW_ARIA_ATTR: false,
    });
}

window.renderChatMarkdown = renderChatMarkdown;
