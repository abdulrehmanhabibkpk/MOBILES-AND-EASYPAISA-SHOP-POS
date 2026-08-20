import React, { useEffect, useRef, useState } from 'react';
import { Html5Qrcode } from 'html5-qrcode';
import { X, Camera, RefreshCw, AlertTriangle } from 'lucide-react';

interface BarcodeScannerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onScanSuccess: (decodedText: string) => void;
}

export const BarcodeScannerModal: React.FC<BarcodeScannerModalProps> = ({
  isOpen,
  onClose,
  onScanSuccess,
}) => {
  const [error, setError] = useState<string | null>(null);
  const [cameras, setCameras] = useState<any[]>([]);
  const [activeCameraId, setActiveCameraId] = useState<string | null>(null);
  const [scanning, setScanning] = useState(false);
  const qrCodeRef = useRef<Html5Qrcode | null>(null);
  const scannerId = "reader-element-id";

  useEffect(() => {
    if (!isOpen) return;

    setError(null);
    setCameras([]);
    setActiveCameraId(null);
    setScanning(false);

    const timer = setTimeout(() => {
      Html5Qrcode.getCameras()
        .then((devices) => {
          if (devices && devices.length > 0) {
            setCameras(devices);
            // Default to back/environment camera if available, otherwise first camera
            const backCam = devices.find(d => 
              d.label.toLowerCase().includes('back') || 
              d.label.toLowerCase().includes('environment') || 
              d.label.toLowerCase().includes('rear')
            );
            const selectedCam = backCam || devices[0];
            setActiveCameraId(selectedCam.id);
            startScanner(selectedCam.id);
          } else {
            setError("No camera devices found. Ensure camera is connected.");
          }
        })
        .catch((err) => {
          setError("Camera permission denied or camera is currently busy. Please allow camera access in browser settings.");
          console.error("getCameras failed", err);
        });
    }, 450);

    return () => {
      clearTimeout(timer);
      stopScanner();
    };
  }, [isOpen]);

  const startScanner = (deviceId: string) => {
    setError(null);
    stopScanner();

    // Small delay to ensure the container element is rendered in DOM
    setTimeout(() => {
      try {
        const container = document.getElementById(scannerId);
        if (!container) return;

        const html5QrCode = new Html5Qrcode(scannerId);
        qrCodeRef.current = html5QrCode;

        html5QrCode.start(
          deviceId,
          {
            fps: 15,
            qrbox: (width, height) => {
              // Custom scanning region tailored for 1D barcodes (wider, shorter)
              const minSize = Math.min(width, height);
              const boxWidth = Math.floor(minSize * 0.85);
              const boxHeight = Math.floor(boxWidth * 0.55); // Rectangle shape perfect for barcode
              return { width: boxWidth, height: boxHeight };
            },
            aspectRatio: 1.0,
          },
          (decodedText) => {
            onScanSuccess(decodedText);
            stopScanner();
            onClose();
          },
          (errorMessage) => {
            // Quiet verbose scanner mismatch logs
          }
        ).then(() => {
          setScanning(true);
        }).catch(err => {
          setError(`Camera start failed: ${err instanceof Error ? err.message : String(err)}`);
          setScanning(false);
        });
      } catch (err) {
        setError(`Initialization error: ${err instanceof Error ? err.message : String(err)}`);
      }
    }, 100);
  };

  const stopScanner = () => {
    if (qrCodeRef.current && qrCodeRef.current.isScanning) {
      qrCodeRef.current.stop()
        .then(() => {
          qrCodeRef.current = null;
          setScanning(false);
        })
        .catch(err => console.error("Failed to stop scanner", err));
    }
  };

  const handleCameraChange = (deviceId: string) => {
    setActiveCameraId(deviceId);
    startScanner(deviceId);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/90 backdrop-blur-md p-4">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        
        {/* Header */}
        <div className="p-4 border-b border-slate-800 flex items-center justify-between text-white">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
              <Camera className="w-4.5 h-4.5" />
            </div>
            <div>
              <h3 className="font-extrabold text-sm sm:text-base text-white">Live Barcode Scanner</h3>
              <p className="text-[10px] text-slate-400">Position the SKU/Barcode inside the framing guide</p>
            </div>
          </div>
          <button 
            type="button"
            onClick={() => {
              stopScanner();
              onClose();
            }} 
            className="p-1.5 rounded-full bg-slate-800 text-slate-400 hover:text-white transition-colors cursor-pointer"
          >
            <X className="w-4.5 h-4.5" />
          </button>
        </div>

        {/* Video stream container */}
        <div className="relative flex-grow bg-slate-950 flex items-center justify-center min-h-[280px] p-4">
          <div 
            id={scannerId} 
            className="w-full aspect-square max-w-[300px] rounded-2xl overflow-hidden shadow-inner border border-slate-800/80" 
          />
          
          {/* Overlay Framing Box */}
          {scanning && !error && (
            <div className="absolute inset-0 pointer-events-none flex items-center justify-center p-4">
              <div className="w-[260px] h-[160px] border-2 border-dashed border-blue-500 rounded-2xl relative shadow-[0_0_0_9999px_rgba(15,23,42,0.45)]">
                {/* L-shaped corner indicators */}
                <div className="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-blue-500 -mt-1 -ml-1 rounded-tl-lg" />
                <div className="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-blue-500 -mt-1 -mr-1 rounded-tr-lg" />
                <div className="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-blue-500 -mb-1 -ml-1 rounded-bl-lg" />
                <div className="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-blue-500 -mb-1 -mr-1 rounded-br-lg" />
                {/* Scanning line animation */}
                <div className="absolute top-1/2 left-0 right-0 h-0.5 bg-red-500 shadow-lg shadow-red-500/50 animate-bounce" />
              </div>
            </div>
          )}

          {error && (
            <div className="absolute inset-0 bg-slate-950/95 flex flex-col items-center justify-center p-6 text-center text-rose-400">
              <AlertTriangle className="w-12 h-12 mb-3 bg-rose-500/10 rounded-2xl p-2 text-rose-500" />
              <p className="text-xs font-black max-w-[280px] leading-relaxed">{error}</p>
              <button 
                type="button" 
                onClick={() => activeCameraId && startScanner(activeCameraId)}
                className="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
              >
                Retry Scanner
              </button>
            </div>
          )}
        </div>

        {/* Footer with Camera Picker */}
        <div className="p-4 border-t border-slate-800 bg-slate-900 flex flex-col gap-2">
          {cameras.length > 1 && (
            <div className="flex items-center justify-between gap-2 text-xs text-slate-300 bg-slate-950 p-2 rounded-xl">
              <span className="font-bold flex items-center gap-1.5 text-slate-400">
                <RefreshCw className="w-3.5 h-3.5 text-blue-400" /> Choose Camera:
              </span>
              <select
                value={activeCameraId || ''}
                onChange={(e) => handleCameraChange(e.target.value)}
                className="bg-slate-800 border border-slate-700 text-white rounded-lg px-2.5 py-1 text-xs focus:ring-1 focus:ring-blue-500 max-w-[180px] font-medium"
              >
                {cameras.map((device, idx) => (
                  <option key={device.id} value={device.id}>
                    {device.label || `Camera ${idx + 1}`}
                  </option>
                ))}
              </select>
            </div>
          )}
          <p className="text-[10px] text-slate-400 text-center leading-normal">
            Compatible with Standard 1D Retail Barcodes & QR Codes. Hold scanner steady.
          </p>
        </div>

      </div>
    </div>
  );
};
