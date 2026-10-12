/** Small helpers shared by the Task list and form. */

/** Soft badge from a hex colour: tinted background, solid text, same colour for the border. */
export function badgeStyle(color) {
    if (!color || !/^#[0-9a-f]{6}$/i.test(color)) return {};
    return { color, backgroundColor: `${color}1a`, border: `1px solid ${color}33` };
}

/** Palette offered when a new type / priority / status is added from the "+" button. */
export const OPTION_COLORS = ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#667085'];

export const SCOPES = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'today', label: 'Due today' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'completed', label: 'Completed' },
];
