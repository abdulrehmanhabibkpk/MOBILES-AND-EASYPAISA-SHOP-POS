import React, { useState } from 'react';
import { MobilePurchaseRecord, AppSettings } from '../types';
import { 
  Smartphone, 
  Search, 
  Plus, 
  User, 
  Printer, 
  Trash2, 
  Eye, 
  Check, 
  X, 
  ShieldCheck,
  Camera
} from 'lucide-react';
import { SimplePurchaseReceiptModal } from './SimplePurchaseReceiptModal';
import { BarcodeScannerModal } from './BarcodeScannerModal';

interface MobilePurchaseViewProps {
  purchases: MobilePurchaseRecord[];
  onAddPurchase: (record: MobilePurchaseRecord, autoAddToStock: boolean) => void;
  onDeletePurchase: (id: string) => void;
  settings: AppSettings;
}

export const MobilePurchaseView: React.FC<MobilePurchaseViewProps> = ({
  purchases,
  onAddPurchase,
  onDeletePurchase,
  settings,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [filterCondition, setFilterCondition] = useState<'ALL' | 'NEW' | 'USED'>('ALL');
  
  // Modals state
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [selectedReceiptRecord, setSelectedReceiptRecord] = useState<MobilePurchaseRecord | null>(null);
  const [selectedDetailRecord, setSelectedDetailRecord] = useState<MobilePurchaseRecord | null>(null);

  // Form State
  const [sellerName, setSellerName] = useState('');
  const [sellerCnic, setSellerCnic] = useState('');
  const [sellerPhone, setSellerPhone] = useState('');
  const [sellerAddress, setSellerAddress] = useState('');
  const [sellerPhoto, setSellerPhoto] = useState<string | undefined>(undefined);
  const [cnicFrontPhoto, setCnicFrontPhoto] = useState<string | undefined>(undefined);
  const [cnicBackPhoto, setCnicBackPhoto] = useState<string | undefined>(undefined);

  const [mobileBrandModel, setMobileBrandModel] = useState('');
  const [condition, setCondition] = useState<'NEW' | 'USED'>('USED');
  const [imei1, setImei1] = useState('');
  const [imei2, setImei2] = useState('');
  const [color, setColor] = useState('');
  const [ramStorage, setRamStorage] = useState('');
  const [mobilePhoto, setMobilePhoto] = useState<string | undefined>(undefined);
  const [sku, setSku] = useState('');
  const [isScannerOpen, setIsScannerOpen] = useState(false);

  const [hasBox, setHasBox] = useState(true);
  const [hasCharger, setHasCharger] = useState(true);
  const [hasCable, setHasCable] = useState(true);
  const [hasHandsfree, setHasHandsfree] = useState(false);
  const [hasWarrantyCard, setHasWarrantyCard] = useState(false);

  const [purchasePrice, setPurchasePrice] = useState<number | ''>('');
  const [paymentMethod, setPaymentMethod] = useState<'CASH' | 'EASYPAISA' | 'JAZZCASH' | 'BANK'>('CASH');
  const [notes, setNotes] = useState('');
  const [autoAddToStock, setAutoAddToStock] = useState(true);

  // Helper to handle image uploads
  const handleImageUpload = (
    e: React.ChangeEvent<HTMLInputElement>,
    setter: (val: string) => void
  ) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => {
        setter(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  // Reset form
  const resetForm = () => {
    setSellerName('');
    setSellerCnic('');
    setSellerPhone('');
    setSellerAddress('');
    setSellerPhoto(undefined);
    setCnicFrontPhoto(undefined);
    setCnicBackPhoto(undefined);

    setMobileBrandModel('');
    setCondition('USED');
    setImei1('');
    setImei2('');
    setColor('');
    setRamStorage('');
    setMobilePhoto(undefined);
    setSku('');

    setHasBox(true);
    setHasCharger(true);
    setHasCable(true);
    setHasHandsfree(false);
    setHasWarrantyCard(false);

    setPurchasePrice('');
    setPaymentMethod('CASH');
    setNotes('');
    setAutoAddToStock(true);
  };

  // Submit Handler
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!sellerName.trim() || !mobileBrandModel.trim() || !imei1.trim() || !purchasePrice) {
      alert('Please fill in all required fields (Seller Name, Mobile Model, IMEI 1, and Purchase Price).');
      return;
    }

    const now = new Date();
    const dateStr = now.toISOString().split('T')[0];
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const receiptNo = `PUR-${1000 + purchases.length + 1}`;

    const newRecord: MobilePurchaseRecord = {
      id: `pur-${Date.now()}`,
      receiptNo,
      date: dateStr,
      time: timeStr,
      sellerName: sellerName.trim(),
      sellerCnic: sellerCnic.trim(),
      sellerPhone: sellerPhone.trim(),
      sellerAddress: sellerAddress.trim(),
      sellerPhoto,
      cnicFrontPhoto,
      cnicBackPhoto,

      mobileBrandModel: mobileBrandModel.trim(),
      condition,
      imei1: imei1.trim(),
      imei2: imei2.trim(),
      color: color.trim(),
      ramStorage: ramStorage.trim(),
      mobilePhoto,
      sku: sku.trim(),

      hasBox,
      hasCharger,
      hasCable,
      hasHandsfree,
      hasWarrantyCard,

      purchasePrice: Number(purchasePrice),
      paymentMethod,
      notes: notes.trim(),
      createdAt: Date.now(),
    };

    onAddPurchase(newRecord, autoAddToStock);
    resetForm();
    setIsAddModalOpen(false);
    setSelectedReceiptRecord(newRecord);
  };

  // Search Filter logic across ALL fields
  const filteredPurchases = purchases.filter((p) => {
    const query = searchTerm.toLowerCase().trim();
    const matchesSearch =
      !query ||
      p.sellerName.toLowerCase().includes(query) ||
      p.sellerCnic.toLowerCase().includes(query) ||
      p.sellerPhone.toLowerCase().includes(query) ||
      p.mobileBrandModel.toLowerCase().includes(query) ||
      p.imei1.toLowerCase().includes(query) ||
      (p.imei2 && p.imei2.toLowerCase().includes(query)) ||
      p.receiptNo.toLowerCase().includes(query) ||
      (p.color && p.color.toLowerCase().includes(query)) ||
      (p.sku && p.sku.toLowerCase().includes(query));

    const matchesCondition =
      filterCondition === 'ALL' || p.condition === filterCondition;

    return matchesSearch && matchesCondition;
  });

  // Calculate totals
  const totalPurchasesCount = purchases.length;
  const totalSpent = purchases.reduce((sum, p) => sum + p.purchasePrice, 0);
  const usedCount = purchases.filter((p) => p.condition === 'USED').length;
  const newCount = purchases.filter((p) => p.condition === 'NEW').length;

  return (
    <div className="space-y-5 font-sans">
      
      {/* Top Banner & Stats */}
      <div className="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white shadow-lg shadow-emerald-600/15 border border-emerald-500">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          
          <div className="flex items-center gap-3.5">
            <div className="w-12 h-12 rounded-2xl bg-white/20 border border-white/30 text-white flex items-center justify-center shrink-0">
              <Smartphone className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base sm:text-xl font-black tracking-tight text-white">
                  Mobile Buy & Purchase Records
                </h2>
                <span className="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-bold uppercase tracking-wider hidden sm:inline-flex items-center gap-1 border border-white/30">
                  <ShieldCheck className="w-3 h-3" />
                  Legal Verification
                </span>
              </div>
              <p className="text-xs text-emerald-100 mt-0.5">
                Record new & used mobile purchases, CNIC details, photos, IMEI records, and print simple receipts
              </p>
            </div>
          </div>

          <button
            onClick={() => {
              resetForm();
              setIsAddModalOpen(true);
            }}
            className="py-2.5 px-4 rounded-xl bg-white text-emerald-800 hover:bg-emerald-50 font-black text-xs sm:text-sm flex items-center justify-center gap-2 shadow-md transition-all cursor-pointer shrink-0"
          >
            <Plus className="w-4 h-4 text-emerald-700" />
            <span>Add Mobile Purchase</span>
          </button>
        </div>

        {/* Quick Stats Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-white/20">
          <div className="bg-white/10 rounded-xl p-2.5 border border-white/15">
            <p className="text-[11px] text-emerald-100 font-medium">Total Mobiles Bought</p>
            <p className="text-lg font-black">{totalPurchasesCount} Units</p>
          </div>
          <div className="bg-white/10 rounded-xl p-2.5 border border-white/15">
            <p className="text-[11px] text-emerald-100 font-medium">Total Investment</p>
            <p className="text-lg font-black font-mono">Rs {totalSpent.toLocaleString()}</p>
          </div>
          <div className="bg-white/10 rounded-xl p-2.5 border border-white/15">
            <p className="text-[11px] text-emerald-100 font-medium">Used Mobiles</p>
            <p className="text-lg font-black text-amber-200">{usedCount} Units</p>
          </div>
          <div className="bg-white/10 rounded-xl p-2.5 border border-white/15">
            <p className="text-[11px] text-emerald-100 font-medium">New Pin-Pack</p>
            <p className="text-lg font-black text-emerald-200">{newCount} Units</p>
          </div>
        </div>
      </div>

      {/* SEARCH BAR & FILTERS */}
      <div className="p-4 bg-white rounded-2xl border border-slate-200 shadow-sm space-y-3">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
          
          {/* Universal Search Input */}
          <div className="relative w-full sm:w-96">
            <Search className="w-4 h-4 absolute left-3.5 top-3 text-emerald-600" />
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search by Seller Name, CNIC, Phone, IMEI, Model..."
              className="w-full pl-10 pr-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-900 placeholder-slate-400 text-xs sm:text-sm font-medium focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600"
            />
            {searchTerm && (
              <button
                onClick={() => setSearchTerm('')}
                className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600"
              >
                <X className="w-4 h-4" />
              </button>
            )}
          </div>

          {/* Condition Filters */}
          <div className="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
            <button
              onClick={() => setFilterCondition('ALL')}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 ${
                filterCondition === 'ALL'
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              All ({purchases.length})
            </button>
            <button
              onClick={() => setFilterCondition('USED')}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 ${
                filterCondition === 'USED'
                  ? 'bg-amber-600 text-white shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              Used / Second-Hand ({usedCount})
            </button>
            <button
              onClick={() => setFilterCondition('NEW')}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 ${
                filterCondition === 'NEW'
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              New / Pin Pack ({newCount})
            </button>
          </div>

        </div>
      </div>

      {/* PURCHASES LIST CARDS */}
      {filteredPurchases.length === 0 ? (
        <div className="p-12 text-center bg-white rounded-2xl border border-slate-200 space-y-3">
          <Smartphone className="w-12 h-12 text-slate-300 mx-auto" />
          <h3 className="text-base font-bold text-slate-700">No Mobile Records Found</h3>
          <p className="text-xs text-slate-500 max-w-sm mx-auto">
            {searchTerm
              ? `No purchase matching "${searchTerm}" was found.`
              : 'No mobile purchase has been recorded yet. Click the button above to add a new purchase.'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filteredPurchases.map((p) => (
            <div
              key={p.id}
              className="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition-all space-y-3 flex flex-col justify-between"
            >
              {/* Header: Receipt & Condition */}
              <div className="flex items-start justify-between border-b border-slate-100 pb-2.5">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center font-bold text-xs shrink-0">
                    <Smartphone className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="font-extrabold text-slate-900 text-sm leading-tight">
                      {p.mobileBrandModel}
                    </h3>
                    <p className="text-[11px] text-slate-500 font-mono">
                      Receipt #: {p.receiptNo} | {p.date} ({p.time})
                    </p>
                  </div>
                </div>

                <span
                  className={`px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase ${
                    p.condition === 'NEW'
                      ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                      : 'bg-amber-100 text-amber-800 border border-amber-200'
                  }`}
                >
                  {p.condition === 'NEW' ? 'NEW' : 'USED'}
                </span>
              </div>

              {/* Seller & Specs Grid */}
              <div className="grid grid-cols-2 gap-2 text-xs bg-slate-50/70 p-2.5 rounded-xl border border-slate-100">
                <div>
                  <span className="text-slate-400 text-[10px] block font-semibold">Seller Name:</span>
                  <p className="font-extrabold text-slate-800 truncate">{p.sellerName}</p>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block font-semibold">CNIC No:</span>
                  <p className="font-mono font-bold text-slate-800">{p.sellerCnic || 'N/A'}</p>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block font-semibold">Phone No:</span>
                  <p className="font-mono font-bold text-slate-800">{p.sellerPhone || 'N/A'}</p>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block font-semibold">IMEI 1:</span>
                  <p className="font-mono font-bold text-slate-800 text-[11px] truncate">{p.imei1}</p>
                </div>
              </div>

              {/* Accessories Checkboxes List */}
              <div className="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 flex-wrap">
                <span className="text-slate-400">Accessories:</span>
                {p.hasBox && <span className="bg-slate-100 px-2 py-0.5 rounded text-slate-700">✓ Box</span>}
                {p.hasCharger && <span className="bg-slate-100 px-2 py-0.5 rounded text-slate-700">✓ Charger</span>}
                {p.hasCable && <span className="bg-slate-100 px-2 py-0.5 rounded text-slate-700">✓ Cable</span>}
                {p.hasHandsfree && <span className="bg-slate-100 px-2 py-0.5 rounded text-slate-700">✓ Handsfree</span>}
                {p.hasWarrantyCard && <span className="bg-slate-100 px-2 py-0.5 rounded text-slate-700">✓ Warranty/Bill</span>}
                {!p.hasBox && !p.hasCharger && !p.hasCable && !p.hasHandsfree && !p.hasWarrantyCard && (
                  <span className="text-slate-400 italic">None</span>
                )}
              </div>

              {/* Price & Actions Footer */}
              <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                <div>
                  <span className="text-[10px] text-slate-400 font-semibold block">Purchase Price Paid:</span>
                  <span className="text-base font-extrabold font-mono text-emerald-700">
                    Rs {p.purchasePrice.toLocaleString()}
                  </span>
                </div>

                <div className="flex items-center gap-1.5">
                  <button
                    onClick={() => setSelectedDetailRecord(p)}
                    className="py-1.5 px-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1 transition-colors cursor-pointer"
                    title="View Full Details & Photos"
                  >
                    <Eye className="w-3.5 h-3.5" />
                    <span>View Details</span>
                  </button>

                  <button
                    onClick={() => setSelectedReceiptRecord(p)}
                    className="py-1.5 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1 shadow-sm transition-colors cursor-pointer"
                    title="Generate Simple Printable Receipt"
                  >
                    <Printer className="w-3.5 h-3.5" />
                    <span>Receipt</span>
                  </button>

                  <button
                    onClick={() => {
                      if (confirm(`Are you sure you want to delete the record for ${p.mobileBrandModel} (${p.sellerName})?`)) {
                        onDeletePurchase(p.id);
                      }
                    }}
                    className="p-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors cursor-pointer"
                    title="Delete Record"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>

            </div>
          ))}
        </div>
      )}

      {/* ADD MOBILE PURCHASE MODAL FORM */}
      {isAddModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto">
          <div className="bg-white text-slate-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden border border-slate-200 my-auto">
            
            {/* Modal Header */}
            <div className="p-4 sm:p-5 bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 text-white flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-white/20 border border-white/30 text-white flex items-center justify-center font-bold">
                  <Smartphone className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-extrabold text-base sm:text-lg">Add New Mobile Purchase Record</h3>
                  <p className="text-xs text-emerald-100">Enter seller and mobile phone details for verification</p>
                </div>
              </div>

              <button
                onClick={() => setIsAddModalOpen(false)}
                className="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Form Fields */}
            <form onSubmit={handleSubmit} className="p-5 sm:p-6 space-y-5 max-h-[80vh] overflow-y-auto text-xs sm:text-sm">
              
              {/* SECTION 1: SELLER DETAILS */}
              <div className="space-y-3 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <div className="flex items-center gap-2 border-b border-slate-200 pb-2 text-emerald-800 font-extrabold">
                  <User className="w-4 h-4 text-emerald-600" />
                  <span>1. Seller (Customer) Details</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Seller Full Name <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={sellerName}
                      onChange={(e) => setSellerName(e.target.value)}
                      placeholder="e.g. Muhammad Hamza"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      CNIC Number <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={sellerCnic}
                      onChange={(e) => setSellerCnic(e.target.value)}
                      placeholder="e.g. 37405-1234567-1"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Mobile / Phone Number <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={sellerPhone}
                      onChange={(e) => setSellerPhone(e.target.value)}
                      placeholder="e.g. 0300-1234567"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Address / City
                    </label>
                    <input
                      type="text"
                      value={sellerAddress}
                      onChange={(e) => setSellerAddress(e.target.value)}
                      placeholder="e.g. Sarai Saleh, Haripur"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>
                </div>

                {/* Seller & CNIC Photos */}
                <div className="pt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="block text-[11px] font-bold text-slate-600 mb-1">Seller Photo</label>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleImageUpload(e, setSellerPhoto)}
                      className="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold"
                    />
                    {sellerPhoto && (
                      <img src={sellerPhoto} alt="Seller" className="w-16 h-16 rounded-xl object-cover mt-2 border border-slate-300" />
                    )}
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-slate-600 mb-1">CNIC Front Image</label>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleImageUpload(e, setCnicFrontPhoto)}
                      className="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold"
                    />
                    {cnicFrontPhoto && (
                      <img src={cnicFrontPhoto} alt="CNIC Front" className="w-20 h-12 rounded-lg object-cover mt-2 border border-slate-300" />
                    )}
                  </div>

                  <div>
                    <label className="block text-[11px] font-bold text-slate-600 mb-1">CNIC Back Image</label>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={(e) => handleImageUpload(e, setCnicBackPhoto)}
                      className="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold"
                    />
                    {cnicBackPhoto && (
                      <img src={cnicBackPhoto} alt="CNIC Back" className="w-20 h-12 rounded-lg object-cover mt-2 border border-slate-300" />
                    )}
                  </div>
                </div>

              </div>

              {/* SECTION 2: MOBILE DETAILS */}
              <div className="space-y-3 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <div className="flex items-center gap-2 border-b border-slate-200 pb-2 text-emerald-800 font-extrabold">
                  <Smartphone className="w-4 h-4 text-emerald-600" />
                  <span>2. Mobile Phone Specifications</span>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Brand & Model <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={mobileBrandModel}
                      onChange={(e) => setMobileBrandModel(e.target.value)}
                      placeholder="e.g. Vivo Y21 or Samsung A14"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Condition <span className="text-rose-500">*</span>
                    </label>
                    <select
                      value={condition}
                      onChange={(e) => setCondition(e.target.value as 'NEW' | 'USED')}
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-bold focus:outline-none focus:border-emerald-600"
                    >
                      <option value="USED">Used / Second Hand</option>
                      <option value="NEW">New / Pin Pack</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      IMEI Number 1 <span className="text-rose-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={imei1}
                      onChange={(e) => setImei1(e.target.value)}
                      placeholder="15 Digit IMEI Number"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-bold focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      IMEI Number 2 (Optional)
                    </label>
                    <input
                      type="text"
                      value={imei2}
                      onChange={(e) => setImei2(e.target.value)}
                      placeholder="Second IMEI Number"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Color
                    </label>
                    <input
                      type="text"
                      value={color}
                      onChange={(e) => setColor(e.target.value)}
                      placeholder="e.g. Black, Blue"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      RAM & Storage
                    </label>
                    <input
                      type="text"
                      value={ramStorage}
                      onChange={(e) => setRamStorage(e.target.value)}
                      placeholder="e.g. 4GB / 64GB"
                      className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      SKU / Barcode Number
                    </label>
                    <div className="flex gap-2">
                      <input
                        type="text"
                        value={sku}
                        onChange={(e) => setSku(e.target.value)}
                        placeholder="Scan or enter barcode"
                        className="flex-1 p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-bold focus:outline-none focus:border-emerald-600"
                      />
                      <button
                        type="button"
                        onClick={() => setIsScannerOpen(true)}
                        className="px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-1 transition-all cursor-pointer shadow-sm shrink-0"
                        title="Scan Barcode via Camera"
                      >
                        <Camera className="w-3.5 h-3.5" />
                        <span>Scan</span>
                      </button>
                    </div>
                  </div>
                </div>

                {/* Mobile Photo Upload */}
                <div className="pt-1">
                  <label className="block text-[11px] font-bold text-slate-600 mb-1">Mobile Phone Image</label>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={(e) => handleImageUpload(e, setMobilePhoto)}
                    className="w-full text-[11px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-bold"
                  />
                  {mobilePhoto && (
                    <img src={mobilePhoto} alt="Mobile" className="w-20 h-20 rounded-xl object-cover mt-2 border border-slate-300" />
                  )}
                </div>

              </div>

              {/* SECTION 3: INCLUDED ACCESSORIES CHECKBOXES */}
              <div className="space-y-2 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <label className="block text-xs font-extrabold text-slate-800 uppercase">
                  Accessories Provided With Mobile:
                </label>
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-2 pt-1">
                  <label className="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200 cursor-pointer text-xs font-bold text-slate-800">
                    <input
                      type="checkbox"
                      checked={hasBox}
                      onChange={(e) => setHasBox(e.target.checked)}
                      className="w-4 h-4 accent-emerald-600 rounded"
                    />
                    <span>Box</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200 cursor-pointer text-xs font-bold text-slate-800">
                    <input
                      type="checkbox"
                      checked={hasCharger}
                      onChange={(e) => setHasCharger(e.target.checked)}
                      className="w-4 h-4 accent-emerald-600 rounded"
                    />
                    <span>Charger</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200 cursor-pointer text-xs font-bold text-slate-800">
                    <input
                      type="checkbox"
                      checked={hasCable}
                      onChange={(e) => setHasCable(e.target.checked)}
                      className="w-4 h-4 accent-emerald-600 rounded"
                    />
                    <span>Cable</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200 cursor-pointer text-xs font-bold text-slate-800">
                    <input
                      type="checkbox"
                      checked={hasHandsfree}
                      onChange={(e) => setHasHandsfree(e.target.checked)}
                      className="w-4 h-4 accent-emerald-600 rounded"
                    />
                    <span>Handsfree</span>
                  </label>

                  <label className="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200 cursor-pointer text-xs font-bold text-slate-800">
                    <input
                      type="checkbox"
                      checked={hasWarrantyCard}
                      onChange={(e) => setHasWarrantyCard(e.target.checked)}
                      className="w-4 h-4 accent-emerald-600 rounded"
                    />
                    <span>Warranty / Bill</span>
                  </label>
                </div>
              </div>

              {/* SECTION 4: FINANCIAL & PAYMENT */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-2xl bg-emerald-50/50 border border-emerald-200">
                <div>
                  <label className="block text-xs font-extrabold text-slate-800 mb-1">
                    Purchase Price (Rs) <span className="text-rose-500">*</span>
                  </label>
                  <input
                    type="number"
                    required
                    value={purchasePrice}
                    onChange={(e) => setPurchasePrice(e.target.value ? Number(e.target.value) : '')}
                    placeholder="e.g. 28500"
                    className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-mono font-extrabold text-base focus:outline-none focus:border-emerald-600"
                  />
                </div>

                <div>
                  <label className="block text-xs font-extrabold text-slate-800 mb-1">
                    Payment Method
                  </label>
                  <select
                    value={paymentMethod}
                    onChange={(e) => setPaymentMethod(e.target.value as any)}
                    className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-bold focus:outline-none focus:border-emerald-600"
                  >
                    <option value="CASH">Cash</option>
                    <option value="EASYPAISA">EasyPaisa</option>
                    <option value="JAZZCASH">JazzCash</option>
                    <option value="BANK">Bank Transfer</option>
                  </select>
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Remarks / Notes
                  </label>
                  <input
                    type="text"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="e.g. Minor scratches on back, all buttons working perfectly..."
                    className="w-full p-2.5 rounded-xl bg-white border border-slate-300 text-slate-900 font-medium focus:outline-none focus:border-emerald-600"
                  />
                </div>

                {/* Auto Add to Shop Inventory Checkbox */}
                <div className="sm:col-span-2 pt-1">
                  <label className="flex items-center gap-2.5 p-3 rounded-xl bg-white border border-emerald-300 cursor-pointer shadow-sm">
                    <input
                      type="checkbox"
                      checked={autoAddToStock}
                      onChange={(e) => setAutoAddToStock(e.target.checked)}
                      className="w-5 h-5 accent-emerald-600 rounded shrink-0"
                    />
                    <div>
                      <span className="font-extrabold text-xs text-emerald-900 block">
                        Auto-add this mobile to Shop Stock Inventory (POS Stock)
                      </span>
                      <span className="text-[11px] text-slate-500">
                        When enabled, this phone will automatically appear in POS stock so you can sell it later.
                      </span>
                    </div>
                  </label>
                </div>
              </div>

              {/* Submit Buttons */}
              <div className="pt-2 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(false)}
                  className="py-3 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm cursor-pointer"
                >
                  Cancel
                </button>

                <button
                  type="submit"
                  className="py-3 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs sm:text-sm shadow-md shadow-emerald-600/20 flex items-center gap-2 cursor-pointer"
                >
                  <Check className="w-4 h-4" />
                  <span>Save Record & Print Receipt</span>
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

      {/* VIEW FULL DETAILS MODAL */}
      {selectedDetailRecord && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto">
          <div className="bg-white text-slate-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-slate-200 my-auto">
            
            <div className="p-4 bg-slate-900 text-white flex items-center justify-between">
              <h3 className="font-extrabold text-sm sm:text-base">
                Mobile Purchase Full Details ({selectedDetailRecord.receiptNo})
              </h3>
              <button
                onClick={() => setSelectedDetailRecord(null)}
                className="w-7 h-7 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-colors cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
              {/* Photos Gallery */}
              <div className="grid grid-cols-3 gap-2 text-center">
                <div>
                  <span className="text-[10px] text-slate-500 font-bold block mb-1">Seller Photo</span>
                  {selectedDetailRecord.sellerPhoto ? (
                    <img src={selectedDetailRecord.sellerPhoto} alt="Seller" className="w-full h-24 rounded-xl object-cover border" />
                  ) : (
                    <div className="w-full h-24 rounded-xl bg-slate-100 border flex items-center justify-center text-slate-400 font-bold">No Image</div>
                  )}
                </div>

                <div>
                  <span className="text-[10px] text-slate-500 font-bold block mb-1">CNIC Front</span>
                  {selectedDetailRecord.cnicFrontPhoto ? (
                    <img src={selectedDetailRecord.cnicFrontPhoto} alt="CNIC Front" className="w-full h-24 rounded-xl object-cover border" />
                  ) : (
                    <div className="w-full h-24 rounded-xl bg-slate-100 border flex items-center justify-center text-slate-400 font-bold">No Image</div>
                  )}
                </div>

                <div>
                  <span className="text-[10px] text-slate-500 font-bold block mb-1">CNIC Back</span>
                  {selectedDetailRecord.cnicBackPhoto ? (
                    <img src={selectedDetailRecord.cnicBackPhoto} alt="CNIC Back" className="w-full h-24 rounded-xl object-cover border" />
                  ) : (
                    <div className="w-full h-24 rounded-xl bg-slate-100 border flex items-center justify-center text-slate-400 font-bold">No Image</div>
                  )}
                </div>
              </div>

              {/* Data Table */}
              <div className="space-y-2 border-t border-slate-200 pt-3">
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">Seller Name:</span>
                  <span className="font-extrabold text-slate-900">{selectedDetailRecord.sellerName}</span>
                </div>
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">CNIC No:</span>
                  <span className="font-mono font-bold">{selectedDetailRecord.sellerCnic || 'N/A'}</span>
                </div>
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">Phone No:</span>
                  <span className="font-mono font-bold">{selectedDetailRecord.sellerPhone || 'N/A'}</span>
                </div>
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">Mobile Model:</span>
                  <span className="font-extrabold text-slate-900">{selectedDetailRecord.mobileBrandModel} ({selectedDetailRecord.condition === 'NEW' ? 'New' : 'Used'})</span>
                </div>
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">IMEI 1:</span>
                  <span className="font-mono font-extrabold text-slate-900">{selectedDetailRecord.imei1}</span>
                </div>
                {selectedDetailRecord.imei2 && (
                  <div className="flex justify-between border-b pb-1">
                    <span className="text-slate-500">IMEI 2:</span>
                    <span className="font-mono font-bold">{selectedDetailRecord.imei2}</span>
                  </div>
                )}
                <div className="flex justify-between border-b pb-1">
                  <span className="text-slate-500">Purchase Price:</span>
                  <span className="font-mono font-extrabold text-emerald-700 text-sm">Rs {selectedDetailRecord.purchasePrice.toLocaleString()}</span>
                </div>
                {selectedDetailRecord.notes && (
                  <div className="pt-1">
                    <span className="text-slate-500 block font-bold mb-0.5">Remarks / Notes:</span>
                    <p className="p-2 bg-slate-50 rounded-xl border text-slate-700 font-medium">{selectedDetailRecord.notes}</p>
                  </div>
                )}
              </div>

              <div className="pt-2 flex justify-end">
                <button
                  onClick={() => {
                    const r = selectedDetailRecord;
                    setSelectedDetailRecord(null);
                    setSelectedReceiptRecord(r);
                  }}
                  className="py-2 px-4 rounded-xl bg-emerald-600 text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Open Printable Receipt</span>
                </button>
              </div>

            </div>

          </div>
        </div>
      )}

      {/* PRINTABLE SIMPLE RECEIPT MODAL */}
      <SimplePurchaseReceiptModal
        record={selectedReceiptRecord}
        settings={settings}
        onClose={() => setSelectedReceiptRecord(null)}
      />

      <BarcodeScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScanSuccess={(code) => setSku(code)}
      />

    </div>
  );
};
