(function(){
  const nav = document.getElementById('nav');
  if (nav) {
    window.addEventListener('scroll', () => nav.classList.toggle('shrunken', scrollY > 60), { passive: true });
  }

  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  const hbg = document.getElementById('hbg');
  const drawer = document.getElementById('drawer');
  if (hbg && drawer) {
    hbg.addEventListener('click', () => {
      hbg.classList.toggle('open');
      drawer.classList.toggle('open');
      hbg.setAttribute('aria-expanded', drawer.classList.contains('open') ? 'true' : 'false');
    });
    drawer.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        hbg.classList.remove('open');
        drawer.classList.remove('open');
        hbg.setAttribute('aria-expanded', 'false');
      });
    });
  }

  const themeButton = document.getElementById('theme-toggle');
  if (themeButton) {
    const html = document.documentElement;
    const saved = localStorage.getItem('fcg-theme');
    const preferLight = window.matchMedia('(prefers-color-scheme: light)').matches;
    if (saved === 'light' || (!saved && preferLight)) html.classList.add('light');
    themeButton.addEventListener('click', () => {
      const isLight = html.classList.toggle('light');
      localStorage.setItem('fcg-theme', isLight ? 'light' : 'dark');
    });
  }

  const revealItems = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && revealItems.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('on');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' });
    revealItems.forEach((item) => io.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('on'));
  }
})();
