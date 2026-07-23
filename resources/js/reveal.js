import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// GSAP ScrollTrigger only fires onEnter on a detected enter *transition* —
// content already inside the "start" threshold at creation time (common for
// anything above the fold, e.g. dashboard stat cards) can end up permanently
// stuck with isActive:true but onEnter never called, since there was no
// transition to detect. Measure with plain geometry (deterministic, no GSAP
// timing/state ambiguity) and fire immediately for what's already in view;
// only hand the rest to ScrollTrigger, which only ever needs to detect real
// future scrolling for those.
function createRevealTrigger(vars, thresholdPercent) {
  const rect = vars.trigger.getBoundingClientRect();
  if (rect.top <= window.innerHeight * thresholdPercent) {
    vars.onEnter();
    return;
  }
  ScrollTrigger.create(vars);
}

export function initReveal() {
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) {
    document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
    document.querySelectorAll('[data-countup]').forEach((el) => {
      el.textContent = Number(el.dataset.countup).toLocaleString('id-ID');
    });
    return;
  }

  // Single reveal (skip items inside a stagger group, handled separately)
  document.querySelectorAll('[data-reveal]:not([data-reveal-group] [data-reveal])').forEach((el) => {
    createRevealTrigger({
      trigger: el, start: 'top 85%', once: true,
      onEnter: () => el.classList.add('is-visible'),
    }, 0.85);
  });

  // Staggered group
  document.querySelectorAll('[data-reveal-group]').forEach((group) => {
    const items = group.querySelectorAll('[data-reveal]');
    createRevealTrigger({
      trigger: group, start: 'top 80%', once: true,
      onEnter: () => items.forEach((el, i) => setTimeout(() => el.classList.add('is-visible'), i * 80)),
    }, 0.80);
  });

  // Count-up numbers
  document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = parseFloat(el.dataset.countup) || 0;
    createRevealTrigger({
      trigger: el, start: 'top 90%', once: true,
      onEnter: () => {
        const obj = { v: 0 };
        gsap.to(obj, {
          v: target, duration: 1.2, ease: 'power2.out',
          onUpdate: () => { el.textContent = Math.round(obj.v).toLocaleString('id-ID'); },
        });
      },
    }, 0.90);
  });

  // Subtle parallax on decorative background layers only
  document.querySelectorAll('[data-parallax]').forEach((el) => {
    gsap.to(el, {
      yPercent: 12, ease: 'none',
      scrollTrigger: { trigger: el.parentElement, scrub: true },
    });
  });
}
