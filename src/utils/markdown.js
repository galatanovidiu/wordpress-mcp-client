import { marked } from "marked";
import DOMPurify from "dompurify";

// Configure marked for better rendering
marked.setOptions({
  breaks: true,
  gfm: true,
  sanitize: false, // We'll use DOMPurify for sanitization
});

/**
 * Parse markdown and sanitize the output HTML
 * @param {string} markdown - The markdown content to parse
 * @returns {string} - Sanitized HTML
 */
export const parseMarkdown = (markdown) => {
  if (!markdown) return markdown;

  try {
    // Parse markdown to HTML
    const html = marked.parse(markdown);

    // Sanitize HTML with DOMPurify
    const sanitizedHTML = DOMPurify.sanitize(html, {
      ALLOWED_TAGS: [
        "h1",
        "h2",
        "h3",
        "h4",
        "h5",
        "h6",
        "p",
        "br",
        "strong",
        "em",
        "u",
        "del",
        "ul",
        "ol",
        "li",
        "blockquote",
        "pre",
        "code",
        "table",
        "thead",
        "tbody",
        "tr",
        "th",
        "td",
        "a",
        "img",
      ],
      ALLOWED_ATTR: ["href", "target", "rel", "src", "alt", "title"],
      ALLOW_DATA_ATTR: false,
    });

    return sanitizedHTML;
  } catch (error) {
    console.error("Error parsing markdown:", error);
    return markdown;
  }
};
