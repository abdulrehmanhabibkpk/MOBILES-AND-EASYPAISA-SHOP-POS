/**
 * Image Optimizer & Compression Utility
 * 
 * Compresses images before saving to reduce document size (down to ~30-50KB per photo),
 * preventing Firestore 1MB document quota overflow and conserving Firestore bandwidth/quota
 * (so 50,000 Reads & 20,000 Writes quotas last months/years without exhausting).
 */

export interface CompressionOptions {
  maxWidth?: number;
  maxHeight?: number;
  quality?: number; // 0.1 to 1.0 (default 0.72 - perfect balance for crystal clear CNIC text & low KB)
  maxSizeBytes?: number; // Target max size (e.g. 70KB)
}

export async function compressImageToDataUrl(
  fileOrDataUrl: File | string,
  options: CompressionOptions = {}
): Promise<string> {
  const {
    maxWidth = 720,
    maxHeight = 720,
    quality = 0.72,
    maxSizeBytes = 65 * 1024 // ~65KB
  } = options;

  return new Promise((resolve, reject) => {
    const processImage = (imgSrc: string) => {
      const img = new Image();
      img.onload = () => {
        let width = img.width;
        let height = img.height;

        // Maintain aspect ratio
        if (width > height) {
          if (width > maxWidth) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
          }
        } else {
          if (height > maxHeight) {
            width = Math.round((width * maxHeight) / height);
            height = maxHeight;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');

        if (!ctx) {
          resolve(imgSrc);
          return;
        }

        // Use high quality image smoothing
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, width, height);

        let currentQuality = quality;
        let result = canvas.toDataURL('image/jpeg', currentQuality);

        // If still larger than maxSizeBytes, iteratively lower quality slightly
        if (result.length > maxSizeBytes * 1.37 && currentQuality > 0.4) {
          currentQuality = 0.55;
          result = canvas.toDataURL('image/jpeg', currentQuality);
        }

        resolve(result);
      };

      img.onerror = () => resolve(imgSrc);
      img.src = imgSrc;
    };

    if (typeof fileOrDataUrl === 'string') {
      processImage(fileOrDataUrl);
    } else {
      const reader = new FileReader();
      reader.onload = (e) => {
        if (e.target?.result) {
          processImage(e.target.result as string);
        } else {
          resolve('');
        }
      };
      reader.onerror = (err) => reject(err);
      reader.readAsDataURL(fileOrDataUrl);
    }
  });
}
