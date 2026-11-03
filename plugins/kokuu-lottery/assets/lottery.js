/* MC 风格抽卡 v3 (kokuu-lottery)
 *
 * 2026-11-03 重写。单抽/十连、逐张翻牌、场景轮播。
 *
 * ══════════════════════════════════════════════════════════════════
 * 沿用硬事实（线上踩过，别改回去）
 * ══════════════════════════════════════════════════════════════════
 * 1. CSRF 提交时实时读 cookie，不缓存（session 会轮换，否则 419）
 * 2. cookie 值只 decodeURIComponent，不剥 CookieValuePrefix
 * 3. 判据「非 419」，不是「等于 200」
 * 4. disabled 按钮 click 被浏览器静默忽略 → early return
 * 5. 奖品 label 管理员可填，innerHTML 前必须转义
 * 6. 动画层 fixed inset:0 会吃掉整页点击 → 必须准时收层 + 跳过按钮
 * 7. 抽卡接口返回 {ok, msg, label, amount, spendable, draws, assets, results}
 */
;(function () {
  'use strict'

  var root = document.getElementById('mc-lot')
  if (!root) {
    return
  }

  var btn1 = document.getElementById('mc-draw1')
  var btn10 = document.getElementById('mc-draw10')
  var result = document.getElementById('mc-result')
  var hint = document.getElementById('mc-hint')
  var sceneEl = document.getElementById('mc-scene')

  var anim = document.getElementById('mc-anim')
  var cardsBox = document.getElementById('mc-cards')
  var skipBtn = document.getElementById('mc-skip')

  var elGold = document.getElementById('mc-gold')
  var elDiamond = document.getElementById('mc-diamond')
  var elEmerald = document.getElementById('mc-emerald')

  var drawUrl = root.getAttribute('data-url') || '/plugin/kokuu-lottery/draw'

  /* MC 场景图（复用外观插件的 6 张真实 MC 截图） */
  var SCENES = [
    '/plugins/kokuu-appearance/assets/scenes/01-mineshaft.png',
    '/plugins/kokuu-appearance/assets/scenes/02-nether.png',
    '/plugins/kokuu-appearance/assets/scenes/03-snow.png',
    '/plugins/kokuu-appearance/assets/scenes/04-jungle.png',
    '/plugins/kokuu-appearance/assets/scenes/05-end.png',
    '/plugins/kokuu-appearance/assets/scenes/06-village.png',
  ]

  var sceneIdx = 0
  var sceneTimer = null

  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
  }

  function csrfToken() {
    var m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1]) : ''
  }

  /* ---------- 场景轮播 ---------- */
  function showScene(i) {
    if (!sceneEl) {
      return
    }
    sceneEl.style.backgroundImage = 'url("' + SCENES[i % SCENES.length] + '")'
  }

  function startScenes() {
    if (!sceneEl || SCENES.length < 2) {
      return
    }
    showScene(0)
    sceneTimer = window.setInterval(function () {
      sceneIdx = (sceneIdx + 1) % SCENES.length
      showScene(sceneIdx)
    }, 6000)

    // 系统偏好减少动效 → 不轮播，固定一张
    var mq = window.matchMedia
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null
    if (mq && mq.matches && sceneTimer) {
      window.clearInterval(sceneTimer)
      sceneTimer = null
    }
  }

  /* ---------- 资产回填 ---------- */
  function applyAssets(assets, spendable) {
    if (spendable !== undefined && spendable !== null && elGold) {
      elGold.textContent = spendable
    }
    if (assets) {
      if (assets.diamond !== undefined && elDiamond) {
        elDiamond.textContent = assets.diamond
      }
      if (assets.emerald !== undefined && elEmerald) {
        elEmerald.textContent = assets.emerald
      }
    }
  }

  function say(html, warn) {
    if (!result) {
      return
    }
    result.hidden = false
    result.className = 'mc-result' + (warn ? ' is-warn' : '')
    result.innerHTML = html
  }

  /* ---------- 翻牌动画 ----------
   *
   * 参照原神：卡片逐张浮现，金卡有额外光效。
   * 时长与 CSS .mc-card animation 对齐（.38s + stagger）。
   */
  var CARD_BASE_MS = 380 // 与 CSS mc-card-in 时长一致
  var CARD_STAGGER_MS = 90 // 逐张延迟

  function iconClass(asset) {
    if (asset === 'diamond') {
      return 'mc-card-ico--diamond'
    }
    if (asset === 'emerald') {
      return 'mc-card-ico--emerald'
    }
    return 'mc-card-ico--gold'
  }

  function buildCard(r) {
    var div = document.createElement('div')
    div.className = 'mc-card mc-card--' + (r.rarity || 'blue')
    div.innerHTML =
      '<div class="mc-card-ico ' +
      iconClass(r.asset) +
      '"></div>' +
      '<div class="mc-card-amt">×' +
      esc(r.amount) +
      '</div>' +
      '<div class="mc-card-name">' +
      esc(r.label) +
      '</div>'
    return div
  }

  var animTimers = []

  function clearAnimTimers() {
    animTimers.forEach(function (t) {
      window.clearTimeout(t)
    })
    animTimers = []
  }

  function closeAnim() {
    clearAnimTimers()
    if (!anim) {
      return
    }
    anim.classList.remove('is-on')
    anim.hidden = true
    if (cardsBox) {
      cardsBox.innerHTML = ''
      cardsBox.classList.remove('is-ten')
    }
  }

  /* 播放抽卡动画：results 为后端返回的数组 */
  function playDraw(results) {
    if (!anim || !cardsBox) {
      return
    }

    var mq = window.matchMedia
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null
    if (mq && mq.matches) {
      return
    }

    cardsBox.innerHTML = ''
    if (results.length > 1) {
      cardsBox.classList.add('is-ten')
    }

    anim.hidden = false
    void anim.offsetWidth
    anim.classList.add('is-on')

    // 暗场 + 闪光后开始翻牌（.34s 闪光起点）
    var flipStart = 620

    results.forEach(function (r, i) {
      var t = window.setTimeout(function () {
        var card = buildCard(r)
        card.style.animationDelay = '0s'
        cardsBox.appendChild(card)
      }, flipStart + i * CARD_STAGGER_MS)
      animTimers.push(t)
    })

    var totalMs =
      flipStart + results.length * CARD_STAGGER_MS + CARD_BASE_MS + 400
    var closeT = window.setTimeout(closeAnim, totalMs)
    animTimers.push(closeT)

    // 跳过按钮：立即收层
    if (skipBtn) {
      skipBtn.onclick = function () {
        closeAnim()
      }
    }
  }

  /* ---------- 抽卡请求 ---------- */
  var busy = false

  function doDraw(times) {
    if (busy) {
      return
    }
    busy = true
    if (btn1) {
      btn1.disabled = true
    }
    if (btn10) {
      btn10.disabled = true
    }

    fetch(drawUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: 'times=' + times,
    })
      .then(function (r) {
        return r
          .json()
          .catch(function () {
            return { ok: false, msg: '服务器异常（HTTP ' + r.status + '）' }
          })
          .then(function (j) {
            return { status: r.status, body: j }
          })
      })
      .then(function (res) {
        var j = res.body || {}

        if (res.status === 419) {
          say('登录状态已失效（419），请刷新页面。', true)
          return
        }

        if (j.ok === true) {
          var results = j.results || []
          // 结果摘要
          var golds = results.filter(function (x) {
            return x.rarity === 'gold'
          }).length
          var purples = results.filter(function (x) {
            return x.rarity === 'purple'
          }).length

          var summary
          if (times === 1) {
            var r0 = results[0] || {}
            summary = '获得 <b>' + esc(r0.label || '奖励') + '</b>'
          } else {
            summary = '十连完成：'
            if (golds) {
              summary += ' <b style="color:#B9821A">' + golds + ' 传说</b>'
            }
            if (purples) {
              summary += ' <b style="color:#7C3AED">' + purples + ' 稀有</b>'
            }
            if (!golds && !purples) {
              summary += ' 全部普通'
            }
          }
          say(summary + ' · 消耗 ' + times * 100 + ' 金粒', false)

          applyAssets(j.assets, j.spendable)
          playDraw(results)
        } else {
          say(esc(j.msg || '祈愿失败'), true)
          applyAssets(j.assets, j.spendable)
        }
      })
      .catch(function (e) {
        say('网络错误：' + esc(e.message), true)
      })
      .then(function () {
        busy = false
        // 按当前金粒恢复按钮可用性
        refreshButtons()
      })
  }

  /* 根据当前显示的金粒数恢复按钮状态 */
  function refreshButtons() {
    var gold = parseInt((elGold && elGold.textContent) || '0', 10) || 0
    var cost = parseInt(root.getAttribute('data-cost') || '100', 10) || 100
    if (btn1) {
      btn1.disabled = gold < cost
    }
    if (btn10) {
      btn10.disabled = gold < cost * 10
    }
    if (hint) {
      hint.textContent =
        gold < cost ? '金粒不足，无法祈愿' : '每次祈愿消耗 ' + cost + ' 金粒'
    }
  }

  if (btn1) {
    btn1.addEventListener('click', function () {
      doDraw(1)
    })
  }
  if (btn10) {
    btn10.addEventListener('click', function () {
      doDraw(10)
    })
  }

  /* ---------- 初始化 ---------- */
  startScenes()

  /* 页面卸载时清理定时器 */
  window.addEventListener('beforeunload', function () {
    if (sceneTimer) {
      window.clearInterval(sceneTimer)
    }
    clearAnimTimers()
  })
})()
