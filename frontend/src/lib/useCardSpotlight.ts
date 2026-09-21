import { useEffect } from 'react';

/**
 * Cursor spotlight for cards: one delegated pointer listener writes the pointer position into
 * `--mx` / `--my` on the card under the cursor; the CSS in index.css draws the glow from them.
 * Desktop pointers only (touch has no hover), throttled to one write per frame.
 */
export function useCardSpotlight(): void {
  useEffect(() => {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    let frame = 0;
    const onMove = (event: PointerEvent) => {
      if (frame) return;
      const { target, clientX, clientY } = event;
      frame = requestAnimationFrame(() => {
        frame = 0;
        const card = target instanceof Element ? target.closest<HTMLElement>('[data-slot="card"]') : null;
        if (!card) return;
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${clientX - rect.left}px`);
        card.style.setProperty('--my', `${clientY - rect.top}px`);
      });
    };

    document.addEventListener('pointermove', onMove, { passive: true });
    return () => {
      document.removeEventListener('pointermove', onMove);
      if (frame) cancelAnimationFrame(frame);
    };
  }, []);
}
