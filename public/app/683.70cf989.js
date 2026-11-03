;(self.webpackChunkkokuu_skin_server =
  self.webpackChunkkokuu_skin_server || []).push([
  [683],
  {
    1570: (e, s) => {
      s.w = function (e) {
        return e
      }
    },
    5996: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => n })
      var a = t(5893)
      t(7294)
      const n = () =>
        (0, a.jsx)(
          'div',
          Object.assign(
            { className: 'container text-center', title: 'Loading...' },
            { children: (0, a.jsx)('i', { className: 'fas fa-sync fa-spin' }) },
          ),
        )
    },
    9147: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => c })
      var a = t(5893),
        n = (t(7294), t(88))
      const i = (e) => {
          const s = ['page-item']
          return (
            e.active && s.push('active'),
            e.disabled && s.push('disabled'),
            e.className && s.push(e.className),
            (0, a.jsx)(
              'li',
              Object.assign(
                {
                  className: s.join(' '),
                  title: e.title,
                  onClick: (s) => {
                    s.preventDefault(), !e.disabled && e.onClick && e.onClick()
                  },
                },
                {
                  children: (0, a.jsx)(
                    'a',
                    Object.assign(
                      {
                        href: '#',
                        className: 'page-link',
                        'aria-disabled': e.disabled,
                      },
                      { children: e.children },
                    ),
                  ),
                },
              ),
            )
          )
        },
        c = (e) => {
          const { page: s, totalPages: t, onChange: c } = e
          return t < 1
            ? null
            : (0, a.jsxs)(
                'ul',
                Object.assign(
                  { className: 'pagination' },
                  {
                    children: [
                      (0, a.jsxs)(
                        i,
                        Object.assign(
                          {
                            title: (0, n.t)('vendor.datatable.prev'),
                            disabled: 1 === s,
                            onClick: () => c(s - 1),
                          },
                          {
                            children: [
                              '‹',
                              (0, a.jsx)(
                                'span',
                                Object.assign(
                                  { className: 'd-inline d-sm-none ml-1' },
                                  {
                                    children: (0, n.t)('vendor.datatable.prev'),
                                  },
                                ),
                              ),
                            ],
                          },
                        ),
                      ),
                      t < 8
                        ? Array.from({ length: t }).map((e, t) =>
                            (0, a.jsx)(
                              i,
                              Object.assign(
                                {
                                  className: 'd-none d-sm-block',
                                  active: s === t + 1,
                                  onClick: () => c(t + 1),
                                },
                                { children: t + 1 },
                              ),
                              t,
                            ),
                          )
                        : (0, a.jsxs)(a.Fragment, {
                            children: [
                              s < 4
                                ? [1, 2, 3, 4].map((e) =>
                                    (0, a.jsx)(
                                      i,
                                      Object.assign(
                                        {
                                          className: 'd-none d-sm-block',
                                          active: s === e,
                                          onClick: () => c(e),
                                        },
                                        { children: e },
                                      ),
                                      e,
                                    ),
                                  )
                                : (0, a.jsx)(
                                    i,
                                    Object.assign(
                                      {
                                        className: 'd-none d-sm-block',
                                        onClick: () => c(1),
                                      },
                                      { children: '1' },
                                    ),
                                  ),
                              (0, a.jsx)(
                                i,
                                Object.assign(
                                  {
                                    className: 'd-none d-sm-block',
                                    disabled: !0,
                                  },
                                  { children: '...' },
                                ),
                              ),
                              s > 3 &&
                                s < t - 2 &&
                                (0, a.jsxs)(a.Fragment, {
                                  children: [
                                    [s - 1, s, s + 1].map((e) =>
                                      (0, a.jsx)(
                                        i,
                                        Object.assign(
                                          {
                                            className: 'd-none d-sm-block',
                                            active: s === e,
                                            onClick: () => c(e),
                                          },
                                          { children: e },
                                        ),
                                        e,
                                      ),
                                    ),
                                    (0, a.jsx)(
                                      i,
                                      Object.assign(
                                        {
                                          className: 'd-none d-sm-block',
                                          disabled: !0,
                                        },
                                        { children: '...' },
                                      ),
                                    ),
                                  ],
                                }),
                              t - s < 3
                                ? [t - 3, t - 2, t - 1, t].map((e) =>
                                    (0, a.jsx)(
                                      i,
                                      Object.assign(
                                        {
                                          className: 'd-none d-sm-block',
                                          active: s === e,
                                          onClick: () => c(e),
                                        },
                                        { children: e },
                                      ),
                                      e,
                                    ),
                                  )
                                : (0, a.jsx)(
                                    i,
                                    Object.assign(
                                      {
                                        className: 'd-none d-sm-block',
                                        onClick: () => c(t),
                                      },
                                      { children: t },
                                    ),
                                  ),
                            ],
                          }),
                      (0, a.jsxs)(
                        i,
                        Object.assign(
                          {
                            title: (0, n.t)('vendor.datatable.next'),
                            disabled: s === t,
                            onClick: () => c(s + 1),
                          },
                          {
                            children: [
                              (0, a.jsx)(
                                'span',
                                Object.assign(
                                  { className: 'd-inline d-sm-none mr-1' },
                                  {
                                    children: (0, n.t)('vendor.datatable.next'),
                                  },
                                ),
                              ),
                              '›',
                            ],
                          },
                        ),
                      ),
                    ],
                  },
                ),
              )
        }
    },
    9341: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => n })
      var a = t(7294)
      function n(e, s) {
        const [t, n] = (0, a.useState)(s)
        return (
          (0, a.useEffect)(() => {
            n(blessing.extra[e])
          }, [e]),
          t
        )
      }
    },
    9018: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => i })
      var a = t(7294),
        n = t(3601)
      function i() {
        ;(0, a.useEffect)(() => {
          ;(0, n.j)('mounted')
        }, [])
      }
    },
    7109: (e, s, t) => {
      'use strict'
      var a, n
      t.d(s, { N: () => n, P: () => a }),
        (function (e) {
          ;(e[(e.Banned = -1)] = 'Banned'),
            (e[(e.Normal = 0)] = 'Normal'),
            (e[(e.Admin = 1)] = 'Admin'),
            (e[(e.SuperAdmin = 2)] = 'SuperAdmin')
        })(a || (a = {})),
        (function (e) {
          ;(e.Steve = 'steve'), (e.Alex = 'alex'), (e.Cape = 'cape')
        })(n || (n = {}))
    },
    6051: (e, s, t) => {
      'use strict'
      t.d(s, { G: () => n, b: () => i })
      var a = t(917)
      const n = a.iv`
  cursor: pointer;
`,
        i = a.iv`
  display: flex;
  justify-content: center;
  align-items: center;
`
    },
    7920: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => l })
      var a = t(88),
        n = t(6703),
        i = t(2065),
        c = t(8848)
      async function l(e) {
        let s
        try {
          const { value: t } = await (0, i.K)({
            mode: 'prompt',
            title: (0, a.t)('skinlib.setItemName'),
            text: (0, a.t)('skinlib.applyNotice'),
            input: e.name,
            validator: (e) => {
              if (!e) return (0, a.t)('skinlib.emptyItemName')
            },
          })
          s = t
        } catch (e) {
          return !1
        }
        const { code: t, message: l } = await n.v_(c.Z.user.closet.add(), {
          tid: e.tid,
          name: s,
        })
        return 0 === t ? i.A.success(l) : i.A.error(l), 0 === t
      }
    },
    5683: (e, s, t) => {
      'use strict'
      t.r(s), t.d(s, { default: () => Z })
      var a = t(5893),
        n = t(7294),
        i = t(1570),
        c = t(9341),
        l = t(9018),
        r = t(88),
        d = t(6703),
        o = t(2065),
        m = t(7109),
        b = t(8848),
        j = t(5996),
        g = t(9147),
        u = t(7920),
        p = t(5667)
      const h = (e) => {
        var s
        const t = [null !== (s = e.className) && void 0 !== s ? s : '']
        e.bg && t.push('btn', `bg-${e.bg}`), e.active && t.push('active')
        const n = Object.assign(Object.assign({}, e), {
          active: void 0,
          bg: void 0,
        })
        return (0, a.jsx)(
          'button',
          Object.assign(
            {},
            n,
            { className: t.join(' ') },
            { children: e.children },
          ),
        )
      }
      function x(e) {
        switch (e) {
          case m.N.Steve:
            return 'Steve'
          case m.N.Alex:
            return 'Alex'
          default:
            return (0, r.t)(`general.${e}`)
        }
      }
      const v = (e) => {
        const { filter: s, onChange: t } = e
        return (0, a.jsxs)(a.Fragment, {
          children: [
            (0, a.jsx)(
              'button',
              Object.assign(
                {
                  className: 'btn btn-default dropdown-toggle',
                  type: 'button',
                  'data-toggle': 'dropdown',
                },
                { children: x(s) },
              ),
            ),
            (0, a.jsxs)(
              'div',
              Object.assign(
                { className: 'dropdown-menu' },
                {
                  children: [
                    (0, a.jsx)(
                      h,
                      Object.assign(
                        {
                          className: 'dropdown-item',
                          active: 'skin' === s,
                          onClick: () => t('skin'),
                        },
                        { children: (0, r.t)('general.skin') },
                      ),
                    ),
                    (0, a.jsx)(
                      h,
                      Object.assign(
                        {
                          className: 'dropdown-item',
                          active: s === m.N.Steve,
                          onClick: () => t(m.N.Steve),
                        },
                        { children: 'Steve' },
                      ),
                    ),
                    (0, a.jsx)(
                      h,
                      Object.assign(
                        {
                          className: 'dropdown-item',
                          active: s === m.N.Alex,
                          onClick: () => t(m.N.Alex),
                        },
                        { children: 'Alex' },
                      ),
                    ),
                    (0, a.jsx)(
                      h,
                      Object.assign(
                        {
                          className: 'dropdown-item',
                          active: s === m.N.Cape,
                          onClick: () => t(m.N.Cape),
                        },
                        { children: (0, r.t)('general.cape') },
                      ),
                    ),
                  ],
                },
              ),
            ),
          ],
        })
      }
      var k = t(8811),
        N = t(6051)
      const f = k.Z.div`
  width: 245px;
  transition-property: box-shadow;
  transition-duration: 0.3s;
  &:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  }

  .card-body {
    background-color: #eff1f0;
  }

  img {
    height: 210px;
  }
`,
        O = k.Z.i`
  height: 24px;
`,
        w = k.Z.span`
  padding-top: 0.4rem;
`,
        C = (0, k.Z)(w)`
  ${N.G}
  max-width: 100px;
`,
        y = k.Z.a`
  ${N.G}

  i, span {
    color: ${(e) => (e.liked ? '#dc3545' : '#6c757d')};
    &:hover {
      color: ${(e) => (e.liked ? '#dc3545' : '#343a40')};
    }
  }
`,
        S = (e) => {
          const { item: s } = e,
            t = `${blessing.base_url}/skinlib/show/${s.tid}`,
            n = `${blessing.base_url}/preview/${s.tid}?height=150`,
            i = `${n}&png`
          return (0, a.jsx)(
            'a',
            Object.assign(
              {
                href: t,
                className: 'ml-3 mr-2 mb-2 d-block',
                target: '_blank',
              },
              {
                children: (0, a.jsxs)(
                  f,
                  Object.assign(
                    { className: 'card' },
                    {
                      children: [
                        (0, a.jsx)(
                          'div',
                          Object.assign(
                            { className: 'card-body' },
                            {
                              children: (0, a.jsx)(
                                'a',
                                Object.assign(
                                  { href: t, target: '_blank' },
                                  {
                                    children: (0, a.jsxs)('picture', {
                                      children: [
                                        (0, a.jsx)('source', {
                                          srcSet: n,
                                          type: 'image/webp',
                                        }),
                                        (0, a.jsx)('img', {
                                          src: i,
                                          alt: s.name,
                                          className: 'card-img-top',
                                        }),
                                      ],
                                    }),
                                  },
                                ),
                              ),
                            },
                          ),
                        ),
                        (0, a.jsxs)(
                          'div',
                          Object.assign(
                            { className: 'card-footer' },
                            {
                              children: [
                                (0, a.jsxs)(
                                  'div',
                                  Object.assign(
                                    { className: 'd-flex align-items-center' },
                                    {
                                      children: [
                                        s.public ||
                                          (0, a.jsx)(O, {
                                            className:
                                              'fas fa-lock text-warning mr-2',
                                            title: (0, r.t)('skinlib.private'),
                                          }),
                                        (0, a.jsx)(
                                          'span',
                                          Object.assign(
                                            {
                                              className:
                                                'd-block mb-1 text-truncate',
                                              title: s.name,
                                            },
                                            { children: s.name },
                                          ),
                                        ),
                                      ],
                                    },
                                  ),
                                ),
                                (0, a.jsxs)(
                                  'div',
                                  Object.assign(
                                    {
                                      className:
                                        'd-flex justify-content-between',
                                    },
                                    {
                                      children: [
                                        (0, a.jsxs)(
                                          'div',
                                          Object.assign(
                                            { className: 'd-flex' },
                                            {
                                              children: [
                                                (0, a.jsx)(
                                                  w,
                                                  Object.assign(
                                                    {
                                                      className:
                                                        'badge bg-teal mr-1',
                                                    },
                                                    { children: x(s.type) },
                                                  ),
                                                ),
                                                (0, a.jsx)(
                                                  C,
                                                  Object.assign(
                                                    {
                                                      className:
                                                        'badge bg-indigo text-truncate',
                                                      title: (0, r.t)(
                                                        'skinlib.show.uploader',
                                                      ),
                                                      onClick: (t) => {
                                                        t.preventDefault(),
                                                          e.onUploaderClick(
                                                            s.uploader,
                                                          )
                                                      },
                                                    },
                                                    { children: s.nickname },
                                                  ),
                                                ),
                                              ],
                                            },
                                          ),
                                        ),
                                        (0, a.jsxs)(
                                          y,
                                          Object.assign(
                                            {
                                              liked: e.liked,
                                              tabIndex: -1,
                                              onClick: (t) => {
                                                t.preventDefault(),
                                                  e.liked
                                                    ? e.onRemove(s)
                                                    : e.onAdd(s)
                                              },
                                            },
                                            {
                                              children: [
                                                (0, a.jsx)('i', {
                                                  className:
                                                    'fas fa-heart mr-1',
                                                }),
                                                (0, a.jsx)('span', {
                                                  children: s.likes,
                                                }),
                                              ],
                                            },
                                          ),
                                        ),
                                      ],
                                    },
                                  ),
                                ),
                              ],
                            },
                          ),
                        ),
                      ],
                    },
                  ),
                ),
              },
            ),
          )
        },
        Z = (0, i.w)(() => {
          const [e, s] = (0, n.useState)(!0),
            [t, i] = (0, n.useState)([]),
            [x, k] = (0, n.useState)([]),
            [N, f] = (0, n.useState)('skin'),
            [O, w] = (0, n.useState)(''),
            [C, y] = (0, n.useState)(''),
            [Z, A] = (0, n.useState)(0),
            [$, U] = (0, n.useState)('time'),
            [_, E] = (0, n.useState)(1),
            [I, F] = (0, n.useState)(1),
            R = (0, c.Z)('currentUid', null)
          ;(0, l.Z)(),
            (0, n.useEffect)(() => {
              const e = (e) => {
                var s, t, a, n, i
                const c = new URLSearchParams(e),
                  l = null !== (s = c.get('filter')) && void 0 !== s ? s : ''
                f(
                  ['skin', m.N.Steve, m.N.Alex, m.N.Cape].includes(l)
                    ? l
                    : 'skin',
                )
                const r = decodeURIComponent(
                  null !== (t = c.get('keyword')) && void 0 !== t ? t : '',
                )
                w(r), y(r)
                const d =
                  null !== (a = c.get('uploader')) && void 0 !== a ? a : '0'
                A(Number.parseInt(d)),
                  U(null !== (n = c.get('sort')) && void 0 !== n ? n : 'time'),
                  E(
                    Number.parseInt(
                      null !== (i = c.get('page')) && void 0 !== i ? i : '1',
                    ),
                  )
              }
              e(location.search)
              const s = (s) => e(s.state)
              return (
                window.addEventListener('popstate', s),
                () => {
                  window.removeEventListener('popstate', s)
                }
              )
            }, []),
            (0, n.useEffect)(() => {
              ;(async () => {
                s(!0)
                const e = new URLSearchParams()
                e.append('filter', N),
                  C && e.append('keyword', C),
                  Z && e.append('uploader', Z.toString()),
                  e.append('sort', $),
                  e.append('page', _.toString()),
                  window.history.pushState(e.toString(), '', `?${e}`)
                const t = await d.U2(b.Z.skinlib.list(), e)
                i(t.data), F(t.last_page), s(!1)
              })()
            }, [N, C, Z, $, _]),
            (0, n.useEffect)(() => {
              R &&
                (async () => {
                  const e = await d.U2(b.Z.user.closet.ids())
                  k(e)
                })()
            }, [R])
          const L = (e) => {
            A(e), E(1)
          }
          return (0, a.jsxs)(
            'div',
            Object.assign(
              { className: 'container' },
              {
                children: [
                  (0, a.jsx)(
                    'div',
                    Object.assign(
                      { className: 'content-header' },
                      {
                        children: (0, a.jsxs)(
                          'div',
                          Object.assign(
                            {
                              className:
                                'container-fluid d-flex justify-content-between',
                            },
                            {
                              children: [
                                (0, a.jsx)('h1', {
                                  children: (0, r.t)('general.skinlib'),
                                }),
                                (0, a.jsx)('span', {
                                  children: Z
                                    ? (0, a.jsxs)(a.Fragment, {
                                        children: [
                                          (0, a.jsx)('i', {
                                            className: 'fas fa-user mr-1',
                                          }),
                                          (0, r.t)('skinlib.filter.uploader', {
                                            uid: Z,
                                          }),
                                        ],
                                      })
                                    : (0, a.jsxs)(a.Fragment, {
                                        children: [
                                          (0, a.jsx)('i', {
                                            className:
                                              'fas fa-user-friends mr-1',
                                          }),
                                          (0, r.t)('skinlib.filter.allUsers'),
                                        ],
                                      }),
                                }),
                              ],
                            },
                          ),
                        ),
                      },
                    ),
                  ),
                  (0, a.jsx)(
                    'section',
                    Object.assign(
                      { className: 'content' },
                      {
                        children: (0, a.jsxs)(
                          'div',
                          Object.assign(
                            { className: 'card' },
                            {
                              children: [
                                (0, a.jsxs)(
                                  'div',
                                  Object.assign(
                                    { className: 'card-body' },
                                    {
                                      children: [
                                        (0, a.jsxs)(
                                          'div',
                                          Object.assign(
                                            {
                                              className:
                                                'form-group pt-0 mb-3 d-flex justify-content-between',
                                            },
                                            {
                                              children: [
                                                (0, a.jsx)(
                                                  'form',
                                                  Object.assign(
                                                    {
                                                      onSubmit: (e) => {
                                                        e.preventDefault(),
                                                          y(O),
                                                          E(1)
                                                      },
                                                    },
                                                    {
                                                      children: (0, a.jsxs)(
                                                        'div',
                                                        Object.assign(
                                                          {
                                                            className:
                                                              'input-group',
                                                          },
                                                          {
                                                            children: [
                                                              (0, a.jsx)(
                                                                'div',
                                                                Object.assign(
                                                                  {
                                                                    className:
                                                                      'input-group-prepend',
                                                                  },
                                                                  {
                                                                    children:
                                                                      (0,
                                                                      a.jsx)(
                                                                        v,
                                                                        {
                                                                          filter:
                                                                            N,
                                                                          onChange:
                                                                            (
                                                                              e,
                                                                            ) => {
                                                                              f(
                                                                                e,
                                                                              ),
                                                                                E(
                                                                                  1,
                                                                                )
                                                                            },
                                                                        },
                                                                      ),
                                                                  },
                                                                ),
                                                              ),
                                                              (0, a.jsx)(
                                                                'input',
                                                                {
                                                                  type: 'text',
                                                                  inputMode:
                                                                    'search',
                                                                  className:
                                                                    'form-control',
                                                                  value: O,
                                                                  placeholder:
                                                                    (0, r.t)(
                                                                      'vendor.datatable.search',
                                                                    ),
                                                                  onChange: (
                                                                    e,
                                                                  ) => {
                                                                    w(
                                                                      e.target
                                                                        .value,
                                                                    )
                                                                  },
                                                                },
                                                              ),
                                                              (0, a.jsx)(
                                                                'div',
                                                                Object.assign(
                                                                  {
                                                                    className:
                                                                      'input-group-append',
                                                                  },
                                                                  {
                                                                    children:
                                                                      (0,
                                                                      a.jsx)(
                                                                        'button',
                                                                        Object.assign(
                                                                          {
                                                                            className:
                                                                              'btn btn-primary px-3',
                                                                            type: 'submit',
                                                                            title:
                                                                              (0,
                                                                              r.t)(
                                                                                'vendor.datatable.search',
                                                                              ),
                                                                          },
                                                                          {
                                                                            children:
                                                                              (0,
                                                                              a.jsx)(
                                                                                'i',
                                                                                {
                                                                                  className:
                                                                                    'fas fa-search',
                                                                                },
                                                                              ),
                                                                          },
                                                                        ),
                                                                      ),
                                                                  },
                                                                ),
                                                              ),
                                                            ],
                                                          },
                                                        ),
                                                      ),
                                                    },
                                                  ),
                                                ),
                                                (0, a.jsx)(
                                                  'div',
                                                  Object.assign(
                                                    {
                                                      className:
                                                        'd-none d-sm-block',
                                                    },
                                                    {
                                                      children: (0, a.jsxs)(
                                                        'div',
                                                        Object.assign(
                                                          {
                                                            className:
                                                              'btn-group',
                                                          },
                                                          {
                                                            children: [
                                                              (0, a.jsx)(
                                                                h,
                                                                Object.assign(
                                                                  {
                                                                    bg: 'olive',
                                                                    active:
                                                                      'likes' ===
                                                                      $,
                                                                    onClick:
                                                                      () =>
                                                                        U(
                                                                          'likes',
                                                                        ),
                                                                  },
                                                                  {
                                                                    children:
                                                                      (0, r.t)(
                                                                        'skinlib.sort.likes',
                                                                      ),
                                                                  },
                                                                ),
                                                              ),
                                                              (0, a.jsx)(
                                                                h,
                                                                Object.assign(
                                                                  {
                                                                    bg: 'olive',
                                                                    active:
                                                                      'time' ===
                                                                      $,
                                                                    onClick:
                                                                      () =>
                                                                        U(
                                                                          'time',
                                                                        ),
                                                                  },
                                                                  {
                                                                    children:
                                                                      (0, r.t)(
                                                                        'skinlib.sort.time',
                                                                      ),
                                                                  },
                                                                ),
                                                              ),
                                                              null !== R &&
                                                                (0, a.jsx)(
                                                                  h,
                                                                  Object.assign(
                                                                    {
                                                                      bg: 'olive',
                                                                      active:
                                                                        Z === R,
                                                                      onClick:
                                                                        () => {
                                                                          A(R),
                                                                            E(1)
                                                                        },
                                                                    },
                                                                    {
                                                                      children:
                                                                        (0,
                                                                        r.t)(
                                                                          'skinlib.seeMyUpload',
                                                                        ),
                                                                    },
                                                                  ),
                                                                ),
                                                              (0, a.jsx)(
                                                                h,
                                                                Object.assign(
                                                                  {
                                                                    bg: 'olive',
                                                                    onClick:
                                                                      () => {
                                                                        f(
                                                                          'skin',
                                                                        ),
                                                                          w(''),
                                                                          y(''),
                                                                          U(
                                                                            'time',
                                                                          ),
                                                                          A(0),
                                                                          E(1)
                                                                      },
                                                                  },
                                                                  {
                                                                    children:
                                                                      (0, r.t)(
                                                                        'skinlib.reset',
                                                                      ),
                                                                  },
                                                                ),
                                                              ),
                                                            ],
                                                          },
                                                        ),
                                                      ),
                                                    },
                                                  ),
                                                ),
                                              ],
                                            },
                                          ),
                                        ),
                                        t.length > 0
                                          ? (0, a.jsx)(
                                              'div',
                                              Object.assign(
                                                {
                                                  className: 'd-flex flex-wrap',
                                                },
                                                {
                                                  children: t.map((e, s) =>
                                                    (0, a.jsx)(
                                                      S,
                                                      {
                                                        item: e,
                                                        liked: x.includes(
                                                          e.tid,
                                                        ),
                                                        onAdd: (e) =>
                                                          (async (e, s) => {
                                                            R
                                                              ? (await (0, u.Z)(
                                                                  e,
                                                                )) &&
                                                                (k((s) => [
                                                                  ...s,
                                                                  e.tid,
                                                                ]),
                                                                i(
                                                                  (t) => (
                                                                    (t[s] =
                                                                      Object.assign(
                                                                        Object.assign(
                                                                          {},
                                                                          e,
                                                                        ),
                                                                        {
                                                                          likes:
                                                                            e.likes +
                                                                            1,
                                                                        },
                                                                      )),
                                                                    t.slice()
                                                                  ),
                                                                ))
                                                              : o.A.warning(
                                                                  (0, r.t)(
                                                                    'skinlib.anonymous',
                                                                  ),
                                                                )
                                                          })(e, s),
                                                        onRemove: (e) =>
                                                          (async (e, s) => {
                                                            ;(await (0, p.Z)(
                                                              e.tid,
                                                            )) &&
                                                              (k((s) =>
                                                                s.filter(
                                                                  (s) =>
                                                                    s !== e.tid,
                                                                ),
                                                              ),
                                                              i(
                                                                (t) => (
                                                                  (t[s] =
                                                                    Object.assign(
                                                                      Object.assign(
                                                                        {},
                                                                        e,
                                                                      ),
                                                                      {
                                                                        likes:
                                                                          e.likes -
                                                                          1,
                                                                      },
                                                                    )),
                                                                  t.slice()
                                                                ),
                                                              ))
                                                          })(e, s),
                                                        onUploaderClick: L,
                                                      },
                                                      e.tid,
                                                    ),
                                                  ),
                                                },
                                              ),
                                            )
                                          : (0, a.jsx)(
                                              'p',
                                              Object.assign(
                                                {
                                                  className: 'text-center m-5',
                                                },
                                                {
                                                  children: (0, r.t)(
                                                    'general.noResult',
                                                  ),
                                                },
                                              ),
                                            ),
                                      ],
                                    },
                                  ),
                                ),
                                (0, a.jsx)(
                                  'div',
                                  Object.assign(
                                    { className: 'card-footer' },
                                    {
                                      children: (0, a.jsx)(
                                        'div',
                                        Object.assign(
                                          {
                                            className:
                                              'd-flex justify-content-center',
                                          },
                                          {
                                            children: (0, a.jsx)(g.Z, {
                                              page: _,
                                              totalPages: I,
                                              onChange: E,
                                            }),
                                          },
                                        ),
                                      ),
                                    },
                                  ),
                                ),
                                e &&
                                  (0, a.jsx)(
                                    'div',
                                    Object.assign(
                                      { className: 'overlay' },
                                      { children: (0, a.jsx)(j.Z, {}) },
                                    ),
                                  ),
                              ],
                            },
                          ),
                        ),
                      },
                    ),
                  ),
                ],
              },
            ),
          )
        })
    },
    5667: (e, s, t) => {
      'use strict'
      t.d(s, { Z: () => l })
      var a = t(88),
        n = t(6703),
        i = t(2065),
        c = t(8848)
      async function l(e) {
        try {
          await (0, i.K)({
            text: (0, a.t)('user.removeFromClosetNotice'),
            okButtonType: 'danger',
          })
        } catch (e) {
          return !1
        }
        const { code: s, message: t } = await n.IV(c.Z.user.closet.remove(e))
        return 0 === s ? i.A.success(t) : i.A.error(t), 0 === s
      }
    },
  },
])
//# sourceMappingURL=683.70cf989.js.map
