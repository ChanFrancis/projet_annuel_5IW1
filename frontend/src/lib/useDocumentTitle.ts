import { useEffect } from 'react';

/**
 * Sets the document title (and its meta description when provided) for the
 * lifetime of a page. Improves SEO / search-engine accessibility for a SPA.
 */
export function useDocumentTitle(title: string, description?: string): void {
  useEffect(() => {
    const previous = document.title;
    document.title = title;

    let metaEl: HTMLMetaElement | null = null;
    let previousDescription: string | null = null;
    if (description) {
      metaEl = document.querySelector('meta[name="description"]');
      if (metaEl) {
        previousDescription = metaEl.getAttribute('content');
        metaEl.setAttribute('content', description);
      }
    }

    return () => {
      document.title = previous;
      if (metaEl && previousDescription !== null) {
        metaEl.setAttribute('content', previousDescription);
      }
    };
  }, [title, description]);
}
