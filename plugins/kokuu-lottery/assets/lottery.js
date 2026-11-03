/* KokuuSkin 祈愿（kokuu-lottery）v2.0
 *
 * 2026-11-03 与模板/CSS 一同重写，类名前缀 kcp* → kg*。
 *
 * ══════════════════════════════════════════════════════════════
 * 沿用的硬事实（都是线上踩过的，别改回去）
 * ══════════════════════════════════════════════════════════════
 * 1. CSRF 值提交时**实时再读一次 cookie**。页面加载时的快照会 419，
 *    因为 session/token 会轮换。绝不用变量缓存。
 * 2. cookie 值只 decodeURIComponent，**不要剥 CookieValuePrefix** ——
 *    服务端会自己剥，多剥一次就无效。
 * 3. 判据是「非 419」，不是「等于 200」。419=CSRF 拦截（没到业务），
 *    422=业务失败（已穿过 CSRF）。
 * 4. disabled 按钮的 click() 被浏览器**静默忽略**（一个请求都不发），
 *    所以状态没变就 early return，不要发出去等 422。
 * 5. 抽奖成功后**冷却才刚开始**，必须按响应里的真实冷却秒数倒计时，
 *    不能假设配置值（管理员可能改过）。
 * 6. 奖品 label 是管理员填的，innerHTML 前必须转义。
 * 7. 🎬 动画层 position:fixed;inset:0，播放期间吃掉整页点击。
 *    必须在 ANIM_MS 准时收层，否则页面一直点不动 ——
 *    这是 JS 定时器与 CSS 动画时长必须对齐的唯一理由。
 *    防重复触发靠 animBusy，不能指望 overlay（它不阻止定时器）。
 */
;(function () {
  'use strict'

  var btn = document.getElementById('kg-draw-btn')
  var btnText = document.getElementById('kg-draw-btn-text')
  var result = document.getElementById('kg-draw-result')
  if (!btn || !btnText || !result) {
    return
  }

  var pool = document.getElementById('kg-pool')
  var elDraws = document.getElementById('kg-draws')
  var elSpend = document.getElementById('kg-spendable')

  var anim = document.getElementById('kg-anim')
  var animName = document.getElementById('kg-anim-name')
  var animAmt = document.getElementById('kg-anim-amt')

  /* 🔴 必须与 lottery.css 里 .kg-anim 的总时长（2.9s）一致。
     多给 100ms 余量：宁可晚收，不可早收 —— 早收会「文字还没浮现就没了」。 */
  var ANIM_MS = 3000
  var animBusy = false

  var drawUrl = btn.getAttribute('data-url') || '/plugin/kokuu-lottery/draw'
  var busy = false
  var timer = null

  /* 🔴 实时读 cookie，不缓存（第 1、2 条） */
  function csrfToken() {
    var m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1]) : ''
  }

  function escapeHtml(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
  }

  function say(html, kind) {
    result.hidden = false
    result.className = 'kg-result' + (kind === 'warn' ? ' is-warn' : '')
    result.innerHTML = html
  }

  function setBtn(text, disabled) {
    btnText.textContent = text
    btn.disabled = !!disabled
    if (pool) {
      pool.classList.toggle('is-ready', !disabled)
    }
  }

  /* 卡池光爆：0.76s（与 CSS kg-hit-flash 一致），
     结束后摘 class，否则下次触发动画不重放。 */
  function flashPool() {
    if (!pool) {
      return
    }
    pool.classList.remove('is-hit')
    void pool.offsetWidth /* 强制回流，确保动画重放 */
    pool.classList.add('is-hit')
    window.setTimeout(function () {
      pool.classList.remove('is-hit')
    }, 800)
  }

  /* 🎬 播放祈愿动画
   *
   * 只做三件事：填文案 → 摘 hidden 加 .is-on → 定时收层。
   * 全部时序在 CSS 里，JS 不监听 animationend ——
   * CSS 时序是设计的一部分，让 JS 编排等于把设计拆成两半。
   */
  function playAnim(label, amount) {
    flashPool()

    if (!anim || !animName || !animAmt) {
      return
    }

    /* 系统开了「减少动态效果」→ 不播，直接给结果。
       CSS 里也有 reduced-motion 分支，这里再判一次是为了
       根本不显示覆盖层，省掉 3 秒白等。 */
    var mq = window.matchMedia
      ? window.matchMedia('(prefers-reduced-motion: reduce)')
      : null
    if (mq && mq.matches) {
      return
    }

    if (animBusy) {
      return
    }
    animBusy = true

    var amt = Number(amount) || 0

    /* 🔴 未中奖（amount === 0）走 .is-miss：去金色，换冷灰。
       判据是**数字**，不是「文案里有没有谢谢」——
       「谢谢参与」「未中奖」都是管理员自填的，靠字符串猜会失效。 */
    anim.classList.toggle('is-miss', amt <= 0)

    var name = label || '奖品'

    /* 🔴 奖品池默认 label 就是「N 积分」，会让大字行和金额行重复。
       这里判「精确的默认格式」（label === amount + ' 积分'）才屏蔽金额行；
       管理员改成「限定皮肤」时两行都正常显示。 */
    var autoNamed = amt > 0 && name.replace(/\s+/g, '') === amt + '积分'

    if (autoNamed) {
      animAmt.textContent = ''
      animName.textContent = name
    } else if (amt > 0) {
      animAmt.textContent = '+' + amt
      animName.textContent = name
    } else {
      animAmt.textContent = ''
      animName.textContent = name
    }

    /* 摘 hidden 前先清 .is-on：上一轮若被中断，残留 class 会让动画不重放 */
    anim.classList.remove('is-on')
    anim.hidden = false
    void anim.offsetWidth /* 强制回流后再加 .is-on */
    anim.classList.add('is-on')

    window.setTimeout(function () {
      anim.classList.remove('is-on')
      anim.classList.remove('is-miss')
      anim.hidden = true
      animName.textContent = ''
      animAmt.textContent = ''
      animBusy = false
    }, ANIM_MS)
  }

  /* 冷却倒计时（用服务端给的秒数，不猜配置值） */
  function cooldown(seconds, label) {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    var left = Math.max(0, parseInt(seconds, 10) || 0)
    if (left <= 0) {
      setBtn(label || '祈 愿 一 次', false)
      return
    }
    setBtn('冷却中（' + left + 's）', true)
    timer = setInterval(function () {
      left -= 1
      if (left <= 0) {
        clearInterval(timer)
        timer = null
        setBtn(label || '祈 愿 一 次', false)
      } else {
        setBtn('冷却中（' + left + 's）', true)
      }
    }, 1000)
  }

  /* 🔴 只信服务端回传的 spendable（余额 - 底仓）。
     用 balance 会把「可用积分」显示成总余额，看着像抽奖没扣钱。 */
  function refreshWallet(spendable, draws) {
    if (draws !== null && draws !== undefined && elDraws) {
      elDraws.textContent = draws
    }
    if (spendable !== null && spendable !== undefined && elSpend) {
      elSpend.textContent = spendable
    }
  }

  btn.addEventListener('click', function () {
    if (busy || btn.disabled) {
      return
    }
    busy = true
    btn.classList.add('is-busy')
    setBtn('祈愿中…', true)

    fetch(drawUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
      },
      body: '',
    })
      .then(function (r) {
        return r
          .json()
          .catch(function () {
            return {
              code: 1,
              message: '服务器返回异常（HTTP ' + r.status + '）',
            }
          })
          .then(function (j) {
            return { status: r.status, body: j }
          })
      })
      .then(function (res) {
        var j = res.body || {}

        if (res.status === 419) {
          say('CSRF 校验未通过（419），请刷新页面后重试。', 'warn')
          setBtn('请刷新页面', true)
          return
        }

        if (j.code === 0) {
          say(
            '获得 <span class="kg-result-name">' +
              escapeHtml(j.label || '奖品') +
              '</span>' +
              (j.amount > 0
                ? ' · <span class="kg-result-amount">+' +
                  (j.amount || 0) +
                  ' 积分</span>'
                : ''),
            'win',
          )
          refreshWallet(j.spendable, j.draws)
          /* 先播动画再起冷却：覆盖层吃掉点击，顺序反了会出现
           「冷却结束但动画还在播」，用户以为按钮坏了。 */
          playAnim(j.label || '奖品', j.amount || 0)
          cooldown(j.cooldown !== undefined ? j.cooldown : 3, '祈 愿 一 次')
          return
        }

        /* 业务失败（422 = 已穿过 CSRF，功能本身是通的） */
        say(escapeHtml(j.message || '祈愿失败'), 'warn')
        refreshWallet(j.spendable, j.draws)
        if (/不足|没有/.test(j.message || '')) {
          setBtn('次数与积分均不足', true)
        } else {
          cooldown(j.cooldown !== undefined ? j.cooldown : 3, '祈 愿 一 次')
        }
      })
      .catch(function (e) {
        say('网络错误：' + escapeHtml(e.message), 'warn')
        setBtn('祈 愿 一 次', false)
      })
      .then(function () {
        busy = false
        btn.classList.remove('is-busy')
      })
  })
})()
