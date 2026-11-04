# Reel promocional de ISA Track

Video vertical para Instagram (1080×1920, 31 s) con los colores corporativos y capturas reales del panel (datos de demostración).

| Archivo | Qué es |
|---|---|
| `isa-track-promo.mp4` | Video sin audio |
| `isa-track-portada.jpg` | Portada del reel |
| `promo.html` | Animación fuente (8 escenas); `render.js` la convierte en cuadros |
| `music.py` | Música original generada por código (sin derechos de terceros) |
| `mix.py` | Mezcla voz + música bajando la música cuando hay voz |
| `hacer_reel_con_voz.py` | Pide la voz colombiana a Azure y arma el reel final |

## Voz colombiana con IA (Microsoft Azure: Salomé o Gonzalo)

Una sola vez:
1. En portal.azure.com crea un recurso **Speech**, plan **Free F0**, región **East US**. Copia la **Key 1**.
2. En la configuración del entorno de Claude (menú del entorno → Edit):
   - **Network access → Allowed domains:** `eastus.tts.speech.microsoft.com`
   - **Network secrets / variables:** `AZURE_SPEECH_KEY` = tu llave · `AZURE_SPEECH_REGION` = `eastus`
3. Abre una sesión nueva.

Generar el reel (Claude lo hace por ti si le dices "genera el reel con Salomé"):

```bash
python3 marketing/reel-promo/hacer_reel_con_voz.py --voz salome     # o --voz gonzalo
```

Sale `marketing/reel-promo/isa-track-promo-voz-salome.mp4`. Para cambiar el guion, edita `GUION` en el script (cada frase tiene su segundo de inicio y el máximo que cabe en su escena).

## Volver a renderizar el video (si cambias promo.html)

```bash
cd marketing/reel-promo && mkdir -p frames && node render.js "$PWD" \
 && ffmpeg -y -framerate 30 -i frames/f%04d.jpg -c:v libx264 -crf 19 -pix_fmt yuv420p -movflags +faststart isa-track-promo.mp4
```
