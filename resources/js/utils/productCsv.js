/**
 * Single source of truth for the product CSV export: the header row and the
 * value readers live side by side so they can never drift apart.
 */

const COLUMNS = [
    ['sku', (p) => p.sku],
    ['name', (p) => p.name],
    ['product_type', (p) => p.product_type],
    ['category', (p) => p.category?.name],
    ['brand', (p) => p.brand],
    ['barcode', (p) => p.barcode],
    ['currency', (p) => p.currency],
    ['sale_price', (p) => p.sale_price],
    ['cost_price', (p) => p.cost_price],
    ['tax_rate', (p) => p.tax_rate],
    ['unit_of_measure', (p) => p.unit_of_measure],
    ['track_inventory', (p) => (p.track_inventory ? 'yes' : 'no')],
    ['quantity_on_hand', (p) => (p.track_inventory ? p.quantity_on_hand : '')],
    ['reorder_level', (p) => (p.track_inventory ? p.reorder_level : '')],
    ['status', (p) => (p.is_active ? 'active' : 'inactive')],
    ['tags', (p) => (p.tags ?? []).join('; ')],
    ['website_url', (p) => p.website_url],
    ['short_description', (p) => p.short_description],
];

export const PRODUCT_CSV_COLUMNS = COLUMNS.map(([header]) => header);

/** One CSV row, in PRODUCT_CSV_COLUMNS order. Missing values become empty cells. */
export const productToCsvRow = (product) => COLUMNS.map(([, read]) => read(product) ?? '');
