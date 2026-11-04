/* =========================================================
   KokuuSkin 全站主题同步（theme-sync）
   注入到所有页面 <head> 之前（尽早执行，避免浅色闪一下）。

   问题背景（2026-11-05 用户反馈）：
   首页把深浅色存在 localStorage 的 kokuu_theme（paper | void），
   而内核（Blessing Skin）只在**登录用户**的 users.is_dark_mode 字段
   记录偏好，并且只在 user/base.twig 与 skinlib/base.twig 里输出
   body.dark-mode。于是：
     · 访客在首页切夜间 → 点进皮肤库仍是浅色
     · 登录用户切换后立刻跳页，PUT 请求可能还没落库
   两套机制互不相通。

   这里以 localStorage 为唯一真相源：
     · 页面一加载就给 body 打上 dark-mode（服务端已输出则幂等）
     · 监听 storage 事件，多标签页同步
     · 暴露 window.KokuuTheme.set() 供各处调用，并同步写回服务端
   ========================================================= */
;(function () {
  'use strict'

  var KEY = 'kokuu_theme'

  function read() {
    try {
      var v = localStorage.getItem(KEY)
      if (v === 'void' || v === 'paper') {
        return v
      }
    } catch (e) {
      /* 隐私模式 */
    }
    return null
  }

  function write(theme) {
    try {
      localStorage.setItem(KEY, theme)
    } catch (e) {
      /* 忽略 */
    }
  }

  function apply(theme) {
    var dark = theme === 'void'
    if (document.body) {
      document.body.classList.toggle('dark-mode', dark)
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.toggle('dark-mode', dark)
      })
    }
    document.documentElement.setAttribute('data-kokuu-theme', theme)
  }

  /* 写回服务端（仅登录用户有效，失败静默） */
  function persist(theme) {
    var b = window.blessing || {}
    var base = (b.base_url || '').replace(/\/+$/, '')
    var meta = document.querySelector('meta[name="csrf-token"]')
    if (!base || !meta) {
      return
    }
    try {
      fetch(base + '/user/dark-mode', {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': meta.content,
        },
      }).catch(function () {})
    } catch (e) {
      /* 忽略 */
    }
  }

  window.KokuuTheme = {
    get: function () {
      var t = read()
      if (t) {
        return t
      }
      return document.body && document.body.classList.contains('dark-mode')
        ? 'void'
        : 'paper'
    },
    set: function (theme, syncServer) {
      var next = theme === 'void' ? 'void' : 'paper'
      write(next)
      apply(next)
      if (syncServer) {
        persist(next)
      }
      return next
    },
    toggle: function () {
      return this.set(this.get() === 'void' ? 'paper' : 'void', true)
    },
  }

  /* 尽早应用：脚本放在 head 中同步执行 */
  var initial = read()
  if (initial) {
    apply(initial)
  }

  /* 多标签页同步 */
  window.addEventListener('storage', function (ev) {
    if (ev.key === KEY && (ev.newValue === 'void' || ev.newValue === 'paper')) {
      apply(ev.newValue)
    }
  })

  /* 服务端已渲染 dark-mode 但没有本地记录时，补写本地，保证后续页面一致 */
  document.addEventListener('DOMContentLoaded', function () {
    if (!read() && document.body.classList.contains('dark-mode')) {
      write('void')
      document.documentElement.setAttribute('data-kokuu-theme', 'void')
    }
  })
})()
