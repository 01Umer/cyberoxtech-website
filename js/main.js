/* ============================================================
   CYBEROX TECHNOLOGIES — GLOBAL JAVASCRIPT
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

  /* ---- NAVIGATION SCROLL EFFECT ---- */
  const nav = document.querySelector('.nav');
  if (nav) {
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 40);
    });
  }

  /* ---- MOBILE HAMBURGER MENU ---- */
  const hamburger = document.querySelector('.nav-hamburger');
  const mobileNav = document.querySelector('.nav-mobile');
  if (hamburger && mobileNav) {
    hamburger.addEventListener('click', () => {
      const isOpen = mobileNav.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', isOpen);
      document.body.style.overflow = isOpen ? 'hidden' : '';
      const spans = hamburger.querySelectorAll('span');
      if (isOpen) {
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
      } else {
        spans.forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      }
    });
    // Close on link click
    mobileNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileNav.classList.remove('open');
        document.body.style.overflow = '';
        hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      });
    });
  }

  /* ---- MOBILE ACCORDION SECTIONS ---- */
  const mobileAccordions = document.querySelectorAll('.mobile-accordion-toggle');
  mobileAccordions.forEach(toggle => {
    toggle.addEventListener('click', () => {
      const content = toggle.nextElementSibling;
      const isOpen = content.style.maxHeight;
      document.querySelectorAll('.mobile-accordion-content').forEach(c => c.style.maxHeight = '');
      document.querySelectorAll('.mobile-accordion-toggle').forEach(t => t.classList.remove('open'));
      if (!isOpen) {
        content.style.maxHeight = content.scrollHeight + 'px';
        toggle.classList.add('open');
      }
    });
  });

  /* ---- FAQ ACCORDION ---- */
  document.querySelectorAll('.faq-question').forEach(question => {
    question.addEventListener('click', () => {
      const item = question.parentElement;
      const isOpen = item.classList.contains('open');
      // Close all
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      // Open clicked if it was closed
      if (!isOpen) item.classList.add('open');
    });
  });

  /* ---- SCROLL REVEAL ---- */
  const reveals = document.querySelectorAll('.reveal');
  if (reveals.length > 0) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    reveals.forEach(el => observer.observe(el));
  }

  /* ---- COUNTER ANIMATION ---- */
  function animateCounter(el) {
    const target = parseInt(el.getAttribute('data-target'));
    const suffix = el.getAttribute('data-suffix') || '';
    const duration = 1800;
    const steps = 60;
    const increment = target / steps;
    let current = 0;
    let step = 0;
    const timer = setInterval(() => {
      step++;
      current = Math.min(Math.round(increment * step), target);
      el.textContent = current + suffix;
      if (step >= steps) clearInterval(timer);
    }, duration / steps);
  }

  const counters = document.querySelectorAll('[data-target]');
  if (counters.length > 0) {
    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    counters.forEach(el => counterObserver.observe(el));
  }

  /* ---- ACTIVE NAV LINK ---- */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href && currentPath.includes(href) && href !== '/') {
      link.classList.add('active');
    } else if (href === '/' && currentPath === '/') {
      link.classList.add('active');
    }
    if (href === 'index.html' && (currentPath === '/' || currentPath.endsWith('index.html'))) {
      link.classList.add('active');
    }
  });

  /* ---- FORM SUBMISSION ---- */
  document.querySelectorAll('.contact-form, .demo-form, .newsletter-form, .gap-form').forEach(form => {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = form.querySelector('[type="submit"]');
      const originalText = btn.textContent;

      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      btn.textContent = 'Submitting...';
      btn.disabled = true;

      const action = form.getAttribute('action');

      if (!action) {
        btn.textContent = '✓ Submitted — We\'ll be in touch!';
        btn.style.background = 'var(--green)';
        form.reset();
        setTimeout(() => {
          btn.textContent = originalText;
          btn.disabled = false;
          btn.style.background = '';
        }, 4000);
        return;
      }

      try {
        const response = await fetch(action, {
          method: form.getAttribute('method') || 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json' },
        });
        const result = await response.json().catch(() => ({}));

        if (!response.ok || result.ok === false) {
          throw new Error(result.message || 'Submission failed.');
        }

        btn.textContent = '✓ Request sent — We\'ll be in touch!';
        btn.style.background = 'var(--green)';
        form.reset();
      } catch (error) {
        btn.textContent = 'Could not send. Try again.';
        btn.style.background = 'var(--red)';
        if (window.showToast) {
          window.showToast(error.message || 'Could not send your request.', 'error');
        }
      } finally {
        setTimeout(() => {
          btn.textContent = originalText;
          btn.disabled = false;
          btn.style.background = '';
        }, 4000);
      }
    });
  });

  /* ---- SMOOTH SCROLL FOR ANCHOR LINKS ---- */
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  /* ---- PROGRESS BAR ANIMATION ---- */
  const progressBars = document.querySelectorAll('.progress-fill');
  if (progressBars.length > 0) {
    const progressObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const bar = entry.target;
          const width = bar.getAttribute('data-width') || '0';
          bar.style.width = width + '%';
          progressObserver.unobserve(bar);
        }
      });
    }, { threshold: 0.5 });
    progressBars.forEach(bar => {
      bar.style.width = '0';
      progressObserver.observe(bar);
    });
  }

  /* ---- NOTIFICATION TOAST ---- */
  window.showToast = function (message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
      position: fixed; bottom: 24px; right: 24px; z-index: 9999;
      background: ${type === 'success' ? 'var(--green)' : 'var(--red)'};
      color: white; padding: 14px 24px; border-radius: 8px;
      font-weight: 600; font-size: 0.9rem;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
      animation: fadeUp 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
  };

  /* ---- COPY EMAIL ---- */
  document.querySelectorAll('.copy-email').forEach(el => {
    el.addEventListener('click', () => {
      navigator.clipboard.writeText(el.dataset.email).then(() => {
        window.showToast('Email copied to clipboard!');
      });
    });
  });

});
