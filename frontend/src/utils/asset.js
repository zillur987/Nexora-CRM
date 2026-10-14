/**
 * Resolve a file in public/assets (works with any Vite `base`).
 * Returns an absolute URL: `url()` inside a CSS custom property is resolved against the
 * *stylesheet*, not the page, so a relative path would break once CSS is bundled into /assets/.
 */
export const asset = (path) => new URL(`${import.meta.env.BASE_URL}assets/${path}`, document.baseURI).href;

/** Inline-style object that sets the `--img` custom property the theme CSS reads. */
export const imgVar = (path) => ({ '--img': `url('${asset(path)}')` });
