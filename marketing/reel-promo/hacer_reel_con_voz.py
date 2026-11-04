"""Arma el reel de ISA Track con voz colombiana de IA (Microsoft Azure) en un solo paso.

Uso:
    python3 marketing/reel-promo/hacer_reel_con_voz.py --voz salome      # o: gonzalo

Requisitos (una sola vez, en la configuración del entorno, nunca en el código):
    AZURE_SPEECH_KEY     llave del recurso "Speech" de Azure (plan gratuito F0 sirve)
    AZURE_SPEECH_REGION  región del recurso, p. ej. eastus
    Red: permitir el dominio <region>.tts.speech.microsoft.com
Además: numpy y ffmpeg instalados.

Resultado: marketing/reel-promo/isa-track-promo-voz-<voz>.mp4 (1080x1920, -14 LUFS, listo para Instagram).
"""
import argparse, os, subprocess, sys, urllib.request, wave
from xml.sax.saxutils import escape

AQUI = os.path.dirname(os.path.abspath(__file__))
VOCES = {'salome': 'es-CO-SalomeNeural', 'gonzalo': 'es-CO-GonzaloNeural'}
SR = 48000
DURACION = 31.0

# (inicio en s, fin máximo en s, texto). Mismos tiempos que las escenas de promo.html.
GUION = [
    (0.25, 3.75, '¿Otra vez llamando a preguntar dónde va el pedido?'),
    (3.95, 7.55, 'Clientes reclamando... ¡y la caja que no cuadra!'),
    (7.85, 10.55, 'Tranqui... para eso está ISA Track.'),
    (10.85, 15.3, 'Las rutas se arman solitas, y el domicilio se cobra por zona.'),
    (15.65, 19.1, 'Ves cada pedido en vivo... ¡con foto y firma!'),
    (19.45, 22.95, 'Y la caja de cada mensajero cuadra sola. ¡Cero enredos!'),
    (23.25, 26.7, 'Tu cliente ve su pedido... sin llamarte.'),
    (27.05, 30.6, '¿Tienes mensajeros? Escríbenos PILOTO. ¡Hecho en Medellín!'),
]


def sintetizar(texto: str, voz: str, velocidad: int, llave: str, region: str) -> bytes:
    """Pide a Azure la frase en WAV mono 48 kHz. velocidad en %, p. ej. 5 = 5 % más rápido."""
    ssml = (f'<speak version="1.0" xml:lang="es-CO"><voice name="{voz}">'
            f'<prosody rate="{velocidad:+d}%">{escape(texto)}</prosody></voice></speak>')
    req = urllib.request.Request(
        f'https://{region}.tts.speech.microsoft.com/cognitiveservices/v1',
        data=ssml.encode('utf-8'),
        headers={
            'Ocp-Apim-Subscription-Key': llave,
            'Content-Type': 'application/ssml+xml',
            'X-Microsoft-OutputFormat': 'riff-48khz-16bit-mono-pcm',
            'User-Agent': 'isa-track-reel',
        },
        method='POST',
    )
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read()


def leer_wav(datos: bytes):
    import io
    import numpy as np
    with wave.open(io.BytesIO(datos)) as w:
        assert w.getframerate() == SR and w.getnchannels() == 1
        return np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype('float32') / 32768


def main() -> None:
    import numpy as np
    p = argparse.ArgumentParser()
    p.add_argument('--voz', choices=VOCES, default='salome')
    p.add_argument('--velocidad', type=int, default=6, help='% sobre la velocidad normal (por defecto 6)')
    a = p.parse_args()

    llave = os.environ.get('AZURE_SPEECH_KEY')
    region = os.environ.get('AZURE_SPEECH_REGION', 'eastus')
    if not llave:
        sys.exit('Falta AZURE_SPEECH_KEY en la configuración del entorno (ver README.md).')

    pista = np.zeros(int(DURACION * SR), dtype='float32')
    for inicio, fin, texto in GUION:
        vel = a.velocidad
        while True:
            audio = leer_wav(sintetizar(texto, VOCES[a.voz], vel, llave, region))
            if len(audio) / SR <= fin - inicio or vel >= 25:
                break
            vel += 4  # si no cabe en su escena, un poco más rápido
        i = int(inicio * SR)
        trozo = audio[: len(pista) - i]
        pista[i:i + len(trozo)] += trozo
        print(f'{inicio:5.2f}s  {len(audio) / SR:4.2f}s / {fin - inicio:4.2f}s  vel {vel:+d}%  {texto}')

    voz_wav = os.path.join(AQUI, 'voz48.wav')
    with wave.open(voz_wav, 'wb') as w:
        w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR)
        w.writeframes((np.clip(pista, -1, 1) * 32767).astype('int16').tobytes())

    musica = os.path.join(AQUI, 'musica.wav')
    if not os.path.exists(musica):
        subprocess.run([sys.executable, os.path.join(AQUI, 'music.py'), musica], check=True)

    mezcla = os.path.join(AQUI, 'mezcla.wav')
    subprocess.run([sys.executable, os.path.join(AQUI, 'mix.py'), musica, voz_wav, mezcla], check=True)

    # Volumen para Instagram (-14,5 LUFS) con limitador de picos.
    medida = subprocess.run(['ffmpeg', '-hide_banner', '-i', mezcla, '-af', 'ebur128', '-f', 'null', '-'],
                            capture_output=True, text=True).stderr
    integrado = float(medida.split('Integrated loudness:')[1].split('I:')[1].split('LUFS')[0])
    ganancia = round(-14.5 - integrado, 2)
    salida = os.path.join(AQUI, f'isa-track-promo-voz-{a.voz}.mp4')
    subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', os.path.join(AQUI, 'isa-track-promo.mp4'), '-i', mezcla,
                    '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-ar', '48000',
                    '-af', f'volume={ganancia}dB,alimiter=limit=0.79:attack=3:release=60:level=false',
                    '-shortest', '-movflags', '+faststart', salida], check=True)
    print('Listo:', salida)


if __name__ == '__main__':
    main()
