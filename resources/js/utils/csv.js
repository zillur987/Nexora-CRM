const FORMULA_PREFIX = /^[=+\-@\t\r]/;

/** Quotes a value for CSV and neutralises spreadsheet formula injection. */
function escapeCell(value) {
    let text = value == null ? '' : String(value);
    if (FORMULA_PREFIX.test(text)) text = `'${text}`;
    return `"${text.replaceAll('"', '""')}"`;
}

/**
 * Builds a CSV in the browser and triggers a download.
 *
 * @param {string} filename
 * @param {string[]} headers
 * @param {Array<Array<string|number|null|undefined>>} rows
 */
export function downloadCsv(filename, headers, rows) {
    const body = [headers, ...rows].map((row) => row.map(escapeCell).join(',')).join('\r\n');
    // The BOM makes Excel read the file as UTF-8.
    const blob = new Blob(['\uFEFF', body], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}