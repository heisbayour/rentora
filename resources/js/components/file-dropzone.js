const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.8;

/** Downscales an image to MAX_EDGE on its longest side and re-encodes it as JPEG. */
async function compressImage(file) {
    if (!file.type.startsWith('image/')) {
        return file;
    }
    const bitmap = await createImageBitmap(file).catch(() => null);
    if (!bitmap) {
        return file;
    }
    const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext('2d');
    context.fillStyle = '#FFFFFF';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));
    if (!blob || blob.size >= file.size) {
        return file;
    }
    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

export default function fileDropzone({ max = 1, tooManyMessage = '' } = {}) {
    return {
        files: [],
        dragging: false,
        busy: false,
        error: '',

        get input() {
            return this.$refs.input;
        },

        async add(fileList) {
            this.error = '';
            const incoming = Array.from(fileList);
            if (this.files.length + incoming.length > max) {
                this.error = tooManyMessage;
                incoming.splice(Math.max(0, max - this.files.length));
            }
            this.busy = true;
            for (const original of incoming) {
                const file = await compressImage(original);
                this.files.push({
                    file,
                    name: file.name,
                    size: file.size,
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                });
            }
            this.busy = false;
            this.sync();
        },

        remove(index) {
            const [removed] = this.files.splice(index, 1);
            if (removed?.preview) {
                URL.revokeObjectURL(removed.preview);
            }
            this.sync();
        },

        onDrop(event) {
            this.dragging = false;
            this.add(event.dataTransfer.files);
        },

        onPick(event) {
            this.add(event.target.files);
        },

        /** Writes the (compressed) files back into the real input so the form submits them. */
        sync() {
            const transfer = new DataTransfer();
            this.files.forEach((entry) => transfer.items.add(entry.file));
            this.input.files = transfer.files;
        },

        humanSize(bytes) {
            return bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.ceil(bytes / 1024)} KB`;
        },
    };
}
