"""
gen_moon_art.py — 生成「月下哥伦比娅」像素插画（开发工具）

用途
----
`assets/moon-columbina.png` 是首页在未配置自定义图片时的默认插画。
本脚本用纯 Python（zlib + struct 手写 PNG 编码，零第三方依赖）绘制该图，
便于调整构图与配色后重新生成。

运行
----
    python tools/gen_moon_art.py      # 输出到 assets/moon-columbina.png

依赖：仅 Python 3 标准库（zlib / struct / math / random）

注意：tools/ 目录不会被插件发布到 public/，仅作源码留存。
"""

#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成「月下哥伦比亚」Minecraft 像素风插画（纯手写 PNG，无第三方依赖）。
画面：挪德卡莱月夜 —— 巨大月盘、星海、哥白鸽（Columbina 的白鸽意象）、
      哥特式阶梯/尖塔剪影、飘浮的月之碎片。冷蓝紫调 + 像素方块。
输出：moon-columbina.png（512x512 RGBA）
"""
import zlib, struct, math, os

W = H = 512

# ---------- 调色板（挪德卡莱月夜冷色） ----------
PAL = {
    '.': (0, 0, 0, 0),          # transparent
    'd': (13, 12, 34, 255),     # 深空底
    'D': (22, 20, 52, 255),     # 次深
    'b': (36, 32, 82, 255),     # 夜空渐变
    'B': (52, 46, 110, 255),    # 夜空亮
    'm': (122, 106, 178, 255),  # 月晕
    'M': (168, 150, 224, 255),  # 月盘亮
    'W': (232, 228, 255, 255),  # 月盘高光/白鸽
    'w': (198, 192, 240, 255),  # 白鸽暗
    's': (86, 176, 200, 255),   # 冷青星
    'S': (140, 220, 235, 255),  # 亮青星
    'p': (196, 168, 236, 255),  # 淡紫雾
}

def blank():
    return [[PAL['.'] for _ in range(W)] for _ in range(H)]

def put(px, x, y, c):
    if 0 <= x < W and 0 <= y < H:
        px[y][x] = PAL[c] if isinstance(c, str) else c

def rect(px, x0, y0, w, h, c):
    x0 = int(x0); y0 = int(y0); w = int(w); h = int(h)
    for y in range(y0, y0 + h):
        for x in range(x0, x0 + w):
            put(px, x, y, c)

def disc(px, cx, cy, r, c, soft=None):
    for y in range(int(cy - r - 2), int(cy + r + 3)):
        for x in range(int(cx - r - 2), int(cx + r + 3)):
            d = math.hypot(x - cx, y - cy)
            if d <= r:
                put(px, x, y, c)
            elif soft and d <= r + soft:
                # 柔边（月晕）
                t = (d - r) / soft
                base = PAL[c]
                bg = PAL['b']
                put(px, x, y, (int(base[0]*(1-t)+bg[0]*t),
                               int(base[1]*(1-t)+bg[1]*t),
                               int(base[2]*(1-t)+bg[2]*t), 255))

# ---------- 1. 夜空垂直渐变（带像素抖动） ----------
px = blank()
for y in range(H):
    t = y / H
    if t < 0.55:
        c = PAL['d']
    elif t < 0.78:
        c = PAL['D']
    else:
        c = PAL['b']
    rect(px, 0, y, W, 1, c)

# 像素噪点星尘（确定性伪随机）
seed = 20261002
def rnd():
    global seed
    seed = (seed * 1103515245 + 12345) & 0x7FFFFFFF
    return seed / 0x7FFFFFFF

for _ in range(900):
    x = int(rnd() * W); y = int(rnd() * int(H * 0.82))
    c = 's' if rnd() < 0.7 else 'S'
    put(px, x, y, c)
# 少量亮星带十字光芒
for _ in range(18):
    x = int(rnd() * W); y = int(rnd() * int(H * 0.6))
    put(px, x, y, 'W')
    if rnd() < 0.5:
        put(px, x + 1, y, 'M'); put(px, x - 1, y, 'M')
        put(px, x, y + 1, 'M'); put(px, x, y - 1, 'M')

# ---------- 2. 巨大月盘（左上偏中） ----------
# 外晕用多层递减透明感的实心圆叠出柔边（避免方形硬边）
disc(px, 196, 150, 104, 'D')
disc(px, 196, 150, 100, 'b')
disc(px, 196, 150, 96, 'D')
disc(px, 196, 150, 92, 'b')
disc(px, 196, 150, 88, 'B')
disc(px, 196, 150, 84, 'm')
disc(px, 196, 150, 78, 'M')
disc(px, 196, 150, 70, 'W')               # 高光面
# 月面环形山（像素块）
for _ in range(26):
    a = rnd() * math.tau
    rr = rnd() * 48
    cx = 196 + math.cos(a) * rr
    cy = 150 + math.sin(a) * rr
    s = 3 + int(rnd() * 7)
    col = 'm' if rnd() < 0.6 else 'M'
    rect(px, int(cx), int(cy), s, s, col)

# ---------- 3. 远景挪德卡莱剪影（尖塔/教堂） ----------
def tower(x0, base_y, w, h, c):
    rect(px, x0, base_y - h, w, h, c)
    # 尖顶
    for i in range(w // 2 + 2):
        ww = 1 if i < w // 2 + 1 else 1
        rect(px, x0 + w // 2 - i, base_y - h - i, ww * 2 - 1, 1, c)
    # 窗（暖光小点）
    for _ in range(max(1, (w * h) // 900)):
        wx = x0 + 3 + int(rnd() * (w - 6))
        wy = base_y - h + 6 + int(rnd() * (h - 10))
        put(px, wx, wy, 'M')

# 地面线
ground = 400
rect(px, 0, ground, W, H - ground, 'D')
# 远处塔群
for x0, w, h in [(20, 34, 120), (70, 26, 86), (330, 30, 100), (376, 22, 70), (410, 40, 140), (466, 26, 92)]:
    tower(x0, ground, w, h, 'd')
# 近景大塔（左）
tower(96, ground, 52, 190, 'd')

# ---------- 4. 哥白鸽（Columbina 意象，居中偏右，飞向月亮） ----------
def dove(cx, cy, s):
    """V 形展翅的鸽子。s=像素单位。"""
    # 身体（纺锤形）
    rect(px, cx - 9 * s, cy - 3 * s, 18 * s, 8 * s, 'W')
    rect(px, cx - 11 * s, cy - 1 * s, 22 * s, 5 * s, 'W')
    # 头 + 颈
    rect(px, cx + 9 * s, cy - 7 * s, 6 * s, 6 * s, 'W')
    rect(px, cx + 8 * s, cy - 4 * s, 3 * s, 3 * s, 'W')
    # 眼
    put(px, cx + 12 * s, cy - 5 * s, 'b')
    # 喙
    rect(px, cx + 15 * s, cy - 4 * s, 2 * s, 2 * s, 'M')
    # 翅：V 形上举（左翼后掠、右翼前扬），用递减像素块堆出羽翼
    # 左翅（向后上方展开）
    for i in range(11):
        ln = 2 * s
        rect(px, cx - 8 * s - i * 2 * s, cy - 5 * s - i * (1.4 * s), ln, 2 * s, 'w' if i % 2 else 'W')
    # 右翅（向前上方展开，更高）
    for i in range(12):
        ln = 2 * s
        rect(px, cx + 6 * s + i * 2 * s, cy - 6 * s - i * (1.7 * s), ln, 2 * s, 'w' if i % 2 else 'W')
    # 尾羽（三层扇形）
    for i in range(5):
        rect(px, cx - 13 * s - i * 2 * s, cy + 1 * s + (0 if i % 2 else 1), 2 * s, 5 * s - (i % 2), 'w' if i % 2 else 'W')
    # 胸腹阴影
    rect(px, cx - 4 * s, cy + 2 * s, 8 * s, 2 * s, 'w')
    # 爪（收起飞行中）
    put(px, cx + 1 * s, cy + 5 * s, 'M')
    put(px, cx + 4 * s, cy + 5 * s, 'M')

dove(336, 226, 3)

# ---------- 5. 飘浮的月之碎片（发光小方块） ----------
for _ in range(26):
    x = int(120 + rnd() * 300)
    y = int(60 + rnd() * 300)
    s = 2 + int(rnd() * 4)
    col = 'M' if rnd() < 0.5 else 'p'
    rect(px, x, y, s, s, col)
    # 光晕
    if rnd() < 0.5:
        put(px, x - 1, y + s // 2, col); put(px, x + s, y + s // 2, col)

# ---------- 6. 底部雾（挪德卡莱银月之庭的冷雾） ----------
for y in range(ground - 26, ground + 6):
    t = (y - (ground - 26)) / 32
    a = int(70 * (1 - t))
    for x in range(W):
        base = px[y][x]
        if base[3] > 0:
            px[y][x] = (min(255, base[0] + a), min(255, base[1] + a), min(255, base[2] + a + 6), base[3])

# ---------- 编码 PNG ----------
def png_encode(path, pixels):
    raw = bytearray()
    for row in pixels:
        raw.append(0)  # filter type 0
        for (r, g, b, a) in row:
            raw += bytes((r, g, b, a))
    def chunk(typ, data):
        c = struct.pack('>I', len(data)) + typ + data
        c += struct.pack('>I', zlib.crc32(typ + data) & 0xFFFFFFFF)
        return c
    sig = b'\x89PNG\r\n\x1a\n'
    ihdr = struct.pack('>IIBBBBB', W, H, 8, 6, 0, 0, 0)  # 8-bit RGBA
    out = sig + chunk(b'IHDR', ihdr) + chunk(b'IDAT', zlib.compress(bytes(raw), 9)) + chunk(b'IEND', b'')
    with open(path, 'wb') as f:
        f.write(out)
    return len(out)

ASSETS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'assets')
out_path = os.path.join(ASSETS, 'moon-columbina.png')
size = png_encode(out_path, px)
print('生成完成:', out_path, size, 'bytes')
