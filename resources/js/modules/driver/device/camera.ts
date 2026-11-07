/**
 * Fotos de la entrega. El mensajero toma la foto con la cámara del celular
 * (<input type="file" capture>), que funciona igual en la PWA y dentro de Capacitor.
 * Antes de subirla se reduce de tamaño: una foto de celular pesa 4–8 MB y con datos
 * móviles tardaría; a 1600 px en JPEG queda en ~300 KB y se ve perfecta.
 */
const MAX_SIDE = 1600;

export async function shrinkPhoto(file: File): Promise<File> {
    if (!file.type.startsWith('image/') || typeof createImageBitmap !== 'function') return file;
    try {
        const bmp = await createImageBitmap(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
        if (scale === 1 && file.size < 1_500_000) return file;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bmp.width * scale);
        canvas.height = Math.round(bmp.height * scale);
        canvas.getContext('2d')?.drawImage(bmp, 0, 0, canvas.width, canvas.height);
        bmp.close();
        const blob = await new Promise<Blob | null>((r) => canvas.toBlob(r, 'image/jpeg', 0.82));
        return blob ? new File([blob], 'entrega.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}
