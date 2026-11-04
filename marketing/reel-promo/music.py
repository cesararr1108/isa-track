"""Pista original para el reel de ISA Track (31 s, 120 BPM), sincronizada con las escenas.

0–7.6 s   intro con tensión (pad + pulso suave): el problema
7.6 s     "drop": entra el ritmo completo con el logo
7.6–26.8  ritmo pop alegre (bombo, palmas, platillos, bajo, acordes, arpegio)
26.8–31   llamado a la acción: ritmo + campanita, final con cierre
Efectos de transición en cada cambio de escena.
"""
import numpy as np, wave, sys

SR = 48000
DUR = 31.0
N = int(SR * DUR)
L = np.zeros(N); R = np.zeros(N)
rng = np.random.default_rng(7)

BPM = 120; BEAT = 60 / BPM
DROP = 7.6
SCENES = [3.8, 7.6, 10.6, 15.4, 19.2, 23.0, 26.8]


def t_arr(n): return np.arange(n) / SR

def add(sig, start, gain=1.0, pan=0.0):
    i = int(start * SR)
    if i >= N: return
    sig = sig[: N - i]
    l = gain * np.cos((pan + 1) * np.pi / 4); r = gain * np.sin((pan + 1) * np.pi / 4)
    L[i:i + len(sig)] += sig * l * 1.4142; R[i:i + len(sig)] += sig * r * 1.4142

def env(n, a=0.005, d=0.2, s=0.0, rel=0.05, hold=None):
    t = t_arr(n); e = np.ones(n)
    ai = int(a * SR); e[:ai] = np.linspace(0, 1, max(ai, 1))
    e[ai:] = s + (1 - s) * np.exp(-(t[ai:] - a) / max(d, 1e-4))
    if rel and n > int(rel * SR):
        ri = int(rel * SR); e[-ri:] *= np.linspace(1, 0, ri)
    return e

def note(f): return 440.0 * 2 ** ((f - 69) / 12)

def kick():
    n = int(0.35 * SR); t = t_arr(n)
    f = 45 + 110 * np.exp(-t * 28)
    ph = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(ph) * np.exp(-t * 9) + 0.15 * np.sin(ph * 2) * np.exp(-t * 30)

def clap():
    n = int(0.25 * SR); t = t_arr(n)
    nz = rng.standard_normal(n)
    e = np.exp(-t * 22)
    for k in (0.0, 0.012, 0.024):
        e += 0.6 * np.exp(-np.maximum(t - k, 0) * 120) * (t >= k)
    # filtro pasa-banda simple (diferencia de suavizados)
    sm = np.convolve(nz, np.ones(6) / 6, 'same')
    return (nz - sm) * e * 0.5

def hat(open_=False):
    n = int((0.18 if open_ else 0.06) * SR); t = t_arr(n)
    nz = rng.standard_normal(n); hp = nz - np.convolve(nz, np.ones(3) / 3, 'same')
    return hp * np.exp(-t * (14 if open_ else 70)) * 0.35

def saw(f, n, detune=0.0):
    t = t_arr(n); out = np.zeros(n)
    for d in (-detune, 0, detune):
        ph = (f * (1 + d) * t) % 1.0
        out += 2 * ph - 1
    return out / 3

def lowpass(x, k=12):
    return np.convolve(x, np.ones(k) / k, 'same')

def pad(midi_notes, dur):
    n = int(dur * SR); out = np.zeros(n)
    for m in midi_notes:
        out += lowpass(saw(note(m), n, 0.004), 30)
    e = np.minimum(1, t_arr(n) / 0.25) * np.minimum(1, (dur - t_arr(n)) / 0.3)
    return out * e / len(midi_notes)

def bass(m, dur):
    n = int(dur * SR); t = t_arr(n)
    f = note(m)
    x = np.sin(2 * np.pi * f * t) + 0.35 * np.sin(4 * np.pi * f * t)
    return x * env(n, 0.004, 0.35, 0.5, 0.04)

def pluck(m, dur=0.22):
    n = int(dur * SR); t = t_arr(n)
    x = lowpass(saw(note(m), n), 6) + 0.3 * np.sin(2 * np.pi * note(m) * 2 * t)
    return x * np.exp(-t * 14)

def bell(m, dur=1.6):
    n = int(dur * SR); t = t_arr(n); f = note(m)
    x = np.sin(2 * np.pi * f * t) + 0.5 * np.sin(2 * np.pi * f * 2.76 * t) * np.exp(-t * 6) + 0.25 * np.sin(2 * np.pi * f * 5.4 * t) * np.exp(-t * 10)
    return x * np.exp(-t * 2.6)

def whoosh(dur=0.55, rise=True):
    n = int(dur * SR); t = t_arr(n)
    nz = rng.standard_normal(n)
    out = np.zeros(n); k_prev = 40
    # barrido de filtro: mezcla de versiones más o menos suavizadas
    a = np.convolve(nz, np.ones(40) / 40, 'same'); b = nz - np.convolve(nz, np.ones(4) / 4, 'same')
    w = (t / dur) if rise else (1 - t / dur)
    out = a * (1 - w) + b * w * 0.5
    e = np.sin(np.pi * t / dur) ** 2
    return out * e

def impact():
    n = int(1.2 * SR); t = t_arr(n)
    sub = np.sin(2 * np.pi * (38 + 40 * np.exp(-t * 6)) * t) * np.exp(-t * 2.5)
    nz = np.convolve(rng.standard_normal(n), np.ones(20) / 20, 'same') * np.exp(-t * 5)
    return sub + 0.6 * nz

# Progresión (C mayor, alegre): C – G – Am – F, un acorde por compás (2 s)
CHORDS = [([60, 64, 67], 36), ([59, 62, 67], 43), ([57, 60, 64], 45), ([57, 60, 65], 41)]
BAR = 4 * BEAT

# ---------- Intro (0 – 7.6 s): pad grave + pulso ----------
t0 = DROP - 4 * BAR  # 4 compases que terminan justo en el drop
for b in range(4):
    st = t0 + b * BAR
    if st + BAR <= 0: continue
    notes, root = CHORDS[[2, 3, 2, 1][b]]
    p = pad([m - 12 for m in notes], BAR)
    if st < 0: p = p[int(-st * SR):]; st = 0
    add(p, st, 0.16)
    for k in range(4):
        tt = st + k * BEAT
        if tt >= 0: add(bass(root, BEAT * 0.9), tt, 0.10)
# tic-tac de tensión que acelera hacia el drop
for k in range(28):
    tt = DROP - 0.5 * (0.93 ** k) * (k + 1) * 0.5
for k in np.arange(0.25, DROP, 0.5):
    add(hat(), k, 0.25, 0.3)
add(whoosh(1.2, True), DROP - 1.2, 0.35)

# ---------- Drop y cuerpo (7.6 – 31 s) ----------
add(impact(), DROP, 0.55)
add(bell(84, 2.2), DROP + 0.05, 0.18, -0.2)
bars = int((DUR - DROP) / BAR) + 1
for b in range(bars):
    st = DROP + b * BAR
    notes, root = CHORDS[b % 4]
    add(pad(notes, BAR), st, 0.12)
    final = st >= 26.8
    for k in range(4):
        tt = st + k * BEAT
        if tt >= DUR - 0.4: break
        add(kick(), tt, 0.55)
        if k in (1, 3): add(clap(), tt, 0.32)
        add(hat(), tt + BEAT / 2, 0.28, 0.25)
        add(hat(), tt + BEAT / 4 * 3, 0.12, -0.25)
        add(bass(root, BEAT * 0.45), tt, 0.22)
        add(bass(root + 12 if k % 2 else root, BEAT * 0.4), tt + BEAT / 2, 0.15)
    # arpegio
    arp = [notes[0] + 12, notes[1] + 12, notes[2] + 12, notes[1] + 12]
    for k in range(8):
        tt = st + k * BEAT / 2
        if tt >= DUR - 0.5: break
        add(pluck(arp[k % 4]), tt, 0.10, 0.35 if k % 2 else -0.35)

# ---------- Efectos de transición ----------
for s in SCENES:
    if s == DROP: continue
    add(whoosh(0.5, True), s - 0.42, 0.22, 0.0)
# campanita del llamado a la acción ("Escribe PILOTO" aparece en 27.7 s)
for i, m in enumerate([79, 84, 88]):
    add(bell(m, 1.4), 27.7 + i * 0.09, 0.14, -0.3 + i * 0.3)

# ---------- Final y mezcla ----------
fade = np.ones(N); fi = int(1.6 * SR); fade[-fi:] = np.linspace(1, 0, fi) ** 1.5
L *= fade; R *= fade
mix = np.stack([L, R], axis=1)
mix = np.tanh(mix * 1.1)            # saturación suave
mix /= np.max(np.abs(mix)) + 1e-9
mix *= 0.89                          # deja margen (≈ -1 dBFS)
pcm = (mix * 32767).astype(np.int16)
with wave.open(sys.argv[1], 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes(pcm.tobytes())
print('ok', DUR, 's')
