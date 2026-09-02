import React, { useState } from 'react';
import { Users, Search, Phone, Plus, UserCheck, FileDown, Building2, MapPin, CreditCard, Trash2, Edit3, X } from 'lucide-react';
import { Supplier, MobilePurchaseRecord, AppSettings } from '../types';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

interface SupplierLedgerProps {
  suppliers: Supplier[];
  mobilePurchases: MobilePurchaseRecord[];
  settings: AppSettings;
  onSaveSupplier: (supplier: Supplier) => void;
  onDeleteSupplier: (id: string) => void;
}

export const SupplierLedger: React.FC<SupplierLedgerProps> = ({
  suppliers,
  mobilePurchases,
  settings,
  onSaveSupplier,
  onDeleteSupplier,
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(null);

  // Form state
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [cnic, setCnic] = useState('');
  const [address, setAddress] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [openingBalance, setOpeningBalance] = useState('');

  const isLight = settings.theme === 'light';

  const filteredSuppliers = suppliers.filter(
    (s) =>
      s.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      s.phone.includes(searchTerm) ||
      (s.companyName && s.companyName.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  const handleOpenAddModal = (sup?: Supplier) => {
    if (sup) {
      setEditingSupplier(sup);
      setName(sup.name);
      setPhone(sup.phone);
      setCnic(sup.cnic || '');
      setAddress(sup.address || '');
      setCompanyName(sup.companyName || '');
      setOpeningBalance(sup.openingBalance ? sup.openingBalance.toString() : '');
    } else {
      setEditingSupplier(null);
      setName('');
      setPhone('');
      setCnic('');
      setAddress('');
      setCompanyName('');
      setOpeningBalance('');
    }
    setIsModalOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    const newSup: Supplier = {
      id: editingSupplier ? editingSupplier.id : `sup-${Date.now()}`,
      name: name.trim(),
      phone: phone.trim(),
      cnic: cnic.trim(),
      address: address.trim(),
      companyName: companyName.trim(),
      openingBalance: Number(openingBalance) || 0,
      createdAt: editingSupplier ? editingSupplier.createdAt : Date.now(),
    };

    onSaveSupplier(newSup);
    setIsModalOpen(false);
  };

  // Supplier purchases
  const supplierPurchases = selectedSupplier
    ? mobilePurchases.filter(
        (p) =>
          p.supplierId === selectedSupplier.id ||
          p.sellerName.toLowerCase() === selectedSupplier.name.toLowerCase() ||
          p.sellerPhone === selectedSupplier.phone
      )
    : [];

  const totalPurchasedValue = supplierPurchases.reduce((acc, p) => acc + p.purchasePrice, 0);
  const totalBalanceDue = (selectedSupplier?.openingBalance || 0);

  const handleDownloadStatement = () => {
    if (!selectedSupplier) return;
    const doc = new jsPDF();
    doc.setFillColor(16, 185, 129);
    doc.rect(0, 0, 210, 32, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(15);
    doc.setFont('helvetica', 'bold');
    doc.text(settings.shopName || 'Mobiles and EasyPaisa Shop POS', 105, 12, { align: 'center' });
    doc.setFontSize(11);
    doc.text(`SUPPLIER KHATA STATEMENT - ${selectedSupplier.name.toUpperCase()}`, 105, 20, { align: 'center' });
    doc.setFontSize(8);
    doc.text(`Phone: ${selectedSupplier.phone} | Company: ${selectedSupplier.companyName || 'N/A'}`, 105, 27, { align: 'center' });

    const rows = supplierPurchases.map((p, idx) => [
      idx + 1,
      `${p.date} ${p.time}`,
      `${p.mobileBrandModel} (${p.condition})`,
      `IMEI: ${p.imei1}`,
      `Rs. ${p.purchasePrice.toLocaleString()}`
    ]);

    autoTable(doc, {
      startY: 40,
      head: [['#', 'Date & Time', 'Mobile Model & Condition', 'IMEI / Serial', 'Purchase Price']],
      body: rows,
      theme: 'striped',
      headStyles: { fillColor: [16, 185, 129], textColor: 255 },
      styles: { fontSize: 8, cellPadding: 3 },
    });

    doc.save(`Supplier_Statement_${selectedSupplier.name.replace(/\s+/g, '_')}.pdf`);
  };

  return (
    <div className="space-y-4 sm:space-y-6 font-sans">
      
      {/* Header */}
      <div className={`${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border p-4 sm:p-5 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 shadow-sm`}>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 flex items-center justify-center font-bold shrink-0">
            <Building2 className="w-5 h-5" />
          </div>
          <div>
            <h2 className={`text-base sm:text-lg font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>Supplier Directory & Khata</h2>
            <p className={`text-xs ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Manage wholesale suppliers, distributor details & purchase khata statements</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <div className="relative flex-1 sm:w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
            <input
              type="text"
              placeholder="Search Supplier name, phone..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-9 pr-3 py-2 ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} border rounded-xl text-xs outline-none focus:border-emerald-500`}
            />
          </div>
          <button
            onClick={() => handleOpenAddModal()}
            className="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shrink-0 cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Add Supplier</span>
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        
        {/* Suppliers List */}
        <div className={`lg:col-span-1 ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 space-y-3 shadow-sm`}>
          <h3 className={`font-bold text-xs sm:text-sm pb-2 border-b ${isLight ? 'border-slate-200 text-slate-900' : 'border-slate-800 text-white'} flex items-center justify-between`}>
            <span>Suppliers ({filteredSuppliers.length})</span>
          </h3>

          <div className="space-y-2 max-h-[480px] overflow-y-auto pr-1">
            {filteredSuppliers.length === 0 ? (
              <p className="text-xs text-slate-500 text-center py-6">No suppliers found</p>
            ) : (
              filteredSuppliers.map((sup) => {
                const isSelected = selectedSupplier?.id === sup.id;
                return (
                  <button
                    key={sup.id}
                    onClick={() => setSelectedSupplier(sup)}
                    className={`w-full text-left p-3 rounded-xl border transition-all cursor-pointer ${
                      isSelected
                        ? isLight
                          ? 'bg-emerald-50 border-emerald-500 text-slate-900 shadow-md'
                          : 'bg-emerald-500/20 border-emerald-500 text-white shadow-lg'
                        : isLight
                          ? 'bg-slate-50 border-slate-200 hover:border-emerald-300 text-slate-800'
                          : 'bg-slate-800/60 border-slate-800 hover:border-slate-700 text-slate-300'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <span className={`font-bold text-sm ${isLight ? 'text-slate-900' : 'text-white'}`}>{sup.name}</span>
                      <span className="text-[10px] px-2 py-0.5 rounded-full font-mono font-bold bg-emerald-600/10 text-emerald-600 dark:text-emerald-400">
                        {sup.companyName || 'Wholesale'}
                      </span>
                    </div>

                    <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                      <span className="font-mono flex items-center gap-1"><Phone className="w-3 h-3" /> {sup.phone || 'No Phone'}</span>
                      <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">Dues: Rs. {sup.openingBalance.toLocaleString()}</span>
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* Selected Supplier Details & Purchases */}
        <div className={`lg:col-span-2 ${isLight ? 'bg-white border-slate-200' : 'bg-slate-900 border-slate-800'} border rounded-2xl p-4 sm:p-5 space-y-4 shadow-sm`}>
          {!selectedSupplier ? (
            <div className="flex flex-col items-center justify-center py-20 text-slate-500 text-center">
              <Building2 className="w-12 h-12 mb-3 text-slate-400" />
              <p className={`text-xs sm:text-sm font-semibold ${isLight ? 'text-slate-600' : 'text-slate-400'}`}>Select a supplier from the list to view statement & khata</p>
            </div>
          ) : (
            <div className="space-y-4">
              
              {/* Supplier Info Header */}
              <div className={`p-4 rounded-xl border ${isLight ? 'bg-slate-50 border-slate-200' : 'bg-slate-800/60 border-slate-700'} flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3`}>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className={`text-base font-bold ${isLight ? 'text-slate-900' : 'text-white'}`}>{selectedSupplier.name}</h3>
                    <span className="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">{selectedSupplier.companyName || 'Supplier'}</span>
                  </div>
                  <p className="text-xs text-slate-500 flex items-center gap-2 mt-1">
                    <span className="font-mono flex items-center gap-1"><Phone className="w-3 h-3" /> {selectedSupplier.phone}</span>
                    {selectedSupplier.cnic && <span className="font-mono">CNIC: {selectedSupplier.cnic}</span>}
                  </p>
                  {selectedSupplier.address && (
                    <p className="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                      <MapPin className="w-3 h-3" /> {selectedSupplier.address}
                    </p>
                  )}
                </div>

                <div className="flex items-center gap-2">
                  <button
                    onClick={() => handleOpenAddModal(selectedSupplier)}
                    className="p-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-800 dark:text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
                    title="Edit Supplier"
                  >
                    <Edit3 className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => {
                      if (window.confirm(`Delete supplier ${selectedSupplier.name}?`)) {
                        onDeleteSupplier(selectedSupplier.id);
                        setSelectedSupplier(null);
                      }
                    }}
                    className="p-2 bg-red-100 dark:bg-red-950/40 text-red-600 hover:bg-red-200 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    title="Delete Supplier"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                  <button
                    onClick={handleDownloadStatement}
                    className="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow cursor-pointer"
                  >
                    <FileDown className="w-4 h-4" />
                    <span>PDF Statement</span>
                  </button>
                </div>
              </div>

              {/* Summary Cards */}
              <div className="grid grid-cols-3 gap-3">
                <div className={`p-3 rounded-xl border ${isLight ? 'bg-emerald-50/50 border-emerald-100 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'}`}>
                  <p className="text-[11px] text-slate-500">Total Purchases</p>
                  <p className="text-sm font-bold font-mono mt-0.5">Rs. {totalPurchasedValue.toLocaleString()}</p>
                </div>
                <div className={`p-3 rounded-xl border ${isLight ? 'bg-blue-50/50 border-blue-100 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'}`}>
                  <p className="text-[11px] text-slate-500">Items Bought</p>
                  <p className="text-sm font-bold font-mono mt-0.5">{supplierPurchases.length} Mobiles</p>
                </div>
                <div className={`p-3 rounded-xl border ${isLight ? 'bg-amber-50/50 border-amber-100 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'}`}>
                  <p className="text-[11px] text-slate-500">Opening Balance / Dues</p>
                  <p className="text-sm font-bold font-mono text-emerald-600 mt-0.5">Rs. {totalBalanceDue.toLocaleString()}</p>
                </div>
              </div>

              {/* Purchase Statement Table */}
              <div className="space-y-2">
                <h4 className={`text-xs font-bold uppercase tracking-wider ${isLight ? 'text-slate-700' : 'text-slate-300'}`}>Purchase Statement & History</h4>
                
                <div className="overflow-x-auto max-h-[320px] overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                  <table className="w-full text-left text-xs">
                    <thead className={`${isLight ? 'bg-slate-100 text-slate-700' : 'bg-slate-800 text-slate-300'} font-bold sticky top-0`}>
                      <tr>
                        <th className="p-2.5">Date & Time</th>
                        <th className="p-2.5">Mobile Model</th>
                        <th className="p-2.5">Condition</th>
                        <th className="p-2.5">IMEI</th>
                        <th className="p-2.5 text-right">Purchase Price</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                      {supplierPurchases.length === 0 ? (
                        <tr>
                          <td colSpan={5} className="text-center py-8 text-slate-500 text-xs">No purchase records found for this supplier.</td>
                        </tr>
                      ) : (
                        supplierPurchases.map((p) => (
                          <tr key={p.id} className={`${isLight ? 'hover:bg-slate-50' : 'hover:bg-slate-800/40'}`}>
                            <td className="p-2.5 font-mono text-[11px] text-slate-500">{p.date} {p.time}</td>
                            <td className="p-2.5 font-bold">{p.mobileBrandModel}</td>
                            <td className="p-2.5">
                              <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${p.condition === 'NEW' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}>
                                {p.condition}
                              </span>
                            </td>
                            <td className="p-2.5 font-mono text-[11px]">{p.imei1}</td>
                            <td className="p-2.5 text-right font-bold font-mono text-emerald-600">Rs. {p.purchasePrice.toLocaleString()}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          )}
        </div>

      </div>

      {/* Add / Edit Supplier Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4 overflow-y-auto">
          <div className={`w-full max-w-lg ${isLight ? 'bg-white text-slate-900' : 'bg-slate-900 text-white'} border border-slate-700 rounded-2xl shadow-2xl overflow-hidden`}>
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-800">
              <h3 className="font-bold text-sm sm:text-base">
                {editingSupplier ? 'Edit Supplier Details' : 'Add New Wholesale Supplier'}
              </h3>
              <button onClick={() => setIsModalOpen(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-5 space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Supplier / Shop Name *</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Al-Madina Mobile Wholesale"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500`}
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Phone Number *</label>
                  <input
                    type="text"
                    required
                    placeholder="0300-1234567"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Company / Market</label>
                  <input
                    type="text"
                    placeholder="e.g. Hall Road Lahore"
                    value={companyName}
                    onChange={(e) => setCompanyName(e.target.value)}
                    className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500`}
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">CNIC Number</label>
                  <input
                    type="text"
                    placeholder="37405-1234567-1"
                    value={cnic}
                    onChange={(e) => setCnic(e.target.value)}
                    className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500`}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Opening Balance / Dues (Rs.)</label>
                  <input
                    type="number"
                    placeholder="0"
                    value={openingBalance}
                    onChange={(e) => setOpeningBalance(e.target.value)}
                    className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500 font-mono`}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Address / Shop Location</label>
                <input
                  type="text"
                  placeholder="Shop No 12, Main Bazaar..."
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  className={`w-full p-2.5 rounded-xl border text-xs ${isLight ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-slate-800 border-slate-700 text-white'} outline-none focus:border-emerald-500`}
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-xs font-bold border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md cursor-pointer"
                >
                  Save Supplier
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};
