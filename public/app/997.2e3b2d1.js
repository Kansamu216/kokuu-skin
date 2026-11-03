'use strict'
;(self.webpackChunkkokuu_skin_server =
  self.webpackChunkkokuu_skin_server || []).push([
  [997],
  {
    7997: (e, r, a) => {
      a.r(r),
        a.d(r, {
          registerNavbarPicker: () => u,
          registerSidebarPicker: () => k,
        })
      var s = a(7027),
        i = a(9553),
        c = a(8170),
        t = a(6650),
        n = a(5709),
        l = a(6008),
        b = a(7554)
      function u(e, r, a) {
        const u = (0, s.R)(r, 'click').pipe(
          (0, n.U)((e) => e.target),
          (0, l.h)((e) => 'INPUT' === e.tagName),
          (0, n.U)((e) => e.value),
        )
        ;(0, i.T)((0, c.of)(a), u)
          .pipe((0, b.G)())
          .subscribe(([r, a]) => {
            e.classList.replace(`navbar-${r}`, `navbar-${a}`)
          })
        const [o, d] = (0, t.u)(u, (e) =>
          ['light', 'warning', 'white', 'orange', 'lime'].includes(e),
        )
        o.subscribe(() => {
          e.classList.remove('navbar-dark'), e.classList.add('navbar-light')
        }),
          d.subscribe(() => {
            e.classList.remove('navbar-light'), e.classList.add('navbar-dark')
          })
      }
      const o = document.querySelector('.wrapper > nav'),
        d = document.querySelector('#navbar-color-picker')
      function k(e, { dark: r, light: a }, t) {
        const u = (0, i.T)((0, s.R)(r, 'click'), (0, s.R)(a, 'click')).pipe(
          (0, n.U)((e) => e.target),
          (0, l.h)((e) => 'INPUT' === e.tagName),
          (0, n.U)((e) => e.value),
        )
        ;(0, i.T)((0, c.of)(t), u)
          .pipe((0, b.G)())
          .subscribe(([r, a]) => {
            e.classList.replace(`sidebar-${r}`, `sidebar-${a}`)
          })
      }
      o && d && u(o, d, blessing.extra.navbar || 'white')
      const p = document.querySelector('.main-sidebar'),
        v = document.querySelector('#sidebar-dark-picker'),
        g = document.querySelector('#sidebar-light-picker')
      p &&
        v &&
        g &&
        k(p, { dark: v, light: g }, blessing.extra.sidebar || 'dark-primary')
    },
  },
])
//# sourceMappingURL=997.2e3b2d1.js.map
