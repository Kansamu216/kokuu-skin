"""
gen_mc_scenes.py — 生成首页轮播用的 6 张 Minecraft 场景像素插画（开发工具）

用途
----
`assets/scenes/*.png` 是首页右侧的自动轮播图（矿洞 / 下界 / 雪原 /
丛林神庙 / 末地 / 村庄）。本脚本用纯 Python（手写 PNG 编码，零第三方
依赖）绘制这 6 张 512×512 像素画。

运行
----
    python tools/gen_mc_scenes.py      # 输出到 assets/scenes/

依赖：仅 Python 3 标准库（zlib / struct / math / random）

可调项
------
每个 `s_xxx()` 函数对应一个场景，内含配色常量、随机种子与构图参数。
修改后重跑即可覆盖生成；想保留某张原图请先备份。

注意：tools/ 目录不会被插件发布到 public/，仅作源码留存。
"""

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成 Minecraft 场景像素插画（纯手写 PNG 编码，无第三方依赖）。
6 个场景，统一 512x512，像素方块语言。
输出到 ../assets/scenes/01-overworld.png … 06-village.png
也保留月下哥伦比亚（挪德卡莱）作为可选第 7 张。
"""
import zlib, struct, math, os

W = H = 512
ASSETS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'assets')
OUT = os.path.join(ASSETS, 'scenes')
os.makedirs(OUT, exist_ok=True)

def blank(c=(0, 0, 0, 0)):
    return [[c for _ in range(W)] for _ in range(H)]

def rgba(c):
    """把 3/4 元组统一成 RGBA"""
    if len(c) == 4: return c
    return (c[0], c[1], c[2], 255)

def put(px, x, y, c):
    if 0 <= x < W and 0 <= y < H:
        px[y][x] = rgba(c) if isinstance(c, tuple) else tuple(c)

def rect(px, x0, y0, w, h, c):
    c = rgba(c)
    x0, y0, w, h = int(x0), int(y0), int(w), int(h)
    for y in range(y0, y0 + h):
        if 0 <= y < H:
            row = px[y]
            for x in range(x0, x0 + w):
                if 0 <= x < W:
                    row[x] = c

def disc(px, cx, cy, r, c):
    c = rgba(c)
    for y in range(int(cy - r - 1), int(cy + r + 2)):
        for x in range(int(cx - r - 1), int(cx + r + 2)):
            if math.hypot(x - cx, y - cy) <= r:
                put(px, x, y, c)

def lerp(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))

def vgrad(px, top, bot, y0=0, y1=H):
    for y in range(y0, y1):
        t = (y - y0) / max(1, (y1 - y0 - 1))
        rect(px, 0, y, W, 1, lerp(top, bot, t))

class R:
    """确定性伪随机"""
    def __init__(self, seed): self.s = seed & 0x7FFFFFFF
    def f(self):
        self.s = (self.s * 1103515245 + 12345) & 0x7FFFFFFF
        return self.s / 0x7FFFFFFF
    def i(self, a, b): return a + int(self.f() * (b - a + 1))
    def pick(self, arr): return arr[int(self.f() * len(arr))]

def png_encode(path, px):
    raw = bytearray()
    for row in px:
        raw.append(0)
        for (r, g, b, a) in row:
            raw += bytes((max(0,min(255,r)), max(0,min(255,g)), max(0,min(255,b)), max(0,min(255,a))))
    def chunk(t, d):
        return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xFFFFFFFF)
    out = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', W, H, 8, 6, 0, 0, 0)) \
        + chunk(b'IDAT', zlib.compress(bytes(raw), 9)) + chunk(b'IEND', b'')
    open(path, 'wb').write(out)
    return len(out)

# ============ 通用构件 ============
GRASS = (106, 170, 72); GRASS_D = (86, 140, 58)
DIRT = (134, 96, 67);  DIRT_D = (110, 78, 54)
STONE = (124, 124, 124); STONE_D = (96, 96, 96); STONE_L = (150, 150, 150)
WOOD = (110, 82, 48);  LEAF = (60, 130, 60); LEAF_D = (46, 104, 48)
SAND = (222, 208, 158); SAND_D = (200, 184, 134)
SNOW = (238, 244, 250); SNOW_D = (206, 220, 234)
WATER = (58, 108, 190); WATER_L = (86, 150, 226)
LAVA = (216, 88, 24);  LAVA_L = (248, 152, 40)
NETH = (96, 24, 32);   NETH_R = (168, 44, 48)
OBS = (28, 22, 40);    OBS_L = (58, 48, 78)
GOLD = (238, 198, 78); DIAMOND = (96, 220, 216)
PLANK = (168, 132, 84)
FIRE = (240, 140, 40)

def ground(px, r, y, top_c, top_d, soil_c, soil_d, depth=None, jitter=3):
    depth = depth or (H - y)
    rect(px, 0, y, W, depth, soil_c)
    # 表层草/雪/沙
    rect(px, 0, y, W, 4, top_c)
    for x in range(0, W, 1):
        if r.f() < 0.5:
            put(px, x, y + 4, top_d)
    # 表层起伏
    for _ in range(W // 6):
        x = r.i(0, W - 1); h = r.i(1, jitter)
        for k in range(h):
            put(px, x, y - k, top_c)
    # 土壤块纹理
    for _ in range((W * depth) // 900):
        x = r.i(0, W - 1); yy = r.i(y + 6, H - 1)
        put(px, x, yy, soil_d)

def blocky_cloud(px, r, x, y, w, h, c=(244, 246, 250)):
    for i in range(0, w, 8):
        hh = h * (1 if i < w * 0.5 else 0.6)
        rect(px, x + i, y - hh, 8, hh, c)
    rect(px, x, y - 2, w, 4, c)

def tree(px, r, x, y, h=60, w=6, leaf=LEAF, leafd=LEAF_D):
    rect(px, x, y - h, w, h, WOOD)
    rect(px, x + 1, y - h, 2, h, (86, 62, 36))
    # 树冠（方块团）
    cw = w + 22
    ch = 26
    for yy in range(ch):
        for xx in range(cw):
            dx = abs(xx - cw // 2) / (cw / 2)
            if dx + abs(yy - ch * 0.55) / ch < 0.95:
                put(px, x - 11 + xx, y - h - ch + yy, leaf if (xx + yy) % 3 else leafd)

def torch(px, x, y):
    rect(px, x, y - 14, 3, 14, WOOD)
    rect(px, x - 1, y - 18, 5, 5, FIRE)
    rect(px, x, y - 21, 3, 4, LAVA_L)

# ============ 场景 1：正午矿洞 ============
def s_mineshaft():
    px = blank(); r = R(1001)
    # 基底：深棕岩壁
    vgrad(px, (66, 50, 38), (40, 30, 24))
    # 洞壁颗粒（只用同色系明暗，不引入彩色）
    for _ in range(4200):
        x = r.i(0, W-1); y = r.i(0, H-1)
        put(px, x, y, r.pick([(58,44,34),(74,58,44),(50,38,30),(82,64,50)]))
    # 洞顶
    for x in range(W):
        h = int(34 * (1 - abs((x - W/2) / (W/2))**2 * 0.45))
        rect(px, x, 0, 1, h + 44, (52, 40, 32))
    # 尽头亮口（隧道深处的光）
    rect(px, 196, 176, 120, 118, (198, 172, 126))
    rect(px, 210, 190, 92, 90, (226, 202, 152))
    # 地面
    ground(px, r, 392, (88,66,50), (68,50,38), (80,58,42), (60,44,32), depth=H-392)
    # 铁轨（近宽远窄，收敛到灭点）
    steps = 50
    for i in range(steps):
        t = i / steps
        y = int(456 + (272 - 456) * t)
        hw = int(300 + (40 - 300) * t)
        # 枕木
        step = max(2, int(hw * 0.13) + 2)
        for sx in range(-hw, hw + 1, step):
            rect(px, 256 + sx, y, max(1, int(2.4*(1-t)+1)), max(1, int(2*(1-t)+1)), (92,64,40))
        # 双轨
        lw = max(1, int(2.4*(1-t)+1))
        rect(px, 256 - hw//2, y, lw, 1, (176,176,180))
        rect(px, 256 + hw//2, y, lw, 1, (176,176,180))
    # 木支撑（4 根，斜撑）
    for (bx, sc) in [(22,1.0),(146,0.7),(360,0.7),(478,1.0)]:
        pw = int(20*sc); ph = int(366*sc); top = 392 - ph
        rect(px, bx, top, pw, ph, (102,74,44))
        rect(px, bx + int(3*sc), top, int(4*sc), ph, (80,58,34))
        rect(px, bx - int(22*sc), top, int(pw + 44*sc), int(15*sc), (110,80,48))
        for k in range(int(34*sc)):
            rect(px, bx + pw + k, top + k, max(1,int(3*sc)), max(1,int(3*sc)), (94,68,40))
        # 火把
        tx = bx + int(pw*0.5)
        rect(px, tx, top + int(12*sc), max(1,int(3*sc)), int(15*sc), (90,66,42))
        rect(px, tx-1, top + int(5*sc), max(2,int(5*sc)), max(2,int(8*sc)), (250,182,66))
    # 顶灯小光晕（仅近距离方块光，不画放射线）
    rect(px, 246, 96, 20, 14, (70,54,40))
    rect(px, 249, 99, 14, 8, (252,206,110))
    for rr in range(46, 0, -8):
        a = int(10 * (1 - rr/46))
        rect(px, 256 - rr, 103 - rr//3, rr*2, 2, (255,220,150,a))
    # 矿车（近景）
    cx, cy = 256, 388
    rect(px, cx-48, cy-42, 96, 42, (116,116,120))
    rect(px, cx-48, cy-42, 96, 7, (154,154,158))
    rect(px, cx-42, cy-34, 84, 26, (46,46,50))
    rect(px, cx-48, cy-12, 96, 5, (70,70,74))
    for wx in (cx-34, cx+20):
        rect(px, wx, cy-7, 17, 17, (58,58,62))
        rect(px, wx+4, cy-3, 9, 9, (142,142,146))
    rect(px, cx-4, cy-46, 9, 9, (70,70,74))
    # 少量矿石（贴近洞壁，稀疏）
    ores = [(96,196),(120,214),(392,190),(416,216),(70,300),(440,296),
            (256,70),(240,84),(276,88),(150,120),(360,126),(160,340),(352,336)]
    for (x, y) in ores:
        c = r.pick([GOLD, (206,110,64), (132,198,100)])
        rect(px, x, y, 7, 7, (28,22,18))
        rect(px, x+1, y+1, 5, 5, c)
        put(px, x+2, y+2, tuple(min(255, v+40) for v in c))
    # 洞顶垂石
    for _ in range(18):
        x = r.i(0, W-6)
        rect(px, x, 46, 6, r.i(8, 30), (56,42,32))
    return px



# ============ 场景 2：下界地狱 ============
def s_nether():
    px = blank(); r = R(2002)
    vgrad(px, (86, 20, 26), (40, 12, 18))
    # 远景下界山脉
    for _ in range(60):
        x = r.i(-40, W); w = r.i(30, 90); h = r.i(40, 150)
        for xx in range(x, x + w):
            t = (xx - x) / w
            top = int(h * (1 - abs(t - .5) * 1.6))
            rect(px, xx, 300 - top, 1, top, NETH if r.f() < .5 else NETH_R)
    # 地面（黑石）
    rect(px, 0, 300, W, H - 300, OBS)
    for _ in range(1800):
        x = r.i(0, W - 1); y = r.i(300, H - 1)
        put(px, x, y, r.pick([OBS_L, (18, 14, 26), (44, 34, 58)]))
    # 岩浆湖
    lava_y = 356
    rect(px, 0, lava_y, W, 40, LAVA)
    for x in range(W):
        if r.f() < .4: put(px, x, lava_y, LAVA_L)
        if r.f() < .2: put(px, x, lava_y + 1, (255, 190, 70))
    for x in range(W):                            # 边缘暗化
        put(px, x, lava_y - 1, (120, 40, 20))
    # 熔岩瀑布
    rect(px, 380, 180, 26, lava_y - 180, LAVA)
    for x in range(380, 406):
        if r.f() < .5: put(px, x, 180 + r.i(0, 30), LAVA_L)
    # 上下界基岩岛 + 蘑菇
    for x in (70, 420):
        rect(px, x, 250, 80, 50, NETH)
        for xx in range(x, x + 80, 6):
            for yy in range(250, 300, 5):
                if r.f() < .5: put(px, xx, yy, NETH_R)
        for _ in range(9):                         # 蘑菇
            mx = x + r.i(4, 70); my = 244
            rect(px, mx, my, 3, 6, (210, 210, 210))
            rect(px, mx - 2, my - 3, 7, 4, (200, 60, 70))
    # 灵魂火
    for _ in range(70):
        x = r.i(0, W - 2); y = r.i(150, 420)
        rect(px, x, y, 2, 2, (250, 214, 120))
    # 烈焰人剪影
    for fx in (250, 272):
        rect(px, fx, 250, 8, 34, (200, 60, 40))
        rect(px, fx - 2, 242, 12, 9, (220, 80, 50))
        rect(px, fx + 8, 256, 14, 3, (220, 80, 50))
        rect(px, fx + 8, 276, 14, 3, (220, 80, 50))
    return px

# ============ 场景 3：雪原 ============
def s_snow():
    px = blank(); r = R(3003)
    vgrad(px, (150, 190, 235), (216, 232, 248))     # 冬日天空
    # 远处雪山
    for mx, mw, mh in [(40, 220, 170), (250, 300, 220), (400, 200, 140)]:
        for xx in range(mx, mx + mw):
            t = (xx - mx) / mw
            top = int(mh * (1 - abs(t - .5) * 1.9))
            rect(px, xx, 330 - top, 1, top, (176, 196, 220) if t < .5 else (200, 216, 236))
            if top > 6: rect(px, xx, 330 - top, 1, 6, SNOW)   # 雪顶
    # 云
    blocky_cloud(px, r, 60, 110, 130, 22)
    blocky_cloud(px, r, 330, 80, 150, 26)
    # 雪地
    ground(px, r, 340, SNOW, SNOW_D, SNOW_D, (196, 210, 228), depth=H - 340)
    # 冰面
    rect(px, 300, 372, 190, 60, (168, 208, 236))
    for _ in range(120):
        x = r.i(300, 490); y = r.i(372, 432)
        put(px, x, y, (196, 226, 246))
    # 雪松树
    for tx, ty, th in [(70, 350, 90), (140, 372, 62), (440, 358, 78)]:
        rect(px, tx, ty - th, 8, th, (74, 58, 40))
        for lv in range(4):
            lw = 54 - lv * 11
            ly = ty - th + 12 + lv * 16
            rect(px, tx + 4 - lw // 2, ly, lw, 10, (52, 112, 76) if lv % 2 else (42, 96, 64))
            rect(px, tx + 4 - lw // 2, ly, lw, 4, SNOW)   # 枝上积雪
    # 冰晶
    for _ in range(40):
        x = r.i(0, W - 1); y = r.i(120, 330)
        rect(px, x, y, 3, 3, (220, 240, 252))
    # 脚印
    for i in range(12):
        x = 60 + i * 30 + r.i(-6, 6); y = 430 + i * 4
        rect(px, x, y, 5, 3, (198, 212, 230))
    return px

# ============ 场景 4：丛林神庙 ============
def s_jungle():
    px = blank(); r = R(4004)
    vgrad(px, (26, 58, 40), (12, 30, 22))
    # 光束
    for bx in (110, 300, 430):
        for i in range(120):
            y = 100 + i
            w = 6 + i // 6
            put(px, bx - w // 2, y, (190, 230, 170, max(0, 26 - i // 6)))
    # 背景丛林剪影（多层）
    for layer, (col, yb) in enumerate([((22, 52, 34), 300), ((30, 68, 44), 340), ((18, 44, 30), 380)]):
        for x in range(0, W, 8):
            th = r.i(90, 210)
            rect(px, x, yb - th, 8, th, col)
            rect(px, x - 6, yb - th - 20, 20, 24, col)   # 树冠团
    # 地面
    ground(px, r, 384, GRASS, GRASS_D, DIRT, DIRT_D, depth=H - 384)
    # 遗迹石阶
    rect(px, 0, 384, W, 16, STONE_D)
    rect(px, 0, 400, W, 18, STONE)
    rect(px, 0, 418, W, 20, STONE_L)
    for x in range(0, W, 28):                          # 石缝
        put(px, x, 400, STONE_D); put(px, x + 14, 418, STONE_D)
    # 神庙入口（阶梯 + 石柱）
    cx = 256
    rect(px, cx - 84, 300, 168, 84, STONE_D)           # 后墙
    rect(px, cx - 62, 320, 124, 64, (40, 36, 44))     # 门洞
    for px_ in (cx - 78, cx + 62):                     # 柱
        rect(px, px_, 268, 18, 116, STONE)
        rect(px, px_ + 2, 268, 4, 116, STONE_L)
        rect(px, px_ - 4, 262, 26, 8, STONE_L)         # 柱头
    # 苔藓与藤蔓
    for _ in range(700):
        x = r.i(0, W - 1); y = r.i(300, H - 1)
        put(px, x, y, r.pick([GRASS_D, (58, 110, 58), (46, 92, 48)]))
    for _ in range(26):                                # 垂藤
        vx = r.i(0, W - 1)
        vl = r.i(30, 120)
        for y in range(0, vl, 4):
            put(px, vx, y, (40, 92, 44))
    # 发光蘑菇
    for _ in range(16):
        x = r.i(0, W - 1); y = r.i(400, H - 6)
        rect(px, x, y, 3, 4, (200, 210, 200))
        rect(px, x - 2, y - 3, 7, 4, (120, 240, 200))
    # 火把
    torch(px, cx - 100, 388); torch(px, cx + 92, 388)
    return px

# ============ 场景 5：末地 ============
def s_end():
    px = blank(); r = R(5005)
    vgrad(px, (12, 10, 26), (5, 4, 14))
    for _ in range(1600):                           # 虚空星
        x = r.i(0, W-1); y = r.i(0, H-1)
        c = r.pick([(255,255,255),(196,196,236),(158,148,208),(120,110,170)])
        s_ = 2 if r.f() < .82 else 3
        rect(px, x, y, s_, s_, c)
    # 中央黑曜石平台（三层台阶）
    rect(px, 76, 344, 360, 12, (34,28,48))
    rect(px, 96, 330, 320, 14, (44,36,60))
    rect(px, 116, 316, 280, 14, (56,46,74))
    for x in range(76, 436):
        if r.f() < .3: put(px, x, 344, (66,54,88))
    # 末地烛（方块光晕，不画放射线）
    for cx in (156, 200, 312, 356):
        rect(px, cx, 246, 5, 70, (34,28,42))        # 烛身
        rect(px, cx-1, 238, 7, 9, (252,230,158))    # 焰
        rect(px, cx, 234, 5, 5, (255,244,206))
        for rr in range(30, 0, -7):                  # 方形光晕（阶梯状）
            a = int(12 * (1 - rr/30))
            rect(px, cx + 2 - rr, 242 - rr//2, rr*2, 2, (255,238,190,a))
    # 末影龙（深紫黑 + 描边，浮于平台上方）
    dx, dy = 256, 168
    DR = (26, 20, 38); DL = (44, 34, 62)
    for i in range(44):                              # 翼（先画，压在身下）
        for t in range(9):
            rect(px, dx-56-i, dy+6-i//2-t*2, 3, 5, DL if t % 2 else DR)
            rect(px, dx+56+i, dy+6-i//2-t*2, 3, 5, DL if t % 2 else DR)
    rect(px, dx-58, dy-2, 20, 7, DR)                # 尾
    rect(px, dx+58, dy-2, 20, 7, DR)
    rect(px, dx-62, dy+30, 120, 44, DR)             # 身体
    rect(px, dx-62, dy+30, 120, 5, DL)              # 背脊高光
    rect(px, dx-58, dy+70, 20, 6, DL)               # 尾鳍
    rect(px, dx-9, dy-30, 18, 32, DR)               # 颈
    rect(px, dx-9, dy-30, 5, 32, DL)
    rect(px, dx-16, dy-50, 32, 22, DR)              # 头
    rect(px, dx-16, dy-50, 32, 5, DL)
    rect(px, dx-22, dy-42, 6, 5, DR)                # 角
    rect(px, dx+16, dy-42, 6, 5, DR)
    put(px, dx-9, dy-40, (255,92,92)); put(px, dx+4, dy-40, (255,92,92))   # 眼
    put(px, dx-8, dy-39, (255,210,210)); put(px, dx+5, dy-39, (255,210,210))
    # 黑曜石柱
    for cx in (34, 452):
        rect(px, cx, 120, 24, 300, (30,24,42))
        rect(px, cx+3, 120, 5, 300, (48,38,66))
        rect(px, cx-7, 106, 38, 14, (48,38,66))
        rect(px, cx-12, 94, 48, 12, (30,24,42))
    # 虚空方块
    for _ in range(26):
        x = r.i(0, W-5); y = r.i(420, H-5)
        rect(px, x, y, 5, 5, (20,16,28))
        put(px, x+1, y+1, (44,34,62))
    return px


# ============ 场景 6：村庄 ============
def s_village():
    px = blank(); r = R(6006)
    vgrad(px, (126, 178, 232), (198, 222, 244))
    blocky_cloud(px, r, 40, 90, 120, 20)
    blocky_cloud(px, r, 320, 66, 140, 24)
    # 远山
    for mx, mw, mh in [(20, 180, 90), (330, 200, 110)]:
        for xx in range(mx, mx + mw):
            t = (xx - mx) / mw
            top = int(mh * (1 - abs(t - .5) * 1.8))
            rect(px, xx, 320 - top, 1, top, (120, 158, 128))
    # 草地
    ground(px, r, 320, GRASS, GRASS_D, DIRT, DIRT_D, depth=H - 320)
    # 农田
    rect(px, 20, 392, 150, 96, DIRT)
    for y in range(392, 488, 8):
        rect(px, 20, y, 150, 3, (150, 116, 78))
    for _ in range(240):                              # 作物
        x = r.i(22, 168); y = r.i(396, 486)
        put(px, x, y, (90, 160, 70))
        if r.f() < .2: rect(px, x, y - 3, 3, 3, (216, 200, 90))
    # 主屋（橡木 + 梯形屋顶）
    hx, hy, hw, hh = 230, 236, 170, 96
    rect(px, hx, hy, hw, hh, PLANK)                   # 墙
    for y in range(hy, hy + hh, 6):                   # 木纹
        rect(px, hx, y, hw, 1, (148, 114, 70))
    rect(px, hx - 8, hy - 46, hw + 16, 46, (150, 74, 52))   # 屋顶
    for i in range(46):                               # 阶梯收顶
        rect(px, hx - 8 + i // 2, hy - 46 - i, hw + 16 - i, 1, (150, 74, 52) if i % 6 else (124, 58, 42))
    rect(px, hx + 66, hy + 34, 38, 62, (92, 64, 40)) # 门
    rect(px, hx + 30, hy + 30, 28, 26, (168, 210, 236))  # 窗
    rect(px, hx + 112, hy + 30, 28, 26, (168, 210, 236))
    for wx in (hx + 30, hx + 112):
        rect(px, wx + 13, hy + 30, 2, 26, (92, 64, 40))
        rect(px, wx, hy + 42, 28, 2, (92, 64, 40))
    rect(px, hx + 78, hy - 92, 14, 48, STONE_D)      # 烟囱
    rect(px, hx + 74, hy - 96, 22, 6, STONE)
    # 副屋
    rect(px, 60, 268, 110, 64, (150, 112, 72))
    rect(px, 52, 232, 126, 36, (150, 74, 52))
    rect(px, 96, 296, 30, 36, (92, 64, 40))
    # 树
    tree(px, r, 440, 350, 66, 7)
    tree(px, r, 176, 336, 50, 6)
    # 栅栏
    for x in range(0, W, 16):
        rect(px, x, 356, 5, 22, PLANK)
    rect(px, 0, 362, W, 4, PLANK)
    rect(px, 0, 372, W, 4, PLANK)
    # 花与草丛
    for _ in range(240):
        x = r.i(0, W - 1); y = r.i(326, H - 1)
        put(px, x, y, r.pick([GRASS_D, (240, 200, 80), (230, 120, 140), (250, 250, 250)]))
    # 鸡
    for fx, fy in [(196, 336), (330, 372)]:
        rect(px, fx, fy - 8, 10, 8, (248, 248, 248))
        rect(px, fx + 8, fy - 12, 6, 6, (248, 248, 248))
        put(px, fx + 12, fy - 10, (40, 40, 40))
        put(px, fx + 14, fy - 8, (232, 180, 60))
        rect(px, fx + 2, fy - 2, 2, 2, (232, 160, 40))
        rect(px, fx + 6, fy - 2, 2, 2, (232, 160, 40))
    return px

SCENES = [
    ('01-mineshaft.png', s_mineshaft),
    ('02-nether.png',    s_nether),
    ('03-snow.png',      s_snow),
    ('04-jungle.png',    s_jungle),
    ('05-end.png',       s_end),
    ('06-village.png',   s_village),
]

if __name__ == '__main__':
    for name, fn in SCENES:
        px = fn()
        size = png_encode(os.path.join(OUT, name), px)
        print(f'  {name}  {size} bytes')
    print('全部场景生成完成 →', OUT)
