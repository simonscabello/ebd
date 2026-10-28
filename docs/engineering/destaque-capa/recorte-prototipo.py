"""Protótipo do que o PHP vai fazer: recorta o fundo papel a partir das bordas e apara a cena."""
import sys, numpy as np
from collections import deque
from PIL import Image, ImageFilter

def recorte(path, out):
    im = Image.open(path).convert('RGB')
    a = np.asarray(im).astype(int); h, w, _ = a.shape
    border = np.concatenate([a[0], a[-1], a[:, 0], a[:, -1]])
    paper = np.median(border, axis=0)
    near = (np.abs(border - paper).sum(axis=1) < 38).mean()
    light = paper.mean() > 215
    if near < 0.6 or not light:
        return f'sem recorte (borda lisa {near:.0%}, papel claro={light})'
    bg = np.abs(a - paper).sum(axis=2) < 38
    seen = np.zeros((h, w), bool); q = deque()
    for y in range(h):
        for x in range(w):
            if (y in (0, h-1) or x in (0, w-1)) and bg[y, x]:
                seen[y, x] = True; q.append((y, x))
    while q:
        y, x = q.popleft()
        for ny, nx in ((y+1, x), (y-1, x), (y, x+1), (y, x-1)):
            if 0 <= ny < h and 0 <= nx < w and not seen[ny, nx] and bg[ny, nx]:
                seen[ny, nx] = True; q.append((ny, nx))
    removed = seen.mean()
    if not 0.2 < removed < 0.95:
        return f'sem recorte (fundo removido {removed:.0%})'
    alpha = Image.fromarray(np.where(seen, 0, 255).astype('uint8')).filter(ImageFilter.GaussianBlur(1.2))
    o = im.copy(); o.putalpha(alpha)
    bbox = Image.fromarray((np.asarray(alpha) > 24).astype('uint8') * 255).getbbox()
    o = o.crop(bbox); o.save(out)
    return f'recorte ok: fundo {removed:.0%}, cena {o.size[0]}x{o.size[1]} ({o.size[0]/o.size[1]:.2f}:1)'

for p in sys.argv[1:]:
    print(p, '→', recorte(p, p.rsplit('.', 1)[0] + '-recorte.png'))
