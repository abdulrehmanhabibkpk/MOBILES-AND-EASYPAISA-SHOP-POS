import React, { useState, useEffect } from 'react';
import { 
  Plus, Search, ShieldAlert, Edit2, Trash2, Image, 
  Package, Sparkles, X, Check, LayoutGrid, List, Camera,
  FileSpreadsheet, ArrowUpDown, Download, Upload, Smartphone,
  Zap, Headphones, Shield, ShieldCheck, Cable, Battery, Layers,
  Hash, Copy, CheckCircle2, ChevronDown, ClipboardList
} from 'lucide-react';
import { Product, ProductCategory, ProductUnitItem, AppSettings } from '../types';
import { BarcodeScannerModal } from './BarcodeScannerModal';
import { StockExportImportModal } from './StockExportImportModal';
import { UnitDetailsModal } from './UnitDetailsModal';
import { ParsedStockItem } from '../lib/stockDataHandler';
import { useHardwareBarcodeScanner } from '../lib/useHardwareBarcodeScanner';
import { compressImageToDataUrl } from '../lib/imageCompressor';
import { 
  COLOR_PRESETS, 
  RAM_STORAGE_PRESETS, 
  PTA_STATUS_OPTIONS, 
  WARRANTY_OPTIONS, 
  CHARGER_WATTAGE_PRESETS, 
  PROTECTOR_TYPE_PRESETS, 
  COVER_TYPE_PRESETS 
} from '../lib/mobilePresets';

interface InventoryViewProps {
  products: Product[];
  onSaveProduct: (product: Omit<Product, 'id' | 'createdAt'>, id?: string) => void;
  onDeleteProduct: (id: string) => void;
  onImportProducts?: (importedItems: ParsedStockItem[], mode: 'merge' | 'replace') => void;
  settings: AppSettings;
}

// Preset High Quality Image Options for Quick Selection
const PRESET_IMAGES: Record<ProductCategory | 'DEFAULT', { label: string; url: string }[]> = {
  MOBILES: [
    { label: 'Smartphone Modern', url: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80' },
    { label: 'iPhone Style', url: 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?auto=format&fit=crop&w=400&q=80' },
    { label: 'Android Phone', url: 'https://images.unsplash.com/photo-1580910051074-3eb694886505?auto=format&fit=crop&w=400&q=80' },
    { label: 'Keypad Feature Phone', url: 'https://images.unsplash.com/photo-1585060544812-6b45742d762f?auto=format&fit=crop&w=400&q=80' },
  ],
  CHARGERS: [
    { label: 'Fast Charger Adapter', url: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80' },
    { label: 'Type-C Fast Charger', url: 'https://images.unsplash.com/photo-1622445262464-84b1456045b6?auto=format&fit=crop&w=400&q=80' },
  ],
  EARPHONES: [
    { label: 'Airpods / TWS Earbuds', url: 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?auto=format&fit=crop&w=400&q=80' },
    { label: 'Handsfree Earphones', url: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80' },
  ],
  COVERS: [
    { label: 'Silicone Case', url: 'https://images.unsplash.com/photo-1541877944-ac82a091518a?auto=format&fit=crop&w=400&q=80' },
    { label: 'Leather Pouch', url: 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?auto=format&fit=crop&w=400&q=80' },
  ],
  PROTECTORS: [
    { label: 'Glass Protector', url: 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?auto=format&fit=crop&w=400&q=80' },
    { label: 'Matte Screen Guard', url: 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?auto=format&fit=crop&w=400&q=80' },
  ],
  CABLES: [
    { label: 'Type-C USB Cable', url: 'https://images.unsplash.com/photo-1585338107529-13afc5f02586?auto=format&fit=crop&w=400&q=80' },
    { label: 'Fast Braided Cable', url: 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?auto=format&fit=crop&w=400&q=80' },
  ],
  BATTERIES: [
    { label: 'Mobile Battery', url: 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=400&q=80' },
  ],
  ACCESSORIES: [
    { label: 'General Accessory', url: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80' },
  ],
  OTHER: [
    { label: 'Other Item', url: 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?auto=format&fit=crop&w=400&q=80' },
  ],
  DEFAULT: [
    { label: 'Default Item', url: 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=400&q=80' },
  ]
};

export const InventoryView: React.FC<InventoryViewProps> = ({
  products,
  onSaveProduct,
  onDeleteProduct,
  onImportProducts,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list'); // Default to LIST VIEW
  
  // Modal states
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [isExportImportOpen, setIsExportImportOpen] = useState(false);
  const [selectedProductForUnits, setSelectedProductForUnits] = useState<Product | null>(null);

  // Dynamic Form states
  const [name, setName] = useState('');
  const [category, setCategory] = useState<ProductCategory>('MOBILES');
  const [purchasePrice, setPurchasePrice] = useState<number | ''>('');
  const [salePrice, setSalePrice] = useState<number | ''>('');
  const [stock, setStock] = useState<number | ''>(1);
  const [image, setImage] = useState<string>('');
  const [brandOrModel, setBrandOrModel] = useState('');
  const [sku, setSku] = useState('');

  // Category specific states
  const [condition, setCondition] = useState<'NEW' | 'USED'>('NEW');
  const [ramStorage, setRamStorage] = useState('4GB / 64GB');
  const [color, setColor] = useState('Black');
  const [customColor, setCustomColor] = useState('');
  const [ptaStatus, setPtaStatus] = useState<'PTA_APPROVED' | 'NON_PTA' | 'JV' | 'FACTORY_UNLOCK'>('PTA_APPROVED');
  const [warranty, setWarranty] = useState('1 Year Official Brand Warranty');
  const [batteryHealth, setBatteryHealth] = useState('');
  const [wattage, setWattage] = useState('25W Fast Charge');
  const [portType, setPortType] = useState('Type-C');
  const [compatibleModel, setCompatibleModel] = useState('');
  const [protectorType, setProtectorType] = useState('9D / 11D Matte');
  const [cableType, setCableType] = useState('Type-C to Type-C');
  const [batteryCapacity, setBatteryCapacity] = useState('5000 mAh (BL-5C)');

  // Individual Units (Multi-IMEI & Multi-Color tracking)
  const [units, setUnits] = useState<ProductUnitItem[]>([
    { id: 'unit-1', imei1: '', imei2: '', color: 'Black', storageRam: '4GB / 64GB', condition: 'NEW', ptaStatus: 'PTA_APPROVED', status: 'AVAILABLE' }
  ]);

  // Bulk paste IMEI state
  const [bulkImeiText, setBulkImeiText] = useState('');
  const [showBulkImeiInput, setShowBulkImeiInput] = useState(false);
  const [activeUnitColorPickerIndex, setActiveUnitColorPickerIndex] = useState<number | null>(null);

  // Active scanning unit index for camera scanner
  const [scanningUnitIndex, setScanningUnitIndex] = useState<number | null>(null);
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  const isLight = settings.theme === 'light';

  // Listen for automatic scans from physical USB scanner
  useHardwareBarcodeScanner((scannedCode) => {
    if (isModalOpen) {
      if (scanningUnitIndex !== null && units[scanningUnitIndex]) {
        updateUnitField(scanningUnitIndex, 'imei1', scannedCode);
        setScanningUnitIndex(null);
      } else {
        setSku(scannedCode);
      }
    } else {
      setSearchTerm(scannedCode);
    }
  });

  const categories: { key: string; label: string; urdu: string; icon: any }[] = [
    { key: 'ALL', label: 'All Items', urdu: 'تمام سامان', icon: Layers },
    { key: 'MOBILES', label: 'Mobiles', urdu: 'موبائل فون', icon: Smartphone },
    { key: 'CHARGERS', label: 'Chargers', urdu: 'چارجر', icon: Zap },
    { key: 'EARPHONES', label: 'Earphones / Airpods', urdu: 'ہینڈز فری', icon: Headphones },
    { key: 'COVERS', label: 'Covers & Cases', urdu: 'کور', icon: Shield },
    { key: 'PROTECTORS', label: 'Glass Protectors', urdu: 'گلاس پروٹیکٹر', icon: ShieldCheck },
    { key: 'CABLES', label: 'USB Cables', urdu: 'کیبلز', icon: Cable },
    { key: 'BATTERIES', label: 'Batteries', urdu: 'بیٹری', icon: Battery },
    { key: 'OTHER', label: 'Other', urdu: 'دیگر', icon: Package },
  ];

  // Synchronize units array length whenever stock quantity changes in Modal
  const handleStockChange = (newStock: number) => {
    const validStock = Math.max(1, newStock || 1);
    setStock(validStock);

    if (category === 'MOBILES' || units.length > 0) {
      const currentUnits = [...units];
      if (currentUnits.length < validStock) {
        // Add more units
        for (let i = currentUnits.length; i < validStock; i++) {
          currentUnits.push({
            id: `unit-${Date.now()}-${i + 1}`,
            imei1: '',
            imei2: '',
            color: color || 'Black',
            storageRam: ramStorage || '4GB / 64GB',
            condition: condition || 'NEW',
            ptaStatus: ptaStatus || 'PTA_APPROVED',
            status: 'AVAILABLE'
          });
        }
      } else if (currentUnits.length > validStock) {
        // Trim only available units from the end
        currentUnits.splice(validStock);
      }
      setUnits(currentUnits);
    }
  };

  const updateUnitField = (index: number, field: keyof ProductUnitItem, val: any) => {
    const updated = [...units];
    if (updated[index]) {
      updated[index] = { ...updated[index], [field]: val };
      setUnits(updated);
    }
  };

  const handleApplyBulkImeis = () => {
    if (!bulkImeiText.trim()) return;
    const lines = bulkImeiText
      .split(/[\n,;]+/)
      .map(s => s.trim())
      .filter(Boolean);

    if (lines.length === 0) return;

    const count = Math.max(lines.length, Number(stock) || 1);
    setStock(count);

    const newUnits: ProductUnitItem[] = [];
    for (let i = 0; i < count; i++) {
      const existing = units[i];
      newUnits.push({
        id: existing?.id || `unit-${Date.now()}-${i + 1}`,
        imei1: lines[i] || existing?.imei1 || '',
        imei2: existing?.imei2 || '',
        color: existing?.color || color || 'Black',
        storageRam: existing?.storageRam || ramStorage || '4GB / 64GB',
        condition: existing?.condition || condition || 'NEW',
        ptaStatus: existing?.ptaStatus || ptaStatus || 'PTA_APPROVED',
        status: existing?.status || 'AVAILABLE'
      });
    }

    setUnits(newUnits);
    setShowBulkImeiInput(false);
    setBulkImeiText('');
  };

  // Auto-generate a descriptive item title based on category parameters
  const handleAutoGenerateTitle = () => {
    const effectiveColor = customColor.trim() || color;
    if (category === 'MOBILES') {
      const brandPart = brandOrModel ? brandOrModel.trim() : 'Mobile Phone';
      const condPart = condition === 'NEW' ? 'Pin Pack' : 'Used';
      const ptaPart = ptaStatus === 'PTA_APPROVED' ? 'PTA Approved' : ptaStatus === 'NON_PTA' ? 'Non-PTA' : ptaStatus === 'JV' ? 'JV' : 'Factory Unlock';
      const colorPart = effectiveColor ? `(${effectiveColor})` : '';
      setName(`${brandPart} (${ramStorage}) ${colorPart} - ${condPart} [${ptaPart}]`.replace(/\s+/g, ' ').trim());
    } else if (category === 'CHARGERS') {
      const brandPart = brandOrModel ? brandOrModel.trim() : 'Fast Charger';
      setName(`${brandPart} ${wattage} (${portType})`);
    } else if (category === 'EARPHONES') {
      const brandPart = brandOrModel ? brandOrModel.trim() : 'Earbuds';
      setName(`${brandPart} ${effectiveColor ? `(${effectiveColor})` : ''} - ${portType}`.trim());
    } else if (category === 'COVERS') {
      const modelPart = compatibleModel ? compatibleModel.trim() : (brandOrModel ? brandOrModel.trim() : 'Phone');
      setName(`${modelPart} Protective Case ${effectiveColor ? `(${effectiveColor})` : ''}`.trim());
    } else if (category === 'PROTECTORS') {
      const modelPart = compatibleModel ? compatibleModel.trim() : (brandOrModel ? brandOrModel.trim() : 'Phone');
      setName(`${modelPart} ${protectorType} Glass`);
    } else if (category === 'CABLES') {
      const brandPart = brandOrModel ? brandOrModel.trim() : 'Fast USB Cable';
      setName(`${brandPart} ${cableType} ${effectiveColor ? `(${effectiveColor})` : ''}`.trim());
    } else if (category === 'BATTERIES') {
      const brandPart = brandOrModel ? brandOrModel.trim() : 'Mobile Battery';
      setName(`${brandPart} (${batteryCapacity})`);
    }
  };

  const handleOpenAddModal = (initialCategory: ProductCategory = 'MOBILES') => {
    setEditingId(null);
    setCategory(initialCategory);
    setName('');
    setPurchasePrice('');
    setSalePrice('');
    setStock(1);
    setImage(PRESET_IMAGES[initialCategory]?.[0]?.url || PRESET_IMAGES.MOBILES[0].url);
    setBrandOrModel('');
    setSku('');
    setCondition('NEW');
    setRamStorage('4GB / 64GB');
    setColor('Black');
    setCustomColor('');
    setPtaStatus('PTA_APPROVED');
    setWarranty('1 Year Official Brand Warranty');
    setBatteryHealth('');
    setWattage('25W Fast Charge');
    setPortType('Type-C');
    setCompatibleModel('');
    setProtectorType('9D / 11D Matte Gaming Glass');
    setCableType('Type-C to Type-C');
    setBatteryCapacity('5000 mAh (BL-5C)');
    setUnits([
      { id: `unit-${Date.now()}-1`, imei1: '', imei2: '', color: 'Black', storageRam: '4GB / 64GB', condition: 'NEW', ptaStatus: 'PTA_APPROVED', status: 'AVAILABLE' }
    ]);
    setShowBulkImeiInput(false);
    setBulkImeiText('');
    setActiveUnitColorPickerIndex(null);
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (p: Product) => {
    setEditingId(p.id);
    setName(p.name);
    setCategory(p.category);
    setPurchasePrice(p.purchasePrice);
    setSalePrice(p.salePrice);
    setStock(p.stock);
    setImage(p.image || PRESET_IMAGES[p.category]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url);
    setBrandOrModel(p.brandOrModel || '');
    setSku(p.sku || '');
    setCondition(p.condition || 'NEW');
    setRamStorage(p.ramStorage || '4GB / 64GB');
    setColor(p.color || 'Black');
    setCustomColor('');
    setPtaStatus(p.ptaStatus || 'PTA_APPROVED');
    setWarranty(p.warranty || '1 Year Official Brand Warranty');
    setBatteryHealth(p.batteryHealth || '');
    setWattage(p.wattage || '25W Fast Charge');
    setPortType(p.portType || 'Type-C');
    setCompatibleModel(p.compatibleModel || '');
    setProtectorType(p.protectorType || '9D / 11D Matte Gaming Glass');
    setCableType(p.cableType || 'Type-C to Type-C');
    setBatteryCapacity(p.batteryCapacity || '5000 mAh');

    if (p.units && p.units.length > 0) {
      setUnits(p.units);
    } else if (p.imeiOrSerial) {
      setUnits([
        {
          id: `unit-${p.id}-1`,
          imei1: p.imeiOrSerial,
          color: p.color || 'Black',
          storageRam: p.ramStorage || '4GB / 64GB',
          condition: p.condition || 'NEW',
          ptaStatus: p.ptaStatus || 'PTA_APPROVED',
          status: p.stock > 0 ? 'AVAILABLE' : 'SOLD'
        }
      ]);
    } else {
      const generated: ProductUnitItem[] = [];
      const stkCount = Math.max(1, p.stock || 1);
      for (let i = 0; i < stkCount; i++) {
        generated.push({
          id: `unit-${p.id}-${i + 1}`,
          imei1: '',
          color: p.color || 'Black',
          storageRam: p.ramStorage,
          condition: p.condition || 'NEW',
          ptaStatus: p.ptaStatus || 'PTA_APPROVED',
          status: 'AVAILABLE'
        });
      }
      setUnits(generated);
    }

    setShowBulkImeiInput(false);
    setActiveUnitColorPickerIndex(null);
    setIsModalOpen(true);
  };

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      try {
        const compressed = await compressImageToDataUrl(file, {
          maxWidth: 720,
          maxHeight: 720,
          quality: 0.70,
          maxSizeBytes: 60 * 1024
        });
        setImage(compressed);
      } catch (err) {
        console.error("Compression error:", err);
      }
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      alert('Tafseel / Item name daakhil karein');
      return;
    }

    const pur = Number(purchasePrice) || 0;
    const sal = Number(salePrice) || 0;
    const stk = Number(stock) || 1;

    // Primary IMEI for backward compatibility
    const primaryImei = units[0]?.imei1 || '';
    const effectiveColor = customColor.trim() || color;

    onSaveProduct(
      {
        name: name.trim(),
        category,
        purchasePrice: pur,
        salePrice: sal,
        stock: stk,
        image: image || PRESET_IMAGES[category]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url,
        brandOrModel: brandOrModel.trim(),
        imeiOrSerial: primaryImei,
        sku: sku.trim(),
        units: category === 'MOBILES' || units.some(u => u.imei1 || u.color) ? units : undefined,
        condition,
        ramStorage,
        color: effectiveColor,
        ptaStatus,
        warranty,
        batteryHealth,
        wattage,
        portType,
        compatibleModel,
        protectorType,
        cableType,
        batteryCapacity,
      },
      editingId || undefined
    );

    setIsModalOpen(false);
  };

  // Filter products
  const filteredProducts = products.filter((p) => {
    const term = searchTerm.toLowerCase();
    const matchesSearch =
      p.name.toLowerCase().includes(term) ||
      (p.brandOrModel && p.brandOrModel.toLowerCase().includes(term)) ||
      (p.imeiOrSerial && p.imeiOrSerial.toLowerCase().includes(term)) ||
      (p.sku && p.sku.toLowerCase().includes(term)) ||
      (p.compatibleModel && p.compatibleModel.toLowerCase().includes(term)) ||
      (p.units && p.units.some(u => 
        (u.imei1 && u.imei1.toLowerCase().includes(term)) ||
        (u.imei2 && u.imei2.toLowerCase().includes(term)) ||
        (u.color && u.color.toLowerCase().includes(term))
      ));

    const matchesCategory = selectedCategory === 'ALL' || p.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const lowStockCount = products.filter((p) => p.stock <= 3).length;

  return (
    <div className="space-y-5">
      {/* Top Banner Header */}
      <div className={`p-5 rounded-2xl border shadow-sm ${
        isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
      }`}>
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-600/20">
              <Package className="w-6 h-6" />
            </div>
            <div>
              <h1 className={`text-lg sm:text-xl font-extrabold ${isLight ? 'text-slate-900' : 'text-white'}`}>
                موبائل اور انوینٹری کنٹرول (Stock Inventory & Multi-IMEI)
              </h1>
              <p className={`text-xs ${isLight ? 'text-slate-500' : 'text-slate-400'}`}>
                {settings.shopName} - Mobile Phones, Accessories & Individual Unit Tracking
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2 flex-wrap">
            {/* View Mode Switcher (List vs Grid) */}
            <div className={`flex items-center p-1 rounded-xl border ${isLight ? 'bg-slate-100 border-slate-200' : 'bg-slate-800 border-slate-700'}`}>
              <button
                onClick={() => setViewMode('list')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'list'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="List View (فہرست)"
              >
                <List className="w-4 h-4" />
                <span>List View</span>
              </button>
              <button
                onClick={() => setViewMode('grid')}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer ${
                  viewMode === 'grid'
                    ? 'bg-blue-700 text-white shadow-sm'
                    : 'text-slate-500 hover:text-slate-800'
                }`}
                title="Grid View"
              >
                <LayoutGrid className="w-4 h-4" />
                <span>Grid</span>
              </button>
            </div>

            {lowStockCount > 0 && (
              <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/30 text-xs font-semibold">
                <ShieldAlert className="w-4 h-4 shrink-0" />
                <span>{lowStockCount} Low Stock</span>
              </div>
            )}

            {/* Export & Import Button */}
            <button
              onClick={() => setIsExportImportOpen(true)}
              className={`py-2.5 px-3.5 rounded-xl border text-xs font-bold flex items-center gap-2 transition-all cursor-pointer ${
                isLight
                  ? 'bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700'
                  : 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-200'
              }`}
              title="Excel, CSV, ya .db file se stock export aur import karein"
            >
              <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
              <span>Export / Import</span>
            </button>

            <button
              onClick={() => handleOpenAddModal('MOBILES')}
              className="py-2.5 px-4 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold shadow-md shadow-blue-700/20 flex items-center gap-2 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>Naya Stock Entry (+ Item)</span>
            </button>
          </div>
        </div>

        {/* Search & Categories Bar */}
        <div className="mt-4 pt-4 border-t border-slate-200/60 flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="Saman search karein (Nokia, Vivo, IMEI 1/2, Color, Charger, Model)..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                isLight ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-slate-100'
              }`}
            />
          </div>

          {/* Category Chips Scroll */}
          <div className="flex gap-1.5 overflow-x-auto pb-1 no-scrollbar">
            {categories.map((cat) => {
              const count = cat.key === 'ALL' 
                ? products.length 
                : products.filter(p => p.category === cat.key).length;
              const Icon = cat.icon;

              return (
                <button
                  key={cat.key}
                  onClick={() => setSelectedCategory(cat.key)}
                  className={`px-3 py-1.5 rounded-xl text-xs font-semibold shrink-0 transition-colors flex items-center gap-1.5 cursor-pointer ${
                    selectedCategory === cat.key
                      ? 'bg-blue-700 text-white shadow-sm'
                      : isLight
                      ? 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                      : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                  }`}
                >
                  <Icon className="w-3.5 h-3.5" />
                  <span>{cat.label}</span>
                  <span className={`text-[10px] px-1.5 py-0.2 rounded-full font-bold ${
                    selectedCategory === cat.key 
                      ? 'bg-blue-900 text-blue-100' 
                      : isLight 
                      ? 'bg-slate-200 text-slate-600' 
                      : 'bg-slate-700 text-slate-300'
                  }`}>
                    {count}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </div>

      {/* PRODUCTS DISPLAY SECTION */}
      {filteredProducts.length === 0 ? (
        <div className={`p-10 text-center rounded-2xl border ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
          <Package className="w-12 h-12 mx-auto text-slate-400 mb-2 opacity-50" />
          <h3 className={`text-base font-bold ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>
            {products.length === 0 ? 'Abhi koi saman stock mein nahi hai' : 'Koi item nahi mila'}
          </h3>
          <p className="text-xs text-slate-400 mt-1 max-w-md mx-auto">
            {products.length === 0 
              ? 'Naya phone ya accessory add karne ke liye upar "+ Naya Stock Entry" button dabayein.' 
              : 'Aap ke search ya category filter ke mutabiq koi product nahi mila.'}
          </p>
          {products.length === 0 && (
            <button
              onClick={() => handleOpenAddModal('MOBILES')}
              className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold transition-all shadow-md cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>Naya Phone ya Saman Shamil Karein</span>
            </button>
          )}
        </div>
      ) : viewMode === 'list' ? (
        /* PRODUCT LIST TABLE VIEW (DEFAULT LIST VIEW) */
        <div className={`rounded-2xl border overflow-hidden shadow-lg ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'}`}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-950 text-slate-400'} uppercase font-extrabold text-[11px] border-b ${isLight ? 'border-slate-200' : 'border-slate-800'}`}>
                <tr>
                  <th className="py-3 px-4">Saman (Item)</th>
                  <th className="py-3 px-4">Category</th>
                  <th className="py-3 px-4">Brand / IMEI / Units</th>
                  <th className="py-3 px-4 text-center">Stock (تعداد)</th>
                  <th className="py-3 px-4 text-right">Khareed (Cost)</th>
                  <th className="py-3 px-4 text-right">Farokht (Sale)</th>
                  <th className="py-3 px-4 text-right">Expected Profit</th>
                  <th className="py-3 px-4 text-center">Action</th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isLight ? 'divide-slate-200' : 'divide-slate-800'} font-medium`}>
                {filteredProducts.map((prod) => {
                  const profitMargin = prod.salePrice - prod.purchasePrice;
                  const isLowStock = prod.stock <= 3;
                  const totalUnitsCount = prod.units?.length || (prod.imeiOrSerial ? 1 : 0);
                  const availableUnitsCount = prod.units?.filter(u => u.status === 'AVAILABLE').length ?? prod.stock;

                  return (
                    <tr key={prod.id} className={`${isLight ? 'hover:bg-blue-50/40' : 'hover:bg-slate-800/50'} transition-colors`}>
                      {/* Product Name & Photo */}
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          <img
                            src={prod.image || PRESET_IMAGES[prod.category]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url}
                            alt={prod.name}
                            className="w-10 h-10 rounded-xl object-cover shrink-0 border border-slate-200"
                            referrerPolicy="no-referrer"
                          />
                          <div>
                            <p className={`font-extrabold text-xs ${isLight ? 'text-slate-900' : 'text-white'}`}>
                              {prod.name}
                            </p>
                            <div className="flex items-center gap-1.5 text-[10px] text-slate-400 mt-0.5">
                              {prod.condition && (
                                <span className={prod.condition === 'NEW' ? 'text-emerald-600 font-bold' : 'text-amber-600 font-bold'}>
                                  {prod.condition === 'NEW' ? 'Pin Pack' : 'Used'}
                                </span>
                              )}
                              {prod.color && <span>• {prod.color}</span>}
                              {prod.ramStorage && <span>• {prod.ramStorage}</span>}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Category */}
                      <td className="py-3 px-4">
                        <span className="px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 font-bold text-[10px]">
                          {prod.category}
                        </span>
                      </td>

                      {/* Brand & Multi-IMEI Units */}
                      <td className="py-3 px-4">
                        <div className="text-xs space-y-1">
                          {prod.brandOrModel && (
                            <p className="font-semibold text-slate-700 dark:text-slate-300">{prod.brandOrModel}</p>
                          )}
                          
                          {/* If has units / IMEIs */}
                          {totalUnitsCount > 0 ? (
                            <button
                              onClick={() => setSelectedProductForUnits(prod)}
                              className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-[11px] font-bold transition-all cursor-pointer"
                              title="Click to view all IMEI numbers and colors"
                            >
                              <Smartphone className="w-3 h-3 text-emerald-600" />
                              <span>{totalUnitsCount} IMEIs / Units</span>
                              <span className="text-[9px] bg-emerald-600 text-white px-1 rounded-full">
                                {availableUnitsCount} Avail
                              </span>
                            </button>
                          ) : prod.imeiOrSerial ? (
                            <p className="font-mono text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded inline-block">
                              IMEI: {prod.imeiOrSerial}
                            </p>
                          ) : (
                            <span className="text-slate-400 text-[11px]">-</span>
                          )}
                        </div>
                      </td>

                      {/* Stock Level */}
                      <td className="py-3 px-4 text-center">
                        <span className={`px-2.5 py-1 rounded-full text-xs font-bold ${
                          isLowStock
                            ? 'bg-rose-100 text-rose-800 border border-rose-300 animate-pulse'
                            : 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                        }`}>
                          {prod.stock} Pcs
                        </span>
                      </td>

                      {/* Purchase Price */}
                      <td className="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-300">
                        Rs. {prod.purchasePrice.toLocaleString()}
                      </td>

                      {/* Sale Price */}
                      <td className="py-3 px-4 text-right font-mono font-bold text-blue-700 dark:text-blue-400">
                        Rs. {prod.salePrice.toLocaleString()}
                      </td>

                      {/* Profit */}
                      <td className="py-3 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        + Rs. {profitMargin.toLocaleString()}
                      </td>

                      {/* Action */}
                      <td className="py-3 px-4 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          {totalUnitsCount > 0 && (
                            <button
                              onClick={() => setSelectedProductForUnits(prod)}
                              className="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 transition-colors cursor-pointer"
                              title="View IMEIs & Colors"
                            >
                              <Smartphone className="w-3.5 h-3.5" />
                            </button>
                          )}
                          <button
                            onClick={() => handleOpenEditModal(prod)}
                            className="p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 transition-colors cursor-pointer"
                            title="Edit Item"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={() => {
                              if (confirm(`Kya aap ${prod.name} ko delete karna chahte hain?`)) {
                                onDeleteProduct(prod.id);
                              }
                            }}
                            className="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 transition-colors cursor-pointer"
                            title="Delete Item"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>

                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        /* GRID VIEW OPTION */
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredProducts.map((prod) => {
            const profitMargin = prod.salePrice - prod.purchasePrice;
            const isLowStock = prod.stock <= 3;
            const totalUnitsCount = prod.units?.length || (prod.imeiOrSerial ? 1 : 0);

            return (
              <div
                key={prod.id}
                className={`rounded-2xl border transition-all duration-200 hover:shadow-lg flex flex-col overflow-hidden ${
                  isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'
                }`}
              >
                {/* Product Image & Stock Badge */}
                <div className="relative h-44 bg-slate-100 overflow-hidden group">
                  <img
                    src={prod.image || PRESET_IMAGES[prod.category]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url}
                    alt={prod.name}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    referrerPolicy="no-referrer"
                  />
                  <div className="absolute top-2 left-2">
                    <span className={`px-2.5 py-1 rounded-full text-[11px] font-bold shadow-md ${
                      isLowStock
                        ? 'bg-rose-500 text-white animate-pulse'
                        : 'bg-emerald-600 text-white'
                    }`}>
                      Stock: {prod.stock} Pcs
                    </span>
                  </div>

                  <div className="absolute top-2 right-2">
                    <span className="px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-md text-white text-[10px] font-medium border border-white/20">
                      {prod.category}
                    </span>
                  </div>
                </div>

                {/* Content */}
                <div className="p-4 flex-1 flex flex-col justify-between space-y-3">
                  <div>
                    <h3 className={`font-bold text-sm leading-snug ${isLight ? 'text-slate-900' : 'text-white'}`}>
                      {prod.name}
                    </h3>
                    <div className="flex items-center gap-1.5 text-[11px] text-slate-500 mt-1 flex-wrap">
                      {prod.brandOrModel && <span>Model: {prod.brandOrModel}</span>}
                      {prod.condition && <span>• {prod.condition === 'NEW' ? 'New' : 'Used'}</span>}
                      {prod.color && <span>• {prod.color}</span>}
                    </div>

                    {totalUnitsCount > 0 && (
                      <button
                        onClick={() => setSelectedProductForUnits(prod)}
                        className="mt-2 w-full py-1 px-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                      >
                        <Smartphone className="w-3.5 h-3.5" />
                        <span>View {totalUnitsCount} IMEIs & Colors</span>
                      </button>
                    )}
                  </div>

                  {/* Price Breakdown */}
                  <div className={`p-2.5 rounded-xl border space-y-1.5 text-xs ${
                    isLight ? 'bg-slate-50 border-slate-100' : 'bg-slate-800/60 border-slate-800'
                  }`}>
                    <div className="flex justify-between text-slate-500">
                      <span>Khareed (Purchase):</span>
                      <span className="font-semibold text-slate-700 dark:text-slate-300">Rs. {prod.purchasePrice.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between font-bold text-slate-900 dark:text-white">
                      <span>Farokht (Sale):</span>
                      <span className="text-blue-700 dark:text-blue-400 text-sm">Rs. {prod.salePrice.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between text-[11px] text-emerald-600 dark:text-emerald-400 pt-1 border-t border-slate-200/60">
                      <span>Manafa (Profit):</span>
                      <span className="font-bold">+ Rs. {profitMargin.toLocaleString()}</span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 pt-1 border-t border-slate-100">
                    <button
                      onClick={() => handleOpenEditModal(prod)}
                      className={`flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold border flex items-center justify-center gap-1 transition-colors ${
                        isLight
                          ? 'border-slate-200 text-slate-700 hover:bg-slate-100'
                          : 'border-slate-700 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                      <span>Edit</span>
                    </button>
                    <button
                      onClick={() => {
                        if (confirm(`Kya aap ${prod.name} ko delete karna chahte hain?`)) {
                          onDeleteProduct(prod.id);
                        }
                      }}
                      className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition-colors"
                      title="Delete Product"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>

                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* DYNAMIC CATEGORY ADD / EDIT MODAL */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-2 sm:p-4 overflow-y-auto">
          <div className={`w-full max-w-2xl rounded-2xl shadow-2xl border overflow-hidden my-auto max-h-[94vh] flex flex-col ${
            isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-900 border-slate-700 text-slate-100'
          }`}>
            
            {/* Header */}
            <div className="bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-800 px-5 py-4 text-white flex items-center justify-between shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center">
                  <Package className="w-5 h-5 text-blue-200" />
                </div>
                <div>
                  <h3 className="font-black text-base sm:text-lg">
                    {editingId ? 'پروڈکٹ تبدیل کریں (Edit Product)' : 'نیا اسٹاک انٹری (New Stock Entry)'}
                  </h3>
                  <p className="text-xs text-blue-200 font-medium">
                    کیٹیگری منتخب کریں، فارم خود بخود اسی کے مطابق سیٹ ہو جائے گا
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsModalOpen(false)}
                className="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1">
              
              {/* Step 1: Category Selector */}
              <div className="space-y-1.5">
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300">
                  1. کیٹیگری منتخب کریں (Select Category): *
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  {categories.filter(c => c.key !== 'ALL').map((cat) => {
                    const Icon = cat.icon;
                    const isSelected = category === cat.key;
                    return (
                      <button
                        key={cat.key}
                        type="button"
                        onClick={() => {
                          setCategory(cat.key as ProductCategory);
                          setImage(PRESET_IMAGES[cat.key as ProductCategory]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url);
                        }}
                        className={`p-2.5 rounded-xl border text-xs font-bold flex items-center gap-2 transition-all cursor-pointer ${
                          isSelected
                            ? 'bg-blue-600 text-white border-blue-700 shadow-md ring-2 ring-blue-500/30 scale-[1.01]'
                            : isLight
                            ? 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'
                            : 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300'
                        }`}
                      >
                        <Icon className="w-4 h-4 shrink-0" />
                        <span className="truncate">{cat.urdu}</span>
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Step 2: Category Specific Detailed Fields */}
              <div className={`p-3.5 sm:p-4 rounded-xl border space-y-3.5 transition-all ${
                isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'
              }`}>
                <div className="flex items-center justify-between border-b pb-2 border-slate-200 dark:border-slate-700">
                  <h4 className="font-black text-xs text-blue-700 dark:text-blue-300 flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5" />
                    <span>
                      {category === 'MOBILES' && 'موبائل فون کی مخصوص خصوصیات (Phone Details & Specs)'}
                      {category === 'CHARGERS' && 'چارجر کی تفصیلات (Charger Specifications)'}
                      {category === 'EARPHONES' && 'ائیر پوڈز / ہینڈز فری کی تفصیلات (Earphones Specs)'}
                      {category === 'COVERS' && 'کور / کیس کی تفصیلات (Cover Specifications)'}
                      {category === 'PROTECTORS' && 'گلاس پروٹیکٹر کی تفصیلات (Protector Specs)'}
                      {category === 'CABLES' && 'کیبل کی تفصیلات (Cable Specs)'}
                      {category === 'BATTERIES' && 'بیٹری کی تفصیلات (Battery Specs)'}
                      {(category === 'ACCESSORIES' || category === 'OTHER') && 'سامان کی تفصیلات (General Details)'}
                    </span>
                  </h4>

                  <button
                    type="button"
                    onClick={handleAutoGenerateTitle}
                    className="text-[11px] font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 cursor-pointer bg-white dark:bg-slate-900 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-slate-700 shadow-sm"
                    title="Generate product title automatically from specs"
                  >
                    <Sparkles className="w-3 h-3 text-blue-600" />
                    <span>⚡ Auto Title</span>
                  </button>
                </div>

                {/* MOBILES SPECIFIC FIELDS */}
                {category === 'MOBILES' && (
                  <div className="space-y-3">
                    {/* Row 1: Brand & Condition */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Brand / Company (برانڈ یا کمپنی):
                        </label>
                        <input
                          type="text"
                          placeholder="e.g. Vivo, Samsung, iPhone, Infinix, Redmi, Oppo"
                          value={brandOrModel}
                          onChange={(e) => setBrandOrModel(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          حالت (Condition):
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                          <button
                            type="button"
                            onClick={() => setCondition('NEW')}
                            className={`p-2 rounded-xl text-xs font-bold border transition-all cursor-pointer flex items-center justify-center gap-1 ${
                              condition === 'NEW'
                                ? 'bg-emerald-600 text-white border-emerald-700 shadow-sm'
                                : isLight ? 'bg-white text-slate-700 border-slate-200' : 'bg-slate-900 text-slate-300 border-slate-700'
                            }`}
                          >
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            <span>Pin Pack (نیا)</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => setCondition('USED')}
                            className={`p-2 rounded-xl text-xs font-bold border transition-all cursor-pointer flex items-center justify-center gap-1 ${
                              condition === 'USED'
                                ? 'bg-amber-600 text-white border-amber-700 shadow-sm'
                                : isLight ? 'bg-white text-slate-700 border-slate-200' : 'bg-slate-900 text-slate-300 border-slate-700'
                            }`}
                          >
                            <span>Used (پرانا 10/10)</span>
                          </button>
                        </div>
                      </div>
                    </div>

                    {/* Row 2: PTA Status */}
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        پی ٹی اے اسٹیٹس (PTA Status):
                      </label>
                      <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                        {PTA_STATUS_OPTIONS.map((opt) => (
                          <button
                            key={opt.key}
                            type="button"
                            onClick={() => setPtaStatus(opt.key as any)}
                            className={`p-2 rounded-lg border text-xs font-bold transition-all cursor-pointer text-center ${
                              ptaStatus === opt.key
                                ? 'bg-blue-600 text-white border-blue-700 shadow-sm ring-1 ring-blue-500'
                                : isLight
                                ? 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                : 'bg-slate-900 text-slate-300 border-slate-700 hover:bg-slate-800'
                            }`}
                          >
                            <div>{opt.label}</div>
                            <div className="text-[10px] opacity-75 font-normal">{opt.urdu}</div>
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Row 3: RAM & Storage Quick Presets */}
                    <div>
                      <div className="flex items-center justify-between mb-1">
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                          RAM اور میموری (RAM / Storage):
                        </label>
                        <span className="text-[10px] text-blue-600 dark:text-blue-400 font-bold">منتخب: {ramStorage}</span>
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {RAM_STORAGE_PRESETS.map((preset) => (
                          <button
                            key={preset}
                            type="button"
                            onClick={() => setRamStorage(preset)}
                            className={`px-2.5 py-1 rounded-lg text-xs font-bold border transition-all cursor-pointer ${
                              ramStorage === preset
                                ? 'bg-blue-600 text-white border-blue-700 shadow-sm'
                                : isLight
                                ? 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                                : 'bg-slate-900 text-slate-300 border-slate-700 hover:bg-slate-800'
                            }`}
                          >
                            {preset}
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Row 4: Warranty & Battery Health */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          وارنٹی (Warranty):
                        </label>
                        <select
                          value={warranty}
                          onChange={(e) => setWarranty(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200 text-slate-800' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        >
                          {WARRANTY_OPTIONS.map((w) => (
                            <option key={w} value={w}>{w}</option>
                          ))}
                        </select>
                      </div>

                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          بیٹری ہیلتھ (Battery Health % / Cycle):
                        </label>
                        <input
                          type="text"
                          placeholder="e.g. 100%, 94%, 88% (خصوصاً Used / iPhones کیلئے)"
                          value={batteryHealth}
                          onChange={(e) => setBatteryHealth(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        />
                      </div>
                    </div>

                  </div>
                )}

                {/* CHARGERS SPECIFIC FIELDS */}
                {category === 'CHARGERS' && (
                  <div className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Brand / Company:
                        </label>
                        <input
                          type="text"
                          placeholder="e.g. Samsung, Xiaomi, Ronin, Anker, Faster"
                          value={brandOrModel}
                          onChange={(e) => setBrandOrModel(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Port Type:
                        </label>
                        <select
                          value={portType}
                          onChange={(e) => setPortType(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        >
                          <option value="Type-C PD">Type-C Super Fast PD</option>
                          <option value="USB-A Fast">USB-A Fast Port</option>
                          <option value="Dual Port (C + A)">Dual Port (Type-C + USB-A)</option>
                          <option value="iPhone Lightning">iPhone Lightning</option>
                          <option value="Micro USB V8">Micro USB V8</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Wattage / Power Presets:
                      </label>
                      <div className="flex flex-wrap gap-1.5">
                        {CHARGER_WATTAGE_PRESETS.map((w) => (
                          <button
                            key={w}
                            type="button"
                            onClick={() => setWattage(w)}
                            className={`px-2.5 py-1 rounded-lg text-xs font-bold border cursor-pointer ${
                              wattage === w ? 'bg-blue-600 text-white border-blue-700' : isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700'
                            }`}
                          >
                            {w}
                          </button>
                        ))}
                      </div>
                    </div>
                  </div>
                )}

                {/* EARPHONES SPECIFIC FIELDS */}
                {category === 'EARPHONES' && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Brand / Model:
                      </label>
                      <input
                        type="text"
                        placeholder="e.g. Airpods Pro 2, Audionic Airbud, Lenovo LP40"
                        value={brandOrModel}
                        onChange={(e) => setBrandOrModel(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Type:
                      </label>
                      <select
                        value={portType}
                        onChange={(e) => setPortType(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      >
                        <option value="TWS Wireless Airpods">Wireless TWS / Airpods</option>
                        <option value="Neckband Bluetooth">Neckband Bluetooth</option>
                        <option value="3.5mm Jack Handsfree">3.5mm Jack Handsfree</option>
                        <option value="Type-C Handsfree">Type-C Handsfree</option>
                      </select>
                    </div>
                  </div>
                )}

                {/* COVERS SPECIFIC FIELDS */}
                {category === 'COVERS' && (
                  <div className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Compatible Phone Model (کس فون کا کور ہے):
                        </label>
                        <input
                          type="text"
                          placeholder="e.g. Vivo Y21, Samsung A14, iPhone 15 Pro Max"
                          value={compatibleModel}
                          onChange={(e) => setCompatibleModel(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Material / Case Type:
                        </label>
                        <select
                          value={portType}
                          onChange={(e) => setPortType(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        >
                          {COVER_TYPE_PRESETS.map((c) => (
                            <option key={c} value={c}>{c}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>
                )}

                {/* PROTECTORS SPECIFIC FIELDS */}
                {category === 'PROTECTORS' && (
                  <div className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Compatible Phone Model (کس فون کا گلاس ہے):
                        </label>
                        <input
                          type="text"
                          placeholder="e.g. Infinix Hot 30, Oppo A58, Redmi Note 13"
                          value={compatibleModel}
                          onChange={(e) => setCompatibleModel(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                          Protector Type:
                        </label>
                        <select
                          value={protectorType}
                          onChange={(e) => setProtectorType(e.target.value)}
                          className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                            isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                          }`}
                        >
                          {PROTECTOR_TYPE_PRESETS.map((p) => (
                            <option key={p} value={p}>{p}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>
                )}

                {/* CABLES SPECIFIC FIELDS */}
                {category === 'CABLES' && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Brand / Company:
                      </label>
                      <input
                        type="text"
                        placeholder="e.g. Ronin, Faster, Remax, Anker"
                        value={brandOrModel}
                        onChange={(e) => setBrandOrModel(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Cable Interface:
                      </label>
                      <select
                        value={cableType}
                        onChange={(e) => setCableType(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border font-medium ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      >
                        <option value="Type-C to Type-C">Type-C to Type-C (65W/100W PD)</option>
                        <option value="USB to Type-C">USB-A to Type-C (6A Fast)</option>
                        <option value="Type-C to Lightning">Type-C to Lightning (iPhone 20W)</option>
                        <option value="USB to Lightning">USB-A to Lightning</option>
                        <option value="Micro USB V8">Micro USB V8</option>
                      </select>
                    </div>
                  </div>
                )}

                {/* BATTERIES SPECIFIC FIELDS */}
                {category === 'BATTERIES' && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Brand / Mobile Model:
                      </label>
                      <input
                        type="text"
                        placeholder="e.g. Nokia, Samsung, Oppo, Vivo"
                        value={brandOrModel}
                        onChange={(e) => setBrandOrModel(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Battery Model / Capacity:
                      </label>
                      <input
                        type="text"
                        placeholder="e.g. BL-5C (1020mAh), BN-59 (5000mAh)"
                        value={batteryCapacity}
                        onChange={(e) => setBatteryCapacity(e.target.value)}
                        className={`w-full p-2.5 rounded-xl text-xs border ${
                          isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700 text-white'
                        }`}
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* Step 3: Color Palette & Visual Color Swatches */}
              <div className={`p-3.5 rounded-xl border space-y-2.5 ${
                isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-700'
              }`}>
                <div className="flex items-center justify-between">
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    رنگ کا انتخاب (Color & Variant):
                  </label>
                  <span className="text-xs font-extrabold text-blue-600 dark:text-blue-400">
                    {customColor.trim() || color || 'Black'}
                  </span>
                </div>

                {/* Visual Circle Swatches */}
                <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                  {COLOR_PRESETS.map((c) => {
                    const isSelected = !customColor.trim() && color.toLowerCase() === c.name.toLowerCase();
                    return (
                      <button
                        key={c.name}
                        type="button"
                        onClick={() => {
                          setColor(c.name);
                          setCustomColor('');
                        }}
                        className={`p-1.5 rounded-xl border text-[11px] font-bold flex items-center gap-2 transition-all cursor-pointer ${
                          isSelected
                            ? 'bg-blue-50 dark:bg-blue-950/60 border-blue-600 text-blue-800 dark:text-blue-300 ring-2 ring-blue-500/40 shadow-sm'
                            : isLight
                            ? 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'
                            : 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300'
                        }`}
                      >
                        <span
                          className="w-4 h-4 rounded-full shrink-0 shadow-inner flex items-center justify-center border"
                          style={{
                            backgroundColor: c.hex,
                            borderColor: c.border || (c.hex === '#f8fafc' ? '#cbd5e1' : 'rgba(0,0,0,0.15)')
                          }}
                        >
                          {isSelected && (
                            <Check className={`w-2.5 h-2.5 ${c.hex === '#f8fafc' || c.hex === '#38bdf8' ? 'text-slate-900' : 'text-white'}`} />
                          )}
                        </span>
                        <span className="truncate">{c.name}</span>
                      </button>
                    );
                  })}
                </div>

                {/* Custom Color Field */}
                <div className="flex items-center gap-2 pt-1">
                  <span className="text-[11px] text-slate-500 font-medium shrink-0">یا کوئی اور خاص رنگ:</span>
                  <input
                    type="text"
                    placeholder="e.g. Aura Glow, Titanium Gray, Alpine Green"
                    value={customColor}
                    onChange={(e) => setCustomColor(e.target.value)}
                    className={`flex-1 p-1.5 rounded-lg text-xs border ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>
              </div>

              {/* Step 4: Item Name & Stock Quantity */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                    آئٹم کا نام / تفصیل (Item Name): *
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. Vivo Y21 (4/64) Midnight Blue - Pin Pack [PTA Approved]"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className={`w-full p-2.5 rounded-xl text-xs border focus:ring-2 focus:ring-blue-500 font-bold ${
                      isLight ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                    تعداد / اسٹاک (Stock Qty): *
                  </label>
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => handleStockChange(Math.max(1, (Number(stock) || 1) - 1))}
                      className="w-8 h-9 rounded-lg bg-slate-200 dark:bg-slate-700 font-black text-slate-700 dark:text-slate-200 flex items-center justify-center hover:bg-slate-300 cursor-pointer"
                    >
                      -
                    </button>
                    <input
                      type="number"
                      required
                      min="1"
                      placeholder="1"
                      value={stock}
                      onChange={(e) => handleStockChange(Number(e.target.value))}
                      className={`flex-1 p-2 text-center rounded-xl text-xs border font-black text-blue-700 dark:text-blue-400 focus:ring-2 focus:ring-blue-500 ${
                        isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                      }`}
                    />
                    <button
                      type="button"
                      onClick={() => handleStockChange((Number(stock) || 1) + 1)}
                      className="w-8 h-9 rounded-lg bg-blue-600 font-black text-white flex items-center justify-center hover:bg-blue-700 cursor-pointer"
                    >
                      +
                    </button>
                  </div>
                </div>
              </div>

              {/* Step 5: Pricing & Calculations Banner */}
              <div className="p-3 bg-blue-50/70 dark:bg-slate-800/80 rounded-xl border border-blue-100 dark:border-slate-700 space-y-2.5">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      خرید قیمت (Purchase Cost): Rs. *
                    </label>
                    <input
                      type="number"
                      required
                      min="0"
                      placeholder="e.g. 24000"
                      value={purchasePrice}
                      onChange={(e) => setPurchasePrice(e.target.value === '' ? '' : Number(e.target.value))}
                      className="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-800 dark:text-white"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                      فروخت قیمت (Sale Price): Rs. *
                    </label>
                    <input
                      type="number"
                      required
                      min="0"
                      placeholder="e.g. 27500"
                      value={salePrice}
                      onChange={(e) => setSalePrice(e.target.value === '' ? '' : Number(e.target.value))}
                      className="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold text-blue-700 dark:text-blue-400"
                    />
                  </div>
                </div>

                {/* Instant Financial Metrics */}
                {Number(salePrice) > 0 && Number(purchasePrice) > 0 && (
                  <div className="pt-2 border-t border-blue-200 dark:border-slate-700 grid grid-cols-3 gap-2 text-center text-xs">
                    <div className="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                      <div className="text-[10px] font-semibold">منافع فی آئٹم</div>
                      <div className="font-black">+ Rs. {(Number(salePrice) - Number(purchasePrice)).toLocaleString()}</div>
                    </div>
                    <div className="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300">
                      <div className="text-[10px] font-semibold">کل لاگت (Investment)</div>
                      <div className="font-black">Rs. {(Number(purchasePrice) * (Number(stock) || 1)).toLocaleString()}</div>
                    </div>
                    <div className="p-1.5 rounded-lg bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300">
                      <div className="text-[10px] font-semibold">متوقع کل فروخت</div>
                      <div className="font-black">Rs. {(Number(salePrice) * (Number(stock) || 1)).toLocaleString()}</div>
                    </div>
                  </div>
                )}
              </div>

              {/* Step 6: INDIVIDUAL PHONE UNITS & MULTI-IMEI / MULTI-COLOR ENTRY */}
              {(category === 'MOBILES' || units.length > 0) && (
                <div className={`p-3.5 sm:p-4 rounded-xl border space-y-3 ${
                  isLight ? 'bg-emerald-50/50 border-emerald-200' : 'bg-emerald-950/20 border-emerald-900/50'
                }`}>
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-2 border-emerald-200 dark:border-emerald-900/60">
                    <div className="flex items-center gap-2">
                      <Smartphone className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                      <div>
                        <h4 className="font-black text-xs text-emerald-900 dark:text-emerald-200">
                          انفرادی فونز کے IMEIs اور ہر فون کا الگ رنگ:
                        </h4>
                        <p className="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">
                          ہر فون کا الگ IMEI اور الگ رنگ (Color) باآسانی سیٹ کریں
                        </p>
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => setShowBulkImeiInput(!showBulkImeiInput)}
                      className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold flex items-center gap-1 cursor-pointer self-start sm:self-auto"
                    >
                      <ClipboardList className="w-3.5 h-3.5" />
                      <span>{showBulkImeiInput ? 'باکس بند کریں' : '⚡ Fast Bulk Paste IMEIs'}</span>
                    </button>
                  </div>

                  {/* Bulk Paste Box */}
                  {showBulkImeiInput && (
                    <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-emerald-300 space-y-2">
                      <p className="text-[11px] text-slate-600 dark:text-slate-400 font-semibold">
                        تمام فونز کے IMEIs ایک ساتھ یہاں پیسٹ کریں (ہر لائن پر ایک IMEI):
                      </p>
                      <textarea
                        rows={3}
                        placeholder="35918290182901&#10;35918290182902&#10;35918290182903"
                        value={bulkImeiText}
                        onChange={(e) => setBulkImeiText(e.target.value)}
                        className="w-full p-2 text-xs font-mono border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:bg-slate-800 font-bold"
                      />
                      <button
                        type="button"
                        onClick={handleApplyBulkImeis}
                        className="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold cursor-pointer hover:bg-emerald-700"
                      >
                        Apply to Units List
                      </button>
                    </div>
                  )}

                  {/* Individual Units Row List */}
                  <div className="space-y-2.5 max-h-64 overflow-y-auto pr-1">
                    {units.map((unit, index) => {
                      const currentColorPreset = COLOR_PRESETS.find(
                        cp => cp.name.toLowerCase() === (unit.color || '').toLowerCase()
                      );

                      return (
                        <div
                          key={unit.id || index}
                          className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-2"
                        >
                          <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 text-[11px] font-black">
                              Phone #{index + 1}
                            </span>

                            {/* Unit Individual Color Selector */}
                            <div className="flex items-center gap-1.5">
                              <span className="text-[10px] font-bold text-slate-500">رنگ:</span>
                              
                              {/* Quick Color Swatch Buttons */}
                              <div className="flex items-center gap-1">
                                {COLOR_PRESETS.slice(0, 6).map((cp) => (
                                  <button
                                    key={cp.name}
                                    type="button"
                                    onClick={() => updateUnitField(index, 'color', cp.name)}
                                    title={cp.name}
                                    className={`w-5 h-5 rounded-full border transition-all cursor-pointer flex items-center justify-center ${
                                      (unit.color || '').toLowerCase() === cp.name.toLowerCase()
                                        ? 'ring-2 ring-blue-600 scale-110'
                                        : 'opacity-70 hover:opacity-100'
                                    }`}
                                    style={{
                                      backgroundColor: cp.hex,
                                      borderColor: cp.border || 'rgba(0,0,0,0.2)'
                                    }}
                                  >
                                    {(unit.color || '').toLowerCase() === cp.name.toLowerCase() && (
                                      <Check className={`w-2.5 h-2.5 ${cp.hex === '#f8fafc' ? 'text-slate-900' : 'text-white'}`} />
                                    )}
                                  </button>
                                ))}
                              </div>

                              <input
                                type="text"
                                placeholder="Color"
                                value={unit.color || ''}
                                onChange={(e) => updateUnitField(index, 'color', e.target.value)}
                                className="w-20 p-1 text-[11px] rounded-lg border border-slate-200 dark:border-slate-700 font-bold text-slate-800 dark:text-slate-100 text-center"
                              />
                            </div>
                          </div>

                          {/* IMEI inputs */}
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div className="flex gap-1">
                              <input
                                type="text"
                                placeholder="IMEI 1 (15 digits) *"
                                value={unit.imei1 || ''}
                                onChange={(e) => updateUnitField(index, 'imei1', e.target.value)}
                                className="flex-1 p-2 rounded-lg text-xs font-mono font-bold border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500"
                              />
                              <button
                                type="button"
                                onClick={() => {
                                  setScanningUnitIndex(index);
                                  setIsScannerOpen(true);
                                }}
                                className="px-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs flex items-center justify-center cursor-pointer"
                                title="Scan IMEI 1 with camera"
                              >
                                <Camera className="w-3.5 h-3.5" />
                              </button>
                            </div>

                            <div>
                              <input
                                type="text"
                                placeholder="IMEI 2 (Optional)"
                                value={unit.imei2 || ''}
                                onChange={(e) => updateUnitField(index, 'imei2', e.target.value)}
                                className="w-full p-2 rounded-lg text-xs font-mono border border-slate-200 dark:border-slate-700"
                              />
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>

                </div>
              )}

              {/* Step 7: SKU & Barcode Field */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  ماسٹر بارکوڈ / SKU (Optional):
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    placeholder="e.g. 880609012345 (Scan or type barcode)"
                    value={sku}
                    onChange={(e) => setSku(e.target.value)}
                    className={`flex-1 p-2.5 rounded-xl text-xs border font-mono font-bold ${
                      isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800 border-slate-700 text-white'
                    }`}
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setScanningUnitIndex(null);
                      setIsScannerOpen(true);
                    }}
                    className="px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-all shadow-sm cursor-pointer shrink-0"
                    title="Scan Barcode via Camera"
                  >
                    <Camera className="w-3.5 h-3.5" />
                    <span>Scan</span>
                  </button>
                </div>
              </div>

              {/* Step 8: Photo Selector */}
              <div>
                <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  پروڈکٹ کی تصویر (Photo):
                </label>
                
                {/* Image Preview & Upload */}
                <div className="flex items-center gap-3 mb-2">
                  <div className="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 overflow-hidden shrink-0 shadow-inner">
                    <img src={image || PRESET_IMAGES[category]?.[0]?.url || PRESET_IMAGES.DEFAULT[0].url} alt="Preview" className="w-full h-full object-cover" />
                  </div>
                  <div className="text-xs space-y-1">
                    <p className="font-semibold text-slate-700 dark:text-slate-300">آسان پری سیٹ یا گیلری سے اپلوڈ کریں:</p>
                    <label className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 hover:bg-blue-100 border border-blue-200 font-bold text-xs">
                      <Image className="w-3.5 h-3.5" />
                      <span>Upload Custom Photo</span>
                      <input type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />
                    </label>
                  </div>
                </div>

                {/* Preset Thumbnails */}
                <div className="grid grid-cols-4 gap-2">
                  {(PRESET_IMAGES[category] || PRESET_IMAGES.DEFAULT).map((preset, idx) => (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => setImage(preset.url)}
                      className={`relative h-12 rounded-xl border overflow-hidden transition-all cursor-pointer ${
                        image === preset.url ? 'ring-2 ring-blue-600 border-transparent scale-95 shadow-md' : 'opacity-70 hover:opacity-100'
                      }`}
                    >
                      <img src={preset.url} alt={preset.label} className="w-full h-full object-cover" />
                      {image === preset.url && (
                        <div className="absolute inset-0 bg-blue-600/30 flex items-center justify-center text-white">
                          <Check className="w-4 h-4 font-black" />
                        </div>
                      )}
                    </button>
                  ))}
                </div>
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                className="w-full py-3.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-black text-sm shadow-lg shadow-blue-700/25 transition-all flex items-center justify-center gap-2 mt-3 cursor-pointer"
              >
                <CheckCircle2 className="w-5 h-5" />
                <span>{editingId ? '💾 تبدیلیاں محفوظ کریں (Save Changes)' : '💾 نیا اسٹاک محفوظ کریں (Save Stock Entry)'}</span>
              </button>

            </form>
          </div>
        </div>
      )}

      {/* Unit Details Inspector Modal */}
      <UnitDetailsModal
        isOpen={Boolean(selectedProductForUnits)}
        onClose={() => setSelectedProductForUnits(null)}
        product={selectedProductForUnits}
        settings={settings}
      />

      {/* Barcode / Camera Scanner Modal */}
      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={(code) => {
          if (scanningUnitIndex !== null && units[scanningUnitIndex]) {
            updateUnitField(scanningUnitIndex, 'imei1', code);
            setScanningUnitIndex(null);
          } else {
            setSku(code);
          }
          setIsScannerOpen(false);
        }}
      />

      {/* Export / Import Modal */}
      <StockExportImportModal
        isOpen={isExportImportOpen}
        onClose={() => setIsExportImportOpen(false)}
        products={products}
        onImportProducts={(items, mode) => {
          if (onImportProducts) {
            onImportProducts(items, mode);
          }
        }}
        settings={settings}
      />

    </div>
  );
};
