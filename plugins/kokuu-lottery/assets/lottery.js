/* 祈愿页 v4 (kokuu-lottery)
 *
 * 2026-11-03 第三次重做 —— 场景交叉轮播 + 精致揭晓动画
 *
 * ══════════════════════════════════════════════════════════════════
 * 沿用硬事实（线上踩过，别改回去）
 * ══════════════════════════════════════════════════════════════════
 * 1. CSRF 提交时实时读 cookie，不缓存（session 轮换会 419）
 * 2. cookie 只 decodeURIComponent，不剥 CookieValuePrefix
 * 3. 判据「非 419」，不是「等于 200」
 * 4. disabled 按钮 click 被静默忽略 → early return
 * 5. 奖品 label 管理员可填，innerHTML 前必须转义
 * 6. 动画层 fixed inset:0 吃整页点击 → 准时收层 + 跳过按钮
 * 7. 接口返回 {ok, msg, label, amount, spendable, assets, results}
 */
;(function () {
  'use strict'

  var page = document.getElementById('ly-page')
  if (!page) {
    return
  }

  var btn1 = document.getElementById('ly-draw1')
  var btn10 = document.getElementById('ly-draw10')
  var result = document.getElementById('ly-result')
  var hint = document.getElementById('ly-hint')

  var anim = document.getElementById('ly-anim')
  var cardsBox = document.getElementById('ly-cards')
  var skipBtn = document.getElementById('ly-skip')

  var elGold = document.getElementById('ly-gold')
  var elDiamond = document.getElementById('ly-diamond')
  var elEmerald = document.getElementById('ly-emerald')

  var dotsBox = document.getElementById('ly-dots')
  var scenes = document.querySelectorAll('.ly-pool-scene')

  var drawUrl = page.getAttribute('data-url') || '/plugin/kokuu-lottery/draw'
  var cost = parseInt(page.getAttribute('data-cost') || '100', 10) || 100

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

  /* =========================================================
     场景交叉轮播
     ========================================================= */
  var sceneIdx = 0
  var sceneTimer = null

  function buildDots() {
    if (!dotsBox) {
      return
    }
    dotsBox.innerHTML = ''
    for (var i = 0; i < scenes.length; i++) {
      var d = document.createElement('i')
      d.setAttribute('data-i', i)
      dotsBox.appendChild(d)
    }
    paintDots(0)
  }

  function paintDots(i) {
    if (!dotsBox) {
      return
    }
    var kids = dotsBox.children
    for (var k = 0; k < kids.length; k++) {
      kids[k].classList.toggle('is-active', k === i)
    }
  }

  function showScene(i) {
    for (var k = 0; k < scenes.length; k++) {
      scenes[k].classList.toggle('is-active', k === i)
    }
    paintDots(i)
  }

  function startScenes() {
    if (scenes.length === 0) {
      return
    }
    buildDots()
    showScene(0)

    // 减少动效 → 只显示第一张，不轮播
    var mq = window.matchMedia
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null
    if (mq && mq.matches) {
      return
    }

    sceneTimer = window.setInterval(function () {
      sceneIdx = (sceneIdx + 1) % scenes.length
      showScene(sceneIdx)
    }, 7000)
  }

  /* =========================================================
     资产回填
     ========================================================= */
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
    result.className = 'ly-result' + (warn ? ' is-warn' : '')
    result.innerHTML = html
  }

  /* =========================================================
     揭晓动画
     ========================================================= */
  var CARD_STAGGER = 85 // 逐张延迟
  var FLIP_START = 560 // 光爆后开始翻牌
  var CARD_DUR = 420 // 单卡动画时长（与 CSS 一致）

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

  function iconStyle(asset) {
    var f =
      asset === 'diamond'
        ? '11-item-diamond.png'
        : asset === 'emerald'
        ? '12-item-emerald.png'
        : '10-item-gold.png'
    return "background-image:url('/plugins/kokuu-lottery/assets/img/" + f + "')"
  }

  function buildCard(r) {
    var div = document.createElement('div')
    div.className = 'ly-card ly-card--' + (r.rarity || 'blue')
    div.innerHTML =
      '<div class="ly-card-ico" style="' +
      iconStyle(r.asset) +
      '"></div>' +
      '<div class="ly-card-amt">×' +
      esc(r.amount) +
      '</div>' +
      '<div class="ly-card-name">' +
      esc(r.label) +
      '</div>'
    return div
  }

  function playDraw(results) {
    if (!anim || !cardsBox || !results.length) {
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

    results.forEach(function (r, i) {
      var t = window.setTimeout(function () {
        cardsBox.appendChild(buildCard(r))
      }, FLIP_START + i * CARD_STAGGER)
      animTimers.push(t)
    })

    var total = FLIP_START + results.length * CARD_STAGGER + CARD_DUR + 900
    animTimers.push(window.setTimeout(closeAnim, total))

    if (skipBtn) {
      skipBtn.onclick = closeAnim
    }
  }

  /* =========================================================
     抽卡请求
     ========================================================= */
  var busy = false

  function refreshButtons() {
    var gold = parseInt((elGold && elGold.textContent) || '0', 10) || 0
    if (btn1) {
      btn1.disabled = gold < cost
    }
    if (btn10) {
      btn10.disabled = gold < cost * 10
    }
    if (hint) {
      hint.textContent =
        gold < cost ? '金粒不足，无法祈愿' : '每抽消耗 ' + cost + ' 金粒'
    }
  }

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
            summary = '十连完成'
            var parts = []
            if (golds) {
              parts.push(golds + ' 传说')
            }
            if (purples) {
              parts.push(purples + ' 稀有')
            }
            if (parts.length) {
              summary += '：' + parts.join(' · ')
            } else {
              summary += '：全部普通'
            }
          }
          say(summary, false)

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
        refreshButtons()
      })
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

  /* 初始化 */
  startScenes()

  window.addEventListener('beforeunload', function () {
    if (sceneTimer) {
      window.clearInterval(sceneTimer)
    }
    clearAnimTimers()
  })
})()
