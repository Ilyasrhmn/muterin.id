import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

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
    ScrollTrigger.create({
      trigger: el, start: 'top 85%', once: true,
      onEnter: () => el.classList.add('is-visible'),
    });
  });

  // Staggered group
  document.querySelectorAll('[data-reveal-group]').forEach((group) => {
    const items = group.querySelectorAll('[data-reveal]');
    ScrollTrigger.create({
      trigger: group, start: 'top 80%', once: true,
      onEnter: () => items.forEach((el, i) => setTimeout(() => el.classList.add('is-visible'), i * 80)),
    });
  });

  // Count-up numbers
  document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = parseFloat(el.dataset.countup) || 0;
    ScrollTrigger.create({
      trigger: el, start: 'top 90%', once: true,
      onEnter: () => {
        const obj = { v: 0 };
        gsap.to(obj, {
          v: target, duration: 1.2, ease: 'power2.out',
          onUpdate: () => { el.textContent = Math.round(obj.v).toLocaleString('id-ID'); },
        });
      },
    });
  });

  // Subtle parallax on decorative background layers only
  document.querySelectorAll('[data-parallax]').forEach((el) => {
    gsap.to(el, {
      yPercent: 12, ease: 'none',
      scrollTrigger: { trigger: el.parentElement, scrub: true },
    });
  });
}
