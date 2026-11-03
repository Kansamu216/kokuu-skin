/* =========================================================
   KokuuSkin 皮肤站首页（前端接管）
   由 KokuuHome 插件注入到首页 `</body>` 之前（DOM 已就绪）。
   不改内核 home.twig：这里直接用 KokuuSkin 落地页整体替换 body 内容。

   风格：Apple 扁平（白底/大留白/细腻阴影/大圆角）
        + Minecraft 像素点缀（像素方块/硬边/网格）
        + 挪德卡莱月神意象（月亮/星尘/哥白鸽）

   特性：
   - 文案与右侧图片由 window.KOKUU_CONFIG 驱动（后台表单可改）
   - 支持浅色 / 虚空两态，两态都是 Apple 扁平语言
   ========================================================= */
;(function () {
  'use strict'

  var THEME_KEY = 'kokuu_theme' // "void" | "paper"

  // 读取后台配置，带默认值
  function readConfig() {
    var c = window.KOKUU_CONFIG || {}
    return {
      hero_title: c.hero_title || 'KokuuSkin',
      tagline: c.tagline || '在方块世界，遇见喜欢的自己。',
      intro:
        c.intro ||
        '挑一款喜欢的皮肤，收进你的衣柜。用 KokuuSkin 账号连接启动器，让每次冒险都有自己的模样。',
      footer_note: c.footer_note || '你的模样，你的冒险。',
      contact_email: c.contact_email || 'hi@kokuu.org',
      // Preserve legacy single image while supporting a three-image campaign rotation.
      hero_image: c.hero_image || '',
      hero_background: c.hero_background || '',
      hero_background_2: c.hero_background_2 || '',
      hero_background_3: c.hero_background_3 || '',
    }
  }

  function currentTheme() {
    try {
      var saved = localStorage.getItem(THEME_KEY)
      if (saved === 'void' || saved === 'paper') return saved
    } catch (e) {
      /* 忽略 */
    }
    return 'paper'
  }

  function applyTheme(theme) {
    var root = document.querySelector('.kokuu-root')
    if (root) {
      root.classList.toggle('kokuu-void', theme === 'void')
    }
    try {
      localStorage.setItem(THEME_KEY, theme)
    } catch (e) {
      /* 忽略 */
    }
    updateToggleLabel(theme)
  }

  function updateToggleLabel(theme) {
    var btn = document.getElementById('kokuu-theme-toggle')
    if (!btn) return
    var isVoid = theme === 'void'
    btn.innerHTML = isVoid
      ? '<span class="px-sun"></span>日间'
      : '<span class="px-moon"></span>夜间'
    btn.setAttribute('title', isVoid ? '切换到浅色模式' : '切换到夜间模式')
    btn.setAttribute('aria-label', isVoid ? '切换到浅色模式' : '切换到夜间模式')
    btn.setAttribute('aria-pressed', String(isVoid))
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
  }

  // 内置 MC 像素场景（可被设置页 hero_image 覆盖为单张自定义图）
  var SCENES = [
    { file: 'scenes/01-mineshaft.png', name: '矿洞深处', tag: 'CAVE' },
    { file: 'scenes/02-nether.png', name: '下界熔岩', tag: 'NETHER' },
    { file: 'scenes/03-snow.png', name: '雪原清晨', tag: 'SNOW' },
    { file: 'scenes/04-jungle.png', name: '丛林神庙', tag: 'JUNGLE' },
    { file: 'scenes/05-end.png', name: '末地黑曜石', tag: 'THE END' },
    { file: 'scenes/06-village.png', name: '村庄日暮', tag: 'VILLAGE' },
  ]

  // Same art direction across the rotating campaign set. The first is the
  // user-provided image; slots 2/3 remain easy to replace with generated art.
  var HERO_SLIDES = [
    {
      file: 'hero-fantasy-mc.webp',
      name: '暮光村庄',
      tag: 'TWILIGHT',
      align: 'left',
    },
    {
      file: 'hero-fantasy-mc-2.webp',
      name: '雪原旅途',
      tag: 'DAWN',
      align: 'left-upper',
    },
    {
      file: 'hero-fantasy-mc-3.webp',
      name: '林间秘境',
      tag: 'JUNGLE',
      align: 'right',
    },
  ]

  // Real public skin-library entries make the product purpose visible.
  // IDs and names are sourced from the public library; artwork is requested
  // from the site's own preview endpoint and is not copied into this repo.
  var FEATURED_SKINS = Array.isArray((window.KOKUU_CONFIG || {}).featured_skins)
    ? window.KOKUU_CONFIG.featured_skins
        .filter(function (skin) {
          return Number.isSafeInteger(Number(skin.id)) && Number(skin.id) > 0
        })
        .slice(0, 3)
    : []
  var SLIDE_MS = 5200
  var slideTimer = null

  function mount() {
    var b = window.blessing || {}
    var base = (b.base_url || '').replace(/\/+$/, '')
    var asset = base + '/plugins/kokuu-appearance/assets/'
    var cfg = readConfig()
    var theme = currentTheme()
    // A custom background never disables the real-skin foreground carousel.
    // Legacy hero_image remains supported as the old single-image mode.
    var legacySingleImage = !!cfg.hero_image && !cfg.hero_background
    var heroSlides = [
      cfg.hero_background || asset + HERO_SLIDES[0].file,
      cfg.hero_background_2 || asset + HERO_SLIDES[1].file,
      cfg.hero_background_3 || asset + HERO_SLIDES[2].file,
    ].map(function (src) {
      return /^https?:\/\//i.test(src)
        ? src
        : base + '/' + src.replace(/^\/+/, '')
    })
    var heroImg = legacySingleImage
      ? /^https?:\/\//i.test(cfg.hero_image)
        ? cfg.hero_image
        : base + '/' + cfg.hero_image.replace(/^\/+/, '')
      : ''

    try {
      var fi = document.querySelector(
        'link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]',
      )
      if (fi) {
        fi.href = asset + 'favicon.png'
      }

      var html = [
        '<div class="kokuu-root' +
          (theme === 'void' ? ' kokuu-void' : '') +
          '">',
        '  <div class="kokuu-grid" aria-hidden="true"></div>',
        '  <div class="kokuu-dust" id="kokuu-dust"></div>',
        '  <div class="kokuu-navbar" aria-hidden="true"></div>',

        /* ---------- 顶栏 ---------- */
        '  <header class="kokuu-topbar">',
        '    <a class="kokuu-brand" href="' + base + '/">',
        '      <span class="kokuu-brand-name">Kokuu<span class="kokuu-accent">Skin</span></span>',
        '    </a>',
        '    <div class="kokuu-topbar-right">',
        '      <button type="button" class="kokuu-theme-toggle" id="kokuu-theme-toggle"></button>',
        '      <nav class="kokuu-nav" id="kokuu-nav">',
        '        <a href="' + base + '/skinlib">皮肤库</a>',
        '        <a href="' + base + '/auth/login">登录</a>',
        '        <a href="' + base + '/auth/register">注册</a>',
        '      </nav>',
        '    </div>',
        '  </header>',

        /* ---------- HERO ---------- */
        '  <section class="kokuu-hero kokuu-align-' +
          HERO_SLIDES[0].align +
          '" id="kokuu-hero" aria-labelledby="kokuu-hero-title">',
        '    <div class="kokuu-hero-art">',
        '      <figure class="kokuu-shot" id="kokuu-shot">',
        legacySingleImage
          ? '        <img src="' +
            esc(heroImg) +
            '" alt="KokuuSkin Minecraft 场景" class="kokuu-shot-img kokuu-shot-single">'
          : heroSlides
              .map(function (src, i) {
                return (
                  '        <img src="' +
                  esc(src) +
                  '" alt="Minecraft fantasy world, ' +
                  esc(HERO_SLIDES[i].name) +
                  '" class="kokuu-shot-img kokuu-hero-bg' +
                  (i === 0 ? ' is-on' : '') +
                  '" data-i="' +
                  i +
                  '"' +
                  (i === 0 ? ' fetchpriority="high"' : ' loading="lazy"') +
                  '>'
                )
              })
              .join('\n'),

        '        <div class="kokuu-shot-cap" id="kokuu-shot-cap">' +
          (legacySingleImage
            ? '<span class="px-moon"></span>探索 KokuuSkin'
            : '<span class="px-grass"></span><span id="kokuu-cap-name">' +
              esc(HERO_SLIDES[0].name) +
              '</span><em id="kokuu-cap-tag">' +
              esc(HERO_SLIDES[0].tag) +
              '</em>') +
          '        </div>',
        legacySingleImage
          ? ''
          : '        <div class="kokuu-bg-dots" id="kokuu-bg-dots" role="group" aria-label="选择首页场景">' +
            HERO_SLIDES.map(function (s, i) {
              return (
                '<button type="button" class="kokuu-bg-dot' +
                (i === 0 ? ' is-on' : '') +
                '" data-i="' +
                i +
                '" aria-label="显示' +
                esc(s.name) +
                '场景" aria-pressed="' +
                (i === 0 ? 'true' : 'false') +
                '"></button>'
              )
            }).join('') +
            '        </div>',
        legacySingleImage
          ? ''
          : '        <div class="kokuu-bg-progress" id="kokuu-bg-progress" aria-hidden="true"></div>',
        legacySingleImage
          ? ''
          : '<button type="button" class="kokuu-pause" id="kokuu-pause" aria-pressed="false">暂停</button>',
        '      </figure>',
        '    </div>',
        '    <div class="kokuu-hero-text">',
        '      <p class="kokuu-eyebrow"><span class="px-dot"></span>YOUR LOOK. YOUR ADVENTURE.</p>',
        '      <h1 class="kokuu-title" id="kokuu-hero-title">' +
          esc(cfg.hero_title) +
          '</h1>',
        '      <p class="kokuu-tagline" id="kokuu-hero-tagline">' +
          esc(cfg.tagline) +
          '</p>',
        '      <p class="kokuu-sub" id="kokuu-hero-intro">' +
          esc(cfg.intro) +
          '</p>',
        '      <div class="kokuu-cta" id="kokuu-cta">',
        '        <a class="kbtn primary" href="' +
          base +
          '/skinlib"><span>探索皮肤库</span><span aria-hidden="true">→</span></a>',
        '        <a class="kbtn" href="' +
          base +
          '/auth/register">创建账户</a>',
        '      </div>',
        '      <ul class="kokuu-badges" aria-label="站点服务">',
        '        <li><span class="px-grass"></span>角色与皮肤</li>',
        '        <li><span class="px-water"></span>Yggdrasil 验证</li>',
        '        <li><span class="px-moon"></span>个人衣柜</li>',
        '      </ul>',
        '    </div>',
        '    <a class="kokuu-scroll" href="#kokuu-main" aria-label="继续浏览"><span></span></a>',
        '  </section>',

        /* ---------- 主体 ---------- */
        '  <main class="kokuu-main" id="kokuu-main">',

        '    <section class="kokuu-block kokuu-featured reveal" aria-labelledby="kokuu-featured-title">',
        '      <div class="kokuu-section-head"><div><p class="kokuu-kicker">来自皮肤库</p><h2 class="kokuu-h2" id="kokuu-featured-title">下一次冒险，从新模样开始</h2></div>',
        '      <a class="kokuu-section-link" href="' +
          base +
          '/skinlib">查看皮肤库 <span aria-hidden="true">→</span></a></div>',
        '      <div class="kokuu-featured-grid">',
        FEATURED_SKINS.slice(0, 3)
          .map(function (skin) {
            return (
              '<a class="kokuu-featured-skin" href="' +
              base +
              '/skinlib/show/' +
              skin.id +
              '"><span class="kokuu-featured-art"><img loading="lazy" src="' +
              base +
              '/preview/' +
              skin.id +
              '?height=260&amp;png" alt="Minecraft ' +
              esc(skin.name) +
              ' 皮肤"></span><span class="kokuu-featured-meta"><strong>' +
              esc(skin.name) +
              '</strong><span>' +
              esc(skin.model) +
              ' · 皮肤预览</span></span></a>'
            )
          })
          .join(''),
        '      </div>',
        '    </section>',

        '    <section class="kokuu-block reveal" aria-labelledby="kokuu-services-title">',
        '      <div class="kokuu-section-head">',
        '        <div><p class="kokuu-kicker">属于你的 Minecraft</p>',
        '        <h2 class="kokuu-h2" id="kokuu-services-title">从发现到出发，都在这里</h2></div>',
        '        <a class="kokuu-section-link" href="' +
          base +
          '/skinlib">浏览全部皮肤 <span aria-hidden="true">→</span></a>',
        '      </div>',
        '      <div class="kokuu-cards">',
        '        <a class="kcard" href="' + base + '/skinlib">',
        '          <span class="kcard-index">01</span>',
        '          <div class="kcard-ico"><span class="px-grass"></span></div>',
        '          <h3>发现皮肤</h3>',
        '          <p>换个角度看看细节，找到想带进游戏的那一款。</p>',
        '          <span class="kmore">打开皮肤库 <span aria-hidden="true">↗</span></span>',
        '        </a>',
        '        <a class="kcard" href="' + base + '/skinlib/upload">',
        '          <span class="kcard-index">02</span>',
        '          <div class="kcard-ico"><span class="px-mine"></span></div>',
        '          <h3>上传作品</h3>',
        '          <p>把自己的创作放上来，让更多人遇见你的作品。</p>',
        '          <span class="kmore">上传皮肤 <span aria-hidden="true">↗</span></span>',
        '        </a>',
        '        <a class="kcard" href="' + base + '/auth/login">',
        '          <span class="kcard-index">03</span>',
        '          <div class="kcard-ico"><span class="px-water"></span></div>',
        '          <h3>连接启动器</h3>',
        '          <p>在支持 Yggdrasil 的启动器中添加账号，带着自己的角色出发。</p>',
        '          <span class="kmore">查看登录方式 <span aria-hidden="true">↗</span></span>',
        '        </a>',
        '      </div>',
        '    </section>',

        '    <section class="kokuu-steps reveal" aria-labelledby="kokuu-steps-title">',
        '      <div class="kokuu-steps-copy">',
        '        <p class="kokuu-kicker">从皮肤到游戏</p>',
        '        <h2 class="kokuu-h2" id="kokuu-steps-title">喜欢的模样，随时带上</h2>',
        '        <p>从收藏一款皮肤，到用它开始冒险，只需完成这三步。</p>',
        '      </div>',
        '      <ol class="kokuu-step-list">',
        '        <li><span>1</span><div><strong>挑选外观</strong><p>看看正面与背面，选出最喜欢的一款。</p></div></li>',
        '        <li><span>2</span><div><strong>保存到衣柜</strong><p>将喜欢的皮肤收好，想换的时候就换。</p></div></li>',
        '        <li><span>3</span><div><strong>连接启动器</strong><p>添加验证地址与账号，让角色在游戏里亮相。</p></div></li>',
        '      </ol>',
        '    </section>',

        '  </main>',

        /* ---------- 页脚 ---------- */
        '  <footer class="kokuu-foot">',
        '    <div class="foot-left">',
        '      <span class="kokuu-foot-brand">Kokuu<span class="kokuu-accent">Skin</span></span>',
        '      <p>' + esc(cfg.footer_note) + '</p>',
        '    </div>',
        '    <div class="foot-right">',
        '      <a href="' + base + '/skinlib">皮肤库</a>',
        '      <a href="' + base + '/skinlib/upload">上传</a>',
        '      <a href="' + base + '/auth/login">登录</a>',
        '      <a href="mailto:' + esc(cfg.contact_email) + '">联系</a>',
        '    </div>',
        '    <p class="foot-fine">© 2026 KokuuSkin · Powered by Blessing Skin</p>',
        '  </footer>',

        '</div>',
      ].join('\n')

      document.body.innerHTML = html
      document.body.classList.add('kokuu-on')
      document.title = cfg.hero_title + ' · 皮肤站'

      var featured = document.querySelector('.kokuu-featured')
      if (featured && !FEATURED_SKINS.length) {
        featured.remove()
      }
      initReveal()
      initHeroCarousel()

      var toggle = document.getElementById('kokuu-theme-toggle')
      if (toggle) {
        updateToggleLabel(theme)
        toggle.addEventListener('click', function () {
          applyTheme(currentTheme() === 'void' ? 'paper' : 'void')
        })
      }

      document
        .querySelectorAll('.kokuu-hero-bg, .kokuu-shot-single')
        .forEach(function (img) {
          img.addEventListener('error', function () {
            if (img.dataset.fallback) {
              return
            }
            img.dataset.fallback = 'true'
            img.src = asset + HERO_SLIDES[0].file
          })
        })
      detectLogin(base)
    } catch (e) {
      document.body.style.visibility = 'visible'
      if (window.console) {
        console.error('[KokuuHome]', e)
      }
    }
  }

  /* Hero backgrounds rotate independently from the foreground skin picker. */
  function initHeroCarousel() {
    var dots = document.getElementById('kokuu-bg-dots')
    var hero = document.getElementById('kokuu-hero')
    var images = document.querySelectorAll('.kokuu-shot-img.kokuu-hero-bg')
    var capName = document.getElementById('kokuu-cap-name')
    var capTag = document.getElementById('kokuu-cap-tag')
    var bar = document.getElementById('kokuu-bg-progress')
    if (!hero || !dots || images.length !== HERO_SLIDES.length) {
      return
    }
    var index = 0
    var paused = false

    function show(i) {
      index = (i + images.length) % images.length
      images.forEach(function (img, n) {
        img.classList.toggle('is-on', n === index)
      })
      for (var d = 0; d < dots.children.length; d++) {
        var active = d === index
        dots.children[d].classList.toggle('is-on', active)
        dots.children[d].setAttribute('aria-pressed', active ? 'true' : 'false')
      }
      hero.classList.remove(
        'kokuu-align-left',
        'kokuu-align-left-upper',
        'kokuu-align-left-lower',
        'kokuu-align-right',
      )
      hero.classList.add('kokuu-align-' + HERO_SLIDES[index].align)
      if (capName) {
        capName.textContent = HERO_SLIDES[index].name
      }
      if (capTag) {
        capTag.textContent = HERO_SLIDES[index].tag
      }
      if (bar) {
        bar.classList.remove('is-run')
        void bar.offsetWidth
        bar.classList.add('is-run')
      }
    }
    function stop() {
      if (slideTimer) {
        window.clearInterval(slideTimer)
        slideTimer = null
      }
      if (bar) {
        bar.classList.remove('is-run')
      }
    }
    function start() {
      stop()
      if (paused || document.hidden) {
        return
      }
      if (
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
      ) {
        return
      }
      slideTimer = window.setInterval(function () {
        if (!document.body.contains(hero)) {
          stop()
          return
        }
        show(index + 1)
      }, SLIDE_MS)
    }
    for (var d = 0; d < dots.children.length; d++) {
      ;(function (n) {
        dots.children[n].addEventListener('click', function () {
          show(n)
          start()
        })
      })(d)
    }
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stop()
      } else {
        start()
      }
    })
    var pause = document.getElementById('kokuu-pause')
    if (pause) {
      pause.addEventListener('click', function () {
        paused = !paused
        pause.textContent = paused ? '播放' : '暂停'
        pause.setAttribute('aria-pressed', String(paused))
        if (paused) {
          stop()
        } else {
          start()
        }
      })
    }
    var motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)')
    motionPreference.addEventListener('change', function () {
      motionPreference.matches ? stop() : start()
    })
    show(0)
    start()
  }

  function initReveal() {
    var els = document.querySelectorAll('.reveal')
    if (
      !('IntersectionObserver' in window) ||
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      els.forEach(function (e) {
        e.classList.add('in')
      })
      return
    }
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) {
            en.target.classList.add('in')
            io.unobserve(en.target)
          }
        })
      },
      { threshold: 0.12 },
    )
    els.forEach(function (e) {
      io.observe(e)
    })
  }

  function detectLogin(base) {
    try {
      fetch(base + '/user', {
        credentials: 'same-origin',
        redirect: 'manual',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(function (r) {
          applyAuthState(r.status === 200, base)
        })
        .catch(function () {})
    } catch (e) {}
  }

  function applyAuthState(loggedIn, base) {
    var nav = document.getElementById('kokuu-nav')
    if (nav) {
      nav.innerHTML = loggedIn
        ? '<a href="' +
          base +
          '/skinlib">皮肤库</a>' +
          '<a href="' +
          base +
          '/user">用户中心</a>' +
          '<a href="' +
          base +
          '/user/closet">我的衣柜</a>'
        : '<a href="' +
          base +
          '/skinlib">皮肤库</a>' +
          '<a href="' +
          base +
          '/auth/login">登录</a>' +
          '<a href="' +
          base +
          '/auth/register">注册</a>'
    }
    var cta = document.getElementById('kokuu-cta')
    if (cta) {
      cta.innerHTML = loggedIn
        ? '<a class="kbtn primary" href="' +
          base +
          '/user">进入用户中心</a>' +
          '<a class="kbtn" href="' +
          base +
          '/user/closet">我的衣柜</a>'
        : '<a class="kbtn primary" href="' +
          base +
          '/skinlib">浏览皮肤库</a>' +
          '<a class="kbtn" href="' +
          base +
          '/auth/register">创建账户</a>'
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount)
  } else {
    mount()
  }
})()
