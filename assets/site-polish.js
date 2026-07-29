(function(){
  const dropdowns = Array.from(document.querySelectorAll('.pkg-nav'));
  if (!dropdowns.length) return;

  const closeDropdown = (item) => {
    item.classList.remove('is-open');
    const trigger = item.querySelector('.pkg-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  };

  const closeAll = (except) => {
    dropdowns.forEach((item) => {
      if (item !== except) closeDropdown(item);
    });
  };

  dropdowns.forEach((item, index) => {
    const trigger = item.querySelector('.pkg-trigger');
    const menu = item.querySelector('.pkg-mega');
    if (!trigger || !menu) return;

    const menuId = menu.id || `packages-menu-${index + 1}`;
    menu.id = menuId;
    trigger.setAttribute('aria-haspopup', 'true');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', menuId);

    let closeTimer;
    const open = () => {
      clearTimeout(closeTimer);
      closeAll(item);
      item.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    };
    const scheduleClose = () => {
      clearTimeout(closeTimer);
      closeTimer = setTimeout(() => closeDropdown(item), 240);
    };

    item.addEventListener('mouseenter', open);
    item.addEventListener('mouseleave', scheduleClose);
    menu.addEventListener('mouseenter', open);
    menu.addEventListener('mouseleave', scheduleClose);
    item.addEventListener('focusin', open);
    item.addEventListener('focusout', () => {
      setTimeout(() => {
        if (!item.contains(document.activeElement)) closeDropdown(item);
      }, 0);
    });

    trigger.addEventListener('click', (event) => {
      const isCoarse = window.matchMedia('(hover: none), (pointer: coarse)').matches;
      if (!isCoarse) return;
      event.preventDefault();
      item.classList.contains('is-open') ? closeDropdown(item) : open();
    });

    trigger.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowDown' && event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      open();
      const firstLink = menu.querySelector('a');
      if (firstLink) firstLink.focus();
    });

    menu.addEventListener('click', (event) => {
      if (event.target.closest('a')) closeDropdown(item);
    });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.pkg-nav')) closeAll();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAll();
  });
})();

(function(){
  const root = document.documentElement;
  const syncThemeAttribute = () => {
    root.setAttribute('data-theme', root.classList.contains('light') ? 'light' : 'dark');
  };

  syncThemeAttribute();

  new MutationObserver(syncThemeAttribute).observe(root, {
    attributes: true,
    attributeFilter: ['class']
  });
})();

(function(){
  if (window.__fcgBackToTopReady) return;
  window.__fcgBackToTopReady = true;

  document.querySelectorAll('.back-to-top').forEach((button) => {
    button.classList.remove('is-visible');
    button.setAttribute('aria-hidden', 'true');
    button.setAttribute('tabindex', '-1');
  });
})();

/* FCG Floating Contact Widget JS (scoped) --------------------------- */
(function(){
  if (window.__fcgContactWidgetReady) return;
  window.__fcgContactWidgetReady = true;

  const WA_NUMBER = '27638095519';
  const PHONE_NUMBER = '+27115680279';
  const EMAIL = 'info@futurecreativegroup.co.za';
  const WA_MESSAGE = encodeURIComponent('Hello Future Creative Group, I would like assistance with your services.');
  const MENU_ID = 'fcg-contact-menu';
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  document.querySelectorAll('#wa-fab, #wafab').forEach((button) => {
    button.setAttribute('aria-hidden', 'true');
    button.setAttribute('tabindex', '-1');
  });

  const createSvg = (name) => {
    const svgs = {
      headset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11a9 9 0 0 1 18 0"/><path d="M5 11h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M18 11h1a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-4"/></svg>',
      phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.35 1.9.66 2.81a2 2 0 0 1-.45 2.11L8.05 9.91a16 16 0 0 0 6.04 6.04l1.27-1.27a2 2 0 0 1 2.11-.45c.91.31 1.85.53 2.81.66A2 2 0 0 1 22 16.92Z"/></svg>',
      email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
      arrowUp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg>',
      wa: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>'
    };
    return svgs[name] || '';
  };

  const wrapper = document.createElement('div');
  wrapper.className = 'fcg-contact-wrap';

  const back = document.createElement('button');
  back.type = 'button';
  back.className = 'fcg-back-to-top';
  back.setAttribute('aria-label','Back to top');
  back.innerHTML = createSvg('arrowUp');
  wrapper.appendChild(back);

  const widget = document.createElement('div');
  widget.className = 'fcg-contact-widget';

  const main = document.createElement('button');
  main.type = 'button';
  main.className = 'fcg-contact-main';
  main.setAttribute('aria-expanded','false');
  main.setAttribute('aria-haspopup','menu');
  main.setAttribute('aria-controls', MENU_ID);
  main.setAttribute('aria-label','Contact us');
  main.innerHTML = createSvg('headset');

  const menu = document.createElement('div');
  menu.className = 'fcg-contact-menu';
  menu.id = MENU_ID;
  menu.setAttribute('role','menu');
  menu.setAttribute('aria-hidden','true');

  const makeItem = (opts) => {
    const a = document.createElement('a');
    a.className = 'fcg-contact-item ' + (opts.cls||'');
    a.href = opts.href;
    a.target = opts.target || '_self';
    if (opts.rel) a.rel = opts.rel;
    a.setAttribute('aria-label', opts.label);
    a.setAttribute('role','menuitem');
    a.setAttribute('tabindex','-1');
    a.innerHTML = `<span class="fcg-item-icon">${opts.icon}</span><span class="fcg-item-label">${opts.label}</span>`;
    return a;
  };

  const waLink = `https://wa.me/${WA_NUMBER}?text=${WA_MESSAGE}`;
  const items = [
    makeItem({cls:'fcg-wa',href:waLink,target:'_blank',rel:'noopener',icon:createSvg('wa'),label:'WhatsApp'}),
    makeItem({cls:'fcg-call',href:`tel:${PHONE_NUMBER}`,icon:createSvg('phone'),label:'Call Us'}),
    makeItem({cls:'fcg-email',href:`mailto:${EMAIL}`,icon:createSvg('email'),label:'Email Us'})
  ];

  items.forEach((it)=>menu.appendChild(it));
  widget.appendChild(menu);
  widget.appendChild(main);
  wrapper.appendChild(widget);
  document.body.appendChild(wrapper);

  let focusTimer;
  const setMenuState = (isOpen, focusItem) => {
    clearTimeout(focusTimer);
    widget.classList.toggle('is-open', isOpen);
    main.setAttribute('aria-expanded','false');
    if (isOpen) main.setAttribute('aria-expanded', 'true');
    menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    items.forEach((item) => item.setAttribute('tabindex', isOpen ? '0' : '-1'));
    if (isOpen && focusItem) {
      focusTimer = setTimeout(() => {
        if (widget.classList.contains('is-open')) items[0].focus();
      }, 300);
    }
  };

  const closeMenu = (returnFocus) => {
    setMenuState(false, false);
    if (returnFocus) main.focus();
  };

  const openMenu = (focusItem) => {
    document.dispatchEvent(new CustomEvent('fcg:closeAllContactMenus', { detail: { source: widget } }));
    setMenuState(true, focusItem);
  };

  main.addEventListener('click', (e)=>{
    e.stopPropagation();
    widget.classList.contains('is-open') ? closeMenu(false) : openMenu(false);
  });

  document.addEventListener('click', (e)=>{
    if (!wrapper.contains(e.target)) closeMenu(false);
  });

  main.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
      e.preventDefault();
      openMenu(true);
    }
  });

  menu.addEventListener('keydown', (e) => {
    const currentIndex = items.indexOf(document.activeElement);

    if (e.key === 'Escape') {
      e.preventDefault();
      closeMenu(true);
      return;
    }

    if (currentIndex < 0) return;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      const direction = e.key === 'ArrowDown' ? 1 : -1;
      const nextIndex = (currentIndex + direction + items.length) % items.length;
      items[nextIndex].focus();
    }

    if (e.key === 'Home') {
      e.preventDefault();
      items[0].focus();
    }

    if (e.key === 'End') {
      e.preventDefault();
      items[items.length - 1].focus();
    }
  });

  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') closeMenu(wrapper.contains(document.activeElement));
  });

  document.addEventListener('fcg:closeAllContactMenus', (e) => {
    if (e.detail && e.detail.source === widget) return;
    closeMenu(false);
  });

  let scheduled = false;
  const syncBackToTop = () => {
    back.classList.toggle('is-visible', window.scrollY > 500);
  };
  const onScroll = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      syncBackToTop();
    });
  };
  window.addEventListener('scroll', onScroll, { passive:true });
  back.addEventListener('click', (e)=>{
    e.preventDefault();
    if (prefersReducedMotion.matches) return window.scrollTo(0,0);
    window.scrollTo({ top:0, behavior:'smooth' });
  });

  items.forEach((it)=>{
    it.addEventListener('click', ()=>{
      closeMenu(false);
    });
  });

  syncBackToTop();
})();
/* end FCG widget JS */
