// Sync mobile/desktop calendar frames from the SAME Turbo response (no 2nd full-page fetch).

const CALENDAR_FRAME_IDS = new Set(['calendar-frame', 'calendar-desktop-frame']);

document.addEventListener('turbo:before-fetch-response', async (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLElement) || !CALENDAR_FRAME_IDS.has(frame.id)) {
        return;
    }

    const otherId = frame.id === 'calendar-frame' ? 'calendar-desktop-frame' : 'calendar-frame';
    const other = document.getElementById(otherId);
    if (!other) {
        return;
    }

    try {
        const html = await event.detail.fetchResponse.responseHTML;
        if (!html) {
            return;
        }
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const remote = doc.getElementById(otherId);
        if (remote) {
            other.innerHTML = remote.innerHTML;
        }
    } catch {
        // La frame visible reste correcte même si la sync échoue.
    }
});
