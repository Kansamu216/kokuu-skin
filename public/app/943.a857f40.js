'use strict'
;(self.webpackChunkkokuu_skin_server =
  self.webpackChunkkokuu_skin_server || []).push([
  [943],
  {
    4943: (t, e, a) => {
      a.r(e)
      var s = a(8023),
        r = a(4470),
        o = a(6379),
        l = a(8774),
        i = a(8290),
        n = a(3294),
        c = a(8690),
        u = a(7813),
        d = a(6703)
      function x(t, e, a, s) {
        r.S1(t).setOption({
          title: { text: s.label, textStyle: { color: a } },
          textStyle: { color: a },
          tooltip: { trigger: 'axis' },
          dataZoom: [
            { type: 'inside', start: 75 },
            { type: 'slider', start: 75 },
          ],
          xAxis: [{ type: 'category', boundaryGap: !1, data: s.xAxis }],
          yAxis: [{ type: 'value', minInterval: 1, boundaryGap: !1 }],
          series: [
            {
              name: s.label,
              type: 'line',
              itemStyle: { color: e },
              areaStyle: { color: e },
              data: s.data,
              smooth: !0,
            },
          ],
        })
      }
      s.D([o.N, l.N, i.N, n.N, c.N, u.N]),
        (async function () {
          const t = document.querySelector('#chart-users-registration'),
            e = document.querySelector('#chart-textures-upload')
          if (!t || !e) return
          const a = document.body.classList.contains('dark-mode'),
            s = a ? '#fff' : '#000',
            r = await (0, d.U2)('/admin/chart')
          x(t, a ? '#3498db' : '#17a2b8', s, {
            label: r.labels[0],
            xAxis: r.xAxis,
            data: r.data[0],
          }),
            x(e, '#6f42c1', s, {
              label: r.labels[1],
              xAxis: r.xAxis,
              data: r.data[1],
            })
        })()
    },
  },
])
//# sourceMappingURL=943.a857f40.js.map
