/**
 * Normalises Laravel error responses into { message, fields }.
 *  - 422 validation:  { message, errors: { field: [..] } }
 *  - business rule:   { error: { code, message } }
 */
export function parseApiError(err) {
    const res = err?.response;
    if (!res) return { message: 'Network error. Please try again.', fields: {} };

    const data = res.data ?? {};

    if (data.errors) {
        const fields = Object.fromEntries(
            Object.entries(data.errors).map(([key, msgs]) => [key, msgs[0]]),
        );
        return { message: data.message ?? 'Please fix the highlighted fields.', fields };
    }

    return { message: data.error?.message ?? data.message ?? 'Something went wrong.', fields: {} };
}
