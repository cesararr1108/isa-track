"""Mezcla voz + música con 'ducking': la música baja mientras habla la voz."""
import sys, wave, numpy as np
def read(p):
    with wave.open(p) as w:
        x = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype(np.float32) / 32768
        return x.reshape(-1, w.getnchannels()), w.getframerate()
music, sr = read(sys.argv[1]); voice, sr2 = read(sys.argv[2]); assert sr == sr2 == 48000
n = min(len(music), len(voice)); music, voice = music[:n], voice[:n, 0]
# Envolvente de la voz: ataque rápido, liberación lenta
lvl = np.abs(voice)
win = int(0.03 * sr); lvl = np.convolve(lvl, np.ones(win) / win, 'same')
env = np.zeros(n); g = 0.0; rel = np.exp(-1 / (0.35 * sr)); att = np.exp(-1 / (0.02 * sr))
on = (lvl > 0.01).astype(np.float32)
for i in range(n):
    c = att if on[i] > g else rel
    g = c * g + (1 - c) * on[i]; env[i] = g
duck = 1 - 0.68 * env                      # la música baja ~10 dB cuando hay voz
mix = music * duck[:, None] * 0.9 + (voice * 1.35)[:, None]
mix /= max(1.0, np.max(np.abs(mix)) / 0.95)
with wave.open(sys.argv[3], 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(sr); w.writeframes((mix * 32767).astype(np.int16).tobytes())
print('ok')
