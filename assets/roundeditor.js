import { registerStickerExtension } from './roundeditor/sticker.js';

registerStickerExtension(new URL(import.meta.url).search);
