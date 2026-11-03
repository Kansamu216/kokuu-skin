'use strict'
;(self.webpackChunkkokuu_skin_server =
  self.webpackChunkkokuu_skin_server || []).push([
  [188],
  {
    3188: (e, t, n) => {
      n.r(t), n.d(t, { default: () => i })
      var a = n(6703),
        s = n(2065),
        d = n(88)
      async function i(e) {
        const t = e.target
        t.disabled = !0
        const n = t.textContent
        t.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${(0, d.t)(
          'admin.downloading',
        )}`
        const { code: i, message: o } = await (0, a.v_)(
          '/admin/update/download',
        )
        ;(t.textContent = n),
          (t.disabled = !1),
          await (0, s.K)({ mode: 'alert', text: o }),
          0 === i && (location.href = '/')
      }
      const o = document.querySelector('#update')
      null == o || o.addEventListener('click', i)
    },
  },
])
//# sourceMappingURL=188.a4adb68.js.map
